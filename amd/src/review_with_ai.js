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
 * Handles the "Review with AI" actions on the assignment review page.
 *
 * @module      local_assign_ai/review_with_ai
 * @copyright   2025 Datacurso
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import { get_string as getString } from 'core/str';

/**
 * Initialize event listeners for AI review buttons.
 */
export const init = async () => {
    // Load localized strings
    const [
        strProcessing,
        strQueued,
        strProcessed,
        strNoSubmissions,
        strReload,
        strError,
        strConfirmReviewAll,
        strConfirmTitle,
        strContinue,
    ] = await Promise.all([
        getString('processing', 'local_assign_ai'),
        getString('queued', 'local_assign_ai'),
        getString('processed', 'local_assign_ai'),
        getString('nosubmissions', 'local_assign_ai'),
        getString('reloadpage', 'local_assign_ai'),
        getString('processingerror', 'local_assign_ai'),
        getString('confirm_review_all', 'local_assign_ai'),
        getString('confirm', 'moodle'),
        getString('continue', 'moodle'),
    ]);

    document.querySelectorAll('.js-review-ai').forEach(button => {
        button.addEventListener('click', e => {
            e.preventDefault();

            const cmid = parseInt(button.dataset.cmid);
            const userid = parseInt(button.dataset.userid || 0);
            const pendingid = button.dataset.pendingid ? parseInt(button.dataset.pendingid, 10) : 0;
            const all = button.dataset.all === '1';

            const processRequest = () => {
                // Save the original HTML to restore it later
                const originalHTML = button.innerHTML;

                // Show spinner with text
                button.innerHTML = `
                    <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                    ${strProcessing}
                `;
                button.disabled = true;

                // AJAX call to the web service
                Ajax.call([{
                    methodname: 'local_assign_ai_process_submission',
                    args: { cmid: cmid, userid: userid, all: all, pendingid: pendingid },
                }])[0].done(result => {
                    if (result.status === 'queued') {
                        Notification.addNotification({
                            message: strQueued,
                            type: 'info',
                        });
                        button.innerHTML = originalHTML;
                        // Keep the bulk button disabled while progress polling runs.
                        if (all) {
                            button.disabled = true;
                            // Soft trigger: add a body class so the progress module (if present) may start earlier.
                            document.body.classList.add('assign-ai-progress-running');
                        } else {
                            button.disabled = false;
                        }
                        return;
                    }

                    if (result.status === 'ok') {
                        Notification.addNotification({
                            message: `${strProcessed.replace('{$a}', result.processed)} ${strReload}`,
                            type: 'success',
                        });
                        window.location.reload();
                        return;
                    }

                    Notification.addNotification({
                        message: strNoSubmissions,
                        type: 'warning',
                    });
                    button.innerHTML = originalHTML;
                    button.disabled = false;
                }).fail(err => {
                    Notification.addNotification({
                        message: strError,
                        type: 'error',
                    });
                    Notification.exception(err);
                    button.innerHTML = originalHTML;
                    button.disabled = false;
                });
            };

            if (all) {
                Notification.saveCancelPromise(
                    strConfirmTitle,
                    strConfirmReviewAll,
                    strContinue,
                    {triggerElement: button}
                ).then(processRequest).catch(() => {});
                return;
            }

            processRequest();
        });
    });
};
