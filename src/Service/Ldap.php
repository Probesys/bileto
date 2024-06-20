<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service;

use App\Entity\User;
use App\Utils\Random;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Ldap\Entry as LdapEntry;
use Symfony\Component\Ldap\Exception\InvalidCredentialsException;
use Symfony\Component\Ldap\Ldap as LdapConnector;
use Symfony\Component\Ldap\LdapInterface;

/**
 * This class provides methods to connect to a LDAP server with a simple
 * developer experience.
 */
class Ldap
{
    public function __construct(
        private LdapConnector $ldapConnector,
        private LoggerInterface $logger,
        #[Autowire(env: 'LDAP_BASE_DN')]
        private string $baseDn,
        #[Autowire(env: 'LDAP_ADMIN_DN')]
        private string $adminDn,
        #[Autowire(env: 'LDAP_ADMIN_PASSWORD')]
        private string $adminPassword,
        #[Autowire(env: 'LDAP_USERS_DN')]
        private string $usersDn,
        #[Autowire(env: 'LDAP_QUERY_SEARCH_USER')]
        private string $querySearchUser,
        #[Autowire(env: 'LDAP_QUERY_LIST_USERS')]
        private string $queryListUsers,
        #[Autowire(env: 'default::LDAP_FIELD_IDENTIFIER')]
        private ?string $fieldIdentifier,
        #[Autowire(env: 'default::LDAP_FIELD_EMAIL')]
        private ?string $fieldEmail,
        #[Autowire(env: 'default::LDAP_FIELD_FULLNAME')]
        private ?string $fieldFullName,
    ) {
        $this->fieldIdentifier ??= 'cn';
        $this->fieldEmail ??= 'mail';
        $this->fieldFullName ??= 'displayName';
    }

    /**
     * Login a user to the LDAP server and return true on success, false otherwise.
     */
    public function loginUser(string $identifier, string $password): bool
    {
        $identifier = $this->ldapConnector->escape($identifier, '', LdapInterface::ESCAPE_DN);
        $userDn = str_replace('{user_identifier}', $identifier, $this->usersDn);

        try {
            $this->ldapConnector->bind($userDn, $password);

            return true;
        } catch (InvalidCredentialsException) {
            return false;
        } catch (\Exception $e) {
            $this->logger->critical("[Ldap#loginUser] Unexpected error: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Search for a user in the LDAP directory.
     *
     * If the user can't be found, or if an error occurs, null is returned.
     * The errors are logged so an admin can understand what's happening.
     *
     * The Ldap Entry is converted into a User before being returned.
     *
     * @see self::convertLdapEntryToUser
     */
    public function searchUser(string $identifier): ?User
    {
        try {
            $this->ldapConnector->bind($this->adminDn, $this->adminPassword);
        } catch (InvalidCredentialsException) {
            $this->logger->critical('[Ldap#searchUser] Invalid LDAP admin credentials.');
            return null;
        } catch (\Exception $e) {
            $this->logger->critical("[Ldap#searchUser] Unexpected error: {$e->getMessage()}");
            return null;
        }

        $identifier = $this->ldapConnector->escape($identifier, '', LdapInterface::ESCAPE_FILTER);
        $querySearchUser = str_replace('{user_identifier}', $identifier, $this->querySearchUser);
        $search = $this->ldapConnector->query($this->baseDn, $querySearchUser, ['filter' => '*']);

        $entries = $search->execute();

        if (count($entries) > 1) {
            $this->logger->error("[Ldap#searchUser] Several entries match the '{$querySearchUser}' search query");
            return null;
        }

        if (count($entries) === 0 || $entries[0] === null) {
            return null;
        }

        return $this->convertLdapEntryToUser($entries[0]);
    }

    /**
     * List the users from the LDAP directory.
     *
     * The Ldap Entries are converted into Users before being returned.
     *
     * @see self::convertLdapEntryToUser
     *
     * @return User[]
     */
    public function listUsers(): array
    {
        try {
            $this->ldapConnector->bind($this->adminDn, $this->adminPassword);
        } catch (InvalidCredentialsException) {
            $this->logger->critical('[Ldap#listUsers] Invalid LDAP admin credentials.');
            return [];
        } catch (\Exception $e) {
            $this->logger->critical("[Ldap#listUsers] Unexpected error: {$e->getMessage()}");
            return [];
        }

        $search = $this->ldapConnector->query($this->baseDn, $this->queryListUsers, ['filter' => '*']);

        $entries = $search->execute();

        $users = [];

        foreach ($entries as $entry) {
            $users[] = $this->convertLdapEntryToUser($entry);
        }

        return $users;
    }

    /**
     * Convert a Ldap entry to an app User.
     *
     * Only few attributes are supported for now: ldapIdentifier, email and
     * name. The other attributes are not set, except password which is set to
     * a random value.
     */
    private function convertLdapEntryToUser(LdapEntry $entry): User
    {
        $identifier = $this->getEntryAttribute($entry, $this->fieldIdentifier);
        if (!$identifier) {
            $identifier = '';
        }

        $email = $this->getEntryAttribute($entry, $this->fieldEmail);
        if (!$email) {
            $email = '';
        }

        $fullName = $this->getEntryAttribute($entry, $this->fieldFullName);
        if (!$fullName) {
            $fullName = '';
        }

        $user = new User();

        $user->setEmail($email);
        $user->setName($fullName);
        $user->setLdapIdentifier($identifier);

        return $user;
    }

    /**
     * Return the value of a Ldap Entry attribute as a string.
     *
     * If the Entry doesn't have the attribute, null is returned.
     * If the Entry has multiple values for the given attribute, only the first
     * is returned.
     */
    private function getEntryAttribute(LdapEntry $entry, string $attribute): ?string
    {
        if (!$entry->hasAttribute($attribute)) {
            return null;
        }

        $values = $entry->getAttribute($attribute);

        if (count($values) === 0) {
            return null;
        }

        return $values[0];
    }
}
