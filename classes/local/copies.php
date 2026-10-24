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
 * The daily copy of a library.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

use local_oksigeniaayvurenda\core\marc\encoder;
use local_oksigeniaayvurenda\core\marc\iso2709;
use local_oksigeniaayvurenda\core\marc\marcxml;

/**
 * Every day, everything an entity's libraries hold, in open formats (CSV in a zip, with a note of what each file is,
 * and the catalogue in MARC21, which any library program imports),
 * kept in the site for the library staff to download with one tap. It does not depend on Moodle's own backups, which
 * only the site administrator sees: when a service goes down, the library has yesterday's copy at hand. The last seven
 * are kept.
 */
final class copies {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var int Copies kept for each entity. */
    public const KEEP = 7;

    /** @var string[] Tables of an entity, and how to find its rows. */
    private const TABLES = [
        'entity' => 'id = :entityid',
        'library' => 'entityid = :entityid',
        'year' => 'entityid = :entityid',
        'authority' => 'entityid = :entityid',
        'record' => 'entityid = :entityid',
        'recauth' => 'recordid IN (SELECT id FROM {local_oksigeniaayvurenda_record} WHERE entityid = :entityid)',
        'item' => 'entityid = :entityid',
        'reader' => 'entityid = :entityid',
        'member' => 'readerid IN (SELECT id FROM {local_oksigeniaayvurenda_reader} WHERE entityid = :entityid)',
        'loan' => 'libraryid IN (SELECT id FROM {local_oksigeniaayvurenda_library} WHERE entityid = :entityid)',
        'hold' => 'libraryid IN (SELECT id FROM {local_oksigeniaayvurenda_library} WHERE entityid = :entityid)',
        'rule' => 'libraryid IN (SELECT id FROM {local_oksigeniaayvurenda_library} WHERE entityid = :entityid)',
        'tutor' => 'yearid IN (SELECT id FROM {local_oksigeniaayvurenda_year} WHERE entityid = :entityid)',
        'source' => 'entityid = :entityid',
    ];

