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
 * Injects rubric selections and comments.
 *
 * @module      local_assign_ai/inject_ai/inject_rubric
 * @copyright   2025 Datacurso
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Notification from 'core/notification';
import { normalizeString } from './normalize_string';

/**
 * Injects rubric selections and comments.
 *
 * @param {Array} rubricData The rubric data array.
 * @param {string} strRubricError Error string for validation.
 * @returns {boolean} True if rubric was injected successfully
 */
export const injectRubric = (rubricData, strRubricError) => {
    if (!Array.isArray(rubricData)) {
        Notification.addNotification({
            message: strRubricError,
            type: 'error'
        });
        return false;
    }

    let injected = false;
    const criterionRows = document.querySelectorAll('tr.criterion');

    if (criterionRows.length === 0) {
        return false;
    }

    rubricData.forEach((criterionData) => {
        const criterionName = criterionData.criterion;
        const targetPoints = criterionData.levels[0].points;
        const comment = criterionData.levels[0].comment;

        criterionRows.forEach((row) => {
            const descriptionCell = row.querySelector('td.description');
            if (!descriptionCell) {
                return;
            }

            const rowCriterionName = descriptionCell.textContent.trim();
            if (normalizeString(rowCriterionName) !== normalizeString(criterionName)) {
                return;
            }

            const levelCells = row.querySelectorAll('td.level');
            levelCells.forEach((levelCell) => {
                const scoreSpan = levelCell.querySelector('.scorevalue');
                if (!scoreSpan) {
                    return;
                }

                const points = parseFloat(scoreSpan.textContent.trim());

                if (Math.abs(points - targetPoints) < 0.1) {
                    const radioInput = levelCell.querySelector('input[type="radio"]');
                    if (!radioInput) {
                        return;
                    }

                    if (radioInput.checked) {
                        injected = true;
                    } else {
                        if (levelCell.click) {
                            levelCell.click();
                        }
                        if (radioInput.click) {
                            radioInput.click();
                        }

                        setTimeout(() => {
                            radioInput.checked = true;
                            radioInput.dispatchEvent(new Event('change', { bubbles: true }));
                            levelCell.setAttribute('aria-checked', 'true');
                            levelCell.parentElement.querySelectorAll('td.level').forEach(c => {
                                if (c !== levelCell) {
                                    c.setAttribute('aria-checked', 'false');
                                }
                            });
                        }, 50);
                        injected = true;
                    }
                }
            });

            const remarkTextarea = row.querySelector('td.remark textarea');
            if (remarkTextarea && comment) {
                if (remarkTextarea.value !== comment) {
                    remarkTextarea.value = comment;
                    remarkTextarea.dispatchEvent(new Event('input', { bubbles: true }));
                    remarkTextarea.dispatchEvent(new Event('change', { bubbles: true }));
                }
                injected = true;
            }
        });
    });

    return injected;
};
