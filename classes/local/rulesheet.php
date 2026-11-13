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
 * The loan rules of a library, the notices of late books and what a late book leaves, with every setting in sight.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

use local_oksigeniaayvurenda\core\desk\sanction_rules;
use local_oksigeniaayvurenda\core\loan_rules;
use local_oksigeniaayvurenda\core\notice_rules;

/**
 * The page of the rules was a sentence per setting with «Change» opening a form: eight of them. Now each loan rule is a
 * library loan card with its counters on it, and every other setting a card with its sentence on top and its gestures
 * under it, in sight. Each gesture is a button that sends only what it changes, so it works without JavaScript and the
 * rest stays as it was.
 */
final class rulesheet {
    /** @var string[] The band of a loan card, by kind of reader. */
    private const COLOURS = ['*' => 'green', 'student' => 'coral', 'teacher' => 'sun', 'staff' => 'green2'];

    /**
     * Applies what a gesture sent, and says where the page goes back to.
     *
     * @param \stdClass $library
     * @return string The anchor of the card touched.
     */
    public static function apply(\stdClass $library): string {
        $entityid = (int) $library->entityid;
        $rule = optional_param('rule', '', PARAM_ALPHANUM);
        if ($rule !== '') {
            $at = workings::change_rule($library, $rule);
            return $at === null ? 'ayv-nr-las' : 'ayv-nr-norma-' . $at;
        }
        $set = optional_param('set', '', PARAM_ALPHA);
        if ($set === 'renew') {
            rules::save_renew_from($entityid, optional_param('renewfrom', loan_rules::FROM_TODAY, PARAM_ALPHA));
            return 'ayv-nr-ampliar';
        }
        $before = sanctions::settings($entityid);
        if ($set === 'notices') {
            sanctions::save_settings($entityid, array_merge($before, self::notices($entityid, $before)));
            return 'ayv-nr-avisos';
        }
        if ($set === 'late') {
            sanctions::save_settings($entityid, array_merge($before, self::late($entityid, $before)));
            return 'ayv-nr-tarde';
        }
        return 'ayv-nr-las';
    }

    /**
     * What a gesture of the notices changes: a start of a touch, or one thing.
     *
     * @param int $entityid
     * @param array $before
     * @return array
     */
    private static function notices(int $entityid, array $before): array {
        $preset = optional_param('preset', '', PARAM_ALPHA);
        if (isset(notice_rules::PRESETS[$preset])) {
            $chosen = notice_rules::PRESETS[$preset];
            if ($chosen['noticeevery'] === notice_rules::DATES) {
                $chosen['noticedates'] = self::thirds($entityid);
            }
            return $chosen;
        }
        $changed = [];
        $after = optional_param('noticeafter', -1, PARAM_INT);
        if ($after !== -1) {
            $changed['noticeafter'] = $after;
        }
        $every = optional_param('noticeevery', '', PARAM_ALPHA);
        if (in_array($every, notice_rules::rhythms(), true)) {
            $changed['noticeevery'] = $every;
            // Some days of the year, with none yet: the end of each third of the year, to be retouched.
            if ($every === notice_rules::DATES && !$before['noticedates']) {
                $changed['noticedates'] = self::thirds($entityid);
            }
        }
        foreach (['noticereaders', 'noticefamilies', 'noticetutors'] as $who) {
            $on = optional_param($who, -1, PARAM_INT);
            if ($on !== -1) {
                $changed[$who] = (bool) $on;
            }
        }
        if (optional_param('dates', 0, PARAM_BOOL)) {
            $changed['noticedates'] = optional_param_array('noticedates', [], PARAM_RAW_TRIMMED);
        }
        return $changed;
    }

    /**
     * The days that end each third of the centre's year: up to the date to have everything back, or the year less its
     * long holiday.
     *
     * @param int $entityid
     * @return string[]
     */
    private static function thirds(int $entityid): array {
        $year = years::around($entityid, time());
        $last = closures::return_by($entityid);
        $end = $last !== null && $last > (int) $year->timestart && $last <= (int) $year->timeend ? $last
            : (int) $year->timeend - 70 * DAYSECS;
        return notice_rules::thirds((int) $year->timestart, $end + 1, zones::of($entityid));
    }

