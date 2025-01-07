<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Entity;

use App\ActivityMonitor;
use App\Repository;
use App\Utils;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: Repository\TokenRepository::class)]
#[UniqueEntity(
    fields: 'value',
    message: new TranslatableMessage('token.value.already_used', [], 'errors'),
)]
class Token implements EntityInterface, ActivityMonitor\MonitorableEntityInterface
{
    use ActivityMonitor\MonitorableEntityTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne]
    private ?User $updatedBy = null;

    #[ORM\Column(length: 250, unique: true)]
    private ?string $value = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?\DateTimeImmutable $expiredAt = null;

    #[ORM\Column(length: 250)]
    private ?string $description = null;

    public static function create(int $number, string $unit, int $length = 20, string $description = ''): self
    {
        $token = new self();

        $value = Utils\Random::hex($length);
        $token->setValue($value);

        $expiredAt = Utils\Time::fromNow($number, $unit);
        $token->setExpiredAt($expiredAt);

        $token->setDescription($description);

        return $token;
    }

    public function __construct()
    {
        $this->value = '';
        $this->description = '';
        $this->expiredAt = Utils\Time::now();
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(string $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function getExpiredAt(): ?\DateTimeImmutable
    {
        return $this->expiredAt;
    }

    public function setExpiredAt(\DateTimeImmutable $expiredAt): static
    {
        $this->expiredAt = $expiredAt;

        return $this;
    }

    public function isValid(): bool
    {
        return Utils\Time::now() < $this->expiredAt;
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
}