    /**
     * Makes today's copy of an entity and forgets the old ones beyond the last seven.
     *
     * @param int $entityid
     * @param int|null $now
     * @return \stored_file
     */
    public static function make(int $entityid, ?int $now = null): \stored_file {
        global $DB, $CFG;
        $now = $now ?? time();
        $dir = make_request_directory();
        $zip = new \ZipArchive();
        $zippath = $dir . '/copy.zip';
        $zip->open($zippath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $counts = [];
        foreach (self::TABLES as $table => $where) {
            $path = $dir . '/' . $table . '.csv';
            $handle = fopen($path, 'w');
            // A byte-order mark so that spreadsheets open accents right.
            fwrite($handle, "\xEF\xBB\xBF");
            $columns = array_keys($DB->get_columns(self::T . $table));
            fputcsv($handle, $columns, ',', '"', '\\');
            $rows = $DB->get_recordset_select(self::T . $table, $where, ['entityid' => $entityid], 'id');
            $count = 0;
            foreach ($rows as $row) {
                fputcsv($handle, array_map(fn($c) => $row->$c, $columns), ',', '"', '\\');
                $count++;
            }
            $rows->close();
            fclose($handle);
            $zip->addFile($path, $table . '.csv');
            $counts[$table] = $count;
        }
        $entity = $DB->get_record(self::T . 'entity', ['id' => $entityid], '*', MUST_EXIST);
        $counts['marc'] = self::marc($entityid, format_string($entity->name), $dir);
        $zip->addFile($dir . '/catalogo.mrc', 'catalogo.mrc');
        $zip->addFile($dir . '/catalogo.xml', 'catalogo.xml');
        $zip->addFromString('LEEME.txt', get_string('copyreadme', 'local_oksigeniaayvurenda', (object) [
            'entity' => format_string($entity->name),
            'date' => userdate($now, get_string('strftimedatetime', 'langconfig')),
            'counts' => json_encode($counts, JSON_PRETTY_PRINT),
            'release' => get_config('local_oksigeniaayvurenda', 'release'),
        ]));
        $zip->close();

        $fs = get_file_storage();
        $context = \context_system::instance();
        $filename = 'ayvurenda-' . $entityid . '-' . date('Ymd-His', $now) . '.zip';
        // Two copies in the same second (the task and the button at once): the newer one takes the name.
        $same = $fs->get_file($context->id, 'local_oksigeniaayvurenda', 'copies', $entityid, '/', $filename);
        if ($same) {
            $same->delete();
        }
        $file = $fs->create_file_from_pathname([
            'contextid' => $context->id,
            'component' => 'local_oksigeniaayvurenda',
            'filearea' => 'copies',
            'itemid' => $entityid,
            'filepath' => '/',
            'filename' => $filename,
            'timecreated' => $now,
            'timemodified' => $now,
        ], $zippath);
        foreach (array_slice(self::list($entityid), self::KEEP) as $old) {
            $old->delete();
        }
        return $file;
    }

    /**
     * The catalogue in MARC21, as ISO 2709 (catalogo.mrc) and MARCXML (catalogo.xml), a few hundred records at a time.
     *
     * @param int $entityid
     * @param string $entityname
     * @param string $dir Where to write them.
     * @return int Records written.
     */
    private static function marc(int $entityid, string $entityname, string $dir): int {
        global $DB;
        $mrc = fopen($dir . '/catalogo.mrc', 'w');
        $xml = fopen($dir . '/catalogo.xml', 'w');
        fwrite($xml, '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<collection xmlns="' . marcxml::NS . '">' . "\n");
        $count = 0;
        $last = 0;
        do {
            $records = $DB->get_records_select(
                self::T . 'record',
                'entityid = :entityid AND id > :last',
                ['entityid' => $entityid, 'last' => $last],
                'id',
                '*',
                0,
                500
            );
            if (!$records) {
                break;
            }
            [$in, $params] = $DB->get_in_or_equal(array_keys($records), SQL_PARAMS_NAMED);
            $authorities = [];
            $rows = $DB->get_recordset_sql(
                'SELECT ra.id, ra.recordid, a.kind, a.label
                   FROM {' . self::T . 'recauth} ra
                   JOIN {' . self::T . 'authority} a ON a.id = ra.authorityid
                  WHERE ra.recordid ' . $in . '
               ORDER BY ra.recordid, ra.sortorder, ra.id',
                $params
            );
            foreach ($rows as $row) {
                $authorities[$row->recordid][$row->kind][] = $row->label;
            }
            $rows->close();
            $items = [];
            $rows = $DB->get_recordset_sql(
                'SELECT i.id, i.recordid, i.barcode, i.serial, i.callnumber1, i.callnumber2, i.callnumber3,
                        b.name AS library, l.label AS location
                   FROM {' . self::T . 'item} i
                   JOIN {' . self::T . 'library} b ON b.id = i.libraryid
              LEFT JOIN {' . self::T . 'authority} l ON l.id = i.locationid
                  WHERE i.recordid ' . $in . '
               ORDER BY i.recordid, i.id',
                $params
            );
            foreach ($rows as $row) {
                $items[$row->recordid][] = ['barcode' => $row->barcode, 'serial' => $row->serial,
                    'library' => format_string($row->library), 'location' => $row->location,
                    'callnumber' => [$row->callnumber1, $row->callnumber2, $row->callnumber3]];
            }
            $rows->close();
            foreach ($records as $record) {
                $marc = encoder::encode((array) $record, $authorities[$record->id] ?? [], $items[$record->id] ?? [], $entityname);
                fwrite($mrc, iso2709::write($marc));
                fwrite($xml, marcxml::element($marc) . "\n");
                $count++;
                $last = (int) $record->id;
            }
        } while (count($records) === 500);
        fwrite($xml, '</collection>' . "\n");
        fclose($mrc);
        fclose($xml);
        return $count;
    }

    /**
     * The copies of an entity, the newest first.
     *
     * @param int $entityid
     * @return \stored_file[]
     */
    public static function list(int $entityid): array {
        $files = get_file_storage()->get_area_files(
            \context_system::instance()->id,
            'local_oksigeniaayvurenda',
            'copies',
            $entityid,
            'timecreated DESC, id DESC',
            false
        );
        return array_values($files);
    }
}