    /**
     * What a gesture of the late books changes: the one thing sent.
     *
     * @param int $entityid
     * @param array $before
     * @return array
     */
    private static function late(int $entityid, array $before): array {
        $changed = [];
        $mode = optional_param('mode', '', PARAM_ALPHA);
        if (in_array($mode, [sanction_rules::NONE, sanction_rules::DAYS, sanction_rules::FINE], true)) {
            $changed['mode'] = $mode;
        }
        foreach (['grace', 'perday', 'maxdays', 'cents', 'maxcents', 'block'] as $field) {
            $value = optional_param($field, -1, PARAM_INT);
            if ($value !== -1) {
                $changed[$field] = $value;
            }
        }
        $adults = optional_param('adults', -1, PARAM_INT);
        if ($adults !== -1) {
            $changed['adults'] = (bool) $adults;
        }
        $currency = \core_text::strtoupper(optional_param('currency', '', PARAM_ALPHA));
        if ($currency !== '' && array_key_exists($currency, get_string_manager()->get_list_of_currencies())) {
            $changed['currency'] = $currency;
        }
        $stage = optional_param('stage', '', PARAM_ALPHANUM);
        $stages = holds::stages($entityid, time());
        if ($stage === 'all') {
            $changed['stages'] = [];
        } else if ($stage !== '' && isset($stages[(int) $stage])) {
            // A stage touched comes in or goes out; with every stage in, or none left, it is all of them again.
            $name = $stages[(int) $stage];
            $mine = $before['stages'];
            $mine = in_array($name, $mine, true) ? array_values(array_diff($mine, [$name])) : array_merge($mine, [$name]);
            $changed['stages'] = count(array_intersect($stages, $mine)) === count($stages) ? [] : $mine;
        }
        return $changed;
    }

    /**
     * What the page shows.
     *
     * @param \stdClass $library
     * @param string $action Where the gestures go.
     * @param string $saved The anchor of the card just changed, which says so.
     * @param bool $help Whether it is inside a guided help, which shows no other help.
     * @return array
     */
    public static function context(\stdClass $library, string $action, string $saved = '', bool $help = false): array {
        global $DB;
        $entityid = (int) $library->entityid;
        $str = skins::speaker($library);
        $url = fn(string $page) => (new \moodle_url('/local/oksigeniaayvurenda/' . $page, ['library' => $library->slug]))
            ->out(false);
        $cards = [];
        foreach (rules::of((int) $library->id) as $at => $rule) {
            $cards[] = self::card($library, (string) $at, $rule, $str, $saved === 'ayv-nr-norma-' . $at);
        }
        $parent = !empty($library->parentid)
            ? $DB->get_record('local_oksigeniaayvurenda_library', ['id' => $library->parentid]) : null;
        $pose = $saved !== '' ? 'celebra' : 'lee';
        $crier = characters::whole($entityid) ? characters::figure('herald', $pose, characters::step($entityid)) : null;
        $late = sanctions::settings($entityid);
        $stages = holds::stages($entityid, time());
        $classes = $stages && !skins::no_classes($entityid);
        return [
            'title' => $str('rulestitle'),
            'intro' => $str('rulesintrogestures'),
            'action' => $action,
            'sesskey' => sesskey(),
            'savedword' => $str('hiwsaved'),
            'crier' => $crier ? ['figure' => $crier, 'puppet' => characters::puppet('herald', $pose, characters::step($entityid))]
                : null,
            'shelf' => $str('rulesshelf'),
            'shelfsub' => $str('rulesprecedence'),
            'inherited' => $cards ? '' : ($parent ? $str('rulesparent', format_string($parent->name, true, ['escape' => false]))
                : $str('rulesdefault')),
            'cards' => $cards,
            'newcard' => [
                'summary' => $str('rulesnewcard'),
                'who' => array_map(fn($w) => ['value' => $w === '*' ? 'any' : $w, 'label' => workings::who($library, $w, $str),
                    'checked' => $w === '*'], workings::readertypes($library)),
                'what' => array_map(fn($w) => ['value' => $w === '*' ? 'any' : $w, 'label' => workings::what($w, $str),
                    'checked' => $w === '*'], array_merge(['*'], cataloguer::itemtypes($entityid))),
                'wholabel' => $str('rulesnewfor'),
                'whatlabel' => $str('hiwwhat'),
                'create' => $str('rulesnewcreate'),
                'note' => $str('rulesnewnote'),
            ],
            'settings' => [
                self::renew($entityid, $str, $saved === 'ayv-nr-ampliar'),
                self::notice_card($entityid, $late, $str, $url, $saved === 'ayv-nr-avisos'),
                // Its own guided help, beside it: the page's «How does it work?» shows the loan rules.
                ['help' => $help ? null : ['url' => (new \moodle_url(
                    '/local/oksigeniaayvurenda/help.php',
                    ['library' => $library->slug, 'piece' => 'sanctions']
                ))->out(false), 'label' => $str('helpnav')]]
                    + self::late_card($late, $stages, $classes, $str, $url, $saved === 'ayv-nr-tarde'),
            ],
        ];
    }

