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

namespace local_assign_ai;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/assign_ai/locallib.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

/**
 * Event observers for local_assign_ai.
 *
 * @package     local_assign_ai
 * @category    event
 * @copyright   2025 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Handles the submission created event.
     *
     * If the assignment is configured to auto‑approve AI feedback, this will
     * send the submission to the AI service for grading without teacher
     * intervention. Otherwise, it does nothing.
     *
     * @param \mod_assign\event\submission_created $event The submission created event.
     * @return void
     */
    public static function submission_created(\mod_assign\event\submission_created $event) {
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

            // Queue ad-hoc task to process AI submission with minimal data.
            $cmid = $assign->get_course_module()->id;
            $task = new \local_assign_ai\task\process_submission_ai();
            $task->set_custom_data((object) [
                'userid' => (int)$userid,
                'cmid' => (int)$cmid,
            ]);
            \core\task\manager::queue_adhoc_task($task);
        } catch (\Exception $e) {
            debugging('Exception in submission_created observer: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Normalizes a score to be saved as an int if it has no decimal places, or as a float otherwise.
     *
     * @param mixed $score Score value (string, int or float).
     * @return int|float
     */
    private static function normalize_points($score) {
        $float = (float) $score;
        return (fmod($float, 1.0) == 0.0) ? (int) $float : $float;
    }

    /**
     * Handles the grading event for a submission.
     *
     * Updates the local_assign_ai_pending table:
     *  - The feedback (comments).
     *  - The grade.
     *  - The rubric response (rubric_response).
     *
     * @param \mod_assign\event\submission_graded $event The grading event.
     * @return void
     */
    public static function submission_graded(\mod_assign\event\submission_graded $event) {
        global $DB;

        try {
            $data = $event->get_data();
            $cmid = $data['contextinstanceid'] ?? null;
            $userid = $data['relateduserid'] ?? null;
            $gradeid = $data['objectid'] ?? null;

            $record = $DB->get_record('local_assign_ai_pending', [
                'assignmentid' => $cmid,
                'userid' => $userid,
            ]);

            if (!$record) {
                return;
            }

            $feedback = $DB->get_record('assignfeedback_comments', ['grade' => $gradeid]);
            if ($feedback && !empty($feedback->commenttext)) {
                $record->message = $feedback->commenttext;
            }

            $grade = $DB->get_record('assign_grades', ['id' => $gradeid]);
            if ($grade && isset($grade->grade)) {
                $record->grade = self::normalize_points($grade->grade);
            }

            $instances = $DB->get_records('grading_instances', [
                'itemid' => $gradeid,
                'status' => 1,
            ]);

            if ($instances) {
                foreach ($instances as $gi) {
                    $fillings = $DB->get_records('gradingform_rubric_fillings', ['instanceid' => $gi->id]);
                    if ($fillings) {
                        $rubricdata = [];
                        foreach ($fillings as $f) {
                            $criterion = $DB->get_field('gradingform_rubric_criteria', 'description', ['id' => $f->criterionid]);
                            $score = $f->levelid
                                ? $DB->get_field('gradingform_rubric_levels', 'score', ['id' => $f->levelid])
                                : 0;

                            $rubricdata[] = [
                                'criterion' => $criterion,
                                'levels' => [
                                    [
                                        'points' => self::normalize_points($score),
                                        'comment' => $f->remark ?? '',
                                    ],
                                ],
                            ];
                        }

                        $record->rubric_response = json_encode($rubricdata, JSON_UNESCAPED_UNICODE);

                        break;
                    }
                }
            }

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
     * @param \mod_assign\event\submission_updated $event The submission updated event.
     * @return void
     */
    public static function submission_updated(\mod_assign\event\submission_updated $event) {
        global $DB;

        try {
            $assign = $event->get_assign();
            if (!$assign) {
                return;
            }

            $instance = $assign->get_instance();
            $maxattempts = isset($instance->maxattempts) ? (int)$instance->maxattempts : 1;
            $allowsmultiple = $maxattempts > 1 || $maxattempts === ASSIGN_UNLIMITED_ATTEMPTS;
            if (!$allowsmultiple) {
                return;
            }

            $data = $event->get_data();
            $userid = $data['relateduserid'] ?? null;
            if (!$userid) {
                return;
            }

            $cmid = $assign->get_course_module()->id;
            $record = $DB->get_record('local_assign_ai_pending', [
                'assignmentid' => $cmid,
                'userid' => $userid,
            ]);

            if (!$record || $record->status !== 'approve') {
                return;
            }

            $DB->delete_records('local_assign_ai_pending', ['id' => $record->id]);
        } catch (\Exception $e) {
            debugging('Exception in submission_updated observer: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
