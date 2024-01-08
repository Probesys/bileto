<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Utils;

class Random
{
    public static function hex(int $length): string
    {
        if ($length < 1) {
            throw new \InvalidArgumentException('Length must be a positive integer');
        }

        $string = '';
        $alphabet = '0123456789abcdef';
        $alphamax = strlen($alphabet) - 1;

        for ($i = 0; $i < $length; ++$i) {
            $string .= $alphabet[random_int(0, $alphamax)];
        }

        return $string;
    }
}
