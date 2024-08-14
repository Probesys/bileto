<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller;

use App\Tests;
use App\Tests\Factory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class PasswordsControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;
    use Tests\AuthorizationHelper;
    use Tests\SessionHelper;

    public function testGetResetRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();

        $client->request(Request::METHOD_GET, '/passwords/reset');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Reset your password');
    }

    public function testGetResetRendersWhenEmailIsSent(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();

        $client->request(Request::METHOD_GET, '/passwords/reset', [
            'sent' => true,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('[data-test="alert-info"]', 'Email sent');
    }

    public function testPostResetRedirectsAndSendsAnEmail(): void
    {
        $client = static::createClient();
        $email = 'alix@example.com';
        $user = Factory\UserFactory::createOne([
            'email' => $email,
            'ldapIdentifier' => null,
            'resetPasswordToken' => null,
        ]);

        $client->request(Request::METHOD_POST, '/passwords/reset', [
            'reset_password' => [
                '_token' => $this->generateCsrfToken($client, 'reset password'),
                'user' => $email,
            ],
        ]);

        $this->assertResponseRedirects('/passwords/reset?sent=1', 302);
        $user->_refresh();
        $resetPasswordToken = $user->getResetPasswordToken();
        $this->assertNotNull($resetPasswordToken);
        $this->assertTrue($resetPasswordToken->isValid());
        $this->assertEmailCount(1);
        $email = $this->getMailerMessage();
        $this->assertEmailHtmlBodyContains($email, 'Reset your password');
    }

    public function testPostResetFailsIfEmailIsUnknown(): void
    {
        $client = static::createClient();
        $email = 'alix@example.com';
        $user = Factory\UserFactory::createOne([
            'email' => $email,
            'ldapIdentifier' => null,
            'resetPasswordToken' => null,
        ]);

        $client->request(Request::METHOD_POST, '/passwords/reset', [
            'reset_password' => [
                '_token' => $this->generateCsrfToken($client, 'reset password'),
                'user' => 'not-the-email@example.com',
            ],
        ]);

        $this->assertSelectorTextContains(
            '#reset_password_user-error',
            'The email address is not associated to a user account'
        );
        $user->_refresh();
        $resetPasswordToken = $user->getResetPasswordToken();
        $this->assertNull($resetPasswordToken);
        $this->assertEmailCount(0);
    }

    public function testPostResetFailsIfUserIsManagedByLdap(): void
    {
        $client = static::createClient();
        $email = 'alix@example.com';
        $user = Factory\UserFactory::createOne([
            'email' => $email,
            'ldapIdentifier' => 'alix',
            'resetPasswordToken' => null,
        ]);

        $client->request(Request::METHOD_POST, '/passwords/reset', [
            'reset_password' => [
                '_token' => $this->generateCsrfToken($client, 'reset password'),
                'user' => $email,
            ],
        ]);

        $this->assertSelectorTextContains(
            '#reset_password_user-error',
            'The email address is associated to a user account managed by LDAP'
        );
        $user->_refresh();
        $resetPasswordToken = $user->getResetPasswordToken();
        $this->assertNull($resetPasswordToken);
        $this->assertEmailCount(0);
    }

    public function testPostResetFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $email = 'alix@example.com';
        $user = Factory\UserFactory::createOne([
            'email' => $email,
            'ldapIdentifier' => null,
            'resetPasswordToken' => null,
        ]);

        $client->request(Request::METHOD_POST, '/passwords/reset', [
            'reset_password' => [
                '_token' => 'not the token',
                'user' => $email,
            ],
        ]);

        $this->assertSelectorTextContains('#reset_password-error', 'The security token is invalid');
        $user->_refresh();
        $resetPasswordToken = $user->getResetPasswordToken();
        $this->assertNull($resetPasswordToken);
        $this->assertEmailCount(0);
    }
}
