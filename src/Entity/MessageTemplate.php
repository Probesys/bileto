<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Entity;

use App\ActivityMonitor\RecordableEntityInterface;
use App\ActivityMonitor\RecordableEntityTrait;
use App\Repository\MessageTemplateRepository;
use App\Uid\UidEntityInterface;
use App\Uid\UidEntityTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MessageTemplateRepository::class)]
class MessageTemplate implements
    UidEntityInterface,
    RecordableEntityInterface
{
    use UidEntityTrait;
    use RecordableEntityTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, unique: true)]
    private ?string $uid = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn]
    private ?User $createdBy = null;

    #[ORM\Column(nullable: false)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn]
    private ?User $updatedBy = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'message_template.name.required')]
    #[Assert\Length(max: 255, maxMessage: 'message_template.name.max_chars')]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $content = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'message_template.type.required')]
    #[Assert\Choice(
        choices: ['normal', 'confidential', 'solution'],
        message: 'message_template.type.invalid'
    )]
    private ?string $type = 'normal';

    #[ORM\Column]
    #[Assert\GreaterThanOrEqual(0, message: 'message_template.time_spent.min')]
    private int $timeSpent = 0;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): self
    {
        $this->content = $content;

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

    public function getTimeSpent(): int
    {
        return $this->timeSpent;
    }

    public function setTimeSpent(int $timeSpent): self
    {
        $this->timeSpent = $timeSpent;

        return $this;
    }
}
