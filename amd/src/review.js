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
 * Modal for reviewing AI-generated comments (Mustache-based).
 *
 * @module      local_assign_ai/review
 * @copyright   2025 Datacurso
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ModalFactory from 'core/modal_factory';
import ModalEvents from 'core/modal_events';
import Ajax from 'core/ajax';
import Notification from 'core/notification';
import { getTinyMCE } from 'editor_tiny/loader';
import * as TinyEditor from 'editor_tiny/editor';
import Templates from 'core/templates';
import { get_string as getString } from 'core/str';

export const init = () => {
    document.querySelectorAll('.view-details').forEach(btn => {
        btn.addEventListener('click', e => {
            const token = e.currentTarget.dataset.token;

            Ajax.call([{
                methodname: 'local_assign_ai_get_details',
                args: { token }
            }])[0].done(async data => {
                // Load localized strings.
                const [title, saveLabel, saveApproveLabel] = await Promise.all([
                    getString('modaltitle', 'local_assign_ai'),
                    getString('save', 'local_assign_ai'),
                    getString('saveapprove', 'local_assign_ai'),
                ]);

                // Render Mustache template.
                const bodyHtml = await Templates.render('local_assign_ai/review_modal', {
                    message: data.message || '',
                    token: token,
                    savelabel: saveLabel,
                    saveapprovelabel: saveApproveLabel
                });

                // Create the modal.
                const modal = await ModalFactory.create({
                    type: ModalFactory.types.DEFAULT,
                    title: title,
                    body: bodyHtml,
                    large: true,
                });

                modal.show();

                const root = modal.getRoot();
                const textarea = root.find('#airesponse-edit')[0];

                // Initialize TinyMCE editor.
                let tinymce;
                try {
                    tinymce = await getTinyMCE();
                    const base = TinyEditor.getStandardConfig ? TinyEditor.getStandardConfig() : {};
                    await tinymce.init({
                        ...base,
                        target: textarea,
                        menubar: base.menubar ?? false,
                        plugins: base.plugins ?? 'lists link table code',
                        toolbar: base.toolbar ?? 'undo redo | bold italic underline | bullist numlist | link | removeformat | code',
                    });
                } catch (err) {
                    // Fallback to plain textarea if TinyMCE is not available.
                    // console.warn('TinyMCE not available:', err);
                }

                const getContent = () => {
                    const inst = tinymce && tinymce.get(textarea.id);
                    return inst ? inst.getContent() : textarea.value;
                };

                // Save action.
                root.on('click', '.save-ai', e => {
                    e.preventDefault();
                    const newMessage = getContent();
                    Ajax.call([{
                        methodname: 'local_assign_ai_update_response',
                        args: { token, message: newMessage },
                    }])[0].done(() => location.reload())
                        .fail(Notification.exception);
                });

                // Save and approve action.
                root.on('click', '.approve-ai', e => {
                    e.preventDefault();
                    const newMessage = getContent();
                    Ajax.call([{
                        methodname: 'local_assign_ai_update_response',
                        args: { token, message: newMessage },
                    }])[0].done(() => {
                        Ajax.call([{
                            methodname: 'local_assign_ai_change_status',
                            args: { token, action: 'approve' },
                        }])[0].done(() => location.reload())
                            .fail(Notification.exception);
                    }).fail(Notification.exception);
                });

                // Reject action.
                root.on('click', '.reject-ai', e => {
                    e.preventDefault();
                    Ajax.call([{
                        methodname: 'local_assign_ai_change_status',
                        args: { token, action: 'rejected' },
                    }])[0].done(() => location.reload())
                        .fail(Notification.exception);
                });

                // Destroy TinyMCE instance when modal is closed.
                root.on(ModalEvents.hidden, () => {
                    const inst = tinymce && tinymce.get(textarea.id);
                    if (inst) { inst.remove(); }
                });
            }).fail(Notification.exception);
        });
    });
};
