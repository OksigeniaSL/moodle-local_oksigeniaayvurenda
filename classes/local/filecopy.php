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

namespace local_oksigeniaayvurenda\local;

use local_oksigeniaayvurenda\core\spreadsheet;

/**
 * The copy of a centre's files: covers, the digital collection, the drawings of its reviews, the pictures of its libraries and
 * its own characters. Apart from the daily copy, because it can weigh a hundred times more. Voices never leave the site.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class filecopy {
    /** @var string Where the last one is kept. */
    public const AREA = 'filecopies';

    /** @var string[] The areas it carries, each with the table its item id belongs to. */
    public const AREAS = ['cover' => 'record', 'digital' => 'digital', 'reviewdrawing' => 'review', 'libraryimage' => 'library',
        'portrait' => 'entity'];

    /** @var int Days between one and the next when it goes out of the server. */
    public const EVERY = 7;

    /** @var int Days it is kept on the server. */
    public const KEEP = 8;

    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * The last one made.
     *
     * @param int $entityid
     * @return \stored_file|null
     */
    public static function latest(int $entityid): ?\stored_file {
        $files = get_file_storage()->get_area_files(
            \context_system::instance()->id,
            'local_oksigeniaayvurenda',
            self::AREA,
            $entityid,
            'timecreated DESC, id DESC',
            false
        );
        return $files ? reset($files) : null;
    }

    /**
     * The name of one made on a day.
     *
     * @param int $entityid
     * @param int $time
     * @return string
     */
    public static function name(int $entityid, int $time): string {
        return 'ayvurenda-' . $entityid . '-ficheros-' . date('Ymd', $time) . '.zip';
    }

    /**
     * Makes it, in place of the last one.
     *
     * @param int $entityid
     * @param int|null $now
     * @return \stored_file
     */
    public static function make(int $entityid, ?int $now = null): \stored_file {
        global $CFG, $DB;
        require_once($CFG->libdir . '/filestorage/zip_archive.php');
        $now = $now ?? time();
        $fs = get_file_storage();
        $dir = make_request_directory();
        $path = $dir . '/ficheros.zip';
        $zip = new \zip_archive();
        $zip->open($path, \file_archive::CREATE);
        $titles = $DB->get_records_menu(self::T . 'record', ['entityid' => $entityid], '', 'id, title');
        $list = fopen($dir . '/ficheros.csv', 'w');
        fputcsv($list, ['area', 'item', 'path', 'file', 'what', 'size'], ',', '"', '');
        $total = 0;
        foreach (array_keys(self::AREAS) as $area) {
            $rows = self::owned($entityid, $area);
            foreach ($rows as $row) {
                $file = $fs->get_file_instance($row);
                $inzip = $area . '/' . $row->itemid . '/' . $row->filename;
                $file->archive_file($zip, $inzip);
                $what = $area === 'cover' ? spreadsheet::cell((string) ($titles[$row->itemid] ?? '')) : '';
                fputcsv($list, [$area, $row->itemid, $inzip, $row->filename, $what, $row->filesize], ',', '"', '');
                $total++;
            }
            $rows->close();
        }
        fclose($list);
        $zip->add_file_from_pathname('ficheros.csv', $dir . '/ficheros.csv');
        // The rows of the digital collection that have a file: without them, its files would say nothing.
        $digital = fopen($dir . '/digital.csv', 'w');
        $columns = array_keys($DB->get_columns(self::T . 'digital'));
        fputcsv($digital, $columns, ',', '"', '');
        $rows = $DB->get_recordset(self::T . 'digital', ['entityid' => $entityid, 'kind' => 'file'], 'id');
        foreach ($rows as $row) {
            fputcsv($digital, array_map(fn($c) => $row->$c, $columns), ',', '"', '');
        }
        $rows->close();
        fclose($digital);
        $zip->add_file_from_pathname('digital.csv', $dir . '/digital.csv');
        $entity = $DB->get_record(self::T . 'entity', ['id' => $entityid], 'id, name', MUST_EXIST);
        $zip->add_file_from_string('origen.json', (string) json_encode(['entity' => (int) $entity->id, 'made' => $now]));
        $zip->add_file_from_string('LEEME.txt', get_string('filecopyreadme', 'local_oksigeniaayvurenda', (object) [
            'entity' => format_string($entity->name, true, ['escape' => false]),
            'date' => userdate($now, get_string('strftimedatetime', 'langconfig')),
            'count' => $total,
        ]));
        $zip->close();
        $context = \context_system::instance();
        $fs->delete_area_files($context->id, 'local_oksigeniaayvurenda', self::AREA, $entityid);
        return $fs->create_file_from_pathname([
            'contextid' => $context->id,
            'component' => 'local_oksigeniaayvurenda',
            'filearea' => self::AREA,
            'itemid' => $entityid,
            'filepath' => '/',
            'filename' => self::name($entityid, $now),
            'timecreated' => $now,
            'timemodified' => $now,
        ], $path);
    }

    /**
     * Takes away what an import or a recovery left waiting in the temporary folder, with its readers in clear, if nobody came back.
     *
     * @param int|null $now
     * @return int How many.
     */
    public static function sweep_temp(?int $now = null): int {
        global $CFG;
        $old = ($now ?? time()) - DAYSECS;
        $gone = 0;
        foreach (glob($CFG->tempdir . '/local_oksigeniaayvurenda/*') ?: [] as $path) {
            if (is_file($path) && filemtime($path) < $old && @unlink($path)) {
                $gone++;
            }
        }
        return $gone;
    }

    /**
     * The files of an area that belong to a centre.
     *
     * @param int $entityid
     * @param string $area
     * @return \moodle_recordset
     */
    private static function owned(int $entityid, string $area): \moodle_recordset {
        global $DB;
        $where = [
            'record' => 'o.entityid = :entityid',
            'review' => 'o.entityid = :entityid',
            'library' => 'o.entityid = :entityid',
            'entity' => 'o.id = :entityid',
            // Voices never leave the site.
            'digital' => "o.entityid = :entityid AND o.kind = 'file'",
        ][self::AREAS[$area]];
        return $DB->get_recordset_sql(
            'SELECT f.*
               FROM {files} f
               JOIN {' . self::T . self::AREAS[$area] . '} o ON o.id = f.itemid
              WHERE f.contextid = :contextid AND f.component = :component AND f.filearea = :area AND f.filename <> :dot
                    AND ' . $where . '
           ORDER BY f.id',
            ['contextid' => \context_system::instance()->id, 'component' => 'local_oksigeniaayvurenda', 'area' => $area,
                'dot' => '.', 'entityid' => $entityid]
        );
    }

    /**
     * Whether one is being made or waits to be.
     *
     * @param int $entityid
     * @return bool
     */
    public static function waiting(int $entityid): bool {
        foreach (\core\task\manager::get_adhoc_tasks(\local_oksigeniaayvurenda\task\make_filecopy::class) as $task) {
            if ((int) ($task->get_custom_data()->entity ?? 0) === $entityid) {
                return true;
            }
        }
        return false;
    }

    /**
     * Asks for one, at once if the site's tasks are free: it can take a while, and no page can wait for it.
     *
     * @param int $entityid
     */
    public static function queue(int $entityid): void {
        $task = new \local_oksigeniaayvurenda\task\make_filecopy();
        $task->set_custom_data(['entity' => $entityid]);
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * What the night does: asks for a new one if the centre takes its copies out of the server and it is time, and forgets
     * one that has been kept long enough.
     *
     * @param int $entityid
     * @param int|null $now
     */
    public static function night(int $entityid, ?int $now = null): void {
        $now = $now ?? time();
        $latest = self::latest($entityid);
        $settings = offsite::settings($entityid);
        $out = plans::allows($entityid, 'offsite') && $settings['recipient'] !== '' && ($settings['places'] || $settings['pull']);
        if ($out && (!$latest || (int) $latest->get_timecreated() <= $now - (self::EVERY - 1) * DAYSECS)) {
            self::queue($entityid);
        } else if (!$out && $latest && (int) $latest->get_timecreated() <= $now - self::KEEP * DAYSECS) {
            $latest->delete();
        }
    }
}