    /**
     * A loan rule as a library loan card: who and what on its head, the sentence, its counters and its lights.
     *
     * @param \stdClass $library
     * @param string $at
     * @param array $rule
     * @param callable $str
     * @param bool $justsaved
     * @return array
     */
    private static function card(\stdClass $library, string $at, array $rule, callable $str, bool $justsaved): array {
        $form = 'ayv-nr-norma-' . $at . '-f';
        $loan = (int) $rule['loandays'];
        $max = (int) $rule['maxloans'];
        $renewals = (int) $rule['renewals'];
        $renewdays = (int) $rule['renewdays'] ?: 7;
        $word = fn(int $n, string $one, string $many) => $str($n === 1 ? $one : $many);
        $steps = [];
        if ($loan > 0) {
            $steps[] = $max === 0 ? self::still($str('hiwatonce'), $str('hiwnolimit'))
                : self::step(
                    $form,
                    'maxloans',
                    $str('hiwatonce'),
                    $max,
                    1,
                    99,
                    1,
                    (string) $max,
                    $word($max, 'rulesunitbook', 'rulesunitbooks'),
                    $str
                );
            $steps[] = self::step(
                $form,
                'loandays',
                $str('hiwdays'),
                $loan,
                1,
                999,
                1,
                (string) $loan,
                $word($loan, 'rulesunitday', 'rulesunitdays'),
                $str
            );
            $steps[] = self::step(
                $form,
                'renewals',
                $str('hiwrenewals'),
                $renewals,
                0,
                20,
                1,
                (string) $renewals,
                $word($renewals, 'rulesunittime', 'rulesunittimes'),
                $str
            );
            if ($renewals > 0) {
                $steps[] = self::step(
                    $form,
                    'renewdays',
                    $str('hiwrenewdays'),
                    $renewdays,
                    1,
                    999,
                    1,
                    (string) $renewdays,
                    $word($renewdays, 'rulesunitdaymore', 'rulesunitdaysmore'),
                    $str
                );
            }
        }
        $lights = [];
        if ($loan > 0) {
            $lights[] = self::light($form, 'maxloans', $max === 0 ? 1 : 0, $max === 0, $str('rulesnolimitlight'));
        }
        // Back home, a rule takes the days of the general one.
        $home = (int) (self::general($library)['loandays'] ?? 14) ?: 14;
        $lights[] = self::light($form, 'loandays', $loan === 0 ? $home : 0, $loan === 0, $str('rulesroomlight'));
        $type = (string) $rule['readertype'];
        $items = array_merge(['*'], cataloguer::itemtypes((int) $library->entityid));
        return [
            'index' => $at,
            'form' => $form,
            'colour' => self::COLOURS[$type] ?? 'night',
            'who' => workings::who($library, $type, $str),
            'what' => \core_text::strtolower(workings::what((string) $rule['itemtype'], $str)),
            'says' => self::says($rule, $str),
            'steps' => $steps,
            'hassteps' => (bool) $steps,
            'lights' => $lights,
            'justsaved' => $justsaved,
            'retarget' => $str('rulesretarget'),
            'whochips' => array_map(fn($w) => ['value' => $w === '*' ? 'any' : $w,
                'label' => workings::who($library, $w, $str), 'pressed' => $w === $type], workings::readertypes($library)),
            'whatchips' => array_map(fn($w) => ['value' => $w === '*' ? 'any' : $w, 'label' => workings::what($w, $str),
                'pressed' => $w === (string) $rule['itemtype']], $items),
            'removelabel' => $str('hiwremove'),
            'removeyes' => $str('hiwremoveyes'),
        ];
    }

