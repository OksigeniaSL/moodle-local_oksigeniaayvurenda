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
 * Brings a reviewed file into a library's catalogue.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local\import;

use local_oksigeniaayvurenda\core\abies\export_reader;
use local_oksigeniaayvurenda\core\import\catalogue_csv;
use local_oksigeniaayvurenda\core\marc\charset;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\notices;
use local_oksigeniaayvurenda\local\plans;
use local_oksigeniaayvurenda\local\screens;

/**
 * A reviewed file into a library's catalogue: in the page when it fits in its time, and in the background (the task
 * run_import) when it is big (the catalogue of a university, hundreds of thousands of records), with a message to whoever
 * started it when it is done and no second import in that library meanwhile.
 */
final class runner {
    /** @var int A file bigger than this goes to the background: some tens of thousands of records. */
    public const BACKGROUND = 8 * 1024 * 1024;

    /** @var int A background import with no sign of life for this long is taken as dead: the library can import again. */
    private const STALE = 6 * HOURSECS;

    /** @var int Times a background import may start: one that died twice (out of memory, killed) is not tried again. */
    public const TRIES = 2;

    /**
     * Imports a reviewed file.
     *
     * @param \stdClass $library
     * @param array $pending path, name, kind (abies, csv, marc, marcxml, onix…) and charset.
     * @param array $options notes and readers (an Abies export); map, the CSV columns.
     * @param callable|null $beat Said after each batch of records: the import is alive.
     * @return array figures (count, label), again and newcodes: what the page or the message says.
     */
    public static function run(\stdClass $library, array $pending, array $options, ?callable $beat = null): array {
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        // A count with the word that goes with it, one or many.
        $figure = fn(int $count, string $key) => ['count' => screens::number($count),
            'label' => $str($key . ($count === 1 ? 'one' : ''))];
        $entityid = (int) $library->entityid;
        if ($pending['kind'] === 'abies') {
            $importer = new abies_import((int) $library->id, !empty($options['notes']), !empty($options['readers']));
            $stats = plans::within($entityid, fn() => $importer->run(new export_reader(files::abies_xml($pending['path']))));
            $figures = [$figure($stats['record']['created'] ?? 0, 'importnewrecords'),
                $figure($stats['item']['created'] ?? 0, 'importnewitems')];
            if (!empty($options['readers'])) {
                $figures[] = $figure($stats['reader']['created'] ?? 0, 'importfigreaders');
                $figures[] = $figure($stats['loan']['created'] ?? 0, 'importfigloans');
            }
            $here = 0;
            $made = 0;
            foreach ($stats as $counts) {
                $here += ($counts['changedhere'] ?? 0) + ($counts['deletedhere'] ?? 0) + ($counts['erased'] ?? 0);
                $made += $counts['madehere'] ?? 0;
            }
            $again = [$str('importabiesagain')];
            if ($here) {
                $again[] = $str('importabieshere');
            }
            if ($made) {
                $again[] = $str('importabiesmade');
            }
            $relabelled = array_map(fn($copy) => $copy[0] . ' → ' . $copy[1], $importer->relabelled());
            catalogue::purge($entityid);
            return ['figures' => $figures, 'again' => implode(' ', $again),
                'newcodes' => $relabelled ? $str('importrelabelled', implode(', ', $relabelled)) : ''];
        }
        $map = array_filter((array) ($options['map'] ?? []), fn($f) => in_array($f, catalogue_csv::FIELDS, true));
        $records = $pending['kind'] === 'csv' ? files::csv_records($pending['path'], $map)
            : files::marc($pending['path'], $pending['kind'], $pending['charset'] ?? charset::AUTO);
        $importer = new catalogue_import($library, $pending['kind'] === 'csv' ? 'csv' : 'marc');
        if ($beat) {
            $importer->on_batch($beat);
        }
        $stats = plans::within($entityid, fn() => $importer->run($records));
        catalogue::purge($entityid);
        return [
            'figures' => [$figure($stats['records'], 'importnewrecords'), $figure($stats['items'], 'importnewitems')],
            'again' => $stats['found'] || $stats['kept'] ? $str('importagain', (object) ['records' => $stats['found'],
                'items' => $stats['kept']]) : '',
            'newcodes' => trim(($stats['newcodes'] ? $str('importnewcodes', $stats['newcodes']) : '') . ' '
                . (!empty($stats['untitled']) ? $str(
                    $stats['untitled'] === 1 ? 'importuntitledone' : 'importuntitled',
                    $stats['untitled']
                ) : '') . ' ' . (!empty($stats['updated']) ? $str('importupdated', $stats['updated']) : '')
                . ' ' . ($importer->relabelled() ? $str('importrelabelled', implode(
                    ', ',
                    array_map(fn($copy) => $copy[0] . ' → ' . $copy[1], $importer->relabelled())
                )) : '')),
        ];
    }

