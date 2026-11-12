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
 * A big file into a library's catalogue, in the background.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\task;

use local_oksigeniaayvurenda\local\import\runner;

/**
 * Imports a file too big for the import page's time, as the person who started it, and tells them how it went. It does
 * not try again on its own: a file that cannot be imported says why, and the library can import again. If the process
 * dies on the way (out of memory, killed), Moodle runs it again and it goes on (what came in is not repeated); dead twice,
 * it says so and stops.
 */
class run_import extends \core\task\adhoc_task {
    /**
     * Its name, in the list of tasks.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskrunimport', 'local_oksigeniaayvurenda');
    }

    /**
     * Runs it.
     */
    public function execute(): void {
        global $DB;
        $data = json_decode(json_encode($this->get_custom_data()), true);
        $pending = (array) ($data['pending'] ?? []);
        $library = $DB->get_record('local_oksigeniaayvurenda_library', ['id' => (int) ($data['library'] ?? 0)]);
        if (!$library) {
            @unlink((string) ($pending['path'] ?? ''));
            return;
        }
        \core_php_time_limit::raise();
        raise_memory_limit(MEMORY_HUGE);
        $userid = (int) $this->get_userid();
        $libraryid = (int) $library->id;
        try {
            if (!is_file((string) ($pending['path'] ?? ''))) {
                throw new \moodle_exception('importunknown', 'local_oksigeniaayvurenda');
            }
            if (runner::started($libraryid) > runner::TRIES) {
                throw new \moodle_exception('importdied', 'local_oksigeniaayvurenda');
            }
            $done = runner::run($library, $pending, (array) ($data['options'] ?? []), fn() => runner::beat($libraryid));
            mtrace('Ayvurenda: imported «' . $pending['name'] . '» into library ' . $library->id . ': '
                . json_encode(array_column($done['figures'], 'count')));
            runner::tell($userid, $library, (string) $pending['name'], $done);
        } catch (\Throwable $e) {
            if ($DB->is_transaction_started()) {
                $DB->force_transaction_rollback();
            }
            mtrace('Ayvurenda: import into library ' . $library->id . ' failed: ' . $e->getMessage());
            runner::tell($userid, $library, (string) ($pending['name'] ?? ''), null, $e->getMessage());
        } finally {
            @unlink((string) ($pending['path'] ?? ''));
            runner::over((int) $library->id);
        }
    }
}
