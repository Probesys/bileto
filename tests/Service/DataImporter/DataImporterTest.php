<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Service\DataImporter;

use App\Service\DataImporter\DataImporter;
use App\Service\DataImporter\DataImporterError;
use App\Tests;
use App\Tests\Factory;
use PHPUnit\Framework\Attributes\Before;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class DataImporterTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;
    use Tests\MessageDocumentStorageHelper;

    private DataImporter $dataImporter;

    #[Before]
    public function setupTest(): void
    {
        $client = static::createClient();
        $container = static::getContainer();

        /** @var DataImporter */
        $dataImporter = $container->get(DataImporter::class);
        $this->dataImporter = $dataImporter;
    }

    /**
     * @param \Generator<int, string, void, void> $generator
     *
     * @return string[]
     */
    private function processGenerator(\Generator $generator): array
    {
        return iterator_to_array($generator);
    }

    public function testImportOrganizations(): void
    {
        $organizations = [
            [
                'id' => '1',
                'name' => 'Foo',
                'domains' => ['example.org', '*'],
            ],
        ];

        $this->processGenerator($this->dataImporter->import(organizations: $organizations));

        $this->assertSame(1, Factory\OrganizationFactory::count());
        $organization = Factory\OrganizationFactory::last();
        $this->assertSame('Foo', $organization->getName());
        $this->assertEquals(['example.org', '*'], $organization->getDomains());
    }

    public function testImportOrganizationsKeepsExistingOrganizationsInDatabase(): void
    {
        $existingOrganization = Factory\OrganizationFactory::createOne([
            'name' => 'Foo',
        ]);

        $organizations = [
            [
                'id' => '1',
                'name' => 'Foo',
            ],
        ];

        $this->processGenerator($this->dataImporter->import(organizations: $organizations));

        $this->assertSame(1, Factory\OrganizationFactory::count());
        $organization = Factory\OrganizationFactory::last();
        $this->assertSame($existingOrganization->getUid(), $organization->getUid());
    }

    public function testImportOrganizationsFailsIfIdIsDuplicatedInFile(): void
    {
        $this->expectException(DataImporterError::class);
        $this->expectExceptionMessage('Organization 1 error: id is duplicated');

        $organizations = [
            [
                'id' => '1',
                'name' => 'Foo',
            ],
            [
                'id' => '1',
                'name' => 'Bar',
            ],
        ];

        $this->processGenerator($this->dataImporter->import(organizations: $organizations));

        $this->assertSame(0, Factory\OrganizationFactory::count());
    }

    public function testImportOrganizationsFailsIfNameIsDuplicatedInFile(): void
    {
        $this->expectException(DataImporterError::class);
        $this->expectExceptionMessage('Organization 2 error: duplicates id 1');

        $organizations = [
            [
                'id' => '1',
                'name' => 'Foo',
            ],
            [
                'id' => '2',
                'name' => 'Foo',
            ],
        ];

        $this->processGenerator($this->dataImporter->import(organizations: $organizations));

        $this->assertSame(0, Factory\OrganizationFactory::count());
    }

    public function testImportOrganizationsFailsIfOrganizationIsInvalid(): void
    {
        $this->expectException(DataImporterError::class);
        $this->expectExceptionMessage('Organization 1 error: Enter a name.');

        $organizations = [
            [
                'id' => '1',
                'name' => '',
            ],
        ];

        $this->processGenerator($this->dataImporter->import(organizations: $organizations));

        $this->assertSame(0, Factory\OrganizationFactory::count());
    }

    public function testImportOrganizationsFailsIfDomainsAreDuplicated(): void
    {
        $this->expectException(DataImporterError::class);
        $this->expectExceptionMessage('Organization 1 error: The domain example.org is already used');

        $exisitingOrganization = Factory\OrganizationFactory::createOne([
            'domains' => ['example.org'],
        ]);

        $organizations = [
            [
                'id' => '1',
                'name' => 'Foo',
                'domains' => ['example.org'],
            ],
        ];

        $this->processGenerator($this->dataImporter->import(organizations: $organizations));

        $this->assertSame(0, Factory\OrganizationFactory::count());
    }

    public function testImportOrganizationsFailsIfDomainsAreInvalid(): void
    {
        $this->expectException(DataImporterError::class);
        $this->expectExceptionMessage('Organization 1 error: The domain not a domain is invalid');

        $organizations = [
            [
                'id' => '1',
                'name' => 'Foo',
                'domains' => ['not a domain'],
            ],
        ];

        $this->processGenerator($this->dataImporter->import(organizations: $organizations));

        $this->assertSame(0, Factory\OrganizationFactory::count());
    }

    public function testImportRoles(): void
    {
        $roles = [
            [
                'id' => '1',
                'name' => 'Foo',
                'description' => 'Foo description',
                'type' => 'user',
                'isDefault' => true,
                'permissions' => [
                    'orga:create:tickets',
                    'orga:see',
                    'admin:see',
                ],
            ],
        ];

        $this->processGenerator($this->dataImporter->import(roles: $roles));

        $this->assertSame(1, Factory\RoleFactory::count());
        $role = Factory\RoleFactory::last();
        $this->assertSame('Foo', $role->getName());
        $this->assertSame('Foo description', $role->getDescription());
        $this->assertSame('user', $role->getType());
        $this->assertTrue($role->isDefault());
        $this->assertSame([
            'orga:create:tickets',
            'orga:see',
        ], $role->getPermissions());
    }

    public function testImportRolesKeepsExistingRolesInDatabase(): void
    {
        $exisitingRole = Factory\RoleFactory::createOne([
            'name' => 'Foo',
        ]);

        $roles = [
            [
                'id' => '1',
                'name' => 'Foo',
                'description' => 'Foo description',
                'type' => 'user',
                'permissions' => [
                    'orga:create:tickets',
                    'orga:see',
                ],
            ],
        ];

        $this->processGenerator($this->dataImporter->import(roles: $roles));

        $this->assertSame(1, Factory\RoleFactory::count());
        $role = Factory\RoleFactory::last();
        $this->assertSame($exisitingRole->getUid(), $role->getUid());
    }

    public function testImportRolesKeepsSuperRoleInDatabase(): void
    {
        /** @var \App\Repository\RoleRepository */
        $roleRepository = Factory\RoleFactory::repository();
        $superRole = $roleRepository->findOrCreateSuperRole();

        $roles = [
            [
                'id' => '1',
                'name' => 'Foo',
                'description' => 'Foo description',
                'type' => 'super',
                'permissions' => ['admin:*'],
            ],
        ];

        $this->processGenerator($this->dataImporter->import(roles: $roles));

        $this->assertSame(1, Factory\RoleFactory::count());
        $role = Factory\RoleFactory::last();
        $this->assertSame($superRole->getUid(), $role->getUid());
    }

    public function testImportRolesKeepsDefaultRoleInDatabase(): void
    {
        $defaultRole = Factory\RoleFactory::createOne([
            'type' => 'user',
            'isDefault' => true,
        ]);

        $roles = [
            [
                'id' => '1',
                'name' => 'Foo',
                'description' => 'Foo description',
                'type' => 'user',
                'isDefault' => true,
                'permissions' => [],
            ],
        ];

        $this->processGenerator($this->dataImporter->import(roles: $roles));

        $this->assertSame(2, Factory\RoleFactory::count());
        $role = Factory\RoleFactory::last();
        $this->assertFalse($role->isDefault());
    }

    public function testImportRolesFailsIfIdIsDuplicatedInFile(): void
    {
        $this->expectException(DataImporterError::class);
        $this->expectExceptionMessage('Role 1 error: id is duplicated');

        $roles = [
            [
                'id' => '1',
                'name' => 'Foo',
                'description' => 'Foo description',
                'type' => 'user',
            ],
            [
                'id' => '1',
                'name' => 'Bar',
                'description' => 'Bar description',
                'type' => 'user',
            ],
        ];

        $this->processGenerator($this->dataImporter->import(roles: $roles));

        $this->assertSame(0, Factory\RoleFactory::count());
    }

    public function testImportRolesFailsIfNameIsDuplicatedInFile(): void
    {
        $this->expectException(DataImporterError::class);
        $this->expectExceptionMessage('Role 2 error: duplicates id 1');

        $roles = [
            [
                'id' => '1',
                'name' => 'Foo',
                'description' => 'Foo description',
                'type' => 'user',
            ],
            [
                'id' => '2',
                'name' => 'Foo',
                'description' => 'Foo description',
                'type' => 'agent',
            ],
        ];

        $this->processGenerator($this->dataImporter->import(roles: $roles));

        $this->assertSame(0, Factory\RoleFactory::count());
    }

    public function testImportRolesFailsIfTypeSuperIsDuplicatedInFile(): void
    {
        $this->expectException(DataImporterError::class);
        $this->expectExceptionMessage('Role 2 error: duplicates id 1');

        $roles = [
            [
                'id' => '1',
                'name' => 'Foo',
                'description' => 'Foo description',
                'type' => 'super',
            ],
            [
                'id' => '2',
                'name' => 'Bar',
                'description' => 'Bar description',
                'type' => 'super',
            ],
        ];

        $this->processGenerator($this->dataImporter->import(roles: $roles));

        $this->assertSame(0, Factory\RoleFactory::count());
    }

    public function testImportRolesFailsIfDefaultIsDuplicatedInFile(): void
    {
        $this->expectException(DataImporterError::class);
        $this->expectExceptionMessage('Role 2 error: duplicates id 1');

        $roles = [
            [
                'id' => '1',
                'name' => 'Foo',
                'description' => 'Foo description',
                'type' => 'user',
                'isDefault' => true,
            ],
            [
                'id' => '2',
                'name' => 'Bar',
                'description' => 'Bar description',
                'type' => 'user',
                'isDefault' => true,
            ],
        ];

        $this->processGenerator($this->dataImporter->import(roles: $roles));

        $this->assertSame(0, Factory\RoleFactory::count());
    }

    public function testImportRolesFailsIfNotUserTypeIsDefaultRole(): void
    {
        $this->expectException(DataImporterError::class);
        $this->expectExceptionMessage('Role 1 error: agent role cannot be a default role');

        $roles = [
            [
                'id' => '1',
                'name' => 'Foo',
                'description' => 'Foo description',
                'type' => 'agent',
                'isDefault' => true,
            ],
        ];

        $this->processGenerator($this->dataImporter->import(roles: $roles));

        $this->assertSame(0, Factory\RoleFactory::count());
    }

    public function testImportRolesFailsIfRoleIsInvalid(): void
    {
        $this->expectException(DataImporterError::class);
        $this->expectExceptionMessage('Role 1 error: The value you selected is not a valid choice.');

        $roles = [
            [
                'id' => '1',
                'name' => 'Foo',
                'description' => 'Foo description',
                'type' => 'foo',
            ],
        ];

        $this->processGenerator($this->dataImporter->import(roles: $roles));

        $this->assertSame(0, Factory\RoleFactory::count());
    }

    public function testImportUsers(): void
    {
        $organizations = [
            [
                'id' => '1',
                'name' => 'Foo',
            ],
        ];
        $roles = [
            [
                'id' => '1',
                'name' => 'Foo',
                'description' => 'Foo description',
                'type' => 'user',
            ],
        ];
        $users = [
            [
                'id' => '1',
                'name' => 'Alix Hambourg',
                'email' => 'alix@example.com',
                'locale' => 'fr_FR',
                'ldapIdentifier' => 'alix.hambourg',
                'organizationId' => '1',
                'authorizations' => [
                    [
                        'roleId' => '1',
                        'organizationId' => '1',
                    ],
                ],
            ],
        ];

        $this->processGenerator($this->dataImporter->import(
            organizations: $organizations,
            roles: $roles,
            users: $users,
        ));

        $this->assertSame(1, Factory\UserFactory::count());
        $user = Factory\UserFactory::last();
        $user->_refresh();
        $this->assertSame('Alix Hambourg', $user->getName());
        $this->assertSame('alix@example.com', $user->getEmail());
        $this->assertSame('fr_FR', $user->getLocale());
        $this->assertSame('alix.hambourg', $user->getLdapIdentifier());
        $organization = $user->getOrganization();
        $this->assertNotNull($organization);
        $this->assertSame('Foo', $organization->getName());
        $authorizations = $user->getAuthorizations();
        $this->assertSame(1, count($authorizations));
        $authOrganization = $authorizations[0]->getOrganization();
        $authRole = $authorizations[0]->getRole();
        $this->assertNotNull($authOrganization);
        $this->assertSame('Foo', $authOrganization->getName());
        $this->assertSame('Foo', $authRole->getName());
    }

    public function testImportUsersKeepsExistingUsersInDatabase(): void
    {
        $existingUser = Factory\UserFactory::createOne([
            'email' => 'alix@example.com',
        ]);

        $users = [
            [
                'id' => '1',
                'email' => 'alix@example.com',
            ],
        ];

        $this->processGenerator($this->dataImporter->import(users: $users));

        $this->assertSame(1, Factory\UserFactory::count());
        $user = Factory\UserFactory::last();
        $this->assertSame($existingUser->getUid(), $user->getUid());
    }

    public function testImportUsersFailsIfIdIsDuplicatedInFile(): void
    {
        $this->expectException(DataImporterError::class);
        $this->expectExceptionMessage('User 1 error: id is duplicated');

        $users = [
            [
                'id' => '1',
                'email' => 'alix@example.com',
            ],
            [
                'id' => '1',
                'email' => 'benedict@example.com',
            ],
        ];

        $this->processGenerator($this->dataImporter->import(users: $users));

        $this->assertSame(0, Factory\UserFactory::count());
    }

    public function testImportUsersFailsIfEmailIsDuplicatedInFile(): void
    {
        $this->expectException(DataImporterError::class);
        $this->expectExceptionMessage('User 2 error: duplicates id 1');

        $users = [
            [
                'id' => '1',
                'email' => 'alix@example.com',
            ],
            [
                'id' => '2',
                'email' => 'alix@example.com',
            ],
        ];

        $this->processGenerator($this->dataImporter->import(users: $users));

        $this->assertSame(0, Factory\UserFactory::count());
    }

    public function testImportUsersFailsIfUserIsInvalid(): void
    {
        $this->expectException(DataImporterError::class);
        $this->expectExceptionMessage('User 1 error: Enter a valid email address');

        $users = [
            [
                'id' => '1',
                'email' => 'not an email',
            ],
        ];

        $this->processGenerator($this->dataImporter->import(users: $users));

        $this->assertSame(0, Factory\UserFactory::count());
    }

    public function testImportUsersFailsIfOrganizationRefersToUnknownId(): void
    {
        $this->expectException(DataImporterError::class);
        $this->expectExceptionMessage('User 1 error: references an unknown organization 1.');

        $users = [
            [
                'id' => '1',
                'email' => 'alix@example.com',
                'organizationId' => '1',
            ],
        ];

        $this->processGenerator($this->dataImporter->import(users: $users));

        $this->assertSame(0, Factory\UserFactory::count());
    }

    public function testImportUsersFailsIfAuthorizationRefersToUnknownRole(): void
    {
        $this->expectException(DataImporterError::class);
        $this->expectExceptionMessage('User 1 error: authorizations: references an unknown role 1');

        $users = [
            [
                'id' => '1',
                'email' => 'alix@example.com',
                'authorizations' => [
                    [
                        'roleId' => '1',
                        'organizationId' => null,
                    ],
                ],
            ],
        ];

        $this->processGenerator($this->dataImporter->import(users: $users));

        $this->assertSame(0, Factory\UserFactory::count());
    }

    public function testImportUsersFailsIfAuthorizationRefersToUnknownOrganization(): void
    {
        $this->expectException(DataImporterError::class);
        $this->expectExceptionMessage('User 1 error: authorizations: references an unknown organization 1');

        $roles = [
            [
                'id' => '1',
                'name' => 'Foo',
                'description' => 'Foo description',
                'type' => 'user',
            ],
        ];
        $users = [
            [
                'id' => '1',
                'email' => 'alix@example.com',
                'authorizations' => [
                    [
                        'roleId' => '1',
                        'organizationId' => '1',
                    ],
                ],
            ],
        ];

        $this->processGenerator($this->dataImporter->import(
            roles: $roles,
            users: $users,
        ));

        $this->assertSame(0, Factory\UserFactory::count());
    }

    public function testImportTeams(): void
    {
        $organizations = [
            [
                'id' => '1',
                'name' => 'My organization',
            ],
        ];
        $roles = [
            [
                'id' => '1',
                'name' => 'My role',
                'description' => 'Role description',
                'type' => 'agent',
            ],
        ];
        $users = [
            [
                'id' => '1',
                'email' => 'alix@example.com',
            ],
        ];
        $teams = [
            [
                'id' => '1',
                'name' => 'My team',
                'isResponsible' => true,
                'teamAuthorizations' => [
                    [
                        'roleId' => '1',
                        'organizationId' => '1',
                    ],
                ],
            ],
        ];

        $this->processGenerator($this->dataImporter->import(
            organizations: $organizations,
            roles: $roles,
            users: $users,
            teams: $teams,
        ));

        $this->assertSame(1, Factory\TeamFactory::count());
        $team = Factory\TeamFactory::last();
        $this->assertSame('My team', $team->getName());
        $this->assertTrue($team->isResponsible());
        $teamAuthorizations = $team->getTeamAuthorizations();
        $this->assertSame(1, count($teamAuthorizations));
        $authOrganization = $teamAuthorizations[0]->getOrganization();
        $authRole = $teamAuthorizations[0]->getRole();
        $this->assertNotNull($authOrganization);
        $this->assertSame('My organization', $authOrganization->getName());
        $this->assertSame('My role', $authRole->getName());
    }

    public function testImportTeamsKeepsExistingTeamsInDatabase(): void
    {
        $existingTeam = Factory\TeamFactory::createOne([
            'name' => 'My team',
        ]);

        $teams = [
            [
                'id' => '1',
                'name' => 'My team',
            ],
        ];

        $this->processGenerator($this->dataImporter->import(
            teams: $teams,
        ));

        $this->assertSame(1, Factory\TeamFactory::count());
        $team = Factory\TeamFactory::last();
        $this->assertSame($existingTeam->getUid(), $team->getUid());
    }

    public function testImportTeamsFailsIfIdIsDuplicatedInFile(): void
    {
        $this->expectException(DataImporterError::class);
        $this->expectExceptionMessage('Team 1 error: id is duplicated');

        $teams = [
            [
                'id' => '1',
                'name' => 'My team 1',
            ],
            [
                'id' => '1',
                'name' => 'My team 2',
            ],
        ];

        $this->processGenerator($this->dataImporter->import(
            teams: $teams,
        ));

        $this->assertSame(0, Factory\TeamFactory::count());
    }

    public function testImportTeamsFailsIfNameIsDuplicatedInFile(): void
    {
        $this->expectException(DataImporterError::class);
        $this->expectExceptionMessage('Team 2 error: duplicates id 1');

        $teams = [
            [
                'id' => '1',
                'name' => 'My team',
            ],
            [
                'id' => '2',
                'name' => 'My team',
            ],
        ];

        $this->processGenerator($this->dataImporter->import(
            teams: $teams,
        ));

        $this->assertSame(0, Factory\TeamFactory::count());
    }

    public function testImportTeamsFailsIfNameIsInvalid(): void
    {
        $this->expectException(DataImporterError::class);
        $this->expectExceptionMessage('Team 1 error: Enter a name');

        $teams = [
            [
                'id' => '1',
                'name' => '',
            ],
        ];

        $this->processGenerator($this->dataImporter->import(
            teams: $teams,
        ));

        $this->assertSame(0, Factory\TeamFactory::count());
    }

    public function testImportTeamsFailsIfTeamAuthorizationRefersToUnknownRole(): void
    {
        $this->expectException(DataImporterError::class);
        $this->expectExceptionMessage('Team 1 error: teamAuthorizations: references an unknown role 1');

        $teams = [
            [
                'id' => '1',
                'name' => 'My team',
                'teamAuthorizations' => [
                    [
                        'roleId' => '1',
                        'organizationId' => null,
                    ],
                ],
            ],
        ];

        $this->processGenerator($this->dataImporter->import(
            teams: $teams,
        ));

        $this->assertSame(0, Factory\TeamFactory::count());
    }

    public function testImportTeamsFailsIfTeamAuthorizationRefersToUnknownOrganization(): void
    {
        $this->expectException(DataImporterError::class);
        $this->expectExceptionMessage('Team 1 error: teamAuthorizations: references an unknown organization 1');

        $roles = [
            [
                'id' => '1',
                'name' => 'My role',
                'description' => 'Role description',
                'type' => 'agent',
            ],
        ];
        $teams = [
            [
                'id' => '1',
                'name' => 'My team',
                'teamAuthorizations' => [
                    [
                        'roleId' => '1',
                        'organizationId' => '1',
                    ],
                ],
            ],
        ];

        $this->processGenerator($this->dataImporter->import(
            roles: $roles,
            teams: $teams,
        ));

        $this->assertSame(0, Factory\TeamFactory::count());
    }

    public function testImportContracts(): void
    {
        $organizations = [
            [
                'id' => '1',
                'name' => 'Foo',
            ],
        ];
        $contracts = [
            [
                'id' => '1',
                'name' => 'My contract',
                'startAt' => '2024-01-01T00:00:00+00:00',
                'endAt' => '2024-12-31T23:59:59+00:00',
                'maxHours' => 42,
                'notes' => 'My notes',
                'organizationId' => '1',
                'timeAccountingUnit' => 30,
                'hoursAlert' => 80,
                'dateAlert' => 60,
            ],
        ];

        $this->processGenerator($this->dataImporter->import(
            organizations: $organizations,
            contracts: $contracts,
        ));

        $this->assertSame(1, Factory\ContractFactory::count());
        $contract = Factory\ContractFactory::last();
        $this->assertSame('My contract', $contract->getName());
        $this->assertSame(1704067200, $contract->getStartAt()->getTimestamp());
        $this->assertSame(1735689599, $contract->getEndAt()->getTimestamp());
        $this->assertSame(42, $contract->getMaxHours());
        $this->assertSame('My notes', $contract->getNotes());
        $this->assertSame(30, $contract->getTimeAccountingUnit());
        $this->assertSame(80, $contract->getHoursAlert());
        $this->assertSame(60, $contract->getDateAlert());
        $organization = $contract->getOrganization();
        $this->assertNotNull($organization);
        $this->assertSame('Foo', $organization->getName());
    }

    public function testImportContractsKeepsExistingContractsInDatabase(): void
    {
        $existingOrganization = Factory\OrganizationFactory::createOne([
            'name' => 'Foo',
        ]);
        $existingContract = Factory\ContractFactory::createOne([
            'name' => 'My contract',
            'organization' => $existingOrganization,
            'startAt' => new \DateTimeImmutable('2024-01-01T00:00:00+00:00'),
            'endAt' => new \DateTimeImmutable('2024-12-31T23:59:59+00:00'),
        ]);

        $organizations = [
            [
                'id' => '1',
                'name' => 'Foo',
            ],
        ];
        $contracts = [
            [
                'id' => '1',
                'name' => 'My contract',
                'startAt' => '2024-01-01T00:00:00+00:00',
                'endAt' => '2024-12-31T23:59:59+00:00',
                'maxHours' => 42,
                'organizationId' => '1',
            ],
        ];

        $this->processGenerator($this->dataImporter->import(
            organizations: $organizations,
            contracts: $contracts,
        ));

        $this->assertSame(1, Factory\ContractFactory::count());
        $contract = Factory\ContractFactory::last();
        $this->assertSame($existingContract->getUid(), $contract->getUid());
    }

    public function testImportContractsFailsIfIdIsDuplicatedInFile(): void
    {
        $this->expectException(DataImporterError::class);
        $this->expectExceptionMessage('Contract 1 error: id is duplicated');

        $organizations = [
            [
                'id' => '1',
                'name' => 'Foo',
            ],
        ];
        $contracts = [
            [
                'id' => '1',
                'name' => 'My contract',
                'startAt' => '2024-01-01T00:00:00+00:00',
                'endAt' => '2024-12-31T23:59:59+00:00',
                'maxHours' => 42,
                'organizationId' => '1',
            ],
            [
                'id' => '1',
                'name' => 'My contract 2',
                'startAt' => '2025-01-01T00:00:00+00:00',
                'endAt' => '2025-12-31T23:59:59+00:00',
                'maxHours' => 42,
                'organizationId' => '1',
            ],
        ];

        $this->processGenerator($this->dataImporter->import(
            organizations: $organizations,
            contracts: $contracts,
        ));

        $this->assertSame(0, Factory\ContractFactory::count());
    }

    public function testImportContractsFailsIfFieldsAreDuplicatedInFile(): void
    {
        $this->expectException(DataImporterError::class);
        $this->expectExceptionMessage('Contract 2 error: duplicates id 1');

        $organizations = [
            [
                'id' => '1',
                'name' => 'Foo',
            ],
        ];
        // A contract is duplicated if its name + organization + startAt +
        // endAt are identical.
        $contracts = [
            [
                'id' => '1',
                'name' => 'My contract',
                'startAt' => '2024-01-01T00:00:00+00:00',
                'endAt' => '2024-12-31T23:59:59+00:00',
                'maxHours' => 42,
                'notes' => 'My notes',
                'organizationId' => '1',
                'timeAccountingUnit' => 30,
                'hoursAlert' => 80,
                'dateAlert' => 60,
            ],
            [
                'id' => '2',
                'name' => 'My contract',
                'startAt' => '2024-01-01T00:00:00+00:00',
                'endAt' => '2024-12-31T23:59:59+00:00',
                'maxHours' => 45,
                'notes' => '',
                'organizationId' => '1',
                'timeAccountingUnit' => 20,
                'hoursAlert' => 50,
                'dateAlert' => 50,
            ],
        ];

        $this->processGenerator($this->dataImporter->import(
            organizations: $organizations,
            contracts: $contracts,
        ));

        $this->assertSame(0, Factory\ContractFactory::count());
    }

    public function testImportContractsFailsIfContractIsInvalid(): void
    {
        $this->expectException(DataImporterError::class);
        $this->expectExceptionMessage('Contract 1 error: Enter a date greater than the start date.');

        $organizations = [
            [
                'id' => '1',
                'name' => 'Foo',
            ],
        ];
        $contracts = [
            [
                'id' => '1',
                'name' => 'My contract',
                'startAt' => '2025-01-01T00:00:00+00:00',
                'endAt' => '2024-12-31T23:59:59+00:00',
                'maxHours' => 42,
                'organizationId' => '1',
            ],
        ];

        $this->processGenerator($this->dataImporter->import(
            organizations: $organizations,
            contracts: $contracts,
        ));

        $this->assertSame(0, Factory\ContractFactory::count());
    }

    public function testImportContractsFailsIfOrganizationRefersToUnknownId(): void
    {
        $this->expectException(DataImporterError::class);
        $this->expectExceptionMessage('Contract 1 error: references an unknown organization 1.');

        $contracts = [
            [
                'id' => '1',
                'name' => 'My contract',
                'startAt' => '2024-01-01T00:00:00+00:00',
                'endAt' => '2024-12-31T23:59:59+00:00',
                'maxHours' => 42,
                'organizationId' => '1',
            ],
        ];

        $this->processGenerator($this->dataImporter->import(
            contracts: $contracts,
        ));

        $this->assertSame(0, Factory\ContractFactory::count());
    }

    public function testImportLabels(): void
    {
        $labels = [
            [
                'id' => '1',
                'name' => 'My label',
                'description' => 'My description',
                'color' => 'primary',
            ],
        ];

        $this->processGenerator($this->dataImporter->import(
            labels: $labels,
        ));

        $this->assertSame(1, Factory\LabelFactory::count());
        $label = Factory\LabelFactory::last();
        $this->assertSame('My label', $label->getName());
        $this->assertSame('My description', $label->getDescription());
        $this->assertSame('primary', $label->getColor());
    }

    public function testImportLabelsKeepsExistingLabelsInDatabase(): void
    {
        $existingLabel = Factory\LabelFactory::createOne([
            'name' => 'My label',
            'description' => 'My description',
        ]);

        $labels = [
            [
                'id' => '1',
                'name' => 'My label',
                'description' => 'Another description',
            ],
        ];

        $this->processGenerator($this->dataImporter->import(
            labels: $labels,
        ));

        $this->assertSame(1, Factory\LabelFactory::count());
        $label = Factory\LabelFactory::last();
        $this->assertSame('My label', $label->getName());
        $this->assertSame('My description', $label->getDescription());
        $this->assertSame($existingLabel->getUid(), $label->getUid());
    }

    public function testImportLabelsFailsIfIdIsDuplicatedInFile(): void
    {
        $this->expectException(DataImporterError::class);
        $this->expectExceptionMessage('Label 1 error: id is duplicated');

        $labels = [
            [
                'id' => '1',
                'name' => 'My label 1',
            ],
            [
                'id' => '1',
                'name' => 'My label 2',
            ],
        ];

        $this->processGenerator($this->dataImporter->import(
            labels: $labels,
        ));

        $this->assertSame(0, Factory\LabelFactory::count());
    }

    public function testImportLabelsFailsIfNameIsDuplicatedInFile(): void
    {
        $this->expectException(DataImporterError::class);
        $this->expectExceptionMessage('Label 2 error: duplicates id 1');

        $labels = [
            [
                'id' => '1',
                'name' => 'My label',
            ],
            [
                'id' => '2',
                'name' => 'My label',
            ],
        ];

        $this->processGenerator($this->dataImporter->import(
            labels: $labels,
        ));

        $this->assertSame(0, Factory\LabelFactory::count());
    }

    public function testImportLabelsFailsIfLabelIsInvalid(): void
    {
        $this->expectExceptionMessage('Label 1 error: Enter a name.');

        $labels = [
            [
                'id' => '1',
                'name' => '',
            ],
        ];

        $this->processGenerator($this->dataImporter->import(
            labels: $labels,
        ));

        $this->assertSame(0, Factory\LabelFactory::count());
    }

    public function testImportTickets(): void
    {
        $organizations = [
            [
                'id' => '1',
                'name' => 'Foo',
            ],
        ];
        $users = [
            [
                'id' => '1',
                'email' => 'alix@example.com',
            ],
            [
                'id' => '2',
                'email' => 'benedict@example.com',
            ],
            [
                'id' => '3',
                'email' => 'charlie@example.com',
            ],
        ];
        $contracts = [
            [
                'id' => '1',
                'name' => 'My contract',
                'startAt' => '2024-01-01T00:00:00+00:00',
                'endAt' => '2024-12-31T23:59:59+00:00',
                'maxHours' => 42,
                'organizationId' => '1',
            ],
        ];
        $labels = [
            [
                'id' => '1',
                'name' => 'My label',
            ],
        ];
        $tickets = [
            [
                'id' => '1',
                'createdAt' => '2024-04-25T17:38:00+00:00',
                'updatedAt' => '2024-04-25T18:00:00+00:00',
                'createdById' => '1',
                'type' => 'incident',
                'status' => 'resolved',
                'title' => 'It does not work',
                'urgency' => 'low',
                'impact' => 'high',
                'priority' => 'medium',
                'requesterId' => '1',
                'assigneeId' => '2',
                'observerIds' => ['3'],
                'organizationId' => '1',
                'solutionId' => '2',
                'contractIds' => ['1'],
                'labelIds' => ['1'],
                'timeSpents' => [
                    [
                        'createdAt' => '2024-04-25T18:00:00+00:00',
                        'createdById' => '2',
                        'time' => 30,
                        'realTime' => 15,
                        'contractId' => '1',
                    ],
                ],
                'messages' => [
                    [
                        'id' => '1',
                        'createdAt' => '2024-04-25T17:38:00+00:00',
                        'createdById' => '1',
                        'isConfidential' => false,
                        'via' => 'email',
                        'content' => '<p>This is not working!</p>',
                    ],
                    [
                        'id' => '2',
                        'createdAt' => '2024-04-25T18:00:00+00:00',
                        'createdById' => '2',
                        'isConfidential' => true,
                        'via' => 'webapp',
                        'emailId' => 'some-id@example.com',
                        'content' => '<p>Indeed, it does not work.</p>',
                    ],
                ],
            ],
        ];

        $this->processGenerator($this->dataImporter->import(
            organizations: $organizations,
            users: $users,
            contracts: $contracts,
            labels: $labels,
            tickets: $tickets,
        ));

        $this->assertSame(1, Factory\TicketFactory::count());
        $ticket = Factory\TicketFactory::last();
        $ticket->_refresh();
        $this->assertSame('It does not work', $ticket->getTitle());
        $this->assertSame(1714066680, $ticket->getCreatedAt()->getTimestamp());
        $this->assertSame(1714068000, $ticket->getUpdatedAt()->getTimestamp());
        $this->assertSame('incident', $ticket->getType());
        $this->assertSame('resolved', $ticket->getStatus());
        $this->assertSame('low', $ticket->getUrgency());
        $this->assertSame('high', $ticket->getImpact());
        $this->assertSame('medium', $ticket->getPriority());
        $createdBy = $ticket->getCreatedBy();
        $this->assertSame('alix@example.com', $createdBy->getEmail());
        $organization = $ticket->getOrganization();
        $this->assertSame('Foo', $organization->getName());
        $requester = $ticket->getRequester();
        $this->assertSame('alix@example.com', $requester->getEmail());
        $assignee = $ticket->getAssignee();
        $this->assertSame('benedict@example.com', $assignee->getEmail());
        $observers = $ticket->getObservers();
        $this->assertSame(1, count($observers));
        $this->assertSame('charlie@example.com', $observers[0]->getEmail());
        $contracts = $ticket->getContracts();
        $this->assertSame(1, count($contracts));
        $this->assertSame('My contract', $contracts[0]->getName());
        $labels = $ticket->getLabels();
        $this->assertSame(1, count($labels));
        $this->assertSame('My label', $labels[0]->getName());
        $timeSpents = $ticket->getTimeSpents();
        $this->assertSame(1, count($timeSpents));
        $this->assertSame(1714068000, $timeSpents[0]->getCreatedAt()->getTimestamp());
        $this->assertSame($assignee->getUid(), $timeSpents[0]->getCreatedBy()->getUid());
        $this->assertSame(30, $timeSpents[0]->getTime());
        $this->assertSame(15, $timeSpents[0]->getRealTime());
        $this->assertSame($contracts[0]->getUid(), $timeSpents[0]->getContract()->getUid());
        $messages = $ticket->getMessages();
        $this->assertSame(2, count($messages));
        $this->assertSame(1714066680, $messages[0]->getCreatedAt()->getTimestamp());
        $this->assertSame($requester->getUid(), $messages[0]->getCreatedBy()->getUid());
        $this->assertFalse($messages[0]->isConfidential());
        $this->assertSame('email', $messages[0]->getVia());
        $this->assertNull($messages[0]->getEmailId());
        $this->assertSame('<p>This is not working!</p>', $messages[0]->getContent());
        $this->assertSame(1714068000, $messages[1]->getCreatedAt()->getTimestamp());
        $this->assertSame($assignee->getUid(), $messages[1]->getCreatedBy()->getUid());
        $this->assertTrue($messages[1]->isConfidential());
        $this->assertSame('webapp', $messages[1]->getVia());
        $this->assertSame('some-id@example.com', $messages[1]->getEmailId());
        $this->assertSame('<p>Indeed, it does not work.</p>', $messages[1]->getContent());
        $solution = $ticket->getSolution();
        $this->assertNotNull($solution);
        $this->assertSame($messages[1]->getUid(), $solution->getUid());
    }

    public function testImportTicketsKeepsExistingTicketsInDatabase(): void
    {
        $existingOrganization = Factory\OrganizationFactory::createOne([
            'name' => 'Foo',
        ]);
        $existingTicket = Factory\TicketFactory::createOne([
            'title' => 'It does not work',
            'createdAt' => new \DateTimeImmutable('2024-04-25T17:38:00+00:00'),
            'organization' => $existingOrganization,
        ]);

        $organizations = [
            [
                'id' => '1',
                'name' => 'Foo',
            ],
        ];
        $users = [
            [
                'id' => '1',
                'email' => 'alix@example.com',
            ],
        ];
        $tickets = [
            [
                'id' => '1',
                'createdAt' => '2024-04-25T17:38:00+00:00',
                'createdById' => '1',
                'title' => 'It does not work',
                'requesterId' => '1',
                'organizationId' => '1',
            ],
        ];

        $this->processGenerator($this->dataImporter->import(
            organizations: $organizations,
            users: $users,
            tickets: $tickets,
        ));

        $this->assertSame(1, Factory\TicketFactory::count());
        $ticket = Factory\TicketFactory::last();
        $this->assertSame($existingTicket->getUid(), $ticket->getUid());
    }

    public function testImportTicketsImportsDocumentsAsWell(): void
    {
        $organizations = [
            [
                'id' => '1',
                'name' => 'Foo',
            ],
        ];
        $users = [
            [
                'id' => '1',
                'email' => 'alix@example.com',
            ],
        ];
        $tickets = [
            [
                'id' => '1',
                'createdAt' => '2024-04-25T17:38:00+00:00',
                'createdById' => '1',
                'title' => 'It does not work',
                'requesterId' => '1',
                'organizationId' => '1',
                'messages' => [
                    [
                        'id' => '1',
                        'createdAt' => '2024-04-25T17:38:00+00:00',
                        'createdById' => '1',
                        'isConfidential' => false,
                        'via' => 'email',
                        'content' => '<p>This is not working!</p>',
                        'messageDocuments' => [
                            [
                                'name' => 'The bug',
                                'filepath' => 'bug.txt',
                            ]
                        ],
                    ],
                ],
            ],
        ];
        $documentsPath = $this->createDocumentsFolder();
        $content = 'My bug';
        $hash = hash('sha256', $content);
        file_put_contents($documentsPath . '/bug.txt', $content);

        $this->processGenerator($this->dataImporter->import(
            organizations: $organizations,
            users: $users,
            tickets: $tickets,
            documentsPath: $documentsPath,
        ));

        $this->assertSame(1, Factory\MessageFactory::count());
        $message = Factory\MessageFactory::last();
        $this->assertSame(1, Factory\MessageDocumentFactory::count());
        $messageDocument = Factory\MessageDocumentFactory::first();
        $this->assertSame('The bug', $messageDocument->getName());
        $this->assertSame($hash . '.txt', $messageDocument->getFilename());
        $this->assertSame('text/plain', $messageDocument->getMimetype());
        $this->assertSame('sha256:' . $hash, $messageDocument->getHash());
        $this->assertNotNull($messageDocument->getMessage());
        $this->assertSame($message->getUid(), $messageDocument->getMessage()->getUid());
        $this->assertTrue(file_exists("{$documentsPath}/bug.txt"));
    }

    public function testImportTicketsFailsIfIdIsDuplicatedInFile(): void
    {
        $this->expectException(DataImporterError::class);
        $this->expectExceptionMessage('Ticket 1 error: id is duplicated');

        $organizations = [
            [
                'id' => '1',
                'name' => 'Foo',
            ],
        ];
        $users = [
            [
                'id' => '1',
                'email' => 'alix@example.com',
            ],
        ];
        $tickets = [
            [
                'id' => '1',
                'createdAt' => '2024-04-25T17:38:00+00:00',
                'createdById' => '1',
                'title' => 'It does not work',
                'requesterId' => '1',
                'organizationId' => '1',
            ],
            [
                'id' => '1',
                'createdAt' => '2025-04-25T17:38:00+00:00',
                'createdById' => '1',
                'title' => 'Please help',
                'requesterId' => '1',
                'organizationId' => '1',
            ],
        ];

        $this->processGenerator($this->dataImporter->import(
            organizations: $organizations,
            users: $users,
            tickets: $tickets,
        ));

        $this->assertSame(0, Factory\TicketFactory::count());
    }

    public function testImportTicketsFailsIfFieldsAreDuplicatedInFile(): void
    {
        $this->expectException(DataImporterError::class);
        $this->expectExceptionMessage('Ticket 2 error: duplicates id 1');

        $organizations = [
            [
                'id' => '1',
                'name' => 'Foo',
            ],
        ];
        $users = [
            [
                'id' => '1',
                'email' => 'alix@example.com',
            ],
        ];
        // A ticket is duplicated if its title + organization + createdAt are
        // identical.
        $tickets = [
            [
                'id' => '1',
                'createdAt' => '2024-04-25T17:38:00+00:00',
                'createdById' => '1',
                'title' => 'It does not work',
                'requesterId' => '1',
                'organizationId' => '1',
            ],
            [
                'id' => '2',
                'createdAt' => '2024-04-25T17:38:00+00:00',
                'createdById' => '1',
                'title' => 'It does not work',
                'requesterId' => '1',
                'organizationId' => '1',
            ],
        ];

        $this->processGenerator($this->dataImporter->import(
            organizations: $organizations,
            users: $users,
            tickets: $tickets,
        ));

        $this->assertSame(0, Factory\TicketFactory::count());
    }

    public function testImportTicketsFailsIfTicketIsInvalid(): void
    {
        $this->expectException(DataImporterError::class);
        $this->expectExceptionMessage('Ticket 1 error: Enter a title.');

        $organizations = [
            [
                'id' => '1',
                'name' => 'Foo',
            ],
        ];
        $users = [
            [
                'id' => '1',
                'email' => 'alix@example.com',
            ],
        ];
        $tickets = [
            [
                'id' => '1',
                'createdAt' => '2024-04-25T17:38:00+00:00',
                'createdById' => '1',
                'title' => '',
                'requesterId' => '1',
                'organizationId' => '1',
            ],
        ];

        $this->processGenerator($this->dataImporter->import(
            organizations: $organizations,
            users: $users,
            tickets: $tickets,
        ));

        $this->assertSame(0, Factory\TicketFactory::count());
    }

    public function testImportTicketsFailsIfCreatedByRefersToUnknownId(): void
    {
        $this->expectException(DataImporterError::class);
        $this->expectExceptionMessage('Ticket 1 error: references an unknown createdBy user 2');

        $organizations = [
            [
                'id' => '1',
                'name' => 'Foo',
            ],
        ];
        $users = [
            [
                'id' => '1',
                'email' => 'alix@example.com',
            ],
        ];
        $tickets = [
            [
                'id' => '1',
                'createdAt' => '2024-04-25T17:38:00+00:00',
                'createdById' => '2',
                'title' => 'It does not work',
                'requesterId' => '1',
                'organizationId' => '1',
            ],
        ];

        $this->processGenerator($this->dataImporter->import(
            organizations: $organizations,
            users: $users,
            tickets: $tickets,
        ));

        $this->assertSame(0, Factory\TicketFactory::count());
    }

    public function testImportTicketsFailsIfOrganizationRefersToUnknownId(): void
    {
        $this->expectException(DataImporterError::class);
        $this->expectExceptionMessage('Ticket 1 error: references an unknown organization 2');

        $organizations = [
            [
                'id' => '1',
                'name' => 'Foo',
            ],
        ];
        $users = [
            [
                'id' => '1',
                'email' => 'alix@example.com',
            ],
        ];
        $tickets = [
            [
                'id' => '1',
                'createdAt' => '2024-04-25T17:38:00+00:00',
                'createdById' => '1',
                'title' => 'It does not work',
                'requesterId' => '1',
                'organizationId' => '2',
            ],
        ];

        $this->processGenerator($this->dataImporter->import(
            organizations: $organizations,
            users: $users,
            tickets: $tickets,
        ));

        $this->assertSame(0, Factory\TicketFactory::count());
    }

    public function testImportTicketsFailsIfRequesterRefersToUnknownId(): void
    {
        $this->expectException(DataImporterError::class);
        $this->expectExceptionMessage('Ticket 1 error: references an unknown requester user 2');

        $organizations = [
            [
                'id' => '1',
                'name' => 'Foo',
            ],
        ];
        $users = [
            [
                'id' => '1',
                'email' => 'alix@example.com',
            ],
        ];
        $tickets = [
            [
                'id' => '1',
                'createdAt' => '2024-04-25T17:38:00+00:00',
                'createdById' => '1',
                'title' => 'It does not work',
                'requesterId' => '2',
                'organizationId' => '1',
            ],
        ];

        $this->processGenerator($this->dataImporter->import(
            organizations: $organizations,
            users: $users,
            tickets: $tickets,
        ));

        $this->assertSame(0, Factory\TicketFactory::count());
    }

    public function testImportTicketsFailsIfAssigneeRefersToUnknownId(): void
    {
        $this->expectException(DataImporterError::class);
        $this->expectExceptionMessage('Ticket 1 error: references an unknown assignee user 2');

        $organizations = [
            [
                'id' => '1',
                'name' => 'Foo',
            ],
        ];
        $users = [
            [
                'id' => '1',
                'email' => 'alix@example.com',
            ],
        ];
        $tickets = [
            [
                'id' => '1',
                'createdAt' => '2024-04-25T17:38:00+00:00',
                'createdById' => '1',
                'title' => 'It does not work',
                'requesterId' => '1',
                'assigneeId' => '2',
                'organizationId' => '1',
            ],
        ];

        $this->processGenerator($this->dataImporter->import(
            organizations: $organizations,
            users: $users,
            tickets: $tickets,
        ));

        $this->assertSame(0, Factory\TicketFactory::count());
    }

    public function testImportTicketsFailsIfObserversRefersToUnknownId(): void
    {
        $this->expectException(DataImporterError::class);
        $this->expectExceptionMessage('Ticket 1 error: references an unknown observer 2');

        $organizations = [
            [
                'id' => '1',
                'name' => 'Foo',
            ],
        ];
        $users = [
            [
                'id' => '1',
                'email' => 'alix@example.com',
            ],
        ];
        $tickets = [
            [
                'id' => '1',
                'createdAt' => '2024-04-25T17:38:00+00:00',
                'createdById' => '1',
                'title' => 'It does not work',
                'requesterId' => '1',
                'organizationId' => '1',
                'observerIds' => ['2'],
            ],
        ];

        $this->processGenerator($this->dataImporter->import(
            organizations: $organizations,
            users: $users,
            tickets: $tickets,
        ));

        $this->assertSame(0, Factory\TicketFactory::count());
    }

    public function testImportTicketsFailsIfContractsRefersToUnknownId(): void
    {
        $this->expectException(DataImporterError::class);
        $this->expectExceptionMessage('Ticket 1 error: references an unknown contract 2');

        $organizations = [
            [
                'id' => '1',
                'name' => 'Foo',
            ],
        ];
        $users = [
            [
                'id' => '1',
                'email' => 'alix@example.com',
            ],
        ];
        $tickets = [
            [
                'id' => '1',
                'createdAt' => '2024-04-25T17:38:00+00:00',
                'createdById' => '1',
                'title' => 'It does not work',
                'requesterId' => '1',
                'organizationId' => '1',
                'contractIds' => ['2'],
            ],
        ];

        $this->processGenerator($this->dataImporter->import(
            organizations: $organizations,
            users: $users,
            tickets: $tickets,
        ));

        $this->assertSame(0, Factory\TicketFactory::count());
    }

    public function testImportTicketsFailsIfLabelsRefersToUnknownId(): void
    {
        $this->expectException(DataImporterError::class);
        $this->expectExceptionMessage('Ticket 1 error: references an unknown label 2');

        $organizations = [
            [
                'id' => '1',
                'name' => 'Foo',
            ],
        ];
        $users = [
            [
                'id' => '1',
                'email' => 'alix@example.com',
            ],
        ];
        $tickets = [
            [
                'id' => '1',
                'createdAt' => '2024-04-25T17:38:00+00:00',
                'createdById' => '1',
                'title' => 'It does not work',
                'requesterId' => '1',
                'organizationId' => '1',
                'labelIds' => ['2'],
            ],
        ];

        $this->processGenerator($this->dataImporter->import(
            organizations: $organizations,
            users: $users,
            tickets: $tickets,
        ));

        $this->assertSame(0, Factory\TicketFactory::count());
    }

    public function testImportTicketsFailsIfSolutionRefersToUnknownId(): void
    {
        $this->expectException(DataImporterError::class);
        $this->expectExceptionMessage('Ticket 1 error: references an unknown solution 2.');

        $organizations = [
            [
                'id' => '1',
                'name' => 'Foo',
            ],
        ];
        $users = [
            [
                'id' => '1',
                'email' => 'alix@example.com',
            ],
        ];
        $tickets = [
            [
                'id' => '1',
                'createdAt' => '2024-04-25T17:38:00+00:00',
                'createdById' => '1',
                'title' => 'It does not work',
                'requesterId' => '1',
                'organizationId' => '1',
                'solutionId' => '2',
            ],
        ];

        $this->processGenerator($this->dataImporter->import(
            organizations: $organizations,
            users: $users,
            tickets: $tickets,
        ));

        $this->assertSame(0, Factory\TicketFactory::count());
    }

    public function testImportTicketsFailsIfSpentTimeIsInvalid(): void
    {
        $this->expectException(DataImporterError::class);
        $this->expectExceptionMessage('Ticket 1 error: timeSpents: Enter a number greater than zero.');

        $organizations = [
            [
                'id' => '1',
                'name' => 'Foo',
            ],
        ];
        $users = [
            [
                'id' => '1',
                'email' => 'alix@example.com',
            ],
        ];
        $tickets = [
            [
                'id' => '1',
                'createdAt' => '2024-04-25T17:38:00+00:00',
                'createdById' => '1',
                'title' => 'It does not work',
                'requesterId' => '1',
                'organizationId' => '1',
                'timeSpents' => [
                    [
                        'createdAt' => '2024-04-25T18:00:00+00:00',
                        'createdById' => '1',
                        'time' => 0,
                        'realTime' => 15,
                    ],
                ],
            ],
        ];

        $this->processGenerator($this->dataImporter->import(
            organizations: $organizations,
            users: $users,
            tickets: $tickets,
        ));

        $this->assertSame(0, Factory\TicketFactory::count());
    }

    public function testImportTicketsFailsIfSpentTimeCreatedByRefersToUnknownId(): void
    {
        $this->expectException(DataImporterError::class);
        $this->expectExceptionMessage('Ticket 1 error: timeSpents: references an unknown user 2');

        $organizations = [
            [
                'id' => '1',
                'name' => 'Foo',
            ],
        ];
        $users = [
            [
                'id' => '1',
                'email' => 'alix@example.com',
            ],
        ];
        $tickets = [
            [
                'id' => '1',
                'createdAt' => '2024-04-25T17:38:00+00:00',
                'createdById' => '1',
                'title' => 'It does not work',
                'requesterId' => '1',
                'organizationId' => '1',
                'timeSpents' => [
                    [
                        'createdAt' => '2024-04-25T18:00:00+00:00',
                        'createdById' => '2',
                        'time' => 30,
                        'realTime' => 15,
                    ],
                ],
            ],
        ];

        $this->processGenerator($this->dataImporter->import(
            organizations: $organizations,
            users: $users,
            tickets: $tickets,
        ));

        $this->assertSame(0, Factory\TicketFactory::count());
    }

    public function testImportTicketsFailsIfSpentTimeContractRefersToUnknownId(): void
    {
        $this->expectException(DataImporterError::class);
        $this->expectExceptionMessage('Ticket 1 error: timeSpents: references an unknown contract 2');

        $organizations = [
            [
                'id' => '1',
                'name' => 'Foo',
            ],
        ];
        $users = [
            [
                'id' => '1',
                'email' => 'alix@example.com',
            ],
        ];
        $tickets = [
            [
                'id' => '1',
                'createdAt' => '2024-04-25T17:38:00+00:00',
                'createdById' => '1',
                'title' => 'It does not work',
                'requesterId' => '1',
                'organizationId' => '1',
                'timeSpents' => [
                    [
                        'createdAt' => '2024-04-25T18:00:00+00:00',
                        'createdById' => '1',
                        'time' => 30,
                        'realTime' => 15,
                        'contractId' => '2',
                    ],
                ],
            ],
        ];

        $this->processGenerator($this->dataImporter->import(
            organizations: $organizations,
            users: $users,
            tickets: $tickets,
        ));

        $this->assertSame(0, Factory\TicketFactory::count());
    }

    public function testImportTicketsFailsIfMessageIsInvalid(): void
    {
        $this->expectException(DataImporterError::class);
        $this->expectExceptionMessage('Ticket 1 error: message 1: Enter a message.');

        $organizations = [
            [
                'id' => '1',
                'name' => 'Foo',
            ],
        ];
        $users = [
            [
                'id' => '1',
                'email' => 'alix@example.com',
            ],
        ];
        $tickets = [
            [
                'id' => '1',
                'createdAt' => '2024-04-25T17:38:00+00:00',
                'createdById' => '1',
                'title' => 'It does not work',
                'requesterId' => '1',
                'organizationId' => '1',
                'messages' => [
                    [
                        'id' => '1',
                        'createdAt' => '2024-04-25T17:38:00+00:00',
                        'createdById' => '1',
                        'content' => '',
                    ],
                ],
            ],
        ];

        $this->processGenerator($this->dataImporter->import(
            organizations: $organizations,
            users: $users,
            tickets: $tickets,
        ));

        $this->assertSame(0, Factory\TicketFactory::count());
    }

    public function testImportTicketsFailsIfMessageCreatedByRefersToUnknownId(): void
    {
        $this->expectException(DataImporterError::class);
        $this->expectExceptionMessage('Ticket 1 error: message 1: references an unknown user 2');

        $organizations = [
            [
                'id' => '1',
                'name' => 'Foo',
            ],
        ];
        $users = [
            [
                'id' => '1',
                'email' => 'alix@example.com',
            ],
        ];
        $tickets = [
            [
                'id' => '1',
                'createdAt' => '2024-04-25T17:38:00+00:00',
                'createdById' => '1',
                'title' => 'It does not work',
                'requesterId' => '1',
                'organizationId' => '1',
                'messages' => [
                    [
                        'id' => '1',
                        'createdAt' => '2024-04-25T17:38:00+00:00',
                        'createdById' => '2',
                        'content' => '<p>This is not working!</p>',
                    ],
                ],
            ],
        ];

        $this->processGenerator($this->dataImporter->import(
            organizations: $organizations,
            users: $users,
            tickets: $tickets,
        ));

        $this->assertSame(0, Factory\TicketFactory::count());
    }

    public function testImportTicketsFailsIfDocumentIsMissing(): void
    {
        $this->expectException(DataImporterError::class);
        $this->expectExceptionMessage('Message 1 error: document The bug: references an unknown document bug.txt');

        $organizations = [
            [
                'id' => '1',
                'name' => 'Foo',
            ],
        ];
        $users = [
            [
                'id' => '1',
                'email' => 'alix@example.com',
            ],
        ];
        $tickets = [
            [
                'id' => '1',
                'createdAt' => '2024-04-25T17:38:00+00:00',
                'createdById' => '1',
                'title' => 'It does not work',
                'requesterId' => '1',
                'organizationId' => '1',
                'messages' => [
                    [
                        'id' => '1',
                        'createdAt' => '2024-04-25T17:38:00+00:00',
                        'createdById' => '1',
                        'isConfidential' => false,
                        'via' => 'email',
                        'content' => '<p>This is not working!</p>',
                        'messageDocuments' => [
                            [
                                'name' => 'The bug',
                                'filepath' => 'bug.txt',
                            ]
                        ],
                    ],
                ],
            ],
        ];
        $documentsPath = $this->createDocumentsFolder();
        $filepath = "{$documentsPath}/bug.txt";
        if (file_exists($filepath)) {
            unlink($filepath);
        }

        $this->processGenerator($this->dataImporter->import(
            organizations: $organizations,
            users: $users,
            tickets: $tickets,
            documentsPath: $documentsPath,
        ));

        $this->assertSame(0, Factory\MessageFactory::count());
        $this->assertSame(0, Factory\MessageDocumentFactory::count());
    }

    private function createDocumentsFolder(): string
    {
        $documentsPath = sys_get_temp_dir() . '/documents';

        if (!is_dir($documentsPath)) {
            mkdir($documentsPath);
        }

        return $documentsPath;
    }
}
