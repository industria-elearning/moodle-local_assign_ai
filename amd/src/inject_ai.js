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
        const grade = data.grade; // Calificación simple si no hay rúbrica

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
                    message: 'rubric_response debe ser un array',
                    type: 'error'
                });
                return false;
            }

            let injected = false;

            // Función para normalizar strings (eliminar tildes y espacios extra)
            const normalizeString = (str) => {
                return str
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .toLowerCase()
                    .trim();
            };

            // Iterar sobre cada criterio
            rubricData.forEach(criterionData => {
                const criterionName = criterionData.criterion;
                const targetPoints = criterionData.levels[0].points;
                const comment = criterionData.levels[0].comment;

                // Buscar todos los criterios en la tabla
                const criterionRows = document.querySelectorAll('tr.criterion');

                criterionRows.forEach(row => {
                    // Obtener el nombre del criterio desde la celda description
                    const descriptionCell = row.querySelector('td.description');
                    if (!descriptionCell) {
                        return;
                    }

                    const rowCriterionName = descriptionCell.textContent.trim();

                    // Comparar nombres normalizados (sin tildes, case-insensitive)
                    if (normalizeString(rowCriterionName) === normalizeString(criterionName)) {
                        // Buscar el nivel con los puntos correctos
                        const levelCells = row.querySelectorAll('td.level');

                        levelCells.forEach(levelCell => {
                            const scoreSpan = levelCell.querySelector('.scorevalue');
                            if (!scoreSpan) {
                                return;
                            }

                            const points = parseInt(scoreSpan.textContent.trim());

                            // Si coinciden los puntos, seleccionar este nivel
                            if (points === targetPoints) {
                                const radioInput = levelCell.querySelector('input[type="radio"]');
                                if (radioInput) {
                                    radioInput.checked = true;

                                    // Actualizar aria-checked en el td
                                    levelCell.setAttribute('aria-checked', 'true');

                                    // Remover aria-checked de otros niveles
                                    levelCells.forEach(otherCell => {
                                        if (otherCell !== levelCell) {
                                            otherCell.setAttribute('aria-checked', 'false');
                                        }
                                    });

                                    injected = true;
                                }
                            }
                        });

                        // Inyectar comentario en el textarea de remark
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
            // Solo intentar si no hay rúbrica y hay una calificación
            if (rubricResponse || !grade) {
                return false;
            }

            // Buscar el campo de calificación simple
            const gradeInput = document.querySelector('#id_grade, input[name="grade"]');

            if (!gradeInput) {
                return false;
            }

            gradeInput.value = grade;

            // Disparar evento change para que Moodle detecte el cambio
            const event = new Event('change', { bubbles: true });
            gradeInput.dispatchEvent(event);

            return true;
        };

        // Intentar inyectar con reintentos
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
                        message: 'Rúbrica inyectada exitosamente',
                        type: 'success'
                    });
                } else if (gradeInjected) {
                    Notification.addNotification({
                        message: 'Calificación inyectada exitosamente',
                        type: 'success'
                    });
                } else if (attempts > 20) {
                    Notification.addNotification({
                        message: 'No se pudo inyectar la rúbrica después de 20 intentos',
                        type: 'warning'
                    });
                }
            }
        }, 500);
    }).fail(Notification.exception);
};
