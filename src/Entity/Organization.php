<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Entity;

use App\ActivityMonitor\MonitorableEntityInterface;
use App\ActivityMonitor\MonitorableEntityTrait;
use App\Repository\OrganizationRepository;
use App\Uid\UidEntityInterface;
use App\Uid\UidEntityTrait;
use App\Utils;
use App\Validator as AppAssert;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: OrganizationRepository::class)]
#[UniqueEntity(
    fields: 'name',
    message: new TranslatableMessage('organization.name.already_used', [], 'errors'),
)]
class Organization implements MonitorableEntityInterface, UidEntityInterface
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
        message: new TranslatableMessage('organization.name.required', [], 'errors'),
    )]
    #[Assert\Length(
        max: 255,
        maxMessage: new TranslatableMessage('organization.name.max_chars', [], 'errors'),
    )]
    private ?string $name = null;

    /** @var Collection<int, Ticket> $tickets */
    #[ORM\OneToMany(mappedBy: 'organization', targetEntity: Ticket::class)]
    private Collection $tickets;

    /** @var Collection<int, Authorization> $authorizations */
    #[ORM\OneToMany(mappedBy: 'organization', targetEntity: Authorization::class)]
    private Collection $authorizations;

    /** @var Collection<int, TeamAuthorization> */
    #[ORM\OneToMany(mappedBy: 'organization', targetEntity: TeamAuthorization::class)]
    private Collection $teamAuthorizations;

    /** @var string[] */
    #[ORM\Column(options: ['default' => '[]'])]
    #[Assert\All([
        new AppAssert\OrganizationDomain(
            messageDuplicated: new TranslatableMessage('organization.domains.already_used', [], 'errors'),
            messageInvalid: new TranslatableMessage('organization.domains.invalid', [], 'errors'),
        ),
    ])]
    private array $domains = [];

    /** @var Collection<int, User> */
    #[ORM\ManyToMany(targetEntity: User::class)]
    private Collection $observers;

    public function __construct()
    {
        $this->name = '';
        $this->tickets = new ArrayCollection();
        $this->authorizations = new ArrayCollection();
        $this->teamAuthorizations = new ArrayCollection();
        $this->observers = new ArrayCollection();
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

    /**
     * @return Collection<int, Ticket>
     */
    public function getTickets(): Collection
    {
        return $this->tickets;
    }

    /**
     * @return Collection<int, Authorization>
     */
    public function getAuthorizations(): Collection
    {
        return $this->authorizations;
    }

    /**
     * @return Collection<int, TeamAuthorization>
     */
    public function getTeamAuthorizations(): Collection
    {
        return $this->teamAuthorizations;
    }

    /**
     * @return string[]
     **/
    public function getDomains(): array
    {
        return array_map(function ($domain): string {
            if ($domain === '*') {
                return '*';
            } else {
                return Utils\Url::domainToUtf8($domain);
            }
        }, $this->domains);
    }

    /**
     * @param string[] $domains
     **/
    public function setDomains(array $domains): static
    {
        $this->domains = array_map(function ($domain): string {
            if ($domain === '*') {
                return '*';
            } else {
                return Utils\Url::sanitizeDomain($domain);
            }
        }, $domains);

        return $this;
    }

    public function normalizeDomains(): static
    {
        $domains = $this->domains;

        // Make sure the list doesn't contain empty or duplicated values.
        $domains = array_filter($domains);
        $domains = array_unique($domains);

        // Make sure to reset the array keys. Otherwise, we could store
        // something like `{"2": "example.com"}`. This would break the
        // findOneByDomain / JSON_CONTAINS method.
        // This MUST be done outside of the setDomains. Indeed, otherwise we
        // would break the references in the form in case of errors.
        $domains = array_values($domains);

        $this->domains = $domains;

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getObservers(): Collection
    {
        return $this->observers;
    }

    public function hasObserver(User $observer): bool
    {
        return $this->observers->contains($observer);
    }

    public function addObserver(User $observer): static
    {
        if (!$this->observers->contains($observer)) {
            $this->observers->add($observer);
        }

        return $this;
    }

    public function removeObserver(User $observer): static
    {
        $this->observers->removeElement($observer);

        return $this;
    }
}
