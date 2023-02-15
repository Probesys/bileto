<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Entity;

use App\EntityListener\EntitySetMetaListener;
use App\Repository\OrganizationRepository;
use App\Validator as AppAssert;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: OrganizationRepository::class)]
#[ORM\EntityListeners([EntitySetMetaListener::class])]
#[UniqueEntity(
    fields: 'uid',
    message: new TranslatableMessage('The uid {{ value }} is already used.', [], 'validators'),
)]
class Organization implements MetaEntityInterface, ActivityRecordableInterface
{
    public const MAX_DEPTH = 3;

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

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(
        message: new TranslatableMessage('The name is required.', [], 'validators'),
    )]
    #[Assert\Length(
        max: 255,
        maxMessage: new TranslatableMessage('The name must be {{ limit }} characters maximum.', [], 'validators'),
    )]
    private ?string $name = null;

    /** @var Collection<int, Ticket> $tickets */
    #[ORM\OneToMany(mappedBy: 'organization', targetEntity: Ticket::class)]
    private Collection $tickets;

    #[ORM\Column(length: 255, options: ['default' => '/'])]
    #[AppAssert\TreeDepth(
        message: new TranslatableMessage(
            'The sub-organization cannot be attached to this organization.',
            [],
            'validators'
        ),
        max: self::MAX_DEPTH,
    )]
    private ?string $parentsPath = null;

    /** @var Organization[] $subOrganizations */
    private array $subOrganizations = [];

    /** @var Collection<int, Authorization> $authorizations */
    #[ORM\OneToMany(mappedBy: 'organization', targetEntity: Authorization::class)]
    private Collection $authorizations;

    public function __construct()
    {
        $this->tickets = new ArrayCollection();
        $this->parentsPath = '/';
        $this->authorizations = new ArrayCollection();
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Collection<int, Ticket>
     */
    public function getTickets(): Collection
    {
        return $this->tickets;
    }

    public function getParentsPath(): ?string
    {
        return $this->parentsPath;
    }

    public function setParentsPath(string $parentsPath): self
    {
        $this->parentsPath = $parentsPath;

        return $this;
    }

    public function setParent(Organization $parentOrganization): self
    {
        $parentsPath = $parentOrganization->getParentsPath() . $parentOrganization->getId() . '/';
        $this->setParentsPath($parentsPath);

        return $this;
    }

    public function isRootOrganization(): bool
    {
        return $this->parentsPath === '/';
    }

    public function getParentOrganizationId(): int|null
    {
        $parentIds = $this->getParentOrganizationIds();
        if (empty($parentIds)) {
            return null;
        }

        return array_pop($parentIds);
    }

    /**
     * @return int[]
     */
    public function getParentOrganizationIds(): array
    {
        if ($this->isRootOrganization()) {
            return [];
        }

        $ids = explode('/', trim($this->parentsPath, '/'));
        return array_map('intval', $ids);
    }

    public function getDepth(): int
    {
        return substr_count($this->parentsPath, '/');
    }

    public function addSubOrganization(Organization $subOrganization): self
    {
        $this->subOrganizations[] = $subOrganization;

        return $this;
    }

    /**
     * @return Organization[]
     */
    public function getSubOrganizations(): array
    {
        return $this->subOrganizations;
    }

    /**
     * @return Collection<int, Authorization>
     */
    public function getAuthorizations(): Collection
    {
        return $this->authorizations;
    }
}
