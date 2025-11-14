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
 * History page for local_assign_ai.
 *
 * @package     local_assign_ai
 * @copyright   2025 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->dirroot . '/user/lib.php');

$cmid = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('assign', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('local/assign_ai:review', $context);

$PAGE->set_url(new moodle_url('/local/assign_ai/history.php', ['id' => $cmid]));
$PAGE->set_course($course);
$PAGE->set_context($context);
$PAGE->set_title(get_string('reviewhistory', 'local_assign_ai'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->requires->css('/local/assign_ai/styles/review.css');
$PAGE->activityheader->disable();

$assign = new assign($context, $cm, $course);
$records = $DB->get_records('local_assign_ai_pending', [
    'assignmentid' => $cm->id,
], 'timemodified DESC');

$rows = [];
if ($records) {
$userids = array_unique(array_map(fn($record) => $record->userid, $records));
    $users = user_get_users_by_id($userids);

    foreach ($records as $record) {
        if (empty($record->userid) || !isset($users[$record->userid])) {
            continue;
        }

        $user = $users[$record->userid];

        switch ($record->status) {
            case 'approve':
                $status = get_string('statusapprove', 'local_assign_ai');
                break;
            case 'rejected':
                $status = get_string('statusrejected', 'local_assign_ai');
                break;
            case 'pending':
            default:
                $status = get_string('statuspending', 'local_assign_ai');
        }

        $grade = $record->grade !== null ? $record->grade : '-';
        $lastmodified = !empty($record->timemodified) ? userdate($record->timemodified) : '-';

        if (!empty($record->message)) {
            $formattedmessage = format_text($record->message, FORMAT_HTML);
            $messagetext = shorten_text(strip_tags($formattedmessage), 180);
        } else {
            $messagetext = '-';
        }

        $rows[] = [
            'fullname' => fullname($user),
            'email' => s($user->email),
            'status' => $status,
            'grade' => $grade,
            'lastmodified' => $lastmodified,
            'message' => $messagetext,
        ];
    }
}

echo $OUTPUT->header();

$backurl = new moodle_url('/local/assign_ai/review.php', ['id' => $cmid]);
echo html_writer::link(
    $backurl,
    get_string('backtoreview', 'local_assign_ai'),
    ['class' => 'btn btn-secondary mb-3']
);

$renderer = $PAGE->get_renderer('core');
$headerlogo = new \local_assign_ai\output\header_logo();
$logocontext = $headerlogo->export_for_template($renderer);

$templatecontext = [
    'rows' => $rows,
    'headerlogo' => $logocontext,
    'alttext' => get_string('altlogo', 'local_assign_ai'),
];

echo $OUTPUT->heading(get_string('reviewhistory', 'local_assign_ai'), 2);
echo $OUTPUT->render_from_template('local_assign_ai/history_table', $templatecontext);
echo $OUTPUT->footer();
