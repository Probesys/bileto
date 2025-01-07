// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static get targets () {
        return ['buttonLeft', 'buttonRight', 'menu'];
    }

    connect () {
        this.refreshButtons();
        this.menuTarget.addEventListener('scroll', this.refreshButtons.bind(this));
    }

    scrollLeft () {
        this.menuTarget.scrollBy({
            top: 0,
            left: Math.round(-1 * this.menuTarget.clientWidth / 2),
            behavior: 'smooth',
        });
    }

    scrollRight () {
        this.menuTarget.scrollBy({
            top: 0,
            left: Math.round(this.menuTarget.clientWidth / 2),
            behavior: 'smooth',
        });
    }

    refreshButtons () {
        if (this.menuTarget.clientWidth >= this.menuTarget.scrollWidth) {
            this.buttonLeftTarget.style.display = 'none';
            this.buttonRightTarget.style.display = 'none';
            return;
        }

        this.buttonLeftTarget.style.display = 'inline-block';
        this.buttonRightTarget.style.display = 'inline-block';

        this.buttonLeftTarget.disabled = false;
        this.buttonRightTarget.disabled = false;

        if (this.menuTarget.scrollLeft === 0) {
            this.buttonLeftTarget.disabled = true;
            this.buttonRightTarget.focus();
        }

        if (this.menuTarget.scrollLeft === this.menuTarget.scrollLeftMax) {
            this.buttonRightTarget.disabled = true;
            this.buttonLeftTarget.focus();
        }
    }
};
