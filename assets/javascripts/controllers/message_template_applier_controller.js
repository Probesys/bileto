// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static get values () {
        return {
            typeSelector: String,
            contentSelector: String,
        };
    }

    apply (event) {
        const selectedTemplate = event.currentTarget;

        const typeNode = document.querySelector(this.typeSelectorValue);
        if (typeNode) {
            typeNode.value = selectedTemplate.dataset.type;
        }

        const contentNode = document.querySelector(this.contentSelectorValue);
        if (contentNode) {
            const contentEditor = window.tinymce.get(contentNode.id)
            if (contentEditor) {
                const currentContent = contentEditor.getContent();
                contentEditor.setContent(currentContent + selectedTemplate.dataset.content);
            }
        }
    }
}
