// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static get values () {
        return {
            hiddenInputSelector: String,
            countSelector: String,
        };
    }

    static get targets () {
        return ['taskList', 'taskItems', 'labelInput', 'startAtInput', 'endAtInput', 'warning', 'cancelButton', 'addButton', 'taskListLabel'];
    }

    connect () {
        this.tasks = [];
        this.editingIndex = null;
        this._syncFromHiddenInput();
        this._updateCount();
        this._resetForm();
        this._renderTaskList();
    }

    addTask (event) {
        event.preventDefault();

        const label = this.labelInputTarget.value.trim();
        const startAt = this.startAtInputTarget.value;
        const endAt = this.endAtInputTarget.value;

        if (!label || !startAt || !endAt) {
            return;
        }

        if (new Date(endAt) <= new Date(startAt)) {
            this.warningTarget.hidden = false;
            return;
        }

        this.warningTarget.hidden = true;

        const task = { label, startAt, endAt };

        if (this.editingIndex !== null) {
            this.tasks[this.editingIndex] = task;
            this.editingIndex = null;
        } else {
            this.tasks.push(task);
        }

        this._syncToHiddenInput();
        if (this.hasAddButtonTarget) {
            this.addButtonTarget.textContent = this.addButtonTarget.dataset.addLabel;
        }
        this._resetForm();
        this._renderTaskList();
    }

    editTask (event) {
        const index = parseInt(event.currentTarget.dataset.taskIndex, 10);
        const task = this.tasks[index];

        if (!task) {
            return;
        }

        this.editingIndex = index;
        this.labelInputTarget.value = task.label;
        this.startAtInputTarget.value = task.startAt;
        this.endAtInputTarget.value = task.endAt;
        this.warningTarget.hidden = true;
        this.cancelButtonTarget.hidden = false;
        if (this.hasAddButtonTarget) {
            this.addButtonTarget.textContent = this.addButtonTarget.dataset.editLabel;
        }
        this._renderTaskList();
        this.labelInputTarget.focus();
    }

    removeTask (event) {
        const index = parseInt(event.currentTarget.dataset.taskIndex, 10);
        this.tasks.splice(index, 1);
        this._syncToHiddenInput();
        this._renderTaskList();
    }

    cancelEdit (event) {
        event.preventDefault();
        this.editingIndex = null;
        if (this.hasAddButtonTarget) {
            this.addButtonTarget.textContent = this.addButtonTarget.dataset.addLabel;
        }
        this._resetForm();
        this._renderTaskList();
    }

    onStartAtChange () {
        const startAt = this.startAtInputTarget.value;
        const endAt = this.endAtInputTarget.value;

        if (!startAt) {
            return;
        }

        const startDate = new Date(startAt);

        if (!endAt) {
            const newEnd = new Date(startDate.getTime() + 60 * 60 * 1000);
            this.endAtInputTarget.value = this._formatDatetimeLocal(newEnd);
            return;
        }

        const endDate = new Date(endAt);
        const previousStart = this._previousStartAt ? new Date(this._previousStartAt) : null;

        if (previousStart) {
            const diff = endDate.getTime() - previousStart.getTime();
            const newEnd = new Date(startDate.getTime() + diff);
            this.endAtInputTarget.value = this._formatDatetimeLocal(newEnd);
        }

        this._previousStartAt = startAt;
        this._checkEndBeforeStart();
    }

    onEndAtChange () {
        this._checkEndBeforeStart();
    }

    // Private methods

    _resetForm () {
        const now = new Date();
        const nextHour = new Date(now);
        nextHour.setMinutes(0, 0, 0);
        nextHour.setHours(nextHour.getHours() + 1);

        const oneHourLater = new Date(nextHour.getTime() + 60 * 60 * 1000);

        this.labelInputTarget.value = '';
        this.startAtInputTarget.value = this._formatDatetimeLocal(nextHour);
        this.endAtInputTarget.value = this._formatDatetimeLocal(oneHourLater);
        this._previousStartAt = this.startAtInputTarget.value;
        this.warningTarget.hidden = true;
        this.cancelButtonTarget.hidden = true;
        this.labelInputTarget.focus();
    }

    _renderTaskList () {
        const container = this.hasTaskItemsTarget ? this.taskItemsTarget : this.taskListTarget;
        container.innerHTML = '';

        if (this.hasTaskListLabelTarget) {
            this.taskListLabelTarget.hidden = this.tasks.length === 0;
        }

        if (this.tasks.length === 0) {
            return;
        }

        const locale = document.documentElement.lang || 'fr';
        const formatOptions = { dateStyle: 'short', timeStyle: 'short' };

        this.tasks.forEach((task, index) => {
            const item = document.createElement('div');
            const isEditing = this.editingIndex === index;
            item.className = 'flow flow--small';
            if (isEditing) {
                item.style.cssText = 'background: var(--color-primary3); border-radius: var(--border-radius); padding: var(--space-smaller) var(--space-small); margin-left: calc(-1 * var(--space-small)); margin-right: calc(-1 * var(--space-small));';
            }

            const startDate = new Date(task.startAt);
            const endDate = new Date(task.endAt);

            item.innerHTML = `
                <div class="cols cols--always cols--center" style="gap: var(--space-smaller)">
                    <div class="col--extend" style="min-width: 0">
                        <strong class="text--small" style="display:block">${this._escapeHtml(task.label)}</strong>
                        <span class="text--small text--secondary" style="display:block; margin-top: var(--space-smaller)">
                            ${startDate.toLocaleString(locale, formatOptions)} → ${endDate.toLocaleString(locale, formatOptions)}
                        </span>
                    </div>
                    <div class="cols cols--always cols--center flow flow--small" style="flex: 0 0 auto">
                        <button type="button" class="button--ghost button--small" data-action="task-planner#editTask" data-task-index="${index}">
                            ${isEditing ? '✎ En cours…' : 'Éditer'}
                        </button>
                        <button type="button" class="button--ghost button--small" data-action="task-planner#removeTask" data-task-index="${index}" style="color:var(--color-error11)">
                            Supprimer
                        </button>
                    </div>
                </div>
            `;

            container.appendChild(item);
        });
    }

    _syncToHiddenInput () {
        const json = JSON.stringify(this.tasks);

        const input = document.querySelector(this.hiddenInputSelectorValue);
        if (input) {
            input.value = json;
        }

        const countEl = document.querySelector(this.countSelectorValue);
        if (countEl) {
            countEl.dataset.tasks = json;
        }

        this._updateCount();
    }

    _updateCount () {
        const countEl = document.querySelector(this.countSelectorValue);
        if (!countEl) {
            return;
        }
        const count = this.tasks.length;
        if (count > 0) {
            countEl.textContent = count;
            countEl.hidden = false;
        } else {
            countEl.hidden = true;
        }
    }

    _syncFromHiddenInput () {
        try {
            // Primary: read from the count element's data-tasks (survives modal open/close)
            const countEl = document.querySelector(this.countSelectorValue);
            const stored = countEl ? (countEl.dataset.tasks || '') : '';
            if (stored) {
                this.tasks = JSON.parse(stored);
                return;
            }
            // Fallback: read from the form hidden input
            const input = document.querySelector(this.hiddenInputSelectorValue);
            const value = input ? input.value : '';
            this.tasks = value ? JSON.parse(value) : [];
        } catch {
            this.tasks = [];
        }
    }

    _checkEndBeforeStart () {
        const startAt = this.startAtInputTarget.value;
        const endAt = this.endAtInputTarget.value;

        if (startAt && endAt && new Date(endAt) <= new Date(startAt)) {
            this.warningTarget.hidden = false;
        } else {
            this.warningTarget.hidden = true;
        }
    }

    _formatDatetimeLocal (date) {
        const pad = (n) => String(n).padStart(2, '0');
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
    }

    _escapeHtml (str) {
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
}
