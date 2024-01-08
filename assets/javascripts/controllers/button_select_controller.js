// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static get targets () {
        return ['button'];
    }

    connect () {
        const radioNode = this.element.querySelector('input[type="radio"]:checked');
        this.updateButton(radioNode);
    }

    update (event) {
        this.updateButton(event.target);
    }

    updateButton (selectedRadio) {
        let labelNode = null;
        if (selectedRadio) {
            labelNode = selectedRadio.nextElementSibling;
        }

        if (labelNode) {
            this.buttonTarget.innerHTML = labelNode.innerHTML;
        }
    }
}
