<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Command\Users;

use App\Entity;
use App\Repository\UserRepository;
use App\Repository\RoleRepository;
use App\Repository\AuthorizationRepository;
use App\Utils\Time;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Doctrine\Persistence\ManagerRegistry;

#[AsCommand(
    name: 'app:users:create',
    description: 'Creates a new user.',
)]
class CreateCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private RoleRepository $roleRepository,
        private AuthorizationRepository $authorizationRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private ValidatorInterface $validator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', '', InputOption::VALUE_OPTIONAL, 'The email of the user.')
            ->addOption('password', '', InputOption::VALUE_OPTIONAL, 'The password of the user.')
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

        $user = new Entity\User();

        $user->setEmail($email);
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            $output = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
            foreach ($errors as $error) {
                $output->writeln($error->getMessage());
            }

            return Command::INVALID;
        }

        $this->userRepository->save($user, true);

        $superRole = $this->roleRepository->findOrCreateSuperRole();
        $this->authorizationRepository->grant($user, $superRole);

        $output->writeln("The user \"{$user->getEmail()}\" has been created.");

        return Command::SUCCESS;
    }
}
