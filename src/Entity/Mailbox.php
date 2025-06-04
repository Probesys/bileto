<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Entity;

use App\ActivityMonitor\MonitorableEntityInterface;
use App\ActivityMonitor\MonitorableEntityTrait;
use App\Repository\MailboxRepository;
use App\Uid\UidEntityInterface;
use App\Uid\UidEntityTrait;
use App\Utils\Time;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MailboxRepository::class)]
class Mailbox implements EntityInterface, MonitorableEntityInterface, UidEntityInterface
{
    use MonitorableEntityTrait;
    use UidEntityTrait;

    public const NAME_MAX_LENGTH = 255;
    public const HOST_MAX_LENGTH = 255;
    public const PROTOCOL_MAX_LENGTH = 10;
    public const PORT_RANGE = [0, 65535];
    public const ENCRYPTION_VALUES = ['ssl', 'starttls', 'none'];
    public const USERNAME_MAX_LENGTH = 255;
    public const PASSWORD_MAX_LENGTH = 255;
    public const AUTHENTICATION_VALUES = ['normal', 'oauth'];
    public const FOLDER_MAX_LENGTH = 255;
    public const POST_ACTION_VALUES = ['delete', 'mark as read'];

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

    #[ORM\Column(length: self::NAME_MAX_LENGTH)]
    #[Assert\NotBlank(
        message: new TranslatableMessage('mailbox.name.required', [], 'errors'),
    )]
    private ?string $name = null;

    #[ORM\Column(length: self::HOST_MAX_LENGTH)]
    #[Assert\NotBlank(
        message: new TranslatableMessage('mailbox.host.required', [], 'errors'),
    )]
    private ?string $host = null;

    #[ORM\Column(length: self::PROTOCOL_MAX_LENGTH)]
    private ?string $protocol = null;

    #[ORM\Column]
    #[Assert\Range(
        min: self::PORT_RANGE[0],
        max: self::PORT_RANGE[1],
        notInRangeMessage: new TranslatableMessage('mailbox.port.invalid', [], 'errors'),
    )]
    private ?int $port = null;

    #[ORM\Column(length: 10)]
    #[Assert\Choice(
        choices: self::ENCRYPTION_VALUES,
        message: new TranslatableMessage('mailbox.encryption.invalid', [], 'errors'),
    )]
    private ?string $encryption = null;

    #[ORM\Column(length: self::USERNAME_MAX_LENGTH)]
    #[Assert\NotBlank(
        message: new TranslatableMessage('mailbox.username.required', [], 'errors'),
    )]
    private ?string $username = null;

    #[ORM\Column(length: self::PASSWORD_MAX_LENGTH)]
    private ?string $password = null;

    #[ORM\Column(length: 10)]
    #[Assert\Choice(
        choices: self::AUTHENTICATION_VALUES,
        message: new TranslatableMessage('mailbox.authentication.invalid', [], 'errors'),
    )]
    private ?string $authentication = null;

    #[ORM\Column(length: self::FOLDER_MAX_LENGTH)]
    #[Assert\NotBlank(
        message: new TranslatableMessage('mailbox.folder.required', [], 'errors'),
    )]
    private ?string $folder = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $lastError = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastErrorAt = null;

    #[ORM\Column(length: 255, options: ['default' => 'delete'])]
    #[Assert\Choice(
        choices: self::POST_ACTION_VALUES,
        message: new TranslatableMessage('mailbox.post_action.invalid', [], 'errors'),
    )]
    private ?string $postAction = null;

    public function __construct()
    {
        $this->protocol = 'imap';
        $this->port = 993;
        $this->encryption = 'ssl';
        $this->password = '';
        $this->authentication = 'normal';
        $this->folder = 'INBOX';
        $this->postAction = 'delete';
        $this->lastError = '';
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

    public function getHost(): ?string
    {
        return $this->host;
    }

    public function setHost(string $host): self
    {
        $this->host = $host;

        return $this;
    }

    public function getProtocol(): ?string
    {
        return $this->protocol;
    }

    public function setProtocol(string $protocol): self
    {
        $this->protocol = $protocol;

        return $this;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    public function setPort(int $port): self
    {
        $this->port = $port;

        return $this;
    }

    public function getEncryption(): ?string
    {
        return $this->encryption;
    }

    public function setEncryption(string $encryption): self
    {
        $this->encryption = $encryption;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getAuthentication(): ?string
    {
        return $this->authentication;
    }

    public function setAuthentication(string $authentication): self
    {
        $this->authentication = $authentication;

        return $this;
    }

    public function getFolder(): ?string
    {
        return $this->folder;
    }

    public function setFolder(string $folder): self
    {
        $this->folder = $folder;

        return $this;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function setLastError(string $lastError): self
    {
        $this->lastError = $lastError;

        if ($lastError) {
            $this->lastErrorAt = Time::now();
        } else {
            $this->lastErrorAt = null;
        }

        return $this;
    }

    public function resetLastError(): self
    {
        $this->lastError = '';
        $this->lastErrorAt = null;

        return $this;
    }

    public function getLastErrorAt(): ?\DateTimeImmutable
    {
        return $this->lastErrorAt;
    }

    public function getPostAction(): ?string
    {
        return $this->postAction;
    }

    public function setPostAction(string $postAction): static
    {
        $this->postAction = $postAction;

        return $this;
    }
}
