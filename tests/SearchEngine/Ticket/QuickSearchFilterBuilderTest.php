<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\SearchEngine\Ticket;

use App\SearchEngine;
use App\Tests\Factory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use PHPUnit\Framework\Attributes\Before;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class QuickSearchFilterBuilderTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private SearchEngine\Ticket\QuickSearchFilterBuilder $ticketQuickSearchFilterBuilder;

    #[Before]
    public function setUpQuickSearchFilterBuilder(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var SearchEngine\Ticket\QuickSearchFilterBuilder */
        $ticketQuickSearchFilterBuilder = $container->get(SearchEngine\Ticket\QuickSearchFilterBuilder::class);
        $this->ticketQuickSearchFilterBuilder = $ticketQuickSearchFilterBuilder;
    }

    public function testGetFilterWithTextConditionReturnsFilter(): void
    {
        $query = SearchEngine\Query::fromString('foo bar, baz OR spam NOT egg');

        $ticketQuickSearchFilter = $this->ticketQuickSearchFilterBuilder->getFilter($query);

        $this->assertNotNull($ticketQuickSearchFilter);
        $this->assertSame('foo bar, baz OR spam NOT egg', $ticketQuickSearchFilter->getText());
    }

    public function testGetFilterWithStatusConditionReturnsFilter(): void
    {
        $query = SearchEngine\Query::fromString('status:new,in_progress');

        $ticketQuickSearchFilter = $this->ticketQuickSearchFilterBuilder->getFilter($query);

        $this->assertNotNull($ticketQuickSearchFilter);
        $this->assertSame(['new', 'in_progress'], $ticketQuickSearchFilter->getStatuses());
    }

    public function testGetFilterWithOpenStatusConditionSanitizesTheIncludedStatuses(): void
    {
        $query = SearchEngine\Query::fromString('status:open,new,in_progress');

        $ticketQuickSearchFilter = $this->ticketQuickSearchFilterBuilder->getFilter($query);

        $this->assertNotNull($ticketQuickSearchFilter);
        $this->assertSame(['open'], $ticketQuickSearchFilter->getGroupStatuses());
        $this->assertSame([], $ticketQuickSearchFilter->getStatuses());
    }

    public function testGetFilterWithFinishedStatusConditionSanitizesTheIncludedStatuses(): void
    {
        $query = SearchEngine\Query::fromString('status:finished,closed,resolved');

        $ticketQuickSearchFilter = $this->ticketQuickSearchFilterBuilder->getFilter($query);

        $this->assertNotNull($ticketQuickSearchFilter);
        $this->assertSame(['finished'], $ticketQuickSearchFilter->getGroupStatuses());
        $this->assertSame([], $ticketQuickSearchFilter->getStatuses());
    }

    public function testGetFilterWithTypeConditionReturnsFilter(): void
    {
        $query = SearchEngine\Query::fromString('type:incident');

        $ticketQuickSearchFilter = $this->ticketQuickSearchFilterBuilder->getFilter($query);

        $this->assertNotNull($ticketQuickSearchFilter);
        $this->assertSame('incident', $ticketQuickSearchFilter->getType());
    }

    public function testGetFilterWithAssigneeConditionReturnsFilter(): void
    {
        $user = Factory\UserFactory::createOne();
        $query = SearchEngine\Query::fromString("assignee:#{$user->getId()}");

        $ticketQuickSearchFilter = $this->ticketQuickSearchFilterBuilder->getFilter($query);

        $this->assertNotNull($ticketQuickSearchFilter);
        $assignees = $ticketQuickSearchFilter->getAssignees();
        $this->assertSame(1, count($assignees));
        $this->assertSame($user->getId(), $assignees[0]->getId());
    }

    public function testGetFilterWithNoAssigneeConditionReturnsFilter(): void
    {
        $query = SearchEngine\Query::fromString('no:assignee');

        $ticketQuickSearchFilter = $this->ticketQuickSearchFilterBuilder->getFilter($query);

        $this->assertNotNull($ticketQuickSearchFilter);
        $this->assertTrue($ticketQuickSearchFilter->getUnassignedOnly());
    }

    public function testGetFilterWithRequesterConditionReturnsFilter(): void
    {
        $user = Factory\UserFactory::createOne();
        $query = SearchEngine\Query::fromString("requester:#{$user->getId()}");

        $ticketQuickSearchFilter = $this->ticketQuickSearchFilterBuilder->getFilter($query);

        $this->assertNotNull($ticketQuickSearchFilter);
        $requesters = $ticketQuickSearchFilter->getRequesters();
        $this->assertSame(1, count($requesters));
        $this->assertSame($user->getId(), $requesters[0]->getId());
    }

    public function testGetFilterWithInvolvesConditionReturnsFilter(): void
    {
        $user = Factory\UserFactory::createOne();
        $query = SearchEngine\Query::fromString("involves:#{$user->getId()}");

        $ticketQuickSearchFilter = $this->ticketQuickSearchFilterBuilder->getFilter($query);

        $this->assertNotNull($ticketQuickSearchFilter);
        $involves = $ticketQuickSearchFilter->getInvolves();
        $this->assertSame(1, count($involves));
        $this->assertSame($user->getId(), $involves[0]->getId());
    }

    public function testGetFilterWithUrgencyConditionReturnsFilter(): void
    {
        $query = SearchEngine\Query::fromString('urgency:low');

        $ticketQuickSearchFilter = $this->ticketQuickSearchFilterBuilder->getFilter($query);

        $this->assertNotNull($ticketQuickSearchFilter);
        $this->assertSame(['low'], $ticketQuickSearchFilter->getUrgencies());
    }

    public function testGetFilterWithImpactConditionReturnsFilter(): void
    {
        $query = SearchEngine\Query::fromString('impact:low,medium');

        $ticketQuickSearchFilter = $this->ticketQuickSearchFilterBuilder->getFilter($query);

        $this->assertNotNull($ticketQuickSearchFilter);
        $this->assertSame(['low', 'medium'], $ticketQuickSearchFilter->getImpacts());
    }

    public function testGetFilterWithPriorityConditionReturnsFilter(): void
    {
        $query = SearchEngine\Query::fromString('priority:high');

        $ticketQuickSearchFilter = $this->ticketQuickSearchFilterBuilder->getFilter($query);

        $this->assertNotNull($ticketQuickSearchFilter);
        $this->assertSame(['high'], $ticketQuickSearchFilter->getPriorities());
    }

    public function testGetFilterWithLabelConditionsReturnsFilter(): void
    {
        $fooLabel = Factory\LabelFactory::createOne([
            'name' => 'foo',
        ]);
        $barLabel = Factory\LabelFactory::createOne([
            'name' => 'bar',
        ]);
        $bazLabel = Factory\LabelFactory::createOne([
            'name' => 'BAZ',
        ]);
        $query = SearchEngine\Query::fromString('label:foo label:baz');

        $ticketQuickSearchFilter = $this->ticketQuickSearchFilterBuilder->getFilter($query);

        $this->assertNotNull($ticketQuickSearchFilter);
        $labels = $ticketQuickSearchFilter->getLabels();
        $this->assertSame(2, count($labels));
        $this->assertSame($fooLabel->getId(), $labels[0]->getId());
        $this->assertSame($bazLabel->getId(), $labels[1]->getId());
    }

    public function testGetFilterWithNotSupportedQualifierConditionReturnsNull(): void
    {
        $query = SearchEngine\Query::fromString('org:#1');

        $ticketQuickSearchFilter = $this->ticketQuickSearchFilterBuilder->getFilter($query);

        $this->assertNull($ticketQuickSearchFilter);
    }

    public function testGetFilterWithSameQualifierConditionReturnsNull(): void
    {
        $query = SearchEngine\Query::fromString('status:new status:in_progress');

        $ticketQuickSearchFilter = $this->ticketQuickSearchFilterBuilder->getFilter($query);

        $this->assertNull($ticketQuickSearchFilter);
    }

    public function testGetFilterWithWrongIdDoesNotIncludeActor(): void
    {
        $query = SearchEngine\Query::fromString('assignee:notid');

        $ticketQuickSearchFilter = $this->ticketQuickSearchFilterBuilder->getFilter($query);

        $this->assertNotNull($ticketQuickSearchFilter);
        $this->assertEmpty($ticketQuickSearchFilter->getAssignees());
    }

    public function testGetFilterWithQueryConditionReturnsNull(): void
    {
        $query = SearchEngine\Query::fromString('foo (bar baz)');

        $ticketQuickSearchFilter = $this->ticketQuickSearchFilterBuilder->getFilter($query);

        $this->assertNull($ticketQuickSearchFilter);
    }

    public function testGetFilterWithOrQualifierReturnsNull(): void
    {
        $query = SearchEngine\Query::fromString('foo OR status:new');

        $ticketQuickSearchFilter = $this->ticketQuickSearchFilterBuilder->getFilter($query);

        $this->assertNull($ticketQuickSearchFilter);
    }

    public function testGetFilterWithNotQualifierReturnsNull(): void
    {
        $query = SearchEngine\Query::fromString('foo -status:new');

        $ticketQuickSearchFilter = $this->ticketQuickSearchFilterBuilder->getFilter($query);

        $this->assertNull($ticketQuickSearchFilter);
    }

    public function testToTextualQueryWithText(): void
    {
        $query = SearchEngine\Query::fromString('foo bar,baz');
        $ticketQuickSearchFilter = $this->ticketQuickSearchFilterBuilder->getFilter($query);

        $textualQuery = $ticketQuickSearchFilter->toTextualQuery();

        $this->assertSame('foo bar\\, baz', $textualQuery);
    }

    public function testToTextualQueryWithStatus(): void
    {
        $query = SearchEngine\Query::fromString('status:new,in_progress');
        $ticketQuickSearchFilter = $this->ticketQuickSearchFilterBuilder->getFilter($query);

        $textualQuery = $ticketQuickSearchFilter->toTextualQuery();

        $this->assertSame('status:new,in_progress', $textualQuery);
    }

    public function testToTextualQueryWithType(): void
    {
        $query = SearchEngine\Query::fromString('type:incident');
        $ticketQuickSearchFilter = $this->ticketQuickSearchFilterBuilder->getFilter($query);

        $textualQuery = $ticketQuickSearchFilter->toTextualQuery();

        $this->assertSame('type:incident', $textualQuery);
    }

    public function testToTextualQueryWithAssignee(): void
    {
        $user = Factory\UserFactory::createOne();
        $query = SearchEngine\Query::fromString("assignee:#{$user->getId()}");
        $ticketQuickSearchFilter = $this->ticketQuickSearchFilterBuilder->getFilter($query);

        $textualQuery = $ticketQuickSearchFilter->toTextualQuery();

        $this->assertSame("assignee:#{$user->getId()}", $textualQuery);
    }

    public function testToTextualQueryWithNoAssignee(): void
    {
        $query = SearchEngine\Query::fromString('no:assignee');
        $ticketQuickSearchFilter = $this->ticketQuickSearchFilterBuilder->getFilter($query);

        $textualQuery = $ticketQuickSearchFilter->toTextualQuery();

        $this->assertSame('no:assignee', $textualQuery);
    }

    public function testToTextualQueryWithRequester(): void
    {
        $user = Factory\UserFactory::createOne();
        $query = SearchEngine\Query::fromString("requester:#{$user->getId()}");
        $ticketQuickSearchFilter = $this->ticketQuickSearchFilterBuilder->getFilter($query);

        $textualQuery = $ticketQuickSearchFilter->toTextualQuery();

        $this->assertSame("requester:#{$user->getId()}", $textualQuery);
    }

    public function testToTextualQueryWithInvolves(): void
    {
        $user = Factory\UserFactory::createOne();
        $query = SearchEngine\Query::fromString("involves:#{$user->getId()}");
        $ticketQuickSearchFilter = $this->ticketQuickSearchFilterBuilder->getFilter($query);

        $textualQuery = $ticketQuickSearchFilter->toTextualQuery();

        $this->assertSame("involves:#{$user->getId()}", $textualQuery);
    }

    public function testToTextualQueryWithUrgency(): void
    {
        $query = SearchEngine\Query::fromString('urgency:low');
        $ticketQuickSearchFilter = $this->ticketQuickSearchFilterBuilder->getFilter($query);

        $textualQuery = $ticketQuickSearchFilter->toTextualQuery();

        $this->assertSame('urgency:low', $textualQuery);
    }

    public function testToTextualQueryWithImpact(): void
    {
        $query = SearchEngine\Query::fromString('impact:low,medium');
        $ticketQuickSearchFilter = $this->ticketQuickSearchFilterBuilder->getFilter($query);

        $textualQuery = $ticketQuickSearchFilter->toTextualQuery();

        $this->assertSame('impact:low,medium', $textualQuery);
    }

    public function testToTextualQueryWithPriority(): void
    {
        $query = SearchEngine\Query::fromString('priority:high');
        $ticketQuickSearchFilter = $this->ticketQuickSearchFilterBuilder->getFilter($query);

        $textualQuery = $ticketQuickSearchFilter->toTextualQuery();

        $this->assertSame('priority:high', $textualQuery);
    }

    public function testToTextualQueryWithLabel(): void
    {
        Factory\LabelFactory::createOne([
            'name' => 'foo',
        ]);
        Factory\LabelFactory::createOne([
            'name' => 'Bar Baz',
        ]);
        $query = SearchEngine\Query::fromString('label:foo label:"bar baz"');
        $ticketQuickSearchFilter = $this->ticketQuickSearchFilterBuilder->getFilter($query);

        $textualQuery = $ticketQuickSearchFilter->toTextualQuery();

        $this->assertSame('label:foo label:"Bar Baz"', $textualQuery);
    }

    public function testSetText(): void
    {
        $query = SearchEngine\Query::fromString('foo');
        $ticketQuickSearchFilter = $this->ticketQuickSearchFilterBuilder->getFilter($query);

        $ticketQuickSearchFilter->setText('bar');

        $this->assertSame('bar', $ticketQuickSearchFilter->getText());
    }

    public function testSetTextContainingSpecialChars(): void
    {
        $query = SearchEngine\Query::fromString('foo');
        $ticketQuickSearchFilter = $this->ticketQuickSearchFilterBuilder->getFilter($query);

        $ticketQuickSearchFilter->setText('status:open foo,bar (spam) \\');

        $this->assertSame('status\\:open foo\\,bar \\(spam\\) \\\\', $ticketQuickSearchFilter->getText(escaped: true));
        $this->assertSame([], $ticketQuickSearchFilter->getStatuses());
    }
}
