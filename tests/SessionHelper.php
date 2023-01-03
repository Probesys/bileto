<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockFileSessionStorage;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;
use Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage;

trait SessionHelper
{
    public function getSession(KernelBrowser $client): Session
    {
        $cookie = $client->getCookieJar()->get('MOCKSESSID');
        if ($cookie) {
            $session = $this->initSession();
            $session->setId($cookie->getValue());
            $session->start();
            return $session;
        } else {
            return self::createSession($client);
        }
    }

    public function createSession(KernelBrowser $client): Session
    {
        $session = $this->initSession();
        $session->start();
        $session->save();

        $sessionCookie = new Cookie(
            $session->getName(),
            $session->getId(),
            null,
            null,
            'localhost',
        );
        $client->getCookieJar()->set($sessionCookie);

        return $session;
    }

    private function initSession(): Session
    {
        $container = static::getContainer();
        /** @var string $sessionSavePath */
        $sessionSavePath = $container->getParameter('session.save_path');
        $sessionStorage = new MockFileSessionStorage($sessionSavePath);
        return new Session($sessionStorage);
    }

    public function generateCsrfToken(KernelBrowser $client, string $tokenId): string
    {
        $session = $this->getSession($client);
        $container = static::getContainer();
        /** @var TokenGeneratorInterface $tokenGenerator */
        $tokenGenerator = $container->get('security.csrf.token_generator');
        $csrfToken = $tokenGenerator->generateToken();
        $session->set(SessionTokenStorage::SESSION_NAMESPACE . "/{$tokenId}", $csrfToken);
        $session->save();
        return $csrfToken;
    }
}
