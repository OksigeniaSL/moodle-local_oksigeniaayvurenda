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
 * The library one visits: rooms painted by Gaudí that one walks through.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

use local_oksigeniaayvurenda\core\homography;

/**
 * Each room is a scene of the art package (pix/visita/<scene>.json, its layers by day and by night, its sound), and
 * this library fills it: the doors lead to its corners and its shelves, the board names its corners as it names them,
 * the stand shows the books that came in last, the blackboard says what moved today, and the characters stand in it.
 * Every place one touches is a real link, also in a row of words for the keyboard and the screen reader. Without the
 * art package there is no visit.
 */
final class visit {
    /** @var int[] The size of the scenes, in pixels of the painting. */
    public const SIZE = [2400, 1350];

    /** @var string The scene one comes in by. */
    public const HALL = 'vestibulo';

    /** @var string[] What a place of a scene may be. */
    private const KINDS = ['room', 'corner', 'directory', 'new', 'pulse', 'voices', 'stacks', 'exit'];

    /** @var string[] The poses the characters may take in a scene, and the gestures of the puppets that mean them. */
    private const POSES = ['saluda' => 'saluda', 'senala' => 'senala', 'celebra' => 'celebra', 'lee' => 'lee',
        'piensa' => 'piensa', 'greet' => 'saluda', 'arrive' => 'saluda', 'point' => 'senala', 'celebrate' => 'celebra',
        'read' => 'lee', 'think' => 'piensa', 'idle' => 'piensa'];

    /**
     * Where the art package is, or another place for the tests.
     *
     * @param array|null $art dir and url.
     * @return array|null dir and url; null without the art package.
     */
    private static function art(?array $art): ?array {
        if ($art) {
            return $art;
        }
        $dir = \core_component::get_component_directory('local_oksigeniaayvurendaarte');
        return $dir ? ['dir' => $dir, 'url' => (new \moodle_url('/local/oksigeniaayvurendaarte'))->out(false)] : null;
    }

    /**
     * Whether there is a library to visit: the art package brings its hall.
     *
     * @param array|null $art
     * @return bool
     */
    public static function available(?array $art = null): bool {
        return self::scene(self::HALL, $art) !== null;
    }

    /**
     * The way in, for the home and the door: the hall's painting and the words; null when there is nothing to visit or
     * someone without an account may not walk the catalogue.
     *
     * @param \stdClass $library
     * @param bool $public
     * @param array|null $art
     * @return array|null url, label, hint, day, night, position.
     */
    public static function card(\stdClass $library, bool $public, ?array $art = null): ?array {
        $hall = self::scene(self::HALL, $art);
        if (!$hall || ($public && !window::catalogue_open($library))) {
            return null;
        }
        return ['url' => (new \moodle_url('/local/oksigeniaayvurenda/visit.php', ['library' => $library->slug]))->out(false),
            'label' => get_string('visitenter', 'local_oksigeniaayvurenda'),
            'hint' => get_string('visitenterhint', 'local_oksigeniaayvurenda'),
            'day' => $hall['layers'][0]['day'], 'night' => $hall['layers'][0]['night'],
            'position' => round($hall['focus'] / self::SIZE[0] * 100)];
    }

