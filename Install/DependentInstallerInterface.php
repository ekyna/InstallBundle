<?php

namespace Ekyna\Bundle\InstallBundle\Install;

/**
 * Interface DependentInstallerInterface
 * @package Ekyna\Bundle\InstallBundle\Install
 * @author Étienne Dauvergne <contact@ekyna.com>
 */
interface DependentInstallerInterface extends InstallerInterface
{
    /**
     * This method must return an array of installer classes
     * on which the implementing class depends on
     *
     * @return array
     */
    public function getDependencies();
}