// This file is part of Bileto.
// Copyright 2022 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static get targets () {
        return ['solutionCheckbox', 'statusSelect'];
    }

    connect () {
        this.updateStatus();
    }

    updateStatus () {
        const isSolution = this.solutionCheckboxTarget.checked;

        if (isSolution) {
            this.statusSelectTarget.value = 'resolved';
            for (const option of this.statusSelectTarget.options) {
                option.disabled = option.value !== 'resolved';
            }
        } else {
            for (const option of this.statusSelectTarget.options) {
                option.disabled = false;
            }
        }
    }
}
