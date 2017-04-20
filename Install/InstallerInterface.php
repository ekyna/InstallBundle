<?php

declare(strict_types=1);

namespace Ekyna\Bundle\InstallBundle\Install;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Interface InstallerInterface
 * @package Ekyna\Bundle\InstallBundle\Install
 * @author  Étienne Dauvergne <contact@ekyna.com>
 */
interface InstallerInterface
{
    /**
     * Performs interaction.
     *
     * @param Command         $command
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    public function initialize(Command $command, InputInterface $input, OutputInterface $output): void;

    /**
     * Performs interaction.
     *
     * @param Command         $command
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    public function interact(Command $command, InputInterface $input, OutputInterface $output): void;

    /**
     * Performs installation.
     *
     * @param Command         $command
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    public function install(Command $command, InputInterface $input, OutputInterface $output): void;

    /**
     * Returns the installer name.
     *
     * @return string
     */
    public static function getName(): string;
}
