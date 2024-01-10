<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Twig;

use Symfony\Component\Form\FormView;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class FormExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('field_id', [$this, 'fieldId']),
            new TwigFunction('field_has_errors', [$this, 'fieldHasErrors']),
        ];
    }

    /**
     * @param FormView|string $field
     */
    public function fieldId(mixed $field, string $suffix = ''): string
    {
        if ($field instanceof FormView) {
            $id = $field->vars['id'];
        } else {
            $id = $field;
        }

        if ($suffix) {
            return $id . '-' . $suffix;
        } else {
            return $id;
        }
    }

    public function fieldHasErrors(FormView $field): bool
    {
        return count($field->vars['errors']) > 0;
    }
}
