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
 * Library functions for local_assign_ai.
 *
 * @package     local_assign_ai
 * @copyright   2025 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Extends assignment navigation to display the "AI Review" button.
 *
 * @param settings_navigation $nav     The settings navigation object.
 * @param context             $context The current context.
 * @package local_assign_ai
 */
function local_assign_ai_extend_settings_navigation(settings_navigation $nav, context $context) {
    global $PAGE, $DB;

    // Verify that we are in a module (activity) context.
    if ($context->contextlevel != CONTEXT_MODULE) {
        return;
    }

    // Verify that it is an assignment.
    if ($PAGE->cm->modname !== 'assign') {
        return;
    }

    // Find the module settings node.
    $modulesettings = $nav->find('modulesettings', navigation_node::TYPE_SETTING);

    if ($modulesettings) {
        $url = new moodle_url('/local/assign_ai/review.php', ['id' => $PAGE->cm->id]);

        $modulesettings->add(
            get_string('reviewwithai', 'local_assign_ai'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'assign_ai_config',
            new pix_icon('i/settings', '')
        );
    }
}
