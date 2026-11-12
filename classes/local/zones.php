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
 * The time zone of a centre: when its day ends.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * Each centre's day ends at its own midnight, wherever the site runs: a book due today in Bogotá is not late at 18:30 there
 * because it is already tomorrow in Madrid. Due dates, what is late, reservations, the school year and the dates shown for
 * them all go by the centre's zone, for whoever looks at them, so that everyone sees the same day on the same loan. Moments
 * (when a copy was made, who did what) stay in the zone of whoever reads them. A centre that chose none has the site's.
 */
final class zones {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * The name of a centre's zone.
     *
     * @param \stdClass|int $where A library, a reader or anything with entityid; or the id of a centre.
     * @return string
     */
    public static function name($where): string {
        global $DB;
        $entityid = is_object($where) ? (int) $where->entityid : (int) $where;
        if (!$entityid) {
            return \core_date::get_server_timezone();
        }
        $cache = \cache::make('local_oksigeniaayvurenda', 'access');
        $zone = $cache->get('zone_' . $entityid);
        if ($zone === false) {
            $settings = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
            $zone = self::known((string) ($settings['timezone'] ?? '')) ? (string) $settings['timezone'] : '';
            $cache->set('zone_' . $entityid, $zone);
        }
        return $zone !== '' ? $zone : \core_date::get_server_timezone();
    }

    /**
     * A centre's zone, to work with.
     *
     * @param \stdClass|int $where
     * @return \DateTimeZone
     */
    public static function of($where): \DateTimeZone {
        return new \DateTimeZone(self::name($where));
    }

    /**
     * Whether a zone is one that can be chosen.
     *
     * @param string $zone
     * @return bool
     */
    public static function known(string $zone): bool {
        return $zone !== '' && in_array($zone, \DateTimeZone::listIdentifiers(), true);
    }

    /**
     * The moment a centre's day began.
     *
     * @param \stdClass|int $where
     * @param int $now
     * @return int
     */
    public static function today($where, int $now): int {
        return (new \DateTimeImmutable('@' . $now))->setTimezone(self::of($where))->setTime(0, 0)->getTimestamp();
    }

    /**
     * The centres whose morning it is, for what the night leaves to tell: from 8 to 20 in the centre's zone, once a day
     * (a cron that missed eight o'clock tells at nine); the notices of late books, only on the days the library opens (not
     * on its days of rest, nor on a day it is closed: Christmas, a strike). Practice libraries tell nobody.
     *
     * @param int $now
     * @param bool $weekdays Only on the days the centre's libraries open (not Saturday or Sunday, unless it says others).
     * @return int[]
     */
    public static function mornings(int $now, bool $weekdays = true): array {
        global $DB;
        $centres = [];
        foreach ($DB->get_fieldset_select('local_oksigeniaayvurenda_entity', 'id', '1 = 1') as $id) {
            $id = (int) $id;
            $local = (new \DateTimeImmutable('@' . $now))->setTimezone(self::of($id));
            $hour = (int) $local->format('G');
            if (
                ($weekdays && in_array((int) $local->format('N'), self::rest_days($id), true)) || $hour < 8 || $hour >= 20
                    || practice::is($id) || ($weekdays && isset(closures::days($id, $now)[$local->format('Y-m-d')]))
            ) {
                continue;
            }
            if ((string) get_config('local_oksigeniaayvurenda', self::morning_key($id, $weekdays)) !== $local->format('Ymd')) {
                $centres[] = $id;
            }
        }
        return $centres;
    }

    /**
     * Notes that these centres were told this morning.
     *
     * @param int[] $centres
     * @param int $now
     * @param bool $weekdays Those of the weekdays.
     */
    public static function mornings_done(array $centres, int $now, bool $weekdays = true): void {
        foreach ($centres as $id) {
            $local = (new \DateTimeImmutable('@' . $now))->setTimezone(self::of((int) $id));
            set_config(self::morning_key((int) $id, $weekdays), $local->format('Ymd'), 'local_oksigeniaayvurenda');
        }
    }

