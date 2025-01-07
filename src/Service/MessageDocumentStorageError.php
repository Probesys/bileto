<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service;

class MessageDocumentStorageError extends \RuntimeException
{
    public const REJECTED_MIMETYPE = 1;
    public const UNREADABLE_FILE = 2;
    public const IMMOVABLE_FILE = 3;

    public static function rejectedMimetype(string $mimetype): self
    {
        return new self(
            "{$mimetype} is not a supported mimetype",
            self::REJECTED_MIMETYPE,
        );
    }

    public static function unreadableFile(string $pathname): self
    {
        return new self(
            "The file {$pathname} cannot be read",
            self::UNREADABLE_FILE,
        );
    }

    public static function immovableFile(string $pathname, string $destination, string $error): self
    {
        return new self(
            "Cannot move {$pathname} to {$destination}: {$error}",
            self::IMMOVABLE_FILE,
        );
    }
}
