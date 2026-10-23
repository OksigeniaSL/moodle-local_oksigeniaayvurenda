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
 * School years.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\core;

/**
 * The school year a moment belongs to, from the day each entity starts its year: 1 September in much of the north,
 * February or March in the south.
 *
 * Part of the core: plain PHP, with no call to Moodle.
 */
final class school_year {
    /**
     * The school year around a moment.
     *
     * @param int $time
     * @param string $start MM-DD
     * @param \DateTimeZone $tz The entity's time zone.
     * @return array{name: string, timestart: int, timeend: int} timeend is the first second of the next year.
     */
    public static function around(int $time, string $start, \DateTimeZone $tz): array {
        if (!preg_match('/^(\d{2})-(\d{2})$/', $start, $parts) || !checkdate((int) $parts[1], (int) $parts[2], 2001)) {
            throw new \InvalidArgumentException('The start of the school year must be MM-DD');
        }
        $month = (int) $parts[1];
        $day = (int) $parts[2];
        $moment = (new \DateTimeImmutable('@' . $time))->setTimezone($tz);
        $year = (int) $moment->format('Y');
        $begin = $moment->setDate($year, $month, $day)->setTime(0, 0);
        if ($begin > $moment) {
            $year--;
            $begin = $begin->setDate($year, $month, $day);
        }
        $end = $begin->setDate($year + 1, $month, $day);
        // A year that starts in the second half of the calendar spans two; one that starts early fits in one.
        $name = $month >= 7 ? $year . '-' . ($year + 1) : (string) $year;
        return ['name' => $name, 'timestart' => $begin->getTimestamp(), 'timeend' => $end->getTimestamp()];
    }
}
