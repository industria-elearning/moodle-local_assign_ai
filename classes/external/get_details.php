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

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use external_multiple_structure;

/**
 * External function to retrieve details of a pending AI assignment approval.
 *
 * @package     local_assign_ai
 * @category    external
 * @copyright   2025 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class get_details extends external_api {
    /**
     * Returns the description of the parameters for this external function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'token' => new external_value(PARAM_RAW, 'Approval token', VALUE_REQUIRED),
        ]);
    }

    /**
     * Executes the external function to retrieve approval details.
     *
     * @param string $token The approval token.
     * @return array The details of the pending approval.
     */
    public static function execute($token) {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['token' => $token]);

        $record = $DB->get_record('local_assign_ai_pending', ['approval_token' => $params['token']], '*', MUST_EXIST);

        $cm = get_coursemodule_from_id('assign', $record->assignmentid, $record->courseid, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        self::validate_context($context);
        require_capability('local/assign_ai:viewdetails', $context);

        return [
            'token' => $record->approval_token,
            'message' => $record->message,
            'status' => $record->status,
            'userid' => $record->userid,
            'grade' => $record->grade,
            'rubric_response' => $record->rubric_response,
        ];
    }

    /**
     * Returns the description of the return values.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'token' => new external_value(PARAM_RAW, 'Approval token'),
            'message' => new external_value(PARAM_RAW, 'AI message'),
            'status' => new external_value(PARAM_TEXT, 'AI status'),
            'userid' => new external_value(PARAM_INT, 'User ID'),
            'grade' => new external_value(PARAM_FLOAT, 'AI suggested grade', VALUE_OPTIONAL),
            'rubric_response' => new external_value(PARAM_RAW, 'AI rubric response JSON', VALUE_OPTIONAL),
        ]);
    }
}
