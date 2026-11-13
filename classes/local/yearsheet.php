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
 * The sheet of the year: the twelve months as a wall calendar, a day touched, and what rules the year as sentences.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * The year page had six «Save» buttons, nine lists and a date field that started in 2016. Now the whole year is one sheet:
 * each day says what it is (its term, closed, a day of rest, the first day, the date to have everything back, today), and
 * a day touched (a link, no JavaScript needed) says what it is now and what can be done with it: close it, close from it,
 * end a term on it, make it the date to have everything back. What rules the year is said below in sentences.
 */
final class yearsheet {
    /** @var string[] The term bands, by term. */
    private const BANDS = [1 => 't1', 2 => 't2', 3 => 't3'];

    /**
     * Applies a gesture and says where the page goes back to.
     *
     * @param \stdClass $library
     * @param int $now
     * @return array [anchor, extra parameters of the address].
     */
    public static function apply(\stdClass $library, int $now): array {
        $entityid = (int) $library->entityid;
        $day = fn(string $name) => self::day(optional_param($name, '', PARAM_RAW_TRIMMED));
        if (($one = $day('closeday')) !== null) {
            closures::add($entityid, $one, $one, optional_param('closedname', '', PARAM_TEXT));
            return ['ayv-cs-sheet', ['dia' => $one, 'saved' => 1]];
        }
        if (($from = $day('closefrom')) !== null && ($to = $day('closeto')) !== null) {
            $ok = closures::add($entityid, $from, $to, optional_param('closedname', '', PARAM_TEXT));
            return ['ayv-cs-sheet', $ok ? ['saved' => 1] : ['dia' => $to, 'desde' => $from, 'toolong' => 1]];
        }
        $remove = optional_param('removeclosed', '', PARAM_RAW_TRIMMED);
        if (preg_match('/^(\d{4}-\d{2}-\d{2})\|(\d{4}-\d{2}-\d{2})$/', $remove, $m)) {
            closures::remove($entityid, $m[1], $m[2]);
            return ['ayv-cs-closures', ['saved' => 1]];
        }
        $termend = optional_param('termend', '', PARAM_RAW_TRIMMED);
        if (preg_match('/^([123]):(\d{4}-\d{2}-\d{2})$/', $termend, $m) && self::day($m[2]) !== null) {
            $year = years::around($entityid, $now);
            $ends = array_map(fn($t) => zones::date($t, '%Y-%m-%d', $library), challenges::ends($year));
            $ends[(int) $m[1] - 1] = $m[2];
            $sorted = $ends;
            sort($sorted);
            // A term cannot end after the next one: the ends stay in order, or nothing changes.
            if ($sorted === $ends && count(array_unique($ends)) === 3) {
                challenges::set_ends($entityid, $ends);
                return ['ayv-cs-sheet', ['dia' => $m[2], 'saved' => 1]];
            }
            return ['ayv-cs-sheet', ['dia' => $m[2], 'order' => 1]];
        }
        if (optional_param('termsreset', 0, PARAM_BOOL)) {
            challenges::set_ends($entityid, []);
            return ['ayv-cs-terms', ['saved' => 1]];
        }
        $deadline = optional_param('deadline', null, PARAM_RAW_TRIMMED);
        if ($deadline !== null) {
            closures::set_return_by($entityid, self::day($deadline) ?? '');
            return [$deadline === '' ? 'ayv-cs-deadline' : 'ayv-cs-sheet', ['saved' => 1]];
        }
        $rest = optional_param_array('weekclosed', [], PARAM_INT);
        if ($rest) {
            $days = zones::rest_days($library);
            foreach ($rest as $iso => $closed) {
                if ($iso >= 1 && $iso <= 7) {
                    $days = $closed ? array_values(array_unique(array_merge($days, [(int) $iso])))
                        : array_values(array_diff($days, [(int) $iso]));
                }
            }
            libraries::update_entity($entityid, ['restdays' => $days]);
            return ['ayv-cs-rest', ['saved' => 1]];
        }
        $weekstart = optional_param('weekstart', -1, PARAM_INT);
        if ($weekstart >= 0 && $weekstart <= 7) {
            libraries::update_entity($entityid, ['weekstart' => $weekstart]);
            return ['ayv-cs-weekstart', ['saved' => 1]];
        }
        $month = optional_param('startmonth', 0, PARAM_INT);
        $startday = optional_param('startday', 0, PARAM_INT);
        if ($month || $startday) {
            [$was, $wasday] = array_map('intval', explode('-', (string) (self::yearstart($entityid))));
            $month = $month ?: $was;
            $startday = max(1, min((int) date('t', mktime(12, 0, 0, $month, 1, 2001)), $startday ?: $wasday));
            year_change::set_start($entityid, $month, $startday);
            return ['ayv-cs-next', ['saved' => 1]];
        }
        if (optional_param('sync', 0, PARAM_BOOL)) {
            $stats = \local_oksigeniaayvurenda\local\sources\cohorts::sync($entityid, null, true);
            return ['ayv-cs-groups', ['synced' => json_encode($stats)]];
        }
        return ['ayv-cs-sheet', []];
    }

