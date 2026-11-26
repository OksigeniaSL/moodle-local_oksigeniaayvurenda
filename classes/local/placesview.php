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
 * The screens of the places of a library and of moving copies.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * What «The places of the library» and «Move books» draw, and what their gestures do. The places are pieces of furniture
 * with a plate (the name readers see) and their books on the plank, at the scale of the fullest; moving is a route from
 * one to another. What was done is told once, with its «Undo», as the page comes back.
 */
final class placesview {
    /** @var int Spines on a plank. */
    private const SPINES = 20;

    /** @var string[] The colour of each corner's colour number, as the covers draw them. */
    private const CORNER_INK = ['var(--green)', 'var(--coral)', 'var(--sun)', 'var(--green2)', 'var(--night)', 'var(--paper2)'];

    /**
     * A string of the plugin, in the words of the library.
     *
     * @param \stdClass $library
     * @param string $key
     * @param mixed $a
     * @return string
     */
    private static function str(\stdClass $library, string $key, $a = null): string {
        return skins::say((int) $library->entityid, $key, $a);
    }

    /**
     * «1 libro», «131 libros».
     *
     * @param \stdClass $library
     * @param int $count
     * @return string
     */
    private static function books(\stdClass $library, int $count): string {
        return self::str($library, $count === 1 ? 'placesbook' : 'placesbooks', screens::number($count));
    }

    /**
     * The spines of a plank: as many lit as the place has books, at the scale of the fullest; at least one if it has any.
     *
     * @param int $count
     * @param int $most
     * @return array[] on.
     */
    private static function spines(int $count, int $most): array {
        $lit = $count ? max(1, (int) round(self::SPINES * $count / max(1, $most))) : 0;
        return array_map(fn($i) => ['on' => $i < $lit], range(0, self::SPINES - 1));
    }

    /**
     * Keeps what was done, to tell it once when the page comes back.
     *
     * @param string $text
     * @param string $undo What undoes it, or ''.
     */
    public static function tell(string $text, string $undo = ''): void {
        global $SESSION;
        $SESSION->ayvplacesdone = ['text' => $text, 'undo' => $undo];
    }

    /**
     * What was done, once.
     *
     * @param \stdClass $library
     * @param string $action
     * @return array|null text, action, sesskey, undo, undolabel.
     */
    private static function told(\stdClass $library, string $action): ?array {
        global $SESSION;
        $done = $SESSION->ayvplacesdone ?? null;
        unset($SESSION->ayvplacesdone);
        if (!$done) {
            return null;
        }
        return ['text' => $done['text'], 'action' => $action, 'sesskey' => sesskey(), 'undo' => $done['undo'],
            'undolabel' => $done['undo'] !== '' ? self::str($library, 'placesundo') : ''];
    }

    /**
     * The name of a place as the staff read it: its name, or the programme's before.
     *
     * @param array|null $place From places::all().
     * @param \stdClass $library
     * @return string
     */
    private static function name(?array $place, \stdClass $library): string {
        return $place === null || $place['id'] === 0 ? self::str($library, 'placesnone') : $place['label'];
    }

