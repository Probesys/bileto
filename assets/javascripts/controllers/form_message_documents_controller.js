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

        this.documentsTarget.appendChild(docNode);

        const hiddenInputNode = document.createElement('input');
        hiddenInputNode.type = 'hidden';
        hiddenInputNode.name = 'messageDocumentUids[]';
        hiddenInputNode.value = doc.uid;
        this.element.appendChild(hiddenInputNode);
    }
}
