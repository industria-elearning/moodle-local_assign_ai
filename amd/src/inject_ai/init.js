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
 * Fetches AI data and injects it into grading forms.
 *
 * @module      local_assign_ai/inject_ai/init
 * @copyright   2025 Datacurso
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import { get_string as getString } from 'core/str';
import Log from 'core/log';
import { injectMessage } from './inject_message';
import { injectRubric } from './inject_rubric';
import { injectGuide } from './inject_guide';
import { injectSimpleGrade } from './inject_simple_grade';

/**
 * Injects AI-generated feedback, rubric selections and/or grade
 * into the assignment grading form for the current student.
 *
 * @param {Object} params                      Required parameters.
 * @param {string} params.token                Approval token used to fetch AI details.
 * @param {number} params.userid               ID of the student being graded.
 * @param {number} params.assignmentid         Assignment identifier (cmid) from the grader URL.
 * @param {number} params.courseid             Course ID of the assignment.
 */
export const init = async ({ token, userid, assignmentid, courseid }) => {
    if (!token || !userid || !assignmentid || !courseid) {
        return;
    }

    const [
        strErrorParsing,
        strRubricArray,
        strRubricSuccess,
        strRubricFailed
    ] = await Promise.all([
        getString('errorparsingrubric', 'local_assign_ai'),
        getString('rubricmustarray', 'local_assign_ai'),
        getString('rubricsuccess', 'local_assign_ai'),
        getString('rubricfailed', 'local_assign_ai'),
    ]);

    Ajax.call([{
        methodname: 'local_assign_ai_get_details',
        args: { courseid, cmid: assignmentid, userid }
    }])[0]
        .done(data => {
            const message = data.message ?? data.reply ?? '';
            const rubricResponse = data.rubric_response ?? data.rubric ?? null;
            const guideResponse = data.assessment_guide_response ?? null;
            const grade = data.grade ?? null;
            const status = data.status ?? 'none';

            Log.debug('[local_assign_ai] Data received:', { reference: message, rubricResponse, guideResponse, grade });

            if (status === 'approve') {
                Log.debug('[local_assign_ai] Skipping injection for approved status.');
                return;
            }

            let successfulInjection = false;

            const runInjection = () => {
                let anyInjected = false;

                // 1. Message
                if (injectMessage(message)) {
                    // Message injected
                }

                // 2. Parse Data
                let advancedData = null;
                let isGuide = false;

                if (guideResponse && guideResponse !== 'null' && guideResponse !== '') {
                    try {
                        advancedData = typeof guideResponse === 'string' ? JSON.parse(guideResponse) : guideResponse;
                        isGuide = true;
                    } catch (e) {
                        Log.error('[local_assign_ai] Error parsing guide:', e);
                        Notification.addNotification({ message: `${strErrorParsing} ${e.message}`, type: 'error' });
                        return false;
                    }
                } else if (rubricResponse && rubricResponse !== 'null' && rubricResponse !== '') {
                    try {
                        advancedData = typeof rubricResponse === 'string' ? JSON.parse(rubricResponse) : rubricResponse;
                        // Legacy fallback check for object-in-rubric-field
                        if (!Array.isArray(advancedData) && typeof advancedData === 'object') {
                            isGuide = true;
                        }
                    } catch (e) {
                        Log.error('[local_assign_ai] Error parsing rubric:', e);
                        Notification.addNotification({ message: `${strErrorParsing} ${e.message}`, type: 'error' });
                        return false;
                    }
                }

                // 3. Dispatch
                if (advancedData) {
                    if (isGuide) {
                        if (injectGuide(advancedData)) {
                            anyInjected = true;
                        }
                    } else if (Array.isArray(advancedData)) {
                        if (injectRubric(advancedData, strRubricArray)) {
                            anyInjected = true;
                        }
                    }
                } else {
                    if (injectSimpleGrade(grade)) {
                        anyInjected = true;
                    }
                }

                return anyInjected;
            };

            const showResults = (success) => {
                Notification.addNotification({
                    message: success ? strRubricSuccess : strRubricFailed,
                    type: success ? 'success' : 'warning'
                });
            };

            // Polling
            let attempts = 0;
            const maxAttempts = 100;
            const interval = setInterval(() => {
                attempts++;
                if (!successfulInjection) {
                    if (runInjection()) {
                        successfulInjection = true;
                    }
                }
                if ((successfulInjection && attempts > 20) || attempts > maxAttempts) {
                    clearInterval(interval);
                    if (successfulInjection) {
                        showResults(true);
                    }
                }
            }, 300);

            // Observer
            const container = document.querySelector('[data-region="grading-actions-form"]') ||
                document.querySelector('.gradingform_rubric') ||
                document.querySelector('.gradingform_guide') ||
                document.body;

            if (container) {
                const observer = new MutationObserver(() => {
                    const criteria = document.querySelectorAll('tr.criterion, .gradingform_guide tr');
                    if (criteria.length > 0 && !successfulInjection) {
                        if (runInjection()) {
                            successfulInjection = true;
                            observer.disconnect();
                        }
                    }
                });
                observer.observe(container, { childList: true, subtree: true });
                setTimeout(() => observer.disconnect(), 20000);
            }
        })
        .fail(Notification.exception);
};
