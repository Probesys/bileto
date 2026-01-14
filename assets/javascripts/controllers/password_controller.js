// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static get targets () {
        return ['input', 'button'];
    }

    connect () {
        this.refreshButton();
    }

    refreshButton () {
        if (this.inputTarget.type === 'password') {
            this.buttonTarget.setAttribute('aria-pressed', false);
        } else {
            this.buttonTarget.setAttribute('aria-pressed', true);
        }
    }

    toggle () {
        if (this.inputTarget.type === 'password') {
            this.inputTarget.type = 'text';
        } else {
            this.inputTarget.type = 'password';
        }

        this.refreshButton();
    }
};
