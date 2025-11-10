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
 * Insert comments, rubric, and AI-generated rating into the task evaluation form.
 *
 * @module      local_assign_ai/inject_ai
 * @copyright   2025 Datacurso
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import { get_string as getString } from 'core/str';

/**
 * Injects AI feedback, rubric, or grade into the assignment grading form.
 *
 * @param {string} token Approval token
 */
export const init = async (token) => {
    if (!token) {
        return;
    }

    // Preload language strings
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

    // Retrieve AI-generated feedback details
    Ajax.call([{
        methodname: 'local_assign_ai_get_details',
        args: { token: token },
    }])[0].done(data => {
        const message = data.message ?? data.reply ?? '';
        const rubricResponse = data.rubric_response ?? data.rubric ?? null;
        const grade = data.grade ?? null;

        /**
         * Injects AI message into feedback editor.
         * @returns {boolean} True if message was injected successfully
         */
        const injectMessage = () => {
            const textarea = document.querySelector(
                '#id_assignfeedbackcomments_editor, textarea[id^="id_feedbackcomments_"]'
            );

            if (!textarea) {
                return false;
            }

            textarea.value = message;

            // TinyMCE support
            if (window.tinymce && window.tinymce.get(textarea.id)) {
                window.tinymce.get(textarea.id).setContent(message);
                return true;
            }

            // Atto support
            if (window.M && window.M.editor_atto && window.M.editor_atto.getEditorForElement) {
                const editor = window.M.editor_atto.getEditorForElement(textarea);
                if (editor) {
                    editor.setHTML(message);
                    return true;
                }
            }

            return textarea.value === message;
        };

        /**
         * Injects rubric selections and comments.
         * @returns {boolean} True if rubric was injected successfully
         */
        const injectRubric = () => {
            if (!rubricResponse) {
                return false;
            }

            let rubricData;
            try {
                rubricData = typeof rubricResponse === 'string'
                    ? JSON.parse(rubricResponse)
                    : rubricResponse;
            } catch (e) {
                Notification.addNotification({
                    message: `${strErrorParsing} ${e.message}`,
                    type: 'error'
                });
                return false;
            }

            if (!Array.isArray(rubricData)) {
                Notification.addNotification({
                    message: strRubricArray,
                    type: 'error'
                });
                return false;
            }

            let injected = false;

            // Normalize text
            const normalizeString = (str) => {
                return str
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .toLowerCase()
                    .trim();
            };

            // Find all criteria rows
            const criterionRows = document.querySelectorAll('tr.criterion');

            if (criterionRows.length === 0) {
                return false;
            }

            // Process each criterion from AI data
            rubricData.forEach((criterionData) => {
                const criterionName = criterionData.criterion;
                const targetPoints = criterionData.levels[0].points;
                const comment = criterionData.levels[0].comment;

                // Find matching criterion row in DOM
                criterionRows.forEach((row) => {
                    const descriptionCell = row.querySelector('td.description');
                    if (!descriptionCell) {
                        return;
                    }

                    const rowCriterionName = descriptionCell.textContent.trim();
                    const normalizedRow = normalizeString(rowCriterionName);
                    const normalizedTarget = normalizeString(criterionName);

                    // Compare normalized names (case-insensitive, no accents)
                    if (normalizedRow === normalizedTarget) {
                        // Find level cells
                        const levelCells = row.querySelectorAll('td.level');

                        levelCells.forEach((levelCell) => {
                            const scoreSpan = levelCell.querySelector('.scorevalue');
                            if (!scoreSpan) {
                                return;
                            }

                            const points = parseInt(scoreSpan.textContent.trim(), 10);

                            // Select level with matching points
                            if (points === targetPoints) {
                                const radioInput = levelCell.querySelector('input[type="radio"]');

                                if (!radioInput) {
                                    return;
                                }

                                // Simulate real user click on cell
                                if (levelCell.click) {
                                    levelCell.click();
                                }

                                // Click directly on radio button
                                if (radioInput.click) {
                                    radioInput.click();
                                }

                                // Force checked state after click
                                setTimeout(() => {
                                    radioInput.checked = true;

                                    // Dispatch native events
                                    if (radioInput.dispatchEvent) {
                                        radioInput.dispatchEvent(new MouseEvent('click', {
                                            bubbles: true,
                                            cancelable: true,
                                            view: window
                                        }));
                                        radioInput.dispatchEvent(new Event('change', { bubbles: true }));
                                    }

                                    // Update ARIA attributes
                                    levelCell.setAttribute('aria-checked', 'true');
                                    levelCells.forEach(otherCell => {
                                        if (otherCell !== levelCell) {
                                            otherCell.setAttribute('aria-checked', 'false');
                                            const otherRadio = otherCell.querySelector('input[type="radio"]');
                                            if (otherRadio) {
                                                otherRadio.checked = false;
                                            }
                                        }
                                    });
                                }, 50);

                                injected = true;
                            }
                        });

                        // Inject comment into remark textarea
                        const remarkTextarea = row.querySelector('td.remark textarea');
                        if (remarkTextarea && comment) {
                            remarkTextarea.value = comment;
                            remarkTextarea.dispatchEvent(new Event('input', { bubbles: true }));
                            remarkTextarea.dispatchEvent(new Event('change', { bubbles: true }));
                            injected = true;
                        }
                    }
                });
            });

            return injected;
        };

        /**
         * Injects simple numeric grade.
         * @returns {boolean} True if grade was injected successfully
         */
        const injectSimpleGrade = () => {
            if (rubricResponse || !grade) {
                return false;
            }

            const gradeInput = document.querySelector('#id_grade, input[name="grade"]');
            if (!gradeInput) {
                return false;
            }

            gradeInput.value = grade;
            gradeInput.dispatchEvent(new Event('change', { bubbles: true }));

            return true;
        };

        /**
         * Shows injection results to user.
         * @param {boolean} success Whether injection was successful
         */
        const showResults = (success) => {
            if (success) {
                Notification.addNotification({
                    message: strRubricSuccess,
                    type: 'success'
                });
            } else {
                Notification.addNotification({
                    message: strRubricFailed,
                    type: 'warning'
                });
            }
        };

        // Injection state
        let successfulInjection = false;
        let attempts = 0;
        const maxAttempts = 50;

        // Strategy 1: Polling
        const interval = setInterval(() => {
            attempts++;

            if (!successfulInjection) {
                injectMessage();
                const rubricInjected = injectRubric();
                const gradeInjected = injectSimpleGrade();

                if (rubricInjected || gradeInjected) {
                    successfulInjection = true;
                }
            }

            // Stop after successful injection or max attempts
            if ((successfulInjection && attempts > 12) || attempts > maxAttempts) {
                clearInterval(interval);
                showResults(successfulInjection);
            }
        }, 300);

        // Strategy 2: MutationObserver
        const rubricContainer = document.querySelector('.gradingform_rubric') ||
            document.querySelector('#page-content') ||
            document.body;

        if (rubricContainer) {
            const observer = new MutationObserver(() => {
                const criterionRows = document.querySelectorAll('tr.criterion');

                if (criterionRows.length > 0 && !successfulInjection) {
                    injectMessage();
                    const rubricInjected = injectRubric();
                    const gradeInjected = injectSimpleGrade();

                    if (rubricInjected || gradeInjected) {
                        successfulInjection = true;
                        observer.disconnect();
                    }
                }
            });

            observer.observe(rubricContainer, {
                childList: true,
                subtree: true
            });

            setTimeout(() => observer.disconnect(), 15000);
        }

    }).fail(Notification.exception);
};
