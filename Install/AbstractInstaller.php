<?php

declare(strict_types=1);

namespace Ekyna\Bundle\InstallBundle\Install;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class AbstractInstaller
 * @package Ekyna\Bundle\InstallBundle\Install
 * @author  Etienne Dauvergne <contact@ekyna.com>
 */
abstract class AbstractInstaller implements InstallerInterface
{
    /**
     * @inheritDoc
     */
    public function initialize(Command $command, InputInterface $input, OutputInterface $output): void
    {
    }

    /**
     * @inheritDoc
     */
    public function interact(Command $command, InputInterface $input, OutputInterface $output): void
    {
    }

    /**
     * @inheritDoc
     */
    public function install(Command $command, InputInterface $input, OutputInterface $output): void
    {
    }
}