    /**
     * The page of the places.
     *
     * @param \stdClass $library
     * @param string $action The page's address.
     * @return array
     */
    public static function places(\stdClass $library, string $action): array {
        $all = places::all($library);
        $byid = array_column($all, null, 'id');
        $most = max(array_merge([1], array_column($all, 'copies')));
        $moves = fn(array $more = []) => (new \moodle_url(
            '/local/oksigeniaayvurenda/moves.php',
            ['library' => $library->slug] + $more
        ))->out(false);
        $place = function (array $p) use ($library, $action, $most, $moves) {
            $old = $p['noname'] ? $p['label'] : implode(' · ', $p['old']);
            return ['id' => $p['id'], 'name' => $p['noname'] ? '' : $p['label'], 'oldname' => $old,
                'searchline' => $p['noname'] ? self::str($library, 'placestodayreaders')
                    : ($p['old'] ? self::str($library, 'placesforsearch') : ''),
                'noname' => $p['noname'], 'empty' => !$p['copies'], 'count' => screens::number($p['copies']),
                'countword' => self::str($library, $p['copies'] === 1 ? 'placesbookword' : 'placesbooksword'),
                'spines' => self::spines($p['copies'], $most), 'url' => $moves(['from' => $p['id']]),
                'openlabel' => self::str($library, 'placesmove'), 'action' => $action, 'sesskey' => sesskey(),
                'namelabel' => self::str($library, 'placesnamelabel'), 'example' => self::str($library, 'placesexample'),
                'savelabel' => self::str($library, 'placessave'), 'renamelabel' => self::str($library, 'placesrename'),
                'removelabel' => $p['copies'] ? '' : self::str($library, 'placesremove')];
        };
        // The places, by the room their old name starts with when two or more share it; the rest, after.
        $groups = [];
        foreach ($all as $p) {
            if ($p['id'] === 0) {
                continue;
            }
            $groups[$p['group']][] = $p;
        }
        uksort($groups, fn($a, $b) => [$a === '' ? 1 : 0, $a] <=> [$b === '' ? 1 : 0, $b]);
        $gid = 0;
        $rooms = [];
        foreach ($groups as $name => $members) {
            $rooms[] = ['gid' => ++$gid, 'name' => $name !== '' ? $name : (count($groups) > 1 ? self::str($library, 'placesother')
                : ''), 'countlabel' => count($groups) > 1 ? self::books($library, array_sum(array_column($members, 'copies'))) : '',
                'places' => array_map($place, $members)];
        }
        // The pairs that may be one, the fuller first.
        $pairs = [];
        $seen = [];
        foreach ($all as $p) {
            foreach ($p['twins'] as $twin) {
                $key = min($p['id'], $twin) . '-' . max($p['id'], $twin);
                if (isset($seen[$key]) || !isset($byid[$twin])) {
                    continue;
                }
                $seen[$key] = true;
                [$a, $b] = $p['copies'] >= $byid[$twin]['copies'] ? [$p, $byid[$twin]] : [$byid[$twin], $p];
                $oldof = fn(array $x) => $x['noname'] ? $x['raw'] : ($x['old'][0] ?? $x['raw']);
                $pairs[] = ['action' => $action, 'sesskey' => sesskey(), 'joinlabel' => self::str($library, 'placesjoinyes'),
                    'apartlabel' => self::str($library, 'placesjoinno'),
                    'a' => ['id' => $a['id'], 'oldhtml' => s($oldof($a)), 'countlabel' => ($a['noname'] ? '' : $a['label'] . ' · ')
                        . self::books($library, $a['copies'])],
                    'b' => ['id' => $b['id'], 'oldhtml' => places::marked($oldof($a), $oldof($b)),
                        'countlabel' => ($b['noname'] ? '' : $b['label'] . ' · ') . self::books($library, $b['copies'])]];
            }
        }
        $unplaced = $byid[0]['copies'] ?? 0;
        $nonames = array_values(array_filter($all, fn($p) => $p['noname']));
        $todo = [];
        if ($unplaced) {
            $todo[] = ['kind' => 'none', 'count' => screens::number($unplaced), 'text' => self::str($library, 'placestodonone'),
                'go' => self::str($library, 'placesplacethem'), 'url' => $moves(['from' => 0, 'way' => 'scan'])];
        }
        if ($pairs) {
            $todo[] = ['kind' => 'pairs', 'count' => screens::number(count($pairs)), 'text' => self::str(
                $library,
                count($pairs) === 1 ? 'placestodopair' : 'placestodopairs'
            ), 'go' => self::str($library, 'placeslook'),
                'url' => '#ayv-si-pares'];
        }
        if ($nonames) {
            $todo[] = ['kind' => 'names', 'count' => screens::number(count($nonames)), 'text' => self::str(
                $library,
                count($nonames) === 1 ? 'placestodoname' : 'placestodonames'
            ), 'go' => self::str($library, 'placesnameit'),
                'url' => '#ayv-si-' . $nonames[0]['id']];
        }
        return [
            'heading' => self::str($library, 'placesheading'),
            'intro' => self::str($library, 'placesintro'),
            'movesurl' => $moves(),
            'moveslabel' => self::str($library, 'placesmoves'),
            'done' => self::told($library, $action),
            'hastodo' => (bool) $todo,
            'todoheading' => self::str($library, 'placestodo'),
            'todo' => $todo,
            'haspairs' => (bool) $pairs,
            'pairsheading' => self::str($library, 'placespairs'),
            'pairshint' => self::str($library, 'placespairshint'),
            'pairs' => $pairs,
            'groups' => $rooms,
            'unplaced' => $unplaced ? ['count' => screens::number($unplaced), 'text' => self::str($library, 'placesunplaced'),
                'url' => $moves(['from' => 0, 'way' => 'scan']), 'go' => self::str($library, 'placesplacethem')] : null,
            'fresh' => ['action' => $action, 'sesskey' => sesskey(), 'heading' => self::str($library, 'placesnew'),
                'label' => self::str($library, 'placesnewlabel'), 'example' => self::str($library, 'placesexample'),
                'go' => self::str($library, 'placescreate')],
        ];
    }

