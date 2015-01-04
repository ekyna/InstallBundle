<?php

namespace Ekyna\Bundle\InstallBundle\Install;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Interface InstallerInterface
 * @package Ekyna\Bundle\InstallBundle\Install
 * @author Étienne Dauvergne <contact@ekyna.com>
 */
interface InstallerInterface
{
    /**
     * Performs installation.
     *
     * @param ContainerInterface $container
     * @param Command            $command
     * @param InputInterface     $input
     * @param OutputInterface    $output
     * @return void
     */
    public function install(ContainerInterface $container, Command $command, InputInterface $input, OutputInterface $output);
}
