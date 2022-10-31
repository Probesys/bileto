// This file is part of Bileto.
// Copyright 2022 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect () {
        const summaryElement = this.element.querySelector('summary');
        if (summaryElement) {
            summaryElement.setAttribute('aria-haspopup', 'menu');
            summaryElement.setAttribute('aria-expanded', this.element.open);
        }
    }

    update (event) {
        const summaryElement = this.element.querySelector('summary');
        if (summaryElement) {
            summaryElement.setAttribute('aria-expanded', this.element.open);
        }
    }

    closeOnClickOutside (event) {
        if (this.element.contains(event.target)) {
            // The user clicked on an element inside the popup menu.
            return;
        }

        if (!this.element.open) {
            return;
        }

        this.element.open = false;
    }

    closeOnEscape (event) {
        if (event.key !== 'Escape') {
            return;
        }

        if (!this.element.open) {
            return;
        }

        this.element.open = false;

        const summaryElement = this.element.querySelector('summary');
        if (summaryElement) {
            summaryElement.focus();
        }
    }
};
