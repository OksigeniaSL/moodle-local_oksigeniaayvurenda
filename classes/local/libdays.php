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
 * The days of the libraries.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * The days libraries celebrate everywhere, that Ayvurenda knows by itself: during the week of each, every library tells
 * it in «These days» and on its front page, with what to do. Data, not a country's calendar: each with its rule, and a
 * centre may leave any of them out.
 */
final class libdays {
    /**
     * The days and their rules: a weekday of a month (nth, 1 the first) or a fixed day (month and day); and the pages its
     * news opens, for the staff and for readers.
     *
     * World Read Aloud Day, created by LitWorld in 2010, is the first Wednesday of February.
     */
    public const DAYS = [
        'readaloud' => ['month' => 2, 'weekday' => 3, 'nth' => 1, 'staffpage' => 'voices.php', 'readerpage' => 'ayvurande.php'],
    ];

    /**
     * When a day falls in a year.
     *
     * @param array $rule
     * @param int $year
     * @param \DateTimeZone $zone
     * @return \DateTimeImmutable Its midnight.
     */
    public static function date(array $rule, int $year, \DateTimeZone $zone): \DateTimeImmutable {
        $first = new \DateTimeImmutable(sprintf('%04d-%02d-%02d 00:00', $year, $rule['month'], $rule['day'] ?? 1), $zone);
        if (!isset($rule['weekday'])) {
            return $first;
        }
        $shift = ((int) $rule['weekday'] - (int) $first->format('N') + 7) % 7;
        return $first->modify('+' . ($shift + 7 * (($rule['nth'] ?? 1) - 1)) . ' days');
    }

    /**
     * The days whose week a moment is in, for a centre: from the first day of its week to the last.
     *
     * @param int $entityid
     * @param int $now
     * @return array[] key, time (its midnight) and the pages.
     */
    public static function this_week(int $entityid, int $now): array {
        $zone = zones::of($entityid);
        $year = (int) (new \DateTimeImmutable('@' . $now))->setTimezone($zone)->format('Y');
        $off = self::off($entityid);
        $found = [];
        foreach (self::DAYS as $key => $rule) {
            if (in_array($key, $off, true)) {
                continue;
            }
            $day = self::date($rule, $year, $zone)->getTimestamp();
            $start = zones::week_start($entityid, $day);
            $end = (new \DateTimeImmutable('@' . $start))->setTimezone($zone)->modify('+7 days')->getTimestamp();
            if ($now >= $start && $now < $end) {
                $found[] = ['key' => $key, 'time' => $day, 'staffpage' => $rule['staffpage'], 'readerpage' => $rule['readerpage']];
            }
        }
        return $found;
    }

    /**
     * The days a centre leaves out.
     *
     * @param int $entityid
     * @return string[]
     */
    public static function off(int $entityid): array {
        global $DB;
        $settings = json_decode((string) $DB->get_field('local_oksigeniaayvurenda_entity', 'settings', ['id' => $entityid]), true);
        return array_values(array_filter((array) ($settings['daysoff'] ?? []), 'is_string'));
    }
}
