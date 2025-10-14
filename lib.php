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
 * Extiende la navegación de las tareas para mostrar el botón "Revisión con IA".
 *
 * @param settings_navigation $nav     The settings navigation object.
 * @param context             $context The current context.
 * @package local_assign_ai
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

    // Solo cargar en grader con token IA.
    if ($cmid && $action === 'grader' && !empty($aitoken)) {
        $PAGE->requires->js_call_amd('local_assign_ai/inject_ai', 'init', [$aitoken]);
    }
}
