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
 * Guard assignment-level AI enable switch when globally disabled.
 *
 * @module     local_assign_ai/assignment_enable_guard
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** @type {boolean} */
let handlingchange = false;

/**
 * Initialize global-disable guard for assignment AI select.
 *
 * @param {string} selector CSS selector for the enable AI select.
 * @param {string} message Alert message.
 * @param {string} dismisslabel Dismiss button label.
 */
export const init = (selector, message, dismisslabel) => {
    const element = document.querySelector(selector);
    if (!element) {
        return;
    }

    const createAlert = () => {
        const existing = document.getElementById('local-assign-ai-global-disabled-alert');
        if (existing) {
            existing.remove();
        }

        const alert = document.createElement('div');
        alert.id = 'local-assign-ai-global-disabled-alert';
        alert.classList.add('alert', 'alert-info', 'alert-block', 'fade', 'in', 'alert-dismissible', 'mt-3');
        alert.setAttribute('role', 'alert');
        alert.setAttribute('data-aria-autofocus', 'true');
        alert.appendChild(document.createTextNode(message));

        const closebutton = document.createElement('button');
        closebutton.type = 'button';
        closebutton.classList.add('btn-close');
        closebutton.setAttribute('data-dismiss', 'alert');

        const closeicon = document.createElement('span');
        closeicon.setAttribute('aria-hidden', 'true');
        closeicon.textContent = '×';

        const closetext = document.createElement('span');
        closetext.classList.add('sr-only');
        closetext.textContent = dismisslabel;

        closebutton.appendChild(closeicon);
        closebutton.appendChild(closetext);
        closebutton.addEventListener('click', () => {
            alert.remove();
        });
        alert.appendChild(closebutton);

        const formitem = element.closest('.fitem') || element.parentElement;
        if (formitem) {
            formitem.appendChild(alert);
        }
    };

    element.addEventListener('change', () => {
        if (handlingchange || element.value !== '1') {
            return;
        }

        handlingchange = true;
        element.value = '0';
        element.dispatchEvent(new Event('change'));
        handlingchange = false;

        createAlert();
    });
};
