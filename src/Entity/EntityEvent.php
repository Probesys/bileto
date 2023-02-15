<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Entity;

use App\EntityListener\EntitySetMetaListener;
use App\Repository\EntityEventRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EntityEventRepository::class)]
#[ORM\EntityListeners([EntitySetMetaListener::class])]
class EntityEvent implements MetaEntityInterface
{
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

    public function getUid(): ?string
    {
        return $this->uid;
    }

    public function setUid(string $uid): self
    {
        $this->uid = $uid;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): self
    {
        $this->createdBy = $createdBy;

        return $this;
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

    public static function initInsert(ActivityRecordableInterface $entity): self
    {
        $entityEvent = new self();
        $entityEvent->type = 'insert';
        $entityEvent->entityType = $entity::class;
        $entityEvent->entityId = $entity->getId();
        $entityEvent->changes = [];
        return $entityEvent;
    }

    /**
     * @param array<string, mixed[]> $changes
     */
    public static function initUpdate(ActivityRecordableInterface $entity, array $changes): self
    {
        $entityEvent = new self();
        $entityEvent->type = 'update';
        $entityEvent->entityType = $entity::class;
        $entityEvent->entityId = $entity->getId();
        $entityEvent->changes = $changes;
        return $entityEvent;
    }

    public static function initDelete(ActivityRecordableInterface $entity): self
    {
        $entityEvent = new self();
        $entityEvent->type = 'delete';
        $entityEvent->entityType = $entity::class;
        $entityEvent->entityId = $entity->getId();
        $entityEvent->changes = [];
        return $entityEvent;
    }
}
