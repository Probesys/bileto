// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect () {
        const bottom = this.element.querySelector('#bottom');

        let lastElement = null
        if (bottom) {
            lastElement = bottom.previousElementSibling;
        } else {
            lastElement = this.element.lastChild;
        }

        if (!lastElement) {
            return;
        }

        this.element.style.setProperty('--timeline-bar-height', lastElement.offsetTop + 'px');
    }
}
