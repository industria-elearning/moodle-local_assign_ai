<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Extiende la navegación de las tareas para mostrar el botón "Revisión con IA".
 */
// function local_assign_ai_extend_navigation_module($navigation, $cm) {
//     global $PAGE;

//     if ($cm->modname === 'assign' && optional_param('action', '', PARAM_ALPHA) === 'grading') {
//         $url = new moodle_url('/local/assign_ai/review.php', ['id' => $cm->id]);
//         $navigation->add(
//             get_string('reviewwithai', 'local_assign_ai'),
//             $url,
//             navigation_node::TYPE_CUSTOM,
//             null,
//             null,
//             new pix_icon('i/ai', '') // si no tienes icono, se puede omitir
//         );
//     }
// }

/**
 * Extiende la navegación de las tareas para mostrar el botón "Revisión con IA".
 */
function local_assign_ai_extend_settings_navigation(settings_navigation $nav, context $context) {
    global $PAGE, $DB;

    // Verificar que estemos en un contexto de módulo (actividad).
    if ($context->contextlevel != CONTEXT_MODULE) {
        return;
    }

    // Verificar que sea un foro.
    if ($PAGE->cm->modname !== 'assign') {
        return;
    }

    // Buscar el nodo de configuraciones del módulo.
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

/**
 * Inyecta el JS de IA en el grader si hay un token.
 */
function local_assign_ai_before_footer() {
    global $PAGE;

    $cmid   = optional_param('id', 0, PARAM_INT);
    $action = optional_param('action', '', PARAM_ALPHA);
    $aitoken = optional_param('aitoken', '', PARAM_ALPHANUM);

    // Solo cargar en grader con token IA
    if ($cmid && $action === 'grader' && !empty($aitoken)) {
        $PAGE->requires->js_call_amd('local_assign_ai/inject_ai', 'init', [$aitoken]);
    }
}
