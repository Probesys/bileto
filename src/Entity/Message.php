<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Entity;

use App\ActivityMonitor\MonitorableEntityInterface;
use App\ActivityMonitor\MonitorableEntityTrait;
use App\Repository\MessageRepository;
use App\Uid\UidEntityInterface;
use App\Uid\UidEntityTrait;
use App\Utils;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
class Message implements EntityInterface, MonitorableEntityInterface, UidEntityInterface
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
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
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

    /** @var Collection<int, MessageDocument> */
    #[ORM\OneToMany(mappedBy: 'message', targetEntity: MessageDocument::class)]
    private Collection $messageDocuments;

    /** @var string[] */
    #[ORM\Column(options: ['default' => '[]'])]
    private array $notificationsReferences = [];

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?\DateTimeImmutable $postedAt = null;

    public function __construct()
    {
        $this->postedAt = Utils\Time::now();
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

    /**
     * @return Collection<int, MessageDocument>
     */
    public function getMessageDocuments(): Collection
    {
        return $this->messageDocuments;
    }

    /**
     * @return string[]
     */
    public function getEmailNotificationsReferences(): array
    {
        $references = [];

        foreach ($this->notificationsReferences as $reference) {
            if (str_starts_with($reference, 'email:')) {
                $references[] = substr($reference, strlen('email:'));
            }
        }

        return $references;
    }

    public function addEmailNotificationReference(string $reference): static
    {
        return $this->addNotificationReference("email:{$reference}");
    }

    private function addNotificationReference(string $reference): static
    {
        $references = $this->notificationsReferences;

        $references[] = $reference;

        // Make sure the list doesn't contain empty or duplicated values.
        $references = array_filter($references);
        $references = array_unique($references);
        $references = array_values($references);

        $this->notificationsReferences = $references;

        return $this;
    }

    /**
     * @param string[] $notificationsReferences
     */
    public function setNotificationsReferences(array $notificationsReferences): self
    {
        $this->notificationsReferences = $notificationsReferences;

        return $this;
    }

    public function getUniqueKey(): string
    {
        $createdAt = $this->getCreatedAt()->getTimestamp();
        $createdBy = $this->getCreatedBy()?->getEmail();
        $ticketKey = $this->getTicket()?->getUniqueKey();
        $content = $this->getContent();

        return md5("{$createdAt}-{$createdBy}-{$ticketKey}-{$content}");
    }

    public function getPostedAt(): ?\DateTimeImmutable
    {
        return $this->postedAt;
    }

    public function setPostedAt(\DateTimeImmutable $postedAt): static
    {
        $this->postedAt = $postedAt;

        return $this;
    }
}
