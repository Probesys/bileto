<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class RolePermissionExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('rolePermissionToLabel', [$this, 'rolePermissionToLabel']),
        ];
    }

    public function rolePermissionToLabel(string $permission): string
    {
        $label = str_replace(':', '.', $permission);
        $label = "roles.permissions.{$label}";
        return $label;
    }
}
