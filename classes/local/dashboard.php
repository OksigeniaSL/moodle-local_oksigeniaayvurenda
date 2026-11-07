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
 * What the library shows on someone's dashboard, for the block of the personal area.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * For each centre where someone reads, keeps a library or may enter one: something to look at every day. With nothing
 * lent, what the memory has for them today (or the book of the week, or what just came); with books at home, each with
 * its cover and when it goes back, in words; then, by who is looking, the class challenge, their own goal, the readings
 * of their courses or what waits at the desk. Ready for the block's template, in the words of each centre's skin.
 * Nothing of anyone else: the counts of the desk are counts, the readings are copies against students.
 */
final class dashboard {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var int The readings of a teacher's courses the block shows; the rest, behind a link. */
    private const READINGS = 2;

    /** @var int The covers of what just came. */
    private const NEWS = 3;

    /** @var string[] The colours of a cover the library draws, by the card's colour. */
    private const DRAWN = ['dc-green', 'dc-night', 'dc-paper', 'dc-ink', 'dc-stone', 'dc-coral'];

    /**
     * What the block shows someone, or nothing for whoever has nothing to do with any library.
     *
     * @param int $userid
     * @param int|null $now
     * @return array|null centres (each with its skin, sections and links) and many.
     */
    public static function for_user(int $userid, ?int $now = null): ?array {
        global $DB;
        $now = $now ?? time();
        if (!$userid) {
            return null;
        }
        $centres = [];
        foreach ($DB->get_records(self::T . 'reader', ['userid' => $userid, 'status' => 'active'], 'id') as $reader) {
            $entityid = (int) $reader->entityid;
            if (isset($centres[$entityid]) || (access::closed($entityid) && !access::is_staff($entityid, $userid))) {
                continue;
            }
            $library = mail::main_library($entityid);
            if ($library && !bank::is($library)) {
                $centres[$entityid] = self::centre($library, $reader, $userid, $now);
            }
        }
        // Whoever may enter a library without a card of its centre (staff, teachers, the site's admins): its desk, if
        // they lend there, and the way in.
        foreach (catalogue::libraries() as $library) {
            $entityid = (int) $library->entityid;
            if (
                isset($centres[$entityid]) || bank::is($library)
                    || !access::can('local/oksigeniaayvurenda:view', $library, $userid)
                    || ($library->parentid && !access::keeps($library, $userid))
            ) {
                continue;
            }
            $centres[$entityid] = self::centre($library, null, $userid, $now);
        }
        $centres = array_values(array_filter($centres));
        if (!$centres) {
            return null;
        }
        foreach ($centres as &$centre) {
            $centre['showname'] = count($centres) > 1;
        }
        return ['centres' => $centres];
    }

    /**
     * What a centre shows someone.
     *
     * @param \stdClass $library The centre's main library.
     * @param \stdClass|null $reader Their reader's card there, if they have one.
     * @param int $userid
     * @param int $now
     * @return array|null
     */
    private static function centre(\stdClass $library, ?\stdClass $reader, int $userid, int $now): ?array {
        $entityid = (int) $library->entityid;
        $look = $reader ? cards::look_of($reader, $now) : 'none';
        $skin = self::skin($entityid, $look, $reader !== null);
        $say = fn(string $key, $a = null) => skins::say($entityid, $key, $a);
        $url = fn(string $script, \stdClass $where, array $params = []) => (new \moodle_url(
            '/local/oksigeniaayvurenda/' . $script,
            ['library' => $where->slug] + $params
        ))->out(false);
        $desks = self::desks($entityid, $userid, $now, $say, $url);
        $sections = [];
        if ($desks) {
            $sections[] = ['desk' => ['heading' => $say('dashdesk'), 'libraries' => $desks, 'many' => count($desks) > 1]];
        }
        if ($reader) {
            $books = self::books($library, $reader, $userid, $now, $skin, $say, $url);
            if ($books) {
                $sections[] = ['books' => $books];
            } else {
                $tell = self::tell($library, $reader, $now, $skin, $say, $url);
                if ($tell) {
                    // Whoever keeps the desk has it to look at: the idea of the day only where there is room.
                    $sections[] = ['tell' => $tell, 'extra' => (bool) $desks];
                }
            }
            $readings = self::readings($library, $userid, $say, $url);
            if ($readings) {
                $sections[] = ['readings' => $readings];
            } else if (!$desks) {
                $own = self::challenge($library, $reader, $now, $skin, $say) ?? self::goal($reader, $userid, $now, $look, $say);
                if ($own) {
                    $sections[] = $own;
                }
            }
        } else if (!$desks) {
            // Nothing of their own here and no desk: what came last, so that the way in is worth taking.
            $tell = self::news($library, $skin, $say, $url);
            if ($tell) {
                $sections[] = ['tell' => $tell, 'extra' => false];
            }
        }
        foreach ($sections as $i => &$section) {
            $section['first'] = $i === 0;
            $section['extra'] = $section['extra'] ?? false;
        }
        return [
            'name' => format_string($library->name, true, ['escape' => false]),
            'skin' => $skin,
            'sections' => $sections,
            'home' => $url('index.php', $library),
            'golabel' => $say('dashgo'),
            'gofirst' => !$desks,
            'card' => $reader ? $url('card.php', $library) : '',
            'cardlabel' => $say('mycard'),
            'leaf' => $skin === 'school',
            'star' => $skin === 'secondary',
        ];
    }

