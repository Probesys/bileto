// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static get targets () {
        return ['startAt', 'endAt'];
    }

    connect () {
        this.refreshMinEndAt();
    }

    /**
     * Set the endAt input value to the end of the year of the startAt value.
     */
    updateEndAt () {
        const startAtDate = new Date(this.startAtTarget.value);
        const endAtDate = new Date(this.endAtTarget.value);

        if (isNaN(startAtDate)) {
            return;
        }

        if (isNaN(endAtDate) || startAtDate >= endAtDate) {
            // Add 1 day as it may set an invalid date if startAtDate is 31th
            // December.
            startAtDate.setDate(startAtDate.getDate() + 1);

            const startAtYear = startAtDate.getFullYear().toString();
            this.endAtTarget.value = startAtYear + '-12-31';
        }

        this.refreshMinEndAt();
    }

    /**
     * Set the min value of endAt input to the startAt date + 1 day.
     */
    refreshMinEndAt () {
        const startAtDate = new Date(this.startAtTarget.value);

        if (isNaN(startAtDate)) {
            return;
        }

        startAtDate.setDate(startAtDate.getDate() + 1);

        const startAtYear = startAtDate.getFullYear().toString();
        const startAtMonth = (startAtDate.getMonth() + 1).toString().padStart(2, '0');
        const startAtDay = startAtDate.getDate().toString().padStart(2, '0');
        this.endAtTarget.min = startAtYear + '-' + startAtMonth + '-' + startAtDay;
    }
}
