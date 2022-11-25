<?php

// This file is part of Bileto.
// Copyright 2022 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Command;

use App\Utils\Random;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:secret',
    description: 'Generate a secret to be used as APP_SECRET',
)]
class SecretCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $secret = Random::hex(32);
        $output->writeln($secret);

        return Command::SUCCESS;
    }
}
