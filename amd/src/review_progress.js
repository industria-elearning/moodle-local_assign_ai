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

// Client-side smoothing state per row. We keep randomized parameters per processing session
// so that progress does not look identical across rows or sessions.
const startTimes = new Map(); // pendingid -> timestamp ms
const expectedDurations = new Map(); // pendingid -> ms
const startOffsets = new Map(); // pendingid -> int percent (1..8)
const curveFactors = new Map(); // pendingid -> float (0.6..1.4)
const lastShown = new Map(); // pendingid -> last integer percent shown

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
    const btnReview = row.querySelector('.js-btn-review');
    const btnDetails = row.querySelector('.js-btn-details');

    if (status === 'processing' && progress > 0 && progress < 100) {
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
        } else if (status === 'queued') {
            // Keep disabled while waiting for processing to start.
            row.querySelectorAll('button').forEach(b => b.setAttribute('disabled', 'disabled'));
        } else {
            row.querySelectorAll('button').forEach(b => b.removeAttribute('disabled'));
        }
    }

    if (badge && hint) {
        // Update badge class and short text per status, and the longer hint below.
        if (status === 'initial') {
            badge.className = 'badge bg-secondary js-state-badge';
            getString('aistatus_initial_short', 'local_assign_ai').then(t => { badge.textContent = t; }).catch(() => {});
            getString('aistatus_initial_help', 'local_assign_ai').then(t => { hint.textContent = t; }).catch(() => {});
        } else if (status === 'queued') {
            badge.className = 'badge bg-warning text-dark js-state-badge';
            getString('queued', 'local_assign_ai').then(t => { badge.textContent = t; }).catch(() => {});
            getString('aistatus_queued_help', 'local_assign_ai').then(t => { hint.textContent = t; }).catch(() => {});
        } else if (status === 'processing') {
            badge.className = 'badge bg-warning text-dark js-state-badge';
            getString('processing', 'local_assign_ai').then(t => { badge.textContent = t; }).catch(() => {});
            getString('aistatus_processing_help', 'local_assign_ai').then(t => { hint.textContent = t; }).catch(() => {});
        } else if (status === 'pending') {
            badge.className = 'badge bg-info js-state-badge';
            getString('aistatus_pending_short', 'local_assign_ai').then(t => { badge.textContent = t; }).catch(() => {});
            getString('aistatus_pending_help', 'local_assign_ai').then(t => { hint.textContent = t; }).catch(() => {});
        }
    }

    // Toggle visibility of action buttons based on status.
    if (btnReview && btnDetails) {
        if (status === 'pending' || progress >= 100) {
            btnReview.classList.add('d-none');
            btnDetails.classList.remove('d-none');
        } else if (status === 'initial' || status === 'queued' || status === 'processing') {
            btnDetails.classList.add('d-none');
            btnReview.classList.remove('d-none');
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
    const now = Date.now();
    entries.forEach(entry => {
        const row = document.querySelector('tr[data-pendingid="' + entry.id + '"]');
        if (!row) {
            return;
        }
        // Initialize smoothing when processing begins and it's not tracked.
        if (entry.status === 'processing') {
            if (!startTimes.has(entry.id)) {
                startTimes.set(entry.id, now);
                // Randomize expected duration 20-55s.
                const expected = 20000 + Math.floor(Math.random() * 35001);
                expectedDurations.set(entry.id, expected);
                // Randomize starting offset 1-8% to avoid uniform starts.
                startOffsets.set(entry.id, 1 + Math.floor(Math.random() * 8));
                // Randomize easing curve factor for diversity (ease-out exponent).
                curveFactors.set(entry.id, 0.6 + Math.random() * 0.8); // 0.6..1.4
                // Initialize last shown.
                lastShown.set(entry.id, 0);
            }
        }

        // Compute adjusted client-side progress.
        let adjusted = 0;
        if (entry.status === 'processing' && startTimes.has(entry.id)) {
            const startedAt = startTimes.get(entry.id);
            const expected = expectedDurations.get(entry.id) || 35000;
            const elapsed = Math.max(0, now - startedAt);
            const t = Math.min(0.999, Math.max(0, elapsed / expected));
            const start = startOffsets.get(entry.id) || 1;
            const curve = curveFactors.get(entry.id) || 1.0;
            // Ease-out with variable exponent to diversify curves.
            const eased = 1 - Math.pow(1 - t, curve);
            let estimated = start + Math.floor(eased * (99 - start));
            // Add small, bounded per-tick noise to avoid perfectly smooth growth.
            const noise = Math.floor((Math.random() * 5) - 2); // -2..+2
            estimated = Math.max(start, Math.min(99, estimated + noise));
            // Ensure monotonic increase.
            const last = lastShown.get(entry.id) || 0;
            adjusted = Math.max(last, estimated);
            lastShown.set(entry.id, adjusted);
        }

        // If status is no longer processing, clear smoothing state.
        if (entry.status !== 'processing') {
            startTimes.delete(entry.id);
            expectedDurations.delete(entry.id);
            startOffsets.delete(entry.id);
            curveFactors.delete(entry.id);
            lastShown.delete(entry.id);
        }

        row.setAttribute('data-progress', String(adjusted));
        updateRow(row, adjusted, entry.status);
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
export function init(cmid) {
    // Initial reflect and start polling.
    reflectHeaderButtonsState();
    // If page was just queued, ensure we start immediately.
    const initialDelay = document.body.classList.contains('assign-ai-progress-running') ? 0 : POLL_MS_DEFAULT;
    setTimeout(() => {
        poll();
        intervalid = setInterval(poll, POLL_MS_DEFAULT);
    }, initialDelay);
}