    /**
     * The rule for any reader and any copy, if the library has one.
     *
     * @param \stdClass $library
     * @return array|null
     */
    private static function general(\stdClass $library): ?array {
        foreach (rules::of((int) $library->id) as $one) {
            if ($one['readertype'] === '*' && $one['itemtype'] === '*' && (int) $one['loandays'] > 0) {
                return $one;
            }
        }
        return null;
    }

    /**
     * A loan rule said in one sentence: how many, how long, how many times more.
     *
     * @param array $rule
     * @param callable $str
     * @return string
     */
    private static function says(array $rule, callable $str): string {
        $days = fn(int $n) => $str($n === 1 ? 'rulesdayone' : 'rulesdaysn', $n);
        if ((int) $rule['loandays'] === 0) {
            return $str('rulesroomonly');
        }
        $max = match ((int) $rule['maxloans']) {
            0 => $str('rulesanynumber'),
            1 => $str('rulesuptoone'),
            default => $str('rulesupto', (int) $rule['maxloans']),
        };
        $renew = (int) $rule['renewals'] === 0 ? $str('rulesnorenew') : $str(
            (int) $rule['renewals'] === 1 ? 'rulesrenewonce' : 'rulesrenewtimes',
            (object) ['times' => (int) $rule['renewals'], 'days' => $days((int) $rule['renewdays'])]
        );
        return $str('rulessentence', (object) ['max' => $max, 'days' => $days((int) $rule['loandays']), 'renew' => $renew]);
    }

    /**
     * How a renewal counts: two chips.
     *
     * @param int $entityid
     * @param callable $str
     * @param bool $justsaved
     * @return array
     */
    private static function renew(int $entityid, callable $str, bool $justsaved): array {
        $from = rules::renew_from($entityid);
        return [
            'id' => 'ayv-nr-ampliar',
            'set' => 'renew',
            'title' => $str('renewfromheading'),
            'says' => $str('renewfromsentence' . $from),
            'justsaved' => $justsaved,
            'groups' => [self::group('', [], [], array_map(
                fn($value) => self::chip('ayv-nr-ampliar-f', 'renewfrom', $value, $str('renewfrom' . $value), $value === $from),
                [loan_rules::FROM_TODAY, loan_rules::FROM_DUE]
            ))],
            'notes' => [['text' => $str('renewfromdesk')], ['text' => $str('renewfromcentre')]],
            'dates' => null,
            'presets' => null,
            'currency' => null,
            'link' => null,
            'help' => null,
        ];
    }

    /**
     * When late books are told about, and to whom: the first after so many days, then a rhythm, and three lights.
     *
     * @param int $entityid
     * @param array $late
     * @param callable $str
     * @param callable $url
     * @param bool $justsaved
     * @return array
     */
    private static function notice_card(int $entityid, array $late, callable $str, callable $url, bool $justsaved): array {
        $form = 'ayv-nr-avisos-f';
        $counted = fn(int $n) => $str($n === 1 ? 'rulesdayone' : 'rulesdaysn', $n);
        $first = $late['noticeafter'] === 1 ? $str('noticesfirstnext') : $str('noticesfirstafter', $late['noticeafter']);
        $tz = zones::of($entityid);
        $year = years::around($entityid, time());
        $inyear = function (string $date) use ($year, $tz): int {
            $start = (new \DateTimeImmutable('@' . (int) $year->timestart))->setTimezone($tz);
            $day = $start->setDate((int) $start->format('Y'), (int) substr($date, 0, 2), (int) substr($date, 3, 2))
                ->setTime(12, 0);
            return ($day < $start ? $day->modify('+1 year') : $day)->getTimestamp();
        };
        $days = array_map($inyear, $late['noticedates']);
        sort($days);
        $named = array_map(fn($time) => userdate($time, get_string('strftimedateshort', 'langconfig'), $tz->getName()), $days);
        $joined = count($named) > 1 ? implode(', ', array_slice($named, 0, -1)) . $str('addand') . end($named)
            : (string) reset($named);
        $every = $late['noticeevery'];
        $says = match (true) {
            $every === notice_rules::HAND => $str('noticeseveryhand'),
            $every === notice_rules::DATES && !$named => $str('noticeseverydatesnone'),
            $every === notice_rules::DATES => $str('noticeseverydates', (object) ['dates' => $joined,
                'days' => $counted($late['noticeafter'])]),
            default => $str('noticesevery' . $every, $first),
        };
        $whom = array_values(array_filter([
            $late['noticereaders'] ? $str('noticeswhoreaders') : '',
            $late['noticefamilies'] ? $str('noticeswhofamilies') : '',
            $late['noticetutors'] ? $str('noticeswhotutors') : '',
        ]));
        $says .= ' ' . (!$whom ? $str('noticeswhonone') : $str('noticeswho', count($whom) > 1
            ? implode(', ', array_slice($whom, 0, -1)) . $str('addand') . end($whom) : $whom[0]));
        $after = $late['noticeafter'];
        $groups = [];
        if ($every !== notice_rules::HAND) {
            $groups[] = self::group($str('noticesfirstlabel'), [self::step(
                $form,
                'noticeafter',
                $str('noticesfirstlabel'),
                $after,
                1,
                90,
                1,
                (string) $after,
                $str($after === 1 ? 'noticesunitafter' : 'noticesunitafters'),
                $str
            )], [], []);
        }
        $groups[] = self::group($str('noticesthenlabel'), [], [], array_map(
            fn($rhythm) => self::chip($form, 'noticeevery', $rhythm, $str('noticesrhythm' . $rhythm), $rhythm === $every),
            notice_rules::rhythms()
        ));
        $groups[] = self::group($str('noticeswholegend'), [], [
            self::light(
                $form,
                'noticereaders',
                $late['noticereaders'] ? 0 : 1,
                $late['noticereaders'],
                $str('noticeslightreaders')
            ),
            self::light(
                $form,
                'noticefamilies',
                $late['noticefamilies'] ? 0 : 1,
                $late['noticefamilies'],
                $str('noticeslightfamilies')
            ),
            self::light($form, 'noticetutors', $late['noticetutors'] ? 0 : 1, $late['noticetutors'], $str('noticeslighttutors')),
        ], []);
        // As a date field wants them (userdate() takes the zero off the day), and room for four.
        $fields = array_map(
            fn($time) => ['value' => (new \DateTimeImmutable('@' . $time))->setTimezone($tz)->format('Y-m-d')],
            $days
        );
        while (count($fields) < 4) {
            $fields[] = ['value' => ''];
        }
        foreach ($fields as $i => $field) {
            $fields[$i]['label'] = $str('noticesdatelabel', $i + 1);
        }
        return [
            'id' => 'ayv-nr-avisos',
            'set' => 'notices',
            'title' => $str('noticesheading'),
            'says' => $says,
            'justsaved' => $justsaved,
            'groups' => $groups,
            'notes' => [],
            'dates' => $every === notice_rules::DATES ? ['fields' => $fields, 'legend' => $str('noticesdateslegend'),
                'hint' => $str('noticesdateshint'), 'save' => $str('noticesdatessave')] : null,
            'presets' => ['summary' => $str('noticespresets'), 'hint' => $str('noticespresetshint'), 'list' => array_map(
                fn($preset) => self::chip($form, 'preset', $preset, $str('noticespreset' . $preset), false),
                array_keys(notice_rules::PRESETS)
            )],
            'currency' => null,
            'link' => ['lead' => $str('rulesseelate'), 'url' => $url('late.php'), 'label' => $str('menulate')],
            'help' => null,
        ];
    }

    /**
     * What a late book leaves: three chips, and with days or a fine its counters, who and which stages.
     *
     * @param array $late
     * @param string[] $stages
     * @param bool $classes Whether the centre has stages to choose.
     * @param callable $str
     * @param callable $url
     * @param bool $justsaved
     * @return array
     */
    private static function late_card(
        array $late,
        array $stages,
        bool $classes,
        callable $str,
        callable $url,
        bool $justsaved
    ): array {
        $form = 'ayv-nr-tarde-f';
        $mode = $late['mode'];
        $money = fn(int $cents) => sanctions::money($cents, $late['currency']);
        $counted = fn(int $n) => $str($n === 1 ? 'rulesdayone' : 'rulesdaysn', $n);
        $says = $mode === sanction_rules::DAYS
            ? $str('sanctionssentencedays', (object) ['days' => $counted($late['perday']), 'max' => $counted($late['maxdays'])])
            : ($mode === sanction_rules::FINE ? $str('sanctionssentencefine', (object) ['amount' => $money($late['cents']),
            'max' => $money($late['maxcents']), 'block' => $money($late['block'])]) : $str('sanctionssentencenone'));
        $groups = [self::group('', [], [], array_map(
            fn($value) => self::chip($form, 'mode', $value, $str('sanctionsmode' . $value), $value === $mode),
            [sanction_rules::NONE, sanction_rules::DAYS, sanction_rules::FINE]
        ))];
        $notes = [];
        if ($mode !== sanction_rules::NONE) {
            if ($late['grace']) {
                $says .= ' ' . ($late['grace'] === 1 ? $str('sanctionsgraceone') : $str('sanctionsgrace', $late['grace']));
            }
            $names = implode(', ', $late['stages']);
            $says .= ' ' . ($late['stages'] ? $str($late['adults'] ? 'sanctionswhostages' : 'sanctionswhostagesonly', $names)
                : $str($late['adults'] ? 'sanctionswhoall' : 'sanctionswhostudents'));
            $daysunit = fn(int $n) => $str($n === 1 ? 'rulesunitday' : 'rulesunitdays');
            $counters = $mode === sanction_rules::DAYS ? [
                self::step(
                    $form,
                    'perday',
                    $str('sanctionsfieldperday'),
                    $late['perday'],
                    1,
                    30,
                    1,
                    (string) $late['perday'],
                    $daysunit($late['perday']),
                    $str
                ),
                self::step(
                    $form,
                    'maxdays',
                    $str('sanctionsfieldmaxdays'),
                    $late['maxdays'],
                    1,
                    365,
                    5,
                    (string) $late['maxdays'],
                    $daysunit($late['maxdays']),
                    $str
                ),
            ] : [
                self::step(
                    $form,
                    'cents',
                    $str('sanctionsfieldcents'),
                    $late['cents'],
                    5,
                    100000,
                    5,
                    $money($late['cents']),
                    '',
                    $str
                ),
                self::step(
                    $form,
                    'maxcents',
                    $str('sanctionsfieldmaxcents'),
                    $late['maxcents'],
                    50,
                    10000000,
                    50,
                    $money($late['maxcents']),
                    '',
                    $str
                ),
                self::step(
                    $form,
                    'block',
                    $str('sanctionsfieldblock'),
                    $late['block'],
                    50,
                    10000000,
                    50,
                    $money($late['block']),
                    '',
                    $str
                ),
            ];
            $counters[] = self::step(
                $form,
                'grace',
                $str('sanctionsfieldgrace'),
                $late['grace'],
                0,
                30,
                1,
                (string) $late['grace'],
                $daysunit($late['grace']),
                $str
            );
            foreach ($counters as $counter) {
                $groups[] = self::group($counter['label'], [$counter], [], []);
            }
            $groups[] = self::group($str('noticeswholegend'), [], [self::light(
                $form,
                'adults',
                $late['adults'] ? 0 : 1,
                $late['adults'],
                $str('sanctionsadults')
            )], []);
            if ($classes) {
                $groups[] = self::group($str('holdsstages'), [], [], array_merge(
                    [self::chip($form, 'stage', 'all', $str('holdsallstages'), !$late['stages'])],
                    array_map(fn($i, $name) => self::chip($form, 'stage', (string) $i, $name, in_array(
                        $name,
                        $late['stages'],
                        true
                    )), array_keys($stages), $stages)
                ));
            }
            $notes[] = ['text' => $str($mode === sanction_rules::DAYS ? 'sanctionsdayshint' : 'sanctionsfinehint')];
        }
        $notes[] = ['text' => $str('sanctionscentre')];
        $currencies = get_string_manager()->get_list_of_currencies();
        return [
            'id' => 'ayv-nr-tarde',
            'set' => 'late',
            'title' => $str('sanctionsheading'),
            'says' => $says,
            'justsaved' => $justsaved,
            'groups' => $groups,
            'notes' => $notes,
            'dates' => null,
            'presets' => null,
            'currency' => $mode === sanction_rules::FINE ? ['summary' => $str('sanctionscurrencynow', $late['currency']),
                'label' => $str('sanctionsfieldcurrency'), 'save' => $str('sourcessave'), 'list' => array_map(
                    fn($code, $name) => ['code' => $code, 'name' => $name],
                    array_keys($currencies),
                    array_values($currencies)
                )] : null,
            'link' => ['lead' => $str('rulesseesanctions'), 'url' => $url('sanctions.php'), 'label' => $str('menusanctions')],
        ];
    }

    /**
     * A group of gestures under a label: counters, lights or chips.
     *
     * @param string $label
     * @param array[] $steps
     * @param array[] $lights
     * @param array[] $chips
     * @return array
     */
    private static function group(string $label, array $steps, array $lights, array $chips): array {
        // Chips take a row of their own: one choice among several reads better on its own line than beside counters.
        return ['label' => $label, 'steps' => $steps, 'lights' => $lights, 'chips' => $chips, 'haslights' => (bool) $lights,
            'haschips' => (bool) $chips, 'whole' => (bool) $chips];
    }

    /**
     * A number with its «−» and «+».
     *
     * @param string $form
     * @param string $field
     * @param string $label
     * @param int $value
     * @param int $min
     * @param int $max
     * @param int $by How much each sign moves it.
     * @param string $shown The number as it is read.
     * @param string $unit What it counts, under it.
     * @param callable $str
     * @return array
     */
    private static function step(
        string $form,
        string $field,
        string $label,
        int $value,
        int $min,
        int $max,
        int $by,
        string $shown,
        string $unit,
        callable $str
    ): array {
        return ['form' => $form, 'field' => $field, 'label' => $label, 'shown' => $shown, 'unit' => $unit,
            'less' => max($min, $value - $by), 'more' => min($max, $value + $by), 'atmin' => $value <= $min,
            'atmax' => $value >= $max, 'lesslabel' => $str('hiwless') . ': ' . $label,
            'morelabel' => $str('hiwmore') . ': ' . $label, 'still' => false];
    }

    /**
     * A figure that is not counted with «−» and «+» (no limit), in the place of one.
     *
     * @param string $label
     * @param string $shown
     * @return array
     */
    private static function still(string $label, string $shown): array {
        return ['form' => '', 'field' => '', 'label' => $label, 'shown' => $shown, 'unit' => '', 'less' => 0, 'more' => 0,
            'atmin' => true, 'atmax' => true, 'lesslabel' => '', 'morelabel' => '', 'still' => true];
    }

    /**
     * A lamp: sends what it turns to.
     *
     * @param string $form
     * @param string $name
     * @param int $value
     * @param bool $on
     * @param string $label
     * @return array
     */
    private static function light(string $form, string $name, int $value, bool $on, string $label): array {
        return ['form' => $form, 'name' => $name, 'value' => $value, 'pressed' => $on, 'label' => $label];
    }

    /**
     * A chip: sends its value.
     *
     * @param string $form
     * @param string $name
     * @param string $value
     * @param string $label
     * @param bool $on
     * @return array
     */
    private static function chip(string $form, string $name, string $value, string $label, bool $on): array {
        return ['form' => $form, 'name' => $name, 'value' => $value, 'label' => $label, 'pressed' => $on];
    }
}
