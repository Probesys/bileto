<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Repository\UserRepository;
use App\Utils\ConstraintErrorsFormatter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ProfileController extends BaseController
{
    #[Route('/profile', name: 'profile', methods: ['GET', 'HEAD'])]
    public function edit(
        #[Autowire(env: 'bool:LDAP_ENABLED')]
        bool $ldapEnabled,
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        return $this->render('profile/edit.html.twig', [
            'name' => $user->getName(),
            'email' => $user->getEmail(),
            'managedByLdap' => $ldapEnabled && $user->getAuthType() === 'ldap',
        ]);
    }

    #[Route('/profile', name: 'update profile', methods: ['POST'])]
    public function update(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        ValidatorInterface $validator,
        RequestStack $requestStack,
        TranslatorInterface $translator,
        #[Autowire(env: 'bool:LDAP_ENABLED')]
        bool $ldapEnabled,
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $initialName = $user->getName();
        $initialEmail = $user->getEmail();

        /** @var string $name */
        $name = $request->request->get('name', $initialName);

        /** @var string $email */
        $email = $request->request->get('email', $initialEmail);

        /** @var string $currentPassword */
        $currentPassword = $request->request->get('currentPassword', '');

        /** @var string $newPassword */
        $newPassword = $request->request->get('newPassword', '');

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        $managedByLdap = $ldapEnabled && $user->getAuthType() === 'ldap';

        if ($managedByLdap) {
            return $this->renderBadRequest('profile/edit.html.twig', [
                'name' => $name,
                'email' => $email,
                'managedByLdap' => $managedByLdap,
                'error' => $translator->trans('user.ldap.cannot_update_profile', [], 'errors'),
            ]);
        }

        if (!$this->isCsrfTokenValid('update profile', $csrfToken)) {
            return $this->renderBadRequest('profile/edit.html.twig', [
                'name' => $name,
                'email' => $email,
                'managedByLdap' => $managedByLdap,
                'error' => $translator->trans('csrf.invalid', [], 'errors'),
            ]);
        }

        if ($newPassword) {
            if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                return $this->renderBadRequest('profile/edit.html.twig', [
                    'name' => $name,
                    'email' => $email,
                    'managedByLdap' => $managedByLdap,
                    'errors' => [
                        'password' => $translator->trans('user.password.dont_match', [], 'errors'),
                    ],
                ]);
            }

            $newHashedPassword = $passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($newHashedPassword);
        }

        $user->setName($name);
        $user->setEmail($email);

        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            $user->setEmail($initialEmail);
            return $this->renderBadRequest('profile/edit.html.twig', [
                'name' => $name,
                'email' => $email,
                'managedByLdap' => $managedByLdap,
                'errors' => ConstraintErrorsFormatter::format($errors),
            ]);
        }

        $userRepository->save($user, true);

        $this->addFlash('success', new TranslatableMessage('notifications.saved'));

        return $this->redirectToRoute('profile');
    }
}
