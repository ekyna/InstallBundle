<?php

namespace Ekyna\Bundle\InstallBundle\Install;

/**
 * Class Loader
 * @package Ekyna\Bundle\InstallBundle\Install
 * @author Étienne Dauvergne <contact@ekyna.com>
 * @author Jonathan H. Wage <jonwage@gmail.com>
 * @see Doctrine\Common\DataFixtures\Loader
 */
class Loader
{
    /**
     * @var array
     */
    private $installers = [];

    /**
     * Array of ordered installer object instances.
     *
     * @var array
     */
    private $orderedInstallers = array();

    /**
     * Determines if we must order installers by number
     *
     * @var boolean
     */
    private $orderInstallersByNumber = false;

    /**
     * Determines if we must order installers by its dependencies
     *
     * @var boolean
     */
    private $orderInstallersByDependencies = false;

    /**
     * The file extension of installer files.
     *
     * @var string
     */
    private $fileExtension = '.php';


    /**
     * Finds installer classes in a given directory and load them.
     *
     * @param string $dir Directory to find installer classes in.
     * @return array $installers Array of loaded installer object instances.
     */
    public function loadFromDirectory($dir)
    {
        if (!is_dir($dir)) {
            throw new \InvalidArgumentException(sprintf('"%s" does not exist', $dir));
        }

        $installers = array();
        $includedFiles = array();

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if (($fileName = $file->getBasename($this->fileExtension)) == $file->getBasename()) {
                continue;
            }
            $sourceFile = realpath($file->getPathName());
            require_once $sourceFile;
            $includedFiles[] = $sourceFile;
        }
        $declared = get_declared_classes();

        foreach ($declared as $className) {
            $refClass = new \ReflectionClass($className);
            $sourceFile = $refClass->getFileName();

            if (in_array($sourceFile, $includedFiles) && !$this->isTransient($className)) {
                $installer = new $className;
                $installers[] = $installer;
                $this->addInstaller($installer);
            }
        }

        return $installers;
    }

    /**
     * Check if the given installer is transient and should not be considered an installer class.
     *
     * @param string $className
     * @return boolean
     */
    public function isTransient($className)
    {
        $rc = new \ReflectionClass($className);
        if ($rc->isAbstract()) return true;

        $interfaces = class_implements($className);
        return in_array('Ekyna\Bundle\InstallBundle\Install\InstallerInterface', $interfaces) ? false : true;
    }

    /**
     * Adds the installer object instance to the loader.
     *
     * @param InstallerInterface $installer
     */
    public function addInstaller(InstallerInterface $installer)
    {
        $installerClass = get_class($installer);

        if (!isset($this->installers[$installerClass])) {
            if ($installer instanceof OrderedInstallerInterface && $installer instanceof DependentInstallerInterface) {
                throw new \InvalidArgumentException(sprintf('Class "%s" can\'t implement "%s" and "%s" at the same time.',
                    $installerClass,
                    'OrderedInstallerInterface',
                    'DependentInstallerInterface'));
            } elseif ($installer instanceof OrderedInstallerInterface) {
                $this->orderInstallersByNumber = true;
            } elseif ($installer instanceof DependentInstallerInterface) {
                $this->orderInstallersByDependencies = true;
                foreach ($installer->getDependencies() as $class) {
                    $this->addInstaller(new $class);
                }
            }

            $this->installers[$installerClass] = $installer;
        }
    }

    /**
     * Returns the array of data installers to execute.
     *
     * @return array|InstallerInterface[] $installers
     */
    public function getInstallers()
    {
        $this->orderedInstallers = array();

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
     * @todo maybe there is a better way to handle reordering
     * @return void
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
    private function orderInstallersByDependencies()
    {
        $sequenceForClasses = array();

        // If installers were already ordered by number then we need
        // to remove classes which are not instances of OrderedInstallerInterface
        // in case installers implementing DependentInstallerInterface exist.
        // This is because, in that case, the method orderInstallersByDependencies
        // will handle all installers which are not instances of
        // OrderedInstallerInterface
        if ($this->orderInstallersByNumber) {
            $count = count($this->orderedInstallers);

            for ($i = 0; $i < $count; ++$i) {
                if (!($this->orderedInstallers[$i] instanceof OrderedInstallerInterface)) {
                    unset($this->orderedInstallers[$i]);
                }
            }
        }

        // First we determine which classes has dependencies and which don't
        foreach ($this->installers as $installer) {
            $installerClass = get_class($installer);

            if ($installer instanceof OrderedInstallerInterface) {
                continue;
            } elseif ($installer instanceof DependentInstallerInterface) {
                $dependenciesClasses = $installer->getDependencies();

                $this->validateDependencies($dependenciesClasses);

                if (!is_array($dependenciesClasses) || empty($dependenciesClasses)) {
                    throw new \InvalidArgumentException(sprintf(
                        'Method "%s" in class "%s" must return an array of classes which are '.
                            'dependencies for the installer, and it must be NOT empty.',
                        'getDependencies',
                        $installerClass
                    ));
                }

                if (in_array($installerClass, $dependenciesClasses)) {
                    throw new \InvalidArgumentException(sprintf(
                        'Class "%s" can\'t have itself as a dependency',
                        $installerClass
                    ));
                }

                // We mark this class as unsequenced
                $sequenceForClasses[$installerClass] = -1;
            } else {
                // This class has no dependencies, so we assign 0
                $sequenceForClasses[$installerClass] = 0;
            }
        }

        // Now we order installers by sequence
        $sequence = 1;
        $lastCount = -1;

        while (($count = count($unsequencedClasses = $this->getUnsequencedClasses($sequenceForClasses))) > 0
                && $count !== $lastCount) {
            foreach ($unsequencedClasses as $key => $class) {
                /** @var DependentInstallerInterface $installer */
                $installer = $this->installers[$class];
                $dependencies = $installer->getDependencies();
                $unsequencedDependencies = $this->getUnsequencedClasses($sequenceForClasses, $dependencies);

                if (count($unsequencedDependencies) === 0) {
                    $sequenceForClasses[$class] = $sequence++;
                }
            }

            $lastCount = $count;
        }

        $orderedInstallers = array();

        // If there are installers unsequenced left and they couldn't be sequenced,
        // it means we have a circular reference
        if ($count > 0) {
            $msg = 'Classes "%s" have produced a circular reference exception. ';
            $msg .= 'An example of this problem would be the following: Class C has class B as its dependency. ';
            $msg .= 'Then, class B has class A has its dependency. Finally, class A has class C as its dependency. ';
            $msg .= 'This case would produce a circular reference exception.';

            throw new \RuntimeException(sprintf($msg, implode(',', $unsequencedClasses)));
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

    private function validateDependencies($dependenciesClasses)
    {
        $loadedInstallerClasses = array_keys($this->installers);

        foreach ($dependenciesClasses as $class) {
            if (!in_array($class, $loadedInstallerClasses)) {
                throw new \RuntimeException(sprintf(
                    'Installer "%s" was declared as a dependency, but it should be added in installer loader first.',
                    $class
                ));
            }
        }

        return true;
    }

    private function getUnsequencedClasses($sequences, $classes = null)
    {
        $unsequencedClasses = array();

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
