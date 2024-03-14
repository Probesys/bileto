<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\SearchEngine\QueryBuilder;

use App\Entity\Ticket;
use App\Entity\User;
use App\SearchEngine;
use App\Tests\AuthorizationHelper;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class TicketQueryBuilderTest extends WebTestCase
{
    use AuthorizationHelper;
    use Factories;
    use ResetDatabase;

    private SearchEngine\QueryBuilder\TicketQueryBuilder $ticketQueryBuilder;

    private User $currentUser;

    /**
     * @before
     */
    public function setUpQueryBuilder(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $this->currentUser = UserFactory::createOne()->object();
        $client->loginUser($this->currentUser);
        /** @var SearchEngine\QueryBuilder\TicketQueryBuilder $ticketQueryBuilder */
        $ticketQueryBuilder = $container->get(SearchEngine\QueryBuilder\TicketQueryBuilder::class);
        $this->ticketQueryBuilder = $ticketQueryBuilder;
    }

    public function testCreate(): void
    {
        $query = SearchEngine\Query::fromString('Foo');

        $queryBuilder = $this->ticketQueryBuilder->create([$query]);

        $dql = $queryBuilder->getDQL();
        $this->assertStringNotContainsString('LEFT JOIN t.contracts', $dql);
    }

    public function testCreateWithContract(): void
    {
        $query = SearchEngine\Query::fromString('(contract:#1)');

        $queryBuilder = $this->ticketQueryBuilder->create([$query]);

        $dql = $queryBuilder->getDQL();
        $this->assertStringContainsString('LEFT JOIN t.contracts', $dql);
    }

    public function testBuildQueryWithText(): void
    {
        $query = SearchEngine\Query::fromString('Foo');

        list($dql, $parameters) = $this->ticketQueryBuilder->buildQuery($query);

        $this->assertSame(<<<SQL
            LOWER(t.title) LIKE :q0p0
            SQL, $dql);
        $this->assertSame('%foo%', $parameters['q0p0']);
    }

    public function testBuildQueryWithTextAndNot(): void
    {
        $query = SearchEngine\Query::fromString('NOT Foo');

        list($dql, $parameters) = $this->ticketQueryBuilder->buildQuery($query);

        $this->assertSame(<<<SQL
            LOWER(t.title) NOT LIKE :q0p0
            SQL, $dql);
        $this->assertSame('%foo%', $parameters['q0p0']);
    }

    public function testBuildQueryWithArrayOfTexts(): void
    {
        $query = SearchEngine\Query::fromString('Foo, BAR');

        list($dql, $parameters) = $this->ticketQueryBuilder->buildQuery($query);

        $this->assertSame(<<<SQL
            (LOWER(t.title) LIKE :q0p0 OR LOWER(t.title) LIKE :q0p1)
            SQL, $dql);
        $this->assertSame('%foo%', $parameters['q0p0']);
        $this->assertSame('%bar%', $parameters['q0p1']);
    }

    public function testBuildQueryWithArrayOfTextsAndNot(): void
    {
        $query = SearchEngine\Query::fromString('NOT Foo, BAR');

        list($dql, $parameters) = $this->ticketQueryBuilder->buildQuery($query);

        $this->assertSame(<<<SQL
            NOT (LOWER(t.title) LIKE :q0p0 OR LOWER(t.title) LIKE :q0p1)
            SQL, $dql);
        $this->assertSame('%foo%', $parameters['q0p0']);
        $this->assertSame('%bar%', $parameters['q0p1']);
    }

    public function testBuildQueryWithTextAsId(): void
    {
        $query = SearchEngine\Query::fromString('#42');

        list($dql, $parameters) = $this->ticketQueryBuilder->buildQuery($query);

        $this->assertSame(<<<SQL
            t.id = :q0p0
            SQL, $dql);
        $this->assertSame(42, $parameters['q0p0']);
    }

    public function testBuildQueryWithTextAsIdAndNot(): void
    {
        $query = SearchEngine\Query::fromString('NOT #42');

        list($dql, $parameters) = $this->ticketQueryBuilder->buildQuery($query);

        $this->assertSame(<<<SQL
            t.id != :q0p0
            SQL, $dql);
        $this->assertSame(42, $parameters['q0p0']);
    }

    public function testBuildQueryWithTextAsArrayAndId(): void
    {
        $query = SearchEngine\Query::fromString('foo, #42');

        list($dql, $parameters) = $this->ticketQueryBuilder->buildQuery($query);

        $this->assertSame(<<<SQL
            (LOWER(t.title) LIKE :q0p0 OR t.id = :q0p1)
            SQL, $dql);
        $this->assertSame('%foo%', $parameters['q0p0']);
        $this->assertSame(42, $parameters['q0p1']);
    }

    public function testBuildQueryWithSubQuery(): void
    {
        $query = SearchEngine\Query::fromString('foo (bar OR baz)');

        list($dql, $parameters) = $this->ticketQueryBuilder->buildQuery($query);

        $this->assertSame(<<<SQL
            LOWER(t.title) LIKE :q0p0 AND (LOWER(t.title) LIKE :q0p1 OR LOWER(t.title) LIKE :q0p2)
            SQL, $dql);
        $this->assertSame('%foo%', $parameters['q0p0']);
        $this->assertSame('%bar%', $parameters['q0p1']);
        $this->assertSame('%baz%', $parameters['q0p2']);
    }

    public function testBuildQueryWithSubQueryAndNot(): void
    {
        $query = SearchEngine\Query::fromString('foo NOT (bar OR baz)');

        list($dql, $parameters) = $this->ticketQueryBuilder->buildQuery($query);

        $this->assertSame(<<<SQL
            LOWER(t.title) LIKE :q0p0 AND NOT (LOWER(t.title) LIKE :q0p1 OR LOWER(t.title) LIKE :q0p2)
            SQL, $dql);
        $this->assertSame('%foo%', $parameters['q0p0']);
        $this->assertSame('%bar%', $parameters['q0p1']);
        $this->assertSame('%baz%', $parameters['q0p2']);
    }

    public function testBuildQueryWithQualifierStatus(): void
    {
        $query = SearchEngine\Query::fromString('status:new');

        list($dql, $parameters) = $this->ticketQueryBuilder->buildQuery($query);

        $this->assertSame(<<<SQL
            t.status = :q0p0
            SQL, $dql);
        $this->assertSame('new', $parameters['q0p0']);
    }

    public function testBuildQueryWithQualifierStatusAsArray(): void
    {
        $query = SearchEngine\Query::fromString('status:new,in_progress');

        list($dql, $parameters) = $this->ticketQueryBuilder->buildQuery($query);

        $this->assertSame(<<<SQL
            t.status IN (:q0p0)
            SQL, $dql);
        $this->assertSame(['new', 'in_progress'], $parameters['q0p0']);
    }

    public function testBuildQueryWithQualifierStatusAsArrayAndNot(): void
    {
        $query = SearchEngine\Query::fromString('-status:new,in_progress');

        list($dql, $parameters) = $this->ticketQueryBuilder->buildQuery($query);

        $this->assertSame(<<<SQL
            t.status NOT IN (:q0p0)
            SQL, $dql);
        $this->assertSame(['new', 'in_progress'], $parameters['q0p0']);
    }

    public function testBuildQueryWithQualifierStatusOpen(): void
    {
        $query = SearchEngine\Query::fromString('status:open');

        list($dql, $parameters) = $this->ticketQueryBuilder->buildQuery($query);

        $this->assertSame(<<<SQL
            t.status IN (:q0p0)
            SQL, $dql);
        $this->assertSame(Ticket::OPEN_STATUSES, $parameters['q0p0']);
    }

    public function testBuildQueryWithQualifierStatusFinished(): void
    {
        $query = SearchEngine\Query::fromString('status:finished');

        list($dql, $parameters) = $this->ticketQueryBuilder->buildQuery($query);

        $this->assertSame(<<<SQL
            t.status IN (:q0p0)
            SQL, $dql);
        $this->assertSame(Ticket::FINISHED_STATUSES, $parameters['q0p0']);
    }

    public function testBuildQueryWithQualifierAssignee(): void
    {
        $alix = UserFactory::createOne([
            'name' => 'Alix Hambourg',
        ])->object();
        $query = SearchEngine\Query::fromString('assignee:alix');

        list($dql, $parameters) = $this->ticketQueryBuilder->buildQuery($query);

        $this->assertSame(<<<SQL
            t.assignee = :q0p0
            SQL, $dql);
        $this->assertSame($alix->getId(), $parameters['q0p0']);
    }

    public function testBuildQueryWithQualifierAssigneeAsId(): void
    {
        $alix = UserFactory::createOne([
            'name' => 'Alix Hambourg',
        ])->object();
        $query = SearchEngine\Query::fromString("assignee:#{$alix->getId()}");

        list($dql, $parameters) = $this->ticketQueryBuilder->buildQuery($query);

        $this->assertSame(<<<SQL
            t.assignee = :q0p0
            SQL, $dql);
        $this->assertSame($alix->getId(), $parameters['q0p0']);
    }

    public function testBuildQueryWithQualifierAssigneeAsMe(): void
    {
        $query = SearchEngine\Query::fromString('assignee:@me');

        list($dql, $parameters) = $this->ticketQueryBuilder->buildQuery($query);

        $this->assertSame(<<<SQL
            t.assignee = :q0p0
            SQL, $dql);
        $this->assertSame($this->currentUser->getId(), $parameters['q0p0']);
    }

    public function testBuildQueryWithQualifierAssigneeAsArray(): void
    {
        $alix = UserFactory::createOne([
            'name' => 'Alix Hambourg',
        ])->object();
        $query = SearchEngine\Query::fromString('assignee:alix,@me');

        list($dql, $parameters) = $this->ticketQueryBuilder->buildQuery($query);

        $this->assertSame(<<<SQL
            t.assignee IN (:q0p0)
            SQL, $dql);
        $this->assertSame(
            [$alix->getId(), $this->currentUser->getId()],
            $parameters['q0p0']
        );
    }

    public function testBuildQueryWithQualifierAssigneeAndUnknownEmail(): void
    {
        $query = SearchEngine\Query::fromString('assignee:alix');

        list($dql, $parameters) = $this->ticketQueryBuilder->buildQuery($query);

        $this->assertSame(<<<SQL
            t.assignee = :q0p0
            SQL, $dql);
        $this->assertSame(-1, $parameters['q0p0']);
    }

    public function testBuildQueryWithQualifierRequester(): void
    {
        $alix = UserFactory::createOne([
            'name' => 'Alix Hambourg',
        ])->object();
        $query = SearchEngine\Query::fromString('requester:alix');

        list($dql, $parameters) = $this->ticketQueryBuilder->buildQuery($query);

        $this->assertSame(<<<SQL
            t.requester = :q0p0
            SQL, $dql);
        $this->assertSame($alix->getId(), $parameters['q0p0']);
    }

    public function testBuildQueryWithQualifierInvolves(): void
    {
        $alix = UserFactory::createOne([
            'name' => 'Alix Hambourg',
        ])->object();
        $query = SearchEngine\Query::fromString('involves:alix');

        list($dql, $parameters) = $this->ticketQueryBuilder->buildQuery($query);

        $this->assertSame(<<<SQL
            (t.assignee = :q0p0 OR t.requester = :q0p1)
            SQL, $dql);
        $this->assertSame($alix->getId(), $parameters['q0p0']);
        $this->assertSame($alix->getId(), $parameters['q0p1']);
    }

    public function testBuildQueryWithQualifierInvolvesAndNot(): void
    {
        $alix = UserFactory::createOne([
            'name' => 'Alix Hambourg',
        ])->object();
        $query = SearchEngine\Query::fromString('-involves:alix');

        list($dql, $parameters) = $this->ticketQueryBuilder->buildQuery($query);

        $this->assertSame(<<<SQL
            NOT (t.assignee = :q0p0 OR t.requester = :q0p1)
            SQL, $dql);
        $this->assertSame($alix->getId(), $parameters['q0p0']);
        $this->assertSame($alix->getId(), $parameters['q0p1']);
    }

    public function testBuildQueryWithQualifierOrg(): void
    {
        $probesys = OrganizationFactory::createOne([
            'name' => 'Probesys',
        ])->object();
        $query = SearchEngine\Query::fromString('org:probesys');

        list($dql, $parameters) = $this->ticketQueryBuilder->buildQuery($query);

        $this->assertSame(<<<SQL
            t.organization = :q0p0
            SQL, $dql);
        $this->assertSame($probesys->getId(), $parameters['q0p0']);
    }

    public function testBuildQueryWithQualifierOrgAsId(): void
    {
        $probesys = OrganizationFactory::createOne([
            'name' => 'Probesys',
        ])->object();
        $query = SearchEngine\Query::fromString("org:#{$probesys->getId()}");

        list($dql, $parameters) = $this->ticketQueryBuilder->buildQuery($query);

        $this->assertSame(<<<SQL
            t.organization = :q0p0
            SQL, $dql);
        $this->assertSame($probesys->getId(), $parameters['q0p0']);
    }

    public function testBuildQueryWithQualifierOrgAsArray(): void
    {
        $probesys = OrganizationFactory::createOne([
            'name' => 'Probesys',
        ])->object();
        $friendlyCoorp = OrganizationFactory::createOne([
            'name' => 'Friendly Coorp',
        ])->object();
        $query = SearchEngine\Query::fromString('org:Probesys,coorp');

        list($dql, $parameters) = $this->ticketQueryBuilder->buildQuery($query);

        $this->assertSame(<<<SQL
            t.organization IN (:q0p0)
            SQL, $dql);
        $this->assertSame(
            [$probesys->getId(), $friendlyCoorp->getId()],
            $parameters['q0p0']
        );
    }

    public function testBuildQueryWithQualifierOrgAsIdAndArray(): void
    {
        $probesys = OrganizationFactory::createOne([
            'name' => 'Probesys',
        ])->object();
        $friendlyCoorp = OrganizationFactory::createOne([
            'name' => 'Friendly Coorp',
        ])->object();
        $query = SearchEngine\Query::fromString("org:#{$probesys->getId()},coorp");

        list($dql, $parameters) = $this->ticketQueryBuilder->buildQuery($query);

        $this->assertSame(<<<SQL
            t.organization IN (:q0p0)
            SQL, $dql);
        $this->assertSame(
            [$probesys->getId(), $friendlyCoorp->getId()],
            $parameters['q0p0']
        );
    }

    public function testBuildQueryWithQualifierOrgAndUnknownName(): void
    {
        $query = SearchEngine\Query::fromString('org:Probesys');

        list($dql, $parameters) = $this->ticketQueryBuilder->buildQuery($query);

        $this->assertSame(<<<SQL
            t.organization = :q0p0
            SQL, $dql);
        $this->assertSame(-1, $parameters['q0p0']);
    }

    public function testBuildQueryWithQualifierContract(): void
    {
        $query = SearchEngine\Query::fromString('contract:#1');

        list($dql, $parameters) = $this->ticketQueryBuilder->buildQuery($query);

        $this->assertSame(<<<SQL
            c.id = :q0p0
            SQL, $dql);
        $this->assertSame(1, $parameters['q0p0']);
    }

    public function testBuildQueryWithQualifierUid(): void
    {
        $query = SearchEngine\Query::fromString('uid:abcde');

        list($dql, $parameters) = $this->ticketQueryBuilder->buildQuery($query);

        $this->assertSame(<<<SQL
            t.uid = :q0p0
            SQL, $dql);
        $this->assertSame('abcde', $parameters['q0p0']);
    }

    public function testBuildQueryWithQualifierType(): void
    {
        $query = SearchEngine\Query::fromString('type:incident');

        list($dql, $parameters) = $this->ticketQueryBuilder->buildQuery($query);

        $this->assertSame(<<<SQL
            t.type = :q0p0
            SQL, $dql);
        $this->assertSame('incident', $parameters['q0p0']);
    }

    public function testBuildQueryWithQualifierUrgency(): void
    {
        $query = SearchEngine\Query::fromString('urgency:low');

        list($dql, $parameters) = $this->ticketQueryBuilder->buildQuery($query);

        $this->assertSame(<<<SQL
            t.urgency = :q0p0
            SQL, $dql);
        $this->assertSame('low', $parameters['q0p0']);
    }

    public function testBuildQueryWithQualifierImpact(): void
    {
        $query = SearchEngine\Query::fromString('impact:medium');

        list($dql, $parameters) = $this->ticketQueryBuilder->buildQuery($query);

        $this->assertSame(<<<SQL
            t.impact = :q0p0
            SQL, $dql);
        $this->assertSame('medium', $parameters['q0p0']);
    }

    public function testBuildQueryWithQualifierPriority(): void
    {
        $query = SearchEngine\Query::fromString('priority:high');

        list($dql, $parameters) = $this->ticketQueryBuilder->buildQuery($query);

        $this->assertSame(<<<SQL
            t.priority = :q0p0
            SQL, $dql);
        $this->assertSame('high', $parameters['q0p0']);
    }

    public function testBuildQueryWithQualifierNoAssignee(): void
    {
        $query = SearchEngine\Query::fromString('no:assignee');

        list($dql, $parameters) = $this->ticketQueryBuilder->buildQuery($query);

        $this->assertSame(<<<SQL
            t.assignee IS NULL
            SQL, $dql);
        $this->assertTrue(empty($parameters));
    }

    public function testBuildQueryWithQualifierNoSolution(): void
    {
        $query = SearchEngine\Query::fromString('no:solution');

        list($dql, $parameters) = $this->ticketQueryBuilder->buildQuery($query);

        $this->assertSame(<<<SQL
            t.solution IS NULL
            SQL, $dql);
        $this->assertTrue(empty($parameters));
    }

    public function testBuildQueryWithQualifierHasAssignee(): void
    {
        $query = SearchEngine\Query::fromString('has:assignee');

        list($dql, $parameters) = $this->ticketQueryBuilder->buildQuery($query);

        $this->assertSame(<<<SQL
            t.assignee IS NOT NULL
            SQL, $dql);
        $this->assertTrue(empty($parameters));
    }

    public function testBuildQueryWithQualifierHasSolution(): void
    {
        $query = SearchEngine\Query::fromString('has:solution');

        list($dql, $parameters) = $this->ticketQueryBuilder->buildQuery($query);

        $this->assertSame(<<<SQL
            t.solution IS NOT NULL
            SQL, $dql);
        $this->assertTrue(empty($parameters));
    }

    public function testBuildQueryFailsWithQualifierUnknown(): void
    {
        $query = SearchEngine\Query::fromString('foo:bar,baz');

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Unexpected "foo" qualifier with value "bar,baz"');

        $this->ticketQueryBuilder->buildQuery($query);
    }
}
