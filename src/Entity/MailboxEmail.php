<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Entity;

use App\ActivityMonitor\MonitorableEntityInterface;
use App\ActivityMonitor\MonitorableEntityTrait;
use App\Repository\MailboxEmailRepository;
use App\Service;
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
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
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

    public function getLastErrorSummary(): string
    {
        if (!$this->lastError) {
            return '';
        }

        $splitError = explode("\n", trim($this->lastError));
        return $splitError[0];
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
            $config = Service\MailboxService::getConfig();
            $this->email = PHPIMAP\Message::fromString($this->raw, $config);
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

    /**
     * @return string[]
     */
    public function getTo(): array
    {
        $email = $this->getEmail();
        $ccAddresses = $email->getTo()->all();

        return array_map(function (PHPIMAP\Address $address): string {
            return $address->mail;
        }, $ccAddresses);
    }

    /**
     * @return string[]
     */
    public function getCc(): array
    {
        $email = $this->getEmail();
        $ccAddresses = $email->getCc()->all();

        return array_map(function (PHPIMAP\Address $address): string {
            return $address->mail;
        }, $ccAddresses);
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

    /**
     * Return whether the email is an autoreply or not.
     *
     * Detecting such a thing is tricky. RFC 3834 specifies how it should be
     * done, in particular by using the "Auto-Submitted" header. However, many
     * mail providers don't care, so we're left with some rules learnt from the
     * experience of others.
     *
     * Some references that helped to build this method:
     *
     * - RFC 3834: https://datatracker.ietf.org/doc/html/rfc3834
     * - FreeScout code: https://github.com/freescout-help-desk/freescout/blob/1.8.203/app/Misc/Mail.php#L646
     * - Stackoverflow answer: https://stackoverflow.com/a/14320010
     * - Blog post by arp242: https://www.arp242.net/autoreply.html
     * - Blog post on jitbit.com: https://www.jitbit.com/maxblog/18-detecting-outlook-autoreplyout-of-office-emails-and-x-auto-response-suppress-header/
     * - Wiki of multi_mail Ruby gem: https://github.com/jpmckinney/multi_mail/wiki/Detecting-autoresponders
     *
     * Note that we don't use all of the techniques listed in these references.
     * In particular, we don't want detecting autoreplies based on the subject
     * prefix (e.g. "Auto:") because this is used as a hint for humans, not as
     * an information for algorithms.
     */
    public function isAutoreply(): bool
    {
        $email = $this->getEmail();

        $headerChecks = [
            'Auto-Submitted' => fn (string $value): bool => $value !== 'no',
            'X-Autoreply' => fn (string $value): bool => $value === 'yes',
            'X-Autorespond' => fn (string $value): bool => $value !== '',
            'X-Autoresponder' => fn (string $value): bool => $value !== '',
            'Precedence' => fn (string $value): bool => $value === 'auto_reply',
            'X-Precedence' => fn (string $value): bool => $value === 'auto_reply',
            'Delivered-To' => fn (string $value): bool => $value === 'autoresponder',
        ];

        foreach ($headerChecks as $headerName => $headerCheck) {
            $header = $email->get($headerName);
            if (!$header instanceof PHPIMAP\Attribute) {
                continue;
            }

            $value = $header->first() ?? '';
            $value = strtolower($value);
            if ($value && $headerCheck($value)) {
                return true;
            }
        }

        return false;
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
