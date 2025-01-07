<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller\Users;

use App\Tests;
use App\Tests\Factory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class PreferencesControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;
    use Tests\FactoriesHelper;
    use Tests\SessionHelper;

    public function testGetEditRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());

        $client->request(Request::METHOD_GET, '/preferences');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Preferences');
    }

    public function testGetEditRedirectsToLoginIfNotConnected(): void
    {
        $client = static::createClient();

        $client->request(Request::METHOD_GET, '/preferences');

        $this->assertResponseRedirects('/login', 302);
    }

    public function testPostEditSavesTheUserAndRedirects(): void
    {
        $client = static::createClient();
        $initialColorScheme = 'light';
        $newColorScheme = 'dark';
        $initialLocale = 'en_GB';
        $newLocale = 'fr_FR';
        $user = Factory\UserFactory::createOne([
            'colorScheme' => $initialColorScheme,
            'locale' => $initialLocale,
        ]);
        $client->loginUser($user->_real());

        $client->request(Request::METHOD_POST, '/preferences', [
            'preferences' => [
                '_token' => $this->generateCsrfToken($client, 'preferences'),
                'colorScheme' => $newColorScheme,
                'locale' => $newLocale,
            ],
        ]);

        $this->assertResponseRedirects('/preferences', 302);
        $user->_refresh();
        $this->assertSame($newColorScheme, $user->getColorScheme());
        $this->assertSame($newLocale, $user->getLocale());
    }

    public function testPostEditFailsIfLocaleIsInvalid(): void
    {
        $client = static::createClient();
        $initialColorScheme = 'light';
        $newColorScheme = 'dark';
        $initialLocale = 'en_GB';
        $newLocale = 'invalid';
        $user = Factory\UserFactory::createOne([
            'colorScheme' => $initialColorScheme,
            'locale' => $initialLocale,
        ]);
        $client->loginUser($user->_real());

        $client->request(Request::METHOD_POST, '/preferences', [
            'preferences' => [
                '_token' => $this->generateCsrfToken($client, 'preferences'),
                'colorScheme' => $newColorScheme,
                'locale' => $newLocale,
            ],
        ]);

        $this->assertSelectorTextContains('#preferences_locale-error', 'The selected choice is invalid');
        $this->clearEntityManager();
        $user->_refresh();
        $this->assertSame($initialColorScheme, $user->getColorScheme());
        $this->assertSame($initialLocale, $user->getLocale());
    }

    public function testPostEditFailsIfColorSchemeIsInvalid(): void
    {
        $client = static::createClient();
        $initialColorScheme = 'light';
        $newColorScheme = 'invalid';
        $initialLocale = 'en_GB';
        $newLocale = 'fr_FR';
        $user = Factory\UserFactory::createOne([
            'colorScheme' => $initialColorScheme,
            'locale' => $initialLocale,
        ]);
        $client->loginUser($user->_real());

        $client->request(Request::METHOD_POST, '/preferences', [
            'preferences' => [
                '_token' => $this->generateCsrfToken($client, 'preferences'),
                'colorScheme' => $newColorScheme,
                'locale' => $newLocale,
            ],
        ]);

        $this->assertSelectorTextContains('#preferences_colorScheme-error', 'The selected choice is invalid');
        $this->clearEntityManager();
        $user->_refresh();
        $this->assertSame($initialColorScheme, $user->getColorScheme());
        $this->assertSame($initialLocale, $user->getLocale());
    }

    public function testPostEditFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $initialColorScheme = 'light';
        $newColorScheme = 'dark';
        $initialLocale = 'en_GB';
        $newLocale = 'fr_FR';
        $user = Factory\UserFactory::createOne([
            'colorScheme' => $initialColorScheme,
            'locale' => $initialLocale,
        ]);
        $client->loginUser($user->_real());

        $client->request(Request::METHOD_POST, '/preferences', [
            'preferences' => [
                '_token' => 'not the token',
                'colorScheme' => $newColorScheme,
                'locale' => $newLocale,
            ],
        ]);

        $this->assertSelectorTextContains('#preferences-error', 'The security token is invalid');
        $this->clearEntityManager();
        $user->_refresh();
        $this->assertSame($initialColorScheme, $user->getColorScheme());
        $this->assertSame($initialLocale, $user->getLocale());
    }

    public function testPostUpdateHideEventsSavesTheUserAndRedirects(): void
    {
        $client = static::createClient();
        $initialHideEvents = false;
        $newHideEvents = true;
        $user = Factory\UserFactory::createOne([
            'hideEvents' => $initialHideEvents,
        ]);
        $client->loginUser($user->_real());

        $client->request(Request::METHOD_POST, '/preferences/hide-events', [
            '_csrf_token' => $this->generateCsrfToken($client, 'update hide events'),
            'hideEvents' => $newHideEvents,
            'from' => '/preferences',
        ]);

        $this->assertResponseRedirects('/preferences', 302);
        $user->_refresh();
        $this->assertSame($newHideEvents, $user->areEventsHidden());
    }
}
