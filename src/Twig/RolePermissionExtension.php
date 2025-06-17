<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Twig;

use Twig\Attribute\AsTwigFilter;

class RolePermissionExtension
{
    #[AsTwigFilter('rolePermissionToLabel')]
    public function rolePermissionToLabel(string $permission): string
    {
        $label = str_replace(':', '.', $permission);
        $label = "roles.permissions.{$label}";
        return $label;
    }
}
