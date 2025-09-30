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
 * Review page for local_assign_ai.
 *
 * @package     local_assign_ai
 * @copyright   2025 Piero Llanos
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

require_login();

$cmid = required_param('id', PARAM_INT);

// Obtener el coursemodule y el curso.
$cm = get_coursemodule_from_id('assign', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_capability('local/assign_ai:review', $context);

// Instanciar el objeto assign.
$assign = new assign($context, $cm, $course);

// Configuración de la página.
$PAGE->set_url(new moodle_url('/local/assign_ai/review.php', ['id' => $cmid]));
$PAGE->set_course($course);
$PAGE->set_context($context);
$PAGE->set_title(get_string('reviewwithai', 'local_assign_ai'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->requires->js_call_amd('local_assign_ai/review', 'init');

$PAGE->activityheader->disable();

echo $OUTPUT->header();

// Obtener lista de usuarios matriculados en el curso con capacidad de entregar.
$students = get_enrolled_users($context, 'mod/assign:submit');

// Verificar si todos los estudiantes tienen feedback pendiente o aprobado.
$allblocked = true;
foreach ($students as $student) {
    $record = $DB->get_record('local_assign_ai_pending', [
        'courseid' => $course->id,
        'assignmentid' => $cm->id,
        'userid' => $student->id,
    ]);
    if (!$record || $record->status === 'rejected') {
        $allblocked = false;
        break;
    }
}

// Título + botón revisar todos en la misma fila.
echo html_writer::start_div('d-flex justify-content-between align-items-center mb-3');
echo $OUTPUT->heading(get_string('reviewwithai', 'local_assign_ai'), 2, 'mb-0');

$reviewallurl = new moodle_url('/local/assign_ai/review_submission.php', [
    'id' => $cmid,
    'all' => 1,
]);

if ($allblocked) {
    // Botón bloqueado.
    echo html_writer::tag('button', get_string('reviewall', 'local_assign_ai'), [
        'class' => 'btn btn-warning',
        'disabled' => 'disabled',
    ]);
} else {
    // Botón activo.
    echo html_writer::link(
        $reviewallurl,
        get_string('reviewall', 'local_assign_ai'),
        ['class' => 'btn btn-warning']
    );
}
echo html_writer::end_div();

$rows = [];

foreach ($students as $student) {
    // Estado de la entrega.
    $submission = $assign->get_user_submission($student->id, false);
    if ($submission) {
        switch ($submission->status) {
            case 'submitted':
                $status = get_string('submission_submitted', 'local_assign_ai');
                break;
            case 'draft':
                $status = get_string('submission_draft', 'local_assign_ai');
                break;
            case 'new':
                $status = get_string('submission_new', 'local_assign_ai');
                break;
            default:
                $status = get_string('submission_none', 'local_assign_ai');
        }
    } else {
        $status = get_string('submission_none', 'local_assign_ai');
    }

    // Ultima modificación y archivos enviados.
    $lastmodified = '-';
    $filelinks = '-';

    if ($submission && $submission->status === 'submitted') {
        if (!empty($submission->timemodified)) {
            $lastmodified = userdate($submission->timemodified);
        }

        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $assign->get_context()->id,
            'assignsubmission_file',
            'submission_files',
            $submission->id,
            'id',
            false
        );

        if ($files) {
            $filelinksarr = [];
            foreach ($files as $file) {
                $url = moodle_url::make_pluginfile_url(
                    $file->get_contextid(),
                    $file->get_component(),
                    $file->get_filearea(),
                    $file->get_itemid(),
                    $file->get_filepath(),
                    $file->get_filename()
                );
                $filelinksarr[] = html_writer::link($url, $file->get_filename());
            }
            $filelinks = implode(', ', $filelinksarr);
        }
    }

    // Estado IA.
    $record = $DB->get_record('local_assign_ai_pending', [
        'courseid' => $course->id,
        'assignmentid' => $cm->id,
        'userid' => $student->id,
    ]);

    if ($record) {
        switch ($record->status) {
            case 'approve':
                $aistatus = get_string('statusapprove', 'local_assign_ai');
                break;
            case 'rejected':
                $aistatus = get_string('statusrejected', 'local_assign_ai');
                break;
            case 'pending':
            default:
                $aistatus = get_string('statuspending', 'local_assign_ai');
        }

        $aibutton = html_writer::tag(
            'button',
            get_string('viewdetails', 'local_assign_ai'),
            ['class' => 'btn btn-success view-details', 'data-token' => $record->approval_token]
        );
    } else {
        $aistatus = get_string('nostatus', 'local_assign_ai');
        $aibutton = html_writer::tag(
            'button',
            get_string('viewdetails', 'local_assign_ai'),
            ['class' => 'btn btn-success view-details', 'disabled' => 'disabled']
        );
    }

    // Botón azul → grader (siempre activo).
    $viewurl = new moodle_url('/local/assign_ai/review_submission.php', [
        'id' => $cmid,
        'userid' => $student->id,
        'goto' => 'grader',
    ]);
    $button = html_writer::link(
        $viewurl,
        get_string('qualify', 'local_assign_ai'),
        ['class' => 'btn btn-primary']
    );

    // Botón gris → revisar IA por usuario.
    $reviewurl = new moodle_url('/local/assign_ai/review_submission.php', [
        'id' => $cmid,
        'userid' => $student->id,
    ]);

    if ($record && in_array($record->status, ['pending', 'approve'])) {
        $reviewbtn = html_writer::tag(
            'button',
            get_string('review', 'local_assign_ai'),
            ['class' => 'btn btn-warning', 'disabled' => 'disabled']
        );
    } else {
        $reviewbtn = html_writer::link(
            $reviewurl,
            get_string('review', 'local_assign_ai'),
            ['class' => 'btn btn-warning']
        );
    }

    $rows[] = [
        'fullname'      => fullname($student),
        'email'         => $student->email,
        'status'        => $status,
        'lastmodified'  => $lastmodified,
        'files'         => $filelinks,
        'aistatus'      => $aistatus,
        'actions'       => $button . ' ' . $aibutton . ' ' . $reviewbtn,
    ];
}

$templatecontext = ['rows' => $rows];
echo $OUTPUT->render_from_template('local_assign_ai/review_table', $templatecontext);
echo $OUTPUT->footer();