    /**
     * Leaves a big file to the background: kept where the task finds it, the task queued as the person who started it,
     * and the library marked as importing.
     *
     * @param \stdClass $library
     * @param array $pending
     * @param array $options
     * @param int $userid
     * @param int|null $now
     */
    public static function queue(\stdClass $library, array $pending, array $options, int $userid, ?int $now = null): void {
        $path = make_upload_directory('local_oksigeniaayvurenda/imports') . '/' . random_string(20) . '.upload';
        if (!@rename($pending['path'], $path)) {
            copy($pending['path'], $path);
            @unlink($pending['path']);
        }
        $pending['path'] = $path;
        $task = new \local_oksigeniaayvurenda\task\run_import();
        $task->set_custom_data(['library' => (int) $library->id, 'pending' => $pending, 'options' => $options]);
        $task->set_userid($userid);
        \core\task\manager::queue_adhoc_task($task);
        set_config('importing_' . $library->id, json_encode(['name' => $pending['name'], 'time' => $now ?? time(),
            'userid' => $userid]), 'local_oksigeniaayvurenda');
    }

    /**
     * What is being imported into a library in the background: the file's name and since when; null when nothing is, or
     * when it has given no sign of life in six hours (it beats at each batch of records, so a long one is still alive).
     *
     * @param \stdClass $library
     * @param int|null $now
     * @return array|null name, time, userid.
     */
    public static function running(\stdClass $library, ?int $now = null): ?array {
        $mark = json_decode((string) get_config('local_oksigeniaayvurenda', 'importing_' . $library->id), true);
        $alive = is_array($mark) ? max((int) ($mark['time'] ?? 0), (int) ($mark['alive'] ?? 0)) : 0;
        return $alive > ($now ?? time()) - self::STALE ? $mark : null;
    }

    /**
     * A background import starts: its mark is alive from now. It says how many times it has started, the first one 1.
     *
     * @param int $libraryid
     * @param int|null $now
     * @return int
     */
    public static function started(int $libraryid, ?int $now = null): int {
        $mark = json_decode((string) get_config('local_oksigeniaayvurenda', 'importing_' . $libraryid), true) ?: [];
        $mark['tries'] = (int) ($mark['tries'] ?? 0) + 1;
        $mark['alive'] = $now ?? time();
        set_config('importing_' . $libraryid, json_encode($mark), 'local_oksigeniaayvurenda');
        return $mark['tries'];
    }

    /**
     * A background import is still going: at each batch of records.
     *
     * @param int $libraryid
     * @param int|null $now
     */
    public static function beat(int $libraryid, ?int $now = null): void {
        $mark = json_decode((string) get_config('local_oksigeniaayvurenda', 'importing_' . $libraryid), true);
        if (is_array($mark)) {
            $mark['alive'] = $now ?? time();
            set_config('importing_' . $libraryid, json_encode($mark), 'local_oksigeniaayvurenda');
        }
    }

    /**
     * The library is no longer importing.
     *
     * @param int $libraryid
     */
    public static function over(int $libraryid): void {
        unset_config('importing_' . $libraryid, 'local_oksigeniaayvurenda');
    }

    /**
     * Tells whoever started a background import how it went: what came in, or why it could not.
     *
     * @param int $userid
     * @param \stdClass $library
     * @param string $name The file's name.
     * @param array|null $done What run() said; null when it failed.
     * @param string $why Why it failed.
     * @return bool Whether the message went.
     */
    public static function tell(int $userid, \stdClass $library, string $name, ?array $done, string $why = ''): bool {
        $user = \core_user::get_user($userid);
        if (!$user || $user->deleted || $user->suspended) {
            return false;
        }
        $language = force_current_language((string) $user->lang);
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        $place = format_string($library->name, true, ['escape' => false]);
        if ($done !== null) {
            $figures = implode(' · ', array_map(fn($figure) => $figure['count'] . ' ' . $figure['label'], $done['figures']));
            $subject = $str('importbgdonesubject', $name);
            $body = trim($str('importbgdonebody', (object) ['library' => $place, 'figures' => $figures]) . ' ' . $done['again']
                . ' ' . $done['newcodes']);
            $page = 'index.php';
        } else {
            $subject = $str('importbgfailedsubject', $name);
            $body = $str('importfailed', $why);
            $page = 'import.php';
        }
        $url = (new \moodle_url('/local/oksigeniaayvurenda/' . $page, ['library' => $library->slug]))->out(false);
        $told = notices::send('stafftodo', $user, $subject, $body, $url, $place, $place);
        force_current_language($language);
        return $told;
    }
}
