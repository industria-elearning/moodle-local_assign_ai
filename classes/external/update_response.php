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
 * External function to update the response message of a pending approval.
 *
 * @package     local_assign_ai
 * @category    external
 * @copyright   2025 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_assign_ai\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;

/**
 * External API to update the response of a pending approval.
 */
class update_response extends external_api {
    /**
     * Returns the description of the parameters for this external function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'token' => new external_value(PARAM_TEXT, 'Approval token', VALUE_REQUIRED),
            'message' => new external_value(PARAM_RAW, 'Mensaje actualizado', VALUE_REQUIRED),
        ]);
    }

    /**
     * Executes the external function to update a pending approval response.
     *
     * @param string $token The approval token.
     * @param string $message The updated message.
     * @return array The result of the operation.
     */
    public static function execute($token, $message) {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'token' => $token,
            'message' => $message,
        ]);

        $record = $DB->get_record('local_assign_ai_pending', [
            'approval_token' => $params['token'],
        ], '*', MUST_EXIST);

        $cm = get_coursemodule_from_id('assign', $record->assignmentid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        self::validate_context($context);
        require_capability('local/assign_ai:changestatus', $context);

        $record->message = $params['message'];
        $DB->update_record('local_assign_ai_pending', $record);

        return [
            'status' => 'ok',
            'message' => $record->message,
        ];
    }

    /**
     * Returns the description of the return values.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'status' => new external_value(PARAM_TEXT, 'Estado de la operación'),
            'message' => new external_value(PARAM_RAW, 'Mensaje actualizado'),
        ]);
    }
}
