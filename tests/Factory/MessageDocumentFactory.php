<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Factory;

use App\Entity\MessageDocument;
use App\Utils\Random;
use Symfony\Component\Mime\MimeTypes;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<MessageDocument>
 */
final class MessageDocumentFactory extends PersistentProxyObjectFactory
{
    /**
     * @return mixed[]
     */
    protected function defaults(): array
    {
        $hash = self::faker()->sha256();
        $mimetype = self::faker()->randomElement(array_keys(MessageDocument::ACCEPTED_MIMETYPES));
        $mimesubtype = self::faker()->randomElement(MessageDocument::ACCEPTED_MIMETYPES[$mimetype]);
        $mimetype = "{$mimetype}/{$mimesubtype}";
        $extension = MimeTypes::getDefault()->getExtensions($mimetype)[0] ?? 'txt';
        return [
            'uid' => Random::hex(20),
            'name' => self::faker()->words(3, true),
            'filename' => $hash . '.' . $extension,
            'mimetype' => $mimetype,
            'hash' => 'sha256:' . $hash,
        ];
    }

    protected function initialize(): static
    {
        return $this->instantiateWith((Instantiator::withConstructor())->alwaysForce());
    }

    public static function class(): string
    {
        return MessageDocument::class;
    }
}
