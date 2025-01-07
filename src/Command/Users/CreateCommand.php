<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Command\Users;

use App\Repository;
use App\Security;
use App\Service;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Question\Question;
use Doctrine\Persistence\ManagerRegistry;

#[AsCommand(
    name: 'app:users:create',
    description: 'Creates a new user.',
)]
class CreateCommand extends Command
{
    public function __construct(
        private Repository\RoleRepository $roleRepository,
        private Security\Authorizer $authorizer,
        private Service\Locales $locales,
        private Service\UserCreator $userCreator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'email',
                '',
                InputOption::VALUE_OPTIONAL,
                'The email of the user.'
            )
            ->addOption(
                'password',
                '',
                InputOption::VALUE_OPTIONAL,
                'The password of the user.'
            )
            ->addOption(
                'locale',
                '',
                InputOption::VALUE_OPTIONAL,
                'The locale of the user.',
                $this->locales->getDefaultLocale(),
                Service\Locales::getSupportedLocalesCodes(),
            )
        ;
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        /** @var \Symfony\Component\Console\Helper\QuestionHelper $helper */
        $helper = $this->getHelper('question');

        $email = $input->getOption('email');
        if (!$email) {
            $question = new Question('Email: ');
            $email = $helper->ask($input, $output, $question);
            $input->setOption('email', $email);
        }

        $password = $input->getOption('password');
        if (!$password) {
            $question = new Question('Password (hidden): ');
            $question->setHidden(true);
            $question->setHiddenFallback(false);

            $password = $helper->ask($input, $output, $question);
            $input->setOption('password', $password);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = trim($input->getOption('email'));
        $password = $input->getOption('password');
        $locale = $input->getOption('locale');

        try {
            $user = $this->userCreator->create(
                email: $email,
                password: $password,
                locale: $locale,
            );
        } catch (Service\UserCreatorException $e) {
            $output = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
            foreach ($e->getErrors() as $error) {
                $output->writeln($error->getMessage());
            }

            return Command::INVALID;
        }

        $superRole = $this->roleRepository->findOrCreateSuperRole();
        $this->authorizer->grant($user, $superRole);

        $output->writeln("The user \"{$user->getEmail()}\" has been created.");

        return Command::SUCCESS;
    }
}
