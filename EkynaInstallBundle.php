<?php

declare(strict_types=1);

namespace Ekyna\Bundle\InstallBundle;

use Ekyna\Bundle\InstallBundle\DependencyInjection\Compiler\InstallerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Class EkynaInstallBundle
 * @package Ekyna\Bundle\InstallBundle
 * @author Étienne Dauvergne <contact@ekyna.com>
 */
class EkynaInstallBundle extends Bundle
{
    /**
     * @inheritDoc
     */
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new InstallerPass());
    }
}
