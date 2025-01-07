// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static get targets () {
        return ['list', 'input', 'template', 'data'];
    }

    static get values () {
        return {
            index: Number,
            nameTemplate: String,
        };
    }

    connect () {
        this.refresh();
    }

    /**
     * Reset the list of buttons and recreate them based on the source of truth
     * (i.e. dataTargets).
     *
     * This method is called each time that the data change (added or removed).
     */
    refresh () {
        this.listTarget.innerHTML = '';

        this.dataTargets.forEach((dataNode) => {
            const buttonNode = this.buttonNode(dataNode);
            this.listTarget.appendChild(buttonNode);
        });
    }

    /**
     * Handle special keystroke in the input.
     *
     * Typing a `,`, a space, enter or tab validate the current typed element
     * and add it to the list.
     *
     * Typing backspace in an empty input remove the last element from the list.
     */
    handleInput (event) {
        const value = this.inputTarget.value;

        if (event.key === ',' || event.key === ' ' || event.key === 'Enter' || event.key === 'Tab') {
            this.addCurrentValue();
        } else if (event.key === 'Backspace' && !value) {
            this.removeLastData();
        }

        if (event.key === ',' || event.key === ' ') {
            event.preventDefault();
        }
    }

    /**
     * Add the value actually typed in the input.
     *
     * A new node (hidden input) is added to the data if the input is not empty.
     * Then, the input is empty, and the buttons list is refreshed.
     */
    addCurrentValue () {
        const value = this.inputTarget.value;

        if (!value) {
            return;
        }

        const name = this.nameTemplateValue.replace(/__name__/g, this.indexValue);

        const dataNode = document.createElement('input');
        dataNode.setAttribute('type', 'hidden');
        dataNode.setAttribute('name', name);
        dataNode.setAttribute('value', value);
        dataNode.setAttribute('data-input-texts-target', 'data');
        this.element.appendChild(dataNode);

        this.inputTarget.value = '';
        this.indexValue += 1;

        this.refresh();
    }

    /**
     * Remove the clicked element.
     */
    remove (event) {
        const currentButton = event.currentTarget;
        this.removeDataByButton(currentButton);
    }

    /**
     * Remove the element that has the focus when typing backspace or delete.
     */
    removeCurrent (event) {
        if (event.key !== 'Backspace' && event.key !== 'Delete') {
            return;
        }

        const currentButton = document.activeElement;

        if (!currentButton) {
            return;
        }

        this.removeDataByButton(currentButton);
    }

    /**
     * Remove the data corresponding to the given button node.
     */
    removeDataByButton (buttonNode) {
        // The sibling will be used to give the focus at the end of the function.
        const sibling = buttonNode.nextElementSibling;

        // Find the data node that corresponds to the given button. They must
        // have the same value/data-value attributes.
        const value = buttonNode.getAttribute('data-value');
        const dataNode = this.dataTargets.find((node) => node.value === value);
        if (dataNode) {
            // We simply remove the node from the DOM to remove the data.
            dataNode.remove();
        }

        this.refresh();

        if (sibling) {
            // As the list of buttons is reset (i.e. innerHTML is set to empty
            // string), the initial "sibling" node doesn't exist anymore in the
            // DOM. So we need to find the button that has the same value as
            // the old one.
            const siblingValue = sibling.getAttribute('data-value');
            const actualSibling = this.listTarget.querySelector(`button[data-value="${siblingValue}"]`);
            if (actualSibling) {
                actualSibling.focus();
            }
        } else {
            // If there are no sibling, then give the focus to the input.
            this.inputTarget.focus();
        }
    }

    /**
     * Remove the last data node. This is called when typing backspace in an
     * empty input.
     */
    removeLastData () {
        if (this.dataTargets.length === 0) {
            return;
        }

        this.dataTargets.at(-1).remove();

        this.refresh();
        this.inputTarget.focus();
    }

    /**
     * Return a button node used to display the actual data.
     */
    buttonNode (dataNode) {
        const buttonNode = this.templateTarget.content.firstElementChild.cloneNode(true);

        buttonNode.setAttribute('data-value', dataNode.value);
        buttonNode.querySelector('[data-target="value"]').textContent = dataNode.value;

        const ariaInvalid = dataNode.getAttribute('aria-invalid');
        if (ariaInvalid) {
            buttonNode.setAttribute('aria-invalid', ariaInvalid);
        }

        const ariaErrorMessage = dataNode.getAttribute('aria-errormessage');
        if (ariaErrorMessage) {
            buttonNode.setAttribute('aria-errormessage', ariaErrorMessage);
        }

        return buttonNode;
    }

    /**
     * Give the focus on the input when we click on the root element.
     *
     * It avoids to give the focus to the input if we click on one of the
     * button (as the focus must be given to the sibling button).
     */
    focusInput (event) {
        if (event.target === this.element) {
            this.inputTarget.focus();
        }
    }
}
