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
 * «La cartilla» (P3 of the review of 12 November): a library challenge as a bingo card of nine squares the desk measures.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * A challenge of the library was a number of books; a cartilla is nine squares, each a kind of book (a corner, a genre,
 * a new one, one with a voice, one rescued from years on the shelf). Each loan in its days fills one square it fits, so
 * the reader decides what to take; a row or a column is a line, all nine the full card. The squares are kept as a list of
 * specs in the challenge: «corner:8», «genre:g2», «new», «voice», «rescued».
 */
final class cartilla {
    /** @var string Prefix of the tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var int Squares of a card. */
    public const SIZE = 9;

    /** @var string[] The squares of every library, besides its corners and genres. */
    public const SPECIAL = ['new', 'voice', 'rescued'];

    /** @var int A book nobody took home for so long is rescued. */
    public const RESCUED = 2 * 365 * DAYSECS;

    /** @var string The stages of the youngest, where a cartilla does not show (the flower is their game). */
    public const INFANT = '/infantil|preescolar|kinder|nursery|\\bei\\b/i';

    /** @var int[][] The rows and columns of the card. */
    public const LINES = [[0, 1, 2], [3, 4, 5], [6, 7, 8], [0, 3, 6], [1, 4, 7], [2, 5, 8]];

    /**
     * The squares a library can put on a card, in the three groups of the box: its corners, its genres and the three of
     * every library; each with its drawing and how many books fill it.
     *
     * @param \stdClass $library
     * @param callable $str
     * @param int|null $now
     * @return array[] spec, label, group (corners, genres, every), art and count.
     */
    public static function options(\stdClass $library, callable $str, ?int $now = null): array {
        $options = [];
        foreach (catalogue::corners($library, false, true) as $corner) {
            $genre = !empty($corner['genre']);
            $options[] = ['spec' => ($genre ? 'genre:' : 'corner:') . $corner['code'], 'label' => (string) $corner['label'],
                'group' => $genre ? 'genres' : 'corners', 'count' => (int) $corner['count'],
                'art' => $genre ? screens::art('genero-' . ($corner['kind'] ?? '')) : screens::art('rincon-' . $corner['code'])];
        }
        $counts = self::counts($library, $now ?? time());
        foreach (self::SPECIAL as $special) {
            $options[] = ['spec' => $special, 'label' => $str('cartilla' . $special), 'group' => 'every',
                'count' => $counts[$special], 'art' => screens::art('casilla-' . $special)];
        }
        return $options;
    }

    /**
     * How many books fill each of the squares of every library now: new ones, with a voice, and waiting to be rescued.
     *
     * @param \stdClass $library
     * @param int $now
     * @return int[] new, voice and rescued.
     */
    public static function counts(\stdClass $library, int $now): array {
        global $DB;
        $counts = [];
        foreach (self::SPECIAL as $special) {
            [$condition, $params] = self::condition($library, $special, 'r.id', 'n' . $special, $now);
            $counts[$special] = $DB->count_records_sql(
                "SELECT COUNT(1) FROM {" . self::T . "record} r WHERE r.entityid = :rentity AND $condition",
                $params + ['rentity' => $library->entityid]
            );
        }
        return $counts;
    }

    /**
     * The SQL that says a book fills one of the squares of every library now: a copy that came in within
     * premieres::DAYS (new), its reading published (voice), or a copy on the shelf for two years that nobody took home in
     * them (rescued).
     *
     * @param \stdClass $library
     * @param string $special new, voice or rescued.
     * @param string $field The record id, as alias.id.
     * @param string $name Prefix of the aliases and parameters.
     * @param int $now
     * @return array SQL and its parameters.
     */
    public static function condition(\stdClass $library, string $special, string $field, string $name, int $now): array {
        $t = self::T;
        if ($special === 'voice') {
            return ["$field IN (SELECT {$name}d.recordid FROM {{$t}digital} {$name}d
                                 WHERE {$name}d.kind = 'voice' AND {$name}d.status = 'published')", []];
        }
        $items = "SELECT {$name}i.recordid
                    FROM {{$t}item} {$name}i
                    JOIN {{$t}library} {$name}b ON {$name}b.id = {$name}i.libraryid
                   WHERE {$name}b.entityid = :{$name}e";
        if ($special === 'new') {
            return ["$field IN ($items AND {$name}i.status <> 'withdrawn' AND {$name}i.timeacquired >= :{$name}f)",
                [$name . 'e' => $library->entityid, $name . 'f' => $now - premieres::DAYS * DAYSECS]];
        }
        return ["$field IN ($items AND {$name}i.status = 'available' AND {$name}i.timeacquired < :{$name}o)
                 AND NOT EXISTS (SELECT 1
                                   FROM {{$t}loan} {$name}ln
                                   JOIN {{$t}item} {$name}x ON {$name}x.id = {$name}ln.itemid
                                  WHERE {$name}x.recordid = $field AND {$name}ln.timeloaned >= :{$name}s)",
            [$name . 'e' => $library->entityid, $name . 'o' => $now - self::RESCUED, $name . 's' => $now - self::RESCUED]];
    }

