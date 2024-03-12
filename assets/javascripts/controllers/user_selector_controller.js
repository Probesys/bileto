// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static get targets () {
        return [
            'data',
            'sectionSelect',
            'sectionInput',
            'select',
            'input',
            'showInputButton',
            'showSelectButton',
        ];
    }

    connect () {
        if (this.selectTarget.options.length === 0) {
            // The <select> is empty, hide it and prevent to show it
            this.sectionSelectTarget.hidden = true;
            this.showSelectButtonTarget.style.display = 'none';
            this.inputTarget.value = this.dataTarget.value;
        } else if (!this.dataIsEmpty() && !this.dataIsInSelect()) {
            // A default value is set and is not in the select: we need to show
            // the <input> and set the input value to the value of the data
            // field.
            this.sectionSelectTarget.hidden = true;
            this.inputTarget.value = this.dataTarget.value;
        } else {
            // Display the <select> and make sure to set the data value to the
            // selected element.
            this.sectionInputTarget.hidden = true;
            this.setDataFromVisibleSection();
        }
    }

    setData (event) {
        this.dataTarget.value = event.target.value;
    }

    setDataFromVisibleSection () {
        if (this.sectionSelectTarget.hidden) {
            this.dataTarget.value = this.inputTarget.value;
        } else {
            this.dataTarget.value = this.selectTarget.value;
        }
    }

    dataIsEmpty () {
        return this.dataTarget.value === '';
    }

    dataIsInSelect () {
        const data = this.dataTarget.value;

        const options = Array.from(this.selectTarget.options);
        return options.some((option) => {
            return option.value === data;
        });
    }

    showInput () {
        this.sectionSelectTarget.hidden = true;
        this.sectionInputTarget.hidden = false;

        this.inputTarget.focus();

        this.setDataFromVisibleSection();
    }

    showSelect () {
        this.sectionSelectTarget.hidden = false;
        this.sectionInputTarget.hidden = true;

        this.selectTarget.focus();

        this.setDataFromVisibleSection();
    }
}
