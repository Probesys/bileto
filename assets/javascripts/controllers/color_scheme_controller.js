// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect () {
        const colorScheme = document.documentElement.dataset.colorScheme;
        if (colorScheme !== 'auto') {
            return;
        }

        if (window.matchMedia) {
            const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
            if (mediaQuery.matches) {
                document.documentElement.dataset.colorScheme = 'dark';
            } else {
                document.documentElement.dataset.colorScheme = 'light';
            }

            mediaQuery.addEventListener('change', (event) => {
                document.documentElement.dataset.colorScheme = event.matches ? 'dark' : 'light';
            });
        } else {
            document.documentElement.dataset.colorScheme = 'light';
        }
    }
}
