<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests;

use App\Service;
use App\Utils;
use PHPUnit\Framework\Attributes\After;

trait MessageDocumentStorageHelper
{
    #[After]
    public function clearDocumentStorage(): void
    {
        /** @var Service\MessageDocumentStorage */
        $messageDocumentStorage = static::getContainer()->get(Service\MessageDocumentStorage::class);

        $directory = $messageDocumentStorage->uploadsDirectory;

        if (is_dir($directory)) {
            Utils\FSHelper::recursiveUnlink($directory);
        }

        self::ensureKernelShutdown();
    }
}