    /**
     * Does what a gesture of the page of the places asks.
     *
     * @param \stdClass $library
     */
    public static function apply_places(\stdClass $library): void {
        $entityid = (int) $library->entityid;
        $all = array_column(places::all($library), null, 'id');
        $undo = optional_param('undo', '', PARAM_ALPHANUMEXT);
        if ($undo !== '') {
            self::undo($library, $undo);
            return;
        }
        $into = optional_param('into', 0, PARAM_INT);
        $join = optional_param('join', 0, PARAM_INT);
        $apart = optional_param('apart', 0, PARAM_INT);
        if ($into && $join && isset($all[$into], $all[$join])) {
            $batch = random_string(32);
            $moved = places::join($entityid, $into, [$join], $batch);
            self::tell(self::str($library, 'placesjoined', (object) ['place' => $all[$into]['label'],
                'books' => self::books($library, $moved), 'old' => $all[$join]['label']]), 'j' . $batch);
            return;
        }
        if ($into && $apart) {
            places::apart($entityid, $into, $apart);
            return;
        }
        $remove = optional_param('remove', 0, PARAM_INT);
        if ($remove && places::remove($entityid, $remove)) {
            self::tell(self::str($library, 'placesremoved', $all[$remove]['label'] ?? ''));
            return;
        }
        $name = trim(optional_param('name', '', PARAM_TEXT));
        $id = optional_param('place', 0, PARAM_INT);
        if ($name === '') {
            return;
        }
        if (!$id) {
            places::create($entityid, $name);
            self::tell(self::str($library, 'placescreated', $name));
            return;
        }
        $now = places::rename($entityid, $id, $name);
        if ($now && $now !== $id) {
            self::tell(self::str($library, 'placesjoined', (object) ['place' => $name,
                'books' => self::books($library, $all[$id]['copies'] ?? 0), 'old' => $all[$id]['label'] ?? '']));
        }
    }

    /**
     * Undoes a join (j…) or a move (m…).
     *
     * @param \stdClass $library
     * @param string $undo
     */
    public static function undo(\stdClass $library, string $undo): void {
        $batch = substr($undo, 1);
        if (!preg_match('/^[A-Za-z0-9]{32}$/', $batch)) {
            return;
        }
        $back = $undo[0] === 'j' ? places::unjoin((int) $library->entityid, $batch) : moves::undo($library, $batch);
        self::tell(self::str($library, $back === 1 ? 'placesundoneone' : 'placesundone', self::books($library, $back)));
    }

    /**
     * The state of «Move books», from the address: from where, which way, the letters or the corner, and where to.
     *
     * @return array from (an id, 0 or null), way, first and last letters, corner, to (an id, 0, 'new' or null), name.
     */
    public static function state(): array {
        $from = optional_param('from', null, PARAM_INT);
        $to = optional_param('to', '', PARAM_ALPHANUM);
        $way = optional_param('way', 'all', PARAM_ALPHA);
        return [
            'from' => $from,
            'way' => in_array($way, ['all', 'letters', 'corner', 'scan'], true) ? $way : 'all',
            'a' => optional_param('a', '', PARAM_ALPHA),
            'b' => optional_param('b', '', PARAM_ALPHA),
            'corner' => optional_param('corner', '', PARAM_ALPHANUM),
            'to' => $to === 'new' ? 'new' : ($to === '' ? null : (int) $to),
            'newname' => trim(optional_param('newname', '', PARAM_TEXT)),
            'batch' => optional_param('batch', '', PARAM_ALPHANUM),
            'reading' => optional_param('reading', 0, PARAM_BOOL) ? 1 : null,
        ];
    }

    /**
     * The address of «Move books» with a state.
     *
     * @param \stdClass $library
     * @param array $state
     * @return \moodle_url
     */
    public static function url(\stdClass $library, array $state): \moodle_url {
        $params = ['library' => $library->slug];
        foreach (['from', 'way', 'a', 'b', 'corner', 'to', 'newname', 'batch', 'reading'] as $key) {
            if (($state[$key] ?? null) !== null && $state[$key] !== '') {
                $params[$key] = $state[$key];
            }
        }
        return new \moodle_url('/local/oksigeniaayvurenda/moves.php', $params);
    }

