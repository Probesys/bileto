<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Entity;

use App\ActivityMonitor\MonitorableEntityInterface;
use App\ActivityMonitor\MonitorableEntityTrait;
use App\Repository\UserRepository;
use App\Uid\UidEntityInterface;
use App\Uid\UidEntityTrait;
use App\Utils\Locales;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
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
    UserInterface,
    PasswordAuthenticatedUserInterface,
    MonitorableEntityInterface,
    UidEntityInterface
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

    #[ORM\Column(length: 5, options: ['default' => Locales::DEFAULT_LOCALE])]
    #[Assert\NotBlank(
        message: new TranslatableMessage('user.language.required', [], 'errors'),
    )]
    #[Assert\Choice(
        choices: Locales::SUPPORTED_LOCALES,
        message: new TranslatableMessage('user.language.invalid', [], 'errors'),
    )]
    private ?string $locale = Locales::DEFAULT_LOCALE;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(
        max: 100,
        maxMessage: new TranslatableMessage('user.name.max_chars', [], 'errors'),
    )]
    private ?string $name = null;

    /** @var Collection<int, Authorization> $authorizations */
    #[ORM\OneToMany(mappedBy: 'holder', targetEntity: Authorization::class, orphanRemoval: true)]
    private Collection $authorizations;

    #[ORM\Column]
    private ?bool $hideEvents = false;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Organization $organization = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ldapIdentifier = null;

    public function __construct()
    {
        $this->password = '';
        $this->authorizations = new ArrayCollection();
    }

    public function getEmail(): ?string
    {
        return $this->email;
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
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
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
        if ($this->getLdapIdentifier() === null) {
            return 'local';
        } else {
            return 'ldap';
        }
    }
}
