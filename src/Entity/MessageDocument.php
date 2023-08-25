<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Entity;

use App\EntityListener\EntitySetMetaListener;
use App\Repository\MessageDocumentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represent a document attached to a Message.
 *
 * @see docs/developers/document-upload.md
 */
#[ORM\Entity(repositoryClass: MessageDocumentRepository::class)]
#[ORM\EntityListeners([EntitySetMetaListener::class])]
class MessageDocument implements MetaEntityInterface, ActivityRecordableInterface
{
    use MetaEntityTrait;

    /**
     * The list of mimetypes (separated in types => subtypes arrays) that we
     * accept in Bileto. The wildcard (*) subtype means that any mimetype with
     * the related type is accepted.
     */
    public const ACCEPTED_MIMETYPES = [
        'application' => [
            'msword',
            'pdf',
            'vnd.ms-excel',
            'vnd.oasis.opendocument.spreadsheet',
            'vnd.oasis.opendocument.text',
            'vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'vnd.openxmlformats-officedocument.wordprocessingml.document',
        ],
        'image' => [
            'bmp',
            'gif',
            'jpeg',
            'png',
            'webp',
        ],
        'text' => ['*'],
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, unique: true)]
    private ?string $uid = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne]
    private ?User $updatedBy = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $filename = null;

    #[ORM\Column(length: 100)]
    private ?string $mimetype = null;

    #[ORM\Column(length: 255)]
    private ?string $hash = null;

    #[ORM\ManyToOne(inversedBy: 'messageDocuments')]
    private ?Message $message = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): static
    {
        $this->filename = $filename;

        return $this;
    }

    /**
     * Return the filepath of the MessageDocument file.
     *
     * The filepath is calculated from its filename. The first four characters
     * of the filename are grouped in 2 groups of 2 characters which represents
     * the 2 subdirectories levels.
     *
     * For instance, for the MessageDocument with the filename
     * `bd62115afd16249cff5bd9418b3c4fab3a9a254ebcf0e695cb3c14b92d7827f1.jpg`,
     * this method returns `bd/62`.
     */
    public function getFilepath(): string
    {
        $folder1 = substr($this->filename, 0, 2);
        $folder2 = substr($this->filename, 2, 2);
        return "{$folder1}/{$folder2}";
    }

    public function getPathname(): string
    {
        return "{$this->getFilepath()}/{$this->filename}";
    }

    public function getExtension(): string
    {
        return pathinfo($this->filename, PATHINFO_EXTENSION);
    }

    public function getMimetype(): ?string
    {
        return $this->mimetype;
    }

    public function setMimetype(string $mimetype): static
    {
        $this->mimetype = $mimetype;

        return $this;
    }

    /**
     * Return wether the given mimetype is accepted.
     *
     * @see self::ACCEPTED_MIMETYPES
     *
     * The mimetype is accepted if it's contained in the ACCEPTED_MIMETYPES
     * array constant, or if the related type declares a wildcard (*) subtype.
     */
    public static function isMimetypeAccepted(string $mimetype): bool
    {
        if (!str_contains($mimetype, '/')) {
            return false;
        }

        list($type, $subtype) = explode('/', $mimetype, 2);

        if (!isset(self::ACCEPTED_MIMETYPES[$type])) {
            return false;
        }

        $validSubtypes = self::ACCEPTED_MIMETYPES[$type];

        return (
            in_array('*', $validSubtypes) ||
            in_array($subtype, $validSubtypes)
        );
    }

    public function getHash(): ?string
    {
        return $this->hash;
    }

    public function setHash(string $algo, string $hash): static
    {
        $this->hash = "{$algo}:{$hash}";

        return $this;
    }

    public function getMessage(): ?Message
    {
        return $this->message;
    }

    public function setMessage(?Message $message): static
    {
        $this->message = $message;

        return $this;
    }
}
