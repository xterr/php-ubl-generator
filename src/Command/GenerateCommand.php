<?php declare(strict_types=1);

namespace Xterr\UBL\Generator\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Xterr\UBL\Generator\Config\GeneratorConfig;
use Xterr\UBL\Generator\UblGenerator;

#[AsCommand(
    name: 'ubl:generate',
    description: 'Generate PHP classes from UBL XSD schemas',
)]
final class GenerateCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to YAML configuration file')
            ->addOption('schema-dir', 's', InputOption::VALUE_REQUIRED, 'Path to XSD schema directory (must contain maindoc/ and common/ subdirs)')
            ->addOption('schema-version', null, InputOption::VALUE_REQUIRED, 'UBL schema version (2.1, 2.2, 2.3, 2.4) — only used with bundled schemas')
            ->addOption('output-dir', 'o', InputOption::VALUE_REQUIRED, 'Output directory for generated classes')
            ->addOption('namespace', null, InputOption::VALUE_REQUIRED, 'Root PHP namespace')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Actually generate files (without this, shows dry-run only)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('UBL PHP Code Generator');

        $configPath = $input->getOption('config');
        if (is_string($configPath) && file_exists($configPath)) {
            $config = GeneratorConfig::fromYaml($configPath);
        } else {
            $config = GeneratorConfig::defaults();
        }

        $overrides = [];
        if ($input->getOption('schema-dir') !== null) {
            $overrides['schema_dir'] = $input->getOption('schema-dir');
        }
        if ($input->getOption('schema-version') !== null) {
            $overrides['schema_version'] = $input->getOption('schema-version');
        }
        if ($input->getOption('output-dir') !== null) {
            $overrides['output_dir'] = $input->getOption('output-dir');
        }
        if ($input->getOption('namespace') !== null) {
            $overrides['namespace'] = $input->getOption('namespace');
        }
        if ($overrides !== []) {
            $config = $config->withOverrides($overrides);
        }

        $io->section('Resolved Configuration');
        $io->definitionList(
            ['Schema Version' => $config->schemaVersion],
            ['Schema Directory' => $config->resolveSchemaDir()],
            ['Output Directory' => $config->outputDir],
            ['Namespace' => $config->namespace],
            ['Validation' => $config->generateValidation ? 'enabled' : 'disabled'],
        );

        if (!$input->getOption('force')) {
            $io->warning('Dry-run mode. Use --force to actually generate files.');

            $generator = new UblGenerator($config);
            $result = $generator->resolve(function (string $stage, int $current, int $total) use ($io): void {
                $io->text('  ' . $stage . '...');
            });

            $io->section('Would Generate');
            $io->text($result->summary());

            return Command::SUCCESS;
        }

        $generator = new UblGenerator($config);
        $result = $generator->generate(function (string $stage, int $current, int $total) use ($io): void {
            $io->text('  ' . $stage . '...');
        });

        // TODO: Wire emitters to actually write files when ClassEmitter integration is complete
        $io->section('Generation Result');
        $io->success($result->summary());

        return Command::SUCCESS;
    }
}
