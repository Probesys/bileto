<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller;

use App\Tests;
use App\Tests\Factory;
use App\Utils;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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
            'ldapIdentifier' => '',
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

    public function testPostResetRedirectsEvenIfEmailIsUnknown(): void
    {
        $client = static::createClient();
        $email = 'alix@example.com';
        $user = Factory\UserFactory::createOne([
            'email' => $email,
            'ldapIdentifier' => '',
            'resetPasswordToken' => null,
        ]);

        $client->request(Request::METHOD_POST, '/passwords/reset', [
            'reset_password' => [
                '_token' => $this->generateCsrfToken($client, 'reset password'),
                'user' => 'not-the-email@example.com',
            ],
        ]);

        $this->assertResponseRedirects('/passwords/reset?sent=1', 302);
        $user->_refresh();
        $resetPasswordToken = $user->getResetPasswordToken();
        $this->assertNull($resetPasswordToken);
        $this->assertEmailCount(0);
    }

    public function testPostResetRedirectsEvenIfUserIsManagedByLdap(): void
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

        $this->assertResponseRedirects('/passwords/reset?sent=1', 302);
        $user->_refresh();
        $resetPasswordToken = $user->getResetPasswordToken();
        $this->assertNull($resetPasswordToken);
        $this->assertEmailCount(0);
    }

    public function testPostResetRedirectsEvenIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $email = 'alix@example.com';
        $user = Factory\UserFactory::createOne([
            'email' => $email,
            'ldapIdentifier' => '',
            'resetPasswordToken' => null,
        ]);

        $client->request(Request::METHOD_POST, '/passwords/reset', [
            'reset_password' => [
                '_token' => 'not the token',
                'user' => $email,
            ],
        ]);

        $this->assertResponseRedirects('/passwords/reset?sent=1', 302);
        $user->_refresh();
        $resetPasswordToken = $user->getResetPasswordToken();
        $this->assertNull($resetPasswordToken);
        $this->assertEmailCount(0);
    }

    public function testGetEditRendersCorrectly(): void
    {
        $client = static::createClient();
        $token = Factory\TokenFactory::createOne([
            'expiredAt' => Utils\Time::fromNow(2, 'hours'),
        ]);
        $user = Factory\UserFactory::createOne([
            'resetPasswordToken' => $token,
            'ldapIdentifier' => '',
        ]);

        $client->request(Request::METHOD_GET, "/passwords/{$token->getValue()}/edit");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Changing password');
    }

    public function testGetEditFailsIfTokenIsNotAssociatedToAUser(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $client = static::createClient();
        $token = Factory\TokenFactory::createOne([
            'expiredAt' => Utils\Time::fromNow(2, 'hours'),
        ]);
        $user = Factory\UserFactory::createOne([
            'resetPasswordToken' => null,
            'ldapIdentifier' => '',
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/passwords/{$token->getValue()}/edit");
    }

    public function testGetEditFailsIfTokenIsExpired(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $client = static::createClient();
        $token = Factory\TokenFactory::createOne([
            'expiredAt' => Utils\Time::ago(2, 'hours'),
        ]);
        $user = Factory\UserFactory::createOne([
            'resetPasswordToken' => $token,
            'ldapIdentifier' => '',
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/passwords/{$token->getValue()}/edit");
    }

    public function testGetEditFailsIfUserIsManagedByLdap(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $client = static::createClient();
        $token = Factory\TokenFactory::createOne([
            'expiredAt' => Utils\Time::fromNow(2, 'hours'),
        ]);
        $user = Factory\UserFactory::createOne([
            'resetPasswordToken' => $token,
            'ldapIdentifier' => 'alix',
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/passwords/{$token->getValue()}/edit");
    }

    public function testPostEditChangesThePasswordAndRedirects(): void
    {
        $client = static::createClient();
        /** @var \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface */
        $passwordHasher = self::getContainer()->get('security.user_password_hasher');
        $token = Factory\TokenFactory::createOne([
            'expiredAt' => Utils\Time::fromNow(2, 'hours'),
        ]);
        $initialPassword = 'a password';
        $newPassword = 'secret';
        $user = Factory\UserFactory::createOne([
            'resetPasswordToken' => $token,
            'ldapIdentifier' => '',
            'password' => $initialPassword,
        ]);

        $client->request(Request::METHOD_POST, "/passwords/{$token->getValue()}/edit", [
            'edit_password' => [
                '_token' => $this->generateCsrfToken($client, 'edit password'),
                'plainPassword' => $newPassword,
            ],
        ]);

        $this->assertResponseRedirects('/login', 302);
        $user->_refresh();
        $this->assertFalse($passwordHasher->isPasswordValid($user->_real(), $initialPassword));
        $this->assertTrue($passwordHasher->isPasswordValid($user->_real(), $newPassword));
        Factory\TokenFactory::assert()->notExists(['id' => $token->getId()]);
    }

    public function testPostEditFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        /** @var \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface */
        $passwordHasher = self::getContainer()->get('security.user_password_hasher');
        $token = Factory\TokenFactory::createOne([
            'expiredAt' => Utils\Time::fromNow(2, 'hours'),
        ]);
        $initialPassword = 'a password';
        $newPassword = 'secret';
        $user = Factory\UserFactory::createOne([
            'resetPasswordToken' => $token,
            'ldapIdentifier' => '',
            'password' => $initialPassword,
        ]);

        $client->request(Request::METHOD_POST, "/passwords/{$token->getValue()}/edit", [
            'edit_password' => [
                '_token' => 'not a token',
                'plainPassword' => $newPassword,
            ],
        ]);

        $this->assertSelectorTextContains('#edit_password-error', 'The security token is invalid');
        $user->_refresh();
        $this->assertTrue($passwordHasher->isPasswordValid($user->_real(), $initialPassword));
        $this->assertFalse($passwordHasher->isPasswordValid($user->_real(), $newPassword));
        Factory\TokenFactory::assert()->exists(['id' => $token->getId()]);
    }
}
