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
 * @copyright   2025 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_assign_ai\assign_submission;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

try {
    $cmid = required_param('id', PARAM_INT);

    // Get the course module and the course.
    $cm = get_coursemodule_from_id('assign', $cmid, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $context = context_module::instance($cm->id);

    // Asegurar login vinculado al módulo para integrar navegación correctamente.
    require_login($course, true, $cm);

    require_capability('local/assign_ai:review', $context);

    // Instantiate the assign object.
    $assign = new assign($context, $cm, $course);

    // Validate Datacurso AI provider configuration.
    if (!\aiprovider_datacurso\webservice_config::is_configured()) {
        $setupurl = \aiprovider_datacurso\webservice_config::get_url();
        $messageparams = (object)['url' => $setupurl->out(false)];
        \core\notification::error(get_string('error_ws_not_configured', 'local_assign_ai', $messageparams));
    }

    // Page configuration.
    $PAGE->set_url(new moodle_url('/local/assign_ai/review.php', ['id' => $cmid]));
    $PAGE->set_course($course);
    $PAGE->set_context($context);
    $PAGE->set_title(get_string('reviewwithai', 'local_assign_ai'));
    $PAGE->set_heading(format_string($course->fullname));
    $PAGE->requires->js_call_amd('local_assign_ai/review', 'init');
    $PAGE->requires->js_call_amd('local_assign_ai/review_with_ai', 'init');
    $PAGE->requires->js_call_amd('local_assign_ai/review_progress', 'init', [$cmid]);
    $PAGE->requires->css('/local/assign_ai/styles/review.css');

    $PAGE->activityheader->disable();

    echo $OUTPUT->header();

    // Get the list of enrolled users with submission capability.
    $students = get_enrolled_users($context, 'mod/assign:submit');

    $pendingcount = $DB->count_records('local_assign_ai_pending', [
        'courseid' => $course->id,
        'assignmentid' => $cm->id,
        'status' => assign_submission::STATUS_INITIAL,
    ]);
    $allblocked = ($pendingcount === 0);
    $hasinitial = ($pendingcount > 0);

    $pendingforapprove = $DB->count_records('local_assign_ai_pending', [
        'courseid' => $course->id,
        'assignmentid' => $cm->id,
        'status' => assign_submission::STATUS_PENDING,
    ]);
    $haspending = ($pendingforapprove > 0);

    $rows = [];

    // Build table from pending records with status initial or pending.
    $statuses = [assign_submission::STATUS_INITIAL, assign_submission::STATUS_PENDING];
    list($insql, $inparams) = $DB->get_in_or_equal($statuses, SQL_PARAMS_NAMED, 'st');
    $select = "courseid = :courseid AND assignmentid = :assignmentid AND status $insql";
    $inparams['courseid'] = $course->id;
    $inparams['assignmentid'] = $cm->id;
    $pendings = $DB->get_records_select('local_assign_ai_pending', $select, $inparams, 'timemodified DESC, id DESC');

    foreach ($pendings as $record) {
        $student = $DB->get_record('user', ['id' => $record->userid], '*', MUST_EXIST);

        // Submission info.
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

        $lastmodified = '-';
        $filesout = [];
        if ($submission) {
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
                foreach ($files as $file) {
                    $url = moodle_url::make_pluginfile_url(
                        $file->get_contextid(),
                        $file->get_component(),
                        $file->get_filearea(),
                        $file->get_itemid(),
                        $file->get_filepath(),
                        $file->get_filename()
                    );
                    $filesout[] = [
                        'url' => $url->out(false),
                        'name' => $file->get_filename(),
                    ];
                }
            }
        }

        $grade = $record->grade !== null ? $record->grade : '-';
        $progress = isset($record->progress) ? (int)$record->progress : 0;
        $inprogress = ($progress > 0 && $progress < 100);
        $isinitial = ($record->status === assign_submission::STATUS_INITIAL);
        $ispending = ($record->status === assign_submission::STATUS_PENDING);

        if ($isinitial) {
            $statebadge = get_string('aistatus_initial_short', 'local_assign_ai');
            $statehint = get_string('aistatus_initial_help', 'local_assign_ai');
            $statebadgeclass = 'badge bg-secondary';
            $canrequestai = true;
            $canapproveai = false;
        } else {
            $statebadge = get_string('aistatus_pending_short', 'local_assign_ai');
            $statehint = get_string('aistatus_pending_help', 'local_assign_ai');
            $statebadgeclass = 'badge bg-info';
            $canrequestai = false;
            $canapproveai = true;
        }

        $graderurl = new moodle_url('/mod/assign/view.php', [
            'id' => $cmid,
            'action' => 'grader',
            'userid' => $student->id,
        ]);

        $rows[] = [
            'fullname' => fullname($student),
            'email' => $student->email,
            'status' => $status,
            'lastmodified' => $lastmodified,
            'files' => $filesout,
            'grade' => $grade,
            'isinitial' => $isinitial,
            'ispending' => $ispending,
            'inprogress' => $inprogress,
            'progress' => $progress,
            'canrequestai' => $canrequestai,
            'canapproveai' => $canapproveai,
            'statebadge' => $statebadge,
            'statehint' => $statehint,
            'statebadgeclass' => $statebadgeclass,
            'courseid' => $course->id,
            'cmid' => $cmid,
            'userid' => $student->id,
            'pendingid' => $record->id,
            'graderurl' => $graderurl->out(false),
        ];
    }

    $renderer = $PAGE->get_renderer('core');
    $headerlogo = new \local_assign_ai\output\header_logo();
    $logocontext = $headerlogo->export_for_template($renderer);

    $templatecontext = [
        'backurl' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
        'rows' => $rows,
        'allblocked' => $allblocked,
        'hasinitial' => $hasinitial,
        'haspending' => $haspending,
        'cmid' => $cmid,
        'courseid' => $course->id,
        'headerlogo' => $logocontext,
        'alttext' => get_string('altlogo', 'local_assign_ai'),
    ];

    echo $OUTPUT->render_from_template('local_assign_ai/review_page', $templatecontext);
    echo $OUTPUT->footer();
} catch (Exception $e) {
    \core\notification::error(get_string('unexpectederror', 'local_assign_ai', $e->getMessage()));
    echo $OUTPUT->footer();
}
