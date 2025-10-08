<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service\DataImporter;

use App\Entity;
use App\Repository;
use App\Service;
use App\Utils;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Welcome to the DataImporter class!
 *
 * This is a pretty big file, but hopefully the structure is pretty basic. You
 * should understand it with these explanations.
 *
 * There are 2 methods to import data:
 *
 * - importFile() is the standard one, and is called by the Data/ImportCommand
 * - import() is called by importFile, but is also the entrypoint for the tests
 *
 * importFile() takes a filename as an argument and extracts the corresponding
 * file if its a ZIP archive. Then, it reads the extracted files and detects if
 * a documents/ folder is present (it's where the message documents must be
 * placed to be imported in Bileto). Finally, it calls import() with the
 * content extracted from the archive, and cleans the files.
 *
 * import() processes the data to create the entities (more on that below),
 * saves them in the database and imports the documents if any. If errors are
 * detected during processing the data, an exception is raised before saving
 * the entities.
 *
 * Each array of data structures from the files is processed separately, but
 * they all have the same form:
 *
 * 1. The structure is checked: the array must contain elements as arrays, and
 *    the required fields must be present.
 * 2. An entity is built out of the data extracted from the array element.
 * 3. If the element references other elements, the references are checked and
 *    the entity references are set.
 * 4. The entity is added to an index, which checks the uniqueness of its id
 *    and properties (such as Organization name which must be unique). In some
 *    cases, the entity is not added to an index (e.g. users' authorizations).
 * 5. The entities in the index are updated with the data from the database
 *    when it's duplicated. For instance, if an organization with a specific
 *    name is present in the database *and* the file, we change the object
 *    in the index with the one from the database.
 * 6. The entities constraints are finally checked with the Symfony Validator.
 *
 * Some processes can use sub-processes to manage elements that are included.
 * This is the case for the users' authorizations, or the tickets' messages for
 * instance.
 *
 * The MessageDocuments are handled quite differently. Indeed, they are built
 * by the MessageDocumentStorage service, which also moves the documents on the
 * filesystem at the same time. In order to not touch the filesystem during the
 * processing of the Tickets/Messages/MessageDocuments, the MessageDocuments
 * are stored in an index as a raw JSON array instead of an entity object.
 * They are built, then saved at the very end of the operations.
 *
 * And that's pretty all.
 *
 * The code could probably be refactored, in particular all the duplicated code
 * of the different process() methods. In the meantime, while the code works
 * and that I don't have to support the addition of new data to import every
 * morning, I'm good with it!
 */
class DataImporter
{
    /** @var string[] */
    private array $errors = [];

    private string $documentsPath = '';

    /** @var Index<Entity\Organization> */
    private Index $indexOrganizations;

    /** @var Index<Entity\Role> */
    private Index $indexRoles;

    /** @var Index<Entity\User> */
    private Index $indexUsers;

    /** @var Index<Entity\Team> */
    private Index $indexTeams;

    /** @var Index<Entity\Contract> */
    private Index $indexContracts;

    /** @var Index<Entity\Label> */
    private Index $indexLabels;

    /** @var Index<Entity\Ticket> */
    private Index $indexTickets;

    /** @var Index<Entity\Message> */
    private Index $indexMessages;

    /**
     * @var Index<array<array{
     *     name: string,
     *     filepath: string,
     * }>>
     */
    private Index $indexMessageToDocuments;

    public function __construct(
        private Repository\ContractRepository $contractRepository,
        private Repository\LabelRepository $labelRepository,
        private Repository\OrganizationRepository $organizationRepository,
        private Repository\RoleRepository $roleRepository,
        private Repository\TeamRepository $teamRepository,
        private Repository\TicketRepository $ticketRepository,
        private Repository\UserRepository $userRepository,
        private Persister $persister,
        private ValidatorInterface $validator,
        private HtmlSanitizerInterface $appMessageSanitizer,
        private Service\MessageDocumentStorage $messageDocumentStorage,
        private Service\Locales $locales,
    ) {
    }

    /**
     * @return \Generator<int, string, void, void>
     */
    public function importFile(string $filepathname, bool $trustMimeTypes = false): \Generator
    {
        $zipArchive = new \ZipArchive();
        $result = $zipArchive->open($filepathname, \ZipArchive::RDONLY);

        if ($result === \ZipArchive::ER_NOENT || $result === \ZipArchive::ER_OPEN) {
            throw new DataImporterError('The file does not exist or cannot be read.');
        } elseif ($result !== true) {
            throw new DataImporterError('The file is not a valid ZIP archive.');
        }

        $now = Utils\Time::now();
        $tmpPath = sys_get_temp_dir();
        $tmpPath = $tmpPath . "/BiletoDataImport_{$now->format('Y-m-d\TH:i:s')}";

        yield "Extracting the archive to {$tmpPath}… ";

        $zipArchive->extractTo($tmpPath);
        $zipArchive->close();

        yield "ok\n";

        $organizations = [];
        if (file_exists("{$tmpPath}/organizations.json")) {
            $organizations = Utils\FSHelper::readJson("{$tmpPath}/organizations.json");
        } else {
            yield "The file organizations.json is missing, so ignoring organizations.\n";
        }

        $roles = [];
        if (file_exists("{$tmpPath}/roles.json")) {
            $roles = Utils\FSHelper::readJson("{$tmpPath}/roles.json");
        } else {
            yield "The file roles.json is missing, so ignoring roles.\n";
        }

        $users = [];
        if (file_exists("{$tmpPath}/users.json")) {
            $users = Utils\FSHelper::readJson("{$tmpPath}/users.json");
        } else {
            yield "The file users.json is missing, so ignoring users.\n";
        }

        $teams = [];
        if (file_exists("{$tmpPath}/teams.json")) {
            $teams = Utils\FSHelper::readJson("{$tmpPath}/teams.json");
        } else {
            yield "The file teams.json is missing, so ignoring teams.\n";
        }

        $contracts = [];
        if (file_exists("{$tmpPath}/contracts.json")) {
            $contracts = Utils\FSHelper::readJson("{$tmpPath}/contracts.json");
        } else {
            yield "The file contracts.json is missing, so ignoring contracts.\n";
        }

        $labels = [];
        if (file_exists("{$tmpPath}/labels.json")) {
            $labels = Utils\FSHelper::readJson("{$tmpPath}/labels.json");
        } else {
            yield "The file labels.json is missing, so ignoring labels.\n";
        }

        $tickets = [];
        foreach (Utils\FSHelper::recursiveScandir("{$tmpPath}/tickets/") as $ticketFilepath) {
            $tickets[] = Utils\FSHelper::readJson($ticketFilepath);
        }

        $countTickets = count($tickets);
        if (count($tickets) === 0) {
            yield "No ticket files found, so ignoring tickets.\n";
        }

        $documentsPath = "{$tmpPath}/documents";
        if (!is_dir($documentsPath)) {
            $documentsPath = '';
            yield "The documents/ directory does not exist, not importing the documents.\n";
        }

        $error = null;
        try {
            yield from $this->import(
                organizations: $organizations,
                roles: $roles,
                users: $users,
                teams: $teams,
                contracts: $contracts,
                labels: $labels,
                tickets: $tickets,
                documentsPath: $documentsPath,
                trustMimeTypes: $trustMimeTypes,
            );
        } catch (DataImporterError $e) {
            $error = $e;
            yield "Errors detected!\n";
        }

        yield "Removing the extracted files at {$tmpPath}… ";
        Utils\FSHelper::recursiveUnlink($tmpPath);

        if ($error) {
            throw $error;
        } else {
            yield "ok\n";
        }
    }

    /**
     * @param mixed[] $organizations
     * @param mixed[] $roles
     * @param mixed[] $users
     * @param mixed[] $teams
     * @param mixed[] $contracts
     * @param mixed[] $labels
     * @param mixed[] $tickets
     * @param string $documentsPath
     *
     * @return \Generator<int, string, void, void>
     */
    public function import(
        array $organizations = [],
        array $roles = [],
        array $users = [],
        array $teams = [],
        array $contracts = [],
        array $labels = [],
        array $tickets = [],
        string $documentsPath = '',
        bool $trustMimeTypes = false,
    ): \Generator {
        $this->errors = [];

        $this->documentsPath = $documentsPath;

        $this->indexOrganizations = new Index();
        $this->indexRoles = new Index();
        $this->indexUsers = new Index();
        $this->indexTeams = new Index();
        $this->indexContracts = new Index();
        $this->indexLabels = new Index();
        $this->indexTickets = new Index();
        $this->indexMessages = new Index();
        $this->indexMessageToDocuments = new Index();

        yield from $this->processOrganizations($organizations);
        yield from $this->processRoles($roles);
        yield from $this->processUsers($users);
        yield from $this->processTeams($teams);
        yield from $this->processContracts($contracts);
        yield from $this->processLabels($labels);
        yield from $this->processTickets($tickets);

        if ($this->errors) {
            throw new DataImporterError(implode("\n", $this->errors));
        }

        yield from $this->importEntities($this->indexOrganizations->list());
        yield from $this->importEntities($this->indexRoles->list());
        yield from $this->importEntities($this->indexUsers->list(), ['authorizations']);
        yield from $this->importEntities($this->indexTeams->list(), ['teamAuthorizations']);
        yield from $this->importEntities($this->indexContracts->list());
        yield from $this->importEntities($this->indexLabels->list());
        yield from $this->importEntities($this->indexTickets->list(), ['timeSpents', 'messages']);
        yield from $this->importMessageDocuments($trustMimeTypes);
    }

    /**
     * @phpstan-impure
     *
     * @param mixed[] $json
     *
     * @return \Generator<int, string, void, void>
     */
    private function processOrganizations(array $json): \Generator
    {
        yield 'Processing organizations… ';

        $requiredFields = [
            'id',
            'name'
        ];

        foreach ($json as $jsonOrganization) {
            // Check the structure of the organization
            $error = self::checkStructure($jsonOrganization, required: $requiredFields);
            if ($error) {
                $this->errors[] = "Organizations file contains invalid data: {$error}";
                continue;
            }

            $id = strval($jsonOrganization['id']);
            $name = strval($jsonOrganization['name']);

            $domains = [];
            if (isset($jsonOrganization['domains'])) {
                $domains = $jsonOrganization['domains'];
            }

            // Build the organization
            $organization = new Entity\Organization();
            $organization->setName($name);

            $organization->setUid(Utils\Random::hex(20));
            $organization->setCreatedAt(Utils\Time::now());
            $organization->setUpdatedAt(Utils\Time::now());

            if (is_array($domains)) {
                $organization->setDomains($domains);
            } else {
                $this->errors[] = "Organization {$id} error: domains: not an array.";
            }

            // Add the organization to the index
            try {
                $this->indexOrganizations->add($id, $organization, uniqueKey: $name);
            } catch (IndexError $e) {
                $this->errors[] = "Organization {$id} error: {$e->getMessage()}";
            }
        }

        // Load existing values from the database and update the indexes
        $existingOrganizations = $this->organizationRepository->findAll();
        foreach ($existingOrganizations as $organization) {
            $name = $organization->getName();
            $this->indexOrganizations->refreshUnique($organization, uniqueKey: $name);
        }

        // Validate the organizations
        foreach ($this->indexOrganizations->list() as $id => $organization) {
            $error = $this->validate($organization);
            if ($error) {
                $this->errors[] = "Organization {$id} error: {$error}";
            }
        }

        yield "ok\n";
    }

    /**
     * @phpstan-impure
     *
     * @param mixed[] $json
     *
     * @return \Generator<int, string, void, void>
     */
    private function processRoles(array $json): \Generator
    {
        yield 'Processing roles… ';

        $requiredFields = [
            'id',
            'name',
            'description',
            'type',
        ];

        $superUniqueKey = '@super';
        $defaultUniqueKey = '@default';

        foreach ($json as $jsonRole) {
            // Check the structure of the role
            $error = self::checkStructure($jsonRole, required: $requiredFields);
            if ($error) {
                $this->errors[] = "Roles file contains invalid data: {$error}";
                continue;
            }

            $id = strval($jsonRole['id']);
            $name = strval($jsonRole['name']);
            $description = strval($jsonRole['description']);
            $type = strval($jsonRole['type']);

            $permissions = [];
            if (isset($jsonRole['permissions'])) {
                $permissions = $jsonRole['permissions'];
            }

            $isDefault = false;
            if (isset($jsonRole['isDefault'])) {
                $isDefault = boolval($jsonRole['isDefault']);
            }

            // Build the role
            $role = new Entity\Role();
            $role->setName($name);
            $role->setDescription($description);
            $role->setType($type);
            $role->setIsDefault($isDefault);

            $role->setUid(Utils\Random::hex(20));
            $role->setCreatedAt(Utils\Time::now());
            $role->setUpdatedAt(Utils\Time::now());

            if (is_array($permissions)) {
                $role->setPermissions($permissions);
            } else {
                $this->errors[] = "Role {$id} error: permissions: not an array.";
            }

            if ($isDefault && $type !== 'user') {
                $this->errors[] = "Role {$id} error: {$type} role cannot be a default role.";
            }

            // Add the role to the index
            try {
                $this->indexRoles->add($id, $role, uniqueKey: $name);

                if ($type === 'super') {
                    $this->indexRoles->addUniqueAlias($id, uniqueKey: $superUniqueKey);
                }

                if ($isDefault) {
                    $this->indexRoles->addUniqueAlias($id, uniqueKey: $defaultUniqueKey);
                }
            } catch (IndexError $e) {
                $this->errors[] = "Role {$id} error: {$e->getMessage()}";
            }
        }

        // Load existing values from the database and update the indexes
        $existingRoles = $this->roleRepository->findAll();
        foreach ($existingRoles as $role) {
            $name = $role->getName();
            $this->indexRoles->refreshUnique($role, uniqueKey: $name);

            if ($role->getType() === 'super') {
                $this->indexRoles->refreshUnique($role, uniqueKey: $superUniqueKey);
            }

            if ($role->isDefault()) {
                $declaredDefaultRole = $this->indexRoles->getByKey($defaultUniqueKey);
                $declaredDefaultRole->setIsDefault(false);
            }
        }

        // Validate the roles
        foreach ($this->indexRoles->list() as $id => $role) {
            $error = $this->validate($role);
            if ($error) {
                $this->errors[] = "Role {$id} error: {$error}";
            }
        }

        yield "ok\n";
    }

    /**
     * @phpstan-impure
     *
     * @param mixed[] $json
     *
     * @return \Generator<int, string, void, void>
     */
    private function processUsers(array $json): \Generator
    {
        yield 'Processing users… ';

        $requiredFields = [
            'id',
            'email',
        ];

        $defaultLocale = $this->locales->getDefaultLocale();

        foreach ($json as $jsonUser) {
            // Check the structure of the user
            $error = self::checkStructure($jsonUser, required: $requiredFields);
            if ($error) {
                $this->errors[] = "Users file contains invalid data: {$error}";
                continue;
            }

            $id = strval($jsonUser['id']);
            $email = strval($jsonUser['email']);
            $name = null;
            if (isset($jsonUser['name'])) {
                $name = strval($jsonUser['name']);
            }
            $locale = $defaultLocale;
            if (isset($jsonUser['locale'])) {
                $locale = strval($jsonUser['locale']);
            }
            $ldapIdentifier = '';
            if (isset($jsonUser['ldapIdentifier'])) {
                $ldapIdentifier = strval($jsonUser['ldapIdentifier']);
            }
            $organizationId = null;
            if (isset($jsonUser['organizationId'])) {
                $organizationId = strval($jsonUser['organizationId']);
            }

            $authorizations = [];
            if (isset($jsonUser['authorizations'])) {
                $authorizations = $jsonUser['authorizations'];
            }

            // Build the user
            $user = new Entity\User();
            $user->setEmail($email);
            $user->setLocale($locale);
            if ($name) {
                $user->setName($name);
            }
            if ($ldapIdentifier) {
                $user->setLdapIdentifier($ldapIdentifier);
            }

            $user->setUid(Utils\Random::hex(20));
            $user->setCreatedAt(Utils\Time::now());
            $user->setUpdatedAt(Utils\Time::now());

            // Check and set references
            if ($organizationId) {
                $organization = $this->indexOrganizations->get($organizationId);

                if ($organization) {
                    $user->setOrganization($organization);
                } else {
                    $this->errors[] = "User {$id} error: references an unknown organization {$organizationId}.";
                }
            }

            if (is_array($authorizations)) {
                $this->processUserAuthorizations($id, $user, $authorizations);
            } else {
                $this->errors[] = "User {$id} error: authorizations: not an array.";
            }

            // Add the user to the index
            try {
                $this->indexUsers->add($id, $user, uniqueKey: $email);
            } catch (IndexError $e) {
                $this->errors[] = "User {$id} error: {$e->getMessage()}";
            }
        }

        // Load existing values from the database and update the indexes
        $existingUsers = $this->userRepository->findAll();
        foreach ($existingUsers as $user) {
            $email = $user->getEmail();
            $this->indexUsers->refreshUnique($user, uniqueKey: $email);
        }

        // Validate the users
        foreach ($this->indexUsers->list() as $id => $user) {
            $error = $this->validate($user);
            if ($error) {
                $this->errors[] = "User {$id} error: {$error}";
            }
        }

        yield "ok\n";
    }

    /**
     * @phpstan-impure
     *
     * @param mixed[] $json
     */
    private function processUserAuthorizations(string $userId, Entity\User $user, array $json): void
    {
        $requiredFields = [
            'roleId',
        ];

        foreach ($json as $jsonAuthorization) {
            // Check the structure of the authorization
            $error = self::checkStructure($jsonAuthorization, required: $requiredFields);
            if ($error) {
                $this->errors[] = "User {$userId} error: authorizations: {$error}";
                continue;
            }

            $roleId = strval($jsonAuthorization['roleId']);

            $organizationId = null;
            if (isset($jsonAuthorization['organizationId'])) {
                $organizationId = strval($jsonAuthorization['organizationId']);
            }

            // Build the authorization
            $authorization = new Entity\Authorization();

            $authorization->setUid(Utils\Random::hex(20));
            $authorization->setCreatedAt(Utils\Time::now());
            $authorization->setUpdatedAt(Utils\Time::now());

            // Check and set references
            $role = $this->indexRoles->get($roleId);

            if ($role) {
                $authorization->setRole($role);
            } else {
                $this->errors[] = "User {$userId} error: authorizations: "
                                . "references an unknown role {$roleId}";
            }

            if ($organizationId) {
                $organization = $this->indexOrganizations->get($organizationId);

                if ($organization) {
                    $authorization->setOrganization($organization);
                } else {
                    $this->errors[] = "User {$userId} error: authorizations: "
                                    . "references an unknown organization {$organizationId}";
                }
            }

            // Add the authorization to the user
            $user->addAuthorization($authorization);
        }
    }

    /**
     * @phpstan-impure
     *
     * @param mixed[] $json
     *
     * @return \Generator<int, string, void, void>
     */
    private function processTeams(array $json): \Generator
    {
        yield 'Processing teams… ';

        $requiredFields = [
            'id',
            'name',
        ];

        foreach ($json as $jsonTeam) {
            // Check the structure of the team
            $error = self::checkStructure($jsonTeam, required: $requiredFields);
            if ($error) {
                $this->errors[] = "Teams file contains invalid data: {$error}";
                continue;
            }

            $id = strval($jsonTeam['id']);
            $name = strval($jsonTeam['name']);

            $isResponsible = false;
            if (isset($jsonTeam['isResponsible'])) {
                $isResponsible = boolval($jsonTeam['isResponsible']);
            }

            $teamAuthorizations = [];
            if (isset($jsonTeam['teamAuthorizations'])) {
                $teamAuthorizations = $jsonTeam['teamAuthorizations'];
            }

            // Build the team
            $team = new Entity\Team();
            $team->setName($name);
            $team->setIsResponsible($isResponsible);

            $team->setUid(Utils\Random::hex(20));
            $team->setCreatedAt(Utils\Time::now());
            $team->setUpdatedAt(Utils\Time::now());

            // Check and set references
            if (is_array($teamAuthorizations)) {
                $this->processTeamAuthorizations($id, $team, $teamAuthorizations);
            } else {
                $this->errors[] = "Team {$id} error: teamAuthorizations: not an array.";
            }

            // Add the team to the index
            try {
                $this->indexTeams->add($id, $team, uniqueKey: $name);
            } catch (IndexError $e) {
                $this->errors[] = "Team {$id} error: {$e->getMessage()}";
            }
        }

        // Load existing values from the database and update the indexes
        $existingTeams = $this->teamRepository->findAll();
        foreach ($existingTeams as $team) {
            $name = $team->getName();
            $this->indexTeams->refreshUnique($team, uniqueKey: $name);
        }

        // Validate the teams
        foreach ($this->indexTeams->list() as $id => $team) {
            $error = $this->validate($team);
            if ($error) {
                $this->errors[] = "Team {$id} error: {$error}";
            }
        }

        yield "ok\n";
    }

    /**
     * @phpstan-impure
     *
     * @param mixed[] $json
     */
    private function processTeamAuthorizations(string $teamId, Entity\Team $team, array $json): void
    {
        $requiredFields = [
            'roleId',
        ];

        foreach ($json as $jsonTeamAuthorization) {
            // Check the structure of the teamAuthorization
            $error = self::checkStructure($jsonTeamAuthorization, required: $requiredFields);
            if ($error) {
                $this->errors[] = "Team {$teamId} error: teamAuthorizations: {$error}";
                continue;
            }

            $roleId = strval($jsonTeamAuthorization['roleId']);

            $organizationId = null;
            if (isset($jsonTeamAuthorization['organizationId'])) {
                $organizationId = strval($jsonTeamAuthorization['organizationId']);
            }

            // Build the authorization
            $teamAuthorization = new Entity\TeamAuthorization();

            $teamAuthorization->setUid(Utils\Random::hex(20));
            $teamAuthorization->setCreatedAt(Utils\Time::now());
            $teamAuthorization->setUpdatedAt(Utils\Time::now());

            // Check and set references
            $role = $this->indexRoles->get($roleId);

            if ($role) {
                $teamAuthorization->setRole($role);
            } else {
                $this->errors[] = "Team {$teamId} error: teamAuthorizations: "
                                . "references an unknown role {$roleId}";
            }

            if ($organizationId) {
                $organization = $this->indexOrganizations->get($organizationId);

                if ($organization) {
                    $teamAuthorization->setOrganization($organization);
                } else {
                    $this->errors[] = "Team {$teamId} error: teamAuthorizations: "
                                    . "references an unknown organization {$organizationId}";
                }
            }

            // Add the authorization to the team
            $team->addTeamAuthorization($teamAuthorization);
        }
    }

    /**
     * @phpstan-impure
     *
     * @param mixed[] $json
     *
     * @return \Generator<int, string, void, void>
     */
    private function processContracts(array $json): \Generator
    {
        yield 'Processing contracts… ';

        $requiredFields = [
            'id',
            'name',
            'startAt',
            'endAt',
            'maxHours',
            'organizationId',
        ];

        foreach ($json as $jsonContract) {
            // Check the structure of the contract
            $error = self::checkStructure($jsonContract, required: $requiredFields);
            if ($error) {
                $this->errors[] = "Contracts file contains invalid data: {$error}";
                continue;
            }

            $id = strval($jsonContract['id']);
            $name = strval($jsonContract['name']);
            $startAt = self::parseDatetime($jsonContract['startAt']);
            $endAt = self::parseDatetime($jsonContract['endAt']);
            $maxHours = intval($jsonContract['maxHours']);
            $organizationId = strval($jsonContract['organizationId']);

            $notes = null;
            if (isset($jsonContract['notes'])) {
                $notes = strval($jsonContract['notes']);
            }
            $timeAccountingUnit = null;
            if (isset($jsonContract['timeAccountingUnit'])) {
                $timeAccountingUnit = intval($jsonContract['timeAccountingUnit']);
            }
            $hoursAlert = null;
            if (isset($jsonContract['hoursAlert'])) {
                $hoursAlert = intval($jsonContract['hoursAlert']);
            }
            $dateAlert = null;
            if (isset($jsonContract['dateAlert'])) {
                $dateAlert = intval($jsonContract['dateAlert']);
            }

            // Build the contract
            $contract = new Entity\Contract();
            $contract->setName($name);
            $contract->setMaxHours($maxHours);

            $contract->setUid(Utils\Random::hex(20));
            $contract->setCreatedAt(Utils\Time::now());
            $contract->setUpdatedAt(Utils\Time::now());

            if ($startAt !== null) {
                $contract->setStartAt($startAt);
            } else {
                $this->errors[] = "Contract {$id} error: invalid startAt datetime";
            }

            if ($endAt !== null) {
                $contract->setEndAt($endAt);
            } else {
                $this->errors[] = "Contract {$id} error: invalid endAt datetime";
            }

            if ($notes) {
                $contract->setNotes($notes);
            }

            if ($timeAccountingUnit) {
                $contract->setTimeAccountingUnit($timeAccountingUnit);
            }

            if ($hoursAlert) {
                $contract->setHoursAlert($hoursAlert);
            }

            if ($dateAlert) {
                $contract->setDateAlert($dateAlert);
            }

            // Check and set references
            $organization = $this->indexOrganizations->get($organizationId);

            if ($organization) {
                $contract->setOrganization($organization);
            } else {
                $this->errors[] = "Contract {$id} error: references an unknown organization {$organizationId}.";
            }

            // Add the contract to the index
            try {
                $uniqueKey = $contract->getUniqueKey();
                $this->indexContracts->add($id, $contract, uniqueKey: $uniqueKey);
            } catch (IndexError $e) {
                $this->errors[] = "Contract {$id} error: {$e->getMessage()}";
            }
        }

        // Load existing values from the database and update the indexes
        $existingContracts = $this->contractRepository->findAll();
        foreach ($existingContracts as $contract) {
            $uniqueKey = $contract->getUniqueKey();
            $this->indexContracts->refreshUnique($contract, uniqueKey: $uniqueKey);
        }

        // Validate the contracts
        foreach ($this->indexContracts->list() as $id => $contract) {
            $error = $this->validate($contract);
            if ($error) {
                $this->errors[] = "Contract {$id} error: {$error}";
            }
        }

        yield "ok\n";
    }

    /**
     * @phpstan-impure
     *
     * @param mixed[] $json
     *
     * @return \Generator<int, string, void, void>
     */
    private function processLabels(array $json): \Generator
    {
        yield 'Processing labels… ';

        $requiredFields = [
            'id',
            'name'
        ];

        foreach ($json as $jsonLabel) {
            // Check the structure of the label
            $error = self::checkStructure($jsonLabel, required: $requiredFields);
            if ($error) {
                $this->errors[] = "Labels file contains invalid data: {$error}";
                continue;
            }

            $id = strval($jsonLabel['id']);
            $name = strval($jsonLabel['name']);

            $description = '';
            if (isset($jsonLabel['description'])) {
                $description = strval($jsonLabel['description']);
            }

            $color = 'grey';
            if (isset($jsonLabel['color'])) {
                $color = strval($jsonLabel['color']);
            }

            // Build the label
            $label = new Entity\Label();
            $label->setName($name);
            $label->setDescription($description);
            $label->setColor($color);

            $label->setUid(Utils\Random::hex(20));
            $label->setCreatedAt(Utils\Time::now());
            $label->setUpdatedAt(Utils\Time::now());

            // Add the label to the index
            try {
                $this->indexLabels->add($id, $label, uniqueKey: $name);
            } catch (IndexError $e) {
                $this->errors[] = "Label {$id} error: {$e->getMessage()}";
            }
        }

        // Load existing values from the database and update the indexes
        $existingLabels = $this->labelRepository->findAll();
        foreach ($existingLabels as $label) {
            $name = $label->getName();
            $this->indexLabels->refreshUnique($label, uniqueKey: $name);
        }

        // Validate the labels
        foreach ($this->indexLabels->list() as $id => $label) {
            $error = $this->validate($label);
            if ($error) {
                $this->errors[] = "Label {$id} error: {$error}";
            }
        }

        yield "ok\n";
    }

    /**
     * @phpstan-impure
     *
     * @param mixed[] $json
     *
     * @return \Generator<int, string, void, void>
     */
    private function processTickets(array $json): \Generator
    {
        yield 'Processing tickets… ';

        $requiredFields = [
            'id',
            'createdAt',
            'createdById',
            'title',
            'requesterId',
            'organizationId',
        ];

        foreach ($json as $jsonTicket) {
            // Check the structure of the ticket
            $error = self::checkStructure($jsonTicket, required: $requiredFields);
            if ($error) {
                $this->errors[] = "Tickets file contains invalid data: {$error}";
                continue;
            }

            $id = strval($jsonTicket['id']);
            $createdAt = self::parseDatetime($jsonTicket['createdAt']);
            $createdById = strval($jsonTicket['createdById']);
            $title = strval($jsonTicket['title']);

            $updatedAt = $createdAt;
            if (isset($jsonTicket['updatedAt'])) {
                $updatedAt = self::parseDatetime($jsonTicket['updatedAt']);
            }

            $type = null;
            if (isset($jsonTicket['type'])) {
                $type = strval($jsonTicket['type']);
            }

            $status = null;
            if (isset($jsonTicket['status'])) {
                $status = strval($jsonTicket['status']);
            }

            $urgency = null;
            if (isset($jsonTicket['urgency'])) {
                $urgency = strval($jsonTicket['urgency']);
            }

            $impact = null;
            if (isset($jsonTicket['impact'])) {
                $impact = strval($jsonTicket['impact']);
            }

            $priority = null;
            if (isset($jsonTicket['priority'])) {
                $priority = strval($jsonTicket['priority']);
            }

            $requesterId = strval($jsonTicket['requesterId']);

            $teamId = null;
            if (isset($jsonTicket['teamId'])) {
                $teamId = strval($jsonTicket['teamId']);
            }

            $assigneeId = null;
            if (isset($jsonTicket['assigneeId'])) {
                $assigneeId = strval($jsonTicket['assigneeId']);
            }

            $observerIds = [];
            if (isset($jsonTicket['observerIds'])) {
                $observerIds = $jsonTicket['observerIds'];
            }

            $organizationId = strval($jsonTicket['organizationId']);

            $solutionId = null;
            if (isset($jsonTicket['solutionId'])) {
                $solutionId = strval($jsonTicket['solutionId']);
            }

            $contractIds = [];
            if (isset($jsonTicket['contractIds'])) {
                $contractIds = $jsonTicket['contractIds'];
            }

            $labelIds = [];
            if (isset($jsonTicket['labelIds'])) {
                $labelIds = $jsonTicket['labelIds'];
            }

            $timeSpents = [];
            if (isset($jsonTicket['timeSpents'])) {
                $timeSpents = $jsonTicket['timeSpents'];
            }

            $messages = [];
            if (isset($jsonTicket['messages'])) {
                $messages = $jsonTicket['messages'];
            }

            // Build the ticket
            $ticket = new Entity\Ticket();
            $ticket->setCreatedAt($createdAt);
            $ticket->setUpdatedAt($updatedAt);
            $ticket->setTitle($title);

            $ticket->setUid(Utils\Random::hex(20));

            if ($type !== null) {
                $ticket->setType($type);
            }

            if ($status !== null) {
                $ticket->setStatus($status);
            }

            if ($urgency !== null) {
                $ticket->setUrgency($urgency);
            }

            if ($impact !== null) {
                $ticket->setImpact($impact);
            }

            if ($priority !== null) {
                $ticket->setPriority($priority);
            }

            // Check and set references
            $createdBy = $this->indexUsers->get($createdById);

            if ($createdBy) {
                $ticket->setCreatedBy($createdBy);
                $ticket->setUpdatedBy($createdBy);
            } else {
                $this->errors[] = "Ticket {$id} error: references an unknown createdBy user {$createdById}.";
            }

            $organization = $this->indexOrganizations->get($organizationId);

            if ($organization) {
                $ticket->setOrganization($organization);
            } else {
                $this->errors[] = "Ticket {$id} error: references an unknown organization {$organizationId}.";
            }

            $requester = $this->indexUsers->get($requesterId);

            if ($requester) {
                $ticket->setRequester($requester);
            } else {
                $this->errors[] = "Ticket {$id} error: references an unknown requester user {$requesterId}.";
            }

            if ($teamId !== null) {
                $team = $this->indexTeams->get($teamId);

                if ($team) {
                    $ticket->setTeam($team);
                } else {
                    $this->errors[] = "Ticket {$id} error: references an unknown team {$teamId}.";
                }
            }

            if ($assigneeId !== null) {
                $assignee = $this->indexUsers->get($assigneeId);

                if ($assignee) {
                    $ticket->setAssignee($assignee);
                } else {
                    $this->errors[] = "Ticket {$id} error: references an unknown assignee user {$assigneeId}.";
                }
            }

            if (is_array($observerIds)) {
                foreach ($observerIds as $observerId) {
                    $observer = $this->indexUsers->get($observerId);

                    if ($observer) {
                        $ticket->addObserver($observer);
                    } else {
                        $this->errors[] = "Ticket {$id} error: references an unknown observer {$observerId}.";
                    }
                }
            } else {
                $this->errors[] = "Ticket {$id} error: observerIds: not an array.";
            }

            if (is_array($contractIds)) {
                foreach ($contractIds as $contractId) {
                    $contract = $this->indexContracts->get($contractId);

                    if ($contract) {
                        $ticket->addContract($contract);
                    } else {
                        $this->errors[] = "Ticket {$id} error: references an unknown contract {$contractId}.";
                    }
                }
            } else {
                $this->errors[] = "Ticket {$id} error: contractIds: not an array.";
            }

            if (is_array($labelIds)) {
                foreach ($labelIds as $labelId) {
                    $label = $this->indexLabels->get($labelId);

                    if ($label) {
                        $ticket->addLabel($label);
                    } else {
                        $this->errors[] = "Ticket {$id} error: references an unknown label {$labelId}.";
                    }
                }
            } else {
                $this->errors[] = "Ticket {$id} error: labelIds: not an array.";
            }

            if (is_array($timeSpents)) {
                $this->processTicketTimeSpents($id, $ticket, $timeSpents);
            } else {
                $this->errors[] = "Ticket {$id} error: timeSpents: not an array.";
            }

            if (is_array($messages)) {
                $this->processTicketMessages($id, $ticket, $messages, $solutionId);
            } else {
                $this->errors[] = "Ticket {$id} error: messages: not an array.";
            }

            if ($solutionId !== null && $ticket->getSolution() === null) {
                $this->errors[] = "Ticket {$id} error: references an unknown solution {$solutionId}.";
            }

            // Add the ticket to the index
            try {
                $uniqueKey = $ticket->getUniqueKey();
                $this->indexTickets->add($id, $ticket, uniqueKey: $uniqueKey);
            } catch (IndexError $e) {
                $this->errors[] = "Ticket {$id} error: {$e->getMessage()}";
            }
        }

        // Load existing values from the database and update the indexes
        $existingTickets = $this->ticketRepository->findAll();
        foreach ($existingTickets as $ticket) {
            $uniqueKey = $ticket->getUniqueKey();
            $this->indexTickets->refreshUnique($ticket, uniqueKey: $uniqueKey);

            foreach ($ticket->getMessages() as $message) {
                $messageUniqueKey = $message->getUniqueKey();
                $this->indexMessages->refreshUnique($message, uniqueKey: $messageUniqueKey);
            }
        }

        // Validate the tickets
        foreach ($this->indexTickets->list() as $id => $ticket) {
            $error = $this->validate($ticket);
            if ($error) {
                $this->errors[] = "Ticket {$id} error: {$error}";
            }
        }

        yield "ok\n";
    }

    /**
     * @phpstan-impure
     *
     * @param mixed[] $json
     */
    private function processTicketTimeSpents(string $ticketId, Entity\Ticket $ticket, array $json): void
    {
        $requiredFields = [
            'createdAt',
            'createdById',
            'time',
            'realTime',
        ];

        foreach ($json as $jsonTimeSpent) {
            // Check the structure of the time spent
            $error = self::checkStructure($jsonTimeSpent, required: $requiredFields);
            if ($error) {
                $this->errors[] = "Ticket {$ticketId} error: timeSpents: {$error}";
                continue;
            }

            $createdAt = self::parseDatetime($jsonTimeSpent['createdAt']);
            $createdById = strval($jsonTimeSpent['createdById']);
            $time = intval($jsonTimeSpent['time']);
            $realTime = intval($jsonTimeSpent['realTime']);

            $contractId = null;
            if (isset($jsonTimeSpent['contractId'])) {
                $contractId = strval($jsonTimeSpent['contractId']);
            }

            // Build the time spent
            $timeSpent = new Entity\TimeSpent();
            $timeSpent->setCreatedAt($createdAt);
            $timeSpent->setUpdatedAt($createdAt);
            $timeSpent->setTime($time);
            $timeSpent->setRealTime($realTime);

            $timeSpent->setUid(Utils\Random::hex(20));

            // Check and set references
            $createdBy = $this->indexUsers->get($createdById);

            if ($createdBy) {
                $timeSpent->setCreatedBy($createdBy);
                $timeSpent->setUpdatedBy($createdBy);
            } else {
                $this->errors[] = "Ticket {$ticketId} error: timeSpents: "
                                . "references an unknown user {$createdById}";
            }

            if ($contractId !== null) {
                $contract = $this->indexContracts->get($contractId);

                if ($contract) {
                    $timeSpent->setContract($contract);
                } else {
                    $this->errors[] = "Ticket {$ticketId} error: timeSpents: "
                                    . "references an unknown contract {$contractId}";
                }
            }

            // Validate the time spent
            $error = $this->validate($timeSpent);
            if ($error) {
                $this->errors[] = "Ticket {$ticketId} error: timeSpents: {$error}";
            }

            // Add the time spent to the ticket
            $ticket->addTimeSpent($timeSpent);
        }
    }

    /**
     * @phpstan-impure
     *
     * @param mixed[] $json
     */
    private function processTicketMessages(
        string $ticketId,
        Entity\Ticket $ticket,
        array $json,
        ?string $solutionId,
    ): void {
        $requiredFields = [
            'id',
            'createdAt',
            'createdById',
            'content',
        ];

        foreach ($json as $jsonMessage) {
            // Check the structure of the message
            $error = self::checkStructure($jsonMessage, required: $requiredFields);
            if ($error) {
                $this->errors[] = "Ticket {$ticketId} error: messages: {$error}";
                continue;
            }

            $id = strval($jsonMessage['id']);
            $createdAt = self::parseDatetime($jsonMessage['createdAt']);
            $createdById = strval($jsonMessage['createdById']);

            $content = strval($jsonMessage['content']);
            $content = $this->appMessageSanitizer->sanitize($content);

            $postedAt = null;
            if (isset($jsonMessage['postedAt'])) {
                $postedAt = self::parseDatetime($jsonMessage['postedAt']);
            }

            $isConfidential = null;
            if (isset($jsonMessage['isConfidential'])) {
                $isConfidential = boolval($jsonMessage['isConfidential']);
            }

            $via = null;
            if (isset($jsonMessage['via'])) {
                $via = strval($jsonMessage['via']);
            }

            $notificationsReferences = [];
            if (isset($jsonMessage['notificationsReferences'])) {
                $notificationsReferences = $jsonMessage['notificationsReferences'];
            }

            $messageDocuments = [];
            if (isset($jsonMessage['messageDocuments'])) {
                $messageDocuments = $jsonMessage['messageDocuments'];
            }

            // Build the message
            $message = new Entity\Message();
            $message->setCreatedAt($createdAt);
            $message->setUpdatedAt($createdAt);
            $message->setContent($content);

            $message->setUid(Utils\Random::hex(20));

            if ($postedAt) {
                $message->setPostedAt($postedAt);
            } else {
                $message->setPostedAt($createdAt);
            }

            if ($isConfidential !== null) {
                $message->setIsConfidential($isConfidential);
            }

            if ($via !== null) {
                $message->setVia($via);
            }

            if ($solutionId === $id) {
                $ticket->setSolution($message);
            }

            if (is_array($notificationsReferences)) {
                $message->setNotificationsReferences($notificationsReferences);
            } else {
                $this->errors[] = "Message {$id} error: notificationsReferences: not an array.";
            }

            // Check and set references
            $createdBy = $this->indexUsers->get($createdById);

            if ($createdBy) {
                $message->setCreatedBy($createdBy);
                $message->setUpdatedBy($createdBy);
            } else {
                $this->errors[] = "Ticket {$ticketId} error: message {$id}: "
                                . "references an unknown user {$createdById}";
            }

            if ($this->documentsPath !== '') {
                // Process (and import) the message documents only if the
                // documentsPath is set.
                if (is_array($messageDocuments)) {
                    $this->processMessageDocuments($id, $messageDocuments);
                } else {
                    $this->errors[] = "Ticket {$ticketId} error: message {$id}: "
                                    . "messageDocuments: not an array.";
                }
            }

            // Validate the message
            $error = $this->validate($message);
            if ($error) {
                $this->errors[] = "Ticket {$ticketId} error: message {$id}: {$error}";
            }

            // Add the message to the ticket and to the index
            $ticket->addMessage($message);

            try {
                $uniqueKey = $message->getUniqueKey();
                $this->indexMessages->add($id, $message, uniqueKey: $uniqueKey);
            } catch (IndexError $e) {
                $this->errors[] = "Ticket {$ticketId} error: message {$id}: {$e->getMessage()}";
            }
        }
    }

    /**
     * @phpstan-impure
     *
     * @param mixed[] $json
     */
    private function processMessageDocuments(string $messageId, array $json): void
    {
        if ($this->documentsPath === '') {
            return;
        }

        $requiredFields = [
            'name',
            'filepath',
        ];

        $messageDocuments = [];

        foreach ($json as $jsonMessageDocument) {
            // Check the structure of the messageDocument
            $error = self::checkStructure($jsonMessageDocument, required: $requiredFields);
            if ($error) {
                $this->errors[] = "Message {$messageId} error: document: {$error}";
                continue;
            }

            $name = strval($jsonMessageDocument['name']);
            $filepath = strval($jsonMessageDocument['filepath']);

            $fullFilepath = "{$this->documentsPath}/{$filepath}";
            if (!file_exists($fullFilepath)) {
                $this->errors[] = "Message {$messageId} error: document {$name}: "
                                . "references an unknown document {$filepath}";
            }

            $messageDocuments[] = [
                'name' => $name,
                'filepath' => $filepath,
            ];
        }

        // Add the message documents to the index
        try {
            $this->indexMessageToDocuments->add($messageId, $messageDocuments);
        } catch (IndexError $e) {
            // Ignore on purpose as this same error will be catch when adding
            // the message to its own index.
        }
    }

    /**
     * Check that the specified data is an array and contains the given
     * required fields.
     *
     * @param string[] $required
     */
    private static function checkStructure(mixed $data, array $required = []): string
    {
        if (!is_array($data)) {
            return 'not an array';
        }

        foreach ($required as $requiredField) {
            if (!isset($data[$requiredField])) {
                return "missing required field {$requiredField}";
            }
        }

        return '';
    }

    private static function parseDatetime(mixed $value): ?\DateTimeImmutable
    {
        $value = strval($value);

        $datetime = \DateTimeImmutable::createFromFormat(\DateTimeInterface::RFC3339, $value);

        if ($datetime === false) {
            return null;
        }

        return $datetime;
    }

    /**
     * @param Entity\EntityInterface[] $entities
     * @param string[] $additionalAssociations
     *
     * @return \Generator<int, string, void, void>
     */
    private function importEntities(array $entities, array $additionalAssociations = []): \Generator
    {
        if (empty($entities)) {
            return;
        }

        $entities = array_values($entities);
        $entityClass = $entities[0]::class;

        $entitiesToSave = [];
        foreach ($entities as $entity) {
            // We save only entities that are not yet in the database.
            if ($entity->getId() === null) {
                $entitiesToSave[] = $entity;
            }
        }

        $countEntities = count($entitiesToSave);
        yield "Saving {$countEntities} {$entityClass}… ";

        $this->persister->insertEntities($entitiesToSave, $additionalAssociations);

        yield "ok\n";
    }

    /**
     * @return \Generator<int, string, void, void>
     */
    private function importMessageDocuments(bool $trustMimeTypes): \Generator
    {
        if ($this->documentsPath === '') {
            return;
        }

        $messageDocuments = [];

        $count = $this->indexMessageToDocuments->count();
        yield "Processing documents of {$count} messages… ";

        $hasErrors = false;

        foreach ($this->indexMessageToDocuments->list() as $messageId => $jsonMessageDocuments) {
            $message = $this->indexMessages->get($messageId);

            // Don't reupload the documents of this message as it already has some.
            if (!$message->getMessageDocuments()->isEmpty()) {
                continue;
            }

            foreach ($jsonMessageDocuments as $jsonMessageDocument) {
                $filename = $jsonMessageDocument['name'];
                $filepath = "{$this->documentsPath}/{$jsonMessageDocument['filepath']}";
                $file = new File($filepath, false);

                try {
                    $messageDocument = $this->messageDocumentStorage->store(
                        $file,
                        $filename,
                        copy: true,
                        trustMimeType: $trustMimeTypes,
                    );
                } catch (Service\MessageDocumentStorageError $e) {
                    $hasErrors = true;
                    yield "Cannot store the file {$filename}: {$e->getMessage()}\n";
                    continue;
                }

                $messageDocument->setCreatedBy($message->getCreatedBy());
                $messageDocument->setUpdatedBy($message->getCreatedBy());
                $messageDocument->setMessage($message);

                $messageDocument->setUid(Utils\Random::hex(20));
                $messageDocument->setCreatedAt($message->getCreatedAt());
                $messageDocument->setUpdatedAt($message->getCreatedAt());

                $messageDocuments[] = $messageDocument;
            }
        }

        if ($hasErrors) {
            yield "Errors occurred when storing files, so stop there.\n";
            return;
        }

        yield "ok\n";

        yield from $this->importEntities($messageDocuments);
    }

    private function validate(Entity\EntityInterface $entity): string
    {
        if ($entity->getId() !== null) {
            // Don't validate entities that are already saved in the database.
            return '';
        }

        $rawErrors = $this->validator->validate($entity);
        if (count($rawErrors) === 0) {
            return '';
        }

        $formattedErrors = Utils\ConstraintErrorsFormatter::format($rawErrors);
        return implode(' ', $formattedErrors);
    }
}
