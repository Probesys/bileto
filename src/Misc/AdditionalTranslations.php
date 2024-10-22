<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Misc;

use Symfony\Component\Translation\TranslatableMessage;

// This file contains translations keys that are built dynamically.
// The translation:extract command cannot find them otherwise, and delete the
// keys from the translations files. By listing them manually in this file, the
// command detects them, even if this file is never used in the application.

// See App\Twig\RolePermissionExtension
new TranslatableMessage('roles.permissions.admin.create.organizations');
new TranslatableMessage('roles.permissions.admin.manage.agents');
new TranslatableMessage('roles.permissions.admin.manage.labels');
new TranslatableMessage('roles.permissions.admin.manage.mailboxes');
new TranslatableMessage('roles.permissions.admin.manage.roles');
new TranslatableMessage('roles.permissions.admin.manage.users');
new TranslatableMessage('roles.permissions.orga.create.tickets');
new TranslatableMessage('roles.permissions.orga.create.tickets.messages');
new TranslatableMessage('roles.permissions.orga.create.tickets.messages.confidential');
new TranslatableMessage('roles.permissions.orga.create.tickets.time_spent');
new TranslatableMessage('roles.permissions.orga.manage');
new TranslatableMessage('roles.permissions.orga.manage.contracts');
new TranslatableMessage('roles.permissions.orga.see.contracts');
new TranslatableMessage('roles.permissions.orga.see.contracts.notes');
new TranslatableMessage('roles.permissions.orga.see.tickets.all');
new TranslatableMessage('roles.permissions.orga.see.tickets.contracts');
new TranslatableMessage('roles.permissions.orga.see.tickets.messages.confidential');
new TranslatableMessage('roles.permissions.orga.see.tickets.time_spent.accounted');
new TranslatableMessage('roles.permissions.orga.see.tickets.time_spent.real');
new TranslatableMessage('roles.permissions.orga.see.users');
new TranslatableMessage('roles.permissions.orga.update.tickets.actors');
new TranslatableMessage('roles.permissions.orga.update.tickets.contracts');
new TranslatableMessage('roles.permissions.orga.update.tickets.labels');
new TranslatableMessage('roles.permissions.orga.update.tickets.organization');
new TranslatableMessage('roles.permissions.orga.update.tickets.priority');
new TranslatableMessage('roles.permissions.orga.update.tickets.status');
new TranslatableMessage('roles.permissions.orga.update.tickets.title');
new TranslatableMessage('roles.permissions.orga.update.tickets.type');

// See templates/labels/_form.html.twig
new TranslatableMessage('common.colors.grey');
new TranslatableMessage('common.colors.primary');
new TranslatableMessage('common.colors.blue');
new TranslatableMessage('common.colors.green');
new TranslatableMessage('common.colors.orange');
new TranslatableMessage('common.colors.red');

// See src/Form/TicketForm.php
new TranslatableMessage('tickets.type.request');
new TranslatableMessage('tickets.type.incident');

// See src/Form/AnswerForm.php
new TranslatableMessage('tickets.show.answer_type.confidential');
new TranslatableMessage('tickets.show.answer_type.normal');
new TranslatableMessage('tickets.show.answer_type.solution');
