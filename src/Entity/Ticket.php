<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Entity;

use App\Repository\TicketRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TicketRepository::class)]
#[UniqueEntity(
    fields: 'uid',
    message: new TranslatableMessage('The uid {{ value }} is already used.', [], 'validators'),
)]
class Ticket
{
    public const TYPES = ['request', 'incident'];
    public const DEFAULT_TYPE = 'request';

    public const STATUSES = ['new', 'in_progress', 'planned', 'pending', 'resolved', 'closed'];
    public const OPEN_STATUSES = ['new', 'in_progress', 'planned', 'pending'];
    public const FINISHED_STATUSES = ['resolved', 'closed'];
    public const DEFAULT_STATUS = 'new';

    public const WEIGHTS = ['low', 'medium', 'high'];
    public const DEFAULT_WEIGHT = 'medium';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, unique: true)]
    private ?string $uid = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    #[ORM\Column(length: 32, options: ['default' => self::DEFAULT_TYPE])]
    #[Assert\Choice(
        choices: self::TYPES,
        message: new TranslatableMessage('The type {{ value }} is not a valid type.', [], 'validators'),
    )]
    private ?string $type = self::DEFAULT_TYPE;

    #[ORM\Column(length: 32, options: ['default' => self::DEFAULT_STATUS])]
    #[Assert\Choice(
        choices: self::STATUSES,
        message: new TranslatableMessage('The status {{ value }} is not a valid status.', [], 'validators'),
    )]
    private ?string $status = self::DEFAULT_STATUS;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(
        message: new TranslatableMessage('The title is required.', [], 'validators'),
    )]
    #[Assert\Length(
        max: 255,
        maxMessage: new TranslatableMessage('The title must be {{ limit }} characters maximum.', [], 'validators'),
    )]
    private ?string $title = null;

    #[ORM\Column(length: 32, options: ['default' => self::DEFAULT_WEIGHT])]
    #[Assert\Choice(
        choices: self::WEIGHTS,
        message: new TranslatableMessage('The urgency {{ value }} is not a valid urgency.', [], 'validators'),
    )]
    private ?string $urgency = self::DEFAULT_WEIGHT;

    #[ORM\Column(length: 32, options: ['default' => self::DEFAULT_WEIGHT])]
    #[Assert\Choice(
        choices: self::WEIGHTS,
        message: new TranslatableMessage('The impact {{ value }} is not a valid impact.', [], 'validators'),
    )]
    private ?string $impact = self::DEFAULT_WEIGHT;

    #[ORM\Column(length: 32, options: ['default' => self::DEFAULT_WEIGHT])]
    #[Assert\Choice(
        choices: self::WEIGHTS,
        message: new TranslatableMessage('The priority {{ value }} is not a valid priority.', [], 'validators'),
    )]
    private ?string $priority = self::DEFAULT_WEIGHT;

    #[ORM\ManyToOne]
    private ?User $requester = null;

    #[ORM\ManyToOne]
    private ?User $assignee = null;

    #[ORM\ManyToOne(inversedBy: 'tickets')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Organization $organization = null;

    /** @var Collection<int, Message> $messages */
    #[ORM\OneToMany(mappedBy: 'ticket', targetEntity: Message::class, orphanRemoval: true)]
    private Collection $messages;

    #[ORM\OneToOne(cascade: ['persist'])]
    private ?Message $solution = null;

    public function __construct()
    {
        $this->messages = new ArrayCollection();
    }

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

    public function getTypeLabel(): ?string
    {
        $typesWithLabels = self::getTypesWithLabels();
        return $typesWithLabels[$this->type];
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function getStatusLabel(): ?string
    {
        $statusesWithLabels = self::getStatusesWithLabels();
        return $statusesWithLabels[$this->status];
    }

    public function getStatusBadgeColor(): ?string
    {
        if ($this->status === 'new') {
            return 'red';
        } elseif (
            $this->status === 'in_progress' ||
            $this->status === 'planned'
        ) {
            return 'orange';
        } elseif ($this->status === 'pending') {
            return 'blue';
        } elseif ($this->status === 'resolved') {
            return 'green';
        } else {
            return 'grey';
        }
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, self::FINISHED_STATUSES);
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = trim($title);

        return $this;
    }

    public function getUrgency(): ?string
    {
        return $this->urgency;
    }

    public function getUrgencyLabel(): ?string
    {
        $weightsWithLabels = [
            'low' => new TranslatableMessage('tickets.urgency.low'),
            'medium' => new TranslatableMessage('tickets.urgency.medium'),
            'high' => new TranslatableMessage('tickets.urgency.high'),
        ];
        return $weightsWithLabels[$this->urgency];
    }

    public function setUrgency(string $urgency): self
    {
        $this->urgency = $urgency;

        return $this;
    }

    public function getImpact(): ?string
    {
        return $this->impact;
    }

    public function getImpactLabel(): ?string
    {
        $weightsWithLabels = [
            'low' => new TranslatableMessage('tickets.impact.low'),
            'medium' => new TranslatableMessage('tickets.impact.medium'),
            'high' => new TranslatableMessage('tickets.impact.high'),
        ];
        return $weightsWithLabels[$this->impact];
    }

    public function setImpact(string $impact): self
    {
        $this->impact = $impact;

        return $this;
    }

    public function getPriority(): ?string
    {
        return $this->priority;
    }

    public function getPriorityLabel(): ?string
    {
        $weightsWithLabels = [
            'low' => new TranslatableMessage('tickets.priority.low'),
            'medium' => new TranslatableMessage('tickets.priority.medium'),
            'high' => new TranslatableMessage('tickets.priority.high'),
        ];
        return $weightsWithLabels[$this->priority];
    }

    public function getPriorityBadgeColor(): ?string
    {
        if ($this->priority === 'low') {
            return 'blue';
        } elseif ($this->priority === 'medium') {
            return 'orange';
        } elseif ($this->priority === 'high') {
            return 'red';
        } else {
            return 'grey';
        }
    }

    public function setPriority(string $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    public function getRequester(): ?User
    {
        return $this->requester;
    }

    public function setRequester(?User $requester): self
    {
        $this->requester = $requester;

        return $this;
    }

    public function getAssignee(): ?User
    {
        return $this->assignee;
    }

    public function setAssignee(?User $assignee): self
    {
        $this->assignee = $assignee;

        return $this;
    }

    public function hasActor(User $user): bool
    {
        $userId = $user->getId();
        return (
            $this->createdBy->getId() === $userId ||
            ($this->requester && $this->requester->getId() === $userId) ||
            ($this->assignee && $this->assignee->getId() === $userId)
        );
    }

    public function getOrganization(): ?Organization
    {
        return $this->organization;
    }

    public function setOrganization(?Organization $organization): self
    {
        $this->organization = $organization;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public static function getStatusesWithLabels(): array
    {
        return [
            'new' => new TranslatableMessage('tickets.status.new'),
            'in_progress' => new TranslatableMessage('tickets.status.in_progress'),
            'planned' => new TranslatableMessage('tickets.status.planned'),
            'pending' => new TranslatableMessage('tickets.status.pending'),
            'resolved' => new TranslatableMessage('tickets.status.resolved'),
            'closed' => new TranslatableMessage('tickets.status.closed'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function getTypesWithLabels(): array
    {
        return [
            'request' => new TranslatableMessage('tickets.request'),
            'incident' => new TranslatableMessage('tickets.incident'),
        ];
    }

    /**
     * @return Collection<int, Message>
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    /**
     * @return Collection<int, Message>
     */
    public function getMessagesWithoutConfidential(): Collection
    {
        $criteria = new Criteria();
        $expr = new Comparison('isConfidential', '=', false);
        $criteria->where($expr);

        /** @var ArrayCollection<int, Message> $messages */
        $messages = $this->messages;
        return $messages->matching($criteria);
    }

    public function getSolution(): ?Message
    {
        return $this->solution;
    }

    public function setSolution(?Message $solution): self
    {
        $this->solution = $solution;

        return $this;
    }
}
