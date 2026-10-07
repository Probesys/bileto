// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';
import TomSelect from 'tom-select';

export default class extends Controller {
    static get targets () {
        return ['search', 'value'];
    }

    static get values () {
        return { url: String };
    }

    connect () {
        this.select = new TomSelect(this.searchTarget, {
            mode: 'single',
            maxItems: 1,
            valueField: 'id',
            labelField: 'name',
            searchField: 'name',
            maxOptions: 10,
            hideSelected: true,
            openOnFocus: false,
            load: (query, callback) => this.load(query, callback),
            onChange: (value) => {
                this.valueTarget.value = value;
            },
        });
        // Tom Select stops click events at its control, so the action must live there.
        this.select.control.dataset.action = 'click->contract-select#browse keydown->contract-select#browse';

        const selectedId = this.valueTarget.value;
        const selectedName = this.searchTarget.dataset.selectedName;
        if (selectedId && selectedName) {
            this.select.addOption({ id: selectedId, name: selectedName });
            this.select.setValue(selectedId, true);
        }
    }

    disconnect () {
        this.select?.destroy();
    }

    async load (query, callback) {
        try {
            const response = await fetch(`${this.urlValue}?q=${encodeURIComponent(query)}`, {
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) {
                callback();
                return;
            }

            const data = await response.json();
            if (this.select.inputValue() === query) {
                this.select.clearOptions();
                callback(data.items);
            } else {
                callback();
            }
        } catch {
            callback();
        }
    }

    browse (event) {
        if (event.type === 'keydown' && event.key !== 'ArrowDown') {
            return;
        }

        if (this.select.isOpen || this.select.inputValue()) {
            return;
        }

        // Keep the selected contract, but discard options from previous searches.
        this.select.clearOptions();
        this.select.load('');
    }

    clear () {
        this.select.clear();
        this.valueTarget.value = '';
        this.searchTarget.focus();
    }
}
