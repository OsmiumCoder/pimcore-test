<?php

namespace App\Command;

use Psy\Configuration;
use Psy\Shell;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(
    name: 'tinker',
    description: 'Interactive shell (Tinker)',
)]
class TinkerCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $application = $this->getApplication();
        /** @var KernelInterface $kernel */
        $kernel = (method_exists($application, 'getKernel')) ? $application->getKernel() : null;
        
        if (!$kernel) {
            $output->writeln('<error>Kernel not found.</error>');
            return Command::FAILURE;
        }

        $container = $kernel->getContainer();

        $config = new Configuration();
        $psyshDir = $kernel->getProjectDir() . '/var/psysh';
        if (!is_dir($psyshDir)) {
            @mkdir($psyshDir, 0777, true);
        }
        
        if (is_writable($psyshDir)) {
            $config->setDataDir($psyshDir);
            $config->setConfigDir($psyshDir);
            $config->setRuntimeDir($psyshDir);
            $config->setHistoryFile($psyshDir . '/psysh_history');
        }

        $shell = new Shell($config);

        $output->writeln('<info>Psy Shell (' . \Psy\Shell::VERSION . ') by Justin Hileman</info>');
        $output->writeln('<comment>Type "help" for a list of available commands.</comment>');
        $output->writeln('<comment>Variables: $container, $kernel, $doctrine, $em</comment>');
        $output->writeln('<comment>Top-level DataObjects (Product, etc.) are auto-aliased.</comment>');
        $output->writeln('<comment>Example: $list = new Product\Listing();</comment>');

        $scopeVariables = [
            'container' => $container,
            'kernel' => $kernel,
        ];

        // Add common services if available
        if ($container->has('doctrine')) {
            $scopeVariables['doctrine'] = $container->get('doctrine');
            if (method_exists($scopeVariables['doctrine'], 'getManager')) {
                $scopeVariables['em'] = $scopeVariables['doctrine']->getManager();
            }
        }

        $shell->setScopeVariables($scopeVariables);

        // Auto-alias DataObjects for easier access
        $dataObjectNamespace = 'Pimcore\Model\DataObject';
        $dataObjectDir = $kernel->getProjectDir() . '/var/classes/DataObject';
        if (is_dir($dataObjectDir)) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dataObjectDir));
            $aliases = [];
            foreach ($files as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $relativePath = str_replace([$dataObjectDir, '.php', '/'], ['', '', '\\'], $file->getRealPath());
                    $className = ltrim($relativePath, '\\');
                    if (strpos($className, '\\') === false) {
                        $aliases[] = "use $dataObjectNamespace\\$className;";
                    }
                }
            }
            if (!empty($aliases)) {
                $shell->addCode(implode(' ', $aliases));
            }
        }

        $shell->run();

        return Command::SUCCESS;
    }
}
