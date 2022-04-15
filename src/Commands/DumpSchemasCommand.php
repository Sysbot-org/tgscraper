<?php

namespace TgScraper\Commands;

use Exception;
use FilesystemIterator;
use PharData;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use TgScraper\Common\Encoder;
use TgScraper\Constants\Versions;
use TgScraper\TgScraper;
use Throwable;

class DumpSchemasCommand extends Command
{

    use Common;

    protected static $defaultName = 'app:dump-schemas';

    protected static function rrmdir(string $directory): void
    {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileInfo) {
            $todo = ($fileInfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileInfo->getRealPath());
        }
        rmdir($directory);
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Export all schemas and stubs to a directory.')
            ->setHelp('This command allows you to generate the schemas for all versions of the Telegram bot API.')
            ->addArgument('destination', InputArgument::REQUIRED, 'Destination directory')
            ->addOption(
                'namespace-prefix',
                null,
                InputOption::VALUE_REQUIRED,
                'Namespace prefix for stubs',
                'TelegramApi'
            )
            ->addOption(
                'readable',
                'r',
                InputOption::VALUE_NONE,
                'Generate human-readable files'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $versionReplacer = function (string $ver) {
            /** @noinspection PhpUndefinedFieldInspection */
            $this->version = $ver;
        };
        $logger = new ConsoleLogger($output);
        $destination = $input->getArgument('destination');
        $readable = $input->getOption('readable');
        $output->writeln('Creating directory tree...');
        try {
            $destination = TgScraper::getTargetDirectory($destination);
            mkdir($destination . '/custom/json', 0755, true);
            mkdir($destination . '/custom/yaml', 0755, true);
            mkdir($destination . '/postman', 0755, true);
            mkdir($destination . '/openapi/json', 0755, true);
            mkdir($destination . '/openapi/yaml', 0755, true);
            mkdir($destination . '/stubs', 0755, true);
        } catch (Exception $e) {
            $logger->critical((string)$e);
            return Command::FAILURE;
        }
        $versions = array_keys(
            (new ReflectionClass(Versions::class))
                ->getConstants()['URLS']
        );
        $versions = array_diff($versions, ['latest']);
        foreach ($versions as $version) {
            $output->writeln(sprintf('Generating v%s schemas...', $version));
            $filename = 'v' . str_replace('.', '', $version);
            try {
                $logger->info($version . ': Fetching data...');
                $generator = TgScraper::fromVersion($logger, $version);
            } catch (Throwable $e) {
                $logger->critical((string)$e);
                return Command::FAILURE;
            }
            $versionReplacer->call($generator, $version);
            $custom = $generator->toArray();
            $postman = $generator->toPostman();
            $openapi = $generator->toOpenApi();
            try {
                $logger->info($version . ': Creating stubs...');
                $generator->toStubs("$destination/tmp", $input->getOption('namespace-prefix'));
            } catch (Exception) {
                $logger->critical($version . ': Could not create stubs.');
                return Command::FAILURE;
            }
            $logger->info($version . ': Compressing stubs...');
            $zip = new PharData("$destination/stubs/$filename.zip");
            $zip->buildFromDirectory("$destination/tmp");
            self::rrmdir("$destination/tmp");
            $logger->info($version . ': Saving schemas...');
            if ($this->saveFile(
                    $logger,
                    $output,
                    "$destination/custom/json/$filename.json",
                    Encoder::toJson($custom, readable: $readable),
                    sprintf('v%s custom (JSON): ', $version)
                ) !== Command::SUCCESS) {
                return Command::FAILURE;
            }
            if ($this->saveFile(
                    $logger,
                    $output,
                    "$destination/custom/yaml/$filename.yaml",
                    Encoder::toYaml($custom),
                    sprintf('v%s custom (YAML): ', $version)
                ) !== Command::SUCCESS) {
                return Command::FAILURE;
            }
            if ($this->saveFile(
                    $logger,
                    $output,
                    "$destination/postman/$filename.json",
                    Encoder::toJson($postman, readable: $readable),
                    sprintf('v%s Postman: ', $version)
                ) !== Command::SUCCESS) {
                return Command::FAILURE;
            }
            if ($this->saveFile(
                    $logger,
                    $output,
                    "$destination/openapi/json/$filename.json",
                    Encoder::toJson($openapi, readable: $readable),
                    sprintf('v%s OpenAPI (JSON): ', $version)
                ) !== Command::SUCCESS) {
                return Command::FAILURE;
            }
            if ($this->saveFile(
                    $logger,
                    $output,
                    "$destination/openapi/yaml/$filename.yaml",
                    Encoder::toYaml($openapi),
                    sprintf('v%s OpenAPI (YAML): ', $version)
                ) !== Command::SUCCESS) {
                return Command::FAILURE;
            }
            $logger->info($version . ': Done!');
        }
        $output->writeln('Done!');
        return Command::SUCCESS;
    }

}