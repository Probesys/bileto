// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static get targets () {
        return [
            'radioOrga',
            'radioAdmin',
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
        const isOrgaChecked = this.radioOrgaTarget.checked;
        if (isOrgaChecked) {
            let selectedRole = '';
            this.roleOptionTargets.forEach((roleOption) => {
                roleOption.hidden = !roleOption.dataset.type.startsWith('orga:');
                if (roleOption.dataset.type.startsWith('orga:') && selectedRole === '') {
                    selectedRole = roleOption.value;
                }
            });
            this.roleSelectTarget.value = selectedRole;
            this.organizationsGroupTarget.hidden = false;
        } else {
            let selectedRole = '';
            this.roleOptionTargets.forEach((roleOption) => {
                roleOption.hidden = roleOption.dataset.type.startsWith('orga:');
                if (!roleOption.dataset.type.startsWith('orga:') && selectedRole === '') {
                    selectedRole = roleOption.value;
                }
            });
            this.roleSelectTarget.value = selectedRole;
            this.organizationsGroupTarget.hidden = true;
        }

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
