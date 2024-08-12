<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service;

use Symfony\Component\Validator\ConstraintViolationListInterface;

class ValidationException extends \RuntimeException
{
    public function __construct(
        private ConstraintViolationListInterface $errors,
        string $message = 'The entity cannot be saved because of validation constraint(s).',
        int $code = 0,
    ) {
        parent::__construct($message, $code);
    }

    public function getErrors(): ConstraintViolationListInterface
    {
        return $this->errors;
    }
}
