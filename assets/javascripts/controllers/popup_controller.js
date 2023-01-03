// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect () {
        const openerElement = this.element.querySelector('.popup__opener');
        if (openerElement) {
            openerElement.setAttribute('aria-haspopup', 'menu');
            openerElement.setAttribute('aria-expanded', this.element.open);
        }

        const containerElement = this.element.querySelector('.popup__container');
        if (containerElement) {
            containerElement.setAttribute('role', 'menu');
        }

        const itemsElements = this.element.querySelectorAll('.popup__item');
        itemsElements.forEach((element) => {
            element.setAttribute('role', 'menuitem');
        });

        this.element.addEventListener('keydown', this.closeOnEscape.bind(this));
    }

    update (event) {
        const openerElement = this.element.querySelector('.popup_opener');
        if (openerElement) {
            openerElement.setAttribute('aria-expanded', this.element.open);
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

        const openerElement = this.element.querySelector('.popup_opener');
        if (openerElement) {
            openerElement.focus();
        }
    }
};
