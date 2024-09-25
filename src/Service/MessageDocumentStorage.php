<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service;

use App\Entity\MessageDocument;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\File;

/**
 * This class provides methods to save and read files as MessageDocuments.
 *
 * @see docs/developers/document-upload.md
 */
class MessageDocumentStorage
{
    private const HASH_ALGO = 'sha256';

    public function __construct(
        #[Autowire(param: 'app.uploads_directory')]
        public readonly string $uploadsDirectory,
    ) {
    }

    /**
     * Store a Symfony File with the given name and return a corresponding
     * MessageDocument.
     *
     * If the file already exists in the storage, it isn't duplicated, but a
     * new MessageDocument is initialized with the same filename.
     *
     * @throws MessageDocumentStorageError
     *     If the file cannot be saved (e.g. invalid mimetype, file doesn't
     *     exist, cannot move the file to its destination).
     */
    public function store(File $file, string $name, bool $copy = false): MessageDocument
    {
        $pathname = $file->getPathname();
        $mimetype = $file->getMimeType();

        if (!MessageDocument::isMimetypeAccepted($mimetype)) {
            throw MessageDocumentStorageError::rejectedMimetype($mimetype);
        }

        $hash = @hash_file(self::HASH_ALGO, $pathname);
        if ($hash === false) {
            throw MessageDocumentStorageError::unreadableFile($pathname);
        }

        // Calculate a filename based on the hash of its content so we can
        // easily avoid to store the same file twice.
        $filename = "{$hash}.{$file->guessExtension()}";

        $messageDocument = new MessageDocument();
        $messageDocument->setName($name);
        $messageDocument->setFilename($filename);
        $messageDocument->setMimetype($mimetype);
        $messageDocument->setHash(self::HASH_ALGO, $hash);

        $pathname = $this->getPathname($messageDocument);
        if (!file_exists($pathname)) {
            $directory = "{$this->uploadsDirectory}/{$messageDocument->getFilepath()}";

            if ($copy) {
                if (!is_dir($directory)) {
                    mkdir($directory, 0755, recursive: true);
                }

                copy($file->getPathname(), "{$directory}/{$filename}");
            } else {
                try {
                    $file->move($directory, $messageDocument->getFilename());
                } catch (FileException $e) {
                    throw MessageDocumentStorageError::immovableFile($pathname, $directory, $e->getMessage());
                }
            }
        }

        return $messageDocument;
    }

    /**
     * Return whether the file exists or not.
     */
    public function exists(MessageDocument $messageDocument): bool
    {
        $pathname = $this->getPathname($messageDocument);
        return @file_exists($pathname);
    }

    /**
     * Return the size of the file related to the given MessageDocument.
     *
     * @throws MessageDocumentStorageError
     *     If the file doesn't exist.
     */
    public function size(MessageDocument $messageDocument): int
    {
        $pathname = $this->getPathname($messageDocument);
        $filesize = @filesize($pathname);

        if ($filesize === false) {
            throw MessageDocumentStorageError::unreadableFile($pathname);
        }

        return $filesize;
    }

    /**
     * Return the content of the file related to the given MessageDocument.
     *
     * @throws MessageDocumentStorageError
     *     If the file doesn't exist.
     */
    public function read(MessageDocument $messageDocument): string
    {
        $pathname = $this->getPathname($messageDocument);
        $content = @file_get_contents($pathname);

        if ($content === false) {
            throw MessageDocumentStorageError::unreadableFile($pathname);
        }

        return $content;
    }

    /**
     * Remove the file related to the given MessageDocument.
     */
    public function remove(MessageDocument $messageDocument): bool
    {
        $pathname = $this->getPathname($messageDocument);
        return @unlink($pathname);
    }

    public function getPathname(MessageDocument $messageDocument): string
    {
        return "{$this->uploadsDirectory}/{$messageDocument->getPathname()}";
    }
}
