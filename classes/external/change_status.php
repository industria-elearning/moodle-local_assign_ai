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
 * External function to change the status of AI assignment approvals.
 *
 * @package     local_assign_ai
 * @copyright   2025 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_assign_ai\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;

/**
 * External API to change status of pending approvals.
 */
class change_status extends external_api {
    /**
     * Returns the description of the parameters for this external function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'token'  => new external_value(PARAM_ALPHANUM, 'Approval token', VALUE_REQUIRED),
            'action' => new external_value(PARAM_ALPHA, 'Acción: approve o rejected', VALUE_REQUIRED),
        ]);
    }

    /**
     * Executes the external function.
     *
     * @param string $token The approval token.
     * @param string $action The action to apply (approve or rejected).
     * @return array The result of the operation.
     */
    public static function execute($token, $action) {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'token'  => $token,
            'action' => $action,
        ]);

        $record = $DB->get_record('local_assign_ai_pending', [
            'approval_token' => $params['token'],
        ], '*', MUST_EXIST);

        $cm = get_coursemodule_from_id('assign', $record->assignmentid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        self::validate_context($context);
        require_capability('local/assign_ai:changestatus', $context);

        $record->status = $params['action'];
        $record->timemodified = time();
        $DB->update_record('local_assign_ai_pending', $record);

        if ($params['action'] === 'approve') {
            $cm = get_coursemodule_from_id('assign', $record->assignmentid, 0, false, MUST_EXIST);
            $context = \context_module::instance($cm->id);
            $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
            $assign = new \assign($context, $cm, $course);

            $grade = $DB->get_record('assign_grades', [
                'assignment' => $cm->instance,
                'userid'     => $record->userid,
            ]);

            if ($grade) {
                $feedback = $DB->get_record('assignfeedback_comments', ['grade' => $grade->id]);
                if ($feedback) {
                    $feedback->commenttext = $record->message;
                    $feedback->commentformat = FORMAT_HTML;
                    $DB->update_record('assignfeedback_comments', $feedback);
                } else {
                    $feedback = (object)[
                        'assignment'     => $cm->instance,
                        'grade'          => $grade->id,
                        'commenttext'    => $record->message,
                        'commentformat'  => FORMAT_HTML,
                    ];
                    $DB->insert_record('assignfeedback_comments', $feedback);
                }

                $event = \mod_assign\event\submission_graded::create_from_grade($assign, $grade);
                $event->trigger();
            } else {
                debugging("⚠️ No existe grade para userid={$record->userid}, assignid={$cm->instance}.", DEBUG_DEVELOPER);
            }
        }

        return [
            'status'    => 'ok',
            'newstatus' => $record->status,
        ];
    }

    /**
     * Returns the description of the return values.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'status'    => new external_value(PARAM_TEXT, 'Estado de la operación'),
            'newstatus' => new external_value(PARAM_TEXT, 'Nuevo estado aplicado'),
        ]);
    }
}
