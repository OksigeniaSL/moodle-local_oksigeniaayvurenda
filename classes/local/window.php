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
 * The public window of a library: a page anyone can open without an account, with what is licensed for everyone.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

use local_oksigeniaayvurenda\core\text;

/**
 * Closed until the centre opens it. It shows only the digital forms meant for the public that their licence lets out,
 * and the voices approved for the public; never who recorded them nor who reads what.
 */
final class window {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var int Works on a page of the window and of its catalogue. */
    public const PAGE = 30;

    /** @var array Someone without an account, as digital::viewer() gives it. */
    public const ANYONE = ['who' => 'nobody', 'stage' => null, 'userid' => 0, 'readerid' => 0];

    /**
     * The window's settings: open or not, the centre's words, whether voices show, whether search engines may list it,
     * and whether anyone may see the catalogue and the shelves (what there is and whether it is free; never who has it).
     *
     * @param \stdClass $library
     * @return array open, text, voices, index, catalogue.
     */
    public static function settings(\stdClass $library): array {
        $settings = json_decode((string) ($library->settings ?? ''), true) ?: [];
        $window = (array) ($settings['window'] ?? []);
        return [
            'open' => (bool) ($window['open'] ?? false),
            'text' => (string) ($window['text'] ?? ''),
            'voices' => (bool) ($window['voices'] ?? true),
            'index' => (bool) ($window['index'] ?? false),
            'catalogue' => (bool) ($window['catalogue'] ?? false),
        ];
    }

    /**
     * Whether anyone, without an account, may walk the catalogue and the shelves of a library: its window is open and
     * the centre chose to show them.
     *
     * @param \stdClass|null $library
     * @return bool
     */
    public static function catalogue_open(?\stdClass $library): bool {
        if (!$library) {
            return false;
        }
        $settings = self::settings($library);
        return $settings['open'] && $settings['catalogue'];
    }

