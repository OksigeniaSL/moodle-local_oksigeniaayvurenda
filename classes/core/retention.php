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

namespace local_oksigeniaayvurenda\core;

/**
 * Which copies are kept somewhere: those of the last days, the newest of each of the last weeks and the newest of each
 * of the last months. Plain PHP.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class retention {
    /**
     * The days to keep, of those there are.
     *
     * @param string[] $days Dates as YYYYMMDD.
     * @param int $daily How many of the newest days.
     * @param int $weekly How many weeks, keeping the newest day of each.
     * @param int $monthly How many months, keeping the newest day of each.
     * @return string[] The dates to keep, newest first.
     */
    public static function keep(array $days, int $daily, int $weekly, int $monthly): array {
        $days = array_values(array_unique(array_filter($days, fn($d) => preg_match('/^\d{8}$/', (string) $d))));
        rsort($days);
        $kept = array_slice($days, 0, max(0, $daily));
        foreach ([['o-\WW', $weekly], ['Y-m', $monthly]] as [$format, $count]) {
            $seen = [];
            foreach ($days as $day) {
                $when = \DateTimeImmutable::createFromFormat('!Ymd', $day, new \DateTimeZone('UTC'));
                $period = $when ? $when->format($format) : '';
                if (!isset($seen[$period])) {
                    if (count($seen) >= $count) {
                        break;
                    }
                    $seen[$period] = true;
                    $kept[] = $day;
                }
            }
        }
        $kept = array_values(array_unique($kept));
        rsort($kept);
        return $kept;
    }
}
