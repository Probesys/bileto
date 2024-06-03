// This file is part of Bileto.
// Copyright 2022-2024 Probesys
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
        if (this.buttonTarget.ariaExpanded === 'true') {
            this.buttonTarget.ariaExpanded = 'false';
        } else {
            this.buttonTarget.ariaExpanded = 'true';
        }
    }

    close() {
        this.buttonTarget.ariaExpanded = 'false';
        this.buttonTarget.focus();
    }
}
