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
 * Injects assessment guide grades and comments.
 *
 * @module      local_assign_ai/inject_ai/inject_guide
 * @copyright   2025 Datacurso
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { normalizeString } from './normalize_string';

/**
 * Injects assessment guide grades and comments.
 *
 * @param {Object} guideData The guide data object.
 * @returns {boolean} True if guide was injected successfully.
 */
export const injectGuide = (guideData) => {
    let injected = false;

    Object.keys(guideData).forEach(criterionName => {
        const data = guideData[criterionName];
        if (!data) {
            return;
        }

        const grade = data.grade;
        let comments = data.reply;
        if (Array.isArray(comments)) {
            comments = comments.join(', ');
        }

        const rows = document.querySelectorAll('tr'); // Broad search for criteria rows
        rows.forEach(row => {
            if (!normalizeString(row.textContent).includes(normalizeString(criterionName))) {
                return;
            }

            const scoreInput = row.querySelector('.score input[type="text"]');
            const remarkTextarea = row.querySelector('.remark textarea');
            let rowUpdated = false;

            if (scoreInput && grade !== undefined && grade !== null) {
                if (scoreInput.value != grade) {
                    scoreInput.value = grade;
                    scoreInput.dispatchEvent(new Event('change', { bubbles: true }));
                }
                rowUpdated = true;
            }

            if (remarkTextarea && comments) {
                if (remarkTextarea.value !== comments) {
                    remarkTextarea.value = comments;
                    remarkTextarea.dispatchEvent(new Event('input', { bubbles: true }));
                    remarkTextarea.dispatchEvent(new Event('change', { bubbles: true }));
                }
                rowUpdated = true;
            }

            if (rowUpdated) {
                injected = true;
            }
        });
    });

    return injected;
};
