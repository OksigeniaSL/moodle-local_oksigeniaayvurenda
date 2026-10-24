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
 * Brings the readers from the cohorts every night.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\task;

/**
 * Every night, each entity with chosen cohorts brings its readers: new ones get a card, those who moved group get
 * their new group for this school year, those who left are marked so and keep their history.
 */
class sync_readers extends \core\task\scheduled_task {
    /**
     * Name of the task.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('tasksyncreaders', 'local_oksigeniaayvurenda');
    }

    /**
     * Runs it.
     */
    public function execute(): void {
        global $DB;
        foreach ($DB->get_records('local_oksigeniaayvurenda_entity', null, 'id', 'id, settings') as $entity) {
            $settings = json_decode((string) $entity->settings, true) ?: [];
            if (empty($settings['cohorts'])) {
                continue;
            }
            $stats = \local_oksigeniaayvurenda\local\sources\cohorts::sync((int) $entity->id);
            mtrace("Ayvurenda: entity {$entity->id}: " . json_encode($stats));
        }
    }
}
