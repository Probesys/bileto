<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Command\Data;

use App\Service\DataImporter\DataImporter;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:data:import',
    description: 'Import data into Bileto from a ZIP archive',
)]
class ImportCommand
{
    public function __construct(
        private DataImporter $dataImporter,
    ) {
    }

    public function __invoke(
        OutputInterface $output,
        #[Argument(name: 'file', description: 'The ZIP archive file to import')]
        string $filename,
        #[Option(name: 'trust-mimetypes', description: 'Whether to trust the mimetypes of files to be imported')]
        bool $trustMimeTypes = false,
    ): int {
        $filepathname = getcwd() . '/' . $filename;

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
