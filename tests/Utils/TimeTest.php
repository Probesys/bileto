<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Utils;

use App\Utils\Time;
use PHPUnit\Framework\TestCase;

class TimeTest extends TestCase
{
    /**
     * @beforeClass
     */
    public static function freezeTime(): void
    {
        $now = new \DateTimeImmutable('2022-11-02');
        Time::freeze($now);
    }

    /**
     * @afterClass
     */
    public static function unfreezeTime(): void
    {
        Time::unfreeze();
    }

    public function testNow(): void
    {
        $now = Time::now();

        $this->assertEquals('2022-11-02', $now->format('Y-m-d'));
    }

    public function testFromNow(): void
    {
        $tomorrow = Time::fromNow(1, 'day');

        $this->assertEquals('2022-11-03', $tomorrow->format('Y-m-d'));
    }

    public function testAgo(): void
    {
        $yesterday = Time::ago(1, 'day');

        $this->assertEquals('2022-11-01', $yesterday->format('Y-m-d'));
    }
}
