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
 * «La orientación con insignia» (P8 of the review of 12 November): the welcome of a new reader, room by room.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * Whoever arrives at a library (a first year at a university or a vocational school, the first of secondary, a new reader
 * of a public library) gets to know it in the visit: they find the hidden book in three rooms of their choice, and with the
 * third they earn the badge «Get to know the library», a Moodle badge of the site in their profile, and a mark on their
 * card. Nothing new is kept of the reader: the rooms come from the books they found («Llévatelo»).
 */
final class orientation {
    /** @var string Prefix of the tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var int Rooms to find a hidden book in. */
    public const ROOMS = 3;

    /** @var string The badge's picture in the art package. */
    public const ART = '/local/oksigeniaayvurendaarte/pix/insignia-orientacion.png';

    /**
     * The orientation of a centre: whether it is on, for which stages (none, all of them but Infantil), and its badge.
     *
     * @param int $entityid
     * @return array on, stages, badgeid.
     */
    public static function settings(int $entityid): array {
        global $DB;
        $entity = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        $own = is_array($entity['orientation'] ?? null) ? $entity['orientation'] : [];
        return ['on' => !empty($own['on']), 'stages' => array_values(array_map('strval', (array) ($own['stages'] ?? []))),
            'badgeid' => (int) ($own['badgeid'] ?? 0)];
    }

    /**
     * Saves the orientation of a centre, keeping its badge.
     *
     * @param int $entityid
     * @param bool $on
     * @param string[] $stages
     */
    public static function save(int $entityid, bool $on, array $stages): void {
        global $DB;
        $entity = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        $entity['orientation'] = ['on' => $on, 'stages' => array_values(array_filter(
            array_map('strval', $stages),
            fn($stage) => $stage !== '' && !cartilla::infant($stage)
        )), 'badgeid' => self::settings($entityid)['badgeid']];
        $DB->set_field(self::T . 'entity', 'settings', json_encode($entity), ['id' => $entityid]);
    }

    /**
     * Whether a reader does the orientation: it is on, the visit is there, and their stage is one of it (not Infantil).
     *
     * @param \stdClass $reader
     * @param int $now
     * @return bool
     */
    public static function applies(\stdClass $reader, int $now): bool {
        $settings = self::settings((int) $reader->entityid);
        // The hidden book is a game of the visit: both have to be on.
        if (!$settings['on'] || !skins::on((int) $reader->entityid, 'visit') || !skins::on((int) $reader->entityid, 'games')) {
            return false;
        }
        $stage = quests::stage_of($reader, $now);
        return !cartilla::infant($stage) && (!$settings['stages'] || in_array($stage, $settings['stages'], true));
    }

