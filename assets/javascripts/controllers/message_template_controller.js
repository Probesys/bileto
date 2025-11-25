// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static get targets () {
        return ['select'];
    }

    static get values () {
        return {
            templates: Array,
        };
    }

    apply () {
        const selectedUid = this.selectTarget.value;

        if (!selectedUid) {
            return;
        }

        const template = this.templatesValue.find(t => t.uid === selectedUid);

        if (!template) {
            return;
        }

        // Set the type
        const typeSelect = document.getElementById('answer_type');
        if (typeSelect) {
            const option = typeSelect.querySelector(`option[value="${template.type}"]`);
            if (option) {
                typeSelect.value = template.type;
            }
        }

        // Set the content using TinyMCE
        const contentTextarea = document.getElementById('answer_content');
        if (contentTextarea) {
            if (typeof tinymce !== 'undefined' && tinymce.get('answer_content')) {
                tinymce.get('answer_content').setContent(template.content || '');
            } else {
                contentTextarea.value = template.content || '';
            }
        }

        // Reset the select
        this.selectTarget.value = '';
    }
}
