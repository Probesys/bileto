<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Utils;

/**
 * Provide helpful methods to manipulate arrays.
 */
class ArrayHelper
{
    /**
     * Find in an array and return (if any) the element matching the callback.
     *
     * @template TElement of mixed
     *
     * @param TElement[] $array
     * @param callable(TElement): bool $callback
     *
     * @return ?TElement
     */
    public static function find(array $array, callable $callback): mixed
    {
        foreach ($array as $item) {
            if ($callback($item)) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Return whether at least one element in the array matches the provided callback.
     *
     * @template TElement of mixed
     *
     * @param TElement[] $array
     * @param callable(TElement): bool $callback
     */
    public static function any(array $array, callable $callback): bool
    {
        foreach ($array as $item) {
            if ($callback($item)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return the first element of an array.
     *
     * @template TElement of mixed
     *
     * @param TElement[] $array
     *
     * @return ?TElement
     */
    public static function first(array $array): mixed
    {
        $firstKey = array_key_first($array);
        return $array[$firstKey];
    }
}
