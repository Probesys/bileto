<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Utils;

class Email
{
    public static function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public static function extractDomain(string $email): string
    {
        $domain = mb_strrchr($email, '@');

        if ($domain === false) {
            return '';
        }

        return mb_substr($domain, 1);
    }
}
