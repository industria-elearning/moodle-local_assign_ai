<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Configuración del plugin Forum AI.
 *
 * @package     local_assign_ai
 * @copyright  2025 Piero Llanos
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_assign_ai_get_details' => [
        'classname'   => 'local_assign_ai\external\get_details',
        'methodname'  => 'execute',
        'classpath'   => '',
        'description' => 'Obtener detalles de retroalimentación IA',
        'type'        => 'read',
        'ajax'        => true,
    ],
    'local_assign_ai_update_response' => [
        'classname'   => 'local_assign_ai\external\update_response',
        'methodname'  => 'execute',
        'classpath'   => '',
        'description' => 'Actualizar mensaje IA',
        'type'        => 'write',
        'ajax'        => true,
    ],
    'local_assign_ai_change_status' => [
        'classname'   => 'local_assign_ai\external\change_status',
        'methodname'  => 'execute',
        'classpath'   => '',
        'description' => 'Cambiar estado de retroalimentación IA',
        'type'        => 'write',
        'ajax'        => true,
    ],
    'local_assign_ai_review_submission' => [
        'classname'   => 'local_assign_ai\external\review_submission',
        'methodname'  => 'execute',
        'classpath'   => '',
        'description' => 'Cambiar estado de retroalimentación IA',
        'type'        => 'write',
        'ajax'        => true,
    ],
];
