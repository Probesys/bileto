<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\ActivityMonitor;

use App\Entity;
use App\Repository;
use App\Tests;
use App\Tests\Factory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class RecordableEntitiesSubscriberTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testSubscriberRecordsInsertions(): void
    {
        /** @var Repository\LabelRepository */
        $labelRepository = Factory\LabelFactory::repository();
        $label = new Entity\Label();

        $labelRepository->save($label, true);

        $this->assertSame(1, Factory\EntityEventFactory::count());
        $entityEvent = Factory\EntityEventFactory::last();
        $this->assertSame('insert', $entityEvent->getType());
        $this->assertSame(Entity\Label::class, $entityEvent->getEntityType());
        $this->assertSame($label->getId(), $entityEvent->getEntityId());
        $this->assertEmpty($entityEvent->getChanges());
    }

    public function testSubscriberRecordsUpdatesToFields(): void
    {
        /** @var Repository\LabelRepository */
        $labelRepository = Factory\LabelFactory::repository();
        $label = Factory\LabelFactory::createOne([
            'name' => 'Foo',
        ]);
        $label->setName('Bar');

        $labelRepository->save($label->_real(), true);

        $this->assertSame(2, Factory\EntityEventFactory::count());
        $entityEvent = Factory\EntityEventFactory::last();
        $this->assertSame('update', $entityEvent->getType());
        $this->assertSame(Entity\Label::class, $entityEvent->getEntityType());
        $this->assertSame($label->getId(), $entityEvent->getEntityId());
        $changes = $entityEvent->getChanges();
        $this->assertTrue(isset($changes['name']));
        $this->assertSame('Foo', $changes['name'][0]);
        $this->assertSame('Bar', $changes['name'][1]);
    }

    public function testSubscriberRecordsUpdatesToCollections(): void
    {
        /** @var Repository\TicketRepository */
        $ticketRepository = Factory\TicketFactory::repository();
        $label1 = Factory\LabelFactory::createOne();
        $label2 = Factory\LabelFactory::createOne();
        $label3 = Factory\LabelFactory::createOne();
        $ticket = Factory\TicketFactory::createOne([
            'labels' => [$label1],
        ]);
        $ticket->removeLabel($label1->_real());
        $ticket->addLabel($label2->_real());
        $ticket->addLabel($label3->_real());

        $countEvents = Factory\EntityEventFactory::count();

        $ticketRepository->save($ticket->_real(), true);

        $this->assertSame($countEvents + 1, Factory\EntityEventFactory::count());
        $entityEvent = Factory\EntityEventFactory::last();
        $this->assertSame('update', $entityEvent->getType());
        $this->assertSame(Entity\Ticket::class, $entityEvent->getEntityType());
        $this->assertSame($ticket->getId(), $entityEvent->getEntityId());
        $changes = $entityEvent->getChanges();
        $this->assertTrue(isset($changes['labels']));
        $removedLabelIds = $changes['labels'][0];
        $this->assertSame(1, count($removedLabelIds));
        $this->assertSame($label1->getId(), $removedLabelIds[0]);
        $addedLabelIds = $changes['labels'][1];
        $this->assertSame(2, count($addedLabelIds));
        $this->assertSame($label2->getId(), $addedLabelIds[0]);
        $this->assertSame($label3->getId(), $addedLabelIds[1]);
    }

    public function testSubscriberRecordsDeletions(): void
    {
        /** @var Repository\LabelRepository */
        $labelRepository = Factory\LabelFactory::repository();
        $label = Factory\LabelFactory::createOne();
        $labelId = $label->getId();

        $labelRepository->remove($label->_real(), true);

        $this->assertSame(2, Factory\EntityEventFactory::count());
        $entityEvent = Factory\EntityEventFactory::last();
        $this->assertSame('delete', $entityEvent->getType());
        $this->assertSame(Entity\Label::class, $entityEvent->getEntityType());
        $this->assertSame($labelId, $entityEvent->getEntityId());
        $this->assertEmpty($entityEvent->getChanges());
    }
}
