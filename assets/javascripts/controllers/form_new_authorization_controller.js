// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static get targets () {
        return [
            'roleSelect',
            'roleOption',
            'roleCaption',
            'organizations',
        ];
    }

    connect () {
        this.refresh();
    }

    refresh () {
        const selectedTypeNode = this.element.querySelector('input[name="authorization[type]"]:checked');
        const selectedType = selectedTypeNode ? selectedTypeNode.value : '';


        let selectedRole = '';

        this.roleOptionTargets.forEach((roleOption) => {
            const displayRole = (
                selectedType === '' ||
                roleOption.dataset.type === selectedType ||
                (roleOption.dataset.type === 'super' && selectedType === 'admin')
            );

            roleOption.hidden = !displayRole;

            if (displayRole && selectedRole === '') {
                selectedRole = roleOption.value;
            }
        });

        this.roleSelectTarget.value = selectedRole;
        this.organizationsTarget.parentNode.hidden = selectedType === 'admin';

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
