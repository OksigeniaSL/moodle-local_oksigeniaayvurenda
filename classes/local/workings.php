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
 * «How your library works»: the centre's settings and the library's loan rules read as sentences, each changed with a
 * gesture where it is said, and saved at once.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

use local_oksigeniaayvurenda\core\card_look;

/**
 * The settings window was a long form nobody read to the end: here each setting says in one sentence what happens today,
 * with the values in bold, on three shelves (day to day, the library's voice, the house). Touched, its gesture opens under
 * it: chips, a − and a +, a light, a sign on the door. Each gesture is one button that sends only what it changes, so it
 * works without JavaScript and nothing else is touched.
 */
final class workings {
    /** @var string[] The lights, in the order they are said. */
    public const LIGHTS = ['visit', 'games', 'voices', 'sound', 'figures'];

    /** @var string[] The small drawing of each card, by look. */
    private const MINIS = ['flower' => 'flor', 'sky' => 'cielo', 'skysober' => 'sobrio'];

    /** @var array The spine of a sample code and the label a class gives, by classification. */
    private const SAMPLES = ['udc' => '82-3 ROW', 'dewey' => '823 ROW', 'other' => 'N ROW'];

    /** @var string[] The colours of the spines, by shelf and kind. */
    private const SPINES = ['rule' => 'green', 'door' => 'coral', 'lights' => 'sun', 'route' => 'green2', 'postcard' => 'coral',
        'diploma' => 'sun', 'booklet' => 'sun', 'forlittle' => 'green2', 'sign' => 'coral', 'challenges' => 'sun',
        'card' => 'green2', 'history' => 'green', 'photos' => 'coral', 'skin' => 'night', 'name' => 'green', 'year' => 'coral',
        'classification' => 'sun', 'letter' => 'green2', 'zone' => 'night'];