    /**
     * The look of a centre's block for someone: the school's, the sky's of a secondary school, the plain one of grown
     * readers, or the company's, without characters nor play.
     *
     * @param int $entityid
     * @param string $look The reader's card: flower, sky, skysober or none.
     * @param bool $reads Whether they have a card there.
     * @return string school, secondary, university or company.
     */
    private static function skin(int $entityid, string $look, bool $reads): string {
        $skin = skins::of($entityid);
        if ($skin === 'company') {
            return 'company';
        }
        if (!$reads) {
            return in_array($skin, ['school', 'secondary', 'university'], true) ? $skin : 'university';
        }
        return match ($look) {
            'flower' => 'school',
            'sky' => 'secondary',
            default => 'university',
        };
    }

    /**
     * A character's round portrait, still: the centre's own if it has one, else Gaudí's of the flower or of the sky
     * from the art package. None in a company, and none for a character the centre turned off.
     *
     * @param int $entityid
     * @param string $role memory or herald.
     * @param string $skin
     * @return string An address, or nothing.
     */
    private static function face(int $entityid, string $role, string $skin): string {
        $character = characters::of($entityid)[$role] ?? null;
        if ($skin === 'company' || !$character || !$character['enabled']) {
            return '';
        }
        if ($character['own']) {
            return (string) $character['portrait'];
        }
        $who = $role === 'herald' ? 'mainumby' : 'memoria';
        return (string) (screens::art('bloque/' . $who . ($skin === 'school' ? '' : '-cielo')) ?? $character['portrait'] ?? '');
    }

    /**
     * A cover for the template: the picture, or the library's drawing with its colour and title.
     *
     * @param int $recordid
     * @param string $title
     * @param int $colour
     * @param array $covers Record id => address, from covers::urls().
     * @return array img, drawn and title.
     */
    private static function cover(int $recordid, string $title, int $colour, array $covers): array {
        return ['img' => (string) ($covers[$recordid] ?? ''), 'drawn' => self::DRAWN[abs($colour) % count(self::DRAWN)],
            'title' => $title];
    }

    /**
     * A day in words, as people say it: today, tomorrow, yesterday, the day of the week within six days, and further
     * away the day of the week with its number.
     *
     * @param int $time
     * @param \stdClass|int $where A library or a centre, for its zone.
     * @param int $now
     * @return string
     */
    public static function when(int $time, $where, int $now): string {
        $zone = zones::of($where);
        $day = fn(int $t) => (new \DateTimeImmutable('@' . $t))->setTimezone($zone)->setTime(0, 0);
        $diff = (int) $day($now)->diff($day($time))->format('%r%a');
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        if ($diff === 0) {
            return $str('whentoday');
        }
        if ($diff === 1) {
            return $str('whentomorrow');
        }
        if ($diff === -1) {
            return $str('whenyesterday');
        }
        $weekday = zones::date($time, '%A', $where);
        if (abs($diff) <= 6) {
            return $str('whenweekday', $weekday);
        }
        return $str('whendate', $weekday . ' ' . (int) zones::date($time, '%d', $where));
    }

