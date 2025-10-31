import ModalFactory from 'core/modal_factory';
import ModalEvents from 'core/modal_events';
import Ajax from 'core/ajax';
import Notification from 'core/notification';
import { getTinyMCE } from 'editor_tiny/loader';
import * as TinyEditor from 'editor_tiny/editor';
import { get_string as getString } from 'core/str';

export const init = () => {
    document.querySelectorAll('.view-details').forEach(btn => {
        btn.addEventListener('click', e => {
            const token = e.currentTarget.dataset.token;

            Ajax.call([{
                methodname: 'local_assign_ai_get_details',
                args: { token }
            }])[0].done(async data => {
                const [title, saveLabel, saveApproveLabel] = await Promise.all([
                    getString('modaltitle', 'local_assign_ai'),
                    getString('save', 'local_assign_ai'),
                    getString('saveapprove', 'local_assign_ai'),
                ]);

                const modal = await ModalFactory.create({
                    type: ModalFactory.types.DEFAULT,
                    title: title,
                    body: `
            <textarea id="airesponse-edit" class="form-control" rows="8">${data.message || ''}</textarea>
            <div class="mt-2">
              <button class="btn btn-success save-ai" data-token="${token}">${saveLabel}</button>
              <button class="btn btn-primary approve-ai" data-token="${token}">${saveApproveLabel}</button>
            </div>
          `,
                    large: true,
                });

                modal.show();

                const root = modal.getRoot();
                const textarea = root.find('#airesponse-edit')[0];

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
                    // Silent fallback to normal textarea
                    // console.warn('Tiny not available:', err);
                }

                const getContent = () => {
                    const inst = tinymce && tinymce.get(textarea.id);
                    return inst ? inst.getContent() : textarea.value;
                };

                // Save
                root.on('click', '.save-ai', e => {
                    e.preventDefault();
                    const newMessage = getContent();
                    Ajax.call([{
                        methodname: 'local_assign_ai_update_response',
                        args: { token, message: newMessage },
                    }])[0].done(() => location.reload())
                        .fail(Notification.exception);
                });

                // Save and approve
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

                // Reject
                root.on('click', '.reject-ai', e => {
                    e.preventDefault();
                    Ajax.call([{
                        methodname: 'local_assign_ai_change_status',
                        args: { token, action: 'rejected' },
                    }])[0].done(() => location.reload())
                        .fail(Notification.exception);
                });

                // Destroy TinyMCE instance when modal closes
                root.on(ModalEvents.hidden, () => {
                    const inst = tinymce && tinymce.get(textarea.id);
                    if (inst) { inst.remove(); }
                });
            }).fail(Notification.exception);
        });
    });
};
