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

/**
 * The copies whose labels are to be printed (those added today, since a day, or a list of codes) and the sheets of
 * labels sold everywhere, so a half-used sheet can be used up starting at any label.
 */
final class labels {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var array<string, array> Sheets of A4 labels: columns, rows, label width and height in mm, page margins. */
    public const SHEETS = [
        '3x8' => ['columns' => 3, 'rows' => 8, 'width' => 70, 'height' => 37, 'top' => 0.5, 'left' => 0, 'gap' => 0],
        '3x7' => ['columns' => 3, 'rows' => 7, 'width' => 70, 'height' => 42.3, 'top' => 0.5, 'left' => 0, 'gap' => 0],
        '4x10' => ['columns' => 4, 'rows' => 10, 'width' => 48.5, 'height' => 25.4, 'top' => 21.5, 'left' => 8, 'gap' => 0],
        '5x13' => ['columns' => 5, 'rows' => 13, 'width' => 38, 'height' => 21.2, 'top' => 10.7, 'left' => 9.5, 'gap' => 0],
    ];

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
            $start = $which === 'since' ? $since : (new \DateTimeImmutable('@' . $now))
                ->setTimezone(\core_date::get_server_timezone_object())->setTime(0, 0)->getTimestamp();
            $rows = array_values($DB->get_records_sql(
                "SELECT $fields FROM $from WHERE i.libraryid = :libraryid AND i.timecreated >= :start ORDER BY i.id",
                ['libraryid' => $library->id, 'start' => $start]
            ));
        }
        $name = format_string($library->name);
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
     * @param string $sheet One of SHEETS.
     * @param int $start The first label to use, from 1.
     * @return array[] Sheets, each with its labels (with blank set for a label left blank).
     */
    public static function sheets(array $labels, string $sheet, int $start = 1): array {
        $format = self::SHEETS[$sheet] ?? self::SHEETS['3x8'];
        $perpage = $format['columns'] * $format['rows'];
        $start = max(1, min($perpage, $start));
        // A blank says so itself: in Mustache, a missing key is looked up in the page around it.
        $cells = array_merge(array_fill(0, $start - 1, ['blank' => true]), $labels);
        return array_map(fn($chunk) => ['labels' => $chunk], array_chunk($cells, $perpage));
    }
}
