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

use local_oksigeniaayvurenda\core\flower;

/**
 * The reader's booklet as it is shown and printed (Jujol, 13 November): «Mi curso en libros», one school year on one
 * sheet, and «Mis años en libros», every year of a reader who leaves; and its cover for the e-mail and the chats (Gaudí).
 * Every picture goes sealed, as the e-mails carry them, so the family's page needs no session.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bookletview {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var string The cover's folder in the art package. */
    private const DIR = '/pix/librito/';

    /**
     * A string of the plugin.
     *
     * @param string $key
     * @param mixed $a
     * @return string
     */
    private static function str(string $key, $a = null): string {
        return get_string($key, 'local_oksigeniaayvurenda', $a);
    }

    /**
     * How many covers go in a row on paper so that a year fits in what is left of its sheet (Jujol's sum, measured on
     * paper): the fewest columns, from a least, whose rows fit, on a sheet 186 mm wide; with eight or fewer, their titles
     * go under them. At least four, or one book alone is a poster (Jujol).
     *
     * @param int $count Books.
     * @param float $budget Millimetres left for them.
     * @param int $least
     * @return int
     */
    public static function columns(int $count, float $budget, int $least = 4): int {
        for ($cols = $least; $cols < 40; $cols++) {
            $width = (186 - 3 * ($cols - 1)) / $cols;
            $rows = (int) ceil($count / $cols);
            if ($rows * (1.5 * $width + ($cols <= 8 ? 8 : 0)) + max(0, $rows - 1) * 2.5 <= $budget) {
                return $cols;
            }
        }
        return 40;
    }

    /**
     * A reader's flower or sky, drawn as on their card.
     *
     * @param string $look flower or sky.
     * @param int $stage
     * @param array $levels
     * @param string $label
     * @param int $size
     * @return string HTML.
     */
    public static function medal(string $look, int $stage, array $levels, string $label, int $size): string {
        $stage = max(0, min(3, $stage));
        return $look === 'sky' ? cards::sky_svg($stage, $levels, $label, $size, false)
            : cards::flower_svg($stage, $levels, $label, $size);
    }

    /**
     * The name of a stage of the flower or of the sky.
     *
     * @param string $look
     * @param int $stage
     * @return string
     */
    public static function stage_name(string $look, int $stage): string {
        $stage = max(0, min(3, $stage));
        return $look === 'sky' ? self::str('stagesky' . $stage) : self::str('stage' . flower::STAGES[$stage]);
    }

    /**
     * The first name a booklet calls its reader by.
     *
     * @param \stdClass $reader
     * @return string
     */
    public static function name(\stdClass $reader): string {
        $user = $reader->userid ? \core_user::get_user((int) $reader->userid) : null;
        $name = trim((string) ($user->firstname ?? $reader->firstname ?? ''));
        return preg_split('/\s+/u', $name)[0] ?? $name;
    }

    /**
     * The address of a reader's voice in a booklet, sealed: the page's player and the QR on paper open it.
     *
     * @param int $digitalid
     * @return string
     */
    public static function voice_url(int $digitalid): string {
        return (new \moodle_url('/local/oksigeniaayvurenda/booklet.php', ['voice' => $digitalid,
            's' => mail::seal('bookletvoice', $digitalid, 'v')]))->out(false);
    }

    /**
     * The covers of a list of books, sealed, with the colours of one without a picture.
     *
     * @param array $books As garden::books() gives them.
     * @return array[]
     */
    private static function books(array $books): array {
        $list = [];
        foreach ($books as $book) {
            $one = mail::book((int) $book['id'], format_string($book['title'], true, ['escape' => false]));
            $list[] = ['bookcover' => $one['cover'], 'bookbg' => $one['coverbg'], 'bookink' => $one['coverink'],
                'booktitle' => $one['title'], 'premiere' => !empty($book['premiere']) ? self::str('bookletpremiere') : ''];
        }
        return $list;
    }

    /**
     * The island of a class as it was left in a year: its layers and what it planted.
     *
     * @param array $page As booklet::year() gives it.
     * @return array|null layers, label and planted; null without the art package or a class.
     */
    private static function island(array $page): ?array {
        if ($page['group'] === '') {
            return null;
        }
        $layers = screens::island($page['island']['met'], $page['island']['everyone'], $page['island']['boat']);
        if (!$layers) {
            return null;
        }
        return [
            'layers' => array_map(fn($url) => ['layer' => $url], $layers),
            'islandlabel' => self::str('bookletislandof', $page['group']),
            'planted' => array_map(fn($p) => ['plantedlabel' => self::str('challengeplanted', $p['term']) . ' · '
                . self::str('challengekind' . $p['kind'])], $page['planted']),
        ];
    }

    /**
     * The collector cards a reader won, with the day of each: those of a year, or all.
     *
     * @param \stdClass $reader
     * @param \stdClass $library
     * @param bool $sky
     * @param int[] $won Corner => the moment it was won.
     * @return array cards (cromoart, cromoname, code, when) and the number there are.
     */
    private static function cromos(\stdClass $reader, \stdClass $library, bool $sky, array $won): array {
        $all = garden::cromos($reader, $library, $sky) ?? [];
        $list = [];
        foreach ($all as $cromo) {
            if (isset($won[$cromo['code']])) {
                $list[] = ['cromoart' => $cromo['art'], 'cromoname' => $cromo['name'], 'code' => (string) $cromo['code'],
                    'time' => (int) $won[$cromo['code']]];
            }
        }
        return ['list' => $list, 'of' => count($all)];
    }

    /**
     * The full bingo cards of a reader in a school year, with their diploma (sealed).
     *
     * @param \stdClass $reader
     * @param \stdClass $year
     * @param int $now
     * @return array|null The last one: img, name, day; null if none.
     */
    private static function cartilla(\stdClass $reader, \stdClass $year, int $now): ?array {
        global $DB;
        if (!diploma::drawable()) {
            return null;
        }
        $found = null;
        foreach ($DB->get_records(self::T . 'questreader', ['readerid' => $reader->id], 'id') as $joined) {
            $full = diploma::of((int) $joined->id, $now);
            if ($full && $full['day'] >= (int) $year->timestart && $full['day'] < (int) $year->timeend) {
                $found = ['img' => mail::diploma((int) $joined->id), 'day' => (int) $full['day'],
                    'name' => format_string($full['quest']->name, true, ['escape' => false])];
            }
        }
        return $found;
    }

    /**
     * «Mi curso en libros»: one school year of a reader, on one sheet.
     *
     * @param \stdClass $reader
     * @param \stdClass $year
     * @param \stdClass $library Whose corners name the collector cards and whose name signs.
     * @param int|null $now
     * @return array The context of booklet_sheet, with look (flower or sky) and its count of books.
     */
    public static function sheet(\stdClass $reader, \stdClass $year, \stdClass $library, ?int $now = null): array {
        $now = $now ?? time();
        $entityid = (int) $reader->entityid;
        $page = booklet::year($reader, $year);
        $sky = $page['look'] === 'sky';
        $name = self::name($reader);
        $librarian = format_string($library->name, true, ['escape' => false]);
        $books = self::books($page['books']);
        $island = self::island($page);
        $won = self::cromos($reader, $library, $sky, $page['cards']);
        $cromos = $won['list'] ? ['cromoshead' => self::str('bookletcromos'),
            'cromoscount' => self::str('bookletcromoscount', (object) ['n' => count($won['list']), 'of' => $won['of']]),
            'list' => array_map(
                fn($c) => $c + ['cromowhen' => zones::date($c['time'], 'strftimedateshort', $entityid)],
                $won['list']
            )] : null;
        $card = self::cartilla($reader, $year, $now);
        $cartilla = $card ? ['cartillahead' => self::str('bookletcartilla'), 'cartillaimg' => $card['img'],
            'cartillaalt' => self::str('bookletcartillaalt', (object) ['name' => $name, 'card' => $card['name']]),
            'cartillafull' => self::str('bookletcartillafull'), 'cartillaname' => $card['name'],
            'cartillawhen' => zones::date($card['day'], 'strftimedate', $entityid)] : null;
        $drawings = array_map(fn($d) => ['drawingurl' => $d['url'], 'drawingalt' => self::str('bookletdrawingalt', $d['title']),
            'drawingtitle' => '«' . format_string($d['title'], true, ['escape' => false]) . '»'], $page['drawings']);
        $voice = null;
        if ($page['voices']) {
            $last = end($page['voices']);
            $said = self::str(
                $last['forlittle'] ? 'bookletvoicelittle' : 'bookletvoiceread',
                format_string($last['title'], true, ['escape' => false])
            );
            $audio = self::voice_url((int) $last['id']);
            $voice = ['voicetext' => $said, 'audio' => $audio, 'listen' => self::str('familypageplay'),
                'pause' => self::str('familypagepause'), 'paper' => $said . ' ' . self::str('bookletvoicepaper'),
                'qr' => 'data:image/png;base64,' . base64_encode((new \core_qrcode($audio))->getBarcodePngData(3, 3))];
        }
        $told = $drawings || $voice ? ['toldhead' => self::str($sky ? 'bookletvoice' : 'booklettold'),
            'hasdrawings' => (bool) $drawings, 'drawings' => $drawings, 'voice' => $voice] : null;
        // The millimetres left for the covers, measured on paper (Jujol): the sheet, its cover, the books' heading and
        // legend, the island and the cards (a second row of cards past five), the bingo card or what was told, the
        // library's slip and a margin, as fonts measure a little differently in each browser.
        $premieres = count(array_filter($books, fn($b) => $b['premiere'] !== ''));
        $row = $island || $cromos ? (count($won['list']) > 5 ? 74 : 62) : 0;
        $budget = 250 - 49 - 11 - ($premieres ? 5 : 0) - $row - ($told || $cartilla ? 40 : 0) - 24 - 8;
        $cols = self::columns(count($books), $budget);
        $line = booklet::line($entityid, (int) $year->id);
        $stage = (int) $page['stage'];
        return [
            'sheetid' => 'r' . $reader->id . 'y' . $year->id,
            'look' => $page['look'],
            'sheettitle' => self::str('bookletcoursetitle'),
            'readername' => $name,
            'groupname' => $page['group'],
            'yearname' => $year->name,
            'medal' => self::medal($page['look'], $stage, $page['levels'], self::str(
                $sky ? 'bookletmysky' : 'bookletmyflower',
                self::stage_name($page['look'], $stage)
            ), 240),
            'stagename' => self::stage_name($page['look'], $stage),
            'stageline' => self::str($stage === 0 ? 'bookletstageseed' : 'bookletstageend'),
            'hasbooks' => (bool) $books,
            'bookshead' => self::str('bookletbooks'),
            'bookcount' => count($books),
            'sheetcols' => $cols,
            'titled' => $cols <= 8,
            'premierehint' => $premieres ? self::str('bookletpremierehint') : '',
            'books' => $books,
            'body' => (bool) ($island || $cromos || $cartilla || $told),
            // With nothing but the island (a reader who took no book), the island takes the whole width.
            'islandonly' => $island && !$cromos && !$cartilla && !$told && !$books,
            'island' => $island ? $island + ['islandhead' => self::str('bookletisland')] : null,
            'cromos' => $cromos,
            'cartilla' => $cartilla,
            'told' => $told,
            'note' => $line !== '' ? ['notetext' => $line, 'notesign' => self::str('bookletsign', $librarian),
                'stamp' => self::str('bookletstamp'), 'stampyear' => $year->name] : null,
        ];
    }

    /**
     * The short name of a school year, under its medallion on the road: «14-15», or «26».
     *
     * @param string $name
     * @return string
     */
    private static function short(string $name): string {
        return preg_match('/^\d\d(\d\d)\D+\d\d(\d\d)$/', $name, $m) ? $m[1] . '-' . $m[2]
            : (preg_match('/^\d\d(\d\d)$/', $name, $m) ? $m[1] : $name);
    }

    /**
     * The span of years of a life, «2014-2027»: from the start of the first to the end of the last.
     *
     * @param string $first
     * @param string $last
     * @return string
     */
    private static function span(string $first, string $last): string {
        $from = preg_match('/^(\d{4})/', $first, $m) ? $m[1] : $first;
        $to = preg_match('/(\d{4})$/', $last, $m) ? $m[1] : $last;
        return $from === $to ? $from : $from . '-' . $to;
    }

    /**
     * «Mis años en libros»: every school year of a reader here.
     *
     * @param \stdClass $reader
     * @param \stdClass $library
     * @return array|null The context of booklet_life, with look (that of the last year); null without a year.
     */
    public static function life(\stdClass $reader, \stdClass $library): ?array {
        global $DB;
        $entityid = (int) $reader->entityid;
        $data = booklet::life($reader);
        if (!$data['years']) {
            return null;
        }
        $id = 'r' . $reader->id;
        $librarian = format_string($library->name, true, ['escape' => false]);
        $years = $data['years'];
        $last = end($years);
        $sky = $last['look'] === 'sky';
        $anchor = fn(array $y) => 'y' . $y['yearid'] . '-' . $id;
        $stagename = fn(array $y) => self::stage_name($y['look'], (int) $y['stage']);
        $medal = fn(array $y, int $size) => self::medal(
            $y['look'],
            (int) $y['stage'],
            $y['levels'],
            $y['year'] . ': ' . $stagename($y),
            $size
        );
        // The first years whose loans are no longer kept, only their flower: together on one sheet.
        $firsts = [];
        foreach ($years as $y) {
            if ($y['books']) {
                break;
            }
            $firsts[] = $y;
        }
        $blocks = array_slice($years, count($firsts));
        $won = self::cromos($reader, $library, $sky, $data['cards']);
        // Where each card was won: the class of the year it fell in.
        $groupat = function (int $time) use ($DB, $years): string {
            foreach ($years as $y) {
                $year = $DB->get_record(self::T . 'year', ['id' => $y['yearid']], 'id, timestart, timeend');
                if ($year && $time >= (int) $year->timestart && $time < (int) $year->timeend) {
                    return $y['group'];
                }
            }
            return '';
        };
        $islands = [];
        $yearblocks = [];
        foreach ($blocks as $y) {
            $books = self::books($y['books']);
            $cols = self::columns(count($books), 92, 6);
            $island = $y['island']['met'] > 0 || $y['island']['boat'] ? self::island($y) : null;
            if ($island) {
                $islands[] = ['islandlabel' => $island['islandlabel'], 'layers' => $island['layers'],
                    'islandcaption' => ($y['group'] !== '' ? $y['group'] . ' · ' : '') . $y['year']];
            }
            $yearcards = array_values(array_filter($won['list'], fn($c) => isset($y['cards'][$c['code']])));
            $what = ($y['group'] !== '' ? '<b>' . s($y['group']) . '</b> · ' : '') . s($stagename($y))
                . ($books ? ' · ' . s(self::str(count($books) === 1 ? 'bookletonebook' : 'bookletnbooks', count($books))) : '');
            $yearblocks[] = [
                'yearanchor' => $anchor($y),
                'yearmedal' => $medal($y, 120),
                'yearname' => $y['year'],
                'yearwhat' => $what,
                'bookshead' => self::str('bookletbooks'),
                'hasbooks' => (bool) $books,
                'bookcount' => count($books),
                'yearcols' => $cols,
                'yeartitled' => $cols <= 8,
                'books' => $books,
                'hasrow' => (bool) ($island || $yearcards),
                'yearisland' => $island ? ['islandhead' => self::str('bookletislandof', $y['group']),
                    'islandlabel' => $island['islandlabel'], 'layers' => $island['layers']] : null,
                'yearcromos' => $yearcards ? ['cromoshead' => self::str('bookletcromos'), 'cromoscount' => count($yearcards),
                    'list' => $yearcards] : null,
            ];
        }
        $first = $data['first'];
        $firstbook = null;
        if ($first) {
            $one = mail::book((int) $first['id'], format_string($first['title'], true, ['escape' => false]));
            $group = booklet::group_of((int) $reader->id, (int) $first['yearid']);
            $day = zones::date((int) $first['time'], 'strftimedate', $entityid);
            $firstbook = ['firsthead' => self::str('bookletfirst'), 'firstcover' => $one['cover'], 'firstbg' => $one['coverbg'],
                'firstink' => $one['coverink'], 'firsttitle' => $one['title'],
                'firstwhen' => $group !== '' ? self::str('bookletfirstwhen', (object) ['group' => $group, 'day' => $day])
                    : self::str('bookletfirstday', $day)];
        }
        $groups = array_values(array_filter(array_column($years, 'group'), 'strlen'));
        $span = self::span($years[0]['year'], $last['year']);
        $farewell = booklet::farewell($entityid) ?: booklet::line($entityid, (int) $last['yearid']);
        $count = count($years);
        return [
            'lifeid' => $id,
            'look' => $last['look'],
            'lifetitle' => self::str('bookletlifetitle'),
            'readername' => self::name($reader),
            'fromto' => count(array_unique($groups)) > 1 ? self::str('bookletfromto', (object) ['from' => s($groups[0]),
                'to' => s(end($groups)), 'years' => s($span)]) : ($groups ? '<b>' . s($groups[0]) . '</b> · ' : '') . s($span),
            'library' => $librarian,
            'emblem' => (new \moodle_url('/local/oksigeniaayvurenda/app/emblema-pequeno.svg'))->out(false),
            // One year makes no road: the year comes right after the cover (Jujol).
            'hasroad' => $count > 1,
            'road' => $count > 1 ? array_map(fn($y) => ['roadanchor' => $anchor($y), 'roadmedal' => $medal($y, 64),
                'roadshort' => self::short($y['year'])], $years) : [],
            'roadhint' => $count > 1 ? self::str('bookletroadhint') : '',
            'first' => $firstbook,
            'count' => self::str($count === 1 ? 'bookletoneyear' : 'bookletyears', $count),
            'firsts' => $firsts ? ['firstshead' => self::str(count($firsts) === 1 ? 'bookletfirstyear' : 'bookletfirstyears'),
                'firstskept' => self::str(count($firsts) === 1 ? 'bookletfirstyearkept' : 'bookletfirstyearskept'),
                'list' => array_map(
                    fn($y) => ['firstanchor' => $anchor($y), 'firstmedal' => $medal($y, 72),
                    'firstyear' => $y['year'], 'firstwhat' => ($y['group'] !== '' ? $y['group'] . ' · ' : '') . $stagename($y)],
                    $firsts
                )] : null,
            'years' => $yearblocks,
            'closing' => [
                'collectionhead' => self::str('bookletcollection'),
                'collectioncount' => $won['list'] && count($won['list']) === $won['of'] ? self::str('bookletcollectionfull')
                    : count($won['list']),
                'collection' => array_map(fn($c) => $c + ['cromowhen' => $groupat($c['time'])], $won['list']),
                'islandshead' => self::str('bookletislands'),
                'hasislands' => (bool) $islands,
                'islands' => $islands,
                'note' => $farewell !== '' ? ['notetext' => $farewell, 'notesign' => self::str('bookletsign', $librarian),
                    'stamp' => self::str('bookletstamp'), 'stampyear' => $span] : null,
            ],
            'books' => $data['books'],
            'span' => $span,
            'groups' => $groups,
            'medals' => array_map(fn($y) => [$y['look'], (int) $y['stage'], $y['levels']], $years),
        ];
    }

    /**
     * The booklet's cover for the e-mail and the chats (Gaudí): the background of its edition and skin, the medallion
     * (and, for the whole life, one small one for each year), the title, the name on its tag, the class and the year,
     * and the library. Drawn each time, nothing kept.
     *
     * @param string $edition curso or vida.
     * @param bool $sky
     * @param array $medal look, stage and levels of the big medallion.
     * @param array $row The small ones, for the whole life: look, stage and levels of each year.
     * @param string $name
     * @param string $line
     * @param string $library
     * @param int $width Smaller than 1200, for a page.
     * @return string|null The JPEG; null without the art package's cover or without fonts in GD.
     */
    public static function cover(
        string $edition,
        bool $sky,
        array $medal,
        array $row,
        string $name,
        string $line,
        string $library,
        int $width = 0
    ): ?string {
        $art = \core_component::get_component_directory('local_oksigeniaayvurendaarte');
        if (!$art || !function_exists('imagettftext') || !is_readable($art . self::DIR . 'huecos.json')) {
            return null;
        }
        $holes = json_decode((string) file_get_contents($art . self::DIR . 'huecos.json'), true);
        $base = @imagecreatefromjpeg($art . self::DIR . 'fondo-' . $edition . '-' . ($sky ? 'noche' : 'dia') . '.jpg');
        if (!$base) {
            return null;
        }
        imagealphablending($base, true);
        $boxes = $holes['ediciones'][$edition];
        $hole = $boxes['medallon'];
        $big = self::medallion($art, ...$medal);
        if ($big) {
            imagecopyresampled($base, $big, $hole['x'], $hole['y'], 0, 0, $hole['w'], $hole['h'], imagesx($big), imagesy($big));
        }
        if ($edition === 'vida' && $row) {
            $spec = $holes['hilera'];
            $row = array_slice($row, -(int) $spec['maximo']);
            $count = count($row);
            $x = (int) round(600 - ($count * $spec['tamano'] + ($count - 1) * $spec['separacion']) / 2);
            $y = (int) ($spec['centro_y'] - $spec['tamano'] / 2);
            foreach ($row as $i => $one) {
                $small = self::medallion($art, ...$one);
                if ($small) {
                    imagecopyresampled(
                        $base,
                        $small,
                        $x + $i * ($spec['tamano'] + $spec['separacion']),
                        $y,
                        0,
                        0,
                        $spec['tamano'],
                        $spec['tamano'],
                        imagesx($small),
                        imagesy($small)
                    );
                }
            }
        }
        $colours = $holes['colores'][$sky ? 'noche' : 'dia'];
        $title = self::str($edition === 'vida' ? 'bookletlifetitle' : 'bookletcoursetitle');
        postcard::text($base, $art, $boxes['titulo'], $title, $colours['titulo']);
        postcard::text($base, $art, $boxes['nombre'], $name, $colours['nombre']);
        postcard::text($base, $art, $boxes['linea'], $line, $colours['linea']);
        postcard::text($base, $art, $boxes['biblioteca'], $library, $colours['biblioteca']);
        if ($width > 0 && $width < imagesx($base)) {
            $base = imagescale($base, $width, -1, IMG_BICUBIC);
        }
        ob_start();
        imagejpeg($base, null, $width > 0 ? 82 : 88);
        return (string) ob_get_clean();
    }

    /**
     * The box «Their booklets» on a reader's record: the year's and that of all their years, to see and to print, for
     * whoever has no account, or when the family asks.
     *
     * @param \stdClass $reader
     * @param \stdClass $library
     * @return array|null booklethead, bookletlist and bhint; null for a reader who never took a book home.
     */
    public static function record(\stdClass $reader, \stdClass $library): ?array {
        global $DB;
        if (
            $reader->readertype !== 'student' || !$DB->record_exists_select(
                'local_oksigeniaayvurenda_loan',
                'readerid = ? AND ' . bank::reading('libraryid'),
                [$reader->id]
            )
        ) {
            return null;
        }
        $now = time();
        $year = years::around((int) $reader->entityid, $now);
        $page = booklet::year($reader, $year);
        $parts = [self::str(count($page['books']) === 1 ? 'bookletonebook' : 'bookletnbooks', count($page['books']))];
        if ($page['cards']) {
            $parts[] = self::str(count($page['cards']) === 1 ? 'bookletonecromo' : 'bookletncromos', count($page['cards']));
        }
        if (self::cartilla($reader, $year, $now)) {
            $parts[] = self::str('bookletfullcard');
        }
        $last = array_pop($parts);
        $what = $parts ? implode(', ', $parts) . self::str('bookletlistand') . $last : $last;
        $url = fn(array $more) => (new \moodle_url('/local/oksigeniaayvurenda/booklet.php', ['library' => $library->slug,
            'reader' => $reader->id] + $more))->out(false);
        $life = booklet::life($reader);
        $groups = array_values(array_unique(array_filter(array_column($life['years'], 'group'), 'strlen')));
        $span = $life['years'] ? self::span($life['years'][0]['year'], end($life['years'])['year']) : $year->name;
        $lastyear = $life['years'] ? end($life['years']) : $page;
        $see = self::str('bookletrecordsee');
        $print = self::str('bookletrecordprint');
        return [
            'booklethead' => self::str('bookletrecord'),
            'bookletlist' => [
                ['bmedal' => self::medal($page['look'], (int) $page['stage'], $page['levels'], self::stage_name(
                    $page['look'],
                    (int) $page['stage']
                ), 64), 'btitle' => self::str('bookletrecordyear', $year->name),
                    'bline' => ($page['group'] !== '' ? $page['group'] . ' · ' : '') . $what, 'see' => $see, 'printit' => $print,
                    'seeurl' => $url([]), 'printurl' => $url([]) . '#imprimir'],
                ['bmedal' => self::medal(
                    $lastyear['look'],
                    (int) $lastyear['stage'],
                    $lastyear['levels'],
                    self::stage_name($lastyear['look'], (int) $lastyear['stage']),
                    64
                ),
                    'btitle' => self::str('bookletrecordlife', $span),
                    'bline' => count($life['years']) > 1 && count($groups) > 1 ? self::str('bookletrecordlifeline', (object) [
                        'years' => count($life['years']), 'from' => $groups[0], 'to' => end($groups)])
                        : self::str('bookletrecordlifelineone'),
                    'see' => $see, 'printit' => $print, 'seeurl' => $url(['life' => 1]),
                    'printurl' => $url(['life' => 1]) . '#imprimir'],
            ],
            'bhint' => self::str('bookletrecordhint'),
        ];
    }

    /**
     * The cover with example data, small, for «How your library works» to show what families get: «Leo», a flower in
     * full bloom, this year and the library's own name.
     *
     * @param \stdClass $library
     * @param int $width
     * @return string|null A data address of the JPEG.
     */
    public static function sample(\stdClass $library, int $width = 480): ?string {
        $year = years::around((int) $library->entityid, time());
        $jpeg = self::cover(
            'curso',
            false,
            ['flower', 3, ['stay' => 2, 'back' => 3, 'explore' => 2, 'tell' => 2]],
            [],
            get_string('hiwfirstbooksamplename', 'local_oksigeniaayvurenda'),
            $year->name,
            format_string($library->name, true, ['escape' => false]),
            $width
        );
        return $jpeg === null ? null : 'data:image/jpeg;base64,' . base64_encode($jpeg);
    }

    /**
     * A medallion composed as the card does: the stage's base and the marks lit on each path, from the PNG copies of the
     * art package (GD on some servers reads no WebP).
     *
     * @param string $art The art package's folder.
     * @param string $look flower or sky.
     * @param int $stage
     * @param array $levels
     * @return \GdImage|null
     */
    private static function medallion(string $art, string $look, int $stage, array $levels) {
        $kind = $look === 'sky' ? 'cielo' : 'flor';
        $spec = json_decode((string) @file_get_contents($art . '/pix/carne/' . $kind . '/carne.json'), true);
        $dir = $art . self::DIR . 'medallon/' . $kind . '/';
        $stage = max(0, min(3, $stage));
        if (!$spec || !isset($spec['stages'][$stage])) {
            return null;
        }
        $image = @imagecreatefrompng($dir . pathinfo($spec['stages'][$stage]['file'], PATHINFO_FILENAME) . '.png');
        if (!$image) {
            return null;
        }
        imagealphablending($image, true);
        imagesavealpha($image, true);
        $scale = imagesx($image) / (int) $spec['frame'];
        foreach ($spec['order'] as $path) {
            foreach ($spec['paths'][$path]['marks'] as $mark) {
                if ((int) $mark['n'] > (int) ($levels[$path] ?? 0)) {
                    continue;
                }
                $layer = @imagecreatefrompng($dir . pathinfo($mark['lit'], PATHINFO_FILENAME) . '.png');
                if (!$layer) {
                    continue;
                }
                if (imagesx($layer) === imagesx($image)) {
                    imagecopy($image, $layer, 0, 0, 0, 0, imagesx($layer), imagesy($layer));
                } else {
                    [$x, $y] = [(int) round($mark['box'][0] * $scale), (int) round($mark['box'][1] * $scale)];
                    imagecopy($image, $layer, $x, $y, 0, 0, imagesx($layer), imagesy($layer));
                }
            }
        }
        return $image;
    }
}
