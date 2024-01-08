<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Entity;

use App\ActivityMonitor\MonitorableEntityInterface;
use App\ActivityMonitor\MonitorableEntityTrait;
use App\Repository\MessageRepository;
use App\Uid\UidEntityInterface;
use App\Uid\UidEntityTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
class Message implements MonitorableEntityInterface, UidEntityInterface
{
    use MonitorableEntityTrait;
    use UidEntityTrait;

    public const VIAS = ['webapp', 'email'];
    public const DEFAULT_VIA = 'webapp';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, unique: true)]
    private ?string $uid = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne]
    private ?User $updatedBy = null;

    #[ORM\Column]
    private bool $isConfidential = false;

    #[ORM\Column(length: 32, options: ['default' => self::DEFAULT_VIA])]
    #[Assert\Choice(choices: self::VIAS)]
    private string $via = self::DEFAULT_VIA;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(
        message: new TranslatableMessage('message.content.required', [], 'errors'),
    )]
    private string $content = '';

    #[ORM\ManyToOne(inversedBy: 'messages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Ticket $ticket = null;

    #[ORM\Column(length: 1000, nullable: true)]
    private ?string $emailId = null;

    /** @var Collection<int, MessageDocument> */
    #[ORM\OneToMany(mappedBy: 'message', targetEntity: MessageDocument::class)]
    private Collection $messageDocuments;

    public function __construct()
    {
        $this->messageDocuments = new ArrayCollection();
    }

    public function isConfidential(): ?bool
    {
        return $this->isConfidential;
    }

    public function setIsConfidential(bool $isConfidential): self
    {
        $this->isConfidential = $isConfidential;

        return $this;
    }

    public function getVia(): ?string
    {
        return $this->via;
    }

    public function setVia(string $via): self
    {
        $this->via = $via;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = trim($content);

        return $this;
    }

    public function getTicket(): ?Ticket
    {
        return $this->ticket;
    }

    public function setTicket(?Ticket $ticket): self
    {
        $this->ticket = $ticket;

        return $this;
    }

    public function getTimelineType(): string
    {
        return 'message';
    }

    public function getEmailId(): ?string
    {
        return $this->emailId;
    }

    public function setEmailId(?string $emailId): static
    {
        $this->emailId = $emailId;

        return $this;
    }

    /**
     * @return Collection<int, MessageDocument>
     */
    public function getMessageDocuments(): Collection
    {
        return $this->messageDocuments;
    }
}
