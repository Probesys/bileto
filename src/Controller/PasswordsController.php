<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Entity;
use App\Form;
use App\Message;
use App\Repository;
use App\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

class PasswordsController extends BaseController
{
    #[Route('/passwords/reset', name: 'reset password')]
    public function reset(
        Request $request,
        Repository\UserRepository $userRepository,
        MessageBusInterface $bus,
        EventDispatcherInterface $eventDispatcher,
    ): Response {
        $sent = $request->query->getBoolean('sent');

        $form = $this->createNamedForm('reset_password', Form\Password\ResetForm::class);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $data = $form->getData();
                $user = $data['user'];

                $token = Entity\Token::create(
                    2,
                    'hours',
                    length: 20,
                    description: "Reset password token for {$user->getEmail()}"
                );
                $user->setResetPasswordToken($token);

                $userRepository->save($user, true);

                $bus->dispatch(new Message\SendResetPasswordEmail($user->getId()));
            }

            $identifier = $request->request->all('reset_password')['user'] ?? '';
            $identifier = trim($identifier);
            if ($identifier) {
                $resetPasswordEvent = new Security\Event\ResetPasswordEvent($request, $identifier);
                $eventDispatcher->dispatch($resetPasswordEvent);
            }

            return $this->redirectToRoute('reset password', ['sent' => true]);
        }

        return $this->render('passwords/reset.html.twig', [
            'form' => $form,
            'emailSent' => $sent,
        ]);
    }

    #[Route('/passwords/{token}/edit', name: 'edit password')]
    public function edit(
        string $token,
        Request $request,
        Repository\TokenRepository $tokenRepository,
        Repository\UserRepository $userRepository,
        EventDispatcherInterface $eventDispatcher,
        #[Autowire(env: 'bool:LDAP_ENABLED')]
        bool $ldapEnabled,
    ): Response {
        $user = $userRepository->findOneByResetPasswordToken($token);

        if (!$user) {
            throw $this->createNotFoundException('The token does not exist.');
        }

        $resetPasswordToken = $user->getResetPasswordToken();

        if (!$resetPasswordToken || !$resetPasswordToken->isValid()) {
            throw $this->createNotFoundException('The token does not exist.');
        }

        $managedByLdap = $ldapEnabled && $user->getAuthType() === 'ldap';

        if ($managedByLdap) {
            throw $this->createNotFoundException('The user is managed by LDAP.');
        }

        if (!$user->canLogin()) {
            throw $this->createNotFoundException('The user cannot login.');
        }

        $form = $this->createNamedForm('edit_password', Form\Password\EditForm::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $form->getData();

            $user->setResetPasswordToken(null);

            $userRepository->save($user, true);

            $tokenRepository->remove($resetPasswordToken, true);

            $changedPasswordEvent = new Security\Event\ChangedPasswordEvent($request, $user);
            $eventDispatcher->dispatch($changedPasswordEvent);

            $this->addFlash('password_changed', true);

            return $this->redirectToRoute('login');
        }

        return $this->render('passwords/edit.html.twig', [
            'form' => $form,
            'user' => $user,
        ]);
    }
}
