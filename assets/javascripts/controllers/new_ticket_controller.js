// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static get targets () {
        return ['assigneeSelect', 'statusSelect'];
    }

    updateStatus () {
        const isAssigned = this.assigneeSelectTarget.value !== '';
        const status = this.statusSelectTarget.value;

        const newOption = this.statusSelectTarget.querySelector('option[value="new"]');

        if (isAssigned) {
            newOption.disabled = true;
            if (status === 'new') {
                this.statusSelectTarget.value = 'in_progress';
            }
        } else {
            newOption.disabled = false;
            this.statusSelectTarget.value = 'new';
        }
    }
}
