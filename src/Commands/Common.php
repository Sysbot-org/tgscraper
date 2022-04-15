<?php

namespace TgScraper\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

trait Common
{

    protected function saveFile(
        ConsoleLogger $logger,
        OutputInterface $output,
        string $destination,
        string $data,
        ?string $prefix = null,
        bool $log = true
    ): int {
        $result = file_put_contents($destination, $data);
        if (false === $result) {
            $logger->critical($prefix . 'Unable to save file to ' . $destination);
            return Command::FAILURE;
        }
        if ($log) {
            $logger->info($prefix . 'Done!');
            return Command::SUCCESS;
        }
        $output->writeln($prefix . 'Done!');
        return Command::SUCCESS;
    }

}