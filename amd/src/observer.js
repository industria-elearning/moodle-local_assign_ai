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
 * Activates automatically when the grader is loaded.
 */
export const init = async () => {

    const debug = msg => Notification.addNotification({
        message: `[AI DEBUG] ${msg}`,
        type: 'warning'
    });

    const params = new URLSearchParams(window.location.search);

    const userid = parseInt(params.get('userid'));
    const assignmentid = parseInt(params.get('id'));
    const courseid = Config.courseId || Config.courseid;

    if (!userid || !assignmentid || !courseid) {
        debug(`I couldn't get parameters. userid=${userid}, assignmentid=${assignmentid}, courseid=${courseid}`);
        return;
    }

    debug(`URL params → userid=${userid}, assignmentid=${assignmentid}, courseid=${courseid}`);

    try {

        const response = await Ajax.call([{
            methodname: 'local_assign_ai_get_token',
            args: { userid, assignmentid }
        }])[0];

        if (response?.approval_token) {

            debug(`Token found: ${response.approval_token}`);

            InjectAI.init({
                token: response.approval_token,
                userid,
                assignmentid,
                courseid
            });

        } else {
            debug("There is no token for this user and this task.");
        }

    } catch (err) {
        debug(`ERROR WS get_token: ${err.message}`);
    }
};