    /**
     * What a move takes, as moves::chosen() reads it.
     *
     * @param \stdClass $library
     * @param array $state
     * @return array|null
     */
    private static function what(\stdClass $library, array $state): ?array {
        $what = ['place' => (int) $state['from']];
        if ($state['way'] === 'letters') {
            if (!preg_match('/^[A-Z]$/', $state['a'])) {
                return null;
            }
            $what['letters'] = [$state['a'], preg_match('/^[A-Z]$/', $state['b']) ? $state['b'] : $state['a']];
        } else if ($state['way'] === 'corner') {
            if ($state['corner'] === '') {
                return null;
            }
            $what['corner'] = $state['corner'];
        }
        return $what;
    }

    /**
     * Does what a gesture of «Move books» asks, and gives the state to go back to.
     *
     * @param \stdClass $library
     * @param array $state
     * @return array
     */
    public static function apply_moves(\stdClass $library, array $state): array {
        $undo = optional_param('undo', '', PARAM_ALPHANUMEXT);
        if ($undo !== '') {
            // One line of the reader: that copy goes back; a whole move: all of it.
            if (preg_match('/^i(\d+)$/', $undo, $m)) {
                global $SESSION;
                if (moves::undo_one($library, $state['batch'], (int) $m[1])) {
                    foreach ($SESSION->ayvmovelog[$state['batch']] ?? [] as $i => $line) {
                        if ($line['itemid'] === (int) $m[1] && $line['kind'] === 'moved') {
                            $SESSION->ayvmovelog[$state['batch']][$i] = ['kind' => 'here',
                                'note' => self::str($library, 'movesbackagain')] + $line;
                        }
                    }
                }
            } else {
                self::undo($library, $undo);
            }
            return $state;
        }
        $letter = optional_param('letter', '', PARAM_ALPHA);
        if ($letter !== '') {
            // The first letter, then the last; a third touch starts again.
            [$state['a'], $state['b']] = $state['a'] === '' || $state['b'] !== '' ? [$letter, ''] : [$state['a'], $letter];
            return $state;
        }
        $corner = optional_param('corner', '', PARAM_ALPHANUM);
        if ($corner !== '') {
            $state['corner'] = $corner;
            return $state;
        }
        $do = optional_param('do', '', PARAM_ALPHA);
        $to = $state['to'];
        if ($to === 'new') {
            $to = $state['newname'] !== '' ? places::create((int) $library->entityid, $state['newname']) : null;
        }
        if (!in_array($do, ['move', 'join'], true)) {
            return $state;
        }
        if ($to === null) {
            self::tell(self::str($library, 'movesnoto'));
            return $state;
        }
        if ($state['way'] === 'scan') {
            return ['from' => $state['from'], 'way' => 'scan', 'to' => $to, 'a' => '', 'b' => '', 'corner' => '',
                'newname' => '', 'batch' => random_string(32), 'reading' => 1];
        }
        $all = array_column(places::all($library), null, 'id');
        if ($do === 'join' && $state['from'] && $to) {
            $batch = random_string(32);
            $moved = places::join((int) $library->entityid, (int) $to, [(int) $state['from']], $batch);
            self::tell(self::str($library, 'placesjoined', (object) ['place' => $all[$to]['label'] ?? '',
                'books' => self::books($library, $moved), 'old' => $all[$state['from']]['label'] ?? '']), 'j' . $batch);
            return ['from' => $to, 'way' => 'all'] + array_fill_keys(['a', 'b', 'corner', 'to', 'newname', 'batch'], '');
        }
        $what = self::what($library, $state);
        if ($what === null) {
            return $state;
        }
        $done = moves::move($library, $what, (int) $to);
        $name = $all[$to]['label'] ?? places::get((int) $library->entityid, (int) $to)->label ?? '';
        self::tell(self::str($library, $done['count'] === 1 ? 'placesmovedone' : 'placesmoved', (object) [
            'books' => self::books($library, $done['count']),
            'place' => $name]), 'm' . $done['batch']);
        return ['from' => $state['from'], 'way' => $state['way']] + array_fill_keys(
            ['a', 'b', 'corner', 'to', 'newname', 'batch'],
            ''
        );
    }

