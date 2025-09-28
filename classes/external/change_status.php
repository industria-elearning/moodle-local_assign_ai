<?php
namespace local_assign_ai\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;

class change_status extends external_api {
    public static function execute_parameters() {
        return new external_function_parameters([
            'token'  => new external_value(PARAM_ALPHANUM, 'Approval token', VALUE_REQUIRED),
            'action' => new external_value(PARAM_ALPHA, 'Acción: approve o rejected', VALUE_REQUIRED)
        ]);
    }

    public static function execute($token, $action) {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'token'  => $token,
            'action' => $action
        ]);

        $record = $DB->get_record('local_assign_ai_pending', [
            'approval_token' => $params['token']
        ], '*', MUST_EXIST);

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
                'userid'     => $record->userid
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
                        'commentformat'  => FORMAT_HTML
                    ];
                    $DB->insert_record('assignfeedback_comments', $feedback);

                }

                $event = \mod_assign\event\submission_graded::create_from_grade($assign, $grade);
                $event->trigger();

            } else {
                error_log("⚠️ No existe grade para userid={$record->userid}, assignid={$cm->instance}");
            }
        }

        return [
            'status'    => 'ok',
            'newstatus' => $record->status
        ];
    }

    public static function execute_returns() {
        return new external_single_structure([
            'status'    => new external_value(PARAM_TEXT, 'Estado de la operación'),
            'newstatus' => new external_value(PARAM_TEXT, 'Nuevo estado aplicado')
        ]);
    }
}
