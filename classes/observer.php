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
     * Handles the submission graded event.
     *
     * @param \mod_assign\event\submission_graded $event The graded submission event.
     * @return void
     */
    public static function submission_graded(\mod_assign\event\submission_graded $event) {
        global $DB;

        try {
            $data = $event->get_data();
            $cmid   = $data['contextinstanceid'] ?? null;
            $userid = $data['relateduserid'] ?? null;
            $gradeid = $data['objectid'] ?? null;

            debugging('=== DEBUGGING ===', DEBUG_DEVELOPER);
            debugging("Event CMID: $cmid, User ID: $userid, Grade ID: $gradeid.", DEBUG_DEVELOPER);

            // Buscar registro existente.
            $record = $DB->get_record('local_assign_ai_pending', [
                'assignmentid' => $cmid,
                'userid' => $userid,
            ]);

            if ($record) {
                // Buscar feedback en assignfeedback_comments con el gradeid.
                $feedback = $DB->get_record('assignfeedback_comments', [
                    'grade' => $gradeid,
                ]);

                if ($feedback && !empty($feedback->commenttext)) {
                    $record->message = $feedback->commenttext;
                    debugging("Nuevo mensaje desde feedback: {$feedback->commenttext}.", DEBUG_DEVELOPER);
                } else {
                    debugging("No se encontró feedback para gradeid=$gradeid.", DEBUG_DEVELOPER);
                }

                // Actualizar estado.
                $record->status = 'approve';
                $record->timemodified = time();

                $DB->update_record('local_assign_ai_pending', $record);
                debugging('Record updated to approved + message refreshed!.', DEBUG_DEVELOPER);
            } else {
                debugging('No matching record found en local_assign_ai_pending.', DEBUG_DEVELOPER);
            }

        } catch (\Exception $e) {
            debugging('ERROR: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
