// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static get targets () {
        return ['button'];
    }

    connect () {
        this.element.addEventListener('keydown', this.trapEscape.bind(this));
    }

    trapEscape (event) {
        if (event.key === 'Escape') {
            this.close();
        }
    }

    switch () {
        if (this.buttonTarget.getAttribute('aria-expanded') === 'true') {
            this.buttonTarget.setAttribute('aria-expanded', 'false');
        } else {
            this.buttonTarget.setAttribute('aria-expanded', 'true');
        }
    }

    close() {
        this.buttonTarget.setAttribute('aria-expanded', 'false');
        this.buttonTarget.focus();
    }
}
