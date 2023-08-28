// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

import * as Turbo from '@hotwired/turbo'; // eslint-disable-line no-unused-vars
import { Application } from '@hotwired/stimulus';

import CheckboxesController from '@/controllers/checkboxes_controller.js';
import ColorSchemeController from '@/controllers/color_scheme_controller.js';
import FormMessageDocumentsController from '@/controllers/form_message_documents_controller.js';
import FormNewAnswerController from '@/controllers/form_new_answer_controller.js';
import FormNewAuthorizationController from '@/controllers/form_new_authorization_controller.js';
import FormPriorityController from '@/controllers/form_priority_controller.js';
import ModalController from '@/controllers/modal_controller.js';
import ModalOpenerController from '@/controllers/modal_opener_controller.js';
import MultiselectActorsController from '@/controllers/multiselect_actors_controller.js';
import NotificationsController from '@/controllers/notifications_controller.js';
import PasswordController from '@/controllers/password_controller.js';
import PopupController from '@/controllers/popup_controller.js';
import ScrollToController from '@/controllers/scroll_to_controller.js';
import TabsController from '@/controllers/tabs_controller.js';
import TinymceController from '@/controllers/tinymce_controller.js';

const application = Application.start();
application.register('checkboxes', CheckboxesController);
application.register('color-scheme', ColorSchemeController);
application.register('form-message-documents', FormMessageDocumentsController);
application.register('form-new-answer', FormNewAnswerController);
application.register('form-new-authorization', FormNewAuthorizationController);
application.register('form-priority', FormPriorityController);
application.register('modal', ModalController);
application.register('modal-opener', ModalOpenerController);
application.register('multiselect-actors', MultiselectActorsController);
application.register('notifications', NotificationsController);
application.register('password', PasswordController);
application.register('popup', PopupController);
application.register('scroll-to', ScrollToController);
application.register('tabs', TabsController);
application.register('tinymce', TinymceController);

// Make sure to visit the response when receiving the `turbo:frame-missing` event.
// This happens most of the time on redirection after submitting a form in a modal.
// Otherwise, "Content missing" would be displayed within the modal.
document.addEventListener('turbo:frame-missing', (event) => {
    event.preventDefault();
    event.detail.visit(event.detail.response);
});

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
