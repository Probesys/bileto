<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller;

use App\Entity\User;
use App\Tests\FactoriesHelper;
use App\Tests\Factory\UserFactory;
use App\Tests\SessionHelper;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class PreferencesControllerTest extends WebTestCase
{
    use Factories;
    use FactoriesHelper;
    use ResetDatabase;
    use SessionHelper;

    public function testGetEditRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
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
        $client->loginUser($user->_real());

        $client->request(Request::METHOD_GET, '/preferences');
        $crawler = $client->submitForm('form-update-preferences-submit', [
            'colorScheme' => $newColorScheme,
            'locale' => $newLocale,
        ]);

        $this->assertResponseRedirects('/preferences', 302);
        $user->_refresh();
        $this->assertSame($newColorScheme, $user->getColorScheme());
        $this->assertSame($newLocale, $user->getLocale());
    }

    public function testPostUpdateFailsIfLocaleIsInvalid(): void
    {
        $client = static::createClient();
        $initialColorScheme = 'light';
        $newColorScheme = 'dark';
        $initialLocale = 'en_GB';
        $newLocale = 'invalid';
        $user = UserFactory::createOne([
            'colorScheme' => $initialColorScheme,
            'locale' => $initialLocale,
        ]);
        $client->loginUser($user->_real());

        $client->request(Request::METHOD_POST, '/preferences', [
            '_csrf_token' => $this->generateCsrfToken($client, 'update preferences'),
            'colorScheme' => $newColorScheme,
            'locale' => $newLocale,
        ]);

        $this->assertSelectorTextContains('#locale-error', 'Select a language from the list');
        $this->clearEntityManager();
        $user->_refresh();
        $this->assertSame($initialColorScheme, $user->getColorScheme());
        $this->assertSame($initialLocale, $user->getLocale());
    }

    public function testPostUpdateFailsIfColorSchemeIsInvalid(): void
    {
        $client = static::createClient();
        $initialColorScheme = 'light';
        $newColorScheme = 'invalid';
        $initialLocale = 'en_GB';
        $newLocale = 'fr_FR';
        $user = UserFactory::createOne([
            'colorScheme' => $initialColorScheme,
            'locale' => $initialLocale,
        ]);
        $client->loginUser($user->_real());

        $client->request(Request::METHOD_POST, '/preferences', [
            '_csrf_token' => $this->generateCsrfToken($client, 'update preferences'),
            'colorScheme' => $newColorScheme,
            'locale' => $newLocale,
        ]);

        $this->assertSelectorTextContains('#color-scheme-error', 'Select a color scheme from the list');
        $this->clearEntityManager();
        $user->_refresh();
        $this->assertSame($initialColorScheme, $user->getColorScheme());
        $this->assertSame($initialLocale, $user->getLocale());
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
        $client->loginUser($user->_real());

        $client->request(Request::METHOD_POST, '/preferences', [
            '_csrf_token' => 'not the token',
            'colorScheme' => $newColorScheme,
            'locale' => $newLocale,
        ]);

        $this->assertSelectorTextContains('[data-test="alert-error"]', 'The security token is invalid');
        $user->_refresh();
        $this->assertSame($initialColorScheme, $user->getColorScheme());
        $this->assertSame($initialLocale, $user->getLocale());
    }

    public function testPostUpdateHideEventsSavesTheUserAndRedirects(): void
    {
        $client = static::createClient();
        $initialHideEvents = false;
        $newHideEvents = true;
        $user = UserFactory::createOne([
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
