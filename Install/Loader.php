<?php

declare(strict_types=1);

namespace Ekyna\Bundle\InstallBundle\Install;

use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use RuntimeException;

use function array_keys;
use function array_merge;
use function asort;
use function count;
use function get_class;
use function get_declared_classes;
use function in_array;
use function is_dir;
use function is_subclass_of;
use function realpath;
use function sprintf;

/**
 * Class Loader
 * @package Ekyna\Bundle\InstallBundle\Install
 * @author  Étienne Dauvergne <contact@ekyna.com>
 *
 * @deprecated
 * @TODO Remove
 */
class Loader
{
    private array $names;
    private array $installers = [];

    /** Array of ordered installer object instances. */
    private array $orderedInstallers = [];

    /** Determines if we must order installers by number */
    private bool $orderInstallersByNumber = false;

    /** Determines if we must order installers by its dependencies */
    private bool $orderInstallersByDependencies = false;

    /** The file extension of installer files. */
    private string $fileExtension = '.php';


    /**
     * Constructor.
     *
     * @param string[] $names
     */
    public function __construct(array $names = [])
    {
        $this->names = $names;
    }

    /**
     * Finds installer classes in a given directory and load them.
     *
     * @param string $dir Directory to find installer classes in.
     *
     * @noinspection PhpDocMissingThrowsInspection
     */
    public function loadFromDirectory(string $dir)
    {
        if (!is_dir($dir)) {
            throw new InvalidArgumentException(sprintf('"%s" does not exist', $dir));
        }

        $includedFiles = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if (($fileName = $file->getBasename($this->fileExtension)) == $file->getBasename()) {
                continue;
            }
            $sourceFile = realpath($file->getPathName());
            /** @noinspection PhpIncludeInspection */
            require_once $sourceFile;
            $includedFiles[] = $sourceFile;
        }

