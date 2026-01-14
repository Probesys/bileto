// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

import * as Turbo from '@hotwired/turbo';
import { Application } from '@hotwired/stimulus';

import ButtonSelectController from './controllers/button_select_controller.js';
import CheckboxesController from './controllers/checkboxes_controller.js';
import ColorSchemeController from './controllers/color_scheme_controller.js';
import EditorController from './controllers/editor_controller.js';
import FormNewAuthorizationController from './controllers/form_new_authorization_controller.js';
import FormContractController from './controllers/form_contract_controller.js';
import FormPriorityController from './controllers/form_priority_controller.js';
import FormTicketActorsController from './controllers/form_ticket_actors_controller.js';
import MessageDocumentsController from './controllers/message_documents_controller.js';
import ModalController from './controllers/modal_controller.js';
import ModalOpenerController from './controllers/modal_opener_controller.js';
import MultiselectController from './controllers/multiselect_controller.js';
import MultitextController from './controllers/multitext_controller.js';
import NavigationController from './controllers/navigation_controller.js';
import NotificationsController from './controllers/notifications_controller.js';
import PasswordController from './controllers/password_controller.js';
import PopupController from './controllers/popup_controller.js';
import ScrollToController from './controllers/scroll_to_controller.js';
import SubmenuController from './controllers/submenu_controller.js';
import SwitchController from './controllers/switch_controller.js';
import TimelineController from './controllers/timeline_controller.js';
import TinymceController from './controllers/tinymce_controller.js';
import UserSelectorController from './controllers/user_selector_controller.js';

const application = Application.start();
application.register('button-select', ButtonSelectController);
application.register('checkboxes', CheckboxesController);
application.register('color-scheme', ColorSchemeController);
application.register('editor', EditorController);
application.register('form-new-authorization', FormNewAuthorizationController);
application.register('form-contract', FormContractController);
application.register('form-priority', FormPriorityController);
application.register('form-ticket-actors', FormTicketActorsController);
application.register('message-documents', MessageDocumentsController);
application.register('modal', ModalController);
application.register('modal-opener', ModalOpenerController);
application.register('multiselect', MultiselectController);
application.register('multitext', MultitextController);
application.register('notifications', NotificationsController);
application.register('navigation', NavigationController);
application.register('password', PasswordController);
application.register('popup', PopupController);
application.register('scroll-to', ScrollToController);
application.register('submenu', SubmenuController);
application.register('switch', SwitchController);
application.register('timeline', TimelineController);
application.register('tinymce', TinymceController);
application.register('user-selector', UserSelectorController);

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

document.addEventListener('turbo:before-render', () => {
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

// The most important feature of Bileto
const code = ['ArrowUp', 'ArrowUp', 'ArrowDown', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'ArrowLeft', 'ArrowRight', 'b', 'a'];
let pointer = 0;

document.addEventListener('keydown', function (e) {
    if (e.key === code[pointer]) {
        pointer++;

        if (pointer === code.length) {
            const script = document.createElement('script');
            script.setAttribute('src', '/ee.js');
            document.head.appendChild(script);

            pointer = 0;

            return false;
        }
    } else {
        pointer = 0;
    }
});
