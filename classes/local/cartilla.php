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

    /** @var int[][] The rows and columns of the card. */
    public const LINES = [[0, 1, 2], [3, 4, 5], [6, 7, 8], [0, 3, 6], [1, 4, 7], [2, 5, 8]];

    /**
     * The squares a library can put on a card: its corners, its genres and the three of every library.
     *
     * @param \stdClass $library
     * @param callable $str
     * @return array[] spec and label.
     */
    public static function options(\stdClass $library, callable $str): array {
        $options = [];
        foreach (catalogue::corners($library, false, false) as $corner) {
            $options[] = ['spec' => 'corner:' . $corner['code'], 'label' => (string) $corner['label']];
        }
        foreach (genres::of($library) as $genre) {
            $options[] = ['spec' => 'genre:' . $genre['code'], 'label' => $genre['label']];
        }
        foreach (self::SPECIAL as $special) {
            $options[] = ['spec' => $special, 'label' => $str('cartilla' . $special)];
        }
        return $options;
    }

    /**
     * The squares of a challenge that is a cartilla, in their order; [] for a challenge of so many books.
     *
     * @param \stdClass $quest
     * @return string[]
     */
    public static function squares(\stdClass $quest): array {
        $squares = json_decode((string) ($quest->squares ?? ''), true);
        return is_array($squares) ? array_values(array_slice(array_filter(
            array_map('strval', $squares),
            fn($spec) => self::valid($spec)
        ), 0, self::SIZE)) : [];
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
        $labels = array_column(self::options($library, $str), 'label', 'spec');
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
            $fits[$at] = $loans ? self::fitting($spec, $library, array_keys($loans)) : [];
        }
        $by = self::match($fits, array_keys($loans));
        $card = [];
        foreach ($squares as $at => $spec) {
            $loanid = $by[$at] ?? null;
            $card[] = ['spec' => $spec, 'label' => $labels[$spec] ?? $spec, 'filled' => $loanid !== null,
                'title' => $loanid !== null ? (string) $loans[$loanid]->title : ''];
        }
        $filled = count(array_filter($card, fn($square) => $square['filled']));
        $lines = count(self::SIZE === count($card) ? array_filter(self::LINES, fn($line) => count(array_filter(
            $line,
            fn($i) => $card[$i]['filled']
        )) === 3) : []);
        return ['squares' => $card, 'filled' => $filled, 'lines' => $lines, 'full' => $card && $filled === count($card)];
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
        } else if (str_starts_with($spec, 'corner:')) {
            [$class, $classparams] = catalogue::class_condition('ca.code', substr($spec, 7), 'cc');
            $sql = "$base AND i.recordid IN (SELECT cra.recordid
                                              FROM {" . self::T . "recauth} cra
                                              JOIN {" . self::T . "authority} ca ON ca.id = cra.authorityid
                                             WHERE ca.entityid = :centity AND ca.kind = 'class' AND $class)";
            $params += $classparams + ['centity' => $library->entityid];
        } else {
            $genre = null;
            foreach (genres::of($library) as $one) {
                if ('genre:' . $one['code'] === $spec) {
                    $genre = $one;
                }
            }
            if (!$genre) {
                return [];
            }
            [$condition, $genreparams] = genres::condition($library, $genre, 'i.recordid', 'cg');
            $sql = "$base AND $condition";
            $params += $genreparams;
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
