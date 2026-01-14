// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static get targets () {
        return ['item'];
    }

    itemTargetConnected (item) {
        setTimeout(() => {
            item.remove();
        }, 10000);
    }

    closeItem (event) {
        const itemKey = event.params.item;
        if (itemKey < this.itemTargets.length) {
            this.itemTargets[itemKey].remove();
        }
    }
};
