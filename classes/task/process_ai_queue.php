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

namespace local_assign_ai\task;


use local_assign_ai\task\process_submission_ai;

/**
 * Scheduled task to process delayed AI queue for assignments.
 *
 * @package    local_assign_ai
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_ai_queue extends \core\task\scheduled_task {
    /**
     * Return the task name shown in admin screens.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_process_ai_queue', 'local_assign_ai');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $DB;

        $now = time();

        // Get pending items whose time has arrived.
        $items = $DB->get_records_select(
            'local_assign_ai_queue',
            'processed = 0 AND timetoprocess <= ?',
            [$now],
            'timetoprocess ASC',
            '*',
            0,
            20
        );

        foreach ($items as $item) {
            $data = json_decode($item->payload);

            try {
                if ($item->type === 'submission') {
                    $task = new process_submission_ai();
                    $task->set_custom_data($data);
                    \core\task\manager::queue_adhoc_task($task);
                }

                $item->processed = 1;
                $DB->update_record('local_assign_ai_queue', $item);
            } catch (\Throwable $e) {
                debugging('Error processing Assign AI queue: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
    }
}
