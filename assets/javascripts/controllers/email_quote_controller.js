// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

// Reply headers must fill a line: a casual mention of "wrote:" in the reply
// must not turn the rest of the customer's message into quoted history.
const replyHeader = /(?:^|\n)[ \t]*(?:On[^\n]+wrote|Le[^\n]+a écrit)[ \t]*:[ \t\r]*(?=\n|$)/iu;
const completeReplyHeader = /^(?:On[\s\S]+wrote|Le[\s\S]+a\s+écrit)\s*:$/iu;
const outlookHeaders = /(?:Sent|Envoyé|Date|Subject|Objet)\s*:/iu;

/**
 * Find the first quoted segment in the rendered, sanitized email.
 * An offset starts inside a text node; without one, the entire element is quoted.
 */
function quotedStart (content) {
    // Email clients use different HTML structures. Walk in document order so
    // nested older replies are folded together from the first quote onward.
    const walker = document.createTreeWalker(content, NodeFilter.SHOW_ELEMENT | NodeFilter.SHOW_TEXT);

    while (walker.nextNode()) {
        const node = walker.currentNode;

        if (node.nodeType === Node.ELEMENT_NODE) {
            // Some clients keep a wrapper; Outlook may instead separate its
            // From/Sent headers from the new reply with a horizontal rule.
            if (node.matches('#divRplyFwdMsg, .gmail_quote, .yahoo_quoted')) {
                return { node };
            }

            if (node.matches('hr')) {
                const header = node.nextElementSibling?.textContent ?? '';
                if (/^\s*(?:From|De)\s*:/iu.test(header) && outlookHeaders.test(header)) {
                    return { node };
                }
            }

            // The sanitizer removes CSS classes, but keeps the text preceding
            // Gmail and Thunderbird blockquotes, including links in that text.
            if (node.nextElementSibling?.tagName === 'BLOCKQUOTE' && completeReplyHeader.test(node.textContent.trim())) {
                return { node };
            }

            continue;
        }

        // A plain-text reply can put the header in the same text node as the
        // new message. Keep the portion before the header visible.
        const match = replyHeader.exec(node.textContent);
        if (match) {
            return { node, offset: match.index + (match[0].startsWith('\n') ? 1 : 0) };
        }
    }

    return null;
}

export default class extends Controller {
    static targets = ['summary'];
    static values = { showLabel: String, hideLabel: String };

    connect () {
        // Turbo can reconnect this controller to existing DOM; don't nest
        // another disclosure around a quote that was already folded.
        if (this.element.querySelector('.message__quoted-history')) {
            return;
        }

        const start = quotedStart(this.element);
        if (!start) {
            return;
        }

        // Move the original HTML nodes into a native, keyboard-accessible
        // disclosure. Nothing is removed from the stored email content.
        const range = document.createRange();
        range.selectNodeContents(this.element);
        if (start.offset === undefined) {
            range.setEndBefore(start.node);
        } else {
            range.setEnd(start.node, start.offset);
        }

        // With no text before the quote, the answer may be inside or below it.
        if (!range.toString().trim()) {
            return;
        }

        range.collapse(false);
        range.setEndAfter(this.element.lastChild);

        const details = document.createElement('details');
        details.className = 'message__quoted-history';
        details.dataset.action = 'toggle->email-quote#toggle';
        const summary = document.createElement('summary');
        summary.className = 'button button--discreet message__quote-toggle';
        summary.dataset.emailQuoteTarget = 'summary';
        summary.textContent = this.showLabelValue;
        const body = document.createElement('div');
        body.className = 'message__quoted-body';
        body.append(range.extractContents());
        details.append(summary, body);
        this.element.append(details);
    }

    toggle (event) {
        this.summaryTarget.textContent = event.currentTarget.open ? this.hideLabelValue : this.showLabelValue;
    }
}
