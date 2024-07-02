<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller;

use App\Entity\User;
use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class LoginControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testGetLoginRendersCorrectly(): void
    {
        $client = static::createClient();

        $client->request(Request::METHOD_GET, '/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('#form-login-submit', 'Login');
        $user = $this->getLoggedUser();
        $this->assertNull($user);
    }

    public function testGetLoginRedirectsIfAlreadyConnected(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());

        $client->request(Request::METHOD_GET, '/login');

        $this->assertResponseRedirects('/', 302);
        $user = $this->getLoggedUser();
        $this->assertNotNull($user);
    }

    public function testPostLoginLogsTheUserAndRedirectsToHome(): void
    {
        $client = static::createClient();
        $identifier = 'alix@example.com';
        $password = 'secret';
        $user = UserFactory::createOne([
            'email' => $identifier,
            'password' => $password,
        ]);

        $client->request(Request::METHOD_GET, '/login');
        $crawler = $client->submitForm('form-login-submit', [
            '_identifier' => $identifier,
            '_password' => $password,
        ]);

        $this->assertResponseRedirects('/', 302);
        $user = $this->getLoggedUser();
        $this->assertNotNull($user);
    }

    public function testPostLoginWithLdapCreatesUser(): void
    {
        // If this test fails, it's probably because you didn't start the LDAP server.
        // Please start the development environment using `make docker-start LDAP=true`

        $client = static::createClient();
        // This user exists in the LDAP directory.
        // See the docker/development/ldap-ldifs/tree.ldif file.
        $identifier = 'dominique';
        $password = 'secret';

        $client->request(Request::METHOD_GET, '/login');
        $crawler = $client->submitForm('form-login-submit', [
            '_identifier' => $identifier,
            '_password' => $password,
        ]);

        $this->assertResponseRedirects('/', 302);
        $user = $this->getLoggedUser();
        $this->assertNotNull($user);
    }

    public function testPostLoginFailsIfPasswordIsIncorrect(): void
    {
        $client = static::createClient();
        $identifier = 'alix@example.com';
        $password = 'secret';
        $user = UserFactory::createOne([
            'email' => $identifier,
            'password' => $password,
        ]);

        $client->request(Request::METHOD_GET, '/login');
        $crawler = $client->submitForm('form-login-submit', [
            '_identifier' => $identifier,
            '_password' => 'not the secret',
        ]);

        $this->assertResponseRedirects('/login', 302);
        $client->followRedirect();

        $this->assertSelectorTextContains(
            '[data-test="alert-error"]',
            'Invalid credentials.'
        );
        $user = $this->getLoggedUser();
        $this->assertNull($user);
    }

    public function testPostLoginFailsIfUserDoesNotExist(): void
    {
        $client = static::createClient();
        $identifier = 'alix@example.com';
        $password = 'secret';

        $client->request(Request::METHOD_GET, '/login');
        $client->submitForm('form-login-submit', [
            '_identifier' => $identifier,
            '_password' => $password,
        ]);

        $this->assertResponseRedirects('/login', 302);
        $client->followRedirect();

        $this->assertSelectorTextContains(
            '[data-test="alert-error"]',
            'Invalid credentials.'
        );
        $user = $this->getLoggedUser();
        $this->assertNull($user);
    }

    public function testPostLogoutLogsUserOutAndRedirects(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());

        $client->request(Request::METHOD_GET, '/profile');
        $client->submitForm('form-logout-submit');

        $this->assertResponseRedirects('http://localhost/', 302);
        $user = $this->getLoggedUser();
        $this->assertNull($user);
    }

    protected function getLoggedUser(): ?User
    {
        /** @var \Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface */
        $tokenStorage = $this->getContainer()->get('security.token_storage');
        $token = $tokenStorage->getToken();
        if (!$token) {
            return null;
        }

        /** @var User|null $user */
        $user = $token->getUser();
        return $user;
    }
}
