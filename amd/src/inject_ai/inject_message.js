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
 * Injects AI feedback into the grading editor.
 *
 * @module      local_assign_ai/inject_ai/inject_message
 * @copyright   2025 Datacurso
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Injects AI message into feedback editor.
 *
 * @param {string} message The message to inject.
 * @returns {boolean} True if message was injected successfully
 */
export const injectMessage = (message) => {
    if (!message) {
        return false;
    }

    const textarea = document.querySelector(
        '#id_assignfeedbackcomments_editor, textarea[id^="id_feedbackcomments_"]'
    );

    if (!textarea) {
        return false;
    }

    if (textarea.value === message) {
        return true;
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

    return true;
};
