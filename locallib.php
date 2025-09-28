<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/grade/grading/lib.php');

/**
 * Convierte la rúbrica de una tarea en JSON simplificado.
 *
 * @param assign $assign
 * @return array|null
 */
function build_rubric_json(assign $assign) {
    global $DB;

    $context = $assign->get_context();

    // Inicializar grading manager
    $gradingmanager = get_grading_manager($context, 'mod_assign', 'submissions');
    $method = $gradingmanager->get_active_method();

    if ($method !== 'rubric') {
        return null; // No hay rúbrica activa
    }

    // Cargar la rúbrica
    $controller = $gradingmanager->get_controller('rubric');
    if (!$controller) {
        return null;
    }

    $definition = $controller->get_definition();
    if (empty($definition) || empty($definition->rubric_criteria)) {
        return null;
    }

    $rubric = [
        'title' => $definition->name ?? 'Rúbrica',
        'description' => $definition->description ?? '',
        'criteria' => []
    ];

    foreach ($definition->rubric_criteria as $criterionid => $criterion) {
        $crit = [
            'criterion' => $criterion['description'],
            'levels' => []
        ];

        foreach ($criterion['levels'] as $levelid => $level) {
            $crit['levels'][] = [
                'points' => (float) $level['score'],
                'description' => $level['definition']
            ];
        }

        $rubric['criteria'][] = $crit;
    }

    return $rubric;
}
