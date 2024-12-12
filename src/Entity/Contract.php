<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Entity;

use App\ActivityMonitor\MonitorableEntityInterface;
use App\ActivityMonitor\MonitorableEntityTrait;
use App\Repository\ContractRepository;
use App\Uid\UidEntityInterface;
use App\Uid\UidEntityTrait;
use App\Utils;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ContractRepository::class)]
class Contract implements EntityInterface, MonitorableEntityInterface, UidEntityInterface
{
    use MonitorableEntityTrait;
    use UidEntityTrait;

    public const NAME_MAX_LENGTH = 255;
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
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne]
    private ?User $updatedBy = null;

    #[ORM\Column(length: self::NAME_MAX_LENGTH)]
    #[Assert\NotBlank(
        message: new TranslatableMessage('contract.name.required', [], 'errors'),
    )]
    #[Assert\Length(
        max: self::NAME_MAX_LENGTH,
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
    #[Assert\GreaterThanOrEqual(
        propertyPath: 'consumedHours',
        message: new TranslatableMessage('contract.max_hours.greater_than_or_equal', [], 'errors'),
    )]
    private ?int $maxHours = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $notes = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Organization $organization = null;

    /** @var Collection<int, Ticket> $tickets */
    #[ORM\ManyToMany(targetEntity: Ticket::class, mappedBy: 'contracts')]
    private Collection $tickets;

    /** @var Collection<int, TimeSpent> $timeSpents */
    #[ORM\OneToMany(mappedBy: 'contract', targetEntity: TimeSpent::class)]
    private Collection $timeSpents;

    #[ORM\Column(options: ["default" => 0])]
    private ?int $timeAccountingUnit = null;

    #[ORM\Column(options: ['default' => 0])]
    private ?int $hoursAlert = null;

    #[ORM\Column(options: ['default' => 0])]
    private ?int $dateAlert = null;

    #[ORM\OneToOne(targetEntity: self::class)]
    private ?self $renewedBy = null;

    public function __construct()
    {
        $this->tickets = new ArrayCollection();
        $this->timeSpents = new ArrayCollection();
        $this->name = '';
        $this->maxHours = 10;
        $this->startAt = Utils\Time::now();
        $this->endAt = Utils\Time::relative('last day of december');
        $this->timeAccountingUnit = 30;
        $this->notes = '';
        $this->hoursAlert = 0;
        $this->dateAlert = 0;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getRenewedName(): ?string
    {
        if (preg_match('/\d$/', $this->name) === 1) {
            $name = $this->name;
            return strval(++$name);
        } else {
            return $this->name;
        }
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
        $times = array_map(function (TimeSpent $timeSpent): int {
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

    public function getConsumedPercentage(): float
    {
        $consumedHours = $this->getConsumedHours();
        $maxHours = $this->getMaxHours();
        return $consumedHours / $maxHours;
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

    public function getTimeAccountingUnit(): ?int
    {
        return $this->timeAccountingUnit;
    }

    public function setTimeAccountingUnit(int $timeAccountingUnit): static
    {
        $this->timeAccountingUnit = $timeAccountingUnit;

        return $this;
    }

    public function initDefaultAlerts(): void
    {
        $this->hoursAlert = 80;
        $daysDuration = $this->getDaysDuration();
        $this->dateAlert = intval(round($daysDuration * 0.2));
    }

    public function getHoursAlert(): ?int
    {
        return $this->hoursAlert;
    }

    public function getHoursOfAlert(): ?float
    {
        if ($this->getHoursAlert() <= 0) {
            return null;
        }

        return $this->getHoursAlert() * $this->getMaxHours() / 100;
    }

    public function setHoursAlert(int $hoursAlert): static
    {
        $this->hoursAlert = $hoursAlert;

        return $this;
    }

    public function isHoursAlertActivated(): bool
    {
        $hoursOfAlert = $this->getHoursOfAlert();
        return (
            $this->getStatus() === 'ongoing' &&
            $hoursOfAlert !== null &&
            $this->getConsumedHours() >= $hoursOfAlert
        );
    }

    public function getDateAlert(): ?int
    {
        return $this->dateAlert;
    }

    public function getDateOfAlert(): ?\DateTimeImmutable
    {
        if ($this->getDateAlert() <= 0) {
            return null;
        }

        return $this->getEndAt()->modify("-{$this->getDateAlert()} days");
    }

    public function getDateAlertPercent(): float
    {
        $dateOfAlert = $this->getDateOfAlert();
        if ($dateOfAlert === null) {
            return 0;
        }

        $interval = $this->startAt->diff($dateOfAlert);
        $daysSinceStart = intval($interval->format('%a'));
        return round($daysSinceStart * 100 / $this->getDaysDuration(), 2);
    }

    public function setDateAlert(int $dateAlert): static
    {
        $this->dateAlert = $dateAlert;

        return $this;
    }

    public function isDateAlertActivated(): bool
    {
        $dateOfAlert = $this->getDateOfAlert();
        return (
            $this->getStatus() === 'ongoing' &&
            $dateOfAlert !== null &&
            utils\Time::now() >= $dateOfAlert
        );
    }

    public function isAlertActivated(): bool
    {
        return $this->isHoursAlertActivated() || $this->isDateAlertActivated();
    }

    public function getRenewed(): Contract
    {
        $contract = new Contract();

        $contract->setName($this->getRenewedName());
        $contract->setMaxHours($this->getMaxHours());
        $startAt = $this->getEndAt()->modify('+1 day');
        $contract->setStartAt($startAt);
        $endAt = $startAt->modify('last day of december');
        $contract->setEndAt($endAt);
        $contract->setTimeAccountingUnit($this->getTimeAccountingUnit());
        $contract->setNotes($this->getNotes());
        $contract->setHoursAlert($this->getHoursAlert());
        $contract->setDateAlert($this->getDateAlert());
        $contract->setOrganization($this->getOrganization());

        return $contract;
    }

    public function getUniqueKey(): string
    {
        $startAt = '';
        if ($this->startAt) {
            $startAt = $this->startAt->getTimestamp();
        }

        $endAt = '';
        if ($this->endAt) {
            $endAt = $this->endAt->getTimestamp();
        }

        $organization = '';
        if ($this->organization) {
            $organization = $this->organization->getName();
        }

        return md5("{$this->name}-{$organization}-{$startAt}-{$endAt}");
    }

    public function getRenewedBy(): ?self
    {
        return $this->renewedBy;
    }

    public function setRenewedBy(?self $renewedBy): static
    {
        $this->renewedBy = $renewedBy;

        return $this;
    }
}
