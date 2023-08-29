// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
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
}
