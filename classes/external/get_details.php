<?php
namespace local_assign_ai\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/externallib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use external_multiple_structure;

class get_details extends external_api {

    public static function execute_parameters() {
        return new external_function_parameters([
            'token' => new external_value(PARAM_RAW, 'Approval token', VALUE_REQUIRED),
        ]);
    }

    public static function execute($token) {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['token' => $token]);

        $record = $DB->get_record('local_assign_ai_pending', ['approval_token' => $params['token']], '*', MUST_EXIST);

        return [
            'token' => $record->approval_token,
            'message' => $record->message,
            'status' => $record->status,
            'userid' => $record->userid,
        ];
    }

    public static function execute_returns() {
        return new external_single_structure([
            'token' => new external_value(PARAM_RAW, 'Approval token'),
            'message' => new external_value(PARAM_RAW, 'AI message'),
            'status' => new external_value(PARAM_TEXT, 'AI status'),
            'userid' => new external_value(PARAM_INT, 'User ID'),
        ]);
    }
}
