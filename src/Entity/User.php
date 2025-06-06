<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Entity;

use App\ActivityMonitor\MonitorableEntityInterface;
use App\ActivityMonitor\MonitorableEntityTrait;
use App\Repository\UserRepository;
use App\Service;
use App\Uid\UidEntityInterface;
use App\Uid\UidEntityTrait;
use App\Utils;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Mime\Address;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`users`')]
#[UniqueEntity(
    fields: 'email',
    message: new TranslatableMessage('user.email.already_used', [], 'errors'),
)]
class User implements
    EntityInterface,
    UserInterface,
    PasswordAuthenticatedUserInterface,
    MonitorableEntityInterface,
    UidEntityInterface
{
    use MonitorableEntityTrait;
    use UidEntityTrait;

    public const NAME_MAX_LENGTH = 100;

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

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank(
        message: new TranslatableMessage('user.email.required', [], 'errors'),
    )]
    #[Assert\Email(
        message: new TranslatableMessage('user.email.invalid', [], 'errors'),
    )]
    private ?string $email = null;

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 255, options: ['default' => 'auto'])]
    #[Assert\NotBlank(
        message: new TranslatableMessage('user.color_scheme.required', [], 'errors'),
    )]
    #[Assert\Choice(
        choices: ['auto', 'light', 'dark'],
        message: new TranslatableMessage('user.color_scheme.invalid', [], 'errors'),
    )]
    private ?string $colorScheme = 'auto';

    #[ORM\Column(length: 5, options: ['default' => 'en_GB'])]
    #[Assert\NotBlank(
        message: new TranslatableMessage('user.language.required', [], 'errors'),
    )]
    #[Assert\Choice(
        callback: [Service\Locales::class, 'getSupportedLocalesCodes'],
        message: new TranslatableMessage('user.language.invalid', [], 'errors'),
    )]
    private ?string $locale = null;

    #[ORM\Column(length: self::NAME_MAX_LENGTH, nullable: true)]
    #[Assert\Length(
        max: self::NAME_MAX_LENGTH,
        maxMessage: new TranslatableMessage('user.name.max_chars', [], 'errors'),
    )]
    private ?string $name = null;

    /** @var Collection<int, Authorization> $authorizations */
    #[ORM\OneToMany(
        mappedBy: 'holder',
        targetEntity: Authorization::class,
        orphanRemoval: true,
        cascade: ['persist'],
    )]
    private Collection $authorizations;

    #[ORM\Column]
    private ?bool $hideEvents = false;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Organization $organization = null;

    #[ORM\Column(length: 255, options: ['default' => ''])]
    private ?string $ldapIdentifier = null;

    /** @var Collection<int, Team> */
    #[ORM\ManyToMany(targetEntity: Team::class, mappedBy: 'agents')]
    private Collection $teams;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Token $resetPasswordToken = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $loginDisabledAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $anonymizedAt = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?self $anonymizedBy = null;

    public function __construct()
    {
        $this->name = '';
        $this->email = '';
        $this->password = '';
        $this->locale = '';
        $this->ldapIdentifier = '';
        $this->authorizations = new ArrayCollection();
        $this->teams = new ArrayCollection();
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getEmailAddress(): Address
    {
        $email = $this->getEmail();
        $name = $this->getDisplayName();

        if ($email === $name) {
            return new Address($email);
        } else {
            return new Address($email, $name);
        }
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     *
     * @return non-empty-string
     */
    public function getUserIdentifier(): string
    {
        $identifier = $this->email;

        if (!$identifier) {
            throw new \LogicException('User identifier (email) cannot be empty');
        }

        return $identifier;
    }

    /**
     * This methods has nothing to do with "Bileto roles". It is in fact a
     * requirement of the Symfony authentication system.
     *
     * @see UserInterface
     */
    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return (string) $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getColorScheme(): ?string
    {
        return $this->colorScheme;
    }

    public function setColorScheme(string $colorScheme): self
    {
        $this->colorScheme = $colorScheme;

        return $this;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getDisplayName(): ?string
    {
        if ($this->name) {
            return $this->name;
        } else {
            return $this->email;
        }
    }

    /**
     * @return Collection<int, Authorization>
     */
    public function getAuthorizations(): Collection
    {
        return $this->authorizations;
    }

    public function addAuthorization(Authorization $authorization): static
    {
        if (!$this->authorizations->contains($authorization)) {
            $this->authorizations->add($authorization);
            $authorization->setHolder($this);
        }

        return $this;
    }

    public function areEventsHidden(): ?bool
    {
        return $this->hideEvents;
    }

    public function setHideEvents(bool $hideEvents): self
    {
        $this->hideEvents = $hideEvents;

        return $this;
    }

    public function getOrganization(): ?Organization
    {
        return $this->organization;
    }

    public function setOrganization(?Organization $organization): self
    {
        $this->organization = $organization;

        return $this;
    }

    public function getLdapIdentifier(): ?string
    {
        return $this->ldapIdentifier;
    }

    public function setLdapIdentifier(?string $ldapIdentifier): static
    {
        $this->ldapIdentifier = $ldapIdentifier;

        return $this;
    }

    /**
     * @return 'local'|'ldap'
     */
    public function getAuthType(): string
    {
        if ($this->getLdapIdentifier() === '') {
            return 'local';
        } else {
            return 'ldap';
        }
    }

    /**
     * @return Collection<int, Team>
     */
    public function getTeams(): Collection
    {
        return $this->teams;
    }

    public function getResetPasswordToken(): ?Token
    {
        return $this->resetPasswordToken;
    }

    public function setResetPasswordToken(?Token $resetPasswordToken): static
    {
        $this->resetPasswordToken = $resetPasswordToken;

        return $this;
    }

    public function canLogin(): bool
    {
        return $this->loginDisabledAt === null;
    }

    public function allowLogin(): static
    {
        $this->loginDisabledAt = null;

        return $this;
    }

    public function disableLogin(): static
    {
        if (!$this->loginDisabledAt) {
            $this->loginDisabledAt = Utils\Time::now();
            $this->password = Utils\Random::hex(64);
        }

        return $this;
    }

    public function setLoginDisabledAt(?\DateTimeImmutable $loginDisabledAt): static
    {
        $this->loginDisabledAt = $loginDisabledAt;

        return $this;
    }

    public function isAnonymized(): bool
    {
        return $this->anonymizedAt !== null;
    }

    public function anonymize(string $name, self $by): static
    {
        $this->disableLogin();

        $this->name = $name;
        $this->email = "anonymous+{$this->uid}@example.com";
        $this->organization = null;
        $this->ldapIdentifier = '';

        $this->anonymizedAt = Utils\Time::now();
        $this->anonymizedBy = $by;

        return $this;
    }

    public function getAnonymizedAt(): ?\DateTimeImmutable
    {
        return $this->anonymizedAt;
    }

    public function setAnonymizedAt(?\DateTimeImmutable $anonymizedAt): static
    {
        $this->anonymizedAt = $anonymizedAt;

        return $this;
    }

    public function getAnonymizedBy(): ?self
    {
        return $this->anonymizedBy;
    }

    public function setAnonymizedBy(?self $anonymizedBy): static
    {
        $this->anonymizedBy = $anonymizedBy;

        return $this;
    }
}
