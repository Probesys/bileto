// This file is part of Bileto.
// Copyright 2022 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

import * as Turbo from '@hotwired/turbo'; // eslint-disable-line no-unused-vars
import { Application } from '@hotwired/stimulus';

import ColorSchemeController from '@/controllers/color_scheme_controller.js';
import PopupController from '@/controllers/popup_controller.js';
import TinymceController from '@/controllers/tinymce_controller.js';

const application = Application.start();
application.register('color-scheme', ColorSchemeController);
application.register('popup', PopupController);
application.register('tinymce', TinymceController);
