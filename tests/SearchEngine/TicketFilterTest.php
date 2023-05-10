<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\SearchEngine;

use App\SearchEngine\Query;
use App\SearchEngine\TicketFilter;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TicketFilterTest extends WebTestCase
{
    public function testFromQueryWithTextConditionReturnsFilter(): void
    {
        $query = Query::fromString('foo bar, baz OR spam NOT egg');

        $ticketFilter = TicketFilter::fromQuery($query);

        $this->assertNotNull($ticketFilter);
        $this->assertSame('foo bar, baz OR spam NOT egg', $ticketFilter->getText());
    }

    public function testFromQueryWithStatusConditionReturnsFilter(): void
    {
        $query = Query::fromString('status:new,in_progress');

        $ticketFilter = TicketFilter::fromQuery($query);

        $this->assertNotNull($ticketFilter);
        $this->assertSame(['new', 'in_progress'], $ticketFilter->getFilter('status'));
    }

    public function testFromQueryWithTypeConditionReturnsFilter(): void
    {
        $query = Query::fromString('type:incident');

        $ticketFilter = TicketFilter::fromQuery($query);

        $this->assertNotNull($ticketFilter);
        $this->assertSame(['incident'], $ticketFilter->getFilter('type'));
    }

    public function testFromQueryWithAssigneeConditionReturnsFilter(): void
    {
        $query = Query::fromString('assignee:#1');

        $ticketFilter = TicketFilter::fromQuery($query);

        $this->assertNotNull($ticketFilter);
        $this->assertSame([1], $ticketFilter->getFilter('assignee'));
    }

    public function testFromQueryWithAssigneeMeConditionReturnsFilter(): void
    {
        $query = Query::fromString('assignee:@me');

        $ticketFilter = TicketFilter::fromQuery($query);

        $this->assertNotNull($ticketFilter);
        $this->assertSame(['@me'], $ticketFilter->getFilter('assignee'));
    }

    public function testFromQueryWithNoAssigneeConditionReturnsFilter(): void
    {
        $query = Query::fromString('no:assignee');

        $ticketFilter = TicketFilter::fromQuery($query);

        $this->assertNotNull($ticketFilter);
        $this->assertSame([null], $ticketFilter->getFilter('assignee'));
    }

    public function testFromQueryWithRequesterConditionReturnsFilter(): void
    {
        $query = Query::fromString('requester:#1');

        $ticketFilter = TicketFilter::fromQuery($query);

        $this->assertNotNull($ticketFilter);
        $this->assertSame([1], $ticketFilter->getFilter('requester'));
    }

    public function testFromQueryWithInvolvesConditionReturnsFilter(): void
    {
        $query = Query::fromString('involves:#1');

        $ticketFilter = TicketFilter::fromQuery($query);

        $this->assertNotNull($ticketFilter);
        $this->assertSame([1], $ticketFilter->getFilter('involves'));
    }

    public function testFromQueryWithUrgencyConditionReturnsFilter(): void
    {
        $query = Query::fromString('urgency:low');

        $ticketFilter = TicketFilter::fromQuery($query);

        $this->assertNotNull($ticketFilter);
        $this->assertSame(['low'], $ticketFilter->getFilter('urgency'));
    }

    public function testFromQueryWithImpactConditionReturnsFilter(): void
    {
        $query = Query::fromString('impact:low,medium');

        $ticketFilter = TicketFilter::fromQuery($query);

        $this->assertNotNull($ticketFilter);
        $this->assertSame(['low', 'medium'], $ticketFilter->getFilter('impact'));
    }

    public function testFromQueryWithPriorityConditionReturnsFilter(): void
    {
        $query = Query::fromString('priority:high');

        $ticketFilter = TicketFilter::fromQuery($query);

        $this->assertNotNull($ticketFilter);
        $this->assertSame(['high'], $ticketFilter->getFilter('priority'));
    }

    public function testFromQueryWithNotSupportedQualifierConditionReturnsNull(): void
    {
        $query = Query::fromString('org:#1');

        $ticketFilter = TicketFilter::fromQuery($query);

        $this->assertNull($ticketFilter);
    }

    public function testFromQueryWithSameQualifierConditionReturnsNull(): void
    {
        $query = Query::fromString('status:new status:in_progress');

        $ticketFilter = TicketFilter::fromQuery($query);

        $this->assertNull($ticketFilter);
    }

    public function testFromQueryWithWrongStatusConditionReturnsNull(): void
    {
        $query = Query::fromString('status:notastatus');

        $ticketFilter = TicketFilter::fromQuery($query);

        $this->assertNull($ticketFilter);
    }

    public function testFromQueryWithWrongTypeConditionReturnsNull(): void
    {
        $query = Query::fromString('type:notatype');

        $ticketFilter = TicketFilter::fromQuery($query);

        $this->assertNull($ticketFilter);
    }

    public function testFromQueryWithWrongPriorityConditionReturnsNull(): void
    {
        $query = Query::fromString('priority:notapriority');

        $ticketFilter = TicketFilter::fromQuery($query);

        $this->assertNull($ticketFilter);
    }

    public function testFromQueryWithWrongUrgencyConditionReturnsNull(): void
    {
        $query = Query::fromString('urgency:notanurgency');

        $ticketFilter = TicketFilter::fromQuery($query);

        $this->assertNull($ticketFilter);
    }

    public function testFromQueryWithWrongImpactConditionReturnsNull(): void
    {
        $query = Query::fromString('impact:notanimpact');

        $ticketFilter = TicketFilter::fromQuery($query);

        $this->assertNull($ticketFilter);
    }

    public function testFromQueryWithWrongActorConditionReturnsNull(): void
    {
        $query = Query::fromString('assignee:notid');

        $ticketFilter = TicketFilter::fromQuery($query);

        $this->assertNull($ticketFilter);
    }

    public function testFromQueryWithQueryConditionReturnsNull(): void
    {
        $query = Query::fromString('foo (bar baz)');

        $ticketFilter = TicketFilter::fromQuery($query);

        $this->assertNull($ticketFilter);
    }

    public function testFromQueryWithOrQualifierReturnsNull(): void
    {
        $query = Query::fromString('foo OR status:new');

        $ticketFilter = TicketFilter::fromQuery($query);

        $this->assertNull($ticketFilter);
    }

    public function testFromQueryWithNotQualifierReturnsNull(): void
    {
        $query = Query::fromString('foo -status:new');

        $ticketFilter = TicketFilter::fromQuery($query);

        $this->assertNull($ticketFilter);
    }

    public function testToTextualQueryWithText(): void
    {
        $query = Query::fromString('foo bar,baz');
        $ticketFilter = TicketFilter::fromQuery($query);

        $textualQuery = $ticketFilter->toTextualQuery();

        $this->assertSame('foo bar\\, baz', $textualQuery);
    }

    public function testToTextualQueryWithStatus(): void
    {
        $query = Query::fromString('status:new,in_progress');
        $ticketFilter = TicketFilter::fromQuery($query);

        $textualQuery = $ticketFilter->toTextualQuery();

        $this->assertSame('status:new,in_progress', $textualQuery);
    }

    public function testToTextualQueryWithType(): void
    {
        $query = Query::fromString('type:incident');
        $ticketFilter = TicketFilter::fromQuery($query);

        $textualQuery = $ticketFilter->toTextualQuery();

        $this->assertSame('type:incident', $textualQuery);
    }

    public function testToTextualQueryWithAssignee(): void
    {
        $query = Query::fromString('assignee:#1');
        $ticketFilter = TicketFilter::fromQuery($query);

        $textualQuery = $ticketFilter->toTextualQuery();

        $this->assertSame('assignee:#1', $textualQuery);
    }

    public function testToTextualQueryWithAssigneeMe(): void
    {
        $query = Query::fromString('assignee:@me');
        $ticketFilter = TicketFilter::fromQuery($query);

        $textualQuery = $ticketFilter->toTextualQuery();

        $this->assertSame('assignee:@me', $textualQuery);
    }

    public function testToTextualQueryWithNoAssignee(): void
    {
        $query = Query::fromString('no:assignee');
        $ticketFilter = TicketFilter::fromQuery($query);

        $textualQuery = $ticketFilter->toTextualQuery();

        $this->assertSame('no:assignee', $textualQuery);
    }

    public function testToTextualQueryWithRequesterMe(): void
    {
        $query = Query::fromString('requester:@me');
        $ticketFilter = TicketFilter::fromQuery($query);

        $textualQuery = $ticketFilter->toTextualQuery();

        $this->assertSame('requester:@me', $textualQuery);
    }

    public function testToTextualQueryWithRequester(): void
    {
        $query = Query::fromString('requester:#1');
        $ticketFilter = TicketFilter::fromQuery($query);

        $textualQuery = $ticketFilter->toTextualQuery();

        $this->assertSame('requester:#1', $textualQuery);
    }

    public function testToTextualQueryWithInvolves(): void
    {
        $query = Query::fromString('involves:#1');
        $ticketFilter = TicketFilter::fromQuery($query);

        $textualQuery = $ticketFilter->toTextualQuery();

        $this->assertSame('involves:#1', $textualQuery);
    }

    public function testToTextualQueryWithInvolvesMe(): void
    {
        $query = Query::fromString('involves:@me');
        $ticketFilter = TicketFilter::fromQuery($query);

        $textualQuery = $ticketFilter->toTextualQuery();

        $this->assertSame('involves:@me', $textualQuery);
    }

    public function testToTextualQueryWithUrgency(): void
    {
        $query = Query::fromString('urgency:low');
        $ticketFilter = TicketFilter::fromQuery($query);

        $textualQuery = $ticketFilter->toTextualQuery();

        $this->assertSame('urgency:low', $textualQuery);
    }

    public function testToTextualQueryWithImpact(): void
    {
        $query = Query::fromString('impact:low,medium');
        $ticketFilter = TicketFilter::fromQuery($query);

        $textualQuery = $ticketFilter->toTextualQuery();

        $this->assertSame('impact:low,medium', $textualQuery);
    }

    public function testToTextualQueryWithPriority(): void
    {
        $query = Query::fromString('priority:high');
        $ticketFilter = TicketFilter::fromQuery($query);

        $textualQuery = $ticketFilter->toTextualQuery();

        $this->assertSame('priority:high', $textualQuery);
    }

    public function testSetText(): void
    {
        $query = Query::fromString('foo');
        $ticketFilter = TicketFilter::fromQuery($query);

        $ticketFilter->setText('bar');

        $this->assertSame('bar', $ticketFilter->getText());
    }

    public function testSetTextContainingSpecialChars(): void
    {
        $query = Query::fromString('foo');
        $ticketFilter = TicketFilter::fromQuery($query);

        $ticketFilter->setText('status:open foo,bar (spam) \\');

        $this->assertSame('status\\:open foo\\,bar \\(spam\\) \\\\', $ticketFilter->getText(escaped: true));
        $this->assertSame([], $ticketFilter->getFilter('status'));
    }
}
