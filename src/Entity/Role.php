<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Entity;

use App\EntityListener\EntitySetMetaListener;
use App\Repository\RoleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: RoleRepository::class)]
#[ORM\EntityListeners([EntitySetMetaListener::class])]
#[UniqueEntity(
    fields: 'uid',
    message: new TranslatableMessage('meta.uid.already_used', [], 'errors'),
)]
#[UniqueEntity(
    fields: 'name',
    message: new TranslatableMessage('role.name.already_used', [], 'errors'),
)]
class Role implements MetaEntityInterface, ActivityRecordableInterface
{
    use MetaEntityTrait;

    public const TYPES = ['super', 'admin', 'orga'];

    public const PERMISSIONS = [
        'admin:*',
        'admin:manage:organizations',
        'admin:manage:roles',
        'admin:manage:users',
        'admin:see',

        'orga:create:tickets',
        'orga:create:tickets:messages',
        'orga:create:tickets:messages:confidential',
        'orga:see',
        'orga:see:tickets:all',
        'orga:see:tickets:messages:confidential',
        'orga:update:tickets:actors',
        'orga:update:tickets:priority',
        'orga:update:tickets:status',
        'orga:update:tickets:title',
        'orga:update:tickets:type',
    ];

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

    #[ORM\Column(length: 50, unique: true)]
    #[Assert\NotBlank(
        message: new TranslatableMessage('role.name.required', [], 'errors'),
    )]
    #[Assert\Length(
        max: 50,
        maxMessage: new TranslatableMessage('role.name.max_chars', [], 'errors'),
    )]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(
        message: new TranslatableMessage('role.description.required', [], 'errors'),
    )]
    #[Assert\Length(
        max: 255,
        maxMessage: new TranslatableMessage('role.description.max_chars', [], 'errors'),
    )]
    private ?string $description = null;

    #[ORM\Column(length: 32)]
    #[Assert\Choice(choices: self::TYPES)]
    private ?string $type = null;

    /** @var string[] $permissions */
    #[ORM\Column(type: Types::ARRAY)]
    #[Assert\All([
        new Assert\NotBlank(),
        new Assert\Choice(choices: self::PERMISSIONS),
    ])]
    private array $permissions = [];

    #[ORM\Column]
    private bool $isDefault = false;

    /** @var Collection<int, Authorization> $authorizations */
    #[ORM\OneToMany(mappedBy: 'role', targetEntity: Authorization::class, orphanRemoval: true)]
    private Collection $authorizations;

    public function __construct()
    {
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getPermissions(): array
    {
        return $this->permissions;
    }

    /**
     * @param string[] $permissions
     */
    public function setPermissions(array $permissions): self
    {
        $this->permissions = $permissions;

        return $this;
    }

    /**
     * @param string[] $permissions
     * @return string[]
     */
    public static function sanitizePermissions(string $type, array $permissions): array
    {
        $sanitizedPermissions = [];

        foreach ($permissions as $permission) {
            if (
                str_starts_with($permission, $type . ':') &&
                in_array($permission, self::PERMISSIONS) &&
                $permission !== 'admin:*' // it is reserved to the super admin role
            ) {
                $sanitizedPermissions[] = $permission;
            }
        }

        return $sanitizedPermissions;
    }

    public function hasPermission(string $permission): bool
    {
        return (
            in_array($permission, $this->permissions) ||
            (str_starts_with($permission, 'admin:') && in_array('admin:*', $this->permissions))
        );
    }

    public function isDefault(): ?bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): self
    {
        $this->isDefault = $isDefault;

        return $this;
    }

    /**
     * @return Collection<int, Authorization>
     */
    public function getAuthorizations(): Collection
    {
        return $this->authorizations;
    }
}
