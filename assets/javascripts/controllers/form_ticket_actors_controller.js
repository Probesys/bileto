// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static get targets () {
        return ['teams', 'assignees'];
    }

    connect () {
        this.refreshAssignees();
    }

    refreshAssignees () {
        if (!this.hasTeamsTarget) {
            return;
        }

        if (this.teamsTarget.selectedOptions.length !== 1) {
            this.displayAllAssignees();
            return;
        }

        const selectedTeamOption = this.teamsTarget.selectedOptions[0];
        if (selectedTeamOption.value === '') {
            this.displayAllAssignees();
            return;
        }

        const teamAgentsUids = JSON.parse(selectedTeamOption.dataset.agentsUids);

        for (const option of this.assigneesTarget.options) {
            if (option.value === '') {
                // The "Unassigned" option must always be visible.
                continue;
            }

            if (teamAgentsUids.includes(option.value)) {
                option.hidden = false;
            } else {
                option.hidden = true;
                option.selected = false;
            }
        };

        if (this.assigneesTarget.selectedOptions.length !== 1) {
            this.assigneesTarget.options[0].selected = true;
        }
    }

    displayAllAssignees () {
        for (const option of this.assigneesTarget.options) {
            option.hidden = false;
        };
    }
};
