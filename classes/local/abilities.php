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

/**
 * «What your library can do» (Jujol, 14 November): each function a book of the house on its shelf, with what it solves,
 * what it did this school year (a number of real use, never a 0: without anything yet, in words), its light where it has
 * one, and «Show me». The functions the desk presents at their moment are here always.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class abilities {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var array[] The shelves and their functions, with the spine's colour of each: its book. */
    public const SHELVES = [
        'desk' => ['green', ['notices' => 'coral', 'holds' => 'sun', 'selfloan' => 'green']],
        'catalogue' => ['sun', ['labels' => 'green', 'purchases' => 'sun', 'window' => 'green2']],
        'readers' => ['coral', ['voices' => 'coral', 'firstbook' => 'coral', 'diploma' => 'sun', 'booklet' => 'sun']],
        'year' => ['night', ['yearsheet' => 'night', 'weekpick' => 'green']],
    ];

    /** @var string[] The functions with a light that this page turns on and off. */
    public const LIGHTS = ['notices', 'holds', 'window', 'firstbook', 'diploma', 'booklet'];

    /**
     * Whether a function's light is on.
     *
     * @param \stdClass $library
     * @param string $key
     * @return bool
     */
    public static function on(\stdClass $library, string $key): bool {
        $entityid = (int) $library->entityid;
        $notices = sanctions::settings($entityid);
        return match ($key) {
            'notices' => ($notices['noticereaders'] || $notices['noticefamilies']) && $notices['noticeevery'] !== 'hand',
            'holds' => (bool) holds::settings($entityid)['online'],
            'window' => window::settings($library, true)['open'],
            'firstbook' => firstbook::on($entityid),
            'diploma' => diploma::on($entityid),
            'booklet' => booklet::on($entityid),
            default => false,
        };
    }

    /**
     * Turns a function's light on or off.
     *
     * @param \stdClass $library
     * @param string $key
     * @param bool $on
     */
    public static function light(\stdClass $library, string $key, bool $on): void {
        $entityid = (int) $library->entityid;
        if ($key === 'notices') {
            $notices = sanctions::settings($entityid);
            sanctions::save_settings($entityid, ['noticereaders' => $on, 'noticefamilies' => $on,
                'noticeevery' => $on && $notices['noticeevery'] === 'hand' ? 'week' : $notices['noticeevery']] + $notices);
        } else if ($key === 'holds') {
            holds::save_settings($entityid, ['online' => $on] + holds::settings($entityid));
        } else if ($key === 'window') {
            window::save($library, ['open' => $on] + window::settings($library, true));
        } else if ($key === 'firstbook') {
            firstbook::save($entityid, $on);
        } else if ($key === 'diploma') {
            diploma::save($entityid, $on);
        } else if ($key === 'booklet') {
            booklet::save($entityid, $on);
        }
    }

    /**
     * What a function did this school year: a number and its words, or words alone when there is nothing yet.
     *
     * @param \stdClass $library
     * @param string $key
     * @param \stdClass $year
     * @param int $now
     * @return array number ('' when none) and words.
     */
    public static function did(\stdClass $library, string $key, \stdClass $year, int $now): array {
        global $DB;
        $entityid = (int) $library->entityid;
        $str = fn(string $k, $a = null) => get_string($k, 'local_oksigeniaayvurenda', $a);
        $span = ['libraryid' => $library->id, 'from' => (int) $year->timestart, 'to' => min($now, (int) $year->timeend)];
        $count = match ($key) {
            'notices' => $DB->count_records_select(self::T . 'loan', 'libraryid = :libraryid AND timenotified >= :from
                AND timenotified < :to', $span),
            'holds' => $DB->count_records_select(self::T . 'hold', "libraryid = :libraryid AND status = 'done'
                AND timemodified >= :from AND timemodified < :to", $span),
            'selfloan' => $DB->count_records_select(self::T . 'loan', 'libraryid = :libraryid AND postid IS NOT NULL
                AND timeloaned >= :from AND timeloaned < :to', $span),
            'labels' => $DB->count_records_select(self::T . 'item', 'libraryid = :libraryid AND timecreated >= :from
                AND timecreated < :to', $span),
            'purchases' => $DB->count_records_select(self::T . 'purchase', 'libraryid = :libraryid AND timecreated >= :from
                AND timecreated < :to', $span),
            'voices' => $DB->count_records_select(self::T . 'digital', "entityid = :entityid AND kind = 'voice'
                AND status = 'published' AND timecreated >= :from AND timecreated < :to", ['entityid' => $entityid] + $span),
            'booklet' => $DB->count_records_select(self::T . 'booklet', 'entityid = :entityid AND timesent >= :from
                AND timesent < :to', ['entityid' => $entityid] + $span),
            default => 0,
        };
        if ($count > 0) {
            return ['number' => screens::number($count), 'words' => $str('abilitydid' . $key, $count)];
        }
        // Without anything yet, in words, as it stands.
        $by = $key === 'yearsheet' ? closures::return_by($entityid) : null;
        $pick = $key === 'weekpick' ? weekpick::of($library, $now) : null;
        return ['number' => '', 'words' => match ($key) {
            'window' => $str(self::on($library, 'window') ? 'abilitywindowopen' : 'abilitywindowclosed'),
            'firstbook', 'diploma' => $str(self::on($library, $key) ? 'abilitytofamilies' : 'abilityoff' . $key),
            'booklet' => $str('abilitybookletwhen'),
            'yearsheet' => $by ? $str('abilityreturnby', zones::date($by, 'strftimedate', $entityid)) : $str('abilitynoreturnby'),
            'weekpick' => $pick ? $str('abilityweekpick', format_string($pick['title'], true, ['escape' => false]))
                : $str('abilitynoweekpick'),
            // A light off says so first, or nothing done would look like the function failing (Jujol).
            default => $str(in_array($key, self::LIGHTS, true) && !self::on($library, $key) ? 'abilityoff' . $key
                : 'abilitynotyet'),
        }];
    }

    /**
     * Where «Show me» takes the staff for a function.
     *
     * @param \stdClass $library
     * @param string $key
     * @return string
     */
    public static function url(\stdClass $library, string $key): string {
        $at = fn(string $page, ?string $anchor = null) => (new \moodle_url(
            '/local/oksigeniaayvurenda/' . $page,
            ['library' => $library->slug],
            $anchor
        ))->out(false);
        return match ($key) {
            'notices' => $at('rules.php', 'ayv-nr-avisos'),
            'holds' => $at('holds.php'),
            'selfloan' => $at('selfloan.php'),
            'labels' => $at('labels.php'),
            'purchases' => $at('purchases.php'),
            'window' => $at('public.php'),
            'voices' => $at('voices.php'),
            'firstbook', 'diploma', 'booklet' => $at('howitworks.php', 'ayv-hiw-' . $key),
            'yearsheet' => $at('year.php'),
            default => $at('index.php'),
        };
    }

    /**
     * The page's shelves, as one person sees them: only what the centre has, and lights only for whoever manages.
     *
     * @param \stdClass $library
     * @param int $now
     * @return array[] shelves (title, colour, books).
     */
    public static function shelves(\stdClass $library, int $now): array {
        $entityid = (int) $library->entityid;
        $str = fn(string $k, $a = null) => get_string($k, 'local_oksigeniaayvurenda', $a);
        $year = years::around($entityid, $now);
        $manages = access::can('local/oksigeniaayvurenda:manage', $library);
        $action = (new \moodle_url('/local/oksigeniaayvurenda/functions.php', ['library' => $library->slug]))->out(false);
        $there = fn(string $key) => match ($key) {
            'voices' => plans::allows($entityid, 'voices'),
            'diploma' => diploma::drawable(),
            'selfloan' => !bank::is($library),
            default => true,
        };
        $out = [];
        foreach (self::SHELVES as $shelf => [$colour, $books]) {
            $list = [];
            foreach ($books as $key => $spine) {
                if (!$there($key)) {
                    continue;
                }
                $did = self::did($library, $key, $year, $now);
                $lit = in_array($key, self::LIGHTS, true) ? self::on($library, $key) : null;
                $list[] = [
                    'key' => $key,
                    'spine' => $str('abilityspine' . $key),
                    'spinecolour' => $spine,
                    'name' => $str('abilityname' . $key),
                    'solves' => $str('abilitysolves' . $key),
                    'number' => $did['number'],
                    'words' => $did['words'],
                    'nothing' => $did['number'] === '',
                    'light' => $lit !== null && $manages ? ['action' => $action, 'sesskey' => sesskey(), 'fn' => $key,
                        'value' => $lit ? '0' : '1', 'pressed' => $lit, 'label' => $str('abilitylight' . $key)] : null,
                    'always' => $lit === null ? $str('functionsalways') : '',
                    'url' => self::url($library, $key),
                ];
            }
            if ($list) {
                $out[] = ['shelfid' => 'ayv-em-b-' . $shelf, 'title' => $str('functions' . $shelf), 'colour' => $colour,
                    'books' => $list];
            }
        }
        return $out;
    }
}
