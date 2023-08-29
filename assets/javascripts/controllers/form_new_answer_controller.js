// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static get targets () {
        return ['confidentialCheckbox', 'solutionCheckbox', 'statusSelect', 'editor', 'messageDocuments'];
    }

    static get values () {
        return {
            ticketStatus: String,
        };
    }

    connect () {
        this.refresh();
    }

    refresh () {
        this.updateConfidential();
        this.updateSolution();
        this.updateStatus();
    }

    updateConfidential () {
        const isSolution = this.solutionCheckboxTarget.checked;
        if (isSolution) {
            this.confidentialCheckboxTarget.checked = false;
            this.confidentialCheckboxTarget.disabled = true;
        } else {
            this.confidentialCheckboxTarget.disabled = false;
        }
    }

    updateSolution () {
        const isConfidential = this.confidentialCheckboxTarget.checked;
        if (isConfidential || this.isFinished) {
            this.solutionCheckboxTarget.checked = false;
            this.solutionCheckboxTarget.disabled = true;
        } else {
            this.solutionCheckboxTarget.disabled = false;
        }
    }

    updateStatus () {
        if (!this.hasStatusSelectTarget) {
            return;
        }

        const isSolution = this.solutionCheckboxTarget.checked;
        if (isSolution) {
            this.statusSelectTarget.value = 'resolved';

            for (const option of this.statusSelectTarget.options) {
                option.disabled = option.value !== 'resolved';
            }
        } else {
            this.statusSelectTarget.value = 'in_progress';

            for (const option of this.statusSelectTarget.options) {
                option.disabled = false;
            }
        }
    }

    get isFinished () {
        return this.ticketStatusValue === 'resolved' || this.ticketStatusValue === 'closed';
    }

    newDocument (event) {
        const newDocumentEvent = new CustomEvent('new-document', { detail: event.detail });
        this.messageDocumentsTarget.dispatchEvent(newDocumentEvent);
    }

    removeDocument (event) {
        const removeDocumentEvent = new CustomEvent('remove-document', { detail: event.detail });
        this.editorTarget.dispatchEvent(removeDocumentEvent);
    }
}
