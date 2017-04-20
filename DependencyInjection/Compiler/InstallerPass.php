<?php

declare(strict_types=1);

namespace Ekyna\Bundle\InstallBundle\DependencyInjection\Compiler;

use Ekyna\Bundle\InstallBundle\Install\InstallerInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;

use function call_user_func;
use function is_subclass_of;

/**
 * Class InstallerPass
 * @package Ekyna\Bundle\InstallBundle\DependencyInjection\Compiler
 * @author  Étienne Dauvergne <contact@ekyna.com>
 */
class InstallerPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    private const INSTALLER_TAG = 'ekyna_install.installer';

    /**
     * @inheritDoc
     */
    public function process(ContainerBuilder $container)
    {
        $map = $names = [];

        foreach ($this->findAndSortTaggedServices(self::INSTALLER_TAG, $container) as $reference) {
            $serviceId = (string)$reference;
            $class = $container->getDefinition($serviceId)->getClass();

            if (!is_subclass_of($class, InstallerInterface::class)) {
                throw new LogicException("Class $class must implements " . InstallerInterface::class);
            }

            $names[] = $name = call_user_func([$class, 'getName']);

            $map[$name] = new Reference($serviceId);
        }

        if (empty($map)) {
            return;
        }

        $container
            ->getDefinition('ekyna_install.registry')
            ->replaceArgument(0, ServiceLocatorTagPass::register($container, $map, 'ekyna_install'))
            ->replaceArgument(1, $names);
    }
}
