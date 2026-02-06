// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Observer to monitor grader page and trigger AI injection.
 *
 * @module     local_assign_ai/observer
 * @copyright   2025 Datacurso
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import * as InjectAI from 'local_assign_ai/inject_ai';
import Config from 'core/config';

/**
 * Observes the grader page, extracts URL parameters, retrieves
 * the AI approval token for the current student, and triggers
 * injection of AI-generated feedback into the grader interface.
 *
 * @returns {Promise<void>}
 */
export const init = async () => {

    const params = new URLSearchParams(window.location.search);
    const assignmentid = parseInt(params.get('id'));
    const courseid = Config.courseId || Config.courseid;

    if (!assignmentid || !courseid) {
        return;
    }

    // Function to attempt initialization
    const tryInit = async () => {
        const currentParams = new URLSearchParams(window.location.search);
        let userid = parseInt(currentParams.get('userid'));

        // Fallback: Try to find userid from the DOM if not in URL
        if (!userid) {
            // Try Common Moodle Grader selectors
            const userLink = document.querySelector('[data-region="user-info"] a[href*="user/view.php"]');
            if (userLink) {
                const url = new URL(userLink.href);
                userid = parseInt(url.searchParams.get('id'));
            } else {
                const input = document.querySelector('input[name="userid"]');
                if (input) {
                    userid = parseInt(input.value);
                }
            }
        }

        if (userid) {
            try {
                const response = await Ajax.call([{
                    methodname: 'local_assign_ai_get_token',
                    args: { userid, assignmentid }
                }])[0];

                if (response?.approval_token) {
                    InjectAI.init({
                        token: response.approval_token,
                        userid,
                        assignmentid,
                        courseid
                    });
                    return true; // Success
                }
            } catch (err) {
                Notification.exception(err);
                return true; // Stop polling on error
            }
        }
        return false; // Not ready yet
    };

    // Attempt immediately
    if (await tryInit()) {
        return;
    }

    // Retry via polling if userid wasn't found immediately (e.g. first load)
    let attempts = 0;
    const maxAttempts = 20; // Wait up to ~10s
    const interval = setInterval(async () => {
        attempts++;
        if (await tryInit() || attempts >= maxAttempts) {
            clearInterval(interval);
        }
    }, 500);
};
