<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Entity;

use App\EntityListener\EntitySetMetaListener;
use App\Repository\MailboxEmailRepository;
use App\Utils\Time;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Webklex\PHPIMAP;

#[ORM\Entity(repositoryClass: MailboxEmailRepository::class)]
#[ORM\EntityListeners([EntitySetMetaListener::class])]
class MailboxEmail implements MetaEntityInterface, ActivityRecordableInterface
{
    use MetaEntityTrait;

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

    public function getId(): ?int
    {
        return $this->id;
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

    public function getReplyTo(): ?string
    {
        $address = $this->getEmail()->getReplyTo()->first();

        if (!$address) {
            return null;
        }

        return $address->mail;
    }

    public function getSubject(): string
    {
        $subject = $this->getEmail()->getSubject();
        $decodedSubject = iconv_mime_decode($subject, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, "UTF-8");

        if ($decodedSubject === false) {
            return $subject;
        }

        return $decodedSubject;
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
