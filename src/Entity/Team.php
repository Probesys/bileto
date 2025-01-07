<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Entity;

use App\ActivityMonitor\MonitorableEntityInterface;
use App\ActivityMonitor\MonitorableEntityTrait;
use App\Repository\TeamRepository;
use App\Uid\UidEntityInterface;
use App\Uid\UidEntityTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TeamRepository::class)]
#[UniqueEntity(
    fields: 'name',
    message: new TranslatableMessage('team.name.already_used', [], 'errors'),
)]
class Team implements EntityInterface, MonitorableEntityInterface, UidEntityInterface
{
    use MonitorableEntityTrait;
    use UidEntityTrait;

    public const NAME_MAX_LENGTH = 50;

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
        message: new TranslatableMessage('team.name.required', [], 'errors'),
    )]
    #[Assert\Length(
        max: self::NAME_MAX_LENGTH,
        maxMessage: new TranslatableMessage('team.name.max_chars', [], 'errors'),
    )]
    private ?string $name = null;

    /** @var Collection<int, User> */
    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'teams')]
    private Collection $agents;

    /** @var Collection<int, TeamAuthorization> */
    #[ORM\OneToMany(
        mappedBy: 'team',
        targetEntity: TeamAuthorization::class,
        cascade: ['persist'],
    )]
    private Collection $teamAuthorizations;

    /**
     * @var Collection<int, Organization>
     */
    #[ORM\OneToMany(targetEntity: Organization::class, mappedBy: 'responsibleTeam')]
    private Collection $supervisedOrganizations;

    #[ORM\Column(options: ['default' => false])]
    private bool $isResponsible = false;

    public function __construct()
    {
        $this->name = '';
        $this->agents = new ArrayCollection();
        $this->teamAuthorizations = new ArrayCollection();
        $this->supervisedOrganizations = new ArrayCollection();
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

    /**
     * @return Collection<int, User>
     */
    public function getAgents(): Collection
    {
        return $this->agents;
    }

    /**
     * @return int[]
     */
    public function getAgentsIds(): array
    {
        return array_map(function ($agent): ?int {
            return $agent->getId();
        }, $this->agents->toArray());
    }

    /**
     * @deprecated
     * @return string[]
     */
    public function getAgentsUids(): array
    {
        return array_map(function ($agent): ?string {
            return $agent->getUid();
        }, $this->agents->toArray());
    }

    public function hasAgent(User $agent): bool
    {
        return $this->agents->contains($agent);
    }

    public function addAgent(User $agent): static
    {
        if (!$this->agents->contains($agent)) {
            $this->agents->add($agent);
        }

        return $this;
    }

    public function removeAgent(User $agent): static
    {
        $this->agents->removeElement($agent);

        return $this;
    }

    /**
     * @return Collection<int, TeamAuthorization>
     */
    public function getTeamAuthorizations(): Collection
    {
        return $this->teamAuthorizations;
    }

    public function addTeamAuthorization(TeamAuthorization $teamAuthorization): static
    {
        if (!$this->teamAuthorizations->contains($teamAuthorization)) {
            $this->teamAuthorizations->add($teamAuthorization);
            $teamAuthorization->setTeam($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, Organization>
     */
    public function getSupervisedOrganizations(): Collection
    {
        return $this->supervisedOrganizations;
    }

    public function isResponsible(): bool
    {
        return $this->isResponsible;
    }

    public function setIsResponsible(bool $isResponsible): static
    {
        $this->isResponsible = $isResponsible;

        return $this;
    }
}
