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
 * Tells readers and tutors about late books.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\task;

/**
 * Every morning: readers with a book late hear about it (on the first day, then once a week), tutors get, once a
 * week, who in their group has books late, and whoever signed up gets the library's news, once a week at most.
 */
class send_notices extends \core\task\scheduled_task {
    /**
     * Name of the task.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('tasksendnotices', 'local_oksigeniaayvurenda');
    }

    /**
     * Runs it.
     */
    public function execute(): void {
        global $DB;
        // It runs every hour; each centre is told in its own morning (8 in its zone), once a day: not at dawn in America
        // because the server is in Europe. The late books and what waits for the staff, on weekdays; what expires, every
        // day (a library open on Saturday does not keep a book set aside until Monday).
        $now = time();
        $week = \local_oksigeniaayvurenda\local\zones::mornings($now);
        $every = \local_oksigeniaayvurenda\local\zones::mornings($now, false);
        mtrace('Ayvurenda: centres whose morning it is: ' . json_encode(['weekdays' => $week, 'every day' => $every]));
        // Each step apart: one that fails does not stop the others, nor the other centres' notices.
        $steps = [
            'voices past the days the centre keeps them' => fn() => \local_oksigeniaayvurenda\local\voices::expire(),
            'voices of reviews past those days' => fn() => \local_oksigeniaayvurenda\local\reviews::expire_voices(),
        ];
        if ($week) {
            $steps += [
                'notices of late books' => fn() => \local_oksigeniaayvurenda\local\notices::run($now, $week),
                'what waits for the staff' => fn() => \local_oksigeniaayvurenda\local\staffnotices::run($now, $week),
            ];
        }
        if ($every) {
            $steps += [
                'reservations no longer set aside' => fn() => \local_oksigeniaayvurenda\local\holds::expire($now, $every),
                'reservations set aside nobody was told of' => fn() => \local_oksigeniaayvurenda\local\holds::pending(
                    $now,
                    $every
                ),
                'readers told what a late book left' => fn() => \local_oksigeniaayvurenda\local\sanctions::notify($now, $every),
                'the library\'s news to whoever signed up' => fn() => \local_oksigeniaayvurenda\local\newsletter::run(
                    $now,
                    $every
                ),
                'the big numbers of the year to the channel' => fn() => \local_oksigeniaayvurenda\local\channel::numbers(
                    $now,
                    $every
                ),
            ];
        }
        foreach ($steps as $what => $step) {
            try {
                mtrace('Ayvurenda: ' . $what . ': ' . json_encode($step()));
            } catch (\Throwable $e) {
                if ($DB->is_transaction_started()) {
                    $DB->force_transaction_rollback();
                }
                mtrace('Ayvurenda: ' . $what . ' failed: ' . $e->getMessage());
            }
        }
        \local_oksigeniaayvurenda\local\zones::mornings_done($week, $now);
        \local_oksigeniaayvurenda\local\zones::mornings_done($every, $now, false);
    }
}
