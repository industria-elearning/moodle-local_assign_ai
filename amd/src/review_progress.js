/* eslint-disable */
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Polls backend for progress of AI reviews and updates UI accordingly.
 *
 * @module     local_assign_ai/review_progress
 * @copyright  2025 Wilber Narvaez <https://datacurso.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import Ajax from 'core/ajax';
import Notification from 'core/notification';
import {get_string as getString} from 'core/str';

const POLL_MS_DEFAULT = 8000;
let intervalid = 0;

/**
 * Disable/enable the header buttons depending on current progress state.
 */
function reflectHeaderButtonsState() {
    const anyInProgress = document.querySelector('tr.js-row-inprogress');
    const btnReviewAll = document.querySelector('.js-review-all');
    const btnApproveAll = document.querySelector('.js-approve-all');

    if (btnReviewAll) {
        btnReviewAll.disabled = !!anyInProgress || btnReviewAll.disabled;
    }
    if (btnApproveAll) {
        if (anyInProgress) {
            btnApproveAll.setAttribute('disabled', 'disabled');
        } else if (btnApproveAll.dataset.enableonidle === '1') {
            btnApproveAll.removeAttribute('disabled');
        }
    }
}

/**
 * Update a single row UI using progress info.
 * @param {HTMLElement} row
 * @param {number} progress
 * @param {string} status
 */
function updateRow(row, progress, status) {
    const badge = row.querySelector('.js-state-badge');
    const hint = row.querySelector('.js-state-hint');
    let indicator = row.querySelector('.js-progress-indicator');

    if (progress > 0 && progress < 100) {
        row.classList.add('js-row-inprogress');
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.className = 'small text-warning js-progress-indicator';
            indicator.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>';
            row.querySelector('td:nth-child(7)')?.appendChild(indicator);
        }
        getString('processing', 'local_assign_ai').then(txt => {
            indicator.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' + txt + ' (' + progress + '%)';
        }).catch(() => {});

        // Disable row action buttons while in progress.
        row.querySelectorAll('button').forEach(b => b.setAttribute('disabled', 'disabled'));
    } else {
        row.classList.remove('js-row-inprogress');
        if (indicator) {
            indicator.remove();
        }
        // Re-enable row buttons depending on status.
        if (status === 'initial') {
            row.querySelectorAll('.js-review-ai').forEach(b => b.removeAttribute('disabled'));
        } else if (status === 'pending') {
            row.querySelectorAll('.view-details').forEach(b => b.removeAttribute('disabled'));
        } else {
            row.querySelectorAll('button').forEach(b => b.removeAttribute('disabled'));
        }
    }

    if (badge && hint) {
        if (status === 'initial') {
            badge.className = 'badge bg-secondary js-state-badge';
        } else if (status === 'pending') {
            badge.className = 'badge bg-info js-state-badge';
        }
    }
}

/**
 * Find pending ids in the table that need updates.
 * @returns {number[]}
 */
function collectPendingIds() {
    const rows = document.querySelectorAll('tr[data-pendingid]');
    const ids = [];
    rows.forEach(row => {
        const pid = parseInt(row.getAttribute('data-pendingid'), 10);
        const progress = parseInt(row.getAttribute('data-progress') || '0', 10);
        if (pid && progress < 100) {
            ids.push(pid);
        }
    });
    return ids;
}

/**
 * Apply returned progress values to DOM.
 * @param {Array} entries
 */
function applyProgress(entries) {
    entries.forEach(entry => {
        const row = document.querySelector('tr[data-pendingid="' + entry.id + '"]');
        if (!row) {
            return;
        }
        row.setAttribute('data-progress', String(entry.progress));
        updateRow(row, entry.progress, entry.status);
    });

    reflectHeaderButtonsState();
}

/**
 * Poll backend for progress.
 */
function poll() {
    const ids = collectPendingIds();
    if (ids.length === 0) {
        clearInterval(intervalid);
        reflectHeaderButtonsState();
        return;
    }

    Ajax.call([{
        methodname: 'local_assign_ai_get_progress',
        args: { pendingids: ids },
    }])[0].done(result => {
        applyProgress(result);
    }).fail(err => {
        Notification.exception(err);
    });
}

/**
 * Init module.
 * @param {number} cmid
 */
export function init(cmid) { // eslint-disable-line no-unused-vars
    // Initial reflect and start polling.
    reflectHeaderButtonsState();
    // If page was just queued, ensure we start immediately.
    const initialDelay = document.body.classList.contains('assign-ai-progress-running') ? 0 : POLL_MS_DEFAULT;
    setTimeout(() => {
        poll();
        intervalid = setInterval(poll, POLL_MS_DEFAULT);
    }, initialDelay);
}
