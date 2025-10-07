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
 * Event observers for local_assign_ai.
 *
 * @package     local_assign_ai
 * @category    event
 * @copyright   2025 Piero Llanos <piero@datacurso.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_assign_ai;

/**
 * Observer class for handling assignment events.
 */
class observer {

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
     *  - La respuesta de la rúbrica (rubric_response).
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
            $record->timemodified = time();
            $DB->update_record('local_assign_ai_pending', $record);

        } catch (\Exception $e) {
            debugging('Exception in submission_graded observer: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