    /**
     * The nine places of a challenge that is a cartilla, each a square or '' while it is a draft; [] for a challenge of
     * so many books.
     *
     * @param \stdClass $quest
     * @return string[]
     */
    public static function squares(\stdClass $quest): array {
        $squares = json_decode((string) ($quest->squares ?? ''), true);
        if (!is_array($squares) || !$squares) {
            return [];
        }
        $places = [];
        for ($at = 0; $at < self::SIZE; $at++) {
            $spec = (string) ($squares[$at] ?? '');
            $places[] = self::valid($spec) ? $spec : '';
        }
        return $places;
    }

    /**
     * Whether a cartilla has its nine squares: a draft does not show on any card.
     *
     * @param \stdClass $quest
     * @return bool
     */
    public static function complete(\stdClass $quest): bool {
        $squares = self::squares($quest);
        return $squares && !in_array('', $squares, true);
    }

    /**
     * The squares still missing, put in their places: varied (a corner, a genre, one of every library in turn), only
     * squares with ten books or more, none twice.
     *
     * @param string[] $squares The nine places.
     * @param array[] $options As options() gives them.
     * @return string[]
     */
    public static function fill(array $squares, array $options): array {
        $groups = ['corners' => [], 'genres' => [], 'every' => []];
        foreach ($options as $option) {
            if ($option['count'] >= 10 && !in_array($option['spec'], $squares, true)) {
                $groups[$option['group']][] = $option['spec'];
            }
        }
        foreach ($groups as $group => $specs) {
            shuffle($groups[$group]);
        }
        $order = ['corners', 'genres', 'every'];
        $turn = 0;
        foreach ($squares as $at => $spec) {
            for ($try = 0; $spec === '' && $try < 3; $try++, $turn++) {
                $spec = (string) array_shift($groups[$order[$turn % 3]]);
            }
            $squares[$at] = $spec;
        }
        return $squares;
    }

    /**
     * Whether a cartilla is the stage's game: not in Infantil, where the flower is.
     *
     * @param string $stage
     * @return bool
     */
    public static function infant(string $stage): bool {
        return (bool) preg_match(self::INFANT, $stage);
    }

    /**
     * Whether a spec is one a card can carry.
     *
     * @param string $spec
     * @return bool
     */
    public static function valid(string $spec): bool {
        return in_array($spec, self::SPECIAL, true) || (bool) preg_match('/^corner:[0-9A-Z]{1,3}$/', $spec)
            || (bool) preg_match('/^genre:g\d+$/', $spec);
    }

    /**
     * A reader's card: each square filled or not (and with which book), the lines and whether it is full. Each loan in the
     * challenge's days fills at most one square, as many squares as can be filled (the reader chose the books).
     *
     * @param \stdClass $quest
     * @param \stdClass $library
     * @param int $readerid
     * @param int $now
     * @param callable $str
     * @return array squares (spec, label, filled, title), filled (how many), lines and full.
     */
    public static function card(\stdClass $quest, \stdClass $library, int $readerid, int $now, callable $str): array {
        global $DB;
        $squares = self::squares($quest);
        $labels = self::labels($library, $str);
        [$from, $params] = quests::loans_sql($quest, $now);
        $params += ['creader' => $readerid];
        $loans = $DB->get_records_sql(
            "SELECT cl.id, cl.timeloaned, ci.recordid, cr.title
               FROM {" . self::T . "loan} cl
               JOIN {" . self::T . "item} ci ON ci.id = cl.itemid
               JOIN {" . self::T . "record} cr ON cr.id = ci.recordid
              WHERE cl.id IN (SELECT l.id $from AND l.readerid = :creader)
           ORDER BY cl.timeloaned, cl.id",
            $params
        );
        // Which loans fit each square.
        $fits = [];
        foreach ($squares as $at => $spec) {
            $fits[$at] = $loans && $spec !== '' ? self::fitting($spec, $library, array_keys($loans)) : [];
        }
        $by = self::match($fits, array_keys($loans));
        $card = [];
        foreach ($squares as $at => $spec) {
            $loanid = $by[$at] ?? null;
            $card[] = ['spec' => $spec, 'label' => $labels[$spec] ?? $spec, 'filled' => $loanid !== null,
                'title' => $loanid !== null ? (string) $loans[$loanid]->title : '',
                'time' => $loanid !== null ? (int) $loans[$loanid]->timeloaned : 0];
        }
        $filled = count(array_filter($card, fn($square) => $square['filled']));
        $lines = count(self::SIZE === count($card) ? array_filter(self::LINES, fn($line) => count(array_filter(
            $line,
            fn($i) => $card[$i]['filled']
        )) === 3) : []);
        return ['squares' => $card, 'filled' => $filled, 'lines' => $lines, 'full' => $card && $filled === count($card)];
    }

