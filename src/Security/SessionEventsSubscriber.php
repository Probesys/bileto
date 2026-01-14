<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Security;

use App\Entity;
use App\Repository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event as SecurityEvent;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SessionEventsSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            SecurityEvent\LoginSuccessEvent::class => 'logLoginSuccess',
            SecurityEvent\LoginFailureEvent::class => 'logLoginFailure',
            SecurityEvent\LogoutEvent::class => 'logLogout',
            Event\ResetPasswordEvent::class => 'logResetPassword',
            Event\ChangedPasswordEvent::class => 'logChangedPassword',
        ];
    }

    public function __construct(
        private Repository\SessionLogRepository $sessionLogRepository,
        private AuthenticationUtils $authenticationUtils,
    ) {
    }

    public function logLoginSuccess(SecurityEvent\LoginSuccessEvent $event): void
    {
        $request = $event->getRequest();
        $identifier = $event->getUser()->getUserIdentifier();

        $sessionLog = Entity\SessionLog::initLoginSuccess($identifier, $request);

        $this->sessionLogRepository->save($sessionLog, flush: true);
    }

    public function logLoginFailure(SecurityEvent\LoginFailureEvent $event): void
    {
        $request = $event->getRequest();
        $identifier = $this->authenticationUtils->getLastUsername();

        $sessionLog = Entity\SessionLog::initLoginFailure($identifier, $request);

        $this->sessionLogRepository->save($sessionLog, flush: true);
    }

    public function logLogout(SecurityEvent\LogoutEvent $event): void
    {
        $request = $event->getRequest();
        $identifier = $event->getToken()?->getUserIdentifier();

        $sessionLog = Entity\SessionLog::initLogout($identifier, $request);

        $this->sessionLogRepository->save($sessionLog, flush: true);
    }

    public function logResetPassword(Event\ResetPasswordEvent $event): void
    {
        $request = $event->getRequest();
        $identifier = $event->getUserIdentifier();

        $sessionLog = Entity\SessionLog::initResetPassword($identifier, $request);

        $this->sessionLogRepository->save($sessionLog, flush: true);
    }

    public function logChangedPassword(Event\ChangedPasswordEvent $event): void
    {
        $request = $event->getRequest();
        $identifier = $event->getUser()->getUserIdentifier();

        $sessionLog = Entity\SessionLog::initChangedPassword($identifier, $request);

        $this->sessionLogRepository->save($sessionLog, flush: true);
    }
}
