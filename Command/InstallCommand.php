<?php

namespace Ekyna\Bundle\InstallBundle\Command;

use Ekyna\Bundle\InstallBundle\Install\InstallerInterface;
use Ekyna\Bundle\InstallBundle\Install\Loader;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;

/**
 * Class InstallCommand
 * @package Ekyna\Bundle\CoreBundle\Command
 * @author Étienne Dauvergne <contact@ekyna.com>
 */
class InstallCommand extends ContainerAwareCommand
{
    /**
     * @var InstallerInterface[]
     */
    private $installers;


    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('ekyna:install')
            ->setDescription('Runs the bundle\'s installers.')
            ->addArgument('names', InputArgument::IS_ARRAY, 'The names of the installers to execute.')
            ->setHelp(<<<EOT
The <info>ekyna:install</info> command loads and runs installers from your bundles:

  <info>php bin/console ekyna:install</info>

You can also optionally specify the names of the installers :

  <info>php bin/console ekyna:install admin cms commerce</info>
EOT
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $names = $input->getArgument('names');

        $loader = new Loader($names);
        foreach ($this->getContainer()->get('kernel')->getBundles() as $bundle) {
            $path = $bundle->getPath() . '/Install';
            if (is_dir($path)) {
                $loader->loadFromDirectory($path);
            }
        }

        $this->installers = $loader->getInstallers();
        if (empty($this->installers)) {
            $message = "Could not find any installers";
            if (!empty($names)) {
                $message .= " for names: '" . implode(', ', $names) . "'";
            }
            throw new \InvalidArgumentException($message);
        }

        foreach ($this->installers as $installer) {
            if ($installer instanceOf ContainerAwareInterface) {
                $installer->setContainer($this->getContainer());
            }
            $installer->initialize($this, $input, $output);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        foreach ($this->installers as $installer) {
            $installer->interact($this, $input, $output);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        foreach ($this->installers as $installer) {
            $installer->install($this, $input, $output);
        }
    }
}
