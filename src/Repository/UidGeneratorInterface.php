<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Repository;

interface UidGeneratorInterface
{
    public function generateUid(int $length = 20): string;
}
