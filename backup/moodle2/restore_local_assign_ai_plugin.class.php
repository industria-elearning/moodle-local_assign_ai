<?php
// This file is part of Moodle - http://moodle.org/.
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
 * Restore handler for the local_assign_ai plugin.
 *
 * Handles the restoration of AI-generated feedback and review data
 * from the `local_assign_ai_pending` table during course restoration.
 *
 * @package    local_assign_ai
 * @category   backup
 * @copyright  2025 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restore subplugin class for the local_assign_ai plugin.
 *
 * Handles restoration of AI-generated assignment review and feedback data
 * from backup files into the `local_assign_ai_pending` table.
 *
 * @package    local_assign_ai
 * @category   backup
 */
class restore_local_assign_ai_plugin extends restore_local_plugin {
    /**
     * Defines the structure of data that will be restored for each assignment.
     *
     * @return restore_path_element[]
     */
    protected function define_assign_subplugin_structure() {
        $paths = [];

        // Include user-related data only if user info was backed up.
        if ($this->get_setting_value('userinfo')) {
            $paths[] = new restore_path_element(
                'local_assign_ai_pending',
                $this->get_pathfor('/aipending/aipending_record')
            );
        }

        return $paths;
    }

    /**
     * Processes each restored AI pending record.
     *
     * @param array $data The raw data for each record from the backup XML.
     * @return void
     */
    public function process_local_assign_ai_pending($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        // Map to new course and assignment context.
        $data->courseid = $this->get_courseid();
        $data->assignmentid = $this->task->get_moduleid();

        // Map restored user if exists.
        if (!empty($data->userid)) {
            $data->userid = $this->get_mappingid('user', $data->userid);
        }

        // If user mapping fails (e.g. user excluded from restore), skip record.
        if (empty($data->userid)) {
            return;
        }

        // Check if record already exists (avoid duplicates).
        $exists = $DB->get_record('local_assign_ai_pending', [
            'assignmentid' => $data->assignmentid,
            'userid' => $data->userid,
        ]);

        if ($exists) {
            // Update existing record.
            $data->id = $exists->id;
            $data->timemodified = time();
            $DB->update_record('local_assign_ai_pending', $data);
            $newid = $exists->id;
        } else {
            // Insert a new record.
            unset($data->id);
            $data->approval_token = bin2hex(random_bytes(8));
            $data->timemodified = time();
            $newid = $DB->insert_record('local_assign_ai_pending', $data);
        }

        // Register mapping for consistency.
        $this->set_mapping('local_assign_ai_pending', $oldid, $newid);
    }
}