    /**
     * A cartilla on a reader's card, as the page draws it: the nine squares (the drawing and the word; when filled, the
     * book and the day, as the desk's date stamp; when not, a link to the books that fill it), its lines, and what
     * Mainumby says when there is something to celebrate. Before joining, the drawings lit and nothing filled.
     *
     * @param \stdClass $quest
     * @param \stdClass $library The challenge's library.
     * @param \stdClass $reader
     * @param int $now
     * @param callable $str
     * @return array
     */
    public static function view(\stdClass $quest, \stdClass $library, \stdClass $reader, int $now, callable $str): array {
        $joined = !empty($quest->joinedid);
        $card = self::card($quest, $library, (int) $reader->id, $now, $str);
        // A full card was finished the day its ninth book went home, whenever its reader looks: as its diploma says.
        $finished = $card['full'] ? max(array_column($card['squares'], 'time')) : 0;
        quests::note_done($quest, $card['filled'], $finished ?: $now);
        $entityid = (int) $library->entityid;
        $art = self::options_art($library);
        $squares = [];
        foreach ($card['squares'] as $at => $square) {
            $filled = $joined && $square['filled'];
            $day = $filled ? zones::date($square['time'], '%e %b', $entityid) : '';
            $squares[] = ['label' => $square['label'], 'art' => $art[$square['spec']] ?? null, 'filled' => $filled,
                'title' => $filled ? format_string($square['title'], true, ['escape' => false]) : '', 'day' => $day,
                'url' => $filled ? '' : self::link($library, $square['spec']),
                'said' => $filled ? $str('cartillasaidfilled', (object) ['title' => format_string(
                    $square['title'],
                    true,
                    ['escape' => false]
                ), 'day' => $day]) : $str('cartillasaidempty')];
        }
        $lines = [];
        foreach (self::LINES as $i => $line) {
            if ($joined && count(array_filter($line, fn($at) => $squares[$at]['filled'])) === 3) {
                $lines[] = ['name' => ['f0', 'f1', 'f2', 'c0', 'c1', 'c2'][$i]];
            }
        }
        $filled = $joined ? $card['filled'] : 0;
        $full = $joined && $card['full'];
        $left = self::SIZE - $filled;
        $cheer = null;
        if ($full) {
            $cheer = ['cartillacheerfull', []];
        } else if (count($lines) === 1) {
            $cheer = ['cartillacheerline', ['left' => $left]];
        } else if ($lines) {
            $cheer = [$left === 1 ? 'cartillacheerlinesone' : 'cartillacheerlines', ['lines' => count($lines), 'left' => $left]];
        }
        $said = $cheer ? characters::say($entityid, 'herald', $cheer[0], $cheer[1], 'celebra') : null;
        $days = (int) ceil(((int) $quest->timeend - $now) / DAYSECS);
        $when = $str('questwhen', zones::span((int) $quest->timestart, (int) $quest->timeend, $entityid));
        if ($full) {
            $when = $str('cartillafullon', zones::date($finished, 'strftimedate', $entityid));
        } else if ($days >= 1 && (int) $quest->timestart <= $now) {
            $when .= ' · ' . ($days === 1 ? $str('cartillalastday') : $str('cartillaleft', $days));
        }
        return [
            'cartilla' => true,
            'squares' => $squares,
            'lines' => $lines,
            'full' => $full,
            'library' => format_string($library->name, true, ['escape' => false]),
            'when' => $when,
            'count' => $joined ? $str('cartillacount', $filled) . ($lines ? ' · ' . (count($lines) === 1
                ? $str('cartillaoneline') : $str('cartillalines', count($lines))) : '') : '',
            'cheer' => $cheer ? ['text' => $said['text'] ?? get_string($cheer[0], 'local_oksigeniaayvurenda', (object) $cheer[1]),
                'figure' => $said['figure'] ?? null] : null,
            // The diploma of the full card (Gaudí's), to keep, print and send on.
            'diploma' => $full && diploma::drawable() ? self::diploma_links($quest, $library, (int) $reader->id, $str, false)
                : null,
        ];
    }

