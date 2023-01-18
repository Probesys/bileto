<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service;

use App\Utils\Locales;
use Symfony\Component\HttpFoundation\RequestStack;

class LocaleSorter
{
    private RequestStack $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    protected function getLocale(): string
    {
        $currentRequest = $this->requestStack->getCurrentRequest();
        if ($currentRequest) {
            return $currentRequest->getLocale();
        } else {
            return Locales::DEFAULT_LOCALE;
        }
    }
}
