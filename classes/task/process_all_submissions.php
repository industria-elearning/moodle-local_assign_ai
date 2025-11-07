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
 * Ad-hoc task for processing all assignment submissions with AI.
 *
 * This task is automatically queued by the external function
 * when the teacher chooses to review all submissions. It processes
 * each student's submission asynchronously using the AI grading service.
 *
 * @package     local_assign_ai
 * @category    task
 * @copyright   2025 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_assign_ai\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->dirroot . '/local/assign_ai/locallib.php');
require_once($CFG->libdir . '/externallib.php');

use core\task\adhoc_task;
use local_assign_ai\api\client;

/**
 * Ad-hoc task to process all AI submissions for a given assignment.
 *
 * The task iterates through all enrolled students with submission capability,
 * retrieves their submitted content, and sends it to the AI evaluation endpoint.
 * Each result is stored in the `local_assign_ai_pending` table until the teacher
 * approves or updates the grade.
 */
class process_all_submissions extends adhoc_task {
    /**
     * Executes the queued ad-hoc task.
     *
     * This function is automatically executed by Moodle's task manager.
     * It validates the required parameters (course ID and cmid), loads
     * all enrolled users who can submit assignments, and sends each one
     * to the AI processing service.
     *
     * @return void
     */
    public function execute() {
        global $DB, $CFG;

        $data = $this->get_custom_data();

        // Validate required task parameters.
        if (empty($data->cmid) || empty($data->courseid)) {
            mtrace(get_string('missingtaskparams', 'local_assign_ai'));
            return;
        }

        $cm = get_coursemodule_from_id('assign', $data->cmid, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $data->courseid], '*', MUST_EXIST);
        $context = \context_module::instance($cm->id);

        $assign = new \assign($context, $cm, $course);
        $students = get_enrolled_users($context, 'mod/assign:submit');

        mtrace(get_string('aitaskstart', 'local_assign_ai', $course->fullname));

        $processed = 0;

        foreach ($students as $student) {
            // Reuse the helper function from the external service.
            $token = \local_assign_ai\external\process_submission::process_submission_ai(
                $assign,
                $course,
                $student,
                $DB,
                false
            );

            if ($token) {
                $processed++;
                $params = [
                    'id' => $student->id,
                    'name' => $student->firstname,
                ];
                mtrace(get_string('aitaskuserqueued', 'local_assign_ai', $params));
            }
        }

        mtrace(get_string('aitaskdone', 'local_assign_ai', $processed));
    }
}
