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

namespace local_assign_ai\config;

use assign;

/**
 * Assignment configuration helpers for local_assign_ai.
 *
 * @package     local_assign_ai
 * @copyright   2025 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assignment_config {
    /**
     * Retrieves cached configuration for a given assignment instance.
     *
     * @param int $assignmentid The assignment instance ID (from {assign}).
     * @return \stdClass|null
     */
    public static function get(int $assignmentid): ?\stdClass {
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
    public static function is_autograde_enabled(assign $assign): bool {
        $config = self::get($assign->get_instance()->id);
        return !empty($config) && !empty($config->autograde);
    }
}
