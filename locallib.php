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
require_once($CFG->dirroot . '/mod/assign/locallib.php');

/**
 * Converts the assignment advanced grading (rubric or guide) into simplified JSON.
 *
 * @param assign $assign The assignment instance.
 * @return array|null Simplified grading array or null if no advanced grading is active.
 */
function local_assign_ai_get_advanced_grading_json(assign $assign) {
    global $DB;

    $context = $assign->get_context();
    $gradingmanager = get_grading_manager($context, 'mod_assign', 'submissions');
    $method = $gradingmanager->get_active_method();

    if (empty($method)) {
        return null;
    }

    $controller = $gradingmanager->get_controller($method);
    if (!$controller) {
        return null;
    }

    $definition = $controller->get_definition();
    if (empty($definition)) {
        return null;
    }

    $gradingdata = [
        'method' => $method,
        'title' => $definition->name ?? '',
        'description' => $definition->description ?? '',
        'criteria' => [],
    ];

    if ($method === 'rubric' && !empty($definition->rubric_criteria)) {
        foreach ($definition->rubric_criteria as $criterionid => $criterion) {
            $crit = [
                'id' => $criterionid,
                'description' => $criterion['description'],
                'levels' => [],
            ];

            foreach ($criterion['levels'] as $levelid => $level) {
                $crit['levels'][] = [
                    'id' => $levelid,
                    'points' => (float) $level['score'],
                    'description' => $level['definition'],
                ];
            }
            $gradingdata['criteria'][] = $crit;
        }
    } else if ($method === 'guide' && !empty($definition->guide_criteria)) {
        foreach ($definition->guide_criteria as $criterionid => $criterion) {
            $gradingdata['criteria'][] = [
                'id' => $criterionid,
                'shortname' => $criterion['shortname'],
                'description' => $criterion['description'],
                'descriptionmarkers' => $criterion['descriptionmarkers'],
                'maxscore' => (float) $criterion['maxscore'],
            ];
        }
    } else {
        return null;
    }

    return $gradingdata;
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
 * @param assign $assign The assignment instance.
 * @return bool
 */
function local_assign_ai_is_autograde_enabled(assign $assign): bool {
    $config = local_assign_ai_get_assignment_config($assign->get_instance()->id);
    return !empty($config) && !empty($config->autograde);
}

/**
 * Applies AI feedback (grade + comments) to a submission.
 *
 * This is the main dispatcher that identifies the grading method and calls
 * the appropriate handler.
 *
 * @param assign $assign The assignment instance.
 * @param stdClass $record The pending AI record.
 * @param int $graderid The user ID applying the change.
 * @return void
 */
function local_assign_ai_apply_ai_feedback(assign $assign, stdClass $record, int $graderid): void {
    global $DB;

    $grade = $assign->get_user_grade($record->userid, true);
    if (!$grade) {
        debugging("No grade exists for userid={$record->userid}.", DEBUG_DEVELOPER);
        return;
    }

    $gradepushed = false;
    $gradingmanager = get_grading_manager($assign->get_context(), 'mod_assign', 'submissions');
    $method = $gradingmanager->get_active_method();

    if ($method === 'rubric' && !empty($record->rubric_response)) {
        $gradepushed = local_assign_ai_apply_rubric_grading($assign, $grade, $record, $graderid, $gradingmanager);
    } else if ($method === 'guide' && !empty($record->rubric_response)) {
        $gradepushed = local_assign_ai_apply_guide_grading($assign, $grade, $record, $graderid, $gradingmanager);
    }

    // Default to simple grading if no advanced grading was successful or used.
    if (!$gradepushed) {
        $gradepushed = local_assign_ai_apply_simple_grading($assign, $grade, $record, $graderid);
    }

    // Always save feedback comments regardless of the grading method.
    local_assign_ai_save_feedback_comments($assign, $grade, $record->message);

    // Trigger event if not already pushed (though update_grade usually triggers it).
    if (!$gradepushed) {
        $event = \mod_assign\event\submission_graded::create_from_grade($assign, $grade);
        $event->trigger();
    }
}

/**
 * Handles rubric grading application.
 *
 * @param assign $assign The assignment instance.
 * @param stdClass $grade The user grade record.
 * @param stdClass $record The pending AI record.
 * @param int $graderid The user ID applying the change.
 * @param grading_manager $gradingmanager The grading manager.
 * @return bool True on success, false otherwise.
 */
function local_assign_ai_apply_rubric_grading($assign, $grade, $record, $graderid, $gradingmanager) {
    global $DB;
    $controller = $gradingmanager->get_controller('rubric');

    // Set grade range.
    $grademenu = local_assign_ai_get_grade_menu($assign);
    $controller->set_grade_range($grademenu, $controller->get_allow_grade_decimals());

    $definition = $controller->get_definition();
    $rubricdata = json_decode($record->rubric_response, true);

    if (!$definition || empty($rubricdata) || !is_array($rubricdata)) {
        return false;
    }

    $instance = $controller->get_or_create_instance(0, $graderid, $grade->id);
    $fillingdata = ['criteria' => []];
    $moodlecriteria = $definition->rubric_criteria;

    foreach ($rubricdata as $criteriondata) {
        $criteriondesc = trim($criteriondata['criterion'] ?? '');
        $levels = $criteriondata['levels'] ?? [];

        if (empty($levels) || $criteriondesc === '') {
            continue;
        }

        $aiclean = trim(strip_tags($criteriondesc));

        foreach ($moodlecriteria as $criterionid => $criterion) {
            $moodleclean = trim(strip_tags($criterion['description']));

            if ($moodleclean === $aiclean) {
                $leveldata = reset($levels);
                $points = (float) ($leveldata['points'] ?? 0);
                $remark = $leveldata['comment'] ?? '';

                foreach ($criterion['levels'] as $levelid => $level) {
                    $levelscore = (float)$level['score'];
                    if (abs($levelscore - $points) < 0.0001) {
                        $fillingdata['criteria'][$criterionid] = [
                            'levelid' => $levelid,
                            'remark' => $remark,
                        ];
                        break;
                    }
                }
                break;
            }
        }
    }

    if (empty($fillingdata['criteria'])) {
        return false;
    }

    try {
        $grade->grade = $instance->submit_and_get_grade($fillingdata, $grade->id);
        $grade->grader = $graderid;
        local_assign_ai_advance_marking_workflow($assign, $record->userid);
        return $assign->update_grade($grade);
    } catch (\Exception $e) {
        debugging("local_assign_ai: Rubric error: " . $e->getMessage(), DEBUG_DEVELOPER);
        return false;
    }
}

/**
 * Handles grading guide application.
 *
 * @param assign $assign The assignment instance.
 * @param stdClass $grade The user grade record.
 * @param stdClass $record The pending AI record.
 * @param int $graderid The user ID applying the change.
 * @param grading_manager $gradingmanager The grading manager.
 * @return bool True on success, false otherwise.
 */
function local_assign_ai_apply_guide_grading($assign, $grade, $record, $graderid, $gradingmanager) {
    global $DB;
    $controller = $gradingmanager->get_controller('guide');

    $grademenu = local_assign_ai_get_grade_menu($assign);
    $controller->set_grade_range($grademenu, $controller->get_allow_grade_decimals());

    $definition = $controller->get_definition();
    $guidedata = json_decode($record->rubric_response, true);

    if (!$definition || empty($guidedata) || !is_array($guidedata)) {
        return false;
    }

    $instance = $controller->get_or_create_instance(0, $graderid, $grade->id);
    $fillingdata = ['criteria' => []];
    $moodlecriteria = $definition->guide_criteria;

    foreach ($guidedata as $item) {
        $aicriterion = trim(strip_tags($item['criterion'] ?? ''));
        if ($aicriterion === '') {
            continue;
        }

        foreach ($moodlecriteria as $id => $criterion) {
            $moodlecriterion = trim(strip_tags($criterion['shortname']));
            if ($moodlecriterion === $aicriterion) {
                $fillingdata['criteria'][$id] = [
                    'score' => (float) ($item['score'] ?? 0),
                    'remark' => $item['comment'] ?? '',
                ];
                break;
            }
        }
    }

    if (empty($fillingdata['criteria'])) {
        return false;
    }

    try {
        $grade->grade = $instance->submit_and_get_grade($fillingdata, $grade->id);
        $grade->grader = $graderid;
        local_assign_ai_advance_marking_workflow($assign, $record->userid);
        return $assign->update_grade($grade);
    } catch (\Exception $e) {
        debugging("local_assign_ai: Guide error: " . $e->getMessage(), DEBUG_DEVELOPER);
        return false;
    }
}

/**
 * Handles simple direct grading (numeric).
 *
 * @param assign $assign The assignment instance.
 * @param stdClass $grade The user grade record.
 * @param stdClass $record The pending AI record.
 * @param int $graderid The user ID applying the change.
 * @return bool True on success, false otherwise.
 */
function local_assign_ai_apply_simple_grading($assign, $grade, $record, $graderid) {
    if ($record->grade === null || $record->grade === '') {
        return false;
    }

    $instancegrade = (float) $assign->get_instance()->grade;
    if ($instancegrade <= 0) {
        return false; // Scales not supported for automatic numeric grading yet.
    }

    $grade->grade = max(0, min((float)$record->grade, $instancegrade));
    $grade->grader = $graderid;

    local_assign_ai_advance_marking_workflow($assign, $record->userid);
    return $assign->update_grade($grade);
}

/**
 * Helper to get the grade menu or scale for a grading controller.
 *
 * @param assign $assign The assignment instance.
 * @return array The grade menu or scale map.
 */
function local_assign_ai_get_grade_menu($assign) {
    global $DB;
    $grademenu = [];
    $instancegrade = $assign->get_instance()->grade;
    if ($instancegrade > 0) {
        $grademenu = make_grades_menu($instancegrade);
    } else if ($instancegrade < 0) {
        $scale = $DB->get_record('scale', ['id' => -($instancegrade)]);
        if ($scale) {
            $grademenu = make_menu_from_list($scale->scale);
        }
    }
    return $grademenu;
}

/**
 * Helper to advance the marking workflow state for a user to 'Released'.
 *
 * @param assign $assign The assignment instance.
 * @param int $userid The student user ID.
 * @return void
 */
function local_assign_ai_advance_marking_workflow($assign, $userid) {
    if ($assign->get_instance()->markingworkflow) {
        $flags = $assign->get_user_flags($userid, true);
        $flags->workflowstate = ASSIGN_MARKING_WORKFLOW_STATE_RELEASED;
        $assign->update_user_flags($flags);
    }
}

/**
 * Helper to save feedback comments for a given submission.
 *
 * @param assign $assign The assignment instance.
 * @param stdClass $grade The user grade record.
 * @param string|null $message The AI feedback message.
 * @return void
 */
function local_assign_ai_save_feedback_comments($assign, $grade, $message) {
    global $DB;
    if (empty($message)) {
        return;
    }

    $feedback = $DB->get_record('assignfeedback_comments', ['grade' => $grade->id]);
    if ($feedback) {
        $feedback->commenttext = $message;
        $feedback->commentformat = FORMAT_HTML;
        $DB->update_record('assignfeedback_comments', $feedback);
    } else {
        $feedback = (object)[
            'assignment' => $assign->get_instance()->id,
            'grade' => $grade->id,
            'commenttext' => $message,
            'commentformat' => FORMAT_HTML,
        ];
        $DB->insert_record('assignfeedback_comments', $feedback);
    }
}
