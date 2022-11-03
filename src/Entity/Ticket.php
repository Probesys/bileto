<?php

// This file is part of Bileto.
// Copyright 2022 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Entity;

use App\Repository\TicketRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TicketRepository::class)]
class Ticket
{
    public const TYPES = ['request', 'incident'];
    public const DEFAULT_TYPE = 'request';

    public const STATUSES = ['new', 'assigned', 'in_progress', 'pending', 'resolved', 'closed'];
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
        $weightsWithLabels = self::getWeightsWithLabels();
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
        $weightsWithLabels = self::getWeightsWithLabels();
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
        $weightsWithLabels = self::getWeightsWithLabels();
        return $weightsWithLabels[$this->priority];
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
            'new' => new TranslatableMessage('New'),
            'assigned' => new TranslatableMessage('Assigned'),
            'in_progress' => new TranslatableMessage('In progress'),
            'pending' => new TranslatableMessage('Pending'),
            'resolved' => new TranslatableMessage('Resolved'),
            'closed' => new TranslatableMessage('Closed'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function getTypesWithLabels(): array
    {
        return [
            'request' => new TranslatableMessage('Request'),
            'incident' => new TranslatableMessage('Incident'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function getWeightsWithLabels(): array
    {
        return [
            'low' => new TranslatableMessage('Low'),
            'medium' => new TranslatableMessage('Medium'),
            'high' => new TranslatableMessage('High'),
        ];
    }

    /**
     * @return Collection<int, Message>
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }
}
