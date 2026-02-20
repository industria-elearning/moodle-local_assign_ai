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
 * Injects a simple numeric grade.
 *
 * @module      local_assign_ai/inject_ai/inject_simple_grade
 * @copyright   2025 Datacurso
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Injects simple numeric grade.
 *
 * @param {number|string} grade The grade to inject.
 * @param {{root?: Element|Document}} options Injection options.
 * @returns {boolean} True if grade was injected successfully
 */
export const injectSimpleGrade = (grade, options = {}) => {
    if (grade === null || grade === undefined) {
        return false;
    }

    const root = options.root || document;
    const targetUserid = options.targetUserid ? parseInt(options.targetUserid, 10) : 0;
    const candidates = Array.from(root.querySelectorAll('#id_grade, input[name="grade"]'));
    const filtered = candidates.filter((el) => {
        if (!targetUserid) {
            return true;
        }
        const form = el.closest('form');
        if (!form) {
            return true;
        }
        const userinput = form.querySelector('input[name="userid"]');
        if (!userinput) {
            return true;
        }
        return parseInt(userinput.value, 10) === targetUserid;
    });
    const input = filtered.find(el => el && (el.offsetParent !== null || el.getClientRects().length > 0))
        || filtered[0]
        || candidates[0];
    if (!input) {
        return false;
    }

    if (input.value != grade) {
        input.value = grade;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    return true;
};
