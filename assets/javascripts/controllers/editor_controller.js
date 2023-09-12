// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static get targets () {
        return ['tinymce', 'messageDocuments'];
    }

    newDocument (event) {
        const newDocumentEvent = new CustomEvent('new-document', { detail: event.detail });
        this.messageDocumentsTarget.dispatchEvent(newDocumentEvent);
    }

    removeDocument (event) {
        const removeDocumentEvent = new CustomEvent('remove-document', { detail: event.detail });
        this.tinymceTarget.dispatchEvent(removeDocumentEvent);
    }
}
