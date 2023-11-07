<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Entity;

use App\EntityListener\EntitySetMetaListener;
use App\Repository\ContractRepository;
use App\Utils;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ContractRepository::class)]
#[ORM\EntityListeners([EntitySetMetaListener::class])]
class Contract implements MetaEntityInterface, ActivityRecordableInterface
{
    use MetaEntityTrait;

    public const STATUSES = ['coming', 'ongoing', 'finished'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private ?string $uid = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $updatedBy = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(
        message: new TranslatableMessage('contract.name.required', [], 'errors'),
    )]
    #[Assert\Length(
        max: 255,
        maxMessage: new TranslatableMessage('contract.name.max_chars', [], 'errors'),
    )]
    private ?string $name = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    #[Assert\NotBlank(
        message: new TranslatableMessage('contract.start_at.required', [], 'errors'),
    )]
    private ?\DateTimeImmutable $startAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    #[Assert\NotBlank(
        message: new TranslatableMessage('contract.end_at.required', [], 'errors'),
    )]
    #[Assert\GreaterThan(
        propertyPath: 'startAt',
        message: new TranslatableMessage('contract.end_at.greater_than_start_at', [], 'errors'),
    )]
    private ?\DateTimeImmutable $endAt = null;

    #[ORM\Column]
    #[Assert\NotBlank(
        message: new TranslatableMessage('contract.max_hours.required', [], 'errors'),
    )]
    #[Assert\GreaterThan(
        value: 0,
        message: new TranslatableMessage('contract.max_hours.greater_than_zero', [], 'errors'),
    )]
    private ?int $maxHours = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $notes = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Organization $organization = null;

    /** @var Collection<int, Ticket> $tickets */
    #[ORM\ManyToMany(targetEntity: Ticket::class, mappedBy: 'contracts')]
    private Collection $tickets;

    /** @var Collection<int, TimeSpent> $timeSpents */
    #[ORM\OneToMany(mappedBy: 'contract', targetEntity: TimeSpent::class)]
    private Collection $timeSpents;

    public function __construct()
    {
        $this->tickets = new ArrayCollection();
        $this->timeSpents = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getStartAt(): ?\DateTimeImmutable
    {
        return $this->startAt;
    }

    public function setStartAt(\DateTimeImmutable $startAt): static
    {
        $this->startAt = $startAt->modify('00:00:00');

        return $this;
    }

    public function getEndAt(): ?\DateTimeImmutable
    {
        return $this->endAt;
    }

    public function setEndAt(\DateTimeImmutable $endAt): static
    {
        $this->endAt = $endAt->modify('23:59:59');

        return $this;
    }

    public function getMaxHours(): ?int
    {
        return $this->maxHours;
    }

    public function setMaxHours(int $maxHours): static
    {
        $this->maxHours = $maxHours;

        return $this;
    }

    public function getConsumedMinutes(): int
    {
        $times = array_map(function (TimeSpent $timeSpent) {
            return $timeSpent->getTime();
        }, $this->getTimeSpents()->getValues());

        /** @var int */
        $consumedHours = array_sum($times);

        return $consumedHours;
    }

    public function getConsumedHours(): float
    {
        return $this->getConsumedMinutes() / 60;
    }

    public function getRemainingMinutes(): int
    {
        return ($this->getMaxHours() * 60) - $this->getConsumedMinutes();
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getOrganization(): ?Organization
    {
        return $this->organization;
    }

    public function setOrganization(?Organization $organization): static
    {
        $this->organization = $organization;

        return $this;
    }

    /**
     * @return value-of<self::STATUSES>
     */
    public function getStatus(): string
    {
        if ($this->getConsumedHours() >= $this->getMaxHours()) {
            return 'finished';
        }

        $today = Utils\Time::now();
        if ($today < $this->startAt) {
            return 'coming';
        } elseif ($today < $this->endAt) {
            return 'ongoing';
        } else {
            return 'finished';
        }
    }

    public function getStatusLabel(): string
    {
        $statusesWithLabels = self::getStatusesWithLabels();
        return $statusesWithLabels[$this->getStatus()];
    }

    public function getStatusBadgeColor(): string
    {
        $status = $this->getStatus();
        if ($status === 'coming') {
            return 'blue';
        } elseif ($status === 'ongoing') {
            return 'green';
        } elseif ($status === 'finished') {
            return 'grey';
        }
    }

    /**
     * @return array<value-of<self::STATUSES>, string>
     */
    public static function getStatusesWithLabels(): array
    {
        return [
            'coming' => new TranslatableMessage('contracts.status.coming'),
            'ongoing' => new TranslatableMessage('contracts.status.ongoing'),
            'finished' => new TranslatableMessage('contracts.status.finished'),
        ];
    }

    /**
     * Return the number of days between startAt and endAt.
     */
    public function getDaysDuration(): int
    {
        $interval = $this->startAt->diff($this->endAt);
        // We add a day because e.g. 2023-01-01 00:00:00 to 2023-01-01 23:59:59
        // will return 0 day, while we consider on our side that a whole day
        // passed.
        return intval($interval->format('%a')) + 1;
    }

    /**
     * Return the number of days since startAt. It returns 0 if startAt is in
     * the future, or the contract duration if endAt is in the past.
     */
    public function getDaysProgress(): int
    {
        $now = Utils\Time::now();
        if ($now < $this->startAt) {
            return 0;
        }

        if ($now >= $this->endAt) {
            return $this->getDaysDuration();
        }

        $interval = $this->startAt->diff($now);
        return intval($interval->format('%a'));
    }

    /**
     * @return Collection<int, Ticket>
     */
    public function getTickets(): Collection
    {
        return $this->tickets;
    }

    /**
     * @return Collection<int, TimeSpent>
     */
    public function getTimeSpents(): Collection
    {
        return $this->timeSpents;
    }

    public function addTimeSpent(TimeSpent $timeSpent): static
    {
        if (!$this->timeSpents->contains($timeSpent)) {
            $this->timeSpents->add($timeSpent);
            $timeSpent->setContract($this);
        }

        return $this;
    }

    public function removeTimeSpent(TimeSpent $timeSpent): static
    {
        if ($this->timeSpents->removeElement($timeSpent)) {
            // set the owning side to null (unless already changed)
            if ($timeSpent->getContract() === $this) {
                $timeSpent->setContract(null);
            }
        }

        return $this;
    }
}