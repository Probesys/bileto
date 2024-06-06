<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Entity;

use App\ActivityMonitor\MonitorableEntityInterface;
use App\ActivityMonitor\MonitorableEntityTrait;
use App\Repository\TicketRepository;
use App\Uid\UidEntityInterface;
use App\Uid\UidEntityTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\Common\Collections\Order;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TicketRepository::class)]
class Ticket implements MonitorableEntityInterface, UidEntityInterface
{
    use MonitorableEntityTrait;
    use UidEntityTrait;

    public const TYPES = ['request', 'incident'];
    public const DEFAULT_TYPE = 'incident';

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

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne]
    private ?User $updatedBy = null;

    #[ORM\Column(length: 32, options: ['default' => self::DEFAULT_TYPE])]
    #[Assert\Choice(
        choices: self::TYPES,
        message: new TranslatableMessage('ticket.type.invalid', [], 'errors'),
    )]
    private ?string $type = self::DEFAULT_TYPE;

    #[ORM\Column(length: 32, options: ['default' => self::DEFAULT_STATUS])]
    #[Assert\Choice(
        choices: self::STATUSES,
        message: new TranslatableMessage('ticket.status.invalid', [], 'errors'),
    )]
    private ?string $status = self::DEFAULT_STATUS;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(
        message: new TranslatableMessage('ticket.title.required', [], 'errors'),
    )]
    #[Assert\Length(
        max: 255,
        maxMessage: new TranslatableMessage('ticket.title.max_chars', [], 'errors'),
    )]
    private ?string $title = null;

    #[ORM\Column(length: 32, options: ['default' => self::DEFAULT_WEIGHT])]
    #[Assert\Choice(
        choices: self::WEIGHTS,
        message: new TranslatableMessage('ticket.urgency.invalid', [], 'errors'),
    )]
    private ?string $urgency = self::DEFAULT_WEIGHT;

    #[ORM\Column(length: 32, options: ['default' => self::DEFAULT_WEIGHT])]
    #[Assert\Choice(
        choices: self::WEIGHTS,
        message: new TranslatableMessage('ticket.impact.invalid', [], 'errors'),
    )]
    private ?string $impact = self::DEFAULT_WEIGHT;

    #[ORM\Column(length: 32, options: ['default' => self::DEFAULT_WEIGHT])]
    #[Assert\Choice(
        choices: self::WEIGHTS,
        message: new TranslatableMessage('ticket.priority.invalid', [], 'errors'),
    )]
    private ?string $priority = self::DEFAULT_WEIGHT;

    #[ORM\ManyToOne]
    #[Assert\NotBlank(
        message: new TranslatableMessage('ticket.requester.invalid', [], 'errors'),
    )]
    private ?User $requester = null;

    #[ORM\ManyToOne]
    private ?User $assignee = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Team $team = null;

    #[ORM\ManyToOne(inversedBy: 'tickets')]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private ?Organization $organization = null;

    /** @var Collection<int, Message> $messages */
    #[ORM\OneToMany(
        mappedBy: 'ticket',
        targetEntity: Message::class,
        orphanRemoval: true,
        cascade: ['persist'],
    )]
    private Collection $messages;

    #[ORM\OneToOne(cascade: ['persist'])]
    private ?Message $solution = null;

    /** @var Collection<int, Contract> $contracts */
    #[ORM\ManyToMany(targetEntity: Contract::class, inversedBy: 'tickets')]
    private Collection $contracts;

    /** @var Collection<int, TimeSpent> $timeSpents */
    #[ORM\OneToMany(
        mappedBy: 'ticket',
        targetEntity: TimeSpent::class,
        cascade: ['persist'],
    )]
    private Collection $timeSpents;

    public function __construct()
    {
        $this->messages = new ArrayCollection();
        $this->contracts = new ArrayCollection();
        $this->timeSpents = new ArrayCollection();
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

    public function getTeam(): ?Team
    {
        return $this->team;
    }

    public function setTeam(?Team $team): static
    {
        $this->team = $team;

        return $this;
    }

    public function hasActor(User $user): bool
    {
        $userId = $user->getId();
        return (
            $this->createdBy->getId() === $userId ||
            ($this->requester && $this->requester->getId() === $userId) ||
            ($this->assignee && $this->assignee->getId() === $userId) ||
            ($this->team && $this->team->hasAgent($user))
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
     * @param 'open'|'finished'|null $group
     *
     * @return array<string, string>
     */
    public static function getStatusesWithLabels(?string $group = null): array
    {
        $statusesWithLabels = [
            'new' => new TranslatableMessage('tickets.status.new'),
            'in_progress' => new TranslatableMessage('tickets.status.in_progress'),
            'planned' => new TranslatableMessage('tickets.status.planned'),
            'pending' => new TranslatableMessage('tickets.status.pending'),
            'resolved' => new TranslatableMessage('tickets.status.resolved'),
            'closed' => new TranslatableMessage('tickets.status.closed'),
        ];

        if ($group === 'open') {
            $statusesWithLabels = array_intersect_key(
                $statusesWithLabels,
                array_flip(self::OPEN_STATUSES),
            );
        } elseif ($group === 'finished') {
            $statusesWithLabels = array_intersect_key(
                $statusesWithLabels,
                array_flip(self::FINISHED_STATUSES),
            );
        }

        return $statusesWithLabels;
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

    public function addMessage(Message $message): static
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setTicket($this);
        }

        return $this;
    }

    public function getSolution(): ?Message
    {
        return $this->solution;
    }

    public function hasSolution(): bool
    {
        return $this->solution !== null;
    }

    public function setSolution(?Message $solution): self
    {
        $this->solution = $solution;

        return $this;
    }

    /**
     * @return Collection<int, Contract>
     */
    public function getContracts(): Collection
    {
        return $this->contracts;
    }

    public function getOngoingContract(): ?Contract
    {
        $contracts = $this->getContracts();

        foreach ($contracts as $contract) {
            if ($contract->getStatus() === 'ongoing') {
                return $contract;
            }
        }

        return null;
    }

    public function addContract(Contract $contract): static
    {
        if (!$this->contracts->contains($contract)) {
            $this->contracts->add($contract);
        }

        return $this;
    }

    public function removeContract(Contract $contract): static
    {
        $this->contracts->removeElement($contract);

        return $this;
    }

    /**
     * @return Collection<int, TimeSpent>
     */
    public function getTimeSpents(): Collection
    {
        return $this->timeSpents;
    }

    /**
     * @return Collection<int, TimeSpent>
     */
    public function getUnaccountedTimeSpents(): Collection
    {
        $criteria = Criteria::create();
        $expr = Criteria::expr()->isNull('contract');
        $criteria->where($expr);
        $criteria->orderBy(['createdAt' => Order::Ascending]);

        /** @var ArrayCollection<int, TimeSpent> */
        $timeSpents = $this->timeSpents;

        return $timeSpents->matching($criteria);
    }

    public function addTimeSpent(TimeSpent $timeSpent): static
    {
        if (!$this->timeSpents->contains($timeSpent)) {
            $this->timeSpents->add($timeSpent);
            $timeSpent->setTicket($this);
        }

        return $this;
    }

    public function removeTimeSpent(TimeSpent $timeSpent): static
    {
        if ($this->timeSpents->removeElement($timeSpent)) {
            // set the owning side to null (unless already changed)
            if ($timeSpent->getTicket() === $this) {
                $timeSpent->setTicket(null);
            }
        }

        return $this;
    }

    public function getUniqueKey(): string
    {
        $createdAt = '';
        if ($this->createdAt) {
            $createdAt = $this->createdAt->getTimestamp();
        }

        $organization = '';
        if ($this->organization) {
            $organization = $this->organization->getName();
        }

        return md5("{$this->title}-{$organization}-{$createdAt}");
    }
}
