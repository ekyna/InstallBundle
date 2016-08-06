<?php

namespace Ekyna\Bundle\InstallBundle\Command;

use Ekyna\Bundle\InstallBundle\Install\InstallerInterface;
use Ekyna\Bundle\InstallBundle\Install\Loader;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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
            ->addOption('installer', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'The directory or file to load installer from.')
            ->setHelp(<<<EOT
The <info>ekyna:install</info> command loads and runs installers from your bundles:

  <info>php bin/console ekyna:install</info>

You can also optionally specify the path to installers with the <info>--installer</info> option:

  <info>php bin/console ekyna:install --installer=/path/to/installer1 --installer=/path/to/installer2</info>
EOT
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $dirOrFile = $input->getOption('installer');
        if ($dirOrFile) {
            $paths = is_array($dirOrFile) ? $dirOrFile : [$dirOrFile];
        } else {
            $paths = [];
            foreach ($this->getContainer()->get('kernel')->getBundles() as $bundle) {
                $paths[] = $bundle->getPath() . '/Install';
            }
        }

        $loader = new Loader();
        foreach ($paths as $path) {
            if (is_dir($path)) {
                $loader->loadFromDirectory($path);
            }
        }

        $this->installers = $loader->getInstallers();
        if (empty($this->installers)) {
            throw new \InvalidArgumentException(
                sprintf('Could not find any installers to load in: %s', "\n\n- ".implode("\n- ", $paths))
            );
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
