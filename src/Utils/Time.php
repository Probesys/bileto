<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Utils;

class Time
{
    private static ?\DateTimeImmutable $freezedNow = null;

    public static function now(): \DateTimeImmutable
    {
        if (self::$freezedNow) {
            return self::$freezedNow;
        } else {
            return new \DateTimeImmutable('now');
        }
    }

    /**
     * @see https://www.php.net/manual/datetime.modify.php
     * @see https://www.php.net/manual/datetime.formats.relative.php
     */
    public static function relative(string $modifier): \DateTimeImmutable
    {
        return self::now()->modify($modifier);
    }

    /**
     * Return a datetime from the future.
     *
     * @see https://www.php.net/manual/en/datetime.formats.relative.php
     */
    public static function fromNow(int $number, string $unit): \DateTimeImmutable
    {
        return self::relative("+{$number} {$unit}");
    }

    /**
     * Return a datetime from the past.
     *
     * @see https://www.php.net/manual/en/datetime.formats.relative.php
     */
    public static function ago(int $number, string $unit): \DateTimeImmutable
    {
        return self::relative("-{$number} {$unit}");
    }

    public static function freeze(\DateTimeImmutable $datetime): void
    {
        self::$freezedNow = $datetime;
    }

    public static function unfreeze(): void
    {
        self::$freezedNow = null;
    }
}
