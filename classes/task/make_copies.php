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
 * The daily copy of every library.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\task;

/**
 * Every night, a copy of everything each entity holds, in open formats, for the library staff to download.
 */
class make_copies extends \core\task\scheduled_task {
    /**
     * Name of the task.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskmakecopies', 'local_oksigeniaayvurenda');
    }

    /**
     * Runs it.
     */
    public function execute(): void {
        global $DB;
        // The sweeps first, each apart: one that fails leaves no centre without its copy.
        $sweeps = [
            'drafts of covers' => fn() => \local_oksigeniaayvurenda\local\covers::forget_drafts(),
            'phones paired with a desk' => fn() => \local_oksigeniaayvurenda\local\relay::forget(),
            'practice libraries' => fn() => \local_oksigeniaayvurenda\local\practice::sweep(),
        ];
        foreach ($sweeps as $what => $sweep) {
            try {
                $sweep();
            } catch (\Throwable $e) {
                mtrace('Ayvurenda: ' . $what . ' failed: ' . $e->getMessage());
            }
        }
        foreach ($DB->get_fieldset_select('local_oksigeniaayvurenda_entity', 'id', '1 = 1') as $entityid) {
            if (\local_oksigeniaayvurenda\local\practice::is((int) $entityid)) {
                continue;
            }
            // What goes wrong with one centre does not leave the others without their copy.
            try {
                $file = \local_oksigeniaayvurenda\local\copies::make((int) $entityid);
                mtrace('Ayvurenda: copy of entity ' . $entityid . ': ' . $file->get_filename() . ' (' .
                    $file->get_filesize() . ' bytes)');
                // And out of the server, encrypted, where the centre chose.
                $out = \local_oksigeniaayvurenda\local\offsite::send((int) $entityid, $file);
                if ($out['sent'] || $out['failed']) {
                    mtrace('Ayvurenda: sent to ' . $out['sent'] . ' places' . ($out['failed'] ? ', failed: ' .
                        implode(', ', $out['failed']) : ''));
                }
            } catch (\Throwable $e) {
                mtrace('Ayvurenda: entity ' . $entityid . ': ' . get_class($e) . ': ' . $e->getMessage());
            }
            // The weekly copy of the files, apart: a copy that did not go out does not leave it undone.
            try {
                \local_oksigeniaayvurenda\local\filecopy::night((int) $entityid);
            } catch (\Throwable $e) {
                mtrace('Ayvurenda: files of entity ' . $entityid . ': ' . get_class($e) . ': ' . $e->getMessage());
            }
        }
        \local_oksigeniaayvurenda\local\filecopy::sweep_temp();
        \local_oksigeniaayvurenda\local\cardprint::sweep();
    }
}