    /**
     * Where the diploma of a reader's full card is: small to show, whole to download.
     *
     * @param \stdClass $quest
     * @param \stdClass $library The challenge's library.
     * @param int $readerid
     * @param callable $str
     * @param bool $staff For the staff, about the reader (their diploma, to print); else, to the reader (yours).
     * @return array img, url, alt and save.
     */
    public static function diploma_links(
        \stdClass $quest,
        \stdClass $library,
        int $readerid,
        callable $str,
        bool $staff
    ): array {
        $params = ['library' => $library->slug, 'quest' => (int) $quest->id, 'reader' => $readerid];
        $name = format_string($quest->name, true, ['escape' => false]);
        return [
            'img' => (new \moodle_url('/local/oksigeniaayvurenda/diploma.php', $params + ['small' => 1]))->out(false),
            'url' => (new \moodle_url('/local/oksigeniaayvurenda/diploma.php', $params + ['download' => 1]))->out(false),
            'alt' => $str($staff ? 'profilediplomaalt' : 'diplomacardalt', $name),
            'save' => $str($staff ? 'profilediplomaprint' : 'diplomadownload'),
        ];
    }

    /**
     * Where the books that fill a square are: its corner or genre on the shelves, the books with a voice, or a list of
     * the new ones or of those waiting to be rescued.
     *
     * @param \stdClass $library
     * @param string $spec
     * @return string
     */
    public static function link(\stdClass $library, string $spec): string {
        $params = ['library' => $library->slug];
        if ($spec === 'voice') {
            $script = 'ayvurande.php';
        } else if (in_array($spec, self::SPECIAL, true)) {
            $script = 'search.php';
            $params['square'] = $spec;
        } else {
            $script = 'search.php';
            $params['corner'] = substr($spec, strpos($spec, ':') + 1);
        }
        return (new \moodle_url('/local/oksigeniaayvurenda/' . $script, $params))->out(false);
    }

    /**
     * The drawing of each square a library can carry, without counting its books.
     *
     * @param \stdClass $library
     * @return array Spec => URL or null.
     */
    private static function options_art(\stdClass $library): array {
        return array_map(fn($name) => screens::art($name), self::art_names($library));
    }

    /**
     * The name of the drawing of each square a library can carry, in the art package (its pages and the diploma's copies).
     *
     * @param \stdClass $library
     * @return string[] Spec => name, without its extension.
     */
    public static function art_names(\stdClass $library): array {
        $names = [];
        foreach (catalogue::corners($library, true, true) as $corner) {
            $names[(!empty($corner['genre']) ? 'genre:' : 'corner:') . $corner['code']] = !empty($corner['genre'])
                ? 'genero-' . ($corner['kind'] ?? '') : 'rincon-' . $corner['code'];
        }
        foreach (self::SPECIAL as $special) {
            $names[$special] = 'casilla-' . $special;
        }
        return $names;
    }

    /**
     * The name of each square a library can carry, without counting its books.
     *
     * @param \stdClass $library
     * @param callable $str
     * @return string[] Spec => label.
     */
    public static function labels(\stdClass $library, callable $str): array {
        $labels = [];
        foreach (catalogue::corners($library, true, true) as $corner) {
            $labels[(!empty($corner['genre']) ? 'genre:' : 'corner:') . $corner['code']] = (string) $corner['label'];
        }
        foreach (self::SPECIAL as $special) {
            $labels[$special] = $str('cartilla' . $special);
        }
        return $labels;
    }

