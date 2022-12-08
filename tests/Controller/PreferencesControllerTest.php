<?php

// This file is part of Bileto.
// Copyright 2022 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller;

use App\Entity\User;
use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class PreferencesControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testGetEditRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());

        $client->request('GET', '/preferences');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Preferences');
    }

    public function testGetEditRedirectsToLoginIfNotConnected(): void
    {
        $client = static::createClient();

        $client->request('GET', '/preferences');

        $this->assertResponseRedirects('http://localhost/login', 302);
    }

    public function testPostUpdateSavesTheUserAndRedirects(): void
    {
        $client = static::createClient();
        $initialColorScheme = 'light';
        $newColorScheme = 'dark';
        $initialLocale = 'en_GB';
        $newLocale = 'fr_FR';
        $user = UserFactory::createOne([
            'colorScheme' => $initialColorScheme,
            'locale' => $initialLocale,
        ]);
        $client->loginUser($user->object());

        $client->request('GET', '/preferences');
        $crawler = $client->submitForm('form-update-preferences-submit', [
            'colorScheme' => $newColorScheme,
            'locale' => $newLocale,
        ]);

        $this->assertResponseRedirects('/preferences', 302);
        $user->refresh();
        $this->assertSame($newColorScheme, $user->getColorScheme());
        $this->assertSame($newLocale, $user->getLocale());
    }

    public function testPostUpdateFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $initialColorScheme = 'light';
        $newColorScheme = 'dark';
        $initialLocale = 'en_GB';
        $newLocale = 'fr_FR';
        $user = UserFactory::createOne([
            'colorScheme' => $initialColorScheme,
            'locale' => $initialLocale,
        ]);
        $client->loginUser($user->object());

        $client->request('GET', '/preferences');
        $crawler = $client->submitForm('form-update-preferences-submit', [
            '_csrf_token' => 'not the token',
            'colorScheme' => $newColorScheme,
            'locale' => $newLocale,
        ]);

        $this->assertSelectorTextContains('[data-test="alert-error"]', 'Invalid CSRF token.');
        $user->refresh();
        $this->assertSame($initialColorScheme, $user->getColorScheme());
        $this->assertSame($initialLocale, $user->getLocale());
    }
}
