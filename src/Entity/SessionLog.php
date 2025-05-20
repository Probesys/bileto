<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Entity;

use App\ActivityMonitor;
use App\Repository;
use App\Uid;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\Request;

#[ORM\Entity(repositoryClass: Repository\SessionLogRepository::class)]
#[ORM\Index(columns: ['identifier'])]
class SessionLog implements EntityInterface, ActivityMonitor\TrackableEntityInterface, Uid\UidEntityInterface
{
    use ActivityMonitor\TrackableEntityTrait;
    use Uid\UidEntityTrait;

    public const TYPES = ['login success', 'login failure', 'logout', 'reset password', 'changed password'];

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

    /** @var value-of<self::TYPES> */
    #[ORM\Column(length: 32)]
    private ?string $type = null;

    #[ORM\Column(length: 255)]
    private ?string $identifier = null;

    // IPv6 addresses can be long up to 45 characters.
    // See https://stackoverflow.com/a/39443536
    #[ORM\Column(length: 45)]
    private ?string $ip = null;

    #[ORM\Column(length: 64)]
    private ?string $sessionIdHash = null;

    /** @var array<string, string> */
    #[ORM\Column]
    private array $httpHeaders = [];

    public static function initLoginSuccess(string $identifier, Request $request): self
    {
        return self::init('login success', $identifier, $request);
    }

    public static function initLoginFailure(string $identifier, Request $request): self
    {
        return self::init('login failure', $identifier, $request);
    }

    public static function initLogout(string $identifier, Request $request): self
    {
        return self::init('logout', $identifier, $request);
    }

    public static function initResetPassword(string $identifier, Request $request): self
    {
        return self::init('reset password', $identifier, $request);
    }

    public static function initChangedPassword(string $identifier, Request $request): self
    {
        return self::init('changed password', $identifier, $request);
    }

    /**
     * @param value-of<self::TYPES> $type
     */
    private static function init(string $type, string $identifier, Request $request): self
    {
        $ip = $request->getClientIp();
        $session = $request->getSession();
        $httpHeaders = [
            'User-Agent' => $request->headers->get('User-Agent', ''),
            'Referer' => $request->headers->get('Referer', ''),
            'Host' => $request->headers->get('Host', ''),
        ];

        $sessionLog = new self();
        $sessionLog->setType($type);
        $sessionLog->setIdentifier($identifier);
        $sessionLog->setIp($ip);
        $sessionLog->setHttpHeaders($httpHeaders);
        $sessionLog->setSessionId($session->getId());

        return $sessionLog;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * @param value-of<self::TYPES> $type
     */
    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function isLoginSuccess(): bool
    {
        return $this->type === 'login success';
    }

    public function isLoginFailure(): bool
    {
        return $this->type === 'login failure';
    }

    public function isLogout(): bool
    {
        return $this->type === 'logout';
    }

    public function isResetPassword(): bool
    {
        return $this->type === 'reset password';
    }

    public function isChangedPassword(): bool
    {
        return $this->type === 'changed password';
    }

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): static
    {
        $this->identifier = $identifier;

        return $this;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function setIp(string $ip): static
    {
        $this->ip = $ip;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getHttpHeaders(): array
    {
        return $this->httpHeaders;
    }

    /**
     * @param array<string, string> $httpHeaders
     */
    public function setHttpHeaders(array $httpHeaders): static
    {
        $this->httpHeaders = $httpHeaders;

        return $this;
    }

    public function getSessionIdHash(): ?string
    {
        return $this->sessionIdHash;
    }

    public function getShortHash(): ?string
    {
        return substr($this->sessionIdHash, 0, 10);
    }

    public function setSessionId(#[\SensitiveParameter] string $sessionId): static
    {
        $this->sessionIdHash = hash('sha256', $sessionId);

        return $this;
    }
}
