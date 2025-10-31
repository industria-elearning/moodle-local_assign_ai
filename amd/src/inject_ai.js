import Ajax from 'core/ajax';
import Notification from 'core/notification';

export const init = (token) => {
    if (!token) {
        return;
    }

    Ajax.call([{
        methodname: 'local_assign_ai_get_details',
        args: { token: token },
    }])[0].done(data => {
        const message = data.message;
        const rubricResponse = data.rubric_response;
        const grade = data.grade; // Simple grade if there is no rubric

        const injectMessage = () => {
            const textarea = document.querySelector('#id_assignfeedbackcomments_editor, textarea[id^="id_feedbackcomments_"]');

            if (!textarea) {
                return false;
            }

            textarea.value = message;

            // TinyMCE
            if (window.tinymce && window.tinymce.get(textarea.id)) {
                window.tinymce.get(textarea.id).setContent(message);
                return true;
            }

            // Atto
            if (window.M && window.M.editor_atto && window.M.editor_atto.getEditorForElement) {
                const editor = window.M.editor_atto.getEditorForElement(textarea);
                if (editor) {
                    editor.setHTML(message);
                    return true;
                }
            }

            return false;
        };

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
                    message: 'Error parsing rubric_response: ' + e.message,
                    type: 'error'
                });
                return false;
            }

            if (!Array.isArray(rubricData)) {
                Notification.addNotification({
                    message: 'rubric_response must be an array',
                    type: 'error'
                });
                return false;
            }

            let injected = false;

            // Function to normalize strings (remove accents and extra spaces)
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

                // Find all criteria in the table
                const criterionRows = document.querySelectorAll('tr.criterion');

                criterionRows.forEach(row => {
                    // Get the criterion name from the description cell
                    const descriptionCell = row.querySelector('td.description');
                    if (!descriptionCell) {
                        return;
                    }

                    const rowCriterionName = descriptionCell.textContent.trim();

                    // Compare normalized names (no accents, case-insensitive)
                    if (normalizeString(rowCriterionName) === normalizeString(criterionName)) {
                        // Find the level with the correct points
                        const levelCells = row.querySelectorAll('td.level');

                        levelCells.forEach(levelCell => {
                            const scoreSpan = levelCell.querySelector('.scorevalue');
                            if (!scoreSpan) {
                                return;
                            }

                            const points = parseInt(scoreSpan.textContent.trim());

                            // If points match, select this level
                            if (points === targetPoints) {
                                const radioInput = levelCell.querySelector('input[type="radio"]');
                                if (radioInput) {
                                    radioInput.checked = true;

                                    // Update aria-checked on the td
                                    levelCell.setAttribute('aria-checked', 'true');

                                    // Remove aria-checked from other levels
                                    levelCells.forEach(otherCell => {
                                        if (otherCell !== levelCell) {
                                            otherCell.setAttribute('aria-checked', 'false');
                                        }
                                    });

                                    injected = true;
                                }
                            }
                        });

                        // Inject comment into the remark textarea
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

        const injectSimpleGrade = () => {
            // Only try if there is no rubric and a grade exists
            if (rubricResponse || !grade) {
                return false;
            }

            // Find the simple grade field
            const gradeInput = document.querySelector('#id_grade, input[name="grade"]');

            if (!gradeInput) {
                return false;
            }

            gradeInput.value = grade;

            // Trigger change event so Moodle detects the update
            const event = new Event('change', { bubbles: true });
            gradeInput.dispatchEvent(event);

            return true;
        };

        // Try injection with retries
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
                        message: 'Rubric successfully injected',
                        type: 'success'
                    });
                } else if (gradeInjected) {
                    Notification.addNotification({
                        message: 'Grade successfully injected',
                        type: 'success'
                    });
                } else if (attempts > 20) {
                    Notification.addNotification({
                        message: 'Failed to inject rubric after 20 attempts',
                        type: 'warning'
                    });
                }
            }
        }, 500);
    }).fail(Notification.exception);
};
