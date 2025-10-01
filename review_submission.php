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
 * Review submission processor for local_assign_ai.
 *
 * @package     local_assign_ai
 * @copyright   2025 Piero Llanos <piero@datacurso.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__.'/../../config.php');
require_once($CFG->dirroot.'/mod/assign/locallib.php');
require_once(__DIR__.'/locallib.php');

use local_assign_ai\api\client;

require_login();

$cmid   = required_param('id', PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$all    = optional_param('all', 0, PARAM_BOOL);
$goto   = optional_param('goto', '', PARAM_ALPHA);

$cm     = get_coursemodule_from_id('assign', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_capability('local/assign_ai:review', $context);

$assign = new assign($context, $cm, $course);

$token = null;
$processedcount = 0;

if ($all) {
    // Revisar todos los estudiantes.
    $students = get_enrolled_users($context, 'mod/assign:submit');
    foreach ($students as $student) {
        $result = process_submission_ai($assign, $course, $student, $DB, true);
        if ($result) {
            $token = $result;
            $processedcount++;
        }
    }

    if ($processedcount === 0) {
        redirect(
            new moodle_url('/local/assign_ai/review.php', ['id' => $cmid]),
            get_string('notasksfound', 'local_assign_ai'),
            null,
            \core\output\notification::NOTIFY_WARNING
        );
    } else {
        $msg = $processedcount === 1
            ? get_string('onetaskreviewed', 'local_assign_ai')
            : get_string('manytasksreviewed', 'local_assign_ai', $processedcount);

        redirect(
            new moodle_url('/local/assign_ai/review.php', ['id' => $cmid]),
            $msg,
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

} else if ($userid) {
    // Revisar solo un usuario.
    $student = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
    $token = process_submission_ai($assign, $course, $student, $DB, false);

    if (!$token) {
        redirect(
            new moodle_url('/local/assign_ai/review.php', ['id' => $cmid]),
            get_string('notasksfound', 'local_assign_ai'),
            null,
            \core\output\notification::NOTIFY_WARNING
        );
    }
}

// 🔹 Redirección final
if ($goto === 'grader' && $userid) {
    $params = [
        'id' => $cmid,
        'action' => 'grader',
        'userid' => $userid,
    ];
    if ($token) {
        $params['aitoken'] = $token;
    }
    redirect(new moodle_url('/mod/assign/view.php', $params));
} else {
    redirect(new moodle_url('/local/assign_ai/review.php', ['id' => $cmid]));
}

/**
 * Procesa la entrega de un usuario con IA y guarda como pendiente en local_assign_ai_pending.
 *
 * @package    local_assign_ai
 * @param assign $assign Objeto asignación.
 * @param stdClass $course Curso de Moodle.
 * @param stdClass $student Usuario estudiante.
 * @param moodle_database $DB Base de datos global.
 * @param bool $countmode Si es true, estamos en "Revisar todos".
 * @return string|null El token generado o existente, o null si no se procesa nada.
 */
function process_submission_ai(assign $assign, $course, $student, $DB, $countmode = false) {
    global $CFG;

    $submission = $assign->get_user_submission($student->id, false);
    if (!$submission || $submission->status !== 'submitted') {
        if (!$countmode) {
            // Para "Revisar uno": devolver token existente si lo hay.
            $record = $DB->get_record('local_assign_ai_pending', [
                'courseid' => $course->id,
                'assignmentid' => $assign->get_course_module()->id,
                'userid' => $student->id,
            ]);
            return $record ? $record->approval_token : null;
        }
        return null; // En "Revisar todos" no se cuenta nada.
    }

    $fs = get_file_storage();
    $files = $fs->get_area_files(
        $assign->get_context()->id,
        'assignsubmission_file',
        'submission_files',
        $submission->id,
        'id',
        false,
    );

    if (empty($files)) {
        return null;
    }

    $file = reset($files);
    $filepath = $CFG->dataroot . '/temp/assign_ai/' . $file->get_filename();
    @mkdir(dirname($filepath), 0777, true);
    $file->copy_content_to($filepath);

    $textcontent = "Contenido convertido del archivo: " . $file->get_filename();

    $assignmentdata = [
        'id' => $assign->get_course_module()->id,
        'title' => $assign->get_instance()->name,
        'description' => $assign->get_instance()->intro,
    ];
    $rubric = build_rubric_json($assign);

    $payload = [
        'course' => $course->fullname,
        'assignment' => $assignmentdata,
        'rubric' => $rubric,
        'student' => [
            'id' => $student->id,
            'name' => fullname($student),
            'submission_assign' => $textcontent,
        ],
    ];

    $data = client::send_to_ai($payload);

    $existing = $DB->get_record('local_assign_ai_pending', [
        'courseid' => $course->id,
        'assignmentid' => $assign->get_course_module()->id,
        'userid' => $student->id,
    ]);

    if ($existing) {
        if ($existing->status === 'approve' || $existing->status === 'rejected') {
            // Se cuenta como revisado.
            return $existing->approval_token;
        }

        if ($existing->status === 'pending') {
            // ⚡ En revisar todos no se cuenta.
            if ($countmode) {
                return null;
            }
            return $existing->approval_token;
        }

        // Actualización genérica (aunque normalmente no llega aquí).
        $existing->message = $data['reply'];
        $existing->timemodified = time();
        $DB->update_record('local_assign_ai_pending', $existing);
        return $existing->approval_token;

    } else {
        // Crear nuevo feedback.
        $token = bin2hex(random_bytes(16));
        $record = (object)[
            'courseid'      => $course->id,
            'assignmentid'  => $assign->get_course_module()->id,
            'title'         => $assign->get_instance()->name,
            'userid'        => $student->id,
            'message'       => $data['reply'],
            'status'        => 'pending',
            'approval_token' => $token,
            'timecreated'   => time(),
            'timemodified'  => time(),
        ];
        $DB->insert_record('local_assign_ai_pending', $record);
        return $token;
    }
}
