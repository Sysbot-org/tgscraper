<?php


namespace TgScraper\Commands;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use TgScraper\Constants\Versions;
use TgScraper\Generator;
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
                'Export schema as YAML instead of JSON (this option takes precedence over "--postman")'
            )
            ->addOption('postman', null, InputOption::VALUE_NONE, 'Export schema as a Postman-compatible JSON')
            ->addOption('options', 'o', InputOption::VALUE_REQUIRED, 'Encoder options', 0)
            ->addOption('readable', 'r', InputOption::VALUE_NONE, '(JSON only) Generate a human-readable JSON')
            ->addOption('inline', null, InputOption::VALUE_REQUIRED, '(YAML only) Inline level', 6)
            ->addOption('indent', null, InputOption::VALUE_REQUIRED, '(YAML only) Indent level', 4)
            ->addOption('layer', 'l', InputOption::VALUE_REQUIRED, 'Bot API version to use', 'latest');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $logger = new ConsoleLogger($output);
        $url = Versions::getVersionFromText($input->getOption('layer'));
        $logger->info('Using URL: ' . $url);
        try {
            $output->writeln('Fetching data from URL...');
            $generator = new Generator($logger, $url);
        } catch (Throwable) {
            return Command::FAILURE;
        }
        $output->writeln('Exporting schema from data...');
        $options = $input->getOption('options');
        $useYaml = $input->getOption('yaml');
        if ($useYaml) {
            $data = $generator->toYaml($input->getOption('inline'), $input->getOption('indent'), $options);
        }
        $destination = $input->getArgument('destination');
        if ($input->getOption('readable')) {
            $options |= JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        }
        $data ??= $input->getOption('postman') ? $generator->toPostman($options) : $generator->toJson($options);
        $output->writeln('Saving scheme to file...');
        $result = file_put_contents($destination, $data);
        if (false === $result) {
            $logger->critical('Unable to save file to ' . $destination);
            return Command::FAILURE;
        }
        $output->writeln('Done!');
        return Command::SUCCESS;
    }

}