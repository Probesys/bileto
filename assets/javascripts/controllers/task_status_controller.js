// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static get values () {
        return {
            startAt: String,
            ongoingLabel: String,
        };
    }

    static get targets () {
        return ['avatar', 'badge'];
    }

    connect () {
        this._update();
        this._interval = setInterval(() => this._update(), 60000);
    }

    disconnect () {
        clearInterval(this._interval);
    }

    _update () {
        const startAt = new Date(parseInt(this.startAtValue, 10));
        const now = new Date();

        if (isNaN(startAt.getTime())) {
            return;
        }

        if (now >= startAt) {
            if (this.hasAvatarTarget) {
                this._setIcon(this.avatarTarget, 'clock-white');
            }

            if (this.hasBadgeTarget) {
                this.badgeTarget.className = 'badge badge--orange';
                this.badgeTarget.textContent = this.ongoingLabelValue;
            }

            clearInterval(this._interval);
        }
    }

    _setIcon (avatarEl, iconName) {
        try {
            const svg = avatarEl.querySelector('svg');
            const use = avatarEl.querySelector('use');

            if (!svg || !use) {
                return;
            }

            // Update class
            const currentClass = svg.getAttribute('class') || '';
            svg.setAttribute('class', currentClass.replace(/icon--\S+/, `icon--${iconName}`));

            // Update href (both namespaced and plain)
            const href = use.getAttributeNS('http://www.w3.org/1999/xlink', 'href') ||
                         use.getAttribute('href') || '';
            const newHref = href.replace(/#\S+$/, `#${iconName}`);

            if (href) {
                use.setAttributeNS('http://www.w3.org/1999/xlink', 'href', newHref);
                use.setAttribute('href', newHref);
            }
        } catch {
            // Icon swap failed silently, badge update continues
        }
    }
}
