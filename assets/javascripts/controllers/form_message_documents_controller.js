// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static get targets () {
        return ['documents', 'documentTemplate'];
    }

    addDocument (event) {
        const doc = event.detail;

        const docNode = this.documentTemplateTarget.content.cloneNode(true).children[0];
        docNode.setAttribute('data-type', doc.type);

        const nameNode = docNode.querySelector('[data-target="document-name"]');
        nameNode.innerHTML = doc.name;
        const linkNode = docNode.querySelector('[data-target="document-link"]');
        linkNode.href = doc.urlShow;
        const deleteFormNode = docNode.querySelector('[data-target="document-delete-form"]');
        deleteFormNode.action = doc.urlDelete;

        this.documentsTarget.appendChild(docNode);

        const hiddenInputNode = document.createElement('input');
        hiddenInputNode.type = 'hidden';
        hiddenInputNode.name = 'messageDocumentUids[]';
        hiddenInputNode.value = doc.uid;
        this.element.appendChild(hiddenInputNode);
    }

    removeDocument (event) {
        // Catch the submit event and send it asynchronously to the server.
        event.preventDefault();

        const form = event.target;
        const docNode = form.closest('[data-target="document"]');
        const linkNode = docNode.querySelector('[data-target="document-link"]');
        const urlShow = linkNode.href;

        const data = new FormData(form);
        fetch(form.action, {
            method: 'POST',
            body: data,
        }).then((response) => {
            // Remove the document node from the list.
            docNode.remove();

            return response.json();
        }).then((json) => {
            // Remove the document from the hidden inputs of the form
            const inputSelector = 'input[name="messageDocumentUids[]"][value="' + json.uid + '"]';
            const input = this.element.querySelector(inputSelector);
            if (input) {
                input.remove();
            }

            // Remove the image if it was present in an active TinyMCE editor.
            if (window.tinymce && window.tinymce.activeEditor) {
                const selector = 'img[src="' + urlShow + '"]';
                const images = window.tinymce.activeEditor.dom.select(selector);
                window.tinymce.activeEditor.dom.remove(images);
            }
        });
    }
}
