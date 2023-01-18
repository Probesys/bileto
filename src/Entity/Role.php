<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Entity;

use App\Repository\RoleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: RoleRepository::class)]
#[UniqueEntity(
    fields: 'uid',
    message: new TranslatableMessage('The uid {{ value }} is already used.', [], 'validators'),
)]
#[UniqueEntity(
    fields: 'name',
    message: new TranslatableMessage('The role {{ value }} is already used.', [], 'validators'),
)]
class Role
{
    public const TYPES = ['super', 'admin', 'orga'];

    public const PERMISSIONS = [
        'admin:*',
        'admin:see:settings',
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

    #[ORM\Column(length: 50, unique: true)]
    #[Assert\NotBlank(
        message: new TranslatableMessage('The name is required.', [], 'validators'),
    )]
    #[Assert\Length(
        max: 50,
        maxMessage: new TranslatableMessage('The name must be {{ limit }} characters maximum.', [], 'validators'),
    )]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(
        message: new TranslatableMessage('The description is required.', [], 'validators'),
    )]
    #[Assert\Length(
        max: 255,
        maxMessage: new TranslatableMessage(
            'The description must be {{ limit }} characters maximum.',
            [],
            'validators'
        ),
    )]
    private ?string $description = null;

    #[ORM\Column(length: 32)]
    #[Assert\Choice(
        choices: self::TYPES,
        message: new TranslatableMessage('The type {{ value }} is not a valid type.', [], 'validators'),
    )]
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
}
