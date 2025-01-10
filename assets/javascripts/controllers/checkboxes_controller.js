// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static get targets () {
        return ['control'];
    }

    connect () {
        this.refreshControls();
    }

    execute (event) {
        const target = event.target;

        let action, controlledNodes;

        try {
            [action, controlledNodes] = this.getActionAndControlledNodes(target);
        } catch (error) {
            console.error(error);
            return;
        }

        if (action === 'check') {
            this.setCheck(controlledNodes, true);
        } else if (action === 'uncheck') {
            this.setCheck(controlledNodes, false);
        } else if (action === 'switch') {
            this.setCheck(controlledNodes, target.checked);
        } else if (action === 'switchDisabled') {
            this.setDisabled(controlledNodes, target.checked);
        }
    }

    setCheck (checkboxes, value) {
        checkboxes.forEach((checkbox) => {
            if (!checkbox.disabled) {
                checkbox.checked = value;
            }
        });
    }

    setDisabled (controlledNodes, value) {
        controlledNodes.forEach((node) => {
            node.disabled = value;

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
            // It is especially useful to refresh the "assignee" multiselect
            // select so it can refresh the list.
            const event = new Event('change');
            node.dispatchEvent(event);
        });
    }

    refreshControls () {
        this.controlTargets.forEach((node) => {
            let action, controlledNodes;

            try {
                [action, controlledNodes] = this.getActionAndControlledNodes(node);
            } catch (error) {
                console.error(error);
                return;
            }

            if (action === 'switch' && node.checked) {
                this.setCheck(controlledNodes, node.checked);
            } else if (action === 'switchDisabled') {
                this.setDisabled(controlledNodes, node.checked);
            }
        });
    }

    getActionAndControlledNodes (node) {
        const control = node.dataset.checkboxesControl;

        if (!control) {
            throw new Error('Node has no data-checkboxes-control');
        }

        const indexHash = control.lastIndexOf('#');

        if (indexHash === -1) {
            throw new Error('Node data-checkboxes-control must contain a hash');
        }

        const selector = control.substring(0, indexHash);

        if (!selector) {
            throw new Error('Node data-checkboxes-control must contain a non-empty selector');
        }

        const action = control.substring(indexHash + 1);

        const validActions = ['check', 'uncheck', 'switch', 'switchDisabled'];
        if (!validActions.includes(action)) {
            throw new Error('Node data-checkboxes-control must contain a valid action');
        }

        const controlledNodes = this.element.querySelectorAll(selector);

        return [action, controlledNodes];
    }
}
