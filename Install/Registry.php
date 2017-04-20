<?php

declare(strict_types=1);

namespace Ekyna\Bundle\InstallBundle\Install;

use Psr\Container\ContainerInterface;

/**
 * Class Registry
 * @package Ekyna\Bundle\InstallBundle\Install
 * @author  Étienne Dauvergne <contact@ekyna.com>
 */
class Registry
{
    private ContainerInterface $container;
    private array              $names;


    /**
     * Constructor.
     *
     * @param ContainerInterface $container
     * @param array              $names
     */
    public function __construct(ContainerInterface $container, array $names)
    {
        $this->container = $container;
        $this->names = $names;
    }

    /**
     * Returns the installers.
     *
     * @param array $names
     *
     * @return InstallerInterface[]
     */
    public function getInstallers(array $names = []): array
    {
        if (empty($names)) {
            $names = $this->names;
        }

        $installers = [];

        foreach ($names as $name) {
            $installers[] = $this->container->get($name);
        }

        return $installers;
    }
}
