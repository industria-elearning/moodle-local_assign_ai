<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_assign_ai\observer;

defined('MOODLE_INTERNAL') || die();

use mod_assign\event\submission_created;
use mod_assign\event\submission_updated;
use mod_assign\event\submission_status_updated;
use mod_assign\event\submission_graded;
use local_assign_ai\task\process_submission_ai;
use local_assign_ai\config\assignment_config;
use local_assign_ai\pending\manager as pending_manager;

require_once($CFG->dirroot . '/mod/assign/locallib.php');

/**
 * Observer for submission events.
 *
 * @package    local_assign_ai
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submission {
    /**
     * Handles the submission created event.
     *
     * If the assignment is configured to auto‑approve AI feedback, this will
     * send the submission to the AI service for grading without teacher
     * intervention. Otherwise, it does nothing.
     *
     * @param submission_created $event The submission created event.
     * @return void
     */
    public static function submission_created(submission_created $event) {
        global $DB;

        try {
            if (!assignment_config::is_feature_enabled()) {
                return;
            }

            $data = $event->get_data();
            $other = $data['other'];

            if ($other['submissionstatus'] !== ASSIGN_SUBMISSION_STATUS_SUBMITTED) {
                return;
            }

            $assign = $event->get_assign();
            if (!$assign) {
                return;
            }

            $config = assignment_config::get_effective((int)$assign->get_instance()->id);
            if (empty($config->enableai)) {
                return;
            }

            $userid = $data['relateduserid'] ?? null;
            if (!$userid) {
                return;
            }

            $cmid = $assign->get_course_module()->id;
            $task = new process_submission_ai();
            $task->set_custom_data((object) [
                'userid' => (int) $userid,
                'cmid' => (int) $cmid,
            ]);
            \core\task\manager::queue_adhoc_task($task);
        } catch (\Exception $e) {
            debugging('Exception in submission_created observer: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Resets AI pending records when a student edits a submission and multiple attempts are allowed.
     *
     * @param submission_updated $event The submission updated event.
     * @return void
     */
    public static function submission_updated(submission_updated $event) {
        global $DB;

        try {
            if (!assignment_config::is_feature_enabled()) {
                return;
            }

            $assign = $event->get_assign();
            if (!$assign) {
                return;
            }

            $data = $event->get_data();
            $userid = $data['relateduserid'] ?? null;
            if (!$userid) {
                return;
            }

            $other = $data['other'] ?? [];
            $submission = $assign->get_user_submission($userid, true);
            $cmid = $assign->get_course_module()->id;

            $config = assignment_config::get_effective((int)$assign->get_instance()->id);
            if (empty($config->enableai)) {
                return;
            }

            $records = $DB->get_records('local_assign_ai_pending', [
                'assignmentid' => $cmid,
                'userid' => $userid,
            ], 'timemodified DESC');

            $record = reset($records);

            $taskdata = (object) [
                'userid' => (int) $userid,
                'cmid' => (int) $cmid,
            ];

            $deletefromqueue = function () use ($DB, $userid, $cmid) {
                $like1 = '%"userid":' . $userid . '%';
                $like2 = '%"userid":"' . $userid . '"%';

                $sql = "DELETE FROM {local_assign_ai_queue}
                WHERE type = 'submission'
                  AND (payload LIKE ? OR payload LIKE ?)";

                $DB->execute($sql, [$like1, $like2]);
            };

            $enqueuetask = function () use ($config, $taskdata, $DB, $deletefromqueue) {

                $deletefromqueue();

                if (!empty($config->usedelay)) {
                    $delay = max(1, (int) $config->delayminutes);
                    $timetoprocess = time() + ($delay * 60);

                    $DB->insert_record('local_assign_ai_queue', (object) [
                        'type' => 'submission',
                        'payload' => json_encode($taskdata),
                        'timecreated' => time(),
                        'timetoprocess' => $timetoprocess,
                        'processed' => 0,
                    ]);
                    return;
                }

                $task = new process_submission_ai();
                $task->set_custom_data($taskdata);
                \core\task\manager::queue_adhoc_task($task);
            };

            if (!$record) {
                if (!empty($other['oldstatus']) && $other['oldstatus'] === ASSIGN_SUBMISSION_STATUS_NEW) {
                    return;
                }
                $enqueuetask();
                return;
            }

            if (!$submission || $submission->status !== ASSIGN_SUBMISSION_STATUS_SUBMITTED) {
                return;
            }

            if ($record->status === 'pending') {
                $DB->delete_records('local_assign_ai_pending', ['id' => $record->id]);
                $enqueuetask();
                return;
            }

            if ($record->status === 'approve') {
                $enqueuetask();
                return;
            }
        } catch (\Exception $e) {
            debugging('Exception in submission_updated observer: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Syncs approved AI records when a teacher updates grading.
     *
     * @param submission_graded $event The submission graded event.
     * @return void
     */
    public static function submission_graded(submission_graded $event) {
        global $DB, $USER;

        try {
            $assign = $event->get_assign();
            if (!$assign) {
                return;
            }

            $data = $event->get_data();
            $userid = $data['relateduserid'] ?? null;
            if (!$userid) {
                return;
            }

            $cmid = $assign->get_course_module()->id;
            $record = pending_manager::get_latest_record($cmid, $userid);

            if (!$record) {
                return;
            }

            if ($record->status !== \local_assign_ai\assign_submission::STATUS_APPROVED) {
                return;
            }

            $grade = $assign->get_user_grade($userid, true);
            if (!$grade) {
                return;
            }

            $gradingmanager = get_grading_manager($assign->get_context(), 'mod_assign', 'submissions');
            pending_manager::sync_after_grading(
                $assign,
                $record,
                $grade,
                $gradingmanager,
                $USER->id ?? $record->usermodified
            );
        } catch (\Exception $e) {
            debugging('Exception in submission_graded observer: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Handles the submission_status_updated event when a student removes their submission.
     *
     * @param submission_status_updated $event The submission status updated event.
     * @return void
     */
    public static function submission_status_updated(submission_status_updated $event) {
        global $DB;

        try {
            $data = $event->get_data();
            $other = $data['other'];

            if (
                !isset($other['newstatus']) ||
                ($other['newstatus'] !== ASSIGN_SUBMISSION_STATUS_NEW &&
                    $other['newstatus'] !== ASSIGN_SUBMISSION_STATUS_DRAFT)
            ) {
                return;
            }

            $assign = $event->get_assign();
            if (!$assign) {
                return;
            }

            $userid = $data['relateduserid'] ?? null;
            if (!$userid) {
                return;
            }

            $cmid = $assign->get_course_module()->id;

            $DB->delete_records('local_assign_ai_pending', [
                'assignmentid' => $cmid,
                'userid' => $userid,
            ]);

            $like1 = '%"userid":' . $userid . '%';
            $like2 = '%"userid":"' . $userid . '"%';

            $sql = "DELETE FROM {local_assign_ai_queue}
            WHERE type = 'submission'
              AND (payload LIKE ? OR payload LIKE ?)";

            $DB->execute($sql, [$like1, $like2]);
        } catch (\Exception $e) {
            debugging('Exception in submission_status_updated observer: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
