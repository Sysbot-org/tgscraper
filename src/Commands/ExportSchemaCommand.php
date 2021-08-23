<?php


namespace TgScraper\Commands;


use Exception;
use Psr\Log\LoggerInterface;
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

class ExportSchemaCommand extends Command
{

    protected static $defaultName = 'app:export-schema';

    protected function configure(): void
    {
        $this
            ->setDescription('Export schema as JSON or YAML.')
            ->setHelp('This command allows you to create a schema for a specific version of the Telegram bot API.')
            ->addArgument('destination', InputArgument::REQUIRED, 'Destination file')
            ->addOption(
                'yaml',
                null,
                InputOption::VALUE_NONE,
                'Export schema as YAML instead of JSON (does not affect "--postman")'
            )
            ->addOption(
                'postman',
                null,
                InputOption::VALUE_NONE,
                'Export schema as a Postman-compatible JSON'
            )
            ->addOption(
                'openapi',
                null,
                InputOption::VALUE_NONE,
                'Export schema as a OpenAPI-compatible file (takes precedence over "--postman")'
            )
            ->addOption('options', 'o', InputOption::VALUE_REQUIRED, 'Encoder options', 0)
            ->addOption(
                'readable',
                'r',
                InputOption::VALUE_NONE,
                'Generate a human-readable file (overrides "--inline" and "--indent")'
            )
            ->addOption('inline', null, InputOption::VALUE_REQUIRED, '(YAML only) Inline level', 12)
            ->addOption('indent', null, InputOption::VALUE_REQUIRED, '(YAML only) Indent level', 4)
            ->addOption('layer', 'l', InputOption::VALUE_REQUIRED, 'Bot API version to use', Versions::LATEST)
            ->addOption(
                'prefer-stable',
                null,
                InputOption::VALUE_NONE,
                'Prefer latest stable version (takes precedence over "--layer")'
            );
    }

    private function saveFile(ConsoleLogger $logger, OutputInterface $output, string $destination, string $data): int
    {
        $result = file_put_contents($destination, $data);
        if (false === $result) {
            $logger->critical('Unable to save file to ' . $destination);
            return Command::FAILURE;
        }
        $output->writeln('Done!');
        return Command::SUCCESS;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $logger = new ConsoleLogger($output);
        $version = Versions::getVersionFromText($input->getOption('layer'));
        if ($input->getOption('prefer-stable')) {
            $version = Versions::STABLE;
        }
        $logger->info('Using version: ' . $version);
        try {
            $output->writeln('Fetching data for version...');
            $generator = TgScraper::fromVersion($logger, $version);
        } catch (Throwable) {
            return Command::FAILURE;
        }
        $output->writeln('Exporting schema from data...');
        $destination = $input->getArgument('destination');
        try {
            TgScraper::getTargetDirectory(pathinfo($destination)['dirname']);
        } catch (Exception) {
            return Command::FAILURE;
        }
        $readable = $input->getOption('readable');
        $options = $input->getOption('options');
        $useYaml = $input->getOption('yaml');
        $inline = $readable ? 12 : $input->getOption('inline');
        $indent = $readable ? 4 : $input->getOption('indent');
        $output->writeln('Saving schema to file...');
        if ($input->getOption('openapi')) {
            $data = $generator->toOpenApi();
            if ($useYaml) {
                return $this->saveFile($logger, $output, $destination, Encoder::toYaml($data, $inline, $indent, $options));
            }
            return $this->saveFile($logger, $output, $destination, Encoder::toJson($data, $options, $readable));
        }
        if ($input->getOption('postman')) {
            $data = $generator->toPostman();
            return $this->saveFile($logger, $output, $destination, Encoder::toJson($data, $options, $readable));
        }
        $data = $generator->toArray();
        if ($useYaml) {
            return $this->saveFile($logger, $output, $destination, Encoder::toYaml($data, $inline, $indent, $options));
        }
        return $this->saveFile($logger, $output, $destination, Encoder::toJson($data, $options, $readable));
    }

}