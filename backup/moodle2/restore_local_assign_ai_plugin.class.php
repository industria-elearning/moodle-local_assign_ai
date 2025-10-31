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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Restore handler for the local_assign_ai plugin.
 *
 * Handles the restoration of AI assignment data from the
 * `local_assign_ai_pending` table during backup restoration.
 *
 * @package    local_assign_ai
 * @category   backup
 * @copyright  2025 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_local_assign_ai_plugin extends restore_local_plugin {
    /**
     * Defines the structure of the data that will be restored for each assignment.
     *
     * @return restore_path_element[]
     */
    protected function define_assign_subplugin_structure() {
        $paths = [];

        // Register XML path for the plugin's pending data.
        $paths[] = new restore_path_element('assign_ai_pending', $this->get_pathfor('/assign_ai_pending'));

        return $paths;
    }

    /**
     * Processes the restored AI pending data.
     *
     * This method handles <assign_ai_pending> elements found in the backup file
     * and reinserts them into the `local_assign_ai_pending` table, assigning
     * new approval tokens to avoid duplication.
     *
     * @param array $data The raw pending response data from the backup XML
     * @return void
     */
    public function process_assign_ai_pending($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        // Map old assignment ID to the restored instance.
        $data->assignmentid = $this->get_new_parentid('assign');
        $data->courseid = $this->get_courseid();

        // Generate a new approval token to avoid duplicates.
        $data->approval_token = \core_text::randomid(16);

        // Insert the restored record.
        $DB->insert_record('local_assign_ai_pending', $data);

        // Keep ID mapping for reference.
        $this->set_mapping('assign_ai_pending', $oldid, $data->id);
    }
}