    /**
     * Applies what a gesture sent, and says where the page goes back to.
     *
     * @param \stdClass $library
     * @return string The anchor of the sentence touched.
     */
    public static function apply(\stdClass $library): string {
        $entityid = (int) $library->entityid;
        $rule = optional_param('rule', '', PARAM_ALPHANUM);
        if ($rule !== '') {
            return self::apply_rule($library, $rule);
        }
        $door = optional_param('door', -1, PARAM_INT);
        if ($door !== -1) {
            access::set_closed($entityid, !$door);
            return 'ayv-hiw-door';
        }
        $play = optional_param_array('play', [], PARAM_INT);
        if ($play) {
            $on = skins::play($entityid);
            foreach ($play as $what => $value) {
                if (array_key_exists($what, $on)) {
                    $on[$what] = (bool) $value;
                }
            }
            libraries::update_entity($entityid, ['play' => array_keys(array_filter($on))]);
            return 'ayv-hiw-lights';
        }
        // The welcome route (P8): its light, and the stages it is for (none chosen: all but Infantil).
        $route = optional_param('orientation', -1, PARAM_INT);
        $routestages = optional_param_array('orientationstage', [], PARAM_INT);
        if ($route !== -1 || $routestages) {
            $now = orientation::settings($entityid);
            $known = array_values(array_filter(holds::stages($entityid, time()), fn($s) => !cartilla::infant($s)));
            $for = $now['stages'] ?: $known;
            foreach ($routestages as $index => $value) {
                $stage = $known[(int) $index] ?? null;
                if ($stage !== null) {
                    $for = $value ? array_values(array_unique(array_merge($for, [$stage])))
                        : array_values(array_diff($for, [$stage]));
                }
            }
            orientation::save($entityid, $route !== -1 ? (bool) $route : $now['on'], $for);
            return 'ayv-hiw-orientation';
        }
        // The postcard of the first book, to the family (the first «subidón»).
        $firstbook = optional_param('firstbook', -1, PARAM_INT);
        if ($firstbook !== -1) {
            firstbook::save($entityid, (bool) $firstbook);
            return 'ayv-hiw-firstbook';
        }
        // What a reader read for the little ones, to their family.
        $readforlittle = optional_param('readforlittle', -1, PARAM_INT);
        if ($readforlittle !== -1) {
            forlittle::save($entityid, (bool) $readforlittle);
            return 'ayv-hiw-forlittle';
        }
        // The diploma of a full bingo card, to the family.
        $diploma = optional_param('diploma', -1, PARAM_INT);
        if ($diploma !== -1) {
            diploma::save($entityid, (bool) $diploma);
            return 'ayv-hiw-diploma';
        }
        // The reader's booklet, to the family, and the library's two lines in it.
        $booklet = optional_param('booklet', -1, PARAM_INT);
        $lines = optional_param_array('set', [], PARAM_RAW_TRIMMED);
        if ($booklet !== -1 || isset($lines['bookletline']) || isset($lines['bookletfarewell'])) {
            if ($booklet !== -1) {
                booklet::save($entityid, (bool) $booklet);
            }
            if (isset($lines['bookletline'])) {
                booklet::save_line($entityid, (int) years::around($entityid, time())->id, (string) $lines['bookletline']);
            }
            if (isset($lines['bookletfarewell'])) {
                booklet::save_farewell($entityid, (string) $lines['bookletfarewell']);
            }
            return 'ayv-hiw-booklet';
        }
        $stages = holds::stages($entityid, time());
        $challenge = optional_param_array('challenge', [], PARAM_INT);
        if ($challenge) {
            $off = challenges::stages_off($entityid);
            foreach ($challenge as $index => $value) {
                $stage = $stages[(int) $index] ?? null;
                if ($stage !== null) {
                    $off = $value ? array_values(array_diff($off, [$stage]))
                        : array_values(array_unique(array_merge($off, [$stage])));
                }
            }
            libraries::update_entity($entityid, ['challengesoff' => $off]);
            return 'ayv-hiw-challenges';
        }
        $look = optional_param_array('cardlook', [], PARAM_ALPHA);
        if ($look) {
            $looks = self::settings($entityid)['cardlooks'] ?? [];
            foreach ($look as $index => $value) {
                $stage = $stages[(int) $index] ?? null;
                if ($stage === null) {
                    continue;
                }
                if (in_array($value, card_look::LOOKS, true)) {
                    $looks[$stage] = $value;
                } else {
                    unset($looks[$stage]);
                }
            }
            libraries::update_entity($entityid, ['cardlooks' => $looks]);
            return 'ayv-hiw-card';
        }
        $set = optional_param_array('set', [], PARAM_RAW_TRIMMED);
        $data = [];
        foreach (['skin', 'reviewsign', 'classification', 'timezone'] as $key) {
            if (isset($set[$key])) {
                $data[$key] = clean_param($set[$key], $key === 'timezone' ? PARAM_TIMEZONE : PARAM_ALPHA);
            }
        }
        foreach (['historyyears', 'controlletter', 'photos'] as $key) {
            if (isset($set[$key])) {
                $data[$key] = (int) $set[$key];
            }
        }
        if (isset($set['name']) && trim($set['name']) !== '') {
            $data['name'] = clean_param($set['name'], PARAM_TEXT);
        }
        if (isset($set['startmonth']) || isset($set['startday'])) {
            $year = self::yearstart($entityid);
            $month = max(1, min(12, (int) ($set['startmonth'] ?? $year[0])));
            $day = max(1, min((int) date('t', mktime(12, 0, 0, $month, 1, 2001)), (int) ($set['startday'] ?? $year[1])));
            $data['yearstart'] = sprintf('%02d-%02d', $month, $day);
        }
        if ($data) {
            libraries::update_entity($entityid, $data);
        }
        $anchors = ['skin' => 'skin', 'reviewsign' => 'sign', 'classification' => 'classification', 'timezone' => 'zone',
            'historyyears' => 'history', 'controlletter' => 'letter', 'photos' => 'photos', 'name' => 'name',
            'yearstart' => 'year'];
        return 'ayv-hiw-' . ($anchors[array_key_first($data) ?? ''] ?? 'top');
    }

    /**
     * Changes, adds or takes away a loan rule of the library, and says where the page goes back to.
     *
     * @param \stdClass $library
     * @param string $index The rule's place, or «new».
     * @return string
     */
    private static function apply_rule(\stdClass $library, string $index): string {
        $place = self::change_rule($library, $index);
        return $place === null ? 'ayv-hiw-daily' : 'ayv-hiw-rule-' . $place;
    }

    /**
     * Changes, adds or takes away a loan rule of the library: only the part touched. A new one is for the readers and
     * copies sent («any», or nothing, is any), and starts as the rule a reader of any kind has here; if there is one for
     * them already, that one is the one touched.
     *
     * @param \stdClass $library
     * @param string $index The rule's place, or «new».
     * @return int|null Where the rule touched is now, or null when it is gone or was not there.
     */
    public static function change_rule(\stdClass $library, string $index): ?int {
        $all = rules::of((int) $library->id);
        $type = fn(string $name, string $was) => match ($sent = optional_param($name, '', PARAM_ALPHANUMEXT)) {
            '' => $was,
            'any' => '*',
            default => $sent,
        };
        $find = function (string $who, string $what) use ($library): ?int {
            foreach (rules::of((int) $library->id) as $at => $one) {
                if ($one['readertype'] === $who && $one['itemtype'] === $what) {
                    return $at;
                }
            }
            return null;
        };
        if ($index === 'new') {
            $who = $type('readertype', '*');
            $what = $type('itemtype', '*');
            $there = $find($who, $what);
            if ($there !== null) {
                return $there;
            }
            // It starts as the rule a reader of any kind has here, and is changed in its place.
            $all[] = ['readertype' => $who, 'itemtype' => $what] + self::rule_for($library, $all);
            rules::save((int) $library->id, $all);
            return $find($who, $what);
        }
        $at = (int) $index;
        if (!isset($all[$at])) {
            return null;
        }
        if (optional_param('remove', 0, PARAM_BOOL)) {
            unset($all[$at]);
            rules::save((int) $library->id, array_values($all));
            return null;
        }
        $rule = $all[$at];
        $who = $type('readertype', $rule['readertype']);
        $what = $type('itemtype', $rule['itemtype']);
        if ($who !== $rule['readertype'] || $what !== $rule['itemtype']) {
            // Two rules for the same readers and copies cannot be: the other one stays, this one does not change.
            $there = $find($who, $what);
            if ($there !== null) {
                return $at;
            }
            $rule['readertype'] = $who;
            $rule['itemtype'] = $what;
        }
        foreach (
            ['loandays' => [0, 999], 'maxloans' => [0, 99], 'renewals' => [0, 20], 'renewdays' => [1,
            999]] as $field => $range
        ) {
            $value = optional_param($field, -1, PARAM_INT);
            if ($value !== -1) {
                $rule[$field] = max($range[0], min($range[1], $value));
            }
        }
        $all[$at] = $rule;
        rules::save((int) $library->id, $all);
        return $find($rule['readertype'], $rule['itemtype']);
    }

