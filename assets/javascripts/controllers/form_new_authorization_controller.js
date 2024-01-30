// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static get targets () {
        return [
            'roleSelect',
            'roleOption',
            'roleCaption',
            'organizationsGroup',
        ];
    }

    connect () {
        this.refresh();
    }

    refresh () {
        const selectedType = this.element.querySelector('input[name="type"]:checked').value;

        let selectedRole = '';

        this.roleOptionTargets.forEach((roleOption) => {
            const displayRole = (
                roleOption.dataset.type === selectedType ||
                (roleOption.dataset.type === 'super' && selectedType === 'admin')
            );

            roleOption.hidden = !displayRole;

            if (displayRole && selectedRole === '') {
                selectedRole = roleOption.value;
            }
        });

        this.roleSelectTarget.value = selectedRole;
        this.organizationsGroupTarget.hidden = selectedType === 'admin';

        this.refreshRoleCaption();
    }

    refreshRoleCaption () {
        let roleCaption = '';
        this.roleOptionTargets.forEach((roleOption) => {
            if (roleOption.value === this.roleSelectTarget.value) {
                roleCaption = roleOption.dataset.desc;
            }
        });
        this.roleCaptionTarget.innerHTML = roleCaption;
    }
};
