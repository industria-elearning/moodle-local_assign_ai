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
 * Local library functions for local_assign_ai.
 *
 * @package     local_assign_ai
 * @copyright   2025 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/grade/grading/lib.php');

/**
 * Converts the assignment rubric into simplified JSON.
 *
 * @param assign $assign The assignment instance.
 * @return array|null Simplified rubric array or null if no rubric is active.
 * @package local_assign_ai
 */
function local_assign_ai_build_rubric_json(assign $assign) {
    global $DB;

    $context = $assign->get_context();

    // Initialize grading manager.
    $gradingmanager = get_grading_manager($context, 'mod_assign', 'submissions');
    $method = $gradingmanager->get_active_method();

    if ($method !== 'rubric') {
        return null;
    }

    $controller = $gradingmanager->get_controller('rubric');
    if (!$controller) {
        return null;
    }

    $definition = $controller->get_definition();
    if (empty($definition) || empty($definition->rubric_criteria)) {
        return null;
    }

    $rubric = [
        'title' => $definition->name ?? get_string('default_rubric_name', 'local_assign_ai'),
        'description' => $definition->description ?? '',
        'criteria' => [],
    ];

    foreach ($definition->rubric_criteria as $criterionid => $criterion) {
        $crit = [
            'criterion' => $criterion['description'],
            'levels' => [],
        ];

        foreach ($criterion['levels'] as $levelid => $level) {
            $crit['levels'][] = [
                'points' => (float) $level['score'],
                'description' => $level['definition'],
            ];
        }

        $rubric['criteria'][] = $crit;
    }

    return $rubric;
}
