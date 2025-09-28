<?php
namespace local_assign_ai\external;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;

class update_response extends external_api {

    public static function execute_parameters() {
        return new external_function_parameters([
            'token' => new external_value(PARAM_TEXT, 'Approval token', VALUE_REQUIRED),
            'message' => new external_value(PARAM_RAW, 'Mensaje actualizado', VALUE_REQUIRED)
        ]);
    }

    public static function execute($token, $message) {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'token' => $token,
            'message' => $message
        ]);

        $record = $DB->get_record('local_assign_ai_pending', [
            'approval_token' => $params['token']
        ], '*', MUST_EXIST);

        $record->message = $params['message'];
        $DB->update_record('local_assign_ai_pending', $record);

        return [
            'status' => 'ok',
            'message' => $record->message
        ];
    }

    public static function execute_returns() {
        return new external_single_structure([
            'status' => new external_value(PARAM_TEXT, 'Estado de la operación'),
            'message' => new external_value(PARAM_RAW, 'Mensaje actualizado')
        ]);
    }
}