    /**
     * A scene as the art package draws it, checked: its layers, its characters and its places.
     *
     * @param string $name
     * @param array|null $art
     * @return array|null size, focus, layers, characters, zones and corner (of the room, '' for the hall).
     */
    public static function scene(string $name, ?array $art = null): ?array {
        $art = self::art($art);
        if (!$art || !preg_match('/^[a-z0-9-]{1,40}$/', $name)) {
            return null;
        }
        $dir = $art['dir'] . '/pix/visita/';
        $json = is_readable($dir . $name . '.json') ? json_decode((string) file_get_contents($dir . $name . '.json'), true) : null;
        if (!is_array($json)) {
            return null;
        }
        $layers = [];
        foreach ((array) ($json['layers'] ?? []) as $layer) {
            $layername = (string) ($layer['name'] ?? '');
            if (!preg_match('/^[a-z0-9]{1,20}$/', $layername) || !is_readable($dir . $name . '-' . $layername . '.webp')) {
                continue;
            }
            $night = is_readable($dir . $name . '-' . $layername . '-noche.webp');
            $layers[] = ['name' => $layername, 'depth' => max(0.0, min(1.0, (float) ($layer['depth'] ?? 0))),
                'day' => $art['url'] . '/pix/visita/' . $name . '-' . $layername . '.webp',
                'night' => $art['url'] . '/pix/visita/' . $name . '-' . $layername . ($night ? '-noche' : '') . '.webp'];
        }
        if (!$layers) {
            return null;
        }
        $characters = [];
        foreach (['memory', 'herald'] as $role) {
            $one = $json['characters'][$role] ?? null;
            $feet = self::point($one['feet'] ?? null);
            $height = (int) ($one['height'] ?? 0);
            if ($feet && $height >= 40 && $height <= self::SIZE[1]) {
                $characters[$role] = ['feet' => $feet, 'height' => $height,
                    'pose' => self::POSES[(string) ($one['pose'] ?? '')] ?? 'saluda'];
            }
        }
        $zones = [];
        $corner = '';
        foreach ((array) ($json['zones'] ?? []) as $i => $zone) {
            $kind = (string) ($zone['kind'] ?? '');
            if (!in_array($kind, self::KINDS, true)) {
                continue;
            }
            $clean = ['id' => 'z' . $i, 'kind' => $kind,
                'corner' => preg_match('/^\d$/', (string) ($zone['corner'] ?? '')) ? (string) $zone['corner'] : '',
                'to' => preg_match('/^[a-z0-9-]{0,40}$/', (string) ($zone['to'] ?? '')) ? (string) ($zone['to'] ?? '') : ''];
            if (in_array($kind, ['directory', 'pulse'], true)) {
                $clean['quad'] = self::quad($zone['quad'] ?? null);
                $clean['rows'] = max(1, min(12, (int) ($zone['rows'] ?? 9)));
                // The colour the painting asks for its words, by day and by night.
                foreach (['ink', 'inknight'] as $ink) {
                    $clean[$ink] = preg_match('/^#[0-9a-f]{6}$/i', (string) ($zone[$ink] ?? '')) ? $zone[$ink] : '';
                }
                if (!$clean['quad']) {
                    continue;
                }
            } else if ($kind === 'new') {
                $clean['slots'] = array_values(array_filter(array_map(
                    fn($q) => self::quad($q),
                    array_slice((array) ($zone['slots'] ?? []), 0, 12)
                )));
                if (!$clean['slots']) {
                    continue;
                }
            } else {
                $clean['shape'] = self::shape($zone['shape'] ?? null);
                if (!$clean['shape'] || (in_array($kind, ['corner', 'stacks'], true) && $clean['corner'] === '')) {
                    continue;
                }
            }
            if ($kind === 'stacks' && $corner === '') {
                $corner = $clean['corner'];
            }
            $zones[] = $clean;
        }
        $focus = (float) ($json['focus'] ?? self::SIZE[0] / 2);
        return ['name' => $name, 'layers' => $layers, 'characters' => $characters, 'zones' => $zones,
            'focus' => max(0.0, min((float) self::SIZE[0], $focus)),
            'corner' => preg_match('/^\d$/', (string) ($json['corner'] ?? '')) ? (string) $json['corner'] : $corner,
            'sound' => is_readable($art['dir'] . '/sonido/visita/' . $name . '.mp3')
                ? $art['url'] . '/sonido/visita/' . $name . '.mp3' : '',
            'door' => is_readable($art['dir'] . '/sonido/visita/puerta.mp3') ? $art['url'] . '/sonido/visita/puerta.mp3' : '',
            'touch' => is_readable($art['dir'] . '/sonido/visita/toque.mp3') ? $art['url'] . '/sonido/visita/toque.mp3' : ''];
    }

    /**
     * The scene filled with this library, ready for the page.
     *
     * @param \stdClass $library
     * @param string $name
     * @param bool $public Seen by someone without an account, through the window.
     * @param array|null $art
     * @param int $turn Which hidden book, in a room with its game.
     * @return array|null
     */
    public static function page(\stdClass $library, string $name, bool $public, ?array $art = null, int $turn = 0): ?array {
        $scene = self::scene($name, $art);
        if (!$scene) {
            return null;
        }
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        $url = fn(string $script, array $params = []) => (new \moodle_url(
            '/local/oksigeniaayvurenda/' . $script,
            ['library' => $library->slug] + $params
        ))->out(false);
        $corners = [];
        foreach (catalogue::corners($library) as $known) {
            $corners[$known['code']] = $known;
        }
        // A room exists when the art package paints it; a corner without its room leads to its shelves.
        $rooms = [];
        foreach ($scene['zones'] as $zone) {
            $room = $zone['kind'] === 'room' && $zone['to'] !== '' ? self::scene($zone['to'], $art) : null;
            if ($room && ($zone['corner'] ?: $room['corner']) !== '') {
                $rooms[$zone['corner'] ?: $room['corner']] = $zone['to'];
            }
        }
        $hall = $name === self::HALL ? null : self::scene(self::HALL, $art);
        $home = $public ? $url('public.php') : $url('index.php');
        $goes = fn(string $corner) => isset($rooms[$corner]) ? $url('visit.php', ['room' => $rooms[$corner]])
            : $url('stacks.php', ['corner' => $corner]);
        $title = $name === self::HALL ? $str('visithall') : ($corners[$scene['corner']]['label'] ?? $str('visit'));
        $zones = [];
        $surfaces = [];
        foreach ($scene['zones'] as $zone) {
            $kind = $zone['kind'];
            $label = '';
            $link = '';
            if ($kind === 'room') {
                $room = $zone['to'] !== '' ? self::scene($zone['to'], $art) : null;
                $corner = $zone['corner'] ?: ($room['corner'] ?? '');
                if ($room) {
                    $link = $url('visit.php', ['room' => $zone['to']]);
                } else if (isset($corners[$corner])) {
                    $link = $url('stacks.php', ['corner' => $corner]);
                }
                $label = $corners[$corner]['label'] ?? '';
            } else if ($kind === 'corner' && isset($corners[$zone['corner']])) {
                $link = $goes($zone['corner']);
                $label = $corners[$zone['corner']]['label'];
            } else if ($kind === 'stacks' && isset($corners[$zone['corner']])) {
                $link = $url('stacks.php', ['corner' => $zone['corner']]);
                $label = $str('visitstacks', $corners[$zone['corner']]['label']);
            } else if ($kind === 'voices') {
                $voices = !$public || window::settings($library)['voices'];
                $link = $voices ? ($public ? $url('public.php') : $url('ayvurande.php')) : '';
                $label = $str('visitvoices');
            } else if ($kind === 'exit') {
                $back = $zone['to'] !== '' && $zone['to'] !== $name ? self::scene($zone['to'], $art) : null;
                $link = $back ? $url('visit.php', $back['name'] === self::HALL ? [] : ['room' => $back['name']]) : $home;
                $label = $back ? ($back['name'] === self::HALL ? $str('visitbackhall')
                    : $str('visitback', $corners[$back['corner']]['label'] ?? $str('visit'))) : $str('visitexit');
            } else if ($kind === 'directory') {
                $surfaces[] = self::directory($zone, array_slice(array_values($corners), 0, $zone['rows']), $goes);
                continue;
            } else if ($kind === 'pulse') {
                $surfaces[] = self::pulse($zone, $library);
                continue;
            } else if ($kind === 'new') {
                array_push($surfaces, ...self::stand($zone, $library, $scene['corner'], $public, $url));
                continue;
            }
            if ($link === '' || $label === '') {
                continue;
            }
            $xs = array_column($zone['shape'], 0);
            $ys = array_column($zone['shape'], 1);
            $zones[] = ['id' => $zone['id'], 'kind' => $kind, 'url' => $link, 'label' => $label,
                'points' => implode(' ', array_map(fn($p) => round($p[0]) . ',' . round($p[1]), $zone['shape'])),
                'tipx' => round((min($xs) + max($xs)) / 2), 'tipy' => round(min($ys)), 'cy' => round((min($ys) + max($ys)) / 2),
                // Going into another room of the visit: the page walks through the door.
                'enters' => str_contains($link, '/visit.php')];
        }
        // In a room of a corner, the memory hides a book of it; in the hall, the herald brings the new books.
        $hunt = $scene['corner'] !== '' && isset($corners[$scene['corner']])
            ? self::hunt($library, $scene['corner'], $public, $turn, $url, $name) : null;
        $sort = $name === self::HALL ? self::sort($library, $public, $turn, $url, $art) : null;
        $depth = 0.0;
        $back = $scene['layers'];
        $front = [];
        if (count($back) > 1 && end($back)['name'] === 'frente') {
            $front = [array_pop($back)];
        }
        foreach ($back as $layer) {
            $depth = $layer['depth'];
        }
        return [
            'name' => $name,
            'title' => $title,
            'width' => self::SIZE[0],
            'height' => self::SIZE[1],
            'back' => $back,
            'front' => $front,
            'depth' => $depth,
            'zones' => $zones,
            'haszones' => (bool) $zones,
            'surfaces' => $surfaces,
            'characters' => self::characters(
                $library,
                $scene,
                $depth,
                $public,
                $url,
                $hunt ? 'ayv-hunt' : ($sort ? 'ayv-sort' : '')
            ),
            'hunt' => $hunt,
            'sort' => $sort,
            'hasplaces' => $zones || $hunt || $sort,
            'focus' => $scene['focus'],
            'sound' => $scene['sound'],
            'door' => $scene['door'],
            'touch' => $scene['touch'],
            'hall' => $hall ? $url('visit.php') : '',
        ];
    }

