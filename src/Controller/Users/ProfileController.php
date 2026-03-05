<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Users;

use App\Controller\BaseController;
use App\Entity;
use App\Form;
use App\Repository;
use App\Security;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Translation\TranslatableMessage;

class ProfileController extends BaseController
{
    public function __construct(
        private readonly Repository\UserRepository $userRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[Route('/profile', name: 'profile')]
    public function edit(Request $request): Response
    {
        /** @var Entity\User */
        $user = $this->getUser();

        $form = $this->createNamedForm('profile', Form\User\ProfileForm::class, $user);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $form->getData();
            $this->userRepository->save($user, true);

            if ($form->has('plainPassword') && $form->get('plainPassword')->getData() !== '') {
                $changedPasswordEvent = new Security\Event\ChangedPasswordEvent($request, $user);
                $this->eventDispatcher->dispatch($changedPasswordEvent);
            }

            $this->addFlash('success', new TranslatableMessage('notifications.saved'));

            return $this->redirectToRoute('profile');
        }

        return $this->render('users/profile/edit.html.twig', [
            'form' => $form,
        ]);
    }
}
