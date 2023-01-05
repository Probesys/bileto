<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Exception\ValidatorException;

class TreeDepthValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof TreeDepth) {
            throw new UnexpectedTypeException($constraint, TreeDepth::class);
        }

        if ($value === null || $value === '') {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedTypeException($value, 'string');
        }

        if (!str_starts_with($value, '/')) {
            throw new ValidatorException("Argument is expected to start with \"/\", \"{$value}\" given");
        }

        if (!str_ends_with($value, '/')) {
            throw new ValidatorException("Argument is expected to end with \"/\", \"{$value}\" given");
        }

        $depth = substr_count($value, '/');

        if ($depth > $constraint->max) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ tree }}', $value)
                ->setParameter('{{ max }}', strval($constraint->max))
                ->addViolation();
        }
    }
}