    /**
     * The reader's books at home and what is set aside for them, or nothing.
     *
     * @param \stdClass $library
     * @param \stdClass $reader
     * @param int $userid
     * @param int $now
     * @param string $skin
     * @param \Closure $say
     * @param \Closure $url
     * @return array|null heading, count, countlabel, items, note, textbooks, notice.
     */
    private static function books(
        \stdClass $library,
        \stdClass $reader,
        int $userid,
        int $now,
        string $skin,
        \Closure $say,
        \Closure $url
    ): ?array {
        global $DB;
        $today = zones::today($library, $now);
        $rows = $DB->get_records_sql(
            "SELECT l.id, l.timedue, l.libraryid, r.id AS recordid, r.title
               FROM {" . self::T . "loan} l
               JOIN {" . self::T . "item} i ON i.id = l.itemid
               JOIN {" . self::T . "record} r ON r.id = i.recordid
              WHERE l.readerid = :readerid AND l.timereturned IS NULL
           ORDER BY l.timedue, l.id",
            ['readerid' => $reader->id]
        );
        $holds = holds::of_reader((int) $reader->id, $now);
        $covers = covers::urls(array_merge(
            array_map(fn($r) => (int) $r->recordid, $rows),
            array_map(fn($h) => (int) $h['recordid'], $holds)
        ));
        $items = [];
        $textbooks = 0;
        $late = false;
        foreach ($rows as $row) {
            $where = (int) $row->libraryid === (int) $library->id ? $library
                : $DB->get_record(self::T . 'library', ['id' => $row->libraryid]);
            if (!$where) {
                continue;
            }
            $islate = (int) $row->timedue < $today;
            if (bank::is($where) && !$islate) {
                // The textbooks lent for the year are no list to read every day: one line says how many.
                $textbooks++;
                continue;
            }
            $late = $late || $islate;
            $title = format_string($row->title, true, ['escape' => false]);
            $until = $islate ? null : renewals::until($where, (int) $reader->id, (int) $row->id, $userid, $now);
            $day = self::when((int) $row->timedue, $library, $now);
            $items[] = ['cover' => self::cover((int) $row->recordid, $title, (int) $row->recordid, $covers), 'title' => $title,
                'url' => $url('record.php', $where, ['id' => $row->recordid]),
                'due' => $islate ? '' : $say('dashdue', $day), 'late' => $islate ? $say('dashlate', $day) : '',
                'ready' => '', 'waiting' => '',
                'datetitle' => zones::date((int) $row->timedue, 'strftimedaydate', $library),
                'renew' => $until ? ['loan' => (int) $row->id, 'label' => $say('dashrenew'), 'title' => get_string(
                    'athomerenew',
                    'local_oksigeniaayvurenda',
                    zones::date($until, 'strftimedateshort', $library)
                )] : null];
        }
        $loans = count($items);
        foreach ($holds as $hold) {
            $title = format_string($hold['title'], true, ['escape' => false]);
            $items[] = ['cover' => self::cover((int) $hold['recordid'], $title, (int) $hold['recordid'], $covers),
                'title' => $title, 'url' => $url('record.php', $library, ['id' => $hold['recordid']]), 'due' => '', 'late' => '',
                'ready' => $hold['ready'] ? $say('dashready', self::when((int) $hold['expires'], $library, $now)) : '',
                'waiting' => $hold['ready'] ? '' : ($hold['ahead'] ? $say('dashwaitingahead', $hold['ahead'])
                    : $say('dashwaiting')), 'datetitle' => '', 'renew' => null];
        }
        $notice = sanctions::for_reader((int) $reader->entityid, (int) $reader->id, $now);
        if (!$items && !$textbooks && $notice === '') {
            return null;
        }
        return [
            'heading' => $say('dashbooks'),
            'count' => $loans,
            'countlabel' => $say('dashbookscount', $loans),
            'items' => $items,
            'hasitems' => (bool) $items,
            'note' => $late && $skin === 'school' ? $say('dashlatenote') : '',
            'textbooks' => $textbooks ? get_string(
                $textbooks === 1 ? 'minetextbooksone' : 'minetextbooks',
                'local_oksigeniaayvurenda',
                $textbooks
            ) : '',
            'notice' => $notice,
            'renewaction' => (new \moodle_url('/local/oksigeniaayvurenda/renew.php'))->out(false),
            'sesskey' => sesskey(),
            'back' => (new \moodle_url('/my/'))->out(false),
        ];
    }

    /**
     * What the library tells a reader with nothing at home: the memory's idea of today, or the book of the week, or
     * what just came.
     *
     * @param \stdClass $library
     * @param \stdClass $reader
     * @param int $now
     * @param string $skin
     * @param \Closure $say
     * @param \Closure $url
     * @return array|null
     */
    private static function tell(
        \stdClass $library,
        \stdClass $reader,
        int $now,
        string $skin,
        \Closure $say,
        \Closure $url
    ): ?array {
        $remembers = memory::remembers($library, $reader, $now);
        if ($remembers) {
            $said = $remembers['say'];
            $book = (int) $remembers['recordid'];
            return [
                'face' => self::face((int) $library->entityid, 'memory', $skin),
                'heading' => $say('dashidea'),
                'line' => (string) $said['text'],
                'pick' => $book ? self::pick($library, $book, $skin, $say, $url) : null,
                'search' => $book ? null : ['url' => $url('search.php', $library, ['q' => $remembers['query']]),
                    'label' => get_string('memorysearchagain', 'local_oksigeniaayvurenda')],
                'shelf' => null,
                'sign' => $skin === 'university' ? $say('dashsign', (string) ($said['name'] ?? '')) : '',
            ];
        }
        $week = screens::week_book($library, $now);
        if ($week && !empty($week['url'])) {
            $recordid = (int) (new \moodle_url($week['url']))->get_param('id');
            return [
                'face' => in_array($skin, ['school', 'secondary'], true) ? self::face((int) $library->entityid, 'herald', $skin)
                    : '',
                'heading' => $say('dashweek'),
                'line' => (string) $week['text'],
                'pick' => $recordid ? self::pick($library, $recordid, $skin, $say, $url) : null,
                'search' => null,
                'shelf' => null,
                'sign' => $skin === 'university' ? $say('dashsignherald', (string) ($week['name'] ?? '')) : '',
            ];
        }
        return self::news($library, $skin, $say, $url);
    }

    /**
     * What came last to the library, as three covers on a shelf.
     *
     * @param \stdClass $library
     * @param string $skin
     * @param \Closure $say
     * @param \Closure $url
     * @return array|null
     */
    private static function news(\stdClass $library, string $skin, \Closure $say, \Closure $url): ?array {
        $cards = catalogue::recent($library, self::NEWS);
        if (!$cards) {
            return null;
        }
        $covers = covers::urls(array_map(fn($c) => (int) $c['id'], $cards));
        $herald = characters::say((int) $library->entityid, 'herald', 'dashnewsline', [], 'saluda');
        return [
            'face' => in_array($skin, ['school', 'secondary'], true) ? self::face((int) $library->entityid, 'herald', $skin) : '',
            'heading' => $say('dashnews'),
            'line' => in_array($skin, ['school', 'secondary'], true) && $herald ? (string) $herald['text'] : '',
            'pick' => null,
            'search' => null,
            'shelf' => [
                'books' => array_map(fn($c) => [
                    'url' => $url('record.php', $library, ['id' => $c['id']]),
                    'title' => format_string($c['title'], true, ['escape' => false]),
                    'cover' => self::cover(
                        (int) $c['id'],
                        format_string($c['title'], true, ['escape' => false]),
                        (int) $c['colour'],
                        $covers
                    ),
                ], $cards),
                'more' => $url('index.php', $library),
                'morelabel' => $say('dashseeall'),
            ],
            'sign' => '',
        ];
    }

    /**
     * One book on its plate: its cover, title, author and whether it is free.
     *
     * @param \stdClass $library
     * @param int $recordid
     * @param string $skin
     * @param \Closure $say
     * @param \Closure $url
     * @return array|null
     */
    private static function pick(\stdClass $library, int $recordid, string $skin, \Closure $say, \Closure $url): ?array {
        $cards = catalogue::cards_of([$recordid], $library);
        $card = $cards ? reset($cards) : null;
        if (!$card) {
            return null;
        }
        $title = format_string($card['title'], true, ['escape' => false]);
        $free = $card['state'] === 'free';
        $where = $free && !empty($card['location']) ? (string) $card['location'] : '';
        return [
            'cover' => self::cover($recordid, $title, (int) $card['colour'], covers::urls([$recordid])),
            'title' => $title,
            'author' => (string) $card['authors'],
            'url' => $url('record.php', $library, ['id' => $recordid]),
            'state' => $free ? ($where !== '' && $skin === 'university' ? $say('dashfreewhere', $where) : $say('dashfree'))
                : $say('dashout'),
            'ok' => $free,
            'label' => get_string('memorygo', 'local_oksigeniaayvurenda'),
        ];
    }

