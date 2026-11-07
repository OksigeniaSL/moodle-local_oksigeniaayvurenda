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
 * Labels and spine labels to print.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

use local_oksigeniaayvurenda\core\barcode\code39;
use local_oksigeniaayvurenda\core\label_sheets;

/**
 * The copies whose labels are to be printed (those added today, since a day, or a list of codes) and the sheets of
 * labels sold everywhere, so a half-used sheet can be used up starting at any label.
 */
final class labels {
    /** @var int Labels in one page: more would not be a page, the day a whole catalogue is imported. */
    public const LIMIT = 2000;

    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * What a library keeps of its sheets: its favourites of each kind, in its order, and how much it moves the print of
     * each sheet in its printer.
     *
     * @param \stdClass $library
     * @return array favs (kind => keys) and nudge (key => [x, y]).
     */
    public static function own(\stdClass $library): array {
        global $DB;
        $settings = json_decode((string) $DB->get_field(self::T . 'library', 'settings', ['id' => $library->id]), true) ?: [];
        $own = (array) ($settings['labelsheets'] ?? []);
        $favs = [];
        foreach (['labels', 'spines'] as $kind) {
            $favs[$kind] = array_values(array_filter(
                (array) ($own['favs'][$kind] ?? []),
                fn($key) => isset(label_sheets::MODELS[$key])
            ));
        }
        $nudge = [];
        foreach ((array) ($own['nudge'] ?? []) as $key => $xy) {
            if (isset(label_sheets::MODELS[$key])) {
                $nudge[$key] = [(float) ($xy[0] ?? 0), (float) ($xy[1] ?? 0)];
            }
        }
        return ['favs' => $favs, 'nudge' => $nudge];
    }

    /**
     * Marks a sheet as a favourite of a kind, or unmarks it.
     *
     * @param \stdClass $library
     * @param string $kind labels or spines.
     * @param string $key
     * @return bool Whether it is a favourite now.
     */
    public static function favourite(\stdClass $library, string $kind, string $key): bool {
        $own = self::own($library);
        $kind = $kind === 'spines' ? 'spines' : 'labels';
        if (!isset(label_sheets::MODELS[$key])) {
            return false;
        }
        $was = in_array($key, $own['favs'][$kind], true);
        $own['favs'][$kind] = $was ? array_values(array_diff($own['favs'][$kind], [$key]))
            : array_slice(array_merge([$key], $own['favs'][$kind]), 0, 12);
        self::keep($library, $own);
        return !$was;
    }

    /**
     * Keeps how much this library moves the print of a sheet.
     *
     * @param \stdClass $library
     * @param string $key
     * @param float $x Millimetres to the right.
     * @param float $y Millimetres down.
     */
    public static function nudge(\stdClass $library, string $key, float $x, float $y): void {
        if (!isset(label_sheets::MODELS[$key])) {
            return;
        }
        $own = self::own($library);
        $clamp = fn(float $v) => round(max(-label_sheets::NUDGE, min(label_sheets::NUDGE, $v)), 1);
        $own['nudge'][$key] = [$clamp($x), $clamp($y)];
        if (!$own['nudge'][$key][0] && !$own['nudge'][$key][1]) {
            unset($own['nudge'][$key]);
        }
        self::keep($library, $own);
    }

    /**
     * The sheet a page shows: the one asked for, else the first favourite of its kind, else the default.
     *
     * @param \stdClass $library
     * @param string $kind
     * @param string $asked
     * @return string
     */
    public static function chosen(\stdClass $library, string $kind, string $asked): string {
        if ($asked !== '') {
            return label_sheets::key($asked, $kind);
        }
        $favs = self::own($library)['favs'][$kind === 'spines' ? 'spines' : 'labels'];
        return label_sheets::key($favs[0] ?? '', $kind);
    }

    /**
     * Writes what the library keeps of its sheets.
     *
     * @param \stdClass $library
     * @param array $own
     */
    private static function keep(\stdClass $library, array $own): void {
        global $DB;
        $settings = json_decode((string) $DB->get_field(self::T . 'library', 'settings', ['id' => $library->id]), true) ?: [];
        $settings['labelsheets'] = $own;
        $DB->update_record(self::T . 'library', (object) ['id' => $library->id, 'settings' => json_encode($settings),
            'timemodified' => time()]);
        $library->settings = json_encode($settings);
    }

    /**
     * The copies of a library to label.
     *
     * @param \stdClass $library
     * @param string $which today, since or codes.
     * @param int $since For since: a moment.
     * @param string[] $codes For codes: the codes, in their order.
     * @param int|null $now
     * @return array[] barcode, title, callnumber (the three lines), library.
     */
    public static function items(\stdClass $library, string $which, int $since = 0, array $codes = [], ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $fields = 'i.id, i.barcode, i.callnumber1, i.callnumber2, i.callnumber3, i.serial, r.title';
        $from = '{' . self::T . 'item} i JOIN {' . self::T . 'record} r ON r.id = i.recordid';
        if ($which === 'codes') {
            $codes = array_values(array_unique(array_filter(array_map('trim', $codes), 'strlen')));
            if (!$codes) {
                return [];
            }
            [$in, $params] = $DB->get_in_or_equal($codes, SQL_PARAMS_NAMED);
            $rows = $DB->get_records_sql(
                "SELECT $fields FROM $from WHERE i.entityid = :entityid AND i.barcode $in",
                $params + ['entityid' => $library->entityid]
            );
            $bycode = [];
            foreach ($rows as $row) {
                $bycode[$row->barcode] = $row;
            }
            $rows = array_values(array_filter(array_map(fn($c) => $bycode[$c] ?? null, $codes)));
        } else {
            $start = $which === 'since' ? $since : zones::today($library, $now);
            $rows = array_values($DB->get_records_sql(
                "SELECT $fields FROM $from WHERE i.libraryid = :libraryid AND i.timecreated >= :start ORDER BY i.id",
                ['libraryid' => $library->id, 'start' => $start],
                0,
                self::LIMIT + 1
            ));
        }
        $name = format_string($library->name, true, ['escape' => false]);
        return array_map(fn($row) => [
            'blank' => false,
            'barcode' => $row->barcode,
            'title' => $row->title,
            'callnumber' => array_values(array_filter(
                [$row->callnumber1, $row->callnumber2, $row->callnumber3],
                fn($l) => (string) $l !== ''
            )),
            'library' => $name,
            'svg' => code39::can($row->barcode) ? code39::svg($row->barcode, 40, true) : '',
        ], $rows);
    }

    /**
     * The labels laid out in sheets, the first ones left empty when the sheet was already started.
     *
     * @param array[] $labels
     * @param string $sheet A key of label_sheets::MODELS (or an old one).
     * @param int $start The first label to use, from 1.
     * @return array[] Sheets, each with its labels (with blank set for a label left blank).
     */
    public static function sheets(array $labels, string $sheet, int $start = 1): array {
        $format = label_sheets::placed($sheet);
        $perpage = $format['per'];
        $start = max(1, min($perpage, $start));
        // A blank says so itself: in Mustache, a missing key is looked up in the page around it.
        $cells = array_merge(array_fill(0, $start - 1, ['blank' => true]), $labels);
        return array_map(fn($chunk) => ['labels' => $chunk], array_chunk($cells, $perpage));
    }
}
