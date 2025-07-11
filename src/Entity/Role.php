<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Entity;

use App\ActivityMonitor\MonitorableEntityInterface;
use App\ActivityMonitor\MonitorableEntityTrait;
use App\Repository\RoleRepository;
use App\Uid\UidEntityInterface;
use App\Uid\UidEntityTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: RoleRepository::class)]
#[UniqueEntity(
    fields: 'name',
    message: new TranslatableMessage('role.name.already_used', [], 'errors'),
)]
class Role implements EntityInterface, MonitorableEntityInterface, UidEntityInterface
{
    use MonitorableEntityTrait;
    use UidEntityTrait;

    public const NAME_MAX_LENGTH = 50;

    public const DESCRIPTION_MAX_LENGTH = 255;

    public const TYPES = ['super', 'admin', 'agent', 'user'];

    public const PERMISSIONS = [
        'super' => [
            'admin:*',
        ],

        'admin' => [
            'admin:create:organizations',
            'admin:manage:agents',
            'admin:manage:labels',
            'admin:manage:mailboxes',
            'admin:manage:roles',
            'admin:manage:users',
            'admin:see',
        ],

        'agent' => [
            'orga:create:tickets',
            'orga:create:tickets:messages',
            'orga:create:tickets:messages:confidential',
            'orga:create:tickets:time_spent',
            'orga:manage',
            'orga:manage:contracts',
            'orga:see',
            'orga:see:contracts',
            'orga:see:contracts:notes',
            'orga:see:tickets:all',
            'orga:see:tickets:contracts',
            'orga:see:tickets:messages:confidential',
            'orga:see:tickets:time_spent:accounted',
            'orga:see:tickets:time_spent:real',
            'orga:see:users',
            'orga:update:tickets:actors',
            'orga:update:tickets:contracts',
            'orga:update:tickets:labels',
            'orga:update:tickets:organization',
            'orga:update:tickets:priority',
            'orga:update:tickets:status',
            'orga:update:tickets:title',
            'orga:update:tickets:type',
        ],

        'user' => [
            'orga:create:tickets',
            'orga:create:tickets:messages',
            'orga:see',
            'orga:see:contracts',
            'orga:see:tickets:all',
            'orga:see:tickets:contracts',
            'orga:see:tickets:time_spent:accounted',
            'orga:see:tickets:time_spent:real',
            'orga:see:users',
            'orga:update:tickets:priority',
            'orga:update:tickets:title',
            'orga:update:tickets:type',
        ],
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
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $updatedBy = null;

    #[ORM\Column(length: self::NAME_MAX_LENGTH, unique: true)]
    #[Assert\NotBlank(
        message: new TranslatableMessage('role.name.required', [], 'errors'),
    )]
    #[Assert\Length(
        max: self::NAME_MAX_LENGTH,
        maxMessage: new TranslatableMessage('role.name.max_chars', [], 'errors'),
    )]
    private ?string $name = null;

    #[ORM\Column(length: self::DESCRIPTION_MAX_LENGTH)]
    #[Assert\NotBlank(
        message: new TranslatableMessage('role.description.required', [], 'errors'),
    )]
    #[Assert\Length(
        max: self::DESCRIPTION_MAX_LENGTH,
        maxMessage: new TranslatableMessage('role.description.max_chars', [], 'errors'),
    )]
    private ?string $description = null;

    #[ORM\Column(length: 32)]
    #[Assert\Choice(choices: self::TYPES)]
    private ?string $type = null;

    /** @var string[] $permissions */
    #[ORM\Column]
    #[Assert\All([
        new Assert\NotBlank(),
    ])]
    private array $permissions = [];

    #[ORM\Column]
    private bool $isDefault = false;

    /** @var Collection<int, Authorization> $authorizations */
    #[ORM\OneToMany(mappedBy: 'role', targetEntity: Authorization::class, orphanRemoval: true)]
    private Collection $authorizations;

    public function __construct()
    {
        $this->name = '';
        $this->description = '';
        $this->authorizations = new ArrayCollection();
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

    public function isAdmin(): bool
    {
        return $this->type === 'admin' || $this->type === 'super';
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
        $this->permissions = self::sanitizePermissions($this->type, $permissions);

        return $this;
    }

    /**
     * @param string[] $permissions
     * @return string[]
     */
    public static function sanitizePermissions(string $type, array $permissions): array
    {
        $availablePermissions = self::PERMISSIONS[$type] ?? [];
        $permissions = array_intersect($availablePermissions, $permissions);

        // We use array_values to reindex the returned array.
        $permissions = array_values($permissions);

        if ($type === 'super' && !in_array('admin:*', $permissions)) {
            $permissions[] = 'admin:*';
        } elseif ($type === 'admin' && !in_array('admin:see', $permissions)) {
            $permissions[] = 'admin:see';
        } elseif ($type === 'agent' && !in_array('orga:see', $permissions)) {
            $permissions[] = 'orga:see';
        } elseif ($type === 'user' && !in_array('orga:see', $permissions)) {
            $permissions[] = 'orga:see';
        }

        return $permissions;
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
