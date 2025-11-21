<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Entity;

use App\ActivityMonitor\MonitorableEntityInterface;
use App\ActivityMonitor\MonitorableEntityTrait;
use App\Repository\MessageTemplateRepository;
use App\Uid\UidEntityInterface;
use App\Uid\UidEntityTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MessageTemplateRepository::class)]
#[UniqueEntity(
    fields: 'name',
    message: new TranslatableMessage('message_template.name.already_used', domain: 'errors'),
)]
class MessageTemplate implements EntityInterface, MonitorableEntityInterface, UidEntityInterface
{
    use MonitorableEntityTrait;
    use UidEntityTrait;

    public const NAME_MAX_LENGTH = 255;

    public const TYPES = ['normal', 'confidential', 'solution'];
    public const DEFAULT_TYPE = 'normal';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, unique: true)]
    private ?string $uid = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $updatedBy = null;

    #[ORM\Column(length: self::NAME_MAX_LENGTH)]
    #[Assert\NotBlank(
        message: new TranslatableMessage('message_template.name.required', domain: 'errors'),
    )]
    #[Assert\Length(
        max: self::NAME_MAX_LENGTH,
        maxMessage: new TranslatableMessage('message_template.name.max_chars', domain: 'errors'),
    )]
    private string $name = '';

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(
        message: new TranslatableMessage('message_template.content.required', domain: 'errors'),
    )]
    private string $content = '';

    #[ORM\Column(length: 32)]
    #[Assert\Choice(
        choices: self::TYPES,
        message: new TranslatableMessage('message_template.type.invalid', [], 'errors'),
    )]
    private string $type = self::DEFAULT_TYPE;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getTypeLabel(): TranslatableMessage
    {
        return new TranslatableMessage("message_templates.type.{$this->getType()}");
    }
}
