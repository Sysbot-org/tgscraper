<?php


namespace TgScraper\Commands;


use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use TgScraper\Constants\Versions;
use TgScraper\TgScraper;
use Throwable;

class CreateStubsCommand extends Command
{

    protected static $defaultName = 'app:create-stubs';

    protected function validateData(string|false $data): bool
    {
        return false !== $data and !empty($data);
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Create stubs from bot API schema.')
            ->setHelp('This command allows you to create class stubs for all types of the Telegram bot API.')
            ->addArgument('destination', InputArgument::REQUIRED, 'Destination directory')
            ->addOption('namespace-prefix', null, InputOption::VALUE_REQUIRED, 'Namespace prefix for stubs', 'TelegramApi')
            ->addOption(
                'json',
                null,
                InputOption::VALUE_REQUIRED,
                'Path to JSON file to use instead of fetching from URL (this option takes precedence over "--layer")'
            )
            ->addOption(
                'yaml',
                null,
                InputOption::VALUE_REQUIRED,
                'Path to YAML file to use instead of fetching from URL (this option takes precedence over "--layer" and "--json")'
            )
            ->addOption('layer', 'l', InputOption::VALUE_REQUIRED, 'Bot API version to use', 'latest')
            ->addOption(
                'prefer-stable',
                null,
                InputOption::VALUE_NONE,
                'Prefer latest stable version (takes precedence over "--layer")'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $logger = new ConsoleLogger($output);
        $version = Versions::getVersionFromText($input->getOption('layer'));
        if ($input->getOption('prefer-stable')) {
            $version = Versions::STABLE;
        }
        $yamlPath = $input->getOption('yaml');
        if (empty($yamlPath)) {
            $jsonPath = $input->getOption('json');
            if (empty($jsonPath)) {
                $logger->info('Using version: ' . $version);
                try {
                    $output->writeln('Fetching data for version...');
                    $generator = TgScraper::fromVersion($logger, $version);
                } catch (Throwable) {
                    return Command::FAILURE;
                }
            } else {
                $data = file_get_contents($jsonPath);
                if (!$this->validateData($data)) {
                    $logger->critical('Invalid JSON file provided');
                    return Command::INVALID;
                }
                $logger->info('Using JSON schema: ' . $jsonPath);
                /** @noinspection PhpUnhandledExceptionInspection */
                $generator = TgScraper::fromJson($logger, $data);
            }
        } else {
            $data = file_get_contents($yamlPath);
            if (!$this->validateData($data)) {
                $logger->critical('Invalid YAML file provided');
                return Command::INVALID;
            }
            $logger->info('Using YAML schema: ' . $yamlPath);
            /** @noinspection PhpUnhandledExceptionInspection */
            $generator = TgScraper::fromYaml($logger, $data);
        }
        try {
            $output->writeln('Creating stubs...');
            $generator->toStubs($input->getArgument('destination'), $input->getOption('namespace-prefix'));
        } catch (Exception) {
            $logger->critical('Could not create stubs.');
            return Command::FAILURE;
        }
        $output->writeln('Done!');
        return Command::SUCCESS;
    }

}