<?php

namespace Ekyna\Bundle\InstallBundle\Install;

/**
 * Interface OrderedInstallerInterface
 * @package Ekyna\Bundle\InstallBundle\Install
 * @author Étienne Dauvergne <contact@ekyna.com>
 */
interface OrderedInstallerInterface extends InstallerInterface
{
    /**
     * Get the order of this fixture
     *
     * @return integer
     */
    public function getOrder();
}
