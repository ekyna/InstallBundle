<?php

declare(strict_types=1);

namespace Ekyna\Bundle\InstallBundle\DependencyInjection;

use Ekyna\Bundle\InstallBundle\Command\InstallCommand;
use Ekyna\Bundle\InstallBundle\Install\Registry;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * Class EkynaInstallExtension
 * @package Ekyna\Bundle\InstallBundle\DependencyInjection
 * @author  Étienne Dauvergne <contact@ekyna.com>
 */
class EkynaInstallExtension extends Extension
{
    /**
     * @inheritDoc
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $container
            ->register('ekyna_install.registry', Registry::class)
            /** Replaced by {@link \Ekyna\Bundle\InstallBundle\DependencyInjection\Compiler\InstallerPass}. */
            ->setArguments([new ServiceLocator([]), []]);

            $container
                ->register('ekyna_install.command.install', InstallCommand::class)
                ->setArgument(0, new Reference('ekyna_install.registry'))
                ->addTag('console.command');
    }
}
