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
 * @package    local_assign_ai
 * @copyright  2025 Datacurso
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines the restore logic for the local_assign_ai plugin.
 *
 * Handles the restoration of AI assignment data (pending and approved records)
 * during course restore operations.
 */
class restore_local_assign_ai_plugin extends restore_local_plugin {
    /** @var array Temporary storage for records (pending or approved). */
    protected $temprecords = [];

    /**
     * Define restore paths.
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
     * Collect each record from XML (pending or approved).
     *
     * @param array $data Record data.
     */
    public function process_local_assign_ai_pending($data) {
        mtrace("   - Reading assign_ai record from XML (assignmentid={$data['assignmentid']}, status={$data['status']})");
        $this->temprecords[] = (object)$data;
    }

    /**
     * After restoring the course, insert all records with new IDs.
     */
    public function after_restore_course() {
        global $DB;

        mtrace(">> [local_assign_ai] Restoring AI feedback records (pending + approved)...");

        if (empty($this->temprecords)) {
            mtrace("   - No AI feedback data found in XML!");
            return;
        }

        foreach ($this->temprecords as $recorddata) {
            $newcourseid = $this->get_mappingid('course', $recorddata->courseid) ?: $recorddata->courseid;
            $newuserid = $this->get_mappingid('user', $recorddata->userid) ?: $recorddata->userid;

            // Map to the new course_module.id (the one seen in URLs).
            $newcmid =
            $this->get_mappingid('module', $recorddata->assignmentid)
            ?: $this->get_mappingid('activity', $recorddata->assignmentid)
            ?: $this->get_mappingid('assign', $recorddata->assignmentid)
            ?: $this->get_mappingid('assignment', $recorddata->assignmentid)
            ?: null;

            // Fallback: search by name in the new course.
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

            // Insert the restored record.
            $record = new stdClass();
            $record->courseid = $newcourseid;
            $record->assignmentid = $newcmid; // Use the course_module.id.
            $record->title = $recorddata->title;
            $record->userid = $newuserid;
            $record->message = $recorddata->message;
            $record->grade = $recorddata->grade;
            $record->rubric_response = $recorddata->rubric_response;
            $record->status = $recorddata->status;
            $record->approval_token = $recorddata->approval_token ?: md5(uniqid('restored_', true));
            $record->timecreated = $recorddata->timecreated ?: time();
            $record->timemodified = $recorddata->timemodified ?: time();
            $record->approved_at = $recorddata->approved_at ?? null;

            $DB->insert_record('local_assign_ai_pending', $record);
            mtrace("   + Restored record → course={$newcourseid}, cm={$newcmid}, status={$record->status}");
        }

        mtrace(">> [local_assign_ai] Restoration completed ✅");
    }
}
