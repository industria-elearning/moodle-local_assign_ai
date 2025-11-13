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
    $PAGE->requires->css('/local/assign_ai/styles/review.css');

    $PAGE->activityheader->disable();

    echo $OUTPUT->header();

    // Back to course button.
    $backurl = new moodle_url('/course/view.php', ['id' => $course->id]);
    echo html_writer::link(
        $backurl,
        get_string('backtocourse', 'local_assign_ai'),
        ['class' => 'btn btn-secondary mb-3']
    );

    // Get the list of enrolled users with submission capability.
    $students = get_enrolled_users($context, 'mod/assign:submit');

    // Check if all students have feedback pending or approved.
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

    // Title + "review all" button in the same row.
    echo html_writer::start_div('d-flex justify-content-between align-items-center mb-3');
    echo $OUTPUT->heading(get_string('reviewwithai', 'local_assign_ai'), 2, 'mb-0');

    $reviewallurl = new moodle_url('/local/assign_ai/review_submission.php', [
        'id' => $cmid,
        'all' => 1,
    ]);

    if ($allblocked) {
        // Disabled button.
        echo html_writer::tag('button', get_string('reviewall', 'local_assign_ai'), [
            'class' => 'btn btn-warning',
            'disabled' => 'disabled',
        ]);
    } else {
        // Active button.
        echo html_writer::tag(
            'button',
            get_string('reviewall', 'local_assign_ai'),
            [
                'type' => 'button',
                'class' => 'btn btn-warning js-review-ai',
                'data-cmid' => $cmid,
                'data-all' => 1,
            ]
        );
    }
    echo html_writer::end_div();

    $rows = [];

    foreach ($students as $student) {
        // Submission status.
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

        // Last modification and submitted files.
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

        // AI status.
        $record = $DB->get_record('local_assign_ai_pending', [
            'courseid' => $course->id,
            'assignmentid' => $cm->id,
            'userid' => $student->id,
        ]);
        $grade = '-';

        if ($record && $record->status === 'approve') {
            // Hide submissions that were already approved and saved.
            continue;
        }

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
                ['class' => 'btn btn-success btn-sm text-nowrap view-details', 'data-token' => $record->approval_token]
            );

            if ($record->grade !== null) {
                $grade = $record->grade;
            }
        } else {
            $aistatus = get_string('nostatus', 'local_assign_ai');
            $aibutton = html_writer::tag(
                'button',
                get_string('viewdetails', 'local_assign_ai'),
                ['class' => 'btn btn-success btn-sm text-nowrap view-details', 'disabled' => 'disabled']
            );
        }

        // Blue button → grader.
        if ($record && !empty($record->approval_token)) {
            $viewurl = new moodle_url('/mod/assign/view.php', [
                'id' => $cmid,
                'action' => 'grader',
                'userid' => $student->id,
                'aitoken' => $record->approval_token,
            ]);
        } else {
            $viewurl = new moodle_url('/mod/assign/view.php', [
                'id' => $cmid,
                'action' => 'grader',
                'userid' => $student->id,
            ]);
        }

        $button = html_writer::link(
            $viewurl,
            get_string('qualify', 'local_assign_ai'),
            ['class' => 'btn btn-primary btn-sm text-nowrap']
        );

        // Gray button → review AI per user.
        $reviewurl = new moodle_url('/local/assign_ai/review_submission.php', [
            'id' => $cmid,
            'userid' => $student->id,
        ]);

        if ($record && in_array($record->status, ['pending', 'approve'])) {
            $reviewbtn = html_writer::tag(
                'button',
                get_string('review', 'local_assign_ai'),
                ['class' => 'btn btn-warning btn-sm text-nowrap', 'disabled' => 'disabled']
            );
        } else {
            $reviewbtn = html_writer::tag(
                'button',
                get_string('review', 'local_assign_ai'),
                [
                'type' => 'button',
                'class' => 'btn btn-warning btn-sm text-nowrap js-review-ai',
                'data-cmid' => $cmid,
                'data-userid' => $student->id,
                ]
            );
        }

        $actions = html_writer::div(
            $button . $aibutton . $reviewbtn,
            'local_assign_ai_action-buttons '
        );

        $rows[] = [
            'fullname' => fullname($student),
            'email' => $student->email,
            'status' => $status,
            'lastmodified' => $lastmodified,
            'files' => $filelinks,
            'aistatus' => $aistatus,
            'grade' => $grade,
            'actions' => $actions,
        ];
    }

    $renderer = $PAGE->get_renderer('core');
    $headerlogo = new \local_assign_ai\output\header_logo();
    $logocontext = $headerlogo->export_for_template($renderer);
    $templatecontext = [
        'rows' => $rows,
        'headerlogo' => $logocontext,
        'alttext' => get_string('altlogo', 'local_assign_ai'),
    ];

    echo $OUTPUT->render_from_template('local_assign_ai/review_table', $templatecontext);
    echo $OUTPUT->footer();
} catch (\Throwable $th) {
    \core\notification::error($e->getMessage());
    echo $OUTPUT->footer();
} catch (Exception $e) {
    \core\notification::error(get_string('unexpectederror', 'local_assign_ai', $e->getMessage()));
    echo $OUTPUT->footer();
}