    /**
     * The challenge of the reader's class this term, with its marks.
     *
     * @param \stdClass $library
     * @param \stdClass $reader
     * @param int $now
     * @param string $skin
     * @param \Closure $say
     * @return array|null A section.
     */
    private static function challenge(\stdClass $library, \stdClass $reader, int $now, string $skin, \Closure $say): ?array {
        global $DB;
        if (!in_array($skin, ['school', 'secondary'], true) || skins::no_classes((int) $library->entityid)) {
            return null;
        }
        $year = years::around((int) $library->entityid, $now);
        $group = (string) $DB->get_field(
            self::T . 'member',
            'groupname',
            ['readerid' => $reader->id, 'yearid' => $year->id],
            IGNORE_MULTIPLE
        );
        $challenge = $group !== '' ? challenges::of((int) $library->entityid, $group, $now) : null;
        if (!$challenge || !$challenge->target) {
            return null;
        }
        $view = challenges::view($challenge, (int) $library->entityid);
        return ['challenge' => [
            'heading' => $say('dashchallenge'),
            'count' => $say('dashof', (object) ['done' => $challenge->done, 'target' => $challenge->target]),
            'marks' => array_map(fn($m) => ['on' => (bool) $m['lit']], $view['marks']),
            'stars' => $skin === 'secondary',
            'text' => $view['text'],
            'meton' => $view['meton'] ?? '',
        ]];
    }

    /**
     * The grown reader's own goal for the year, which only they see.
     *
     * @param \stdClass $reader
     * @param int $userid
     * @param int $now
     * @param string $look
     * @param \Closure $say
     * @return array|null A section.
     */
    private static function goal(\stdClass $reader, int $userid, int $now, string $look, \Closure $say): ?array {
        if ($look === 'flower') {
            return null;
        }
        $year = years::around((int) $reader->entityid, $now);
        $goal = goals::of($userid, (int) $year->id);
        if (!$goal) {
            return null;
        }
        $done = min(goals::done((int) $reader->id, (int) $year->id), $goal);
        // Twelve steps at most: a bar, not a count.
        $steps = min(12, $goal);
        $lit = (int) floor($done * $steps / $goal);
        return ['goal' => [
            'heading' => $say('goaltitlelay'),
            'count' => $say('dashof', (object) ['done' => $done, 'target' => $goal]),
            'bar' => array_map(fn($i) => ['on' => $i < $lit], range(0, $steps - 1)),
            'steps' => $steps,
            'text' => $say('dashgoalhint'),
        ]];
    }

