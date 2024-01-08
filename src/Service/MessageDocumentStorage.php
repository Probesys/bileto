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
        private string $uploadsDirectory,
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
    public function store(File $file, string $name): MessageDocument
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

        $pathname = "{$this->uploadsDirectory}/{$messageDocument->getPathname()}";
        if (!file_exists($pathname)) {
            $directory = "{$this->uploadsDirectory}/{$messageDocument->getFilepath()}";

            try {
                $file->move($directory, $messageDocument->getFilename());
            } catch (FileException $e) {
                throw MessageDocumentStorageError::immovableFile($pathname, $directory, $e->getMessage());
            }
        }

        return $messageDocument;
    }

    /**
     * Return whether the file exists or not.
     */
    public function exists(MessageDocument $messageDocument): bool
    {
        $pathname = "{$this->uploadsDirectory}/{$messageDocument->getPathname()}";
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
        $pathname = "{$this->uploadsDirectory}/{$messageDocument->getPathname()}";
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
        $pathname = "{$this->uploadsDirectory}/{$messageDocument->getPathname()}";
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
        $pathname = "{$this->uploadsDirectory}/{$messageDocument->getPathname()}";
        return @unlink($pathname);
    }
}
