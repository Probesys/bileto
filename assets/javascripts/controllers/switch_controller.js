// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

import { FOCUSABLE_ELEMENTS } from '../query_selectors.js';

export default class extends Controller {
    static get targets () {
        return ['panel'];
    }

    change (event) {
        this.panelTargets.forEach((panel) => {
            panel.hidden = panel.id !== event.params.for;

            if (!panel.hidden) {
                const focusableElements = Array.from(panel.querySelectorAll(FOCUSABLE_ELEMENTS));
                if (focusableElements.length > 0) {
                    focusableElements[0].focus();
                }
            }
        });
    }
}
