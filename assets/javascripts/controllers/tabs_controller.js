// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

// Credit to https://developer.mozilla.org/en-US/docs/Web/Accessibility/ARIA/Roles/tab_role
export default class extends Controller {
    static get targets () {
        return ['tablist', 'tab', 'tabpanel'];
    }

    static get values () {
        return { active: String };
    }

    connect () {
        this.tabFocus = 0;

        this.element.addEventListener('keydown', this.trapArrows.bind(this));

        this.tablistTarget.setAttribute('role', 'tablist');

        this.tabTargets.forEach((tab) => {
            tab.setAttribute('role', 'tab');
        });

        this.tabpanelTargets.forEach((tabpanel) => {
            tabpanel.setAttribute('role', 'tabpanel');
            tabpanel.setAttribute('role', 'tabpanel');
            tabpanel.setAttribute('tabindex', 0);
        });

        const selectedTab = this.tabTargets.find((tab) => {
            return tab.getAttribute('aria-selected') === 'true';
        });

        this.selectTab(selectedTab);
    }

    go (event) {
        this.selectTab(event.target);
    }

    selectTab (selectedTab) {
        this.tabTargets.forEach((tab, index) => {
            if (tab === selectedTab) {
                tab.setAttribute('aria-selected', 'true');
                tab.setAttribute('tabindex', 0);
                this.tabFocus = index;
            } else {
                tab.setAttribute('aria-selected', 'false');
                tab.setAttribute('tabindex', -1);
            }
        });

        this.tabpanelTargets.forEach((tabpanel) => {
            tabpanel.hidden = tabpanel.id !== selectedTab.getAttribute('aria-controls');
        });
    }

    trapArrows (event) {
        if (event.key !== 'ArrowRight' && event.key !== 'ArrowLeft') {
            return;
        }

        // Don't trap the key if it's not one of the tabs or tabpanels
        const tabHasFocus = this.tabTargets.find((tab) => {
            return tab === document.activeElement;
        });
        const tabpanelHasFocus = this.tabpanelTargets.find((tabpanel) => {
            return tabpanel === document.activeElement;
        });

        if (!tabHasFocus && !tabpanelHasFocus) {
            return;
        }

        this.tabTargets[this.tabFocus].setAttribute('tabindex', -1);

        if (event.key === 'ArrowRight') {
            this.tabFocus = this.tabFocus + 1;
        } else {
            this.tabFocus = this.tabFocus - 1;
        }

        if (this.tabFocus >= this.tabTargets.length) {
            this.tabFocus = 0;
        } else if (this.tabFocus < 0) {
            this.tabFocus = this.tabTargets.length - 1;
        }

        this.tabTargets[this.tabFocus].setAttribute('tabindex', 0);
        this.tabTargets[this.tabFocus].focus();
    }
}
