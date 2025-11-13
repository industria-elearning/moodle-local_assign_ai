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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/assign_ai/locallib.php');

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

        $historyurl = new moodle_url('/local/assign_ai/history.php', ['id' => $PAGE->cm->id]);
        $modulesettings->add(
            get_string('reviewhistory', 'local_assign_ai'),
            $historyurl,
            navigation_node::TYPE_SETTING,
            null,
            'assign_ai_history',
            new pix_icon('i/report', '')
        );
    }
}

/**
 * Adds AI configuration elements to the module form.
 *
 * @param moodleform_mod $formwrapper
 * @param MoodleQuickForm $mform
 * @return void
 */
function local_assign_ai_coursemodule_standard_elements($formwrapper, $mform) {
    global $USER;

    if ($formwrapper->get_current()->modulename !== 'assign') {
        return;
    }

    $courseid = $formwrapper->get_course()->id ?? $formwrapper->get_current()->course ?? null;
    if (!$courseid) {
        return;
    }

    $context = context_course::instance($courseid);
    if (!has_capability('moodle/course:manageactivities', $context, $USER)) {
        return;
    }

    $assignid = $formwrapper->get_current()->instance ?? 0;
    $config = $assignid ? local_assign_ai_get_assignment_config($assignid) : null;
    $default = $config->autograde ?? 0;

    $mform->addElement('header', 'local_assign_ai_header', get_string('aiconfigheader', 'local_assign_ai'));
    $mform->addElement(
        'select',
        'local_assign_ai_autograde',
        get_string('autograde', 'local_assign_ai'),
        [0 => get_string('no'), 1 => get_string('yes')]
    );
    $mform->addHelpButton('local_assign_ai_autograde', 'autograde', 'local_assign_ai');
    $mform->setDefault('local_assign_ai_autograde', $default);
}

/**
 * Persists AI configuration when the assignment form is submitted.
 *
 * @param stdClass $data
 * @param stdClass $course
 * @return stdClass
 */
function local_assign_ai_coursemodule_edit_post_actions($data, $course) {
    global $DB, $USER;

    if ($data->modulename !== 'assign' || empty($data->instance)) {
        return $data;
    }

    $record = $DB->get_record('local_assign_ai_config', ['assignmentid' => $data->instance]);
    $config = (object)[
        'assignmentid' => $data->instance,
        'autograde' => empty($data->local_assign_ai_autograde) ? 0 : 1,
        'timemodified' => time(),
        'usermodified' => $USER->id ?? null,
    ];

    if ($record) {
        $config->id = $record->id;
        $DB->update_record('local_assign_ai_config', $config);
    } else {
        $config->timecreated = time();
        $DB->insert_record('local_assign_ai_config', $config);
    }

    return $data;
}
