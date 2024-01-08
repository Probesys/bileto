<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Entity;

use App\ActivityMonitor\RecordableEntityInterface;
use App\ActivityMonitor\TrackableEntityInterface;
use App\ActivityMonitor\TrackableEntityTrait;
use App\Repository\EntityEventRepository;
use App\Uid\UidEntityInterface;
use App\Uid\UidEntityTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EntityEventRepository::class)]
#[ORM\Index(columns: ['entity_type', 'entity_id'])]
class EntityEvent implements TrackableEntityInterface, UidEntityInterface
{
    use TrackableEntityTrait;
    use UidEntityTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private ?string $uid = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne]
    private ?User $updatedBy = null;

    #[ORM\Column(length: 10)]
    private ?string $type = null;

    #[ORM\Column(length: 255)]
    private ?string $entityType = null;

    #[ORM\Column]
    private ?int $entityId = null;

    /** @var array<string, mixed[]> */
    #[ORM\Column]
    private array $changes = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getEntityType(): ?string
    {
        return $this->entityType;
    }

    public function setEntityType(string $entityType): self
    {
        $this->entityType = $entityType;

        return $this;
    }

    public function getEntityId(): ?int
    {
        return $this->entityId;
    }

    public function setEntityId(int $entityId): self
    {
        $this->entityId = $entityId;

        return $this;
    }

    /**
     * @return array<string, mixed[]>
     */
    public function getChanges(): array
    {
        return $this->changes;
    }

    /**
     * @param array<string, mixed[]> $changes
     */
    public function setChanges(array $changes): self
    {
        $this->changes = $changes;

        return $this;
    }

    /**
     * Return true if the EntityEvent references the specified field.
     */
    public function refersTo(string $field): bool
    {
        return isset($this->changes[$field]);
    }

    public static function initInsert(RecordableEntityInterface $entity): self
    {
        $entityEvent = new self();
        $entityEvent->type = 'insert';
        $entityEvent->entityType = $entity->getEntityType();
        $entityEvent->entityId = $entity->getId();
        $entityEvent->changes = [];
        return $entityEvent;
    }

    /**
     * @param array<string, mixed[]> $changes
     */
    public static function initUpdate(RecordableEntityInterface $entity, array $changes): self
    {
        $entityEvent = new self();
        $entityEvent->type = 'update';
        $entityEvent->entityType = $entity->getEntityType();
        $entityEvent->entityId = $entity->getId();
        $entityEvent->changes = $changes;
        return $entityEvent;
    }

    public static function initDelete(RecordableEntityInterface $entity): self
    {
        $entityEvent = new self();
        $entityEvent->type = 'delete';
        $entityEvent->entityType = $entity->getEntityType();
        $entityEvent->entityId = $entity->getId();
        $entityEvent->changes = [];
        return $entityEvent;
    }

    public function getTimelineType(): string
    {
        return 'event';
    }
}