    /**
     * What a new rule starts with: the library's rule for any reader, or its first, or the usual one.
     *
     * @param \stdClass $library
     * @param array[] $all
     * @return array loandays, maxloans, renewals, renewdays.
     */
    private static function rule_for(\stdClass $library, array $all): array {
        $pick = null;
        foreach ($all as $one) {
            if ($one['readertype'] === '*' && $one['itemtype'] === '*') {
                $pick = $one;
            }
        }
        $pick ??= $all[0] ?? ['loandays' => 14, 'maxloans' => 2, 'renewals' => 1, 'renewdays' => 7];
        return ['loandays' => (int) $pick['loandays'] ?: 14, 'maxloans' => (int) $pick['maxloans'],
            'renewals' => (int) $pick['renewals'], 'renewdays' => (int) $pick['renewdays'] ?: 7];
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
     * The month and day the centre's year starts.
     *
     * @param int $entityid
     * @return int[] [month, day].
     */
    private static function yearstart(int $entityid): array {
        global $DB;
        $start = (string) $DB->get_field('local_oksigeniaayvurenda_entity', 'yearstart', ['id' => $entityid]);
        return preg_match('/^(\d{2})-(\d{2})$/', $start, $m) ? [(int) $m[1], (int) $m[2]] : [9, 1];
    }

    /**
     * What the page shows.
     *
     * @param \stdClass $library
     * @param string $action Where the gestures go.
     * @param string $saved The sentence just changed, which says so.
     * @return array
     */
    public static function context(\stdClass $library, string $action, string $saved = ''): array {
        global $DB;
        $entityid = (int) $library->entityid;
        $entity = $DB->get_record('local_oksigeniaayvurenda_entity', ['id' => $entityid], '*', MUST_EXIST);
        $settings = self::settings($entityid);
        $str = skins::speaker($library);
        $b = fn(string $text) => '<b>' . s($text) . '</b>';
        $chip = fn(string $name, string $value, string $label, bool $on) => ['name' => $name, 'value' => $value,
            'label' => $label, 'pressed' => $on];
        $phrase = fn(string $id, string $kind, string $spine, string $says, array $gesture = []) => ['id' => 'ayv-hiw-' . $id,
            'kind' => $kind, 'spine' => $spine, 'spinecolour' => self::SPINES[$kind] ?? 'green', 'says' => $says,
            'justsaved' => $saved === 'ayv-hiw-' . $id] + $gesture + ['link' => null];
        $stages = holds::stages($entityid, time());
        $classes = !skins::no_classes($entityid) && $stages;

        // The day to day: the rules of this library, the door, the lights.
        $daily = [];
        foreach (rules::of((int) $library->id) as $at => $rule) {
            $daily[] = $phrase(
                'rule-' . $at,
                'rule',
                $str('hiwspinerule'),
                self::rule_says($library, $rule, $str, $b),
                ['rule' => self::rule_gesture($library, (string) $at, $rule, $str)]
            );
        }
        $daily[] = $phrase('rule-new', 'rule', '+', s($str('hiwnewrule')), ['newrule' => [
            'who' => array_map(
                fn($who) => $chip('readertype', $who === '*' ? 'any' : $who, self::who($library, $who, $str), false),
                self::readertypes($library)
            ),
            'note' => $str('hiwnewrulenote'),
        ], 'isnew' => true]);
        $closed = access::closed($entityid);
        $daily[] = $phrase('door', 'door', $str('hiwspinedoor'), $str($closed ? 'hiwdoorclosed' : 'hiwdooropen', $b($str(
            $closed ? 'hiwdoorclosedbold' : 'hiwdooropenbold'
        ))), ['door' => ['open' => !$closed, 'value' => $closed ? 1 : 0, 'face' => $str('hiwdooropenword'),
            'back' => $str('hiwdoorclosedword'), 'explain' => $str('hiwdoorexplain')]]);
        $play = skins::play($entityid);
        $lit = array_values(array_filter(self::LIGHTS, fn($what) => !empty($play[$what])));
        $dark = array_values(array_filter(self::LIGHTS, fn($what) => isset($play[$what]) && empty($play[$what])));
        $names = fn(array $list) => self::join(array_map(fn($what) => $b($str('hiwlight' . $what)), $list), $str);
        $daily[] = $phrase('lights', 'lights', $str('hiwspinelights'), !$lit ? $str('hiwlightsnone')
            : ($dark ? $str('hiwlightssome', (object) ['on' => $names($lit), 'off' => $names($dark)])
            : $str('hiwlightsall', $names($lit))), ['lights' => [
                'list' => array_map(fn($what) => $chip(
                    'play[' . $what . ']',
                    !empty($play[$what]) ? '0' : '1',
                    $str('hiwlightlabel' . $what),
                    !empty($play[$what])
                ), array_values(array_filter(
                    self::LIGHTS,
                    fn($what) => array_key_exists($what, $play)
                ))),
                'note' => $str('hiwlightsnote'),
            ]]);
        // The welcome route, behind the lights it needs (Jujol, 13 November).
        $route = orientation::settings($entityid);
        $needs = empty($play['visit']) || empty($play['games']);
        $grown = array_values(array_filter($stages, fn($stage) => !cartilla::infant($stage)));
        $routefor = $route['stages'] ?: $grown;
        $routeon = $route['on'] && !$needs;
        $daily[] = $phrase('orientation', 'route', $str('hiwspineroute'), !$routeon ? $str('hiworientationoff')
            : ($routefor ? $str('hiworientationon', self::join(array_map('s', $routefor), $str)) : $str('hiworientationonall')), [
            'orientation' => [
                'img' => orientation::art('sky'),
                'light' => $chip('orientation', $route['on'] ? '0' : '1', $str('hiworientationlight'), $routeon)
                    + ['disabled' => $needs],
                'stages' => $needs ? [] : array_map(
                    fn($stage, $i) => $chip(
                        'orientationstage[' . $i . ']',
                        in_array($stage, $routefor, true) ? '0' : '1',
                        $stage,
                        in_array($stage, $routefor, true)
                    ),
                    $grown,
                    array_keys($grown)
                ),
                'hasstages' => !$needs && $grown,
                'note' => $str($needs ? 'hiworientationneeds' : 'hiworientationnote'),
            ],
        ]);

        // The library's voice: signing, the class challenges, the card, the history, the photo.
        $voice = [];
        $sign = reviews::sign($entityid);
        $low = fn(string $text) => \core_text::strtolower(\core_text::substr($text, 0, 1)) . \core_text::substr($text, 1);
        $signed = $b($low($str('reviewsign' . $sign)));
        $voice[] = $phrase('sign', 'sign', $str('hiwspinesign'), $str('hiwsign', $signed), ['reviews' => [
            'list' => array_map(fn($s) => $chip('set[reviewsign]', $s, $str('reviewsign' . $s), $s === $sign) + [
                'text' => $str('hiwsignsample'), 'signature' => $str(skins::words($entityid, 'hiwsignby' . $s))], reviews::SIGNS),
        ]]);
        // The postcard of the first book, to the family of a young reader (the first «subidón»; Jujol, 13 November).
        $postcard = firstbook::on($entityid);
        $voice[] = $phrase('firstbook', 'postcard', $str('hiwspinepostcard'), $str(
            $postcard ? 'hiwfirstbookon' : 'hiwfirstbookoff'
        ), [
            'firstbook' => [
                // Gaudí's postcard itself, small, with example data (Jujol); the drawn one without the art package.
                'postcard' => postcard::sample($library),
                'cover' => screens::art('portadas/libro-abuela'),
                'sampletitle' => $str('hiwfirstbooksampletitle'),
                'sampleline' => $str('hiwfirstbooksampleline'),
                'light' => $chip('firstbook', $postcard ? '0' : '1', $str('hiwfirstbooklight'), $postcard),
                'note' => $str('hiwfirstbooknote'),
            ],
        ]);
        // What an older reader read for the little ones: the family of whoever wants it hears it, if the centre wants.
        if (plans::allows($entityid, 'voices')) {
            $told = forlittle::on($entityid);
            $voice[] = $phrase('forlittle', 'forlittle', $str('hiwspineforlittle'), $str(
                $told ? 'hiwforlittleon' : 'hiwforlittleoff'
            ), [
                'lights' => [
                    'list' => [$chip('readforlittle', $told ? '0' : '1', $str('hiwforlittlelight'), $told)],
                    'note' => $str('hiwforlittlenote'),
                ],
            ]);
        }
        // The diploma of a full bingo card: every reader downloads theirs; the family of a young one, if the centre wants.
        if (diploma::drawable()) {
            $diploma = diploma::on($entityid);
            $voice[] = $phrase('diploma', 'diploma', $str('hiwspinediploma'), $str(
                $diploma ? 'hiwdiplomaon' : 'hiwdiplomaoff'
            ), [
                'diploma' => [
                    'img' => diploma::sample($library),
                    'light' => $chip('diploma', $diploma ? '0' : '1', $str('hiwdiplomalight'), $diploma),
                    'note' => $str('hiwdiplomanote'),
                ],
            ]);
        }
        // The reader's booklet: each reader's at the end of the year; to their family, if the centre turns it on (Jujol).
        $sends = booklet::on($entityid);
        $voice[] = $phrase('booklet', 'booklet', $str('hiwspinebooklet'), $str($sends ? 'hiwbookleton' : 'hiwbookletoff'), [
            'booklet' => [
                'img' => bookletview::sample($library),
                'light' => $chip('booklet', $sends ? '0' : '1', $str('hiwbookletlight'), $sends),
                'line' => booklet::line($entityid, (int) years::around($entityid, time())->id),
                'farewell' => booklet::farewell($entityid),
                'max' => booklet::LINE,
                'note' => $str('hiwbookletnote'),
            ],
        ]);
        if ($classes) {
            $off = challenges::stages_off($entityid);
            $on = array_values(array_diff($stages, $off));
            $voice[] = $phrase('challenges', 'challenges', $str('hiwspinechallenges'), !$on ? $str('hiwchallengesnone')
                : (array_intersect($stages, $off) ? $str('hiwchallengessome', (object) [
                    'on' => self::join(array_map($b, $on), $str),
                    'off' => self::join(array_map($b, array_values(array_intersect($stages, $off))), $str),
                ]) : $str('hiwchallengesall')), ['lights' => ['list' => array_map(
                    fn($stage, $i) => $chip(
                        'challenge[' . $i . ']',
                        in_array($stage, $off, true) ? '1' : '0',
                        $stage,
                        !in_array($stage, $off, true)
                    ),
                    $stages,
                    array_keys($stages)
                ), 'note' => '']]);
            $looks = $settings['cardlooks'] ?? [];
            $unknown = skins::unknown_look($entityid);
            $bylook = [];
            $rows = [];
            foreach ($stages as $i => $stage) {
                $auto = card_look::guess('student', $stage, '', $unknown);
                $chosen = $looks[$stage] ?? '';
                $bylook[$chosen !== '' ? $chosen : $auto][] = $stage;
                $rows[] = ['stage' => $stage, 'chips' => array_merge(
                    [$chip('cardlook[' . $i . ']', 'auto', $str('cardlookauto', $str('cardlookopt' . $auto)), $chosen === '')],
                    array_map(
                        fn($l) => $chip('cardlook[' . $i . ']', $l, $str('cardlookopt' . $l), $chosen === $l)
                            + ['mini' => self::MINIS[$l] ?? ''],
                        card_look::LOOKS
                    )
                )];
            }
            $parts = [];
            foreach ($bylook as $look => $list) {
                $parts[] = $str('hiwcardpart', (object) ['look' => $b($str('hiwlook' . $look)),
                    'stages' => self::join(array_map('s', $list), $str)]);
            }
            $voice[] = $phrase('card', 'card', $str('hiwspinecard'), $str('hiwcard', implode('; ', $parts)), ['card' => [
                'rows' => $rows,
            ]]);
        }
        $years = forgetting::years($entityid);
        $span = fn(int $n) => $str(skins::words($entityid, 'historyyears' . $n));
        $voice[] = $phrase('history', 'history', $str('hiwspinehistory'), $years === 0 ? $str('hiwhistoryalways')
            : $str('hiwhistory', $b(\core_text::strtolower($span($years)))), ['chips' => [
                'list' => array_map(
                    fn($n) => $chip('set[historyyears]', (string) $n, $span($n), $n === $years),
                    forgetting::CHOICES
                ),
            ]]);
        $photos = profile::photos_on($entityid);
        $voice[] = $phrase('photos', 'photos', $str('hiwspinephotos'), $photos ? $str('hiwphotoson', $b($str('hiwphotosbold')))
            : $str('hiwphotosoff'), ['lights' => ['list' => [$chip(
                'set[photos]',
                $photos ? '0' : '1',
                $str('hiwphotoslight'),
                $photos
            )], 'note' => '']]);

        // The house: the kind of centre, its name, the year, the classification, the letter, the clock.
        $house = [];
        $skin = skins::of($entityid);
        $says = $str('hiwskin', (object) ['name' => $b($str('hiwskinname' . $skin)), 'what' => s($str('hiwskinwhat' . $skin))]);
        $house[] = $phrase('skin', 'skin', $str('hiwspineskin'), $says, ['skins' => [
            'list' => array_map(fn($s) => $chip('set[skin]', $s, $str('skin' . $s), $s === $skin) + [
                'what' => \core_text::strtoupper(\core_text::substr($str('hiwskinwhat' . $s), 0, 1))
                    . \core_text::substr($str('hiwskinwhat' . $s), 1)], skins::SKINS),
            'note' => $str('hiwskinnote'),
        ]]);
        $house[] = $phrase('name', 'name', $str('hiwspinename'), $str('hiwname', $b(format_string(
            $entity->name,
            true,
            ['escape' => false]
        ))), ['name' => ['value' => $entity->name, 'label' => $str('hiwnamelabel'), 'save' => $str('hiwsave')]]);
        [$month, $day] = self::yearstart($entityid);
        $date = $str('hiwdaymonth', (object) ['day' => $day, 'month' => userdate(make_timestamp(2001, $month, 15, 12), '%B')]);
        $house[] = $phrase('year', 'year', $str('hiwspineyear'), $str(skins::words($entityid, 'hiwyear'), $b($date)), ['year' => [
            'months' => array_map(fn($m) => $chip(
                'set[startmonth]',
                (string) $m,
                userdate(make_timestamp(2001, $m, 15, 12), '%B'),
                $m === $month
            ), range(1, 12)),
            'day' => $day, 'less' => max(1, $day - 1), 'more' => min(31, $day + 1), 'atfirst' => $day <= 1, 'atlast' => $day >= 31,
            'monthshort' => \core_text::strtolower(userdate(make_timestamp(2001, $month, 15, 12), '%b')),
            'daylabel' => $str('hiwyearday'), 'monthlabel' => $str('hiwyearmonth'),
            'sheet' => (new \moodle_url('/local/oksigeniaayvurenda/year.php', ['library' => $library->slug]))->out(false),
            'sheetlabel' => $str('hiwyearsheet'),
        ], 'link' => [
            'url' => (new \moodle_url('/local/oksigeniaayvurenda/year.php', ['library' => $library->slug]))->out(false),
            'label' => $str(skins::words($entityid, 'hiwyearsheetof'), years::around($entityid, time())->name),
        ]]);
        $classification = (string) ($settings['classification'] ?? 'udc');
        $house[] = $phrase('classification', 'classification', $str('hiwspineclass'), $str('hiwclass', (object) [
            'name' => $b($str('classification' . $classification)), 'sample' => self::SAMPLES[$classification] ?? 'N ROW',
        ]), ['spines' => ['list' => array_map(
            fn($c) => $chip(
                'set[classification]',
                $c,
                $str('classification' . $c),
                $c === $classification
            ) + ['code' => explode(' ', self::SAMPLES[$c])[0], 'mark' => explode(' ', self::SAMPLES[$c])[1]],
            array_keys(self::SAMPLES)
        )]]);
        $letter = !empty($settings['controlletter']);
        $sample = \local_oksigeniaayvurenda\core\control_letter::append('100234');
        $house[] = $phrase('letter', 'letter', $str('hiwspineletter'), $letter ? $str('hiwletteron', (object) [
            'bold' => $b($str('hiwletterbold')), 'code' => s(substr($sample, 0, -1)) . '<b>' . s(substr($sample, -1)) . '</b>'])
            : $str('hiwletteroff', '100234'), ['letter' => ['light' => $chip(
                'set[controlletter]',
                $letter ? '0' : '1',
                $str('hiwletterlight'),
                $letter
            ), 'code' => substr($sample, 0, -1), 'mark' => $letter ? substr($sample, -1) : '',
                'note' => $str('hiwletternote')]]);
        $zones = zones::choices($entityid);
        $mine = array_values(array_filter($zones, fn($z) => $z['selected']))[0] ?? ['value' => '', 'label' => ''];
        $site = \core_date::get_localised_timezone(\core_date::get_server_timezone());
        $says = $mine['value'] === '' ? $str('hiwzonesite', $b($site)) : $str('hiwzone', $b($mine['label']));
        $house[] = $phrase('zone', 'zone', $str('hiwspinezone'), $says, ['zone' => [
            'options' => $zones, 'label' => $str('hiwzonelabel'), 'save' => $str('hiwsave'),
        ]]);

        // The herald reads while the page is still, and celebrates a moment when a gesture was saved.
        $pose = $saved !== '' ? 'celebra' : 'lee';
        $crier = characters::whole($entityid) ? characters::figure('herald', $pose, characters::step($entityid)) : null;
        return [
            'title' => $str('hiwtitle'),
            'intro' => $str('hiwintro'),
            'action' => $action,
            'sesskey' => sesskey(),
            'change' => $str('hiwchange'),
            'close' => $str('hiwclose'),
            'lesslabel' => $str('hiwless'),
            'morelabel' => $str('hiwmore'),
            'savedword' => $str('hiwsaved'),
            'crier' => $crier ? ['figure' => $crier, 'puppet' => characters::puppet('herald', $pose, characters::step($entityid))]
                : null,
            'shelves' => [
                ['id' => 'ayv-hiw-daily', 'title' => $str('hiwdaily'), 'sub' => $str('hiwdailysub'), 'colour' => 'green',
                    'phrases' => $daily],
                ['id' => 'ayv-hiw-voice', 'title' => $str('hiwvoice'), 'sub' => $str('hiwvoicesub'), 'colour' => 'coral',
                    'phrases' => $voice],
                ['id' => 'ayv-hiw-house', 'title' => $str('hiwhouse'), 'sub' => $str('hiwhousesub'), 'colour' => 'night',
                    'phrases' => $house],
            ],
            'elsewhere' => $str('hiwelsewhere'),
            'elsewhereurl' => (new \moodle_url('/local/oksigeniaayvurenda/libraries.php'))->out(false),
            'elsewherelabel' => $str('hiwelsewherelink'),
        ];
    }

    /**
     * A loan rule said in one sentence: who, what, and the rule.
     *
     * @param \stdClass $library
     * @param array $rule
     * @param callable $str
     * @param callable $b
     * @return string HTML.
     */
    private static function rule_says(\stdClass $library, array $rule, callable $str, callable $b): string {
        $days = fn(int $n) => $str($n === 1 ? 'rulesdayone' : 'rulesdaysn', $n);
        $who = $b(self::who($library, (string) $rule['readertype'], $str)) . ' · '
            . s(\core_text::strtolower(self::what((string) $rule['itemtype'], $str))) . ': ';
        $low = fn(string $text) => \core_text::strtolower(\core_text::substr($text, 0, 1)) . \core_text::substr($text, 1);
        if ((int) $rule['loandays'] === 0) {
            return $who . s($low($str('rulesroomonly')));
        }
        // One is «one at a time», not «up to 1 at a time».
        $max = match ((int) $rule['maxloans']) {
            0 => $b($str('rulesanynumber')),
            1 => $b($str('rulesuptoone')),
            default => $b($str('rulesupto', (int) $rule['maxloans'])),
        };
        $renew = (int) $rule['renewals'] === 0 ? s($str('rulesnorenew')) : $str(
            (int) $rule['renewals'] === 1 ? 'rulesrenewonce' : 'rulesrenewtimes',
            (object) ['times' => '<b>' . (int) $rule['renewals'] . '</b>', 'days' => s($days((int) $rule['renewdays']))]
        );
        return $who . $low($str('rulessentence', (object) ['max' => $max, 'days' => $b($days((int) $rule['loandays'])),
            'renew' => $renew]));
    }

    /**
     * The gesture of a rule: who and what in chips, the figures with − and +, and taking it away.
     *
     * @param \stdClass $library
     * @param string $at
     * @param array $rule
     * @param callable $str
     * @return array
     */
    private static function rule_gesture(\stdClass $library, string $at, array $rule, callable $str): array {
        $chip = fn(string $name, string $value, string $label, bool $on) => ['name' => $name, 'value' => $value,
            'label' => $label, 'pressed' => $on];
        $step = fn(string $field, int $value, int $min, int $max, string $label, string $unit) => ['field' => $field,
            'label' => $label, 'value' => $value, 'unit' => $unit, 'less' => max($min, $value - 1), 'more' => min($max, $value + 1),
            'atmin' => $value <= $min, 'atmax' => $value >= $max];
        $loan = (int) $rule['loandays'];
        $max = (int) $rule['maxloans'];
        $items = array_merge(['*'], cataloguer::itemtypes((int) $library->entityid));
        return [
            'index' => $at,
            'who' => array_map(fn($w) => $chip(
                'readertype',
                $w === '*' ? 'any' : $w,
                self::who($library, $w, $str),
                $w === $rule['readertype']
            ), self::readertypes($library)),
            'what' => array_map(fn($w) => $chip(
                'itemtype',
                $w === '*' ? 'any' : $w,
                self::what($w, $str),
                $w === $rule['itemtype']
            ), $items),
            'lent' => $loan > 0,
            'steps' => $loan > 0 ? [
                $step('loandays', $loan, 1, 999, $str('hiwdays'), $str($loan === 1 ? 'rulesdayone' : 'rulesdaysn', $loan)),
                $step('maxloans', max(1, $max), 1, 99, $str('hiwatonce'), $max === 0 ? $str('rulesanynumber')
                    : $str($max === 1 ? 'hiwbookone' : 'hiwbooks', $max)) + ['nolimit' => $chip(
                        'maxloans',
                        '0',
                        $str('hiwnolimit'),
                        $max === 0
                    ), 'unlimited' => $max === 0],
                $step('renewals', (int) $rule['renewals'], 0, 20, $str('hiwrenewals'), $str(
                    (int) $rule['renewals'] === 1 ? 'hiwtimesone' : 'hiwtimes',
                    (int) $rule['renewals']
                )),
                $step('renewdays', (int) $rule['renewdays'] ?: 7, 1, 999, $str('hiwrenewdays'), $str(
                    (int) $rule['renewdays'] === 1 ? 'rulesdayone' : 'rulesdaysn',
                    (int) $rule['renewdays'] ?: 7
                )),
            ] : [],
            'home' => [$chip('loandays', $loan > 0 ? (string) $loan : '14', $str('hiwlent'), $loan > 0),
                $chip('loandays', '0', $str('hiwroomonly'), $loan === 0)],
            'removelabel' => $str('hiwremove'),
            'removeyes' => $str('hiwremoveyes'),
            'note' => $str('hiwrulenote'),
        ];
    }

    /**
     * The kinds of reader a rule may be for here.
     *
     * @param \stdClass $library
     * @return string[]
     */
    public static function readertypes(\stdClass $library): array {
        global $DB;
        $known = $DB->get_fieldset_sql(
            'SELECT DISTINCT readertype FROM {local_oksigeniaayvurenda_reader} WHERE entityid = ?',
            [$library->entityid]
        );
        return array_values(array_unique(array_merge(
            skins::no_courses((int) $library->entityid) ? ['*', 'student', 'staff'] : ['*', 'student', 'teacher', 'staff'],
            array_filter(array_map('strval', $known), fn($t) => $t !== '' && $t !== ill::READERTYPE)
        )));
    }

    /**
     * The name of a kind of reader.
     *
     * @param \stdClass $library
     * @param string $type
     * @param callable $str
     * @return string
     */
    public static function who(\stdClass $library, string $type, callable $str): string {
        if ($type === '*') {
            return $str('rulesanyreader');
        }
        return get_string_manager()->string_exists('readertype' . $type, 'local_oksigeniaayvurenda')
            ? $str(skins::words((int) $library->entityid, 'readertype' . $type)) : $type;
    }

    /**
     * The name of a kind of copy.
     *
     * @param string $type
     * @param callable $str
     * @return string
     */
    public static function what(string $type, callable $str): string {
        if ($type === '*') {
            return $str('rulesanyitem');
        }
        return get_string_manager()->string_exists('itemtype' . $type, 'local_oksigeniaayvurenda') ? $str('itemtype' . $type)
            : $type;
    }

    /**
     * A list said as people say it: «a, b and c».
     *
     * @param string[] $list
     * @param callable $str
     * @return string
     */
    private static function join(array $list, callable $str): string {
        $list = array_values($list);
        if (count($list) < 2) {
            return $list[0] ?? '';
        }
        $last = array_pop($list);
        return implode(', ', $list) . ' ' . $str('hiwand') . ' ' . $last;
    }
}
