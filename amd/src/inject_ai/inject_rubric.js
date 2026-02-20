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
 * @param {{detailed?: boolean, root?: Element|Document}} options Injection options.
 * @returns {boolean|Object} True or detailed injection result.
 */
export const injectRubric = (rubricData, strRubricError, options = {}) => {
    const detailed = !!options.detailed;
    const root = options.root || document;
    if (!Array.isArray(rubricData)) {
        Notification.addNotification({
            message: strRubricError,
            type: 'error'
        });
        if (detailed) {
            return {
                injected: false,
                totalCriteria: 0,
                matchedCriteria: 0,
                fullyAppliedCriteria: 0,
                availableCriteria: [],
                details: [],
            };
        }
        return false;
    }

    let anyInjected = false;
    const allrows = Array.from(root.querySelectorAll('tr.criterion'));
    const visiblerows = allrows.filter(row => row.offsetParent !== null || row.getClientRects().length > 0);
    const criterionRows = visiblerows.length ? visiblerows : allrows;
    const availableCriteria = criterionRows
        .map((row) => {
            const descriptionCell = row.querySelector('td.description');
            return descriptionCell ? descriptionCell.textContent.trim() : '';
        })
        .filter((value) => !!value)
        .slice(0, 20);
    const details = [];
    let matchedCriteria = 0;
    let fullyAppliedCriteria = 0;

    if (criterionRows.length === 0) {
        if (detailed) {
            return {
                injected: false,
                totalCriteria: rubricData.length,
                matchedCriteria: 0,
                fullyAppliedCriteria: 0,
                availableCriteria,
                details: rubricData.map((criterionData) => ({
                    criterion: criterionData?.criterion || '',
                    rowFound: false,
                    levelFound: false,
                    levelApplied: false,
                    commentApplied: false,
                    expectedPoints: criterionData?.levels?.[0]?.points ?? null,
                })),
            };
        }
        return false;
    }

    rubricData.forEach((criterionData) => {
        const criterionName = criterionData.criterion;
        const targetPoints = criterionData.levels[0].points;
        const comment = criterionData.levels[0].comment;
        const criterionResult = {
            criterion: criterionName,
            rowFound: false,
            levelFound: false,
            levelApplied: false,
            commentApplied: false,
            expectedPoints: targetPoints,
            selectedPointsBefore: null,
            selectedPointsAfter: null,
            radioFound: false,
            radioDisabled: false,
            mutationAttempted: false,
            checkedAfterManualSet: false,
            checkedAfterEvents: false,
            checkedAfterFallbackClick: false,
            classAfterMutation: false,
        };

        const row = criterionRows.find((rowItem) => {
            const descriptionCell = rowItem.querySelector('td.description');
            if (!descriptionCell) {
                return false;
            }
            const rowCriterionName = descriptionCell.textContent.trim();
            return normalizeString(rowCriterionName) === normalizeString(criterionName);
        });

        if (!row) {
            details.push(criterionResult);
            return;
        }

        criterionResult.rowFound = true;
        matchedCriteria++;

        const levelCells = row.querySelectorAll('td.level');

        levelCells.forEach((levelCell) => {
            const radioInput = levelCell.querySelector('input[type="radio"]');
            const isSelected = !!radioInput?.checked || levelCell.classList.contains('checked');
            if (!radioInput || !isSelected) {
                return;
            }
            const scoreSpan = levelCell.querySelector('.scorevalue');
            if (!scoreSpan) {
                return;
            }
            criterionResult.selectedPointsBefore = parseFloat(scoreSpan.textContent.trim());
        });

        levelCells.forEach((levelCell) => {
            const scoreSpan = levelCell.querySelector('.scorevalue');
            if (!scoreSpan) {
                return;
            }

            const points = parseFloat(scoreSpan.textContent.trim());
            if (Math.abs(points - targetPoints) >= 0.1) {
                return;
            }

            const radioInput = levelCell.querySelector('input[type="radio"]');
            if (!radioInput) {
                return;
            }

            criterionResult.levelFound = true;
            criterionResult.radioFound = true;
            criterionResult.radioDisabled = !!radioInput.disabled;

            const rowRadios = Array.from(row.querySelectorAll('input[type="radio"]'));

            if (!radioInput.checked && !radioInput.disabled) {
                criterionResult.mutationAttempted = true;
                const rowLevels = Array.from(row.querySelectorAll('td.level'));

                rowRadios.forEach((radio) => {
                    const shouldCheck = radio === radioInput;
                    radio.checked = shouldCheck;
                    radio.defaultChecked = shouldCheck;
                    if (shouldCheck) {
                        radio.setAttribute('checked', 'checked');
                    } else {
                        radio.removeAttribute('checked');
                    }
                });

                criterionResult.checkedAfterManualSet = !!radioInput.checked;

                rowLevels.forEach((cell) => {
                    const isTarget = cell === levelCell;
                    cell.classList.toggle('checked', isTarget);
                    cell.setAttribute('aria-checked', isTarget ? 'true' : 'false');
                });

                criterionResult.classAfterMutation = levelCell.classList.contains('checked');

                radioInput.dispatchEvent(new Event('input', { bubbles: true }));
                radioInput.dispatchEvent(new Event('change', { bubbles: true }));
                criterionResult.checkedAfterEvents = !!radioInput.checked;

                if (!radioInput.checked && radioInput.click) {
                    radioInput.click();
                }

                if (!radioInput.checked && levelCell.dispatchEvent) {
                    levelCell.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
                }

                criterionResult.checkedAfterFallbackClick = !!radioInput.checked;
            }

            if (radioInput.checked || levelCell.classList.contains('checked')) {
                criterionResult.levelApplied = true;
            }

            if (radioInput.checked) {
                criterionResult.selectedPointsAfter = points;
            }
        });

        const remarkTextarea = row.querySelector('td.remark textarea');
        if (remarkTextarea && comment) {
            if (remarkTextarea.value !== comment) {
                remarkTextarea.value = comment;
                remarkTextarea.dispatchEvent(new Event('input', { bubbles: true }));
                remarkTextarea.dispatchEvent(new Event('change', { bubbles: true }));
            }
            criterionResult.commentApplied = remarkTextarea.value === comment;
        }

        if (criterionResult.levelApplied) {
            anyInjected = true;
        }

        if (criterionResult.levelApplied && (!comment || criterionResult.commentApplied)) {
            fullyAppliedCriteria++;
        }

        details.push(criterionResult);
    });

    const injected = anyInjected && fullyAppliedCriteria === rubricData.length;
    if (detailed) {
        return {
            injected,
            totalCriteria: rubricData.length,
            matchedCriteria,
            fullyAppliedCriteria,
            availableCriteria,
            details,
        };
    }
    return injected;
};
