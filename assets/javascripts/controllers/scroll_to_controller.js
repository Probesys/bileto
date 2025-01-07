// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static get values () {
        return {
            target: String,
        };
    }

    connect () {
        if ('visible' in this.element.dataset) {
            const intersectionObserver = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    entry.target.dataset.visible = !entry.isIntersecting;
                });
            });
            intersectionObserver.observe(this.element);
        }
    }

    scroll () {
        const target = document.querySelector(this.targetValue);
        if (target) {
            const mediaQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
            target.scrollIntoView({ behavior: mediaQuery.matches ? 'auto' : 'smooth' });
        }
    }
}
