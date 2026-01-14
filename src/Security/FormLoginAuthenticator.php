<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Ldap;
use App\Service\UserCreator;
use App\Service\UserCreatorException;
use App\Utils\ConstraintErrorsFormatter;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\PasswordUpgradeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A custom Authenticator able to handle both local and LDAP authentications.
 */
class FormLoginAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    /**
     * @param UserProviderInterface<User> $userProvider
     */
    public function __construct(
        private UserCreator $userCreator,
        private Ldap $ldap,
        private LoggerInterface $logger,
        private TranslatorInterface $translator,
        private UrlGeneratorInterface $urlGenerator,
        private UserProviderInterface $userProvider,
        private UserRepository $userRepository,
        #[Autowire(env: 'bool:LDAP_ENABLED')]
        private bool $ldapEnabled,
    ) {
    }

    /**
     * Perform the authentication.
     *
     * If LDAP is disabled, the authentication is almost identical to the
     * default form login authenticator: return a Passport with a UserBadge and
     * a PasswordCredentials badges to be checked.
     *
     * Otherwise, we load the User either by its email or its LDAP identifier.
     * If its authentication type is 'ldap' (i.e. they have a LDAP identifier),
     * log the user using LDAP, otherwise, log the user using the default
     * workflow explained above.
     *
     * If the user doesn't exist, we first search for them in the LDAP
     * directory and try to create a User out of the LDAP entry.
     */
    public function authenticate(Request $request): Passport
    {
        /** @var string */
        $csrfToken = $request->request->get('_csrf_token', '');
        /** @var string */
        $identifier = $request->request->get('_identifier', '');
        /** @var string */
        $password = $request->request->get('_password', '');

        $identifier = trim($identifier);

        // Remember the identifier so it can be displayed in the authentication
        // form on errors.
        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $identifier);

        if ($this->ldapEnabled) {
            $userLoader = $this->userRepository->loadUserByIdentifier(...);
        } else {
            $userLoader = $this->userRepository->loadUserByEmail(...);
        }

        $userBadge = new UserBadge($identifier, $userLoader);
        $securityBadges = [
            new CsrfTokenBadge('authenticate', $csrfToken),
            new RememberMeBadge(),
        ];

        if ($this->userProvider instanceof PasswordUpgraderInterface) {
            $securityBadges[] = new PasswordUpgradeBadge($password, $this->userProvider);
        }

        if (!$this->ldapEnabled) {
            // No LDAP? Just use the normal authentication process.
            return new Passport(
                $userBadge,
                new PasswordCredentials($password),
                $securityBadges,
            );
        }

        $user = $userLoader($identifier);

        if (!$user) {
            // If the user doesn't exist yet, search for them in the LDAP
            // directory and try to create them in the database.
            $ldapUser = $this->ldap->searchUser($identifier);

            if ($ldapUser) {
                $user = $ldapUser;

                try {
                    $this->userCreator->createUser($user);
                } catch (UserCreatorException $e) {
                    $errors = implode(' ', ConstraintErrorsFormatter::format($e->getErrors()));
                    $this->logger->error("Can't create LDAP user: {$errors}");

                    throw new CustomUserMessageAuthenticationException(
                        $this->translator->trans('Invalid credentials.', [], 'security')
                    );
                }
            }
        }

        if (
            $user &&
            $user->getAuthType() === 'ldap' &&
            $this->ldap->loginUser($identifier, $password)
        ) {
            // We can return a SelfValidatingPassport as the loginUser(...)
            // method makes sure the credentials are valid.
            return new SelfValidatingPassport(
                $userBadge,
                $securityBadges,
            );
        } elseif (
            $user &&
            $user->getAuthType() === 'local'
        ) {
            // Here though, we didn't check the credentials yet. It's why we
            // return a simple Passport with a PasswordCredentials badge!
            return new Passport(
                $userBadge,
                new PasswordCredentials($password),
                $securityBadges,
            );
        } else {
            throw new CustomUserMessageAuthenticationException(
                $this->translator->trans('Invalid credentials.', [], 'security')
            );
        }
    }

    /**
     * Return a redirect response to either the home page, or the page from
     * which the user was redirected.
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $session = $request->getSession();
        $targetPath = $this->getTargetPath($session, $firewallName);

        if ($targetPath) {
            $this->removeTargetPath($session, $firewallName);

            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->urlGenerator->generate('home'));
    }

    /**
     * Return the URL to the login page.
     */
    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate('login');
    }
}
