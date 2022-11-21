// This file is part of Bileto.
// Copyright 2022 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

import * as Turbo from '@hotwired/turbo'; // eslint-disable-line no-unused-vars
import { Application } from '@hotwired/stimulus';

import ColorSchemeController from '@/controllers/color_scheme_controller.js';
import FormPriorityController from '@/controllers/form_priority_controller.js';
import ModalController from '@/controllers/modal_controller.js';
import ModalOpenerController from '@/controllers/modal_opener_controller.js';
import PopupController from '@/controllers/popup_controller.js';
import NewTicketController from '@/controllers/new_ticket_controller.js';
import TinymceController from '@/controllers/tinymce_controller.js';

const application = Application.start();
application.register('color-scheme', ColorSchemeController);
application.register('form-priority', FormPriorityController);
application.register('modal', ModalController);
application.register('modal-opener', ModalOpenerController);
application.register('new-ticket', NewTicketController);
application.register('popup', PopupController);
application.register('tinymce', TinymceController);

// Allow to disable the automatic scroll-to-top on form submission.
// Submitting forms with a `data-turbo-preserve-scroll` attribute will keep the
// scroll position at the current position.
let disableScroll = false;

document.addEventListener('turbo:submit-start', (event) => {
    if (event.detail.formSubmission.formElement.hasAttribute('data-turbo-preserve-scroll')) {
        disableScroll = true;
    }
});

document.addEventListener('turbo:before-render', (event) => {
    if (disableScroll && Turbo.navigator.currentVisit) {
        // As explained on GitHub, `Turbo.navigator.currentVisit.scrolled`
        // is internal and private attribute: we should NOT access it.
        // Unfortunately, there is no good alternative yet to maintain the
        // scroll position. This means we have to be pay double attention when
        // upgrading Turbo.
        // Reference: https://github.com/hotwired/turbo/issues/37#issuecomment-979466543
        Turbo.navigator.currentVisit.scrolled = true;
        disableScroll = false;
    }
});
