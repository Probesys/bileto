// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static get targets () {
        return ['data', 'select', 'list', 'template'];
    }

    connect () {
        this.refresh();
    }

    refresh () {
        this.refreshList();
        this.refreshSelect();
    }

    refreshList () {
        this.listTarget.innerHTML = '';

        for (const option of this.dataTarget.selectedOptions) {
            const node = this.itemNode(option.value, option.text);
            this.listTarget.appendChild(node);
        }
    }

    refreshSelect () {
        // Reset the options of the select.
        this.selectTarget.innerHTML = '';

        // Add a placeholder if one is passed via the dataset.
        const placeholder = this.selectTarget.dataset.placeholder;
        if (placeholder) {
            const selectOption = document.createElement('option');
            selectOption.value = '';
            selectOption.text = placeholder;
            selectOption.selected = true;
            selectOption.disabled = true;
            this.selectTarget.add(selectOption);
        } else {
            console.warn('You should pass a data-placeholder attribute to the multiselect widget.', this.element);
        }

        // Read options that have not been selected yet, and add them to the
        // select.
        const optionsNoGroup = this.dataTarget.querySelectorAll('select > option');
        for (const option of optionsNoGroup) {
            if (!option.selected) {
                const newOption = document.createElement('option');
                newOption.value = option.value;
                newOption.text = option.text;
                this.selectTarget.add(newOption);
            }
        }

        // Same with the options in optgroups.
        const groups = this.dataTarget.querySelectorAll('select > optgroup');
        for (const group of groups) {
            const newOptGroup = document.createElement('optgroup');
            newOptGroup.label = group.label;

            let groupIsEmpty = true;
            const groupOptions = group.querySelectorAll('optgroup > option');
            for (const option of groupOptions) {
                if (!option.selected) {
                    const newOption = document.createElement('option');
                    newOption.value = option.value;
                    newOption.text = option.text;
                    newOptGroup.append(newOption);
                    groupIsEmpty = false;
                }
            }

            if (!groupIsEmpty) {
                this.selectTarget.add(newOptGroup);
            }
        }

        if (this.selectTarget.options.length === 1) {
            // Disable the select if all actors have been selected.
            this.selectTarget.disabled = true;
        } else if (this.dataTarget.disabled) {
            // Disable the select if the initial dataTarget is disabled.
            this.selectTarget.disabled = true;
        } else {
            this.selectTarget.disabled = false;
        }
    }

    select (event) {
        const value = event.target.value;
        for (const option of this.dataTarget.options) {
            if (option.value === value) {
                option.selected = true;
                break;
            }
        }

        this.refresh();
        this.selectTarget.focus();
    }

    unselect (event) {
        const value = event.currentTarget.getAttribute('data-value');

        for (const option of this.dataTarget.selectedOptions) {
            if (option.value === value) {
                option.selected = false;
                break;
            }
        }

        this.refresh();
        this.selectTarget.focus();
    }

    itemNode (value, name) {
        const item = this.templateTarget.content.firstElementChild.cloneNode(true);

        item.setAttribute('data-value', value);
        item.querySelector('[data-target="name"]').textContent = name;

        return item;
    }
}