    /**
     * The readings of the courses someone teaches in this centre: the required ones that are short first, each with its
     * copies against the course's students.
     *
     * @param \stdClass $library
     * @param int $userid
     * @param \Closure $say
     * @param \Closure $url
     * @return array|null heading, rows, more and morelabel.
     */
    private static function readings(\stdClass $library, int $userid, \Closure $say, \Closure $url): ?array {
        global $DB;
        $libraries = array_keys($DB->get_records(self::T . 'library', ['entityid' => $library->entityid], '', 'id'));
        if (!$libraries) {
            return null;
        }
        [$in, $params] = $DB->get_in_or_equal($libraries, SQL_PARAMS_NAMED);
        $lists = $DB->get_records_select(self::T . 'readinglist', "libraryid $in", $params, 'id', '*', 0, 200);
        $rows = [];
        $courses = [];
        foreach ($lists as $list) {
            $context = \context_course::instance((int) $list->courseid, IGNORE_MISSING);
            if (!$context || !has_capability('moodle/course:update', $context, $userid)) {
                continue;
            }
            $course = get_course((int) $list->courseid);
            $courses[] = (int) $course->id;
            $students = count_enrolled_users($context, 'moodle/course:isincompletionreports');
            $items = $DB->get_records(self::T . 'readingitem', ['listid' => $list->id], 'required DESC, sortorder, id', '*', 0, 20);
            foreach ($items as $item) {
                $copies = $DB->count_records_select(self::T . 'item', "recordid = :recordid AND libraryid = :libraryid
                    AND status <> 'lost' AND status <> 'withdrawn'", ['recordid' => $item->recordid,
                    'libraryid' => $list->libraryid]);
                $waiting = $DB->count_records(self::T . 'hold', ['recordid' => $item->recordid, 'libraryid' => $list->libraryid,
                    'status' => 'waiting']);
                $short = $item->required && $copies < $students;
                $rows[] = ['recordid' => (int) $item->recordid, 'course' => format_string(
                    $course->shortname,
                    true,
                    ['context' => $context]
                ), 'short' => $short, 'need' => $short ? $students - $copies : 0,
                    'copies' => $copies, 'students' => $students, 'waiting' => $waiting,
                    'where' => (int) $list->libraryid];
            }
        }
        if (!$rows) {
            return null;
        }
        // The short ones first, the most missing first.
        usort($rows, fn($a, $b) => [$b['short'], $b['need']] <=> [$a['short'], $a['need']]);
        $shown = array_slice($rows, 0, self::READINGS);
        $cards = catalogue::cards_of(array_column($shown, 'recordid'), $library);
        $covers = covers::urls(array_column($shown, 'recordid'));
        $out = [];
        foreach ($shown as $row) {
            $card = $cards[$row['recordid']] ?? null;
            if (!$card) {
                continue;
            }
            $title = format_string($card['title'], true, ['escape' => false]);
            $a = (object) ['copies' => $row['copies'], 'students' => $row['students'], 'missing' => $row['need'],
                'waiting' => $row['waiting']];
            $out[] = ['cover' => self::cover($row['recordid'], $title, (int) $card['colour'], $covers), 'title' => $title,
                'url' => $url('record.php', $library, ['id' => $row['recordid']]), 'course' => $row['course'],
                'ok' => !$row['short'], 'state' => $row['short'] ? $say($row['waiting'] ? 'dashreadshortwaiting'
                    : 'dashreadshort', $a) : $say('dashreadok', $a)];
        }
        $courses = array_values(array_unique($courses));
        return $out ? ['heading' => $say('dashreadings'), 'rows' => $out,
            'more' => count($rows) > count($out) ? (new \moodle_url(
                '/local/oksigeniaayvurenda/readings.php',
                ['course' => $courses[0]]
            ))->out(false) : '',
            'morelabel' => $say('dashreadingsmore')] : null;
    }

    /**
     * What waits at the desks someone keeps in a centre: books set aside for their readers, books late and what readers
     * and classes asked for. All at nothing says so.
     *
     * @param int $entityid
     * @param int $userid
     * @param int $now
     * @param \Closure $say
     * @param \Closure $url
     * @return array[] One per library: name, rows (number, label, url, late), uptodate, desk and desklabel.
     */
    private static function desks(int $entityid, int $userid, int $now, \Closure $say, \Closure $url): array {
        global $DB;
        if (practice::is($entityid)) {
            return [];
        }
        $out = [];
        foreach (catalogue::libraries() as $library) {
            if (
                (int) $library->entityid !== $entityid
                    || !(access::can('local/oksigeniaayvurenda:desk', $library, $userid) || access::keeps($library, $userid))
                    || ($library->parentid && !access::keeps($library, $userid) && !access::can(
                        'local/oksigeniaayvurenda:manage',
                        $library,
                        $userid
                    ))
            ) {
                continue;
            }
            $ready = $DB->count_records(self::T . 'hold', ['libraryid' => $library->id, 'status' => 'ready']);
            $late = $DB->count_records_select(
                self::T . 'loan',
                'libraryid = :libraryid AND timereturned IS NULL AND timedue < :today',
                ['libraryid' => $library->id, 'today' => zones::today($library, $now)]
            );
            $waiting = menu::waiting($library, $userid);
            $asked = (int) ($waiting['wishes.php'] ?? 0) + (int) ($waiting['purchases.php'] ?? 0)
                + (int) ($waiting['formats.php'] ?? 0);
            $askpage = ($waiting['wishes.php'] ?? 0) >= ($waiting['purchases.php'] ?? 0) ? 'wishes.php' : 'purchases.php';
            if (!empty($waiting['formats.php']) && !($waiting['wishes.php'] ?? 0) && !($waiting['purchases.php'] ?? 0)) {
                $askpage = 'formats.php';
            }
            $rows = [];
            foreach (
                [
                    ['n' => $ready, 'key' => 'dashdeskready', 'page' => 'holds.php', 'late' => false],
                    ['n' => $late, 'key' => 'dashdesklate', 'page' => 'late.php', 'late' => true],
                    ['n' => $asked, 'key' => 'dashdeskasks', 'page' => $askpage, 'late' => false],
                ] as $row
            ) {
                if ($row['n']) {
                    $rows[] = ['number' => $row['n'], 'label' => $say($row['key'] . ($row['n'] === 1 ? 'one' : '')),
                        'url' => $url($row['page'], $library), 'late' => $row['late']];
                }
            }
            $out[] = ['name' => format_string($library->name, true, ['escape' => false]), 'rows' => $rows,
                'uptodate' => $rows ? '' : $say('dashdeskclear'), 'desk' => $url('desk.php', $library),
                'desklabel' => $say('dashdeskgo')];
        }
        return $out;
    }
}
