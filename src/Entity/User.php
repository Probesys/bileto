<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Entity;

use App\Repository\UserRepository;
use App\Utils\Locales;
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
    message: new TranslatableMessage('The email {{ value }} is already used.', [], 'validators'),
)]
#[UniqueEntity(
    fields: 'uid',
    message: new TranslatableMessage('The uid {{ value }} is already used.', [], 'validators'),
)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank(
        message: new TranslatableMessage('The email is required.', [], 'validators'),
    )]
    #[Assert\Email(
        message: new TranslatableMessage('The email {{ value }} is not a valid address.', [], 'validators'),
    )]
    private ?string $email = null;

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 255, options: ['default' => 'auto'])]
    #[Assert\NotBlank(
        message: new TranslatableMessage('The color scheme is required.', [], 'validators'),
    )]
    #[Assert\Choice(
        choices: ['auto', 'light', 'dark'],
        message: new TranslatableMessage('The color scheme {{ value }} is not a valid choice.', [], 'validators'),
    )]
    private ?string $colorScheme = 'auto';

    #[ORM\Column(length: 5, options: ['default' => Locales::DEFAULT_LOCALE])]
    #[Assert\NotBlank(
        message: new TranslatableMessage('The language is required.', [], 'validators'),
    )]
    #[Assert\Choice(
        choices: Locales::SUPPORTED_LOCALES,
        message: new TranslatableMessage('The language {{ value }} is not a valid choice.', [], 'validators'),
    )]
    private ?string $locale = Locales::DEFAULT_LOCALE;

    #[ORM\Column(length: 20, unique: true)]
    private ?string $uid = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(
        max: 100,
        maxMessage: new TranslatableMessage('The name must be {{ limit }} characters maximum.', [], 'validators'),
    )]
    private ?string $name = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getUid(): ?string
    {
        return $this->uid;
    }

    public function setUid(string $uid): self
    {
        $this->uid = $uid;

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
}
