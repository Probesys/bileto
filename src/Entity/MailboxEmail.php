<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Entity;

use App\ActivityMonitor\MonitorableEntityInterface;
use App\ActivityMonitor\MonitorableEntityTrait;
use App\Repository\MailboxEmailRepository;
use App\Uid\UidEntityInterface;
use App\Uid\UidEntityTrait;
use App\Utils\Time;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Webklex\PHPIMAP;

#[ORM\Entity(repositoryClass: MailboxEmailRepository::class)]
class MailboxEmail implements EntityInterface, MonitorableEntityInterface, UidEntityInterface
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

    #[ORM\Column(type: Types::TEXT)]
    private ?string $raw = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Mailbox $mailbox = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $lastError = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastErrorAt = null;

    private ?PHPIMAP\Message $email = null;

    public function __construct(Mailbox $mailbox, PHPIMAP\Message $email)
    {
        $this->mailbox = $mailbox;
        $this->raw = $email->getHeader()->raw . "\r\n\r\n" . $email->getRawBody();
        $this->lastError = '';
    }

    public function getRaw(): ?string
    {
        return $this->raw;
    }

    public function setRaw(string $raw): self
    {
        $this->raw = $raw;

        return $this;
    }

    public function getMailbox(): ?Mailbox
    {
        return $this->mailbox;
    }

    public function setMailbox(?Mailbox $mailbox): self
    {
        $this->mailbox = $mailbox;

        return $this;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function setLastError(string $lastError): self
    {
        $this->lastError = $lastError;
        $this->lastErrorAt = Time::now();

        return $this;
    }

    public function getLastErrorAt(): ?\DateTimeImmutable
    {
        return $this->lastErrorAt;
    }

    public function getEmail(): PHPIMAP\Message
    {
        if (!$this->email) {
            $clientManager = new PHPIMAP\ClientManager();
            $this->email = PHPIMAP\Message::fromString($this->raw);
        }

        return $this->email;
    }

    public function getMessageId(): string
    {
        return $this->getEmail()->getMessageId()->first();
    }

    public function getFrom(): string
    {
        return $this->getEmail()->getFrom()->first()->mail;
    }

    public function getInReplyTo(): ?string
    {
        $inReplyTo = $this->getEmail()->getInReplyTo()->first();
        $inReplyTo = str_replace(['<', '>'], '', $inReplyTo);

        if (!$inReplyTo) {
            return null;
        }

        return $inReplyTo;
    }

    public function isAutoreply(): bool
    {
        $email = $this->getEmail();

        $isAutosubmitted = false;
        $autosubmittedHeader = $email->get('Auto-Submitted');
        if ($autosubmittedHeader instanceof PHPIMAP\Attribute) {
            $autosubmitted = $autosubmittedHeader->first();
            $isAutosubmitted = $autosubmitted !== false && $autosubmitted !== 'no';
        }

        $isAutoreply = false;
        $autoreplyHeader = $email->get('X-Autoreply');
        if ($autoreplyHeader instanceof PHPIMAP\Attribute) {
            $autoreply = $autoreplyHeader->first();
            $isAutoreply = $autoreply !== false && $autoreply === 'yes';
        }

        return $isAutosubmitted || $isAutoreply;
    }

    public function getSubject(): string
    {
        return $this->getEmail()->getSubject();
    }

    public function getBody(): string
    {
        $email = $this->getEmail();
        if ($email->hasHTMLBody()) {
            return $email->getHTMLBody();
        } else {
            $messageContent = $email->getTextBody();
            $messageContent = htmlspecialchars($messageContent, ENT_COMPAT, 'UTF-8');
            $messageContent = nl2br($messageContent);
            return $messageContent;
        }
    }

    /**
     * @return PHPIMAP\Attachment[]
     */
    public function getAttachments(): array
    {
        $email = $this->getEmail();
        return $email->getAttachments()->toArray();
    }

    public function getDate(): \DateTimeImmutable
    {
        $date = $this->getEmail()->getDate()->first();
        return $date->toDateTimeImmutable();
    }

    public function extractTicketId(): ?int
    {
        preg_match('/\[#(?P<id>\d+)\]/', $this->getSubject(), $matches);

        if (isset($matches['id'])) {
            return intval($matches['id']);
        } else {
            return null;
        }
    }
}
