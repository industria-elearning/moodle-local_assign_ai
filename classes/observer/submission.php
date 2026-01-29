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

require_once($CFG->dirroot . '/local/assign_ai/locallib.php');
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
            $data = $event->get_data();
            $other = $data['other'];

            if ($other['submissionstatus'] !== ASSIGN_SUBMISSION_STATUS_SUBMITTED) {
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
     * Handles the grading event for a submission.
     *
     * Updates the local_assign_ai_pending table:
     *  - The feedback (comments).
     *  - The grade.
     *  - The rubric response (rubric_response).
     *
     * @param submission_graded $event The grading event.
     * @return void
     */
    public static function submission_graded(submission_graded $event) {
        global $DB;

        try {
            $data = $event->get_data();
            $cmid = $data['contextinstanceid'] ?? null;
            $userid = $data['relateduserid'] ?? null;
            $gradeid = $data['objectid'] ?? null;

            $records = $DB->get_records('local_assign_ai_pending', [
                'assignmentid' => $cmid,
                'userid' => $userid,
            ], 'timemodified DESC');

            $record = reset($records);

            if (!$record || empty($record->rubric_response)) {
                return;
            }

            $rubricdata = json_decode($record->rubric_response, true);

            if (!$rubricdata || !is_array($rubricdata)) {
                debugging('Invalid rubric_response JSON', DEBUG_DEVELOPER);
                return;
            }

            $instances = $DB->get_records(
                'grading_instances',
                ['itemid' => $gradeid],
                'timemodified DESC'
            );

            if (!$instances) {
                debugging('No grading instances found', DEBUG_DEVELOPER);
                return;
            }

            $gradinginstance = reset($instances);

            foreach ($instances as $instance) {
                if ($instance->id != $gradinginstance->id) {
                    $DB->delete_records('gradingform_rubric_fillings', [
                        'instanceid' => $instance->id,
                    ]);
                    $DB->delete_records('grading_instances', [
                        'id' => $instance->id,
                    ]);
                }
            }

            $DB->delete_records('gradingform_rubric_fillings', [
                'instanceid' => $gradinginstance->id,
            ]);

            $definitionid = $gradinginstance->definitionid;

            foreach ($rubricdata as $criteriondata) {
                $criteriondesc = $criteriondata['criterion'] ?? '';
                $levels = $criteriondata['levels'] ?? [];

                if (empty($levels)) {
                    continue;
                }

                $leveldata = reset($levels);
                $points = $leveldata['points'] ?? 0;
                $comment = $leveldata['comment'] ?? '';

                $criterion = $DB->get_record('gradingform_rubric_criteria', [
                    'definitionid' => $definitionid,
                    'description' => $criteriondesc,
                ]);

                if (!$criterion) {
                    debugging("Criterion not found: {$criteriondesc}", DEBUG_DEVELOPER);
                    continue;
                }

                $level = $DB->get_record('gradingform_rubric_levels', [
                    'criterionid' => $criterion->id,
                    'score' => $points,
                ]);

                $levelid = $level ? $level->id : null;

                $filling = new \stdClass();
                $filling->instanceid = $gradinginstance->id;
                $filling->criterionid = $criterion->id;
                $filling->levelid = $levelid;
                $filling->remark = $comment;

                $DB->insert_record('gradingform_rubric_fillings', $filling);
            }

            $gradinginstance->status = 1;
            $gradinginstance->timemodified = time();
            $DB->update_record('grading_instances', $gradinginstance);

            $record->status = 'approve';
            if (!empty($data['userid'])) {
                $record->usermodified = $data['userid'];
            }
            $record->timemodified = time();
            $DB->update_record('local_assign_ai_pending', $record);
        } catch (\Exception $e) {
            debugging('Exception in submission_graded observer: ' . $e->getMessage(), DEBUG_DEVELOPER);
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

            $config = local_assign_ai_get_assignment_config($assign->get_instance()->id);

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