    /**
     * Where a centre's last morning is noted.
     *
     * @param int $id
     * @param bool $weekdays
     * @return string
     */
    private static function morning_key(int $id, bool $weekdays): string {
        return ($weekdays ? 'morning_' : 'everymorning_') . $id;
    }

    /** @var int[] The days a library closes every week, unless its centre says: Saturday and Sunday (ISO 6 and 7). */
    public const REST = [6, 7];

    /**
     * The days a centre's libraries close every week, by their ISO number (1 Monday, 7 Sunday): Saturday and Sunday,
     * unless the centre says others (Friday and Saturday, only Sunday…).
     *
     * @param \stdClass|int $where
     * @return int[]
     */
    public static function rest_days($where): array {
        $settings = self::settings($where);
        if (!isset($settings['restdays']) || !is_array($settings['restdays'])) {
            return self::REST;
        }
        return array_values(array_unique(array_filter(
            array_map('intval', $settings['restdays']),
            fn($day) => $day >= 1 && $day <= 7
        )));
    }

    /**
     * The day a centre's week starts, by its ISO number: the centre's own, or what Moodle says for the site or its
     * language (Sunday in American English, Monday in Spain or Latin America).
     *
     * @param \stdClass|int $where
     * @return int 1 Monday to 7 Sunday.
     */
    public static function first_weekday($where): int {
        $own = (int) (self::settings($where)['weekstart'] ?? 0);
        if ($own >= 1 && $own <= 7) {
            return $own;
        }
        $moodle = (int) \core_calendar\type_factory::get_calendar_instance()->get_starting_weekday();
        return $moodle === 0 ? 7 : $moodle;
    }

    /**
     * The moment this week began where a centre is: midnight of its first day.
     *
     * @param \stdClass|int $where
     * @param int $now
     * @return int
     */
    public static function week_start($where, int $now): int {
        $today = (new \DateTimeImmutable('@' . $now))->setTimezone(self::of($where))->setTime(0, 0);
        $back = ((int) $today->format('N') - self::first_weekday($where) + 7) % 7;
        return $today->modify('-' . $back . ' days')->getTimestamp();
    }

    /**
     * The settings of a centre.
     *
     * @param \stdClass|int $where A centre's id, or a library or a centre with entityid or id.
     * @return array
     */
    private static function settings($where): array {
        global $DB;
        $entityid = is_object($where) ? (int) ($where->entityid ?? $where->id) : (int) $where;
        return json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
    }

    /**
     * A date in a centre's zone.
     *
     * @param int $time
     * @param string $format A format of the language pack (strftimedate…), or a format of its own (%A, %Y-%m-%d).
     * @param \stdClass|int $where
     * @return string
     */
    public static function date(int $time, string $format, $where): string {
        // The language pack's formats lose the zero of the day («3 de noviembre»); a format of its own keeps it, or
        // «2026-09-01» would be «2026-09-1», which no date field reads.
        $pack = strpos($format, 'strftime') === 0;
        return userdate($time, $pack ? get_string($format, 'langconfig') : $format, self::name($where), $pack);
    }

    /**
     * What to choose from: the site's first, with what it is, and every zone by its name.
     *
     * @param \stdClass|int $where
     * @return array[] value, label and selected.
     */
    public static function choices($where): array {
        global $DB;
        $entityid = is_object($where) ? (int) $where->entityid : (int) $where;
        $settings = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        $mine = (string) ($settings['timezone'] ?? '');
        $choices = [['value' => '', 'selected' => !self::known($mine), 'label' => get_string(
            'zonesite',
            'local_oksigeniaayvurenda',
            \core_date::get_localised_timezone(\core_date::get_server_timezone())
        )]];
        foreach (\core_date::get_list_of_timezones(null, false) as $zone => $label) {
            if (self::known((string) $zone)) {
                $choices[] = ['value' => (string) $zone, 'label' => $label, 'selected' => $zone === $mine];
            }
        }
        return $choices;
    }

    /**
     * Forgets what was remembered of a centre's zone.
     *
     * @param int $entityid
     */
    public static function forget(int $entityid): void {
        \cache::make('local_oksigeniaayvurenda', 'access')->delete('zone_' . $entityid);
    }
}