    /**
     * The page of «Move books»: choosing (from, which books, where to), or reading the books one by one.
     *
     * @param \stdClass $library
     * @param array $state
     * @return array
     */
    public static function moves(\stdClass $library, array $state): array {
        global $SESSION;
        $all = places::all($library);
        $byid = array_column($all, null, 'id');
        $most = max(array_merge([1], array_column($all, 'copies')));
        $from = $state['from'] ?? null;
        if ($from === null || !isset($byid[$from])) {
            $from = isset($byid[0]) ? 0 : (int) ($all[0]['id'] ?? 0);
        }
        $state['from'] = $from;
        $here = fn(array $more = []) => self::url($library, $more + $state)->out(false);
        $box = fn(?array $p) => $p === null ? null : ['name' => self::name($p, $library),
            'oldline' => $p['id'] && !$p['noname'] ? implode(' · ', $p['old']) : '',
            'countlabel' => self::books($library, $p['copies']), 'spines' => self::spines($p['copies'], $most)];
        $placesurl = (new \moodle_url('/local/oksigeniaayvurenda/places.php', ['library' => $library->slug]))->out(false);
        $data = ['heading' => self::str($library, 'placesmoves'), 'intro' => self::str($library, 'movesintro'),
            'done' => self::told($library, $here()), 'choose' => null, 'reading' => null];
        if (!empty($state['reading']) && is_int($state['to'])) {
            $to = $byid[$state['to']] ?? ['id' => $state['to'], 'label' => places::get(
                (int) $library->entityid,
                (int) $state['to']
            )->label ?? '', 'old' => [], 'copies' => 0, 'noname' => false];
            $log = $SESSION->ayvmovelog[$state['batch']] ?? [];
            $tools = shell::readers_tools($library);
            $data['heading'] = self::str($library, 'movesreadheading');
            $data['intro'] = self::str($library, 'movesreadintro');
            $data['reading'] = ['toheading' => self::str($library, 'movesreadto'), 'to' => $box($to),
                'changeurl' => $here(['reading' => null, 'way' => 'scan']), 'changelabel' => self::str($library, 'moveschangeto'),
                'prompt' => self::str($library, 'movesprompt'), 'tools' => $tools['html'], 'action' => $here(),
                'sesskey' => sesskey(), 'cifra' => ['seen' => screens::number(count(array_filter(
                    $log,
                    fn($l) => $l['kind'] === 'moved'
                ))), 'of' => self::str($library, 'movesplacedhere')],
                'lastheading' => self::str($library, 'moveslast'),
                'log' => array_map(fn($l) => $l + ['undo' => $l['kind'] === 'moved' ? ['action' => $here(), 'sesskey' => sesskey(),
                    'id' => 'i' . $l['itemid'], 'label' => self::str($library, 'placesundo')] : null], array_reverse($log)),
                'finishurl' => $placesurl, 'finishlabel' => self::str($library, 'movesfinish'),
                'hint' => self::str($library, 'movesreadhint')];
            $data['tools'] = $tools;
            return $data;
        }
        // Which books: the ways, and the letters or the corners of the place.
        $ways = [];
        foreach (['all', 'letters', 'corner', 'scan'] as $way) {
            $ways[] = ['kind' => $way, 'url' => $here(['way' => $way, 'a' => '', 'b' => '', 'corner' => '']),
                'title' => self::str($library, 'movesway' . $way), 'text' => self::str($library, 'movesway' . $way . 'text'),
                'current' => $state['way'] === $way];
        }
        $what = self::what($library, $state);
        $preview = $what !== null && $state['way'] !== 'scan' ? moves::preview($library, $what) : null;
        $line = $preview && $preview['count'] ? self::str($library, 'movesline', (object) ['books' => self::books(
            $library,
            $preview['count']
        ), 'first' => s($preview['first']), 'last' => s($preview['last'])]) : '';
        $letters = null;
        if ($state['way'] === 'letters') {
            $found = moves::letters($library, $from);
            $order = array_merge(range('A', 'Z'), isset($found['#']) ? ['#'] : []);
            $a = array_search($state['a'], $order, true);
            $b = $state['b'] !== '' ? array_search($state['b'], $order, true) : $a;
            $list = [];
            foreach ($order as $i => $letter) {
                $in = $a !== false && $i >= min($a, $b) && $i <= max($a, $b);
                $list[] = ['letter' => $letter, 'count' => isset($found[$letter]) ? screens::number($found[$letter]['count']) : '',
                    'in' => $in, 'edge' => $a !== false && ($i === $a || $i === $b), 'none' => !isset($found[$letter])];
            }
            $letters = ['hint' => self::str($library, 'movesletterhint'), 'list' => $list, 'line' => $line];
        }
        $corners = null;
        if ($state['way'] === 'corner') {
            $colours = array_column(catalogue::corners($library, true, false), 'colour', 'code');
            $corners = ['hint' => self::str($library, 'movescornerhint', self::name($byid[$from] ?? null, $library)),
                'list' => array_map(fn($c) => ['id' => $c['code'], 'label' => $c['label'], 'count' => screens::number($c['count']),
                    'colour' => self::CORNER_INK[(int) ($colours[$c['code']] ?? 0) % count(self::CORNER_INK)],
                    'pressed' => $state['corner'] === $c['code']], moves::corners($library, $from)),
                'line' => $line];
        }
        $to = is_int($state['to']) ? ($byid[$state['to']] ?? null) : null;
        $count = $preview['count'] ?? ($state['way'] === 'all' ? (int) ($byid[$from]['copies'] ?? 0) : 0);
        $toname = $to ? self::name($to, $library) : ($state['to'] === 'new' ? $state['newname'] : '');
        $data['choose'] = [
            'fromheading' => self::str($library, 'movesfrom'), 'from' => $box($byid[$from] ?? null),
            'fromchange' => self::str($library, 'movesotherplace'), 'fromchangeurl' => $placesurl,
            'toheading' => self::str($library, 'movesto'), 'to' => $to ? $box($to) : ($state['to'] === 'new' && $toname !== ''
                ? ['name' => $toname, 'countlabel' => self::str($library, 'placesnew'), 'spines' => self::spines(0, 1)] : null),
            'toempty' => self::str($library, 'movestoempty'),
            'whatheading' => self::str($library, 'moveswhat'), 'ways' => $ways, 'letters' => $letters, 'corners' => $corners,
            'whereheading' => self::str($library, 'moveswhere'),
            'targets' => array_values(array_map(
                fn($p) => ['id' => $p['id'], 'name' => self::name($p, $library),
                'countlabel' => self::books($library, $p['copies']), 'here' => $to && $to['id'] === $p['id'],
                'herelabel' => self::str($library, 'moveshere')],
                array_filter($all, fn($p) => $p['id'] !== $from && $p['id'] !== 0)
            )),
            'fresh' => ['label' => self::str($library, 'placesnew'), 'example' => self::str($library, 'placesexample'),
                'value' => $state['newname'], 'chosen' => $state['to'] === 'new'],
            'action' => $here(), 'sesskey' => sesskey(),
            'go' => $state['way'] === 'scan' ? ['label' => self::str($library, 'movesstartreading'),
                'hint' => self::str($library, 'movesreadhint')]
                : ($count ? ['label' => $toname !== '' ? self::str($library, 'movesgoto', (object) ['books' => self::books(
                    $library,
                    $count
                ), 'place' => $toname]) : self::str($library, 'movesgo', self::books($library, $count)),
                    'hint' => self::str($library, $state['to'] === 'new' ? 'movessplithint' : 'movesundohint')] : null),
            'join' => $state['way'] === 'all' && $from && $to ? ['label' => self::str($library, 'movesjoin')] : null,
        ];
        return $data;
    }

