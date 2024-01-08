<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Validator;

use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;

#[\Attribute]
class TreeDepth extends Constraint
{
    public string $message = 'The tree "{{ tree }}" is too deep (max is {{ max }} of depth).';

    public int $max = 2;

    #[HasNamedArguments]
    public function __construct(string $message, int $max, array $groups = null, mixed $payload = null)
    {
        parent::__construct([], $groups, $payload);
        $this->message = $message;
        $this->max = $max;
    }
}
