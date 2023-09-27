<?php

declare(strict_types=1);

namespace Ekyna\Bundle\InstallBundle\Command;

use Ekyna\Bundle\InstallBundle\Install\InstallerInterface;
use Ekyna\Bundle\InstallBundle\Install\Registry;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class InstallCommand
 * @package Ekyna\Bundle\InstallBundle\Command
 * @author  Étienne Dauvergne <contact@ekyna.com>
 */
class InstallCommand extends Command
{
    protected static $defaultName = 'ekyna:install';

    private Registry $registry;
    /** @var InstallerInterface[] */
    private array $installers;

    /**
     * Constructor.
     *
     * @param Registry $registry
     */
    public function __construct(Registry $registry)
    {
        parent::__construct();

        $this->registry = $registry;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Runs the installers.')
            ->addArgument('names', InputArgument::IS_ARRAY, 'The names of the installers to execute.')
            ->setHelp(<<<EOT
The <info>ekyna:install</info> command installers:

  <info>php bin/console ekyna:install</info>

You can also optionally specify the names of the installers :

  <info>php bin/console ekyna:install admin cms commerce</info>
EOT
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $names = $input->getArgument('names');

        $this->installers = $this->registry->getInstallers($names);

        if (empty($this->installers)) {
            $message = 'Could not find any installers';
            if (!empty($names)) {
                $message .= " for names: '" . implode(', ', $names) . "'";
            }
            throw new InvalidArgumentException($message);
        }

        foreach ($this->installers as $installer) {
            $installer->initialize($this, $input, $output);
        }
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        foreach ($this->installers as $installer) {
            $installer->interact($this, $input, $output);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach ($this->installers as $installer) {
            $installer->install($this, $input, $output);
        }

        return Command::SUCCESS;
    }
}
