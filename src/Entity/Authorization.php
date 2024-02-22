<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Entity;

use App\ActivityMonitor\MonitorableEntityInterface;
use App\ActivityMonitor\MonitorableEntityTrait;
use App\Repository\AuthorizationRepository;
use App\Uid\UidEntityInterface;
use App\Uid\UidEntityTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AuthorizationRepository::class)]
#[ORM\Table(name: '`authorizations`')]
class Authorization implements MonitorableEntityInterface, UidEntityInterface
{
    use MonitorableEntityTrait;
    use UidEntityTrait;

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

    #[ORM\ManyToOne(inversedBy: 'authorizations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Role $role = null;

    #[ORM\ManyToOne(inversedBy: 'authorizations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $holder = null;

    #[ORM\ManyToOne(inversedBy: 'authorizations')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Organization $organization = null;

    #[ORM\ManyToOne(inversedBy: 'authorizations')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?TeamAuthorization $teamAuthorization = null;

    public static function fromTeamAuthorization(TeamAuthorization $teamAuthorization): self
    {
        $authorization = new Authorization();
        $authorization->setRole($teamAuthorization->getRole());
        $authorization->setOrganization($teamAuthorization->getOrganization());
        $authorization->setTeamAuthorization($teamAuthorization);
        return $authorization;
    }

    public function getRole(): ?Role
    {
        return $this->role;
    }

    public function setRole(?Role $role): self
    {
        $this->role = $role;

        return $this;
    }

    public function getHolder(): ?User
    {
        return $this->holder;
    }

    public function setHolder(?User $holder): self
    {
        $this->holder = $holder;

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

    public function getTeamAuthorization(): ?TeamAuthorization
    {
        return $this->teamAuthorization;
    }

    public function setTeamAuthorization(?TeamAuthorization $teamAuthorization): static
    {
        $this->teamAuthorization = $teamAuthorization;

        return $this;
    }
}
