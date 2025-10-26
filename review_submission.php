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
 * @copyright   2025 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once(__DIR__ . '/locallib.php');

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
    $student = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
    $token = process_submission_ai($assign, $course, $student, $DB, false);

    if ($token === false) {
        echo $OUTPUT->header();
        echo html_writer::link(
            new moodle_url('/local/assign_ai/review.php', ['id' => $cmid]),
            get_string('continue'),
            ['class' => 'btn btn-primary']
        );
        echo $OUTPUT->footer();
        exit;
    } else if (!$token) {
        redirect(
            new moodle_url('/local/assign_ai/review.php', ['id' => $cmid]),
            get_string('notasksfound', 'local_assign_ai'),
            null,
            \core\output\notification::NOTIFY_WARNING
        );
    }
}

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
            $record = $DB->get_record('local_assign_ai_pending', [
                'courseid' => $course->id,
                'assignmentid' => $assign->get_course_module()->id,
                'userid' => $student->id,
            ]);
            return $record ? $record->approval_token : null;
        }
        return null;
    }

    $assignment = $assign->get_instance();
    $cmid = $assign->get_course_module()->id;

    $fs = get_file_storage();
    $files = $fs->get_area_files(
        $assign->get_context()->id,
        'assignsubmission_file',
        'submission_files',
        $submission->id,
        'id',
        false
    );

    $submissioncontent = null;
    if (empty($files)) {
        $onlinetext = $DB->get_record('assignsubmission_onlinetext', [
            'submission' => $submission->id,
        ]);
        if ($onlinetext && !empty($onlinetext->onlinetext)) {
            $submissioncontent = $onlinetext->onlinetext;
        }
    }

    $payload = [
        'site_id' => md5($CFG->wwwroot),
        'course_id' => (string)$course->id,
        'course' => $course->fullname,
        'assignment_id' => (string)$assignment->id,
        'cmi_id' => (string)$cmid,
        'assignment_title' => $assignment->name,
        'assignment_description' => $assignment->intro,
        'rubric' => build_rubric_json($assign),
        'userid' => (string)$student->id,
        'student_name' => fullname($student),
        'submission_assign' => $submissioncontent,
        'maximum_grade' => (int)$assignment->grade,
    ];

    try {
        $data = client::send_to_ai($payload);
    } catch (\moodle_exception $e) {
        debugging('AI request failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        redirect(
            new moodle_url('/local/assign_ai/review.php', ['id' => $assign->get_course_module()->id]),
            get_string('error_airequest', 'local_assign_ai', $e->getMessage()),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    } catch (\Throwable $e) {
        debugging('Unexpected AI error: ' . $e->getMessage(), DEBUG_DEVELOPER);
        redirect(
            new moodle_url('/local/assign_ai/review.php', ['id' => $assign->get_course_module()->id]),
            get_string('error_airequest', 'local_assign_ai', $e->getMessage()),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    $existing = $DB->get_record('local_assign_ai_pending', [
        'courseid' => $course->id,
        'assignmentid' => $cmid,
        'userid' => $student->id,
    ]);

    if ($existing) {
        if ($countmode) {
            return null;
        }
        return $existing->approval_token;
    }

    $token = bin2hex(random_bytes(16));
    $record = (object)[
        'courseid'      => $course->id,
        'assignmentid'  => $cmid,
        'title'         => $assignment->name,
        'userid'        => $student->id,
        'message'       => $data['reply'],
        'grade'          => $data['grade'] ?? null,
        'rubric_response' => isset($data['rubric']) ? json_encode($data['rubric'], JSON_UNESCAPED_UNICODE) : null,
        'status'        => 'pending',
        'approval_token' => $token,
    ];
    $DB->insert_record('local_assign_ai_pending', $record);

    return $token;
}