    /**
     * How a cartilla goes, all added up, never with names: who plays, the lines and full cards, and the square that is
     * filled the least.
     *
     * @param \stdClass $quest
     * @param \stdClass $library
     * @param int $now
     * @param callable $str
     * @return array players, lines, full, hardest (label and count) or null.
     */
    public static function stats(\stdClass $quest, \stdClass $library, int $now, callable $str): array {
        global $DB;
        $readers = $DB->get_fieldset_select(self::T . 'questreader', 'readerid', 'questid = :questid', ['questid' => $quest->id]);
        $lines = 0;
        $full = 0;
        $filled = array_fill(0, self::SIZE, 0);
        $squares = self::squares($quest);
        foreach ($readers as $readerid) {
            $card = self::card($quest, $library, (int) $readerid, $now, $str);
            $lines += $card['lines'];
            $full += $card['full'] ? 1 : 0;
            foreach ($card['squares'] as $at => $square) {
                $filled[$at] += $square['filled'] ? 1 : 0;
            }
        }
        $hardest = null;
        if ($readers && $squares) {
            $at = array_search(min($filled), $filled, true);
            $hardest = ['label' => self::labels($library, $str)[$squares[$at]] ?? $squares[$at], 'count' => $filled[$at]];
        }
        return ['players' => count($readers), 'lines' => $lines, 'full' => $full, 'hardest' => $hardest];
    }

    /**
     * The loans, of those given, whose book fits a square.
     *
     * @param string $spec
     * @param \stdClass $library
     * @param int[] $loanids
     * @return int[]
     */
    private static function fitting(string $spec, \stdClass $library, array $loanids): array {
        global $DB;
        [$in, $params] = $DB->get_in_or_equal($loanids, SQL_PARAMS_NAMED, 'fl');
        $base = "SELECT l.id
                   FROM {" . self::T . "loan} l
                   JOIN {" . self::T . "item} i ON i.id = l.itemid
                  WHERE l.id $in";
        if ($spec === 'new') {
            $sql = "$base AND i.timeacquired IS NOT NULL AND i.timeacquired >= l.timeloaned - :fresh";
            $params['fresh'] = premieres::DAYS * DAYSECS;
        } else if ($spec === 'voice') {
            $sql = "$base AND EXISTS (SELECT 1 FROM {" . self::T . "digital} d
                                       WHERE d.recordid = i.recordid AND d.kind = 'voice' AND d.status = 'published')";
        } else if ($spec === 'rescued') {
            $sql = "$base AND NOT EXISTS (SELECT 1
                                           FROM {" . self::T . "loan} o
                                           JOIN {" . self::T . "item} oi ON oi.id = o.itemid
                                          WHERE oi.recordid = i.recordid AND o.id <> l.id AND o.timeloaned < l.timeloaned
                                                AND o.timeloaned >= l.timeloaned - :rescued)
                          AND i.timeacquired IS NOT NULL AND i.timeacquired < l.timeloaned - :old";
            $params += ['rescued' => self::RESCUED, 'old' => self::RESCUED];
        } else {
            // A corner or a genre as its shelf has it: a book of a genre is in the genre, not in the corner of stories.
            $condition = catalogue::corner_condition($library, substr($spec, strpos($spec, ':') + 1), 'i.recordid', 'cc');
            if (!$condition) {
                return [];
            }
            $sql = "$base AND {$condition[0]}";
            $params += $condition[1];
        }
        return array_map('intval', $DB->get_fieldset_sql($sql, $params));
    }

    /**
     * As many squares filled as can be, each by one loan and each loan in one square, the earlier loans first.
     *
     * @param array $fits Square => the loans that fit it.
     * @param int[] $loanids In the order they were made.
     * @return int[] Square => the loan that fills it.
     */
    private static function match(array $fits, array $loanids): array {
        $order = array_flip($loanids);
        $owner = [];
        $try = function (int $square, array &$seen) use (&$try, &$owner, $fits, $order): bool {
            $loans = $fits[$square] ?? [];
            usort($loans, fn($a, $b) => ($order[$a] ?? 0) <=> ($order[$b] ?? 0));
            foreach ($loans as $loan) {
                if (isset($seen[$loan])) {
                    continue;
                }
                $seen[$loan] = true;
                if (!isset($owner[$loan]) || $try($owner[$loan], $seen)) {
                    $owner[$loan] = $square;
                    return true;
                }
            }
            return false;
        };
        foreach (array_keys($fits) as $square) {
            $seen = [];
            $try($square, $seen);
        }
        return array_flip(array_map('intval', $owner));
    }
}
