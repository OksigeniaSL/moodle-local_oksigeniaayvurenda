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
 * The library's functions, each presented at its moment (the first day, point 6, decided by Enrique on 11 November): when
 * something real happens in a library (the first late books, a queue for one book, a catalogue just imported, the end of
 * the year coming, the window closed with the catalogue ready, voices waiting), its staff see on the desk the one function
 * that answers it, with «Show me», «Turn it on» where it has a light, and «Not now».
 *
 * One at a time and at most one a week for each centre. «Not now» puts it away until the situation comes again; a second
 * «Not now» puts it away for good. What is touched is counted, so that what is used, not what is seen, can be measured.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class timely {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var string[] The situations, the most urgent first. */
    public const KINDS = ['late', 'queue', 'imported', 'yearend', 'window', 'voices'];

    /** @var int Seconds between one card and the next, for a centre. */
    public const WEEK = 7 * DAYSECS;

    /** @var int Days before the last day of classes from which the end of the year is a moment. */
    public const YEAREND = 21;

    /** @var int Days after an import when its labels are still a moment. */
    public const IMPORTED = 7;

    /** @var int Readers waiting for the same book that make a queue. */
    public const QUEUE = 3;

    /** @var int Records from which a catalogue is ready to be shown. */
    public const READY = 50;

    /**
     * A centre's state of the cards: the one showing and since when, the last answered, and each kind's «not now».
     *
     * @param int $entityid
     * @return array current, since, last, no (kind => times), calm (kind => true once its situation went away).
     */
    public static function state(int $entityid): array {
        global $DB;
        $settings = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        return ((array) ($settings['timely'] ?? [])) + ['current' => '', 'since' => 0, 'last' => 0, 'no' => [], 'calm' => [],
            'used' => []];
    }

    /**
     * Saves a centre's state of the cards.
     *
     * @param int $entityid
     * @param array $state
     */
    private static function keep(int $entityid, array $state): void {
        global $DB;
        $settings = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        $settings['timely'] = $state;
        $DB->set_field(self::T . 'entity', 'settings', json_encode($settings), ['id' => $entityid]);
    }

    /**
     * Whether a situation is happening in a library now, and what it is about.
     *
     * @param \stdClass $library
     * @param string $kind
     * @param int $now
     * @param int $userid Who looks: the voices waiting are those they may approve.
     * @return array|null count and, for some, day; null when it is not happening.
     */
    public static function situation(\stdClass $library, string $kind, int $now, int $userid = 0): ?array {
        global $DB;
        $entityid = (int) $library->entityid;
        switch ($kind) {
            case 'late':
                $notices = sanctions::settings($entityid);
                if (($notices['noticereaders'] || $notices['noticefamilies']) && $notices['noticeevery'] !== 'hand') {
                    return null;
                }
                $today = (new \DateTimeImmutable('@' . $now))->setTimezone(zones::of($entityid))->setTime(0, 0)->getTimestamp();
                $late = $DB->count_records_select(self::T . 'loan', 'libraryid = :libraryid AND timereturned IS NULL
                    AND timedue < :today', ['libraryid' => $library->id, 'today' => $today]);
                return $late ? ['count' => $late] : null;
            case 'queue':
                $queues = $DB->get_records_sql(
                    "SELECT h.recordid, COUNT(1) AS waiting, r.title
                       FROM {" . self::T . "hold} h
                       JOIN {" . self::T . "record} r ON r.id = h.recordid
                      WHERE h.libraryid = :libraryid AND h.status = 'waiting'
                   GROUP BY h.recordid, r.title
                     HAVING COUNT(1) >= :queue
                   ORDER BY COUNT(1) DESC",
                    ['libraryid' => $library->id, 'queue' => self::QUEUE],
                    0,
                    20
                );
                $top = reset($queues);
                return $top ? ['count' => count($queues), 'waiting' => (int) $top->waiting,
                    'title' => format_string($top->title, true, ['escape' => false])] : null;
            case 'imported':
                $last = activity::last($entityid, ['catalogue_imported']);
                if (!$last || $last['time'] <= $now - self::IMPORTED * DAYSECS) {
                    return null;
                }
                $day = (new \DateTimeImmutable('@' . $last['time']))->setTimezone(zones::of($entityid))->setTime(0, 0);
                $today = (new \DateTimeImmutable('@' . $now))->setTimezone(zones::of($entityid))->setTime(0, 0);
                $count = $DB->count_records_select(
                    self::T . 'item',
                    'entityid = ? AND timecreated >= ?',
                    [$entityid, $day->getTimestamp()]
                );
                return $count ? ['count' => $count, 'day' => $day->format('Y-m-d'), 'today' => $day == $today] : null;
            case 'yearend':
                $end = booklet::classes_end(years::around($entityid, $now));
                $left = (int) ceil(($end - $now) / DAYSECS);
                return $left > 0 && $left <= self::YEAREND ? ['count' => $left] : null;
            case 'window':
                if (window::settings($library, true)['open'] || access::closed($entityid)) {
                    return null;
                }
                $records = $DB->get_records_select(self::T . 'record', 'entityid = ?', [$entityid], 'id', 'id', 0, self::READY);
                return count($records) >= self::READY ? ['count' => count($records)] : null;
            case 'voices':
                if (!plans::allows($entityid, 'voices') || !$userid) {
                    return null;
                }
                $pending = voices::pending($library, $userid);
                return $pending ? ['count' => count($pending)] : null;
        }
        return null;
    }

    /**
     * Whether someone may act on a situation: what it shows and what it turns on are theirs to touch.
     *
     * @param \stdClass $library
     * @param string $kind
     * @param int $userid
     * @return bool
     */
    public static function theirs(\stdClass $library, string $kind, int $userid): bool {
        $capability = match ($kind) {
            'queue', 'imported' => 'local/oksigeniaayvurenda:catalogue',
            'voices' => 'local/oksigeniaayvurenda:desk',
            default => 'local/oksigeniaayvurenda:manage',
        };
        return access::can($capability, $library, $userid);
    }

    /**
     * The card a person sees on the desk now, if any: the one showing this week while its situation lasts; otherwise, a
     * week after the last, the most urgent situation happening that is theirs, not put away.
     *
     * @param \stdClass $library
     * @param int $userid
     * @param int $now
     * @return array|null kind and the situation's count and day.
     */
    public static function pick(\stdClass $library, int $userid, int $now): ?array {
        $entityid = (int) $library->entityid;
        if (practice::is($entityid) || sample::is($entityid) || access::closed($entityid)) {
            return null;
        }
        $state = self::state($entityid);
        $changed = false;
        // A kind put away once may come again only after its situation went away.
        foreach ($state['no'] as $kind => $times) {
            if ((int) $times === 1 && empty($state['calm'][$kind]) && !self::situation($library, $kind, $now, $userid)) {
                $state['calm'][$kind] = true;
                $changed = true;
            }
        }
        $found = null;
        if ($state['current'] !== '' && $state['since'] > $now - self::WEEK) {
            $situation = self::situation($library, $state['current'], $now, $userid);
            if ($situation && self::theirs($library, $state['current'], $userid)) {
                $found = ['kind' => $state['current']] + $situation;
            }
        } else if ($state['last'] <= $now - self::WEEK) {
            foreach (self::KINDS as $kind) {
                $times = (int) ($state['no'][$kind] ?? 0);
                if ($times >= 2 || ($times === 1 && empty($state['calm'][$kind])) || !self::theirs($library, $kind, $userid)) {
                    continue;
                }
                $situation = self::situation($library, $kind, $now, $userid);
                if ($situation) {
                    $found = ['kind' => $kind] + $situation;
                    $state['current'] = $kind;
                    $state['since'] = $now;
                    $state['last'] = $now;
                    $changed = true;
                    break;
                }
            }
        }
        if ($changed) {
            self::keep($entityid, $state);
        }
        return $found;
    }

    /**
     * What the staff answered to a card: «show» (they went to see it), «on» (they turned it on) or «no» (not now). The
     * card goes; «not now» counts, and the rest count as used.
     *
     * @param \stdClass $library
     * @param string $kind
     * @param string $answer show, on or no.
     * @param int $now
     */
    public static function answer(\stdClass $library, string $kind, string $answer, int $now): void {
        $entityid = (int) $library->entityid;
        if (!in_array($kind, self::KINDS, true)) {
            return;
        }
        $state = self::state($entityid);
        if ($answer === 'no') {
            $state['no'][$kind] = (int) ($state['no'][$kind] ?? 0) + 1;
            unset($state['calm'][$kind]);
        } else {
            $state['used'][$kind] = (int) ($state['used'][$kind] ?? 0) + 1;
        }
        if ($answer === 'on') {
            self::turn_on($library, $kind);
        }
        if ($state['current'] === $kind) {
            $state['current'] = '';
        }
        $state['last'] = $now;
        self::keep($entityid, $state);
    }

    /**
     * Whether a situation's function has a light that is off: what «Turn it on» would do.
     *
     * @param \stdClass $library
     * @param string $kind
     * @return bool
     */
    public static function can_turn_on(\stdClass $library, string $kind): bool {
        $entityid = (int) $library->entityid;
        return match ($kind) {
            'late' => true,
            'queue' => !holds::settings($entityid)['online'],
            'yearend' => !booklet::on($entityid),
            'window' => !window::settings($library, true)['open'],
            default => false,
        };
    }

    /**
     * Turns on the function that answers a situation.
     *
     * @param \stdClass $library
     * @param string $kind
     */
    private static function turn_on(\stdClass $library, string $kind): void {
        $entityid = (int) $library->entityid;
        if ($kind === 'late') {
            $notices = sanctions::settings($entityid);
            sanctions::save_settings($entityid, ['noticereaders' => true, 'noticefamilies' => true,
                'noticeevery' => $notices['noticeevery'] === 'hand' ? 'week' : $notices['noticeevery']] + $notices);
        } else if ($kind === 'queue') {
            holds::save_settings($entityid, ['online' => true] + holds::settings($entityid));
        } else if ($kind === 'yearend') {
            booklet::save($entityid, true);
        } else if ($kind === 'window') {
            window::save($library, ['open' => true] + window::settings($library, true));
        }
    }

    /** @var array The spine of each situation's function, and its colour, as the books of «How your library works». */
    public const SPINES = ['late' => 'coral', 'queue' => 'sun', 'imported' => 'green', 'yearend' => 'night',
        'window' => 'green2', 'voices' => 'coral'];

    /**
     * The card of the desk (Jujol): the situation happening now that is this person's, or, once turned on, what happens
     * from tomorrow.
     *
     * @param \stdClass $library
     * @param int $userid
     * @param int $now
     * @param string $lit A kind just turned on: its card once, lit.
     * @return array|null The context of timely_card.
     */
    public static function context(\stdClass $library, int $userid, int $now, string $lit = ''): ?array {
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        $action = (new \moodle_url('/local/oksigeniaayvurenda/desk.php', ['library' => $library->slug]))->out(false);
        if (in_array($lit, self::KINDS, true) && !in_array($lit, ['imported', 'voices'], true)) {
            return ['lit' => true, 'spine' => $str('timelyspine' . $lit), 'colour' => self::SPINES[$lit],
                'when' => $str('timelylit'), 'title' => $str('timelylit' . $lit . 'title'),
                'what' => $str('timelylit' . $lit . 'what'),
                'action' => $action, 'sesskey' => sesskey(), 'kind' => $lit, 'light' => null,
                'show' => ['url' => self::lit_url($library, $lit)->out(false), 'label' => $str('timelyseehow')], 'notnow' => ''];
        }
        $situation = self::pick($library, $userid, $now);
        if (!$situation) {
            return null;
        }
        $kind = $situation['kind'];
        $count = (int) $situation['count'];
        $one = $count === 1 ? 'one' : '';
        $title = match ($kind) {
            'queue' => $str('timelyqueuetitle', (object) ['waiting' => $situation['waiting'], 'title' => $situation['title']]),
            'imported' => $str('timelyimportedtitle', screens::number($count)),
            default => $str('timely' . $kind . 'title' . $one, screens::number($count)),
        };
        $what = $str('timely' . $kind . 'what' . ($kind === 'voices' ? $one : ''));
        if ($kind === 'queue' && self::can_turn_on($library, 'queue')) {
            $what .= ' ' . $str('timelyqueueonline');
        }
        if ($kind === 'yearend' && self::can_turn_on($library, 'yearend')) {
            $what .= ' ' . $str('timelyyearendbooklet');
        }
        $when = match ($kind) {
            'imported' => $str(!empty($situation['today']) ? 'timelyhappenedtoday' : 'timelyhappenedweek'),
            'yearend' => $str('timelycoming'),
            'window' => $str('timelyready'),
            'voices' => $str('timelywaiting'),
            default => $str('timelyhappeningnow'),
        };
        $through = new \moodle_url('/local/oksigeniaayvurenda/desk.php', ['library' => $library->slug, 'timely' => 'show',
            'kind' => $kind, 'sesskey' => sesskey()]);
        $show = $kind === 'window' ? null : ['url' => $through->out(false),
            'label' => $str('timely' . $kind . 'show' . ($kind === 'voices' ? $one : ''))];
        return ['lit' => false, 'spine' => $str('timelyspine' . $kind), 'colour' => self::SPINES[$kind], 'when' => $when,
            'title' => $title, 'what' => $what, 'action' => $action, 'sesskey' => sesskey(), 'kind' => $kind,
            'light' => self::can_turn_on($library, $kind) ? ['label' => $str('timely' . $kind . 'light')] : null,
            'show' => $show, 'notnow' => $str('timelynotnow')];
    }

    /**
     * Where «See how it is» takes the staff once a function is turned on.
     *
     * @param \stdClass $library
     * @param string $kind
     * @return \moodle_url
     */
    public static function lit_url(\stdClass $library, string $kind): \moodle_url {
        $at = fn(string $page, ?string $anchor = null) => new \moodle_url(
            '/local/oksigeniaayvurenda/' . $page,
            ['library' => $library->slug],
            $anchor
        );
        return match ($kind) {
            'late' => $at('rules.php', 'ayv-nr-avisos'),
            'queue' => $at('holds.php'),
            'yearend' => $at('howitworks.php', 'ayv-hiw-booklet'),
            default => $at('public.php'),
        };
    }

    /**
     * Where «Show me» takes the staff for a situation.
     *
     * @param \stdClass $library
     * @param array $situation As pick() gives it.
     * @return \moodle_url
     */
    public static function show_url(\stdClass $library, array $situation): \moodle_url {
        $at = fn(string $page, array $more = [], ?string $anchor = null) => new \moodle_url(
            '/local/oksigeniaayvurenda/' . $page,
            ['library' => $library->slug] + $more,
            $anchor
        );
        return match ($situation['kind']) {
            'late' => $at('rules.php', [], 'ayv-nr-avisos'),
            'queue' => $at('purchases.php', ['tab' => 'asked']),
            'imported' => $at('labels.php', ['which' => 'since', 'since' => $situation['day'] ?? '']),
            'yearend' => $at('year.php'),
            'window' => $at('public.php'),
            default => $at('voices.php'),
        };
    }
}
