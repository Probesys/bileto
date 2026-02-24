// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

import { FOCUSABLE_ELEMENTS } from '../query_selectors.js';

export default class extends Controller {
    static targets = ['container', 'prototype']

    static values = {
        index: Number,
    }

    connect () {
        this.refreshLabels();
    }

    addItem () {
        const element = this.prototypeTarget.content.firstElementChild.cloneNode(true);
        element.innerHTML = element.innerHTML.replace(/__name__/g, this.indexValue);

        this.containerTarget.appendChild(element);

        this.indexValue++;

        this.refreshLabels();

        const focusableElements = Array.from(element.querySelectorAll(FOCUSABLE_ELEMENTS));
        if (focusableElements.length >= 1) {
            focusableElements[0].focus();
        }
    }

    removeItem (event) {
        const target = event.target;
        const element = target.closest('[data-form-collection-target="item"]');

        element.remove();

        this.refreshLabels();
    }

    refreshLabels () {
        const labels = this.containerTarget.querySelectorAll('legend');

        labels.forEach((labelNode, index) => {
            let labelPattern = labelNode.dataset.labelPattern;

            if (!labelPattern) {
                // First time we refresh the labels, we save the content of
                // labels as patterns.
                labelPattern = labelNode.innerHTML;
                labelNode.dataset.labelPattern = labelPattern;
            }

            // Update the labels with the correct number.
            labelNode.innerHTML = labelPattern.replace(/__number__/, index + 1);
        });
    }
}
