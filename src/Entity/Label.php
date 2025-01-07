<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Entity;

use App\ActivityMonitor\MonitorableEntityInterface;
use App\ActivityMonitor\MonitorableEntityTrait;
use App\Repository\LabelRepository;
use App\Uid\UidEntityInterface;
use App\Uid\UidEntityTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: LabelRepository::class)]
#[UniqueEntity(
    fields: 'name',
    message: new TranslatableMessage('label.name.already_used', [], 'errors'),
)]
class Label implements EntityInterface, MonitorableEntityInterface, UidEntityInterface
{
    use MonitorableEntityTrait;
    use UidEntityTrait;

    public const NAME_MAX_LENGTH = 50;

    public const DESCRIPTION_MAX_LENGTH = 250;

    public const COLORS = ['grey', 'primary', 'blue', 'green', 'orange', 'red'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, unique: true)]
    private ?string $uid = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne]
    private ?User $updatedBy = null;

    #[ORM\Column(length: self::NAME_MAX_LENGTH, unique: true)]
    #[Assert\NotBlank(
        message: new TranslatableMessage('label.name.required', [], 'errors'),
    )]
    #[Assert\Length(
        max: self::NAME_MAX_LENGTH,
        maxMessage: new TranslatableMessage('label.name.max_chars', [], 'errors'),
    )]
    private ?string $name = null;

    #[ORM\Column(length: self::DESCRIPTION_MAX_LENGTH)]
    #[Assert\Length(
        max: self::DESCRIPTION_MAX_LENGTH,
        maxMessage: new TranslatableMessage('label.description.max_chars', [], 'errors'),
    )]
    private ?string $description = null;

    #[ORM\Column(length: 7)]
    #[Assert\Choice(
        choices: self::COLORS,
        message: new TranslatableMessage('label.color.invalid', [], 'errors'),
    )]
    private ?string $color = null;

    /** @var Collection<int, Ticket> */
    #[ORM\ManyToMany(targetEntity: Ticket::class, mappedBy: 'labels')]
    private Collection $tickets;

    public function __construct()
    {
        $this->name = '';
        $this->description = '';
        $this->color = 'grey';
        $this->tickets = new ArrayCollection();
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(string $color): static
    {
        $this->color = $color;

        return $this;
    }

    /**
     * @return Collection<int, Ticket>
     */
    public function getTickets(): Collection
    {
        return $this->tickets;
    }
}
