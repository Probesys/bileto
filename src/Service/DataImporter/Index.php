<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service\DataImporter;

/**
 * @template T of mixed
 */
class Index
{
    /** @var array<string, T> */
    private array $index = [];

    /** @var array<string, string> */
    private array $uniqueIndex = [];

    /**
     * @return array<string, T>
     */
    public function list(): array
    {
        return $this->index;
    }

    /**
     * @return ?T
     */
    public function get(string $id): mixed
    {
        return $this->index[$id] ?? null;
    }

    /**
     * @return ?T
     */
    public function getByKey(string $uniqueKey): mixed
    {
        $id = $this->uniqueIndex[$uniqueKey] ?? null;

        if ($id === null) {
            return null;
        }

        return $this->get($id);
    }

    public function has(string $id): bool
    {
        return isset($this->index[$id]);
    }

    public function count(): int
    {
        return count($this->index);
    }

    /**
     * @param T $object
     */
    public function add(string $id, mixed $object, ?string $uniqueKey = null): void
    {
        if (isset($this->index[$id])) {
            throw new IndexError('id is duplicated');
        }

        $this->index[$id] = $object;

        if ($uniqueKey) {
            $this->addUniqueAlias($id, $uniqueKey);
        }
    }

    public function addUniqueAlias(string $id, string $uniqueKey): void
    {
        if (isset($this->uniqueIndex[$uniqueKey])) {
            $duplicatedId = $this->uniqueIndex[$uniqueKey];
            throw new IndexError("duplicates id {$duplicatedId}");
        }

        $this->uniqueIndex[$uniqueKey] = $id;
    }

    /**
     * @param T $object
     */
    public function refreshUnique(mixed $object, string $uniqueKey): void
    {
        if (isset($this->uniqueIndex[$uniqueKey])) {
            $id = $this->uniqueIndex[$uniqueKey];
            $this->index[$id] = $object;
        }
    }
}
