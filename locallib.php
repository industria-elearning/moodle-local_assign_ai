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
 * @return array|null An array containing the method and the formatted data, or null if not active.
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

    if ($method === 'rubric' && !empty($definition->rubric_criteria)) {
        $data = [
            'title' => $definition->name ?? '',
            'description' => $definition->description ?? '',
            'criteria' => [],
        ];

        foreach ($definition->rubric_criteria as $criterionid => $criterion) {
            $crit = [
                'id' => $criterionid,
                'criterion' => $criterion['description'],
                'levels' => [],
            ];

            foreach ($criterion['levels'] as $levelid => $level) {
                $crit['levels'][] = [
                    'id' => $levelid,
                    'points' => (float) $level['score'],
                    'description' => $level['definition'],
                ];
            }
            $data['criteria'][] = $crit;
        }
        return ['method' => 'rubric', 'data' => $data];
    } else if ($method === 'guide' && !empty($definition->guide_criteria)) {
        $data = [
            'title' => $definition->name ?? '',
            'description' => $definition->description ?? '',
            'criteria' => [],
            'predefined_comments' => [],
        ];

        foreach ($definition->guide_criteria as $criterionid => $criterion) {
            $data['criteria'][] = [
                'id' => $criterionid,
                'criterion' => $criterion['shortname'],
                'description_students' => $criterion['descriptionmarkers'],
                'description_evaluators' => $criterion['description'],
                'maximum_score' => (float) $criterion['maxscore'],
            ];
        }

        if (!empty($definition->guide_comments)) {
            foreach ($definition->guide_comments as $comment) {
                $data['predefined_comments'][] = $comment['description'];
            }
        }
        return ['method' => 'guide', 'data' => $data];
    }

    return null;
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

    $debugmsg = '';
    $debugmsg .= "local_assign_ai_apply_ai_feedback: inicio.\n";

    $grade = $assign->get_user_grade($record->userid, true);
    if (!$grade) {
        $debugmsg .= "No grade para userid={$record->userid}.\n";
        debugging($debugmsg, DEBUG_DEVELOPER);
        debugging("No grade exists for userid={$record->userid}.", DEBUG_DEVELOPER);
        return;
    }

    $gradepushed = false;
    $gradingmanager = get_grading_manager($assign->get_context(), 'mod_assign', 'submissions');
    $method = $gradingmanager->get_active_method();

    $debugmsg .= "Metodo activo: {$method}.\n";
    $debugmsg .= "rubric_response presente: " . (!empty($record->rubric_response) ? 'si' : 'no') . ".\n";
    $debugmsg .= "assessment_guide_response presente: " . (!empty($record->assessment_guide_response) ? 'si' : 'no') . ".\n";

    if ($method === 'rubric' && !empty($record->rubric_response)) {
        $debugmsg .= "Ruta rubric seleccionada.\n";
        $gradepushed = local_assign_ai_apply_rubric_grading($assign, $grade, $record, $graderid, $gradingmanager);
        $debugmsg .= "Resultado rubric: " . ($gradepushed ? 'ok' : 'fallo') . ".\n";
    } else if ($method === 'guide' && !empty($record->assessment_guide_response)) {
        $debugmsg .= "Ruta guide seleccionada.\n";
        $gradepushed = local_assign_ai_apply_guide_grading($assign, $grade, $record, $graderid, $gradingmanager);
        $debugmsg .= "Resultado guide: " . ($gradepushed ? 'ok' : 'fallo') . ".\n";
    } else {
        $debugmsg .= "No se selecciono ruta avanzada.\n";
    }

    // Default to simple grading if no advanced grading was successful or used.
    if (!$gradepushed) {
        $debugmsg .= "Aplicando calificacion simple.\n";
        $gradepushed = local_assign_ai_apply_simple_grading($assign, $grade, $record, $graderid);
        $debugmsg .= "Resultado simple: " . ($gradepushed ? 'ok' : 'fallo') . ".\n";
    }

    // Always save feedback comments regardless of the grading method.
    local_assign_ai_save_feedback_comments($assign, $grade, $record->message);

    $debugmsg .= "Fin apply_ai_feedback.\n";
    debugging($debugmsg, DEBUG_DEVELOPER);

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

    $debugmsg = '';
    $debugmsg .= "local_assign_ai_apply_guide_grading: inicio.\n";
    $debugmsg .= "assessment_guide_response length: " . (isset($record->assessment_guide_response) ? strlen((string)$record->assessment_guide_response) : 0) . ".\n";
    $controller = $gradingmanager->get_controller('guide');

    $grademenu = local_assign_ai_get_grade_menu($assign);
    $controller->set_grade_range($grademenu, $controller->get_allow_grade_decimals());

    $definition = $controller->get_definition();
    $guidedata = json_decode($record->assessment_guide_response, true);

    if (!$definition || empty($guidedata) || !is_array($guidedata)) {
        $debugmsg .= "Guide sin definicion o guidata invalida.\n";
        $debugmsg .= "definition: " . (!empty($definition) ? 'ok' : 'null') . ".\n";
        $debugmsg .= "guidedata tipo: " . gettype($guidedata) . ".\n";
        $debugmsg .= "guidedata empty: " . (empty($guidedata) ? 'si' : 'no') . ".\n";
        debugging($debugmsg, DEBUG_DEVELOPER);
        return false;
    }

    $instance = $controller->get_or_create_instance(0, $graderid, $grade->id);
    $fillingdata = ['criteria' => []];
    $moodlecriteria = $definition->guide_criteria;

    $debugmsg .= "Total criterios Moodle: " . (is_array($moodlecriteria) ? count($moodlecriteria) : 0) . ".\n";
    if (is_array($moodlecriteria)) {
        $i = 0;
        foreach ($moodlecriteria as $id => $criterion) {
            $shortname = trim(strip_tags($criterion['shortname'] ?? ''));
            $debugmsg .= "Moodle criterio[$id]: {$shortname}.\n";
            $i++;
            if ($i >= 20) {
                $debugmsg .= "(Lista de criterios Moodle truncada a 20)\n";
                break;
            }
        }
    }

    $debugmsg .= "Guidedata keys: " . implode(', ', array_keys($guidedata)) . ".\n";

    // Guidedata is keyed by criterion name: "Criterion A" => ["grade" => 10, "reply" => ["Good", "Comments"]].
    foreach ($guidedata as $aicriterionname => $item) {
        $aicriterionclean = trim(strip_tags($aicriterionname));
        $debugmsg .= "Procesando criterio AI: {$aicriterionclean}.\n";
        $debugmsg .= "Item AI keys: " . (is_array($item) ? implode(', ', array_keys($item)) : gettype($item)) . ".\n";
        $matched = false;

        // Find matching Moodle criterion.
        foreach ($moodlecriteria as $id => $criterion) {
            $moodlecriterionclean = trim(strip_tags($criterion['shortname']));

            if (strcasecmp($moodlecriterionclean, $aicriterionclean) === 0) {
                $matched = true;
                $score = (float)($item['grade'] ?? 0);

                $remark = '';
                if (!empty($item['reply'])) {
                    if (is_array($item['reply'])) {
                        $remark = implode(', ', $item['reply']);
                    } else {
                        $remark = (string)$item['reply'];
                    }
                }

                $fillingdata['criteria'][$id] = [
                    'score' => $score,
                    'remark' => $remark,
                    'remarkformat' => FORMAT_HTML,
                ];
                $debugmsg .= "Match criterio: {$moodlecriterionclean}. score={$score}.\n";
                break;
            }
        }

        if (!$matched) {
            $debugmsg .= "Sin match para criterio AI: {$aicriterionclean}.\n";
        }
    }

    if (empty($fillingdata['criteria'])) {
        $debugmsg .= "Sin criterios para enviar.\n";
        debugging($debugmsg, DEBUG_DEVELOPER);
        return false;
    }

    try {
        $grade->grade = $instance->submit_and_get_grade($fillingdata, $grade->id);
        $grade->grader = $graderid;
        local_assign_ai_advance_marking_workflow($assign, $record->userid);
        $debugmsg .= "Guide submit OK.\n";
        debugging($debugmsg, DEBUG_DEVELOPER);
        return $assign->update_grade($grade);
    } catch (\Exception $e) {
        $debugmsg .= "Guide exception: " . $e->getMessage() . "\n";
        debugging($debugmsg, DEBUG_DEVELOPER);
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
