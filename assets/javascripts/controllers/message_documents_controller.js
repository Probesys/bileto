// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static get targets () {
        return ['uploadButton', 'filesSelector'];
    }

    removeDocument (event) {
        const button = event.target.closest('button');

        const url = button.getAttribute('data-url');
        const csrf = button.getAttribute('data-csrf');

        const formData = new FormData();
        formData.append('_csrf_token', csrf);

        fetch(url, {
            method: 'POST',
            body: formData,
        }).then((response) => {
            return response.json();
        }).then((json) => {
            const removeDocumentEvent = new CustomEvent('remove-document', { detail: json });
            this.element.dispatchEvent(removeDocumentEvent);

            this.reload();
        });
    }

    reload () {
        this.element.reload();
    }

    openFilesSelector () {
        this.filesSelectorTarget.click();
    }

    uploadFiles () {
        this.uploadButtonTarget.disabled = true;

        const file = this.filesSelectorTarget.files[0];
        const uploadUrl = this.filesSelectorTarget.getAttribute('data-upload-url');
        const uploadCsrf = this.filesSelectorTarget.getAttribute('data-upload-csrf');

        const formData = new FormData();
        formData.append('document', file, file.name);
        formData.append('_csrf_token', uploadCsrf);

        fetch(uploadUrl, {
            method: 'POST',
            body: formData,
        }).then((response) => {
            return response.json();
        }).then((json) => {
            if (json.uid != null) {
                const newDocumentEvent = new CustomEvent('new-document', { detail: json });
                this.element.dispatchEvent(newDocumentEvent);

                this.reload();
            } else {
                alert(json.error);

                if (json.description) {
                    console.error(json.description);
                }
            }

            this.uploadButtonTarget.disabled = false;
        }).catch((error) => {
            alert('Bad response from the server.');
            console.error(error);

            this.uploadButtonTarget.disabled = false;
        });
    }
}
