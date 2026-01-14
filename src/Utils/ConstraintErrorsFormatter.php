<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Utils;

use Symfony\Component\Validator\ConstraintViolationListInterface;

class ConstraintErrorsFormatter
{
    /**
     * Format a list of errors returned by a Validator into a list of errors
     * displayable in the interface.
     *
     * The keys are the properties of the concerned Entity, and the values are
     * a concatenation of the corresponding error messages.
     *
     * @return array<string, string>
     */
    public static function format(ConstraintViolationListInterface $errors): array
    {
        $formattedErrors = [];
        foreach ($errors as $error) {
            $property = $error->getPropertyPath();
            if (isset($formattedErrors[$property])) {
                $formattedErrors[$property] = implode(
                    ' ',
                    [$formattedErrors[$property], $error->getMessage()],
                );
            } else {
                $formattedErrors[$property] = $error->getMessage();
            }
        }
        return $formattedErrors;
    }
}
