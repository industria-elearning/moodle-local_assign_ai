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
        strGradeSuccess,
        strRubricFailed
    ] = await Promise.all([
        getString('errorparsingrubric', 'local_assign_ai'),
        getString('rubricmustarray', 'local_assign_ai'),
        getString('rubricsuccess', 'local_assign_ai'),
        getString('gradesuccess', 'local_assign_ai'),
        getString('rubricfailed', 'local_assign_ai'),
    ]);

    // Retrieve AI-generated feedback details
    Ajax.call([{
        methodname: 'local_assign_ai_get_details',
        args: { token: token },
    }])[0].done(data => {
        const message = data.message;
        const rubricResponse = data.rubric_response;
        const grade = data.grade; // Simple grade if there is no rubric

        // --- Inject message into feedback editor ---
        const injectMessage = () => {
            const textarea = document.querySelector('#id_assignfeedbackcomments_editor, textarea[id^="id_feedbackcomments_"]');

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

            return false;
        };

        // --- Inject rubric selections and comments ---
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

            // Normalize text (remove accents and extra spaces)
            const normalizeString = (str) => {
                return str
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .toLowerCase()
                    .trim();
            };

            // Iterate over each criterion
            rubricData.forEach(criterionData => {
                const criterionName = criterionData.criterion;
                const targetPoints = criterionData.levels[0].points;
                const comment = criterionData.levels[0].comment;

                // Find all criteria rows
                const criterionRows = document.querySelectorAll('tr.criterion');

                criterionRows.forEach(row => {
                    const descriptionCell = row.querySelector('td.description');
                    if (!descriptionCell) {
                        return;
                    }

                    const rowCriterionName = descriptionCell.textContent.trim();

                    // Compare normalized names (case-insensitive, no accents)
                    if (normalizeString(rowCriterionName) === normalizeString(criterionName)) {
                        // Find level cells
                        const levelCells = row.querySelectorAll('td.level');

                        levelCells.forEach(levelCell => {
                            const scoreSpan = levelCell.querySelector('.scorevalue');
                            if (!scoreSpan) {
                                return;
                            }

                            const points = parseInt(scoreSpan.textContent.trim());

                            // Select level with matching points
                            if (points === targetPoints) {
                                const radioInput = levelCell.querySelector('input[type="radio"]');
                                if (radioInput) {
                                    radioInput.checked = true;

                                    // Update aria-checked attributes
                                    levelCell.setAttribute('aria-checked', 'true');
                                    levelCells.forEach(otherCell => {
                                        if (otherCell !== levelCell) {
                                            otherCell.setAttribute('aria-checked', 'false');
                                        }
                                    });

                                    injected = true;
                                }
                            }
                        });

                        // Inject comment
                        const remarkTextarea = row.querySelector('td.remark textarea');
                        if (remarkTextarea && comment) {
                            remarkTextarea.value = comment;
                            injected = true;
                        }
                    }
                });
            });

            return injected;
        };

        // --- Inject simple numeric grade ---
        const injectSimpleGrade = () => {
            if (rubricResponse || !grade) {
                return false;
            }

            const gradeInput = document.querySelector('#id_grade, input[name="grade"]');
            if (!gradeInput) {
                return false;
            }

            gradeInput.value = grade;

            // Trigger change event for Moodle detection
            const event = new Event('change', { bubbles: true });
            gradeInput.dispatchEvent(event);

            return true;
        };

        let attempts = 0;
        const interval = setInterval(() => {
            attempts++;
            const messageInjected = injectMessage();
            const rubricInjected = injectRubric();
            const gradeInjected = injectSimpleGrade();

            if ((messageInjected || rubricInjected || gradeInjected) || attempts > 20) {
                clearInterval(interval);

                if (rubricInjected) {
                    Notification.addNotification({
                        message: strRubricSuccess,
                        type: 'success'
                    });
                } else if (gradeInjected) {
                    Notification.addNotification({
                        message: strGradeSuccess,
                        type: 'success'
                    });
                } else if (attempts > 20) {
                    Notification.addNotification({
                        message: strRubricFailed,
                        type: 'warning'
                    });
                }
            }
        }, 500);
    }).fail(Notification.exception);
};
