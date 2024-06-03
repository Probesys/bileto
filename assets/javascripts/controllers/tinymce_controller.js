// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static get values () {
        return {
            uploadUrl: String,
            uploadCsrf: String,
        };
    }

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
            plugins: 'autolink autosave lists link image autoresize',
            toolbar: 'bold italic | numlist bullist | link unlink image',
            min_height: 250,
            autoresize_bottom_margin: 10,
            autosave_interval: '5s',
            autosave_retention: '120m',
            menubar: false,
            statusbar: false,
            contextmenu: false,
            promotion: false,
            link_assume_external_targets: true,
            link_target_list: false,
            auto_focus: autofocus,
            images_upload_handler: this.imagesUploader.bind(this),
            relative_urls: false,
            remove_script_host: false,
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

    imagesUploader (blobInfo, progress) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.withCredentials = false;
            xhr.open('POST', this.uploadUrlValue);

            xhr.upload.onprogress = (e) => {
                progress(e.loaded / e.total * 100);
            };

            xhr.onload = () => {
                if (xhr.status === 401) {
                    reject({ message: 'You are not authorized to upload files.', remove: true });
                    return;
                }

                let json;
                try {
                    json = JSON.parse(xhr.responseText);
                } catch {
                    console.error('Bad JSON from server: ' + xhr.responseText);

                    reject({ message: 'Bad response from the server.', remove: true });
                    return;
                }

                if (
                    json == null ||
                    (typeof json !== 'object') ||
                    (json.error == null && json.urlShow == null)
                ) {
                    console.error('Bad JSON from server: ' + xhr.responseText);

                    reject({ message: 'Bad response from the server.', remove: true });
                    return;
                }

                if (json.error) {
                    if (json.description) {
                        console.error('Unexpected error from server: ' + json.description);
                    }

                    reject({ message: json.error, remove: true });
                    return;
                }

                const newDocumentEvent = new CustomEvent('new-document', { detail: json });
                this.element.dispatchEvent(newDocumentEvent);

                resolve(json.urlShow);
            };

            xhr.onerror = () => {
                console.error('Unexpected error from server: error code ' + xhr.status);

                reject({ message: 'Bad response from the server.', remove: true });
            };

            const formData = new FormData();
            formData.append('document', blobInfo.blob(), blobInfo.filename());
            formData.append('_csrf_token', this.uploadCsrfValue);

            xhr.send(formData);
        });
    }

    removeImage (event) {
        const urlShow = event.detail.urlShow;
        const selector = 'img[src="' + urlShow + '"]';
        const images = window.tinymce.activeEditor.dom.select(selector);
        window.tinymce.activeEditor.dom.remove(images);
    }
}