    /**
     * A day as 'Y-m-d', when it is one.
     *
     * @param string $text
     * @return string|null
     */
    private static function day(string $text): ?string {
        return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $text, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])
            ? $text : null;
    }

    /**
     * The settings of a centre as kept.
     *
     * @param int $entityid
     * @return array
     */
    private static function settings(int $entityid): array {
        global $DB;
        return json_decode((string) $DB->get_field('local_oksigeniaayvurenda_entity', 'settings', ['id' => $entityid]), true)
            ?: [];
    }

    /**
     * The month and day the centre's year starts, as 'm-d'.
     *
     * @param int $entityid
     * @return string
     */
    private static function yearstart(int $entityid): string {
        global $DB;
        $start = (string) $DB->get_field('local_oksigeniaayvurenda_entity', 'yearstart', ['id' => $entityid]);
        return preg_match('/^\d{2}-\d{2}$/', $start) ? $start : '09-01';
    }

    /**
     * The sheet: the twelve months of the year, each day with what it is.
     *
     * @param \stdClass $library
     * @param \stdClass $year
     * @param int $now
     * @param string $touched The day touched, or ''.
     * @param string $from The first day of a closure being chosen, or ''.
     * @param string $base The address of the page.
     * @param array|null $pick Choosing the days of a report: report, and first (the first day, or '').
     * @return array months and the letters of the week.
     */
    public static function months(
        \stdClass $library,
        \stdClass $year,
        int $now,
        string $touched,
        string $from,
        string $base,
        ?array $pick = null
    ): array {
        $entityid = (int) $library->entityid;
        $zone = zones::of($entityid);
        $local = fn(int $time) => (new \DateTimeImmutable('@' . $time))->setTimezone($zone);
        $classes = !skins::no_classes($entityid);
        $ends = $classes ? array_map(fn($t) => $local($t)->format('Y-m-d'), challenges::ends($year)) : [];
        $closed = [];
        foreach (closures::of($entityid) as $range) {
            $day = new \DateTimeImmutable($range['from'], $zone);
            for ($i = 0; $i <= closures::LONGEST && $day->format('Y-m-d') <= $range['to']; $i++) {
                $closed[$day->format('Y-m-d')] = $range['label'];
                $day = $day->modify('+1 day');
            }
        }
        $rest = zones::rest_days($library);
        $first = zones::first_weekday($library);
        $start = $local((int) $year->timestart)->format('Y-m-d');
        $deadline = closures::return_by($entityid);
        $deadline = $deadline ? $local($deadline)->format('Y-m-d') : '';
        $today = $local($now)->format('Y-m-d');
        $letters = explode(',', get_string('yearsheetletters', 'local_oksigeniaayvurenda'));
        $months = [];
        $month = $local((int) $year->timestart)->modify('first day of this month')->setTime(12, 0);
        for ($m = 0; $m < 12; $m++) {
            $cells = [];
            $lead = ((int) $month->format('N') - $first + 7) % 7;
            for ($i = 0; $i < $lead; $i++) {
                $cells[] = ['blank' => true];
            }
            $day = $month;
            while ($day->format('m') === $month->format('m')) {
                $date = $day->format('Y-m-d');
                $term = 0;
                foreach ($ends as $i => $end) {
                    if ($date >= $start && $date <= $end) {
                        $term = $i + 1;
                        break;
                    }
                }
                $params = ['library' => $library->slug,
                    'dia' => $date] + ($from !== '' && $date >= $from ? ['desde' => $from] : []);
                // Choosing the days of a report: the first touch is its first day, the second goes back to the report.
                $link = new \moodle_url($base, $params, 'ayv-cs-touched');
                if ($pick) {
                    $link = $pick['first'] !== '' && $date >= $pick['first']
                        ? new \moodle_url('/local/oksigeniaayvurenda/reports.php', ['library' => $library->slug,
                            'report' => $pick['report'], 'desde' => $pick['first'], 'hasta' => $date])
                        : new \moodle_url(
                            $base,
                            ['library' => $library->slug, 'elegir' => $pick['report'], 'desde' => $date],
                            'ayv-cs-sheet'
                        );
                }
                // What a screen reader says of the day: its name and what it is.
                $aria = [get_string('hiwdaymonth', 'local_oksigeniaayvurenda', (object) ['day' => (int) $day->format('j'),
                    'month' => userdate($day->getTimestamp(), '%B')])];
                $aria[0] = \core_text::strtolower(userdate($day->getTimestamp(), '%A')) . ' ' . $aria[0];
                if (isset($closed[$date])) {
                    $aria[] = get_string('yearsheetariaclosed', 'local_oksigeniaayvurenda', $closed[$date] !== '' ? $closed[$date]
                        : get_string('closuresnoname', 'local_oksigeniaayvurenda'));
                }
                $marks = ['first' => $date === $start, 'deadline' => $date === $deadline, 'today' => $date === $today];
                foreach ($marks as $what => $is) {
                    if ($is) {
                        $aria[] = get_string('yearsheetaria' . $what, 'local_oksigeniaayvurenda');
                    }
                }
                $cells[] = [
                    'blank' => false,
                    'aria' => implode(', ', $aria),
                    'n' => (int) $day->format('j'),
                    'date' => $date,
                    'url' => $link->out(false),
                    'band' => $term ? self::BANDS[$term] : '',
                    'termend' => in_array($date, $ends, true),
                    'closed' => isset($closed[$date]),
                    'rest' => in_array((int) $day->format('N'), $rest, true),
                    'first' => $date === $start,
                    'deadline' => $date === $deadline,
                    'today' => $date === $today,
                    'touched' => $date === $touched,
                    'chosen' => ($from !== '' && $date >= $from
                        && $date <= ($touched !== '' && $touched >= $from ? $touched : $from))
                        || ($pick && $date === $pick['first']),
                ];
                $day = $day->modify('+1 day');
            }
            $months[] = ['name' => \core_text::strtoupper(\core_text::substr(userdate($month->getTimestamp(), '%B'), 0, 1))
                . \core_text::substr(userdate($month->getTimestamp(), '%B'), 1), 'year' => $month->format('Y'), 'cells' => $cells];
            $month = $month->modify('first day of next month');
        }
        return ['months' => $months, 'letters' => array_map(
            fn($i) => ['letter' => trim($letters[(($first - 1 + $i) % 7)] ?? '')],
            range(0, 6)
        )];
    }

    /**
     * The card of the day touched: what it is now and what can be done with it.
     *
     * @param \stdClass $library
     * @param \stdClass $year
     * @param string $touched
     * @param string $from
     * @param callable $str
     * @return array|null
     */
    public static function card(\stdClass $library, \stdClass $year, string $touched, string $from, callable $str): ?array {
        $entityid = (int) $library->entityid;
        if (self::day($touched) === null) {
            return null;
        }
        $zone = zones::of($entityid);
        $at = new \DateTimeImmutable($touched . ' 12:00', $zone);
        $long = $str('yearsheetlong', (object) [
            'weekday' => self::upper(userdate($at->getTimestamp(), '%A')),
            'day' => (int) $at->format('j'),
            'month' => userdate($at->getTimestamp(), '%B'),
            'year' => $at->format('Y'),
        ]);
        $ends = !skins::no_classes($entityid) ? array_map(fn($t) => zones::date($t, '%Y-%m-%d', $library), challenges::ends($year))
            : [];
        $start = zones::date((int) $year->timestart, '%Y-%m-%d', $library);
        $term = 0;
        foreach ($ends as $i => $end) {
            if ($touched >= $start && $touched <= $end) {
                $term = $i + 1;
                break;
            }
        }
        $closure = null;
        foreach (closures::of($entityid) as $range) {
            if ($touched >= $range['from'] && $touched <= $range['to']) {
                $closure = $range;
            }
        }
        $now = [];
        if ($term) {
            $now[] = $str('yearsheetinterm', $term);
        }
        $now[] = $closure ? $str('yearsheetclosednow', $closure['label'] !== '' ? $closure['label'] : $str('closuresnoname'))
            : (in_array((int) $at->format('N'), zones::rest_days($library), true) ? $str('yearsheetrestnow')
            : $str('yearsheetopennow'));
        $actions = [];
        // Choosing the last day of a closure that started on another.
        if ($from !== '' && $touched >= $from) {
            return ['long' => $long, 'now' => implode(' ', $now), 'monthshort' => userdate($at->getTimestamp(), '%b'),
                'n' => (int) $at->format('j'), 'range' => [
                    'from' => $from, 'to' => $touched,
                    'says' => $str('yearsheetrange', (object) ['from' => self::short($from, $zone),
                        'to' => self::short($touched, $zone)]),
                    'namelabel' => $str('closuresname'), 'save' => $str('closuressave'),
                ]];
        }
        if ($closure) {
            $actions[] = ['name' => 'removeclosed', 'value' => $closure['from'] . '|' . $closure['to'],
                'label' => $str('yearsheetopen', $closure['label'] !== '' ? $closure['label'] : $str('closuresnoname'))];
        } else {
            $actions[] = ['name' => 'closeday', 'value' => $touched, 'label' => $str('yearsheetcloseone')];
            $actions[] = ['link' => true, 'url' => (new \moodle_url(
                '/local/oksigeniaayvurenda/year.php',
                ['library' => $library->slug,
                'desde' => $touched],
                'ayv-cs-sheet'
            ))->out(false), 'label' => $str('yearsheetclosefrom')];
        }
        foreach ($ends as $i => $end) {
            if (
                $end !== $touched && ($term === $i + 1 || ($term === 0 && $i === 2 && $touched > $end)
                || ($term === $i + 2 && $touched > ($ends[$i] ?? '')))
            ) {
                $actions[] = ['name' => 'termend', 'value' => ($i + 1) . ':' . $touched,
                    'label' => $str('yearsheettermend', $i + 1)];
            }
        }
        $actions[] = ['name' => 'deadline', 'value' => $touched, 'label' => $str('yearsheetdeadline')];
        return ['long' => $long, 'now' => implode(' ', $now), 'monthshort' => userdate($at->getTimestamp(), '%b'),
            'n' => (int) $at->format('j'), 'actions' => $actions, 'range' => null];
    }

    /**
     * A day said short, «22 de diciembre».
     *
     * @param string $day
     * @param \DateTimeZone $zone
     * @return string
     */
    private static function short(string $day, \DateTimeZone $zone): string {
        $at = new \DateTimeImmutable($day . ' 12:00', $zone);
        return get_string('hiwdaymonth', 'local_oksigeniaayvurenda', (object) ['day' => (int) $at->format('j'),
            'month' => userdate($at->getTimestamp(), '%B')]);
    }

    /**
     * A word with its first letter in capitals.
     *
     * @param string $word
     * @return string
     */
    private static function upper(string $word): string {
        return \core_text::strtoupper(\core_text::substr($word, 0, 1)) . \core_text::substr($word, 1);
    }

    /**
     * What rules the year, as sentences with their gestures: the days closed every week, the start of the week, the
     * closures, the ends of the terms, the date to have everything back and the start of the next year.
     *
     * @param \stdClass $library
     * @param \stdClass $year
     * @param callable $str
     * @param string $saved
     * @return array[]
     */
    public static function rules(\stdClass $library, \stdClass $year, callable $str, string $saved): array {
        $entityid = (int) $library->entityid;
        $zone = zones::of($entityid);
        $b = fn(string $text) => '<b>' . s($text) . '</b>';
        $join = function (array $list) use ($str): string {
            $list = array_values($list);
            if (count($list) < 2) {
                return $list[0] ?? '';
            }
            $last = array_pop($list);
            return implode(', ', $list) . ' ' . $str('hiwand') . ' ' . $last;
        };
        $phrase = fn(string $id, string $spine, string $colour, string $says, array $gesture) => ['id' => 'ayv-cs-' . $id,
            'spine' => $spine, 'spinecolour' => $colour, 'says' => $says, 'justsaved' => $saved === 'ayv-cs-' . $id] + $gesture;
        $calendar = \core_calendar\type_factory::get_calendar_instance();
        $weekdays = $calendar->get_weekdays();
        $name = fn(int $iso) => (string) $weekdays[$iso % 7]['fullname'];
        $rest = zones::rest_days($library);
        $first = zones::first_weekday($library);
        $order = array_map(fn($i) => ($first - 1 + $i) % 7 + 1, range(0, 6));
        $phrases = [];
        $letters = explode(',', get_string('yearsheetletters', 'local_oksigeniaayvurenda'));
        $restnames = array_map(
            fn($iso) => $b(\core_text::strtolower($name($iso))),
            array_values(array_intersect($order, $rest))
        );
        $phrases[] = $phrase('rest', $str('yearsheetspinerest'), 'green', $restnames ? $str('yearsheetrest', $join($restnames))
            : $str('yearsheetrestnone'), ['discs' => array_map(fn($iso) => ['name' => 'weekclosed[' . $iso . ']',
                'value' => in_array($iso, $rest, true) ? '0' : '1', 'label' => self::upper($name($iso)),
                'letter' => trim($letters[$iso - 1] ?? ''), 'pressed' => in_array($iso, $rest, true)], $order)]);
        $own = (int) (self::settings($entityid)['weekstart'] ?? 0);
        $phrases[] = $phrase('weekstart', $str('yearsheetspineweek'), 'green2', $str($own ? 'yearsheetweekstart'
            : 'yearsheetweekstartmoodle', $b(\core_text::strtolower($name($first)))), ['chips' => array_merge(
                [['name' => 'weekstart', 'value' => '0', 'label' => $str('yearsheetweeklanguage'), 'pressed' => $own === 0]],
                array_map(fn($iso) => ['name' => 'weekstart', 'value' => (string) $iso, 'label' => self::upper($name($iso)),
                    'pressed' => $own === $iso], range(1, 7))
            )]);
        $today = zones::date(time(), '%Y-%m-%d', $library);
        $ranges = array_values(array_filter(closures::of($entityid), fn($r) => $r['to'] >= $today));
        $label = fn(array $r) => $r['label'] !== '' ? $r['label'] : $str('closuresnoname');
        $when = fn(array $r) => $r['from'] === $r['to'] ? self::short($r['from'], $zone)
            : $str('closuresrange', (object) ['from' => self::short($r['from'], $zone), 'to' => self::short($r['to'], $zone)]);
        $phrases[] = $phrase('closures', $str('yearsheetspineclosed'), 'coral', !$ranges ? $str('yearsheetclosednone')
            : $str(count($ranges) === 1 ? 'yearsheetclosedone' : 'yearsheetclosed', (object) ['count' => count($ranges),
                'names' => $join(array_map(fn($r) => $b($label($r)), $ranges))]), ['closures' => [
                    'list' => array_map(
                        fn($r) => ['name' => $label($r), 'when' => $when($r),
                        'value' => $r['from'] . '|' . $r['to']],
                        $ranges
                    ),
                    'note' => $str('yearsheetclosednote'), 'remove' => $str('closuresremove'),
                ]]);
        if (!skins::no_classes($entityid)) {
            $ends = challenges::ends($year);
            $settings = self::settings($entityid);
            $phrases[] = $phrase('terms', $str('yearsheetspineterms'), 'sun', $str('yearsheetterms', $join(array_map(
                fn($t) => $b(self::short(zones::date($t, '%Y-%m-%d', $library), $zone)),
                $ends
            ))), ['terms' => ['note' => $str('yearsheettermsnote'), 'reset' => !empty($settings['termends'])
                ? $str('yearsheettermsreset') : '']]);
        }
        $deadline = closures::return_by($entityid);
        $phrases[] = $phrase('deadline', $str('yearsheetspinedeadline'), 'coral', $deadline ? $str(
            'yearsheetdeadlineis',
            $b(self::short(zones::date($deadline, '%Y-%m-%d', $library), $zone))
        ) : $str('yearsheetdeadlinenone'), ['deadline' => [
                'has' => (bool) $deadline, 'none' => $str('yearsheetdeadlineclear'), 'note' => $str('yearsheetdeadlinenote'),
            ]]);
        [$month, $day] = array_map('intval', explode('-', self::yearstart($entityid)));
        $phrases[] = $phrase('next', $str('yearsheetspinenext'), 'night', $str('yearsheetnext', $b(get_string(
            'hiwdaymonth',
            'local_oksigeniaayvurenda',
            (object) ['day' => $day, 'month' => userdate(make_timestamp(2001, $month, 15, 12), '%B')]
        ))), ['next' => [
            'months' => array_map(fn($m) => ['name' => 'startmonth', 'value' => (string) $m,
                'label' => userdate(make_timestamp(2001, $m, 15, 12), '%B'), 'pressed' => $m === $month], range(1, 12)),
            'day' => $day, 'less' => max(1, $day - 1), 'more' => min(31, $day + 1), 'atfirst' => $day <= 1, 'atlast' => $day >= 31,
            'monthshort' => userdate(make_timestamp(2001, $month, 15, 12), '%b'),
            'daylabel' => $str('hiwyearday'), 'monthlabel' => $str('hiwyearmonth'),
        ]]);
        return $phrases;
    }
}
