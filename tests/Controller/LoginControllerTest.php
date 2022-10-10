<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class LoginControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testGetLoginRendersCorrectly(): void
    {
        $client = static::createClient();

        $client->request('GET', '/login');

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

        $client->request('GET', '/login');

        $this->assertResponseRedirects('/', 302);
        $user = $this->getLoggedUser();
        $this->assertNotNull($user);
    }

    public function testPostLoginLogsTheUserAndRedirectsToHome(): void
    {
        $client = static::createClient();
        $username = 'alix@example.com';
        $password = 'secret';
        $user = UserFactory::createOne([
            'email' => $username,
            'password' => $password,
        ]);

        $client->request('GET', '/login');
        $crawler = $client->submitForm('form-login-submit', [
            '_username' => $username,
            '_password' => $password,
        ]);

        $this->assertResponseRedirects('http://localhost/', 302);
        $user = $this->getLoggedUser();
        $this->assertNotNull($user);
    }

    public function testPostLoginFailsIfPasswordIsIncorrect(): void
    {
        $client = static::createClient();
        $username = 'alix@example.com';
        $password = 'secret';
        $user = UserFactory::createOne([
            'email' => $username,
            'password' => $password,
        ]);

        $client->request('GET', '/login');
        $crawler = $client->submitForm('form-login-submit', [
            '_username' => $username,
            '_password' => 'not the secret',
        ]);

        $this->assertResponseRedirects('http://localhost/login', 302);
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
        $username = 'alix@example.com';
        $password = 'secret';

        $client->request('GET', '/login');
        $client->submitForm('form-login-submit', [
            '_username' => $username,
            '_password' => $password,
        ]);

        $this->assertResponseRedirects('http://localhost/login', 302);
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

        $client->request('GET', '/');
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
