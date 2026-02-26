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
 * Resolve the current grader userid from URL or DOM fallback.
 *
 * @returns {number}
 */
const resolveCurrentUserid = () => {
    const params = new URLSearchParams(window.location.search);
    let userid = parseInt(params.get('userid'), 10);

    if (userid) {
        return userid;
    }

    const userLink = document.querySelector('[data-region="user-info"] a[href*="user/view.php"]');
    if (userLink) {
        const url = new URL(userLink.href);
        userid = parseInt(url.searchParams.get('id'), 10);
        if (userid) {
            return userid;
        }
    }

    const input = document.querySelector('input[name="userid"]');
    if (input) {
        userid = parseInt(input.value, 10);
        if (userid) {
            return userid;
        }
    }

    return 0;
};

/**
 * Observes the grader page and starts injection for the active student.
 *
 * Handles grader navigation where `userid` changes in-place.
 *
 * @returns {Promise<void>}
 */
export const init = async () => {
    const courseid = Config.courseId || Config.courseid;
    if (!courseid) {
        return;
    }

    let lastuserid = 0;
    let lastassignmentid = 0;
    let lasttoken = '';
    let inflight = false;
    let attempts = 0;
    const maxAttempts = 240;

    const syncCurrentUser = async () => {
        if (inflight) {
            return;
        }

        const params = new URLSearchParams(window.location.search);
        const assignmentid = parseInt(params.get('id'), 10);
        if (!assignmentid) {
            return;
        }

        const userid = resolveCurrentUserid();
        if (!userid) {
            return;
        }

        inflight = true;
        try {
            const response = await Ajax.call([{
                methodname: 'local_assign_ai_get_token',
                args: { userid, assignmentid }
            }])[0];

            const token = response?.approval_token || '';
            const contextChanged = userid !== lastuserid || assignmentid !== lastassignmentid;
            const tokenChanged = token !== lasttoken;

            if (contextChanged || tokenChanged) {
                InjectAI.init({ token, userid, assignmentid, courseid });
            }

            lastuserid = userid;
            lastassignmentid = assignmentid;
            lasttoken = token;
        } catch (err) {
            Notification.exception(err);
        } finally {
            inflight = false;
        }
    };

    await syncCurrentUser();

    const interval = setInterval(() => {
        attempts++;
        if (attempts > maxAttempts) {
            clearInterval(interval);
            return;
        }
        void syncCurrentUser();
    }, 500);
};
