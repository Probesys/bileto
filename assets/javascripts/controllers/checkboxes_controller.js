// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static get targets () {
        return ['control'];
    }

    connect () {
        this.refreshControls();
    }

    checkAll () {
        const checkboxes = this.element.querySelectorAll('input[type="checkbox"]:not([disabled])');

        checkboxes.forEach((checkbox) => {
            checkbox.checked = true;
        });

        this.refreshControls();
    }

    uncheckAll () {
        const checkboxes = this.element.querySelectorAll('input[type="checkbox"]:not([disabled])');

        checkboxes.forEach((checkbox) => {
            checkbox.checked = false;
        });

        this.refreshControls();
    }

    refreshControls () {
        this.controlTargets.forEach((node) => {
            this.switchDisabledForControl(node);
        });
    }

    switchDisabled (event) {
        this.switchDisabledForControl(event.target);
    }

    switchDisabledForControl (control) {
        const selector = control.dataset.checkboxesControl;
        const controlledNodes = this.element.querySelectorAll(selector);

        controlledNodes.forEach((node) => {
            node.disabled = control.checked;

            if (node.disabled) {
                // Deselect the node value(s)
                if (
                    node.nodeName === 'INPUT' &&
                    (node.type === 'checkbox' || node.type === 'radio')
                ) {
                    node.checked = false;
                } else if (node.nodeName === 'INPUT' || node.nodeName === 'TEXTAREA') {
                    node.value = '';
                } else if (node.nodeName === 'SELECT') {
                    node.selectedIndex = -1;
                }
            }

            // Send a "change" event to the node element.
            // It is especially useful to refresh the "assignee" multiselect_actors
            // select so it can refresh the list.
            const event = new Event('change');
            node.dispatchEvent(event);
        });
    }
}
