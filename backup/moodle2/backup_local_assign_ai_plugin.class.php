<?php
// This file is part of Moodle - http://moodle.org/
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
 * Backup handler for the local_assign_ai plugin.
 *
 * Defines the structure and data to include when backing up assignment-related
 * AI records and pending approval data from the local_assign_ai_pending table.
 *
 * @package    local_assign_ai
 * @category   backup
 * @copyright  2025 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_local_assign_ai_plugin extends backup_local_plugin {
    /**
     * Define the structure of the data included in the backup.
     *
     * This method specifies which database tables and fields are exported
     * for the local_assign_ai plugin as part of the assignment backup process.
     *
     * @return backup_subplugin_element
     */
    protected function define_assign_subplugin_structure() {
        // Reference to <plugin> node in the backup XML.
        $plugin = $this->get_subplugin_element();

        // Wrapper element for this plugin's data.
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());

        // Define the table data structure for pending AI records.
        $pending = new backup_nested_element('assign_ai_pending', ['id'], [
            'courseid',
            'assignmentid',
            'title',
            'userid',
            'message',
            'grade',
            'rubric_response',
            'status',
            'approval_token',
        ]);

        // Build the XML tree.
        $plugin->add_child($pluginwrapper);
        $pluginwrapper->add_child($pending);

        // Define the data source.
        $pending->set_source_table('local_assign_ai_pending', [
            'assignmentid' => backup::VAR_ACTIVITYID,
        ]);

        return $plugin;
    }

    /**
     * Define any course-level backup structure if required (none here).
     *
     * @return void
     */
    protected function define_course_subplugin_structure() {
        // No course-level data required.
    }
}