    /**
     * The board of the hall with the corners as the library names them, one row each, each a way to its room or shelf.
     *
     * @param array $zone
     * @param array[] $corners
     * @param callable $goes Where a corner leads.
     * @return array
     */
    private static function directory(array $zone, array $corners, callable $goes): array {
        [$w, $h] = self::box($zone['quad']);
        $rows = [];
        foreach ($corners as $corner) {
            $rows[] = ['label' => $corner['label'], 'url' => $goes($corner['code']), 'colour' => $corner['colour']];
        }
        return ['directory' => true, 'pulse' => false, 'book' => false, 'width' => round($w), 'height' => round($h),
            'transform' => homography::css($w, $h, $zone['quad']), 'rows' => $rows, 'ink' => self::ink($zone),
            // Letters as for a full board; a library with fewer corners spreads them, not crammed at the top.
            'rowheight' => round($h / max(count($rows), min($zone['rows'], 6)), 1),
            'fontsize' => round(min($h / $zone['rows'] * .5, $w / 14), 1)];
    }

    /**
     * The blackboard: what moved in the library today, with no one in it.
     *
     * @param array $zone
     * @param \stdClass $library
     * @return array
     */
    private static function pulse(array $zone, \stdClass $library): array {
        [$w, $h] = self::box($zone['quad']);
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        $pulse = window::pulse($library);
        $lines = [];
        if ($pulse['today']) {
            $lines[] = ['text' => $str($pulse['today'] === 1 ? 'windowpulsetodayone' : 'windowpulsetoday', $pulse['today'])];
        }
        if ($pulse['corner'] !== '') {
            $lines[] = ['text' => $str('windowpulsecorner', $pulse['corner'])];
        }
        if ($pulse['new']) {
            $lines[] = ['text' => $str($pulse['new'] === 1 ? 'windowpulsenewone' : 'windowpulsenew', $pulse['new'])];
        }
        if (!$lines) {
            $lines[] = ['text' => $str('visitpulsequiet')];
        }
        return ['directory' => false, 'pulse' => true, 'book' => false, 'width' => round($w), 'height' => round($h),
            'transform' => homography::css($w, $h, $zone['quad']), 'heading' => $str('visitpulse'), 'lines' => $lines,
            'ink' => self::ink($zone),
            'fontsize' => round(min($h * .1, $w / 13), 1)];
    }

