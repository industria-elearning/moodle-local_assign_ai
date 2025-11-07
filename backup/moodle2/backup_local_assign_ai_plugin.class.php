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
 * Backup handler for the local_assign_ai plugin.
 *
 * Defines the structure and data to include when backing up AI-generated
 * reviews and pending approval records from the `local_assign_ai_pending` table.
 *
 * @package    local_assign_ai
 * @category   backup
 * @copyright  2025 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Backup subplugin class for the local_assign_ai plugin.
 *
 * Handles the inclusion of AI-generated assignment review data
 * in the Moodle backup structure.
 *
 * @package    local_assign_ai
 * @category   backup
 */
class backup_local_assign_ai_plugin extends backup_local_plugin {
    /**
     * Defines the structure of the data included in the assignment backup.
     *
     * This creates an <aipending> node under the assignment activity XML,
     * containing all AI review data linked to that specific instance.
     *
     * @return backup_subplugin_element
     */
    protected function define_assign_subplugin_structure() {
        // Reference to <plugin> node in the backup XML.
        $plugin = $this->get_subplugin_element();

        // Wrapper element for this plugin's data.
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());

        // Define main nodes.
        $aipending = new backup_nested_element('aipending');
        $aipendingrecord = new backup_nested_element('aipending_record', ['id'], [
            'courseid',
            'assignmentid',
            'title',
            'userid',
            'message',
            'grade',
            'rubric_response',
            'status',
            'approval_token',
            'timemodified',
        ]);

        // Build XML hierarchy.
        $plugin->add_child($pluginwrapper);
        $pluginwrapper->add_child($aipending);
        $aipending->add_child($aipendingrecord);

        // Include AI data only if user info is backed up.
        if ($this->get_setting_value('userinfo')) {
            $aipendingrecord->set_source_table('local_assign_ai_pending', [
                'assignmentid' => backup::VAR_ACTIVITYID,
            ]);

            // Annotate user id for mapping during restore.
            $aipendingrecord->annotate_ids('user', 'userid');
        }

        return $plugin;
    }

    /**
     * Define any course-level backup structure (none for this plugin).
     *
     * @return void
     */
    protected function define_course_subplugin_structure() {
        // No course-level data to back up.
    }
}
