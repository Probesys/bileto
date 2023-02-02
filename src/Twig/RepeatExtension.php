<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class RepeatExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('repeat', [$this, 'repeat']),
        ];
    }

    public function repeat(string $string, int $times): string
    {
        return str_repeat($string, $times);
    }
}
