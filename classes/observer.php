<?php
namespace local_assign_ai;

defined('MOODLE_INTERNAL') || die();

class observer {

    public static function submission_graded(\mod_assign\event\submission_graded $event) {
        global $DB;

        try {
            $data = $event->get_data();
            $cmid   = $data['contextinstanceid'] ?? null; // cmid
            $userid = $data['relateduserid'] ?? null;
            $gradeid = $data['objectid'] ?? null; // assign_grades.id

            error_log("=== DEBUGGING ===");
            error_log("Event CMID: $cmid, User ID: $userid, Grade ID: $gradeid");

            // Buscar registro existente
            $record = $DB->get_record('local_assign_ai_pending', [
                'assignmentid' => $cmid,
                'userid' => $userid
            ]);

            if ($record) {
                // Buscar feedback en assignfeedback_comments con el gradeid
                $feedback = $DB->get_record('assignfeedback_comments', [
                    'grade' => $gradeid
                ]);

                if ($feedback && !empty($feedback->commenttext)) {
                    $record->message = $feedback->commenttext; // actualizar mensaje
                    error_log("Nuevo mensaje desde feedback: {$feedback->commenttext}");
                } else {
                    error_log("⚠️ No se encontró feedback para gradeid=$gradeid");
                }

                // Actualizar estado
                $record->status = 'approve';
                $record->timemodified = time();

                $DB->update_record('local_assign_ai_pending', $record);
                error_log("✅ Record updated to approved + message refreshed!");
            } else {
                error_log("❌ No matching record found en local_assign_ai_pending");
            }

        } catch (\Exception $e) {
            error_log('ERROR: ' . $e->getMessage());
        }
    }
}
