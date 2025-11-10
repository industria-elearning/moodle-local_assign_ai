<?php
// This file is part of Moodle - https://moodle.org/.
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
 * Restore plugin for local_assign_ai.
 *
 * Handles the restoration of AI assignment data (pending and approved records)
 * during course restore operations.
 *
 * @package    local_assign_ai
 * @copyright  2025 Datacurso
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_local_assign_ai_plugin extends restore_local_plugin {
    /** @var array Temporary storage for records (pending or approved). */
    protected $temprecords = [];

    /**
     * Returns the definition of the restore paths for this plugin.
     *
     * @return restore_path_element[]
     */
    protected function define_course_plugin_structure() {
        return [
            new restore_path_element(
                'local_assign_ai_pending',
                $this->get_pathfor('/assign_ai_pendings/assign_ai_pending')
            ),
        ];
    }

    /**
     * Processes each record found in the XML backup file (pending or approved).
     *
     * @param array $data Record data from the backup file.
     * @return void
     */
    public function process_local_assign_ai_pending($data) {
        mtrace("   - Reading assign_ai record from XML (assignmentid={$data['assignmentid']}, status={$data['status']})");
        $this->temprecords[] = (object)$data;
    }

    /**
     * Restores all AI feedback records after the course has been restored.
     *
     * Maps old IDs to new ones and generates new approval tokens to avoid duplication.
     *
     * @return void
     */
    public function after_restore_course() {
        global $DB;

        mtrace(">> [local_assign_ai] Restoring AI feedback records (pending + approved)...");

        if (empty($this->temprecords)) {
            mtrace("   - No AI feedback data found in XML.");
            return;
        }

        foreach ($this->temprecords as $recorddata) {
            // Map course ID.
            $newcourseid = $this->get_mappingid('course', $recorddata->courseid);
            if (empty($newcourseid)) {
                $newcourseid = $recorddata->courseid;
            }

            // Map user ID.
            $newuserid = $this->get_mappingid('user', $recorddata->userid);
            if (empty($newuserid)) {
                $newuserid = $recorddata->userid;
            }

            // Map assignment to new course module ID.
            $newcmid = $this->get_mappingid('module', $recorddata->assignmentid);
            if (empty($newcmid)) {
                $newcmid = $this->get_mappingid('activity', $recorddata->assignmentid);
            }
            if (empty($newcmid)) {
                $newcmid = $this->get_mappingid('assign', $recorddata->assignmentid);
            }
            if (empty($newcmid)) {
                $newcmid = $this->get_mappingid('assignment', $recorddata->assignmentid);
            }
            if (empty($newcmid)) {
                $newcmid = null;
            }

            // Fallback: search by title in the new course.
            if (!$newcmid && !empty($recorddata->title)) {
                $newcmid = $DB->get_field_sql("
                    SELECT cm.id
                      FROM {course_modules} cm
                      JOIN {modules} m ON m.id = cm.module
                      JOIN {assign} a ON a.id = cm.instance
                     WHERE cm.course = ? AND m.name = 'assign' AND a.name = ?
                ", [$newcourseid, $recorddata->title]);

                if ($newcmid) {
                    mtrace("   - Mapped by title '{$recorddata->title}' → cm.id={$newcmid}");
                }
            }

            if (!$newcmid) {
                mtrace("   - Warning: could not map assignmentid={$recorddata->assignmentid}, keeping original.");
                $newcmid = $recorddata->assignmentid;
            }

            // Create restored record.
            $record = new stdClass();
            $record->courseid = $newcourseid;
            $record->assignmentid = $newcmid;
            $record->title = $recorddata->title;
            $record->userid = $newuserid;
            $record->message = $recorddata->message;
            $record->grade = $recorddata->grade;
            $record->rubric_response = $recorddata->rubric_response;
            $record->status = $recorddata->status;

            // Always generate a new approval token to avoid duplicates with the original backup.
            $record->approval_token = md5(uniqid('restored_', true));

            // Set timestamps.
            if (!empty($recorddata->timecreated)) {
                $record->timecreated = $recorddata->timecreated;
            } else {
                $record->timecreated = time();
            }

            if (!empty($recorddata->timemodified)) {
                $record->timemodified = $recorddata->timemodified;
            } else {
                $record->timemodified = time();
            }

            if (isset($recorddata->approved_at)) {
                $record->approved_at = $recorddata->approved_at;
            } else {
                $record->approved_at = null;
            }

            // Insert record into the database.
            $DB->insert_record('local_assign_ai_pending', $record);
            mtrace("   + Restored record → course={$newcourseid}, cm={$newcmid}, status={$record->status}");
        }

        mtrace(">> [local_assign_ai] Restoration completed");
    }
}