    /**
     * The rooms where a reader found the hidden book, by the corner of each, the first first.
     *
     * @param int $readerid
     * @return string[]
     */
    public static function rooms(int $readerid): array {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT corner, MIN(timefound) AS first
               FROM {" . self::T . "find}
              WHERE readerid = :readerid AND corner IS NOT NULL AND corner <> ''
           GROUP BY corner
           ORDER BY MIN(timefound), corner",
            ['readerid' => $readerid]
        );
        return array_map('strval', array_keys($rows));
    }

    /**
     * Whether a reader has done the orientation: a hidden book found in three rooms.
     *
     * @param int $readerid
     * @return bool
     */
    public static function done(int $readerid): bool {
        return count(self::rooms($readerid)) >= self::ROOMS;
    }

    /**
     * A hidden book just found: how the orientation goes, and the badge with the third room.
     *
     * @param \stdClass $reader
     * @param int $now
     * @return array|null done (rooms so far), left, finished (just now), badge (its id, if one was given); null when the
     *     reader does not do it.
     */
    public static function step(\stdClass $reader, int $now): ?array {
        if (!self::applies($reader, $now)) {
            return null;
        }
        $done = count(self::rooms((int) $reader->id));
        $badge = null;
        $finished = false;
        if ($done >= self::ROOMS) {
            [$badge, $finished] = self::award($reader);
        }
        return ['done' => min($done, self::ROOMS), 'left' => max(0, self::ROOMS - $done), 'finished' => $finished,
            'badge' => $badge];
    }

    /**
     * The path of the route as the pages draw it (Jujol's mock-up of 13 November): a stone for each room, lit with the name
     * of the room where the book was found, and the badge waiting at the end, grey until the third.
     *
     * @param string[] $done The corners of the rooms done, the first first.
     * @param string[] $labels Corner => its name.
     * @param bool $meta With the badge at the end.
     * @param string $look flower or sky: the badge's drawing.
     * @param callable $str
     * @return array pathlabel, steps, meta.
     */
    public static function path(array $done, array $labels, bool $meta, string $look, callable $str): array {
        $names = array_map(fn($corner) => (string) ($labels[$corner] ?? $corner), array_slice($done, 0, self::ROOMS));
        $steps = [];
        for ($at = 0; $at < self::ROOMS; $at++) {
            $steps[] = isset($names[$at]) ? ['done' => true, 'room' => $names[$at]]
                : ['done' => false, 'room' => $at === count($names) ? $str('orientationchoose') : $str('orientationother')];
        }
        return [
            'pathlabel' => $str('orientationpathlabel', (object) ['done' => count($names), 'rooms' => implode(', ', $names)]),
            'steps' => $steps,
            'meta' => $meta ? ['img' => self::art($look), 'label' => $str('orientationname')] : null,
        ];
    }

    /**
     * The badge's drawing for a card or a page: the flower's or the sky's.
     *
     * @param string $look
     * @return string|null
     */
    public static function art(string $look): ?string {
        return screens::art('insignia-orientacion-' . ($look === 'flower' ? 'flor' : 'cielo'));
    }

    /**
     * The band of the route in the hall of the visit, for a reader who does it and has not finished: what it is, the path,
     * and the way to the first room of the board they have not done.
     *
     * @param \stdClass $library
     * @param int $userid
     * @param array[] $corners Code => corner, in the board's order.
     * @param string[] $rooms Corner => the scene of its room.
     * @param callable $url
     * @return array|null
     */
    public static function band(\stdClass $library, int $userid, array $corners, array $rooms, callable $url): ?array {
        global $DB;
        $reader = $userid ? $DB->get_record(self::T . 'reader', ['userid' => $userid, 'entityid' => $library->entityid,
            'status' => 'active'], '*', IGNORE_MULTIPLE) : null;
        if (!$reader || !$rooms || !self::applies($reader, time())) {
            return null;
        }
        $done = self::rooms((int) $reader->id);
        if (count($done) >= self::ROOMS) {
            return null;
        }
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        $labels = array_map(fn($corner) => (string) $corner['label'], $corners);
        $next = null;
        foreach (array_keys($corners) as $code) {
            if (isset($rooms[$code]) && !in_array((string) $code, $done, true)) {
                $next = (string) $code;
                break;
            }
        }
        return [
            'title' => $str('orientationtitle'),
            'lead' => trim(($done ? $str('orientationdone' . count($done)) . ' ' : '') . $str('orientationlead')),
            'go' => $next !== null ? ['url' => $url('visit.php', ['room' => $rooms[$next]]), 'label' => $str($done
                ? 'orientationnext' : 'orientationstart', $labels[$next] ?? $next)] : null,
        ] + self::path($done, $labels, true, cards::look_of($reader, time()), $str);
    }

    /**
     * The route on a reader's card, first of their paths: on the way, the badge grey and the rooms; won, its day and the
     * way to their badges.
     *
     * @param \stdClass $reader
     * @param \stdClass $library
     * @param string $look
     * @param int $now
     * @return array|null
     */
    public static function card(\stdClass $reader, \stdClass $library, string $look, int $now): ?array {
        global $DB;
        if ($look === 'none' || !self::applies($reader, $now)) {
            return null;
        }
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        $corners = catalogue::corners($library, true, false);
        $labels = array_combine(array_column($corners, 'code'), array_column($corners, 'label'));
        $done = self::rooms((int) $reader->id);
        $card = ['img' => self::art($look), 'name' => $str('orientationname'), 'won' => count($done) >= self::ROOMS];
        if (!$card['won']) {
            $left = self::ROOMS - count($done);
            return $card + self::path($done, $labels, false, $look, $str) + [
                'hint' => $left === 1 ? $str('orientationcardwayone') : $str('orientationcardway', $left),
                'link' => (new \moodle_url('/local/oksigeniaayvurenda/visit.php', ['library' => $library->slug]))->out(false),
                'linklabel' => $str('orientationcardvisit'),
            ];
        }
        $badgeid = self::settings((int) $reader->entityid)['badgeid'];
        $issued = $badgeid && !empty($reader->userid) ? $DB->get_record('badge_issued', ['badgeid' => $badgeid,
            'userid' => $reader->userid]) : null;
        $when = $issued ? (int) $issued->dateissued : (int) $DB->get_field_sql(
            "SELECT MIN(timefound) FROM {" . self::T . "find} WHERE readerid = :readerid AND corner = :corner",
            ['readerid' => $reader->id, 'corner' => $done[self::ROOMS - 1]]
        );
        $names = array_map(fn($corner) => (string) ($labels[$corner] ?? $corner), array_slice($done, 0, self::ROOMS));
        return $card + [
            'day' => $str('orientationcardwon', zones::date($when, 'strftimedateshort', $reader->entityid)),
            'hint' => $str('orientationcardrooms', self::join($names, $str)),
            'link' => $issued ? (new \moodle_url('/badges/mybadges.php'))->out(false) : '',
            'linklabel' => $issued ? $str('orientationmybadges') : '',
        ];
    }

    /**
     * What the game of a room shows after a book found, for the route: the small path and the rooms left; with the third,
     * the badge falling like a stamp, and the way to it (or to the card, without badges on the site).
     *
     * @param \stdClass $reader
     * @param \stdClass $library
     * @param array $route As step() gives it.
     * @return array
     */
    public static function shown(\stdClass $reader, \stdClass $library, array $route): array {
        global $DB;
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        $look = cards::look_of($reader, time());
        if ($route['left'] > 0) {
            $corners = catalogue::corners($library, true, false);
            $labels = array_combine(array_column($corners, 'code'), array_column($corners, 'label'));
            $path = self::path(self::rooms((int) $reader->id), $labels, false, $look, $str);
            // In the game, the stones still to light have no name: the room is the one the reader is in next.
            $path['steps'] = array_map(fn($step) => $step['done'] ? $step : ['done' => false, 'room' => ''], $path['steps']);
            return ['avance' => $path + ['says' => self::says($route)]];
        }
        $hash = $route['badge'] ? (string) $DB->get_field('badge_issued', 'uniquehash', ['badgeid' => $route['badge'],
            'userid' => $reader->userid]) : '';
        return ['insignia' => [
            'img' => self::art($look),
            'title' => $str('orientationfinished'),
            'text' => $hash !== '' ? $str('orientationbadgeprofile') : $str('orientationnobadges'),
            'link' => $hash !== '' ? (new \moodle_url('/badges/badge.php', ['hash' => $hash]))->out(false)
                : (new \moodle_url('/local/oksigeniaayvurenda/card.php', ['library' => $library->slug]))->out(false),
            'linklabel' => $hash !== '' ? $str('orientationbadgeview') : $str('orientationcardview'),
        ]];
    }

    /**
     * Names joined as a sentence says them: «A, B y C».
     *
     * @param string[] $names
     * @param callable $str
     * @return string
     */
    private static function join(array $names, callable $str): string {
        $last = array_pop($names);
        return $names ? $str('orientationjoin', (object) ['some' => implode(', ', $names), 'last' => $last]) : (string) $last;
    }

    /**
     * What the visit says after a room of the orientation: the rooms left, or the badge.
     *
     * @param array $route As step() gives it.
     * @return string
     */
    public static function says(array $route): string {
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        if ($route['left'] > 0) {
            return $route['left'] === 1 ? $str('orientationleftone') : $str('orientationleft', $route['left']);
        }
        if ($route['finished']) {
            return $str($route['badge'] ? 'orientationbadgegiven' : 'orientationfinished');
        }
        return $str('orientationfinished');
    }

    /**
     * The badge of a reader who finished: given once, if the site has badges and the reader an account.
     *
     * @param \stdClass $reader
     * @return array The badge's id (or null) and whether it was given just now.
     */
    public static function award(\stdClass $reader): array {
        global $DB;
        $badgeid = !empty($reader->userid) ? self::badge((int) $reader->entityid) : null;
        if (!$badgeid) {
            return [null, false];
        }
        if ($DB->record_exists('badge_issued', ['badgeid' => $badgeid, 'userid' => $reader->userid])) {
            return [$badgeid, false];
        }
        (new \core_badges\badge($badgeid))->issue((int) $reader->userid, defined('PHPUNIT_TEST') && PHPUNIT_TEST);
        return [$badgeid, true];
    }

    /**
     * The centre's badge «Get to know the library»: a badge of the site, made the first time it is given, with the picture
     * of the art package; none without badges on the site or without the picture.
     *
     * @param int $entityid
     * @param string|null $image Another picture than the art package's (tests).
     * @return int|null
     */
    public static function badge(int $entityid, ?string $image = null): ?int {
        global $CFG, $DB;
        if (empty($CFG->enablebadges)) {
            return null;
        }
        $settings = self::settings($entityid);
        if ($settings['badgeid'] && $DB->record_exists('badge', ['id' => $settings['badgeid']])) {
            return $settings['badgeid'];
        }
        $image = $image ?? $CFG->dirroot . self::ART;
        if (!is_readable($image)) {
            return null;
        }
        require_once($CFG->libdir . '/badgeslib.php');
        $name = format_string(
            (string) $DB->get_field(self::T . 'entity', 'name', ['id' => $entityid]),
            true,
            ['escape' => false]
        );
        $admin = get_admin();
        $now = time();
        $id = (int) $DB->insert_record('badge', (object) [
            'name' => \core_text::substr(get_string('orientationbadge', 'local_oksigeniaayvurenda', $name), 0, 255),
            'description' => get_string('orientationbadgedesc', 'local_oksigeniaayvurenda', $name),
            'timecreated' => $now, 'timemodified' => $now, 'usercreated' => $admin->id, 'usermodified' => $admin->id,
            'issuername' => \core_text::substr($name, 0, 255), 'issuerurl' => $CFG->wwwroot,
            'issuercontact' => (string) ($CFG->badges_defaultissuercontact ?? ''),
            'expiredate' => null, 'expireperiod' => null, 'type' => BADGE_TYPE_SITE, 'courseid' => null,
            'messagesubject' => get_string('orientationbadgesubject', 'local_oksigeniaayvurenda'),
            'message' => get_string('orientationbadgemessage', 'local_oksigeniaayvurenda', $name),
            'attachment' => 1, 'notification' => 0, 'status' => BADGE_STATUS_ACTIVE, 'version' => '',
            'language' => current_language(), 'imagecaption' => '',
        ]);
        $badge = new \core_badges\badge($id);
        $temp = make_request_directory() . '/insignia.png';
        copy($image, $temp);
        badges_process_badge_image($badge, $temp);
        // Given by the library, as a manager would by hand: Moodle shows it as such, and nobody else meets it by chance.
        $manager = (int) $DB->get_field('role', 'id', ['shortname' => 'manager']);
        \award_criteria::build(['badgeid' => $id, 'criteriatype' => BADGE_CRITERIA_TYPE_OVERALL])
            ->save(['agg' => BADGE_CRITERIA_AGGREGATION_ALL]);
        if ($manager) {
            \award_criteria::build(['badgeid' => $id, 'criteriatype' => BADGE_CRITERIA_TYPE_MANUAL])
                ->save(['role_' . $manager => $manager, 'description' => '']);
        }
        $entity = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        $entity['orientation'] = ['badgeid' => $id] + self::settings($entityid);
        $DB->set_field(self::T . 'entity', 'settings', json_encode($entity), ['id' => $entityid]);
        return $id;
    }
}
