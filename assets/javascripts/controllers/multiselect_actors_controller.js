// This file is part of Bileto.
// Copyright 2022-2024 Probesys
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
        // Reset the options of the select (only keep the "Select an actor"
        // option).
        const selectActorOption = this.selectTarget.item(0);
        selectActorOption.selected = true;
        this.selectTarget.innerHTML = '';
        this.selectTarget.add(selectActorOption);

        // Read options that have not been selected yet, and add them to the
        // select.
        for (const option of this.dataTarget.options) {
            if (!option.selected) {
                const newOption = document.createElement('option');
                newOption.value = option.value;
                newOption.text = option.text;
                this.selectTarget.add(newOption);
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

        item.querySelector('[data-target="name"]').textContent = name;

        const unselectButton = item.querySelector('[data-target="unselect"]');
        unselectButton.setAttribute('data-value', value);

        return item;
    }
}
