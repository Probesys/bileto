// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

const priorityCalculationMatrix = {
    low: {
        low: 'low',
        medium: 'low',
        high: 'medium',
    },
    medium: {
        low: 'low',
        medium: 'medium',
        high: 'high',
    },
    high: {
        low: 'medium',
        medium: 'high',
        high: 'high',
    },
};

export default class extends Controller {
    static get targets () {
        return ['urgency', 'impact', 'priority'];
    }

    updatePriority () {
        const urgency = this.urgencyTarget.value;
        const impact = this.impactTarget.value;

        if (
            priorityCalculationMatrix[urgency] &&
            priorityCalculationMatrix[urgency][impact]
        ) {
            this.priorityTarget.value = priorityCalculationMatrix[urgency][impact];
        }
    }
};
