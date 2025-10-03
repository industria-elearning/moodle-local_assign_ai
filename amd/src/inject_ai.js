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

        const injectMessage = () => {
            // Buscar el textarea de feedback
            const textarea = document.querySelector('#id_assignfeedbackcomments_editor, textarea[id^="id_feedbackcomments_"]');

            if (!textarea) {
                return false;
            }

            // Insertar texto plano
            textarea.value = message;

            // 🔹 TinyMCE
            if (window.tinymce && window.tinymce.get(textarea.id)) {
                window.tinymce.get(textarea.id).setContent(message);
                return true;
            }

            // 🔹 Atto
            if (window.M && window.M.editor_atto && window.M.editor_atto.getEditorForElement) {
                const editor = window.M.editor_atto.getEditorForElement(textarea);
                if (editor) {
                    editor.setHTML(message);
                    return true;
                }
            }

            return false;
        };

        let attempts = 0;
        const interval = setInterval(() => {
            attempts++;
            if (injectMessage() || attempts > 20) {
                clearInterval(interval);
            }
        }, 500);
    }).fail(Notification.exception);
};
