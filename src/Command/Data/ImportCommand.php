<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Command\Data;

use App\Service\DataImporter\DataImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:data:import',
    description: 'Import data into Bileto from a ZIP archive',
)]
class ImportCommand extends Command
{
    public function __construct(
        private DataImporter $dataImporter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::REQUIRED, 'The ZIP archive file to import');
        $this->addOption(
            'trust-mimetypes',
            '',
            InputOption::VALUE_NONE,
            'Whether to trust the mimetypes of files to be imported'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filename = $input->getArgument('file');
        $filepathname = getcwd() . '/' . $filename;

        $trustMimeTypes = $input->getOption('trust-mimetypes');

        $output->writeln("Starting to import {$filename}…");

        try {
            $progress = $this->dataImporter->importFile($filepathname, $trustMimeTypes);

            foreach ($progress as $log) {
                $output->write($log);
            }

            $output->writeln("File {$filename} imported successfully.");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('ERROR');
            $output->writeln($e->getMessage());

            return Command::FAILURE;
        }
    }
}
