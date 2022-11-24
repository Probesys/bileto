// This file is part of Bileto.
// Copyright 2022 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static get targets () {
        return ['confidentialCheckbox', 'solutionCheckbox', 'statusSelect'];
    }

    connect () {
        this.setConfidential();
        this.updateStatus();
    }

    setConfidential () {
        const isConfidential = this.confidentialCheckboxTarget.checked;

        if (isConfidential) {
            this.solutionCheckboxTarget.checked = false;
            this.solutionCheckboxTarget.disabled = true;
        } else {
            this.solutionCheckboxTarget.disabled = false;
        }

        this.updateStatus();
    }

    updateStatus () {
        const isSolution = this.solutionCheckboxTarget.checked;

        if (isSolution) {
            this.confidentialCheckboxTarget.checked = false;
            this.confidentialCheckboxTarget.disabled = true;

            this.statusSelectTarget.value = 'resolved';

            for (const option of this.statusSelectTarget.options) {
                option.disabled = option.value !== 'resolved';
            }
        } else {
            this.confidentialCheckboxTarget.disabled = false;

            this.statusSelectTarget.value = 'in_progress';

            for (const option of this.statusSelectTarget.options) {
                option.disabled = false;
            }
        }
    }
}
