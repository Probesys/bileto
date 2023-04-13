<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Service;

use App\Entity\Ticket;
use App\Entity\User;
use App\Service\TicketsQueryBuilder;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\UserFactory;
use App\Utils\Queries;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class TicketsQueryBuilderTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private TicketsQueryBuilder $ticketsQueryBuilder;

    private User $currentUser;

    /**
     * @before
     */
    public function setUpQueryBuilder(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $this->currentUser = UserFactory::createOne()->object();
        /** @var TicketsQueryBuilder $ticketsQueryBuilder */
        $ticketsQueryBuilder = $container->get(TicketsQueryBuilder::class);
        $this->ticketsQueryBuilder = $ticketsQueryBuilder;
        $this->ticketsQueryBuilder->setCurrentUser($this->currentUser);
    }

    public function testBuildWithText(): void
    {
        $query = Queries\Query::fromString('Foo');

        list($dql, $parameters) = $this->ticketsQueryBuilder->build($query);

        $this->assertSame(<<<SQL
            LOWER(t.title) LIKE :q0p0
            SQL, $dql);
        $this->assertSame('%foo%', $parameters['q0p0']);
    }

    public function testBuildWithTextAndNot(): void
    {
        $query = Queries\Query::fromString('NOT Foo');

        list($dql, $parameters) = $this->ticketsQueryBuilder->build($query);

        $this->assertSame(<<<SQL
            LOWER(t.title) NOT LIKE :q0p0
            SQL, $dql);
        $this->assertSame('%foo%', $parameters['q0p0']);
    }

    public function testBuildWithArrayOfTexts(): void
    {
        $query = Queries\Query::fromString('Foo, BAR');

        list($dql, $parameters) = $this->ticketsQueryBuilder->build($query);

        $this->assertSame(<<<SQL
            (LOWER(t.title) LIKE :q0p0 OR LOWER(t.title) LIKE :q0p1)
            SQL, $dql);
        $this->assertSame('%foo%', $parameters['q0p0']);
        $this->assertSame('%bar%', $parameters['q0p1']);
    }

    public function testBuildWithArrayOfTextsAndNot(): void
    {
        $query = Queries\Query::fromString('NOT Foo, BAR');

        list($dql, $parameters) = $this->ticketsQueryBuilder->build($query);

        $this->assertSame(<<<SQL
            NOT (LOWER(t.title) LIKE :q0p0 OR LOWER(t.title) LIKE :q0p1)
            SQL, $dql);
        $this->assertSame('%foo%', $parameters['q0p0']);
        $this->assertSame('%bar%', $parameters['q0p1']);
    }

    public function testBuildWithId(): void
    {
        $query = Queries\Query::fromString('#42');

        list($dql, $parameters) = $this->ticketsQueryBuilder->build($query);

        $this->assertSame(<<<SQL
            t.id = :q0p0
            SQL, $dql);
        $this->assertSame('42', $parameters['q0p0']);
    }

    public function testBuildWithIdAndNot(): void
    {
        $query = Queries\Query::fromString('NOT #42');

        list($dql, $parameters) = $this->ticketsQueryBuilder->build($query);

        $this->assertSame(<<<SQL
            t.id != :q0p0
            SQL, $dql);
        $this->assertSame('42', $parameters['q0p0']);
    }

    public function testBuildWithSubQuery(): void
    {
        $query = Queries\Query::fromString('foo (bar OR baz)');

        list($dql, $parameters) = $this->ticketsQueryBuilder->build($query);

        $this->assertSame(<<<SQL
            LOWER(t.title) LIKE :q0p0 AND (LOWER(t.title) LIKE :q0p1 OR LOWER(t.title) LIKE :q0p2)
            SQL, $dql);
        $this->assertSame('%foo%', $parameters['q0p0']);
        $this->assertSame('%bar%', $parameters['q0p1']);
        $this->assertSame('%baz%', $parameters['q0p2']);
    }

    public function testBuildWithSubQueryAndNot(): void
    {
        $query = Queries\Query::fromString('foo NOT (bar OR baz)');

        list($dql, $parameters) = $this->ticketsQueryBuilder->build($query);

        $this->assertSame(<<<SQL
            LOWER(t.title) LIKE :q0p0 AND NOT (LOWER(t.title) LIKE :q0p1 OR LOWER(t.title) LIKE :q0p2)
            SQL, $dql);
        $this->assertSame('%foo%', $parameters['q0p0']);
        $this->assertSame('%bar%', $parameters['q0p1']);
        $this->assertSame('%baz%', $parameters['q0p2']);
    }

    public function testBuildWithQualifierStatus(): void
    {
        $query = Queries\Query::fromString('status:new');

        list($dql, $parameters) = $this->ticketsQueryBuilder->build($query);

        $this->assertSame(<<<SQL
            t.status = :q0p0
            SQL, $dql);
        $this->assertSame('new', $parameters['q0p0']);
    }

    public function testBuildWithQualifierStatusAsArray(): void
    {
        $query = Queries\Query::fromString('status:new,in_progress');

        list($dql, $parameters) = $this->ticketsQueryBuilder->build($query);

        $this->assertSame(<<<SQL
            t.status IN (:q0p0)
            SQL, $dql);
        $this->assertSame(['new', 'in_progress'], $parameters['q0p0']);
    }

    public function testBuildWithQualifierStatusAsArrayAndNot(): void
    {
        $query = Queries\Query::fromString('-status:new,in_progress');

        list($dql, $parameters) = $this->ticketsQueryBuilder->build($query);

        $this->assertSame(<<<SQL
            t.status NOT IN (:q0p0)
            SQL, $dql);
        $this->assertSame(['new', 'in_progress'], $parameters['q0p0']);
    }

    public function testBuildWithQualifierStatusOpen(): void
    {
        $query = Queries\Query::fromString('status:open');

        list($dql, $parameters) = $this->ticketsQueryBuilder->build($query);

        $this->assertSame(<<<SQL
            t.status IN (:q0p0)
            SQL, $dql);
        $this->assertSame(Ticket::OPEN_STATUSES, $parameters['q0p0']);
    }

    public function testBuildWithQualifierStatusFinished(): void
    {
        $query = Queries\Query::fromString('status:finished');

        list($dql, $parameters) = $this->ticketsQueryBuilder->build($query);

        $this->assertSame(<<<SQL
            t.status IN (:q0p0)
            SQL, $dql);
        $this->assertSame(Ticket::FINISHED_STATUSES, $parameters['q0p0']);
    }

    public function testBuildWithQualifierAssignee(): void
    {
        $alix = UserFactory::createOne([
            'name' => 'Alix Hambourg',
        ])->object();
        $query = Queries\Query::fromString('assignee:alix');

        list($dql, $parameters) = $this->ticketsQueryBuilder->build($query);

        $this->assertSame(<<<SQL
            t.assignee = :q0p0
            SQL, $dql);
        $this->assertSame($alix->getId(), $parameters['q0p0']);
    }

    public function testBuildWithQualifierAssigneeAsMe(): void
    {
        $query = Queries\Query::fromString('assignee:@me');

        list($dql, $parameters) = $this->ticketsQueryBuilder->build($query);

        $this->assertSame(<<<SQL
            t.assignee = :q0p0
            SQL, $dql);
        $this->assertSame($this->currentUser->getId(), $parameters['q0p0']);
    }

    public function testBuildWithQualifierAssigneeAsArray(): void
    {
        $alix = UserFactory::createOne([
            'name' => 'Alix Hambourg',
        ])->object();
        $query = Queries\Query::fromString('assignee:alix,@me');

        list($dql, $parameters) = $this->ticketsQueryBuilder->build($query);

        $this->assertSame(<<<SQL
            t.assignee IN (:q0p0)
            SQL, $dql);
        $this->assertSame(
            [$alix->getId(), $this->currentUser->getId()],
            $parameters['q0p0']
        );
    }

    public function testBuildWithQualifierAssigneeAndUnknownEmail(): void
    {
        $query = Queries\Query::fromString('assignee:alix');

        list($dql, $parameters) = $this->ticketsQueryBuilder->build($query);

        $this->assertSame(<<<SQL
            t.assignee = :q0p0
            SQL, $dql);
        $this->assertSame(-1, $parameters['q0p0']);
    }

    public function testBuildWithQualifierRequester(): void
    {
        $alix = UserFactory::createOne([
            'name' => 'Alix Hambourg',
        ])->object();
        $query = Queries\Query::fromString('requester:alix');

        list($dql, $parameters) = $this->ticketsQueryBuilder->build($query);

        $this->assertSame(<<<SQL
            t.requester = :q0p0
            SQL, $dql);
        $this->assertSame($alix->getId(), $parameters['q0p0']);
    }

    public function testBuildWithQualifierInvolves(): void
    {
        $alix = UserFactory::createOne([
            'name' => 'Alix Hambourg',
        ])->object();
        $query = Queries\Query::fromString('involves:alix');

        list($dql, $parameters) = $this->ticketsQueryBuilder->build($query);

        $this->assertSame(<<<SQL
            (t.assignee = :q0p0 OR t.requester = :q0p1)
            SQL, $dql);
        $this->assertSame($alix->getId(), $parameters['q0p0']);
        $this->assertSame($alix->getId(), $parameters['q0p1']);
    }

    public function testBuildWithQualifierInvolvesAndNot(): void
    {
        $alix = UserFactory::createOne([
            'name' => 'Alix Hambourg',
        ])->object();
        $query = Queries\Query::fromString('-involves:alix');

        list($dql, $parameters) = $this->ticketsQueryBuilder->build($query);

        $this->assertSame(<<<SQL
            NOT (t.assignee = :q0p0 OR t.requester = :q0p1)
            SQL, $dql);
        $this->assertSame($alix->getId(), $parameters['q0p0']);
        $this->assertSame($alix->getId(), $parameters['q0p1']);
    }

    public function testBuildWithQualifierOrg(): void
    {
        $probesys = OrganizationFactory::createOne([
            'name' => 'Probesys',
        ])->object();
        $query = Queries\Query::fromString('org:probesys');

        list($dql, $parameters) = $this->ticketsQueryBuilder->build($query);

        $this->assertSame(<<<SQL
            t.organization = :q0p0
            SQL, $dql);
        $this->assertSame($probesys->getId(), $parameters['q0p0']);
    }

    public function testBuildWithQualifierOrgAsArray(): void
    {
        $probesys = OrganizationFactory::createOne([
            'name' => 'Probesys',
        ])->object();
        $friendlyCoorp = OrganizationFactory::createOne([
            'name' => 'Friendly Coorp',
        ])->object();
        $query = Queries\Query::fromString('org:Probesys,coorp');

        list($dql, $parameters) = $this->ticketsQueryBuilder->build($query);

        $this->assertSame(<<<SQL
            t.organization IN (:q0p0)
            SQL, $dql);
        $this->assertSame(
            [$probesys->getId(), $friendlyCoorp->getId()],
            $parameters['q0p0']
        );
    }

    public function testBuildWithQualifierOrgAndUnknownName(): void
    {
        $query = Queries\Query::fromString('org:Probesys');

        list($dql, $parameters) = $this->ticketsQueryBuilder->build($query);

        $this->assertSame(<<<SQL
            t.organization = :q0p0
            SQL, $dql);
        $this->assertSame(-1, $parameters['q0p0']);
    }

    public function testBuildWithQualifierUid(): void
    {
        $query = Queries\Query::fromString('uid:abcde');

        list($dql, $parameters) = $this->ticketsQueryBuilder->build($query);

        $this->assertSame(<<<SQL
            t.uid = :q0p0
            SQL, $dql);
        $this->assertSame('abcde', $parameters['q0p0']);
    }

    public function testBuildWithQualifierType(): void
    {
        $query = Queries\Query::fromString('type:incident');

        list($dql, $parameters) = $this->ticketsQueryBuilder->build($query);

        $this->assertSame(<<<SQL
            t.type = :q0p0
            SQL, $dql);
        $this->assertSame('incident', $parameters['q0p0']);
    }

    public function testBuildWithQualifierUrgency(): void
    {
        $query = Queries\Query::fromString('urgency:low');

        list($dql, $parameters) = $this->ticketsQueryBuilder->build($query);

        $this->assertSame(<<<SQL
            t.urgency = :q0p0
            SQL, $dql);
        $this->assertSame('low', $parameters['q0p0']);
    }

    public function testBuildWithQualifierImpact(): void
    {
        $query = Queries\Query::fromString('impact:medium');

        list($dql, $parameters) = $this->ticketsQueryBuilder->build($query);

        $this->assertSame(<<<SQL
            t.impact = :q0p0
            SQL, $dql);
        $this->assertSame('medium', $parameters['q0p0']);
    }

    public function testBuildWithQualifierPriority(): void
    {
        $query = Queries\Query::fromString('priority:high');

        list($dql, $parameters) = $this->ticketsQueryBuilder->build($query);

        $this->assertSame(<<<SQL
            t.priority = :q0p0
            SQL, $dql);
        $this->assertSame('high', $parameters['q0p0']);
    }

    public function testBuildWithQualifierNoAssignee(): void
    {
        $query = Queries\Query::fromString('no:assignee');

        list($dql, $parameters) = $this->ticketsQueryBuilder->build($query);

        $this->assertSame(<<<SQL
            t.assignee IS NULL
            SQL, $dql);
        $this->assertTrue(empty($parameters));
    }

    public function testBuildWithQualifierNoSolution(): void
    {
        $query = Queries\Query::fromString('no:solution');

        list($dql, $parameters) = $this->ticketsQueryBuilder->build($query);

        $this->assertSame(<<<SQL
            t.solution IS NULL
            SQL, $dql);
        $this->assertTrue(empty($parameters));
    }

    public function testBuildWithQualifierHasAssignee(): void
    {
        $query = Queries\Query::fromString('has:assignee');

        list($dql, $parameters) = $this->ticketsQueryBuilder->build($query);

        $this->assertSame(<<<SQL
            t.assignee IS NOT NULL
            SQL, $dql);
        $this->assertTrue(empty($parameters));
    }

    public function testBuildWithQualifierHasSolution(): void
    {
        $query = Queries\Query::fromString('has:solution');

        list($dql, $parameters) = $this->ticketsQueryBuilder->build($query);

        $this->assertSame(<<<SQL
            t.solution IS NOT NULL
            SQL, $dql);
        $this->assertTrue(empty($parameters));
    }

    public function testBuildFailsWithQualifierUnknown(): void
    {
        $query = Queries\Query::fromString('foo:bar,baz');

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Unexpected "foo" qualifier with value "bar,baz"');

        $this->ticketsQueryBuilder->build($query);
    }
}