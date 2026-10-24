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
 * School years of an entity.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

use local_oksigeniaayvurenda\core\school_year;

/**
 * The school year a moment belongs to, for an entity, created the first time something happens in it.
 */
final class years {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * The school year of an entity around a moment.
     *
     * @param int $entityid
     * @param int $time
     * @return \stdClass
     */
    public static function around(int $entityid, int $time): \stdClass {
        global $DB;
        $year = $DB->get_record_select(
            self::T . 'year',
            'entityid = :entityid AND timestart <= :a AND timeend > :b',
            ['entityid' => $entityid, 'a' => $time, 'b' => $time],
            '*',
            IGNORE_MULTIPLE
        );
        if ($year) {
            return $year;
        }
        $start = (string) $DB->get_field(self::T . 'entity', 'yearstart', ['id' => $entityid]);
        $year = (object) (['entityid' => $entityid] +
            school_year::around($time, $start ?: '09-01', \core_date::get_server_timezone_object()));
        $existing = $DB->get_record(self::T . 'year', ['entityid' => $entityid, 'name' => $year->name]);
        if ($existing) {
            return $existing;
        }
        // If the centre changed the day its year starts, the new year begins where the last one ended.
        $last = $DB->get_records_select(
            self::T . 'year',
            'entityid = :entityid AND timeend <= :time',
            ['entityid' => $entityid, 'time' => $time],
            'timeend DESC',
            'id, timeend',
            0,
            1
        );
        $last = $last ? reset($last) : null;
        if ($last && $year->timestart < $last->timeend) {
            $year->timestart = (int) $last->timeend;
        }
        $year->id = $DB->insert_record(self::T . 'year', $year);
        return $year;
    }
}
