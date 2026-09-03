// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static get targets () {
        return [
            'label',
            'showEmailButton',
            'showSelectButton',
            'data',
            'select',
            'sectionSelect',
            'sectionEmail',
            'emailInput',
            'emailData',
            'list',
            'template',
        ];
    }

    static get values () {
        return {
            index: Number,
            nameTemplate: String,
        };
    }

    get isMultiple () {
        return this.hasDataTarget;
    }

    connect () {
        if (this.isMultiple) {
            this.refresh();
        }

        if (
            // If the email input has content or is invalid, display the field.
            this.hasEmailInputTarget &&
            (this.emailInputTarget.value || this.emailInputTarget.getAttribute('aria-invalid'))
        ) {
            this.showEmail();
        } else {
            this.showSelect();
        }
    }

    showEmail () {
        this.sectionEmailTarget.hidden = false;
        this.sectionSelectTarget.hidden = true;
        this.showEmailButtonTarget.hidden = true;
        this.showSelectButtonTarget.hidden = false;
        this.labelTarget.htmlFor = this.emailInputTarget.id;

        if (!this.isMultiple) {
            // Disable the select so its data isn't submitted to the backend.
            this.selectTarget.disabled = true;
        }

        this.emailInputTarget.focus();
    }

    showSelect () {
        this.sectionEmailTarget.hidden = true;
        this.sectionSelectTarget.hidden = false;
        this.showEmailButtonTarget.hidden = false;
        this.showSelectButtonTarget.hidden = true;
        this.labelTarget.htmlFor = this.selectTarget.id;

        if (!this.isMultiple) {
            this.selectTarget.disabled = false;
            this.emailInputTarget.value = '';
        }

        this.selectTarget.focus();
    }

    refresh () {
        this.refreshList();
        this.refreshSelect();
    }

    refreshSelect () {
        // Reset the options of the select.
        this.selectTarget.innerHTML = '';

        // Add a placeholder if one is passed via the dataset.
        const placeholder = this.selectTarget.dataset.placeholder;
        if (placeholder) {
            const selectOption = document.createElement('option');
            selectOption.value = '';
            selectOption.text = placeholder;
            selectOption.selected = true;
            selectOption.disabled = true;
            this.selectTarget.add(selectOption);
        } else {
            console.warn('You should pass a data-placeholder attribute to the actor-selector widget.', this.element);
        }

        // Read options that have not been selected yet, and add them to the
        // select.
        const optionsNoGroup = this.dataTarget.querySelectorAll('select > option');
        for (const option of optionsNoGroup) {
            if (!option.selected) {
                const newOption = document.createElement('option');
                newOption.value = option.value;
                newOption.text = option.text;
                this.selectTarget.add(newOption);
            }
        }

        // Same with the options in optgroups.
        const groups = this.dataTarget.querySelectorAll('select > optgroup');
        for (const group of groups) {
            const newOptGroup = document.createElement('optgroup');
            newOptGroup.label = group.label;

            let groupIsEmpty = true;
            const groupOptions = group.querySelectorAll('optgroup > option');
            for (const option of groupOptions) {
                if (!option.selected) {
                    const newOption = document.createElement('option');
                    newOption.value = option.value;
                    newOption.text = option.text;
                    newOptGroup.append(newOption);
                    groupIsEmpty = false;
                }
            }

            if (!groupIsEmpty) {
                this.selectTarget.add(newOptGroup);
            }
        }

        if (this.selectTarget.options.length === 1) {
            // Disable the select if all actors have been selected.
            this.selectTarget.disabled = true;
        } else if (this.dataTarget.disabled) {
            // Disable the select if the initial dataTarget is disabled.
            this.selectTarget.disabled = true;
        } else {
            this.selectTarget.disabled = false;
        }
    }

    refreshList () {
        this.listTarget.innerHTML = '';

        // The users selected from the select box.
        for (const option of this.dataTarget.selectedOptions) {
            const node = this.itemNode(option.value, option.text, 'user');
            this.listTarget.appendChild(node);
        }

        // Then the emails entered manually.
        for (const emailNode of this.emailDataTargets) {
            const node = this.itemNode(emailNode.value, emailNode.value, 'email');
            this.listTarget.appendChild(node);
        }
    }

    select (event) {
        const value = event.target.value;
        for (const option of this.dataTarget.options) {
            if (option.value === value) {
                option.selected = true;
                break;
            }
        }

        this.refresh();
        this.selectTarget.focus();
    }

    unselect (event) {
        const kind = event.currentTarget.dataset.kind;
        const value = event.currentTarget.dataset.value;

        if (kind === 'email') {
            const emailDataNode = this.emailDataTargets.find((node) => node.value === value);
            if (emailDataNode) {
                emailDataNode.remove();
            }
        } else {
            const value = event.currentTarget.getAttribute('data-value');

            for (const option of this.dataTarget.selectedOptions) {
                if (option.value === value) {
                    option.selected = false;
                    break;
                }
            }
        }

        this.refresh();
        this.selectTarget.focus();
    }

    handleEmailInput (event) {
        if (event.key === ',' || event.key === ' ' || event.key === 'Enter' || event.key === 'Tab') {
            this.addEmail();
        }

        if (event.key === ',' || event.key === ' ') {
            event.preventDefault();
        }
    }

    addEmail () {
        const value = this.emailInputTarget.value.trim();

        if (!value || !this.emailInputTarget.reportValidity()) {
            return;
        }

        const name = this.nameTemplateValue.replace(/__name__/g, this.indexValue);

        const dataNode = document.createElement('input');
        dataNode.type = 'hidden';
        dataNode.name = name;
        dataNode.value = value;
        dataNode.setAttribute('data-actor-selector-target', 'emailData');
        this.element.appendChild(dataNode);

        this.emailInputTarget.value = '';
        this.indexValue += 1;

        this.refresh();
    }

    itemNode (value, label, kind) {
        const item = this.templateTarget.content.firstElementChild.cloneNode(true);

        item.setAttribute('data-value', value);
        item.setAttribute('data-kind', kind);
        item.querySelector('[data-target="name"]').textContent = label;

        return item;
    }
}