    /**
     * The stand: in each hollow, a book that came in last (of the room's corner, in a room), with its cover or, without
     * one, a cover drawn with its title.
     *
     * @param array $zone
     * @param \stdClass $library
     * @param string $corner
     * @param bool $public
     * @param callable $url
     * @return array[]
     */
    private static function stand(array $zone, \stdClass $library, string $corner, bool $public, callable $url): array {
        $cards = catalogue::recent($library, 40, $corner);
        // The ones with a cover first: a painted stand of plain colours says less.
        usort($cards, fn($a, $b) => (int) empty($a['cover']) <=> (int) empty($b['cover']));
        $books = [];
        foreach (array_slice($cards, 0, count($zone['slots'])) as $i => $card) {
            [$w, $h] = self::box($zone['slots'][$i]);
            $books[] = ['directory' => false, 'pulse' => false, 'book' => true, 'ink' => '', 'width' => round($w),
                'height' => round($h),
                'transform' => homography::css($w, $h, $zone['slots'][$i]), 'title' => $card['title'],
                'cover' => $card['cover'], 'colour' => $card['colour'],
                'url' => $public ? $url('public.php', ['record' => $card['id']]) : $url('record.php', ['id' => $card['id']]),
                'fontsize' => round($w / 9, 1)];
        }
        return $books;
    }

    /**
     * The characters standing in the scene, alive where the art package brings their puppets, with what they say when
     * touched: the memory asks what one is looking for, the herald tells what moved today. Not a character the centre
     * turned off, nor one with a portrait of its own (a photograph does not stand in a painting).
     *
     * @param \stdClass $library
     * @param array $scene
     * @param float $depth
     * @param bool $public
     * @param callable $url
     * @param string $game The room's game, which the herald offers ('' for none): the id of its window.
     * @return array[]
     */
    private static function characters(
        \stdClass $library,
        array $scene,
        float $depth,
        bool $public,
        callable $url,
        string $game
    ): array {
        $out = [];
        foreach ($scene['characters'] as $role => $one) {
            $news = '';
            if ($role === 'herald') {
                $pulse = window::pulse($library);
                $news = get_string($pulse['new'] ? ($pulse['new'] === 1 ? 'windowpulsenewone' : 'windowpulsenew')
                    : 'visitpulsequiet', 'local_oksigeniaayvurenda', $pulse['new']);
            }
            $said = characters::say((int) $library->entityid, $role, 'visit' . $role, ['news' => $news], $one['pose']);
            if (!$said || empty($said['figure'])) {
                continue;
            }
            $out[] = ['role' => $role, 'name' => $said['name'], 'text' => $said['text'], 'figure' => $said['figure'],
                'puppet' => $said['puppet'] ?? '', 'size' => $one['height'], 'depth' => $depth,
                'left' => round($one['feet'][0] - $one['height'] / 2), 'top' => round($one['feet'][1] - $one['height']),
                'search' => $role === 'memory' ? ['action' => $public ? $url('public.php') : $url('search.php'),
                    'slug' => $library->slug] : null,
                'play' => $role === 'herald' && $game !== '' ? get_string(
                    $game === 'ayv-hunt' ? 'huntherald' : 'sortherald',
                    'local_oksigeniaayvurenda'
                ) : '',
                'playid' => $game];
        }
        return $out;
    }

