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

/**
 * Retrieves cached configuration for a given assignment instance.
 *
 * @param int $assignmentid The assignment instance ID (from {assign}).
 * @return stdClass|null
 */
function local_assign_ai_get_assignment_config(int $assignmentid) {
    global $DB;

    static $cache = [];

    if (!$assignmentid) {
        return null;
    }

    if (!array_key_exists($assignmentid, $cache)) {
        $cache[$assignmentid] = $DB->get_record('local_assign_ai_config', ['assignmentid' => $assignmentid]);
    }

    return $cache[$assignmentid];
}

/**
 * Checks whether auto-grading is enabled for a given assignment.
 *
 * @param assign $assign Assignment instance.
 * @return bool
 */
function local_assign_ai_is_autograde_enabled(assign $assign): bool {
    $config = local_assign_ai_get_assignment_config($assign->get_instance()->id);
    return !empty($config) && !empty($config->autograde);
}

/**
 * Applies AI feedback (grade + comments) to a submission and triggers grading events.
 *
 * @param assign $assign Assignment instance.
 * @param stdClass $record Pending AI record.
 * @param int|null $graderid User applying the change.
 * @return void
 */
function local_assign_ai_apply_ai_feedback(assign $assign, stdClass $record, ?int $graderid = null): void {
    global $DB;

    $grade = $assign->get_user_grade($record->userid, true);
    if (!$grade) {
        debugging("No grade exists for userid={$record->userid}, assignid={$assign->get_instance()->id}.", DEBUG_DEVELOPER);
        return;
    }

    $gradepushed = false;
    $instancegrade = (float) $assign->get_instance()->grade;

    if ($record->grade !== null && $record->grade !== '') {
        if ($instancegrade > 0) {
            $gradevalue = max(0, min((float)$record->grade, $instancegrade));
            $grade->grade = $gradevalue;
            if ($graderid) {
                $grade->grader = $graderid;
            }
            $gradepushed = $assign->update_grade($grade);
        } else {
            debugging('The assignment uses a scale; automatic numeric grading is not supported.', DEBUG_DEVELOPER);
        }
    }

    $feedback = $DB->get_record('assignfeedback_comments', ['grade' => $grade->id]);
    if ($feedback) {
        $feedback->commenttext = $record->message;
        $feedback->commentformat = FORMAT_HTML;
        $DB->update_record('assignfeedback_comments', $feedback);
    } else {
        $feedback = (object)[
            'assignment' => $assign->get_instance()->id,
            'grade' => $grade->id,
            'commenttext' => $record->message,
            'commentformat' => FORMAT_HTML,
        ];
        $DB->insert_record('assignfeedback_comments', $feedback);
    }

    if (!$gradepushed) {
        $event = \mod_assign\event\submission_graded::create_from_grade($assign, $grade);
        $event->trigger();
    }
}