    /**
     * One book passed by the reader: it goes to the place, and its line to the list of the last ones.
     *
     * @param \stdClass $library
     * @param array $state
     * @param string $code
     */
    public static function read(\stdClass $library, array $state, string $code): void {
        global $SESSION;
        if (!is_int($state['to']) || trim($code) === '') {
            return;
        }
        $done = moves::one($library, $code, $state['to'], $state['batch']);
        global $DB;
        $item = $done['found'] ? desk::by_code('item', 'barcode', (int) $library->entityid, trim($code)) : null;
        $kind = !$done['found'] ? 'unknown' : ($done['already'] ? 'here' : 'moved');
        $note = match ($kind) {
            'unknown' => self::str($library, 'movesunknown'),
            'here' => self::str($library, 'movesalready'),
            default => $done['from'] !== '' ? self::str($library, 'moveswasin', $done['from'])
                : self::str($library, 'moveswasnowhere'),
        };
        $log = $SESSION->ayvmovelog[$state['batch']] ?? [];
        $log[] = ['title' => $done['found'] ? $done['title'] : trim($code), 'note' => $note, 'kind' => $kind,
            'itemid' => $item ? (int) $item->id : 0];
        $SESSION->ayvmovelog = [$state['batch'] => array_slice($log, -30)];
    }
}
