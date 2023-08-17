<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Factory;

use App\Entity\MessageDocument;
use App\Repository\MessageDocumentRepository;
use App\Security\Encryptor;
use App\Utils\Random;
use Symfony\Component\Mime\MimeTypes;
use Zenstruck\Foundry\RepositoryProxy;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;

/**
 * @extends ModelFactory<MessageDocument>
 *
 * @method static MessageDocument|Proxy createOne(array $attributes = [])
 * @method static MessageDocument[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static MessageDocument[]|Proxy[] createSequence(array|callable $sequence)
 * @method static MessageDocument|Proxy find(object|array|mixed $criteria)
 * @method static MessageDocument|Proxy findOrCreate(array $attributes)
 * @method static MessageDocument|Proxy first(string $sortedField = 'id')
 * @method static MessageDocument|Proxy last(string $sortedField = 'id')
 * @method static MessageDocument|Proxy random(array $attributes = [])
 * @method static MessageDocument|Proxy randomOrCreate(array $attributes = [])
 * @method static MessageDocument[]|Proxy[] all()
 * @method static MessageDocument[]|Proxy[] findBy(array $attributes)
 * @method static MessageDocument[]|Proxy[] randomSet(int $number, array $attributes = [])
 * @method static MessageDocument[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static MessageDocumentRepository|RepositoryProxy repository()
 * @method MessageDocument|Proxy create(array|callable $attributes = [])
 *
 * @phpstan-method static MessageDocument&Proxy createOne(array $attributes = [])
 * @phpstan-method static MessageDocument[]&Proxy[] createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static MessageDocument[]&Proxy[] createSequence(array|callable $sequence)
 * @phpstan-method static MessageDocument&Proxy find(object|array|mixed $criteria)
 * @phpstan-method static MessageDocument&Proxy findOrCreate(array $attributes)
 * @phpstan-method static MessageDocument&Proxy first(string $sortedField = 'id')
 * @phpstan-method static MessageDocument&Proxy last(string $sortedField = 'id')
 * @phpstan-method static MessageDocument&Proxy random(array $attributes = [])
 * @phpstan-method static MessageDocument&Proxy randomOrCreate(array $attributes = [])
 * @phpstan-method static MessageDocument[]&Proxy[] all()
 * @phpstan-method static MessageDocument[]&Proxy[] findBy(array $attributes)
 * @phpstan-method static MessageDocument[]&Proxy[] randomSet(int $number, array $attributes = [])
 * @phpstan-method static MessageDocument[]&Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method MessageDocument&Proxy create(array|callable $attributes = [])
 */
final class MessageDocumentFactory extends ModelFactory
{
    /**
     * @return mixed[]
     */
    protected function getDefaults(): array
    {
        $hash = self::faker()->sha256();
        $mimetype = self::faker()->randomElement(MessageDocument::ACCEPTED_MIMETYPES);
        $mimesubtype = self::faker()->randomElement(MessageDocument::ACCEPTED_MIMETYPES[$mimetype]);
        $mimetype = "{$mimetype}/{$mimesubtype}";
        $extension = MimeTypes::getDefault()->getExtensions($mimetype)[0];
        return [
            'uid' => Random::hex(20),
            'name' => self::faker()->words(3, true),
            'filename' => $hash . '.' . $extension,
            'mimetype' => $mimetype,
            'hash' => 'sha256:' . $hash,
        ];
    }

    protected static function getClass(): string
    {
        return MessageDocument::class;
    }
}
