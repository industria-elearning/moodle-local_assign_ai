<?php
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
 * Plugin strings are defined here.
 *
 * @package     local_assign_ai
 * @category    string
 * @copyright   2025 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['actions'] = 'Acciones';
$string['aistatus'] = 'Estado IA';
$string['aitaskdone'] = 'Procesamiento de IA completado. Total de envíos procesados: {$a}';
$string['aitaskstart'] = 'Procesando envíos de IA para el curso: {$a}';
$string['aitaskuserqueued'] = 'Envío en cola para el usuario con ID {$a->id} ({$a->name})';
$string['altlogo'] = 'Logo Datacurso';
$string['backtocourse'] = 'Regresar al curso';
$string['assign_ai:changestatus'] = 'Cambiar el estado de aprobación de IAs';
$string['assign_ai:review'] = 'Revisar las sugerencias de IA para las tareas';
$string['assign_ai:viewdetails'] = 'Ver detalles de comentarios de IA';
$string['default_rubric_name'] = 'Rúbrica';
$string['email'] = 'Correo electrónico';
$string['error_airequest'] = 'Error al comunicarse con el servicio de IA: {$a}';
$string['errorparsingrubric'] = 'Error al analizar la respuesta de la rúbrica: {$a}';
$string['feedbackcomments'] = 'Comentarios';
$string['fullname'] = 'Nombre completo';
$string['grade'] = 'Calificación';
$string['gradesuccess'] = 'Calificación inyectada con éxito';
$string['lastmodified'] = 'Última modificación';
$string['manytasksreviewed'] = 'Se revisaron {$a} tareas';
$string['missingtaskparams'] = 'Faltan parámetros de la tarea. No se puede iniciar el procesamiento por lotes de IA.';
$string['modaltitle'] = 'Retroalimentación IA';
$string['norecords'] = 'No se encontraron registros';
$string['nostatus'] = 'Sin retroalimentación';
$string['nosubmissions'] = 'No se encontraron entregas para procesar.';
$string['notasksfound'] = 'No hay tarea para revisar';
$string['onetaskreviewed'] = 'Se revisó 1 tarea';
$string['pluginname'] = 'Assignment AI';
$string['privacy:metadata:local_assign_ai_pending'] = 'Almacena las retroalimentaciones generadas por IA pendientes de aprobación.';
$string['privacy:metadata:local_assign_ai_pending:approval_token'] = 'Token único utilizado para el seguimiento de aprobaciones.';
$string['privacy:metadata:local_assign_ai_pending:assignmentid'] = 'La tarea a la que corresponde esta retroalimentación de IA.';
$string['privacy:metadata:local_assign_ai_pending:courseid'] = 'El curso asociado a esta retroalimentación.';
$string['privacy:metadata:local_assign_ai_pending:grade'] = 'La calificación propuesta generada por la IA.';
$string['privacy:metadata:local_assign_ai_pending:message'] = 'El mensaje de retroalimentación generado por la IA.';
$string['privacy:metadata:local_assign_ai_pending:rubric_response'] = 'La retroalimentación de rúbrica generada por la IA.';
$string['privacy:metadata:local_assign_ai_pending:status'] = 'El estado de aprobación de la retroalimentación.';
$string['privacy:metadata:local_assign_ai_pending:title'] = 'El título de la retroalimentación generada.';
$string['privacy:metadata:local_assign_ai_pending:userid'] = 'El usuario para quien se generó la retroalimentación de la IA.';
$string['processed'] = 'Se procesaron correctamente {$a} entrega(s).';
$string['processing'] = 'Procesando';
$string['processingerror'] = 'Ocurrió un error al procesar la revisión con IA.';
$string['qualify'] = 'Calificar';
$string['queued'] = 'Todas las entregas han sido enviadas a la cola para revisión con IA. Serán procesadas en breve.';
$string['reloadpage'] = 'Recarga la página para ver los resultados actualizados.';
$string['review'] = 'Revisar';
$string['reviewall'] = 'Revisar todos';
$string['reviewwithai'] = 'Revisión con IA';
$string['rubricfailed'] = 'No se logró inyectar la rúbrica después de 20 intentos';
$string['rubricmustarray'] = 'La respuesta a la rúbrica debe ser una matriz.';
$string['rubricsuccess'] = 'Rúbrica inyectada con éxito';
$string['save'] = 'Guardar';
$string['saveapprove'] = 'Guardar y Aprobar';
$string['status'] = 'Estado';
$string['statusapprove'] = 'Aprobado';
$string['statuspending'] = 'Pendiente';
$string['statusrejected'] = 'Rechazado';
$string['submission_draft'] = 'Borrador';
$string['submission_new'] = 'Nuevo';
$string['submission_none'] = 'Sin entrega';
$string['submission_submitted'] = 'Enviado';
$string['submittedfiles'] = 'Archivos enviados';
$string['unexpectederror'] = 'Ocurrió un error inesperado: {$a}';
$string['viewdetails'] = 'Ver detalles';
