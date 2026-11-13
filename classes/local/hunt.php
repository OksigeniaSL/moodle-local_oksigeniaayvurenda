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
 * Find the hidden book: a game in a room of the library one visits.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * The memory hides a real book of the room's corner that is free to take home, and gives its label as the clue (and its
 * cover, to the young); it is found on the room's shelf, among the books really beside it, in their order. A wrong
 * spine says which way to go; the book found comes out with where it stands. Nothing is kept: the book of the day comes
 * from the day, the reader and the turn.
 */
final class hunt {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var int Books on each side of the hidden one. */
    public const EACH = 6;

    /** @var int The books on each side for a reader of the flower: a shorter shelf for the youngest (JV-D11). */
    public const EACH_YOUNG = 4;

    /** @var int Of the books that may be hidden, how many are looked at around the day's one, to prefer one with a cover. */
    private const NEAR = 12;

    /**
     * The book hidden for someone in a corner: free to take home, with its label, better with a cover and better one
     * they have not taken home before. The same all day for the same reader and turn.
     *
     * @param \stdClass $library
     * @param string $corner The top class.
     * @param int $readerid 0 for someone without a card.
     * @param int $turn Another book with each turn.
     * @param int|null $now
     * @return int|null The record, or null when the corner has no book to hide.
     */
    public static function target(\stdClass $library, string $corner, int $readerid, int $turn, ?int $now = null): ?int {
        global $DB;
        if (!preg_match('/^\d$/', $corner)) {
            return null;
        }
        stacks::fill((int) $library->id);
        $base = "FROM {" . self::T . "item} i
                WHERE i.libraryid = :libraryid AND i.status = 'available' AND i.itemtype <> 'reference'
                      AND i.shelfkey IS NOT NULL AND i.shelfkey <> '' AND i.callnumber1 IS NOT NULL AND i.callnumber1 <> ''
                      AND i.recordid IN (SELECT ra.recordid
                                           FROM {" . self::T . "recauth} ra
                                           JOIN {" . self::T . "authority} a ON a.id = ra.authorityid
                                          WHERE a.entityid = :entityid AND a.kind = 'class' AND " .
            catalogue::class_condition('a.code', $corner, 'code')[0] . ")";
        $params = ['libraryid' => $library->id, 'entityid' => $library->entityid]
            + catalogue::class_condition('a.code', $corner, 'code')[1];
        $unread = '';
        if ($readerid) {
            $unread = " AND NOT EXISTS (SELECT 1
                                          FROM {" . self::T . "loan} l
                                          JOIN {" . self::T . "item} li ON li.id = l.itemid
                                         WHERE l.readerid = :readerid AND li.recordid = i.recordid)";
            $params['readerid'] = $readerid;
        }
        // Better one not read yet; if they read them all, any.
        foreach ($unread !== '' ? [$unread, ''] : [''] as $more) {
            $count = (int) $DB->count_records_sql('SELECT COUNT(DISTINCT i.recordid) ' . $base . $more, $params);
            if (!$count) {
                continue;
            }
            $day = date('Ymd', $now ?? time());
            $at = (int) (sprintf('%u', crc32($day . ':' . $library->id . ':' . $corner . ':' . $readerid . ':' . $turn)) % $count);
            // Only the few around the day's one, never the whole corner (thousands, in a real library).
            $sql = 'SELECT DISTINCT i.recordid ' . $base . $more . ' ORDER BY i.recordid';
            $near = array_map('intval', array_keys($DB->get_records_sql($sql, $params, $at, self::NEAR)));
            if (count($near) < min(self::NEAR, $count)) {
                $near = array_merge($near, array_map('intval', array_keys($DB->get_records_sql(
                    $sql,
                    $params,
                    0,
                    min(self::NEAR, $count) - count($near)
                ))));
            }
            $covered = array_keys(covers::urls($near));
            foreach ($near as $id) {
                if (in_array($id, $covered, true)) {
                    return $id;
                }
            }
            return $near[0];
        }
        return null;
    }

    /**
     * The game of a hidden book: its clue and the shelf it is found on.
     *
     * @param \stdClass $library
     * @param int $recordid
     * @param bool $young A reader of the flower: the cover too, and a shorter shelf.
     * @param bool $public Seen through the window: the links lead there.
     * @param bool $grown A reader past the flower: the clue is the title and the author, and the label to work out.
     * @return array|null target, title, author, cover, lines, place, young, grown and spines; null if the book is not on a
     *     shelf here.
     */
    public static function game(\stdClass $library, int $recordid, bool $young, bool $public, bool $grown = false): ?array {
        $beside = stacks::neighbours($library, $recordid, $young ? self::EACH_YOUNG : self::EACH);
        if (!$beside) {
            return null;
        }
        $spines = $public ? screens::public_spines($beside['spines'], $library) : $beside['spines'];
        $target = null;
        foreach ($spines as &$spine) {
            // The book hidden is not told apart from the others.
            $spine['here'] = false;
            if ($spine['id'] === $recordid) {
                $target = $spine;
            }
        }
        unset($spine);
        if (!$target || $target['out'] || !$target['lines']) {
            return null;
        }
        // A twin on the shelf, with the very same label, makes the game a guess: not this book, then (D2).
        foreach ($spines as $spine) {
            if ($spine['id'] !== $recordid && !$spine['out'] && $spine['lines'] === $target['lines']) {
                return null;
            }
        }
        // In the game the spines keep their title for the screen reader, not in a tooltip to match with the cover (D15);
        // for a grown reader, not on the spine either: they come to it by its label (Gaudí, JV-D11).
        $cards = $grown ? catalogue::cards_of([$recordid], $library) : [];
        $author = $cards ? (string) reset($cards)['authors'] : '';
        foreach ($spines as &$spine) {
            $spine['notip'] = true;
            $spine['notitle'] = $grown && $author !== '';
        }
        unset($spine);
        return ['target' => $recordid, 'title' => $target['title'], 'cover' => $young ? $target['cover'] : null,
            'lines' => $target['lines'], 'place' => $target['place'], 'young' => $young,
            // A grown reader works the label out from the title and the author, as on a real shelf; without an author,
            // the label.
            'grown' => $grown && $author !== '', 'author' => $author,
            'spines' => screens::spines($spines)];
    }

    /**
     * The game for a room of the visit, for whoever is visiting: the day's book of the room's corner.
     *
     * @param \stdClass $library
     * @param string $corner
     * @param bool $public
     * @param int $turn
     * @return array|null
     */
    public static function for_visit(\stdClass $library, string $corner, bool $public, int $turn): ?array {
        global $DB, $USER;
        $reader = !$public && isloggedin() && !isguestuser() ? $DB->get_record(
            self::T . 'reader',
            ['entityid' => $library->entityid, 'userid' => $USER->id, 'status' => 'active']
        ) : null;
        $look = $reader ? cards::look_of($reader) : '';
        $young = $look === 'flower';
        // The day's book, or the next turns' when it has a twin on its shelf: still the same for the same day and reader.
        for ($try = 0; $try < 5; $try++) {
            $recordid = self::target($library, $corner, $reader ? (int) $reader->id : 0, $turn + $try);
            if (!$recordid) {
                return null;
            }
            $game = self::game($library, $recordid, $young, $public, $reader && !$young);
            if ($game) {
                // Who plays, for «Llévatelo»: the book found is kept for a reader with a card.
                return $game + ['readerid' => $reader ? (int) $reader->id : 0];
            }
        }
        return null;
    }
}
