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

namespace local_assign_ai\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->dirroot . '/local/assign_ai/locallib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use local_assign_ai\api\client;

/**
 * External function for processing assignment submissions with AI.
 *
 * Handles the submission of one or multiple student assignments to the
 * AI grading service. If the "all" parameter is enabled, the process is queued
 * as an ad-hoc Moodle task and handled asynchronously by the cron system.
 *
 * @package     local_assign_ai
 * @category    external
 * @copyright   2025 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_submission extends external_api {
    /**
     * Returns the parameters required for the external function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID', VALUE_REQUIRED),
            'userid' => new external_value(PARAM_INT, 'User ID (0 for all)', VALUE_DEFAULT, 0),
            'all' => new external_value(
                PARAM_BOOL,
                'Whether to process all users (true) or a single one (false)',
                VALUE_DEFAULT,
                false
            ),

        ]);
    }

    /**
     * Executes the AI submission processing.
     *
     * When called with the "all" flag, an ad-hoc task is created to handle
     * all student submissions asynchronously. If a specific user ID is
     * provided, the function processes that submission immediately.
     *
     * @param int $cmid The course module ID.
     * @param int $userid The user ID (0 for all users).
     * @param bool $all Whether to process all submissions (queued task) or a single one.
     * @return array An associative array containing the processing result.
     */
    public static function execute($cmid, $userid = 0, $all = false) {
        global $DB, $CFG;

        require_login();

        $cm = get_coursemodule_from_id('assign', $cmid, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $context = \context_module::instance($cm->id);

        self::validate_context($context);
        require_capability('local/assign_ai:review', $context);

        $assign = new \assign($context, $cm, $course);
        $processed = 0;
        $token = null;

        // If processing all submissions → queue background task.
        if ($all) {
            $task = new \local_assign_ai\task\process_all_submissions();
            $task->set_custom_data([
                'cmid' => $cmid,
                'courseid' => $course->id,
            ]);
            $task->set_component('local_assign_ai');
            \core\task\manager::queue_adhoc_task($task);

            return [
                'status' => 'queued',
                'processed' => 0,
                'token' => '',
            ];
        }

        // Process a single user submission directly.
        if ($userid) {
            $student = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
            $token = self::process_submission_ai($assign, $course, $student, $DB);
            if ($token) {
                $processed++;
            }
        }

        return [
            'status' => 'ok',
            'processed' => $processed,
            'token' => $token ?? '',
        ];
    }

    /**
     * Returns the structure of the function’s response.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'status' => new external_value(PARAM_TEXT, 'Processing status (ok, queued, or error)'),
            'processed' => new external_value(PARAM_INT, 'Number of processed submissions'),
            'token' => new external_value(PARAM_TEXT, 'Approval token if available'),
        ]);
    }

    /**
     * Internal helper to process a single assignment submission via AI.
     *
     * This method compiles the submission data (either file or online text),
     * sends it to the AI service, and stores the pending result in the
     * `local_assign_ai_pending` table. If a record already exists for the
     * same user and assignment, it simply returns the existing token.
     *
     * @param \assign $assign The assignment instance.
     * @param \stdClass $course The course object.
     * @param \stdClass $student The student whose submission is processed.
     * @param \moodle_database $DB Moodle database global.
     * @param bool $countmode Whether to run in counting-only mode (default false).
     * @return string|null The approval token generated or found, or null on failure.
     */
    public static function process_submission_ai(\assign $assign, $course, $student, $DB, $countmode = false) {
        global $CFG;

        $submission = $assign->get_user_submission($student->id, false);
        if (!$submission || $submission->status !== 'submitted') {
            return null;
        }

        $assignment = $assign->get_instance();
        $cmid = $assign->get_course_module()->id;

        // Retrieve submission content (files or online text).
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
            $onlinetext = $DB->get_record('assignsubmission_onlinetext', ['submission' => $submission->id]);
            if ($onlinetext && !empty($onlinetext->onlinetext)) {
                $submissioncontent = $onlinetext->onlinetext;
            }
        }

        // Prepare payload for the AI service.
        $payload = [
            'site_id' => md5($CFG->wwwroot),
            'course_id' => (string)$course->id,
            'course' => $course->fullname,
            'assignment_id' => (string)$assignment->id,
            'cmi_id' => (string)$cmid,
            'assignment_title' => $assignment->name,
            'assignment_description' => $assignment->intro,
            'rubric' => local_assign_ai_build_rubric_json($assign),
            'userid' => (string)$student->id,
            'student_name' => fullname($student),
            'submission_assign' => $submissioncontent,
            'maximum_grade' => (int)$assignment->grade,
        ];

        // Send to AI client.
        try {
            $data = client::send_to_ai($payload);
        } catch (\Throwable $e) {
            debugging('AI request failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return null;
        }

        // Check if record already exists.
        $existing = $DB->get_record('local_assign_ai_pending', [
            'courseid' => $course->id,
            'assignmentid' => $cmid,
            'userid' => $student->id,
        ]);

        if ($existing) {
            return $existing->approval_token;
        }

        // Create new pending record.
        $token = bin2hex(random_bytes(16));
        $record = (object)[
            'courseid' => $course->id,
            'assignmentid' => $cmid,
            'title' => $assignment->name,
            'userid' => $student->id,
            'message' => $data['reply'] ?? '',
            'grade' => $data['grade'] ?? null,
            'rubric_response' => isset($data['rubric']) ? json_encode($data['rubric'], JSON_UNESCAPED_UNICODE) : null,
            'status' => 'pending',
            'approval_token' => $token,
            'timemodified' => time(),
        ];
        $DB->insert_record('local_assign_ai_pending', $record);

        return $token;
    }
}
