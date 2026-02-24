// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static get targets () {
        return ['startAt', 'endAt'];
    }

    static get values () {
        return {
            endAtInvalidError: String,
        };
    }

    connect () {
        const startAt = this.startAtTarget.value;

        if (!startAt) {
            const newStart = new Date(Date.now() + 60 * 60 * 1000);
            newStart.setMinutes(0);
            this.startAtTarget.value = this._formatDatetimeLocal(newStart);
            this.onStartAtChange();
        }
    }

    onStartAtChange () {
        const startAt = this.startAtTarget.value;
        const endAt = this.endAtTarget.value;

        if (!startAt) {
            return;
        }

        const startDate = new Date(startAt);

        if (!endAt) {
            const newEnd = new Date(startDate.getTime() + 60 * 60 * 1000);
            this.endAtTarget.value = this._formatDatetimeLocal(newEnd);

            this._previousStartAt = startAt;

            return;
        }

        const endDate = new Date(endAt);
        const previousStart = this._previousStartAt ? new Date(this._previousStartAt) : null;

        if (previousStart) {
            const diff = endDate.getTime() - previousStart.getTime();
            const newEnd = new Date(startDate.getTime() + diff);
            this.endAtTarget.value = this._formatDatetimeLocal(newEnd);
        }

        this._previousStartAt = startAt;
        this._checkEndBeforeStart();
    }

    onEndAtChange () {
        this._checkEndBeforeStart();
    }

    _checkEndBeforeStart () {
        const startAt = this.startAtTarget.value;
        const endAt = this.endAtTarget.value;

        if (startAt && endAt && new Date(endAt) < new Date(startAt)) {
            this.endAtTarget.setCustomValidity(this.endAtInvalidErrorValue);
            this.endAtTarget.reportValidity();
        } else {
            this.endAtTarget.setCustomValidity('');
        }
    }

    _formatDatetimeLocal (date) {
        const pad = (n) => String(n).padStart(2, '0');

        const year = date.getFullYear();
        const month = pad(date.getMonth() + 1);
        const day = pad(date.getDate());
        const hours = pad(date.getHours());
        const minutes = pad(date.getMinutes());

        return `${year}-${month}-${day}T${hours}:${minutes}`;
    }
}