    /**
     * The colours of the words of a surface, as CSS properties ('' for the page's own).
     *
     * @param array $zone
     * @return string
     */
    private static function ink(array $zone): string {
        return trim(($zone['ink'] !== '' ? '--visit-ink: ' . $zone['ink'] . '; ' : '') .
            ($zone['inknight'] !== '' ? '--visit-ink-night: ' . $zone['inknight'] . ';' : ''));
    }

    /**
     * The game of the room, ready for the page: the memory's clue, the shelf, and what is said when the book is found.
     *
     * @param \stdClass $library
     * @param string $corner
     * @param bool $public
     * @param int $turn
     * @param callable $url
     * @param string $room
     * @return array|null
     */
    private static function hunt(\stdClass $library, string $corner, bool $public, int $turn, callable $url, string $room): ?array {
        $game = hunt::for_visit($library, $corner, $public, $turn);
        if (!$game) {
            return null;
        }
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        $entityid = (int) $library->entityid;
        $said = characters::say($entityid, 'memory', $game['young'] ? 'huntclueyoung' : 'huntclue', [], 'piensa');
        $label = implode(' ', array_column($game['lines'], 'line'));
        $placecolour = '';
        foreach ($game['spines'] as $spine) {
            if ($spine['id'] === $game['target']) {
                $placecolour = (string) ($spine['placecolour'] ?? '');
            }
        }
        $cheer = $said && !empty($said['figure']) ? characters::figure('memory', 'celebra') : null;
        return [
            'target' => $game['target'],
            'heading' => $str('huntheading'),
            'name' => $said['name'] ?? '',
            'clue' => $said['text'] ?? $str($game['young'] ? 'huntclueyoung' : 'huntclue', (object) ['name' => '']),
            'figure' => $said['figure'] ?? null,
            'puppet' => $said['puppet'] ?? '',
            'cheer' => $cheer,
            'cheerpuppet' => $cheer ? characters::puppet('memory', 'celebra') : '',
            // Not «cover», «lines» nor «placecolour»: a spine without its own would take the hidden book's.
            'huntcover' => $game['cover'],
            'huntlines' => $game['lines'],
            'huntplace' => $placecolour,
            'labelsr' => $str('huntlabel', $label),
            'spines' => $game['spines'],
            'title' => $game['title'],
            'found' => $str('huntfound', $game['place'] !== '' ? $game['place'] : $str('stacksnoplace')),
            'foundhint' => $str($public ? 'huntfoundhintpublic' : 'huntfoundhint'),
            'recordurl' => $public ? $url('public.php', ['record' => $game['target']])
                : $url('record.php', ['id' => $game['target']]),
            'againurl' => $url('visit.php', ['room' => $room, 'hunt' => $turn + 1]) . '#busca',
            'messages' => json_encode(
                ['before' => $str('huntbefore'), 'after' => $str('huntafter'),
                'line1' => $str('huntline1', '{line}'), 'line2' => $str('huntline2', '{line}'),
                'line3' => $str('huntline3', '{line}'), 'gap' => $str('huntgap'), 'glow' => $str('huntglow')],
                JSON_UNESCAPED_UNICODE
            ),
        ];
    }

