// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect () {
        const colorScheme = this.colorScheme;

        let language = document.documentElement.dataset.locale;
        if (language === 'en_GB') {
            language = null; // Use the default language (en_US)
        }

        let autofocus = '';
        if (this.element.autofocus) {
            autofocus = this.element.id;
        }

        const configuration = {
            selector: '#' + this.element.id,
            skin: colorScheme === 'light' ? 'oxide' : 'oxide-dark',
            content_css: colorScheme === 'light' ? 'default' : 'dark',
            language,
            plugins: 'emoticons lists link image autoresize',
            toolbar: 'bold italic | numlist bullist | link unlink image emoticons',
            min_height: 250,
            autoresize_bottom_margin: 10,
            menubar: false,
            statusbar: false,
            contextmenu: false,
            promotion: false,
            link_assume_external_targets: true,
            link_target_list: false,
            auto_focus: autofocus,
        };

        window.tinymce.init(configuration);
    }

    disconnect () {
        window.tinymce.activeEditor.destroy();
    }

    get colorScheme () {
        let colorScheme = document.documentElement.dataset.colorScheme;
        if (colorScheme === 'auto') {
            if (window.matchMedia) {
                const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
                colorScheme = mediaQuery.matches ? 'dark' : 'light';
            } else {
                colorScheme = 'light';
            }
        }
        return colorScheme;
    }
}
