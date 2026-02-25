<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Entity;

use App\ActivityMonitor\MonitorableEntityInterface;
use App\ActivityMonitor\MonitorableEntityTrait;
use App\Repository\TaskRepository;
use App\Uid\UidEntityInterface;
use App\Uid\UidEntityTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TaskRepository::class)]
class Task implements EntityInterface, MonitorableEntityInterface, UidEntityInterface
{
    use MonitorableEntityTrait;
    use UidEntityTrait;

    public const LABEL_MAX_LENGTH = 255;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, unique: true)]
    private ?string $uid = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $updatedBy = null;

    #[ORM\ManyToOne(inversedBy: 'tasks')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Ticket $ticket = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Message $message = null;

    #[ORM\Column(length: self::LABEL_MAX_LENGTH)]
    #[Assert\NotBlank(
        message: new TranslatableMessage('task.label.required', [], 'errors'),
    )]
    #[Assert\Length(
        max: self::LABEL_MAX_LENGTH,
        maxMessage: new TranslatableMessage('task.label.max_chars', [], 'errors'),
    )]
    private ?string $label = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    #[Assert\NotBlank(
        message: new TranslatableMessage('task.start_at.required', [], 'errors'),
    )]
    private ?\DateTimeImmutable $startAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    #[Assert\NotBlank(
        message: new TranslatableMessage('task.end_at.required', [], 'errors'),
    )]
    #[Assert\GreaterThan(
        propertyPath: 'startAt',
        message: new TranslatableMessage('task.end_at.greater_than_start_at', [], 'errors'),
    )]
    private ?\DateTimeImmutable $endAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    public function getTicket(): ?Ticket
    {
        return $this->ticket;
    }

    public function setTicket(?Ticket $ticket): static
    {
        $this->ticket = $ticket;

        return $this;
    }

    public function getMessage(): ?Message
    {
        return $this->message;
    }

    public function setMessage(?Message $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getStartAt(): ?\DateTimeImmutable
    {
        return $this->startAt;
    }

    public function setStartAt(\DateTimeImmutable $startAt): static
    {
        $this->startAt = $startAt;

        return $this;
    }

    public function getEndAt(): ?\DateTimeImmutable
    {
        return $this->endAt;
    }

    public function setEndAt(\DateTimeImmutable $endAt): static
    {
        $this->endAt = $endAt;

        return $this;
    }

    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function setFinishedAt(?\DateTimeImmutable $finishedAt): static
    {
        $this->finishedAt = $finishedAt;

        return $this;
    }

    public function isFinished(): bool
    {
        return $this->finishedAt !== null;
    }

    public function getTimelineType(): string
    {
        return 'task';
    }
}