    /**
     * The herald's game of the hall, ready for the page: the new books, one after another, and the corners as doors.
     *
     * @param \stdClass $library
     * @param bool $public
     * @param int $turn
     * @param callable $url
     * @param array|null $art
     * @return array|null
     */
    private static function sort(\stdClass $library, bool $public, int $turn, callable $url, ?array $art): ?array {
        $books = shelve::books($library, $turn);
        if (!$books) {
            return null;
        }
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        $said = characters::say((int) $library->entityid, 'herald', 'sortintro', [], 'saluda');
        foreach ($books as $i => &$book) {
            $book['count'] = $str('sortcount', (object) ['n' => $i + 1, 'total' => count($books)]);
            $book['labelsr'] = $str('sortlabel', implode(' ', array_column($book['lines'], 'line')));
            $book['url'] = $public ? $url('public.php', ['record' => $book['id']]) : $url('record.php', ['id' => $book['id']]);
            // Without a cover, one drawn with its title; never the key of a page («cover» of the stand is another).
            $book['photo'] = $book['cover'];
            unset($book['cover']);
            $book['first'] = $i === 0;
        }
        unset($book);
        $doors = [];
        foreach (catalogue::corners($library) as $corner) {
            $doors[] = ['code' => $corner['code'], 'label' => $corner['label'], 'colour' => $corner['colour'],
                'art' => screens::art('rincon-' . $corner['code'])];
        }
        $cheer = $said && !empty($said['figure']) ? characters::figure('herald', 'celebra') : null;
        return [
            'heading' => $str('sortheading'),
            'name' => $said['name'] ?? '',
            'intro' => $said['text'] ?? $str('sortintro', (object) ['name' => '']),
            'figure' => $said['figure'] ?? null,
            'puppet' => $said['puppet'] ?? '',
            'cheer' => $cheer,
            'cheerpuppet' => $cheer ? characters::puppet('herald', 'celebra') : '',
            'books' => $books,
            'doors' => $doors,
            'againurl' => $url('visit.php', ['sort' => $turn + 1]) . '#coloca',
            'messages' => json_encode(['no' => $str('sortno', (object) ['corner' => '{corner}', 'digit' => '{digit}',
                'mine' => '{mine}']), 'topic' => $str('sorttopic', '{topic}'), 'glow' => $str('sortglow'),
                'yes' => $str('sortyes', '{corner}')], JSON_UNESCAPED_UNICODE),
        ];
    }

    /**
     * How big a flat box is to lie on four corners without stretching much: the mean of its opposite sides.
     *
     * @param array $quad
     * @return float[] [w, h]
     */
    private static function box(array $quad): array {
        $len = fn($p, $q) => hypot($q[0] - $p[0], $q[1] - $p[1]);
        return [max(1.0, ($len($quad[0], $quad[1]) + $len($quad[3], $quad[2])) / 2),
            max(1.0, ($len($quad[0], $quad[3]) + $len($quad[1], $quad[2])) / 2)];
    }

    /**
     * A point of the painting, or null.
     *
     * @param mixed $p
     * @return float[]|null
     */
    private static function point($p): ?array {
        if (!is_array($p) || count($p) !== 2 || !is_numeric($p[0] ?? null) || !is_numeric($p[1] ?? null)) {
            return null;
        }
        [$x, $y] = [(float) $p[0], (float) $p[1]];
        return $x >= -100 && $x <= self::SIZE[0] + 100 && $y >= -100 && $y <= self::SIZE[1] + 100 ? [$x, $y] : null;
    }

    /**
     * Four corners in order, or null.
     *
     * @param mixed $quad
     * @return array|null
     */
    private static function quad($quad): ?array {
        if (!is_array($quad) || count($quad) !== 4) {
            return null;
        }
        $points = array_map(fn($p) => self::point($p), array_values($quad));
        if (in_array(null, $points, true)) {
            return null;
        }
        try {
            homography::solve(100, 100, $points);
        } catch (\InvalidArgumentException $e) {
            return null;
        }
        return $points;
    }

    /**
     * A shape of three corners or more (at most 64), or null.
     *
     * @param mixed $shape
     * @return array|null
     */
    private static function shape($shape): ?array {
        if (!is_array($shape) || count($shape) < 3 || count($shape) > 64) {
            return null;
        }
        $points = array_map(fn($p) => self::point($p), array_values($shape));
        return in_array(null, $points, true) ? null : $points;
    }
}
