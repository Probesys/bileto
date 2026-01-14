// This file is part of Bileto.
// Copyright 2020 - 2022 Marien Fressinaud (flusio)
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static get values () {
        return {
            href: String,
        };
    }

    connect () {
        this.element.setAttribute('aria-haspopup', 'dialog');
        this.element.setAttribute('aria-controls', 'modal');
    }

    fetch (event) {
        event.preventDefault();

        const modal = document.getElementById('modal');
        const openModalEvent = new CustomEvent('open-modal', {
            detail: {
                target: this.element,
                href: this.hrefValue,
            },
        });
        modal.dispatchEvent(openModalEvent);
    }
};
