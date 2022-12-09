<?php

// This file is part of Bileto.
// Copyright 2022 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller;

use App\Tests\SessionHelper;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SessionControllerTest extends WebTestCase
{
    use SessionHelper;

    public function testPostUpdateLocaleSavesTheLocaleInTheSessionAndRedirects(): void
    {
        $client = static::createClient();
        $session = $this->createSession($client);

        $client->request('GET', '/login');
        $crawler = $client->submitForm('form-update-session-locale-fr_FR-submit');

        $this->assertResponseRedirects('/login', 302);
        $this->assertSame('fr_FR', $session->get('_locale'));
    }

    public function testPostUpdateLocaleFailsIfLocaleIsInvalid(): void
    {
        $client = static::createClient();
        $session = $this->createSession($client);

        $client->request('GET', '/login');
        $crawler = $client->submitForm('form-update-session-locale-fr_FR-submit', [
            'locale' => 'unsupported',
        ]);

        $this->assertResponseRedirects('/login', 302);
        $this->assertNull($session->get('_locale'));
    }

    public function testPostUpdateLocaleFailsIfCsrfIsInvalid(): void
    {
        $client = static::createClient();
        $session = $this->createSession($client);

        $client->request('GET', '/login');
        $crawler = $client->submitForm('form-update-session-locale-fr_FR-submit', [
            '_csrf_token' => 'not the token',
        ]);

        $this->assertResponseRedirects('/login', 302);
        $this->assertNull($session->get('_locale'));
    }
}