    /**
     * What is moving in the library, with no one in it: how many books went out today, the corner most books came from
     * this week, how many arrived this month. Only what is more than nothing.
     *
     * @param \stdClass $library
     * @param int|null $now
     * @return array today, corner (its name, or '') and new, as numbers.
     */
    public static function pulse(\stdClass $library, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $tz = \core_date::get_server_timezone_object();
        $today = (new \DateTimeImmutable('@' . $now))->setTimezone($tz)->setTime(0, 0)->getTimestamp();
        $out = $DB->count_records_select(
            self::T . 'loan',
            'libraryid = :libraryid AND timeloaned >= :today',
            ['libraryid' => $library->id, 'today' => $today]
        );
        $rows = $DB->get_records_sql(
            "SELECT l.id, (SELECT MIN(a.code)
                             FROM {" . self::T . "recauth} ra
                             JOIN {" . self::T . "authority} a ON a.id = ra.authorityid AND a.kind = 'class'
                            WHERE ra.recordid = i.recordid) AS class
               FROM {" . self::T . "loan} l
               JOIN {" . self::T . "item} i ON i.id = l.itemid
              WHERE l.libraryid = :libraryid AND l.timeloaned >= :week",
            ['libraryid' => $library->id, 'week' => $now - WEEKSECS],
            0,
            2000
        );
        $corners = [];
        foreach ($rows as $row) {
            $first = $row->class !== null ? \core_text::substr(trim($row->class), 0, 1) : '';
            if ($first !== '' && ctype_digit($first)) {
                $corners[$first] = ($corners[$first] ?? 0) + 1;
            }
        }
        $corner = '';
        if ($corners) {
            arsort($corners);
            $top = (string) array_key_first($corners);
            foreach (catalogue::corners($library) as $known) {
                if ($known['code'] === $top) {
                    $corner = $known['label'];
                }
            }
        }
        $new = (int) $DB->count_records_sql(
            "SELECT COUNT(DISTINCT recordid) FROM {" . self::T . "item}
              WHERE libraryid = :libraryid AND COALESCE(timeacquired, timecreated) >= :month AND status <> 'withdrawn'",
            ['libraryid' => $library->id, 'month' => $now - 30 * DAYSECS]
        );
        return ['today' => $out, 'corner' => $corner, 'new' => $new];
    }

    /**
     * Saves the window's settings.
     *
     * @param \stdClass $library
     * @param array $data open, text, voices, index, catalogue.
     */
    public static function save(\stdClass $library, array $data): void {
        global $DB;
        $settings = json_decode((string) $DB->get_field(self::T . 'library', 'settings', ['id' => $library->id]), true) ?: [];
        $settings['window'] = [
            'open' => !empty($data['open']),
            'text' => \core_text::substr(trim((string) ($data['text'] ?? '')), 0, 4000),
            'voices' => !empty($data['voices']),
            'index' => !empty($data['index']),
            'catalogue' => !empty($data['catalogue']),
        ];
        $DB->update_record(self::T . 'library', (object) ['id' => $library->id, 'settings' => json_encode($settings),
            'timemodified' => time()]);
        $library->settings = json_encode($settings);
    }

    /**
     * The open window of a library, by its short name.
     *
     * @param string $ref
     * @return \stdClass|null
     */
    public static function open(string $ref): ?\stdClass {
        $library = $ref !== '' ? catalogue::library($ref) : null;
        return $library && self::settings($library)['open'] ? $library : null;
    }

    /**
     * Whether a digital form is in the window of some library of its entity.
     *
     * @param \stdClass $item
     * @return bool
     */
    public static function shows(\stdClass $item): bool {
        global $DB;
        if (!digital::visible($item, self::ANYONE)) {
            return false;
        }
        foreach ($DB->get_records(self::T . 'library', ['entityid' => $item->entityid], 'id', 'id, settings') as $library) {
            $settings = self::settings($library);
            if ($settings['open'] && ($item->kind !== 'voice' || $settings['voices'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether a record has something in an open window, or its centre shows its catalogue there, so that its cover
     * can be shown.
     *
     * @param int $recordid
     * @return bool
     */
    public static function shows_record(int $recordid): bool {
        global $DB;
        // A library that shows its catalogue to anyone shows every cover of its centre.
        $entityid = (int) $DB->get_field(self::T . 'record', 'entityid', ['id' => $recordid]);
        foreach ($DB->get_records(self::T . 'library', ['entityid' => $entityid], 'id', 'id, settings') as $library) {
            if (self::catalogue_open($library)) {
                return true;
            }
        }
        foreach ($DB->get_records(self::T . 'digital', ['recordid' => $recordid, 'status' => 'published']) as $item) {
            if (self::shows($item)) {
                return true;
            }
        }
        return false;
    }

    /**
     * The records with something in the window, newest first, each with what it has there.
     *
     * @param \stdClass $library
     * @param string $query Words of the title or the authors.
     * @param int $page
     * @return array records (id, title, subtitle, authors, lang, summary, cover, updated, items), and more.
     */
    public static function records(\stdClass $library, string $query = '', int $page = 0): array {
        global $DB;
        $voices = self::settings($library)['voices'];
        $params = ['entityid' => $library->entityid, 'public' => '%,public,%'];
        $where = "d.entityid = :entityid AND d.status = 'published'
            AND " . $DB->sql_like($DB->sql_concat("','", 'd.audience', "','"), ':public')
            . ($voices ? '' : " AND d.kind <> 'voice'");
        foreach (array_slice(text::words(text::normalise($query)), 0, 6) as $n => $word) {
            $where .= ' AND ' . $DB->sql_like('r.searchtext', ':w' . $n, false);
            $params['w' . $n] = '%' . $DB->sql_like_escape($word) . '%';
        }
        $rows = $DB->get_records_sql(
            "SELECT d.*, r.title AS rtitle, r.subtitle AS rsubtitle, r.lang AS rlang, r.summary AS rsummary
               FROM {" . self::T . "digital} d
               JOIN {" . self::T . "record} r ON r.id = d.recordid
              WHERE $where
           ORDER BY d.timecreated DESC, d.id DESC",
            $params
        );
        $records = [];
        foreach ($rows as $row) {
            // The licence and the family's permission have the last word, row by row.
            if (!digital::visible($row, self::ANYONE)) {
                continue;
            }
            $id = (int) $row->recordid;
            $records[$id] = $records[$id] ?? ['id' => $id, 'title' => $row->rtitle, 'subtitle' => (string) $row->rsubtitle,
                'lang' => (string) $row->rlang, 'summary' => (string) $row->rsummary, 'updated' => 0, 'items' => []];
            $records[$id]['items'][] = $row;
            $records[$id]['updated'] = max($records[$id]['updated'], (int) $row->timemodified);
        }
        $total = count($records);
        $records = array_slice($records, $page * self::PAGE, self::PAGE, true);
        if ($records) {
            $covers = covers::urls(array_keys($records));
            [$in, $inparams] = $DB->get_in_or_equal(array_keys($records), SQL_PARAMS_NAMED);
            $links = $DB->get_records_sql(
                "SELECT ra.id, ra.recordid, a.label
                   FROM {" . self::T . "recauth} ra
                   JOIN {" . self::T . "authority} a ON a.id = ra.authorityid
                  WHERE ra.recordid $in AND a.kind = 'author'
               ORDER BY ra.sortorder, ra.id",
                $inparams
            );
            foreach ($records as $id => $record) {
                $records[$id]['cover'] = $covers[$id] ?? null;
                $records[$id]['authors'] = [];
            }
            foreach ($links as $link) {
                $records[(int) $link->recordid]['authors'][] = catalogue::display_name($link->label);
            }
        }
        return ['records' => array_values($records), 'more' => $total > ($page + 1) * self::PAGE, 'total' => $total];
    }
}