        $declared = get_declared_classes();
        foreach ($declared as $className) {
            /** @noinspection PhpUnhandledExceptionInspection */
            $refClass = new ReflectionClass($className);
            $sourceFile = $refClass->getFileName();

            if (in_array($sourceFile, $includedFiles, true) && !$this->isTransient($className)) {
                /** @var InstallerInterface $installer */
                $installer = new $className();

                if (!empty($this->names) && !in_array($installer->getName(), $this->names, true)) {
                    continue;
                }

                $this->addInstaller($installer);
            }
        }
    }

    /**
     * Check if the given installer is transient and should not be considered an installer class.
     *
     * @param string $className
     *
     * @return bool
     *
     * @noinspection PhpDocMissingThrowsInspection
     */
    public function isTransient(string $className): bool
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $rc = new ReflectionClass($className);
        if ($rc->isAbstract()) {
            return true;
        }

        return !is_subclass_of($className, InstallerInterface::class);
    }

    /**
     * Adds the installer object instance to the loader.
     *
     * @param InstallerInterface $installer
     */
    public function addInstaller(InstallerInterface $installer): void
    {
        $installerClass = get_class($installer);

        if (!isset($this->installers[$installerClass])) {
            if ($installer instanceof OrderedInstallerInterface) {
                if ($installer instanceof DependentInstallerInterface) {
                    throw new InvalidArgumentException(sprintf('Class "%s" can\'t implement "%s" and "%s" at the same time.',
                        $installerClass,
                        'OrderedInstallerInterface',
                        'DependentInstallerInterface'));
                }

                $this->orderInstallersByNumber = true;
            } elseif ($installer instanceof DependentInstallerInterface) {
                $this->orderInstallersByDependencies = true;
                foreach ($installer->getDependencies() as $class) {
                    $this->addInstaller(new $class());
                }
            }

            $this->installers[$installerClass] = $installer;
        }
    }

    /**
     * Returns the array of data installers to execute.
     *
     * @return InstallerInterface[] $installers
     */
    public function getInstallers(): array
    {
        $this->orderedInstallers = [];

        if ($this->orderInstallersByNumber) {
            $this->orderInstallersByNumber();
        }

        if ($this->orderInstallersByDependencies) {
            $this->orderInstallersByDependencies();
        }

        if (!$this->orderInstallersByNumber && !$this->orderInstallersByDependencies) {
            $this->orderedInstallers = $this->installers;
        }

        return $this->orderedInstallers;
    }

    /**
     * Orders installers by number
     *
     * @return void
     * @todo maybe there is a better way to handle reordering
     */
    private function orderInstallersByNumber()
    {
        $this->orderedInstallers = $this->installers;

        usort($this->orderedInstallers, function ($a, $b) {
            if ($a instanceof OrderedInstallerInterface && $b instanceof OrderedInstallerInterface) {
                if ($a->getOrder() === $b->getOrder()) {
                    return 0;
                }

                return $a->getOrder() < $b->getOrder() ? -1 : 1;
            } elseif ($a instanceof OrderedInstallerInterface) {
                return $a->getOrder() === 0 ? 0 : 1;
            } elseif ($b instanceof OrderedInstallerInterface) {
                return $b->getOrder() === 0 ? 0 : -1;
            }

            return 0;
        });
    }

    /**
     * Orders installers by dependencies
     *
     * @return void
     */
    private function orderInstallersByDependencies(): void
    {
        $sequenceForClasses = [];

        // If installers were already ordered by number then we need
        // to remove classes which are not instances of OrderedInstallerInterface
        // in case installers implementing DependentInstallerInterface exist.
        // This is because, in that case, the method orderInstallersByDependencies
        // will handle all installers which are not instances of
        // OrderedInstallerInterface
        if ($this->orderInstallersByNumber) {
            $count = count($this->orderedInstallers);

            for ($i = 0; $i < $count; ++$i) {
                if (!$this->orderedInstallers[$i] instanceof OrderedInstallerInterface) {
                    unset($this->orderedInstallers[$i]);
                }
            }
        }

        // First we determine which classes has dependencies and which don't
        foreach ($this->installers as $installer) {
            $installerClass = get_class($installer);

            if ($installer instanceof OrderedInstallerInterface) {
                continue;
            }

            if ($installer instanceof DependentInstallerInterface) {
                $dependenciesClasses = $installer->getDependencies();

                $this->validateDependencies($dependenciesClasses);

                if (!is_array($dependenciesClasses) || empty($dependenciesClasses)) {
                    throw new InvalidArgumentException(sprintf(
                        'Method "%s" in class "%s" must return an array of classes which are ' .
                        'dependencies for the installer, and it must be NOT empty.',
                        'getDependencies',
                        $installerClass
                    ));
                }

                if (in_array($installerClass, $dependenciesClasses, true)) {
                    throw new InvalidArgumentException(sprintf(
                        'Class "%s" can\'t have itself as a dependency',
                        $installerClass
                    ));
                }

                // We mark this class as unsequenced
                $sequenceForClasses[$installerClass] = -1;

                continue;
            }

            // This class has no dependencies, so we assign 0
            $sequenceForClasses[$installerClass] = 0;
        }

        // Now we order installers by sequence
        $sequence = 1;
        $lastCount = -1;

        while (
            0 < ($count = count($unsequencedClasses = $this->getUnsequencedClasses($sequenceForClasses)))
            && $count !== $lastCount
        ) {
            foreach ($unsequencedClasses as $key => $class) {
                /** @var DependentInstallerInterface $installer */
                $installer = $this->installers[$class];
                $dependencies = $installer->getDependencies();
                $unsequencedDependencies = $this->getUnsequencedClasses($sequenceForClasses, $dependencies);

                if (0 === count($unsequencedDependencies)) {
                    $sequenceForClasses[$class] = $sequence++;
                }
            }

            $lastCount = $count;
        }

        $orderedInstallers = [];

        // If there are installers unsequenced left and they couldn't be sequenced,
        // it means we have a circular reference
        if ($count > 0) {
            $msg = 'Classes "%s" have produced a circular reference exception. ';
            $msg .= 'An example of this problem would be the following: Class C has class B as its dependency. ';
            $msg .= 'Then, class B has class A has its dependency. Finally, class A has class C as its dependency. ';
            $msg .= 'This case would produce a circular reference exception.';

            throw new RuntimeException(sprintf($msg, implode(',', $unsequencedClasses)));
        } else {
            // We order the classes by sequence
            asort($sequenceForClasses);

            foreach ($sequenceForClasses as $class => $sequence) {
                // If installers were ordered
                $orderedInstallers[] = $this->installers[$class];
            }
        }

        $this->orderedInstallers = array_merge($this->orderedInstallers, $orderedInstallers);
    }

    /**
     * Validates the installer dependencies.
     *
     * @param string[] $dependenciesClasses
     *
     * @return bool
     */
    private function validateDependencies(array $dependenciesClasses): bool
    {
        $loadedInstallerClasses = array_keys($this->installers);

        foreach ($dependenciesClasses as $class) {
            if (!in_array($class, $loadedInstallerClasses, true)) {
                throw new RuntimeException(sprintf(
                    'Installer "%s" was declared as a dependency, but it should be added in installer loader first.',
                    $class
                ));
            }
        }

        return true;
    }

    /**
     * Returns unsequenced classes.
     *
     * @param array      $sequences
     * @param array|null $classes
     *
     * @return array
     */
    private function getUnsequencedClasses(array $sequences, array $classes = null): array
    {
        $unsequencedClasses = [];

        if (is_null($classes)) {
            $classes = array_keys($sequences);
        }

        foreach ($classes as $class) {
            if ($sequences[$class] === -1) {
                $unsequencedClasses[] = $class;
            }
        }

        return $unsequencedClasses;
    }
}
