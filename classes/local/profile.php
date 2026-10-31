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
 * A reader's record, for the library staff.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

use local_oksigeniaayvurenda\core\barcode\code39;

/**
 * What the library staff see of a reader, clear at a glance: their photo, so the child is known even without the card,
 * who they are this year, what they have now, what they took home this year and before, and the staff's own notes.
 * Only for the staff: never on a page other readers or the public see.
 */
final class profile {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var int How many of this year's books the record lists. */
    private const LISTED = 12;

    /** @var int The longest notes the staff keep of a reader. */
    public const NOTES = 1000;

    /**
     * Whether a centre shows its readers' photos to the staff (on, unless it turned them off).
     *
     * @param int $entityid
     * @return bool
     */
    public static function photos_on(int $entityid): bool {
        global $DB;
        $settings = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        return !array_key_exists('photos', $settings) || !empty($settings['photos']);
    }

    /**
     * The photo of a reader, from their Moodle account's picture; '' without one or where the centre does not show them.
     *
     * @param \stdClass $reader With userid and entityid.
     * @param \stdClass|null $user Their account, if already at hand.
     * @return string
     */
    public static function photo(\stdClass $reader, ?\stdClass $user = null): string {
        global $PAGE;
        if (empty($reader->userid) || !self::photos_on((int) $reader->entityid)) {
            return '';
        }
        $user = $user ?? \core_user::get_user((int) $reader->userid);
        if (!$user || !empty($user->deleted) || empty($user->picture)) {
            return '';
        }
        $picture = new \user_picture($user);
        $picture->size = 200;
        return $picture->get_url($PAGE)->out(false);
    }

    /**
     * The first letter of a name, for the round where there is no photo.
     *
     * @param string $name
     * @return string
     */
    public static function initial(string $name): string {
        return \core_text::strtoupper(\core_text::substr(trim($name), 0, 1)) ?: '·';
    }

    /**
     * The record of a reader of this library's centre.
     *
     * @param \stdClass $library
     * @param int $readerid
     * @param int $now
     * @return array|null Null when the reader is not of this centre.
     */
    public static function of(\stdClass $library, int $readerid, int $now): ?array {
        global $DB;
        $reader = $DB->get_record(self::T . 'reader', ['id' => $readerid, 'entityid' => $library->entityid]);
        if (!$reader) {
            return null;
        }
        $user = $reader->userid ? \core_user::get_user((int) $reader->userid) : null;
        $name = $user ? fullname($user) : trim($reader->firstname . ' ' . $reader->lastname);
        $name = $name !== '' ? $name : (string) $reader->cardcode;
        $year = years::around((int) $library->entityid, $now);
        $member = $DB->get_record(
            self::T . 'member',
            ['readerid' => $readerid, 'yearid' => $year->id],
            'groupname, stage',
            IGNORE_MULTIPLE
        );
        $group = $member ? (string) $member->groupname : '';
        $tutorid = $group !== '' ? (int) $DB->get_field(self::T . 'tutor', 'userid', ['yearid' => $year->id,
            'groupname' => $group]) : 0;
        $tutor = $tutorid ? \core_user::get_user($tutorid) : null;
        $look = cards::look_of($reader, $now);
        $flower = garden::flower($reader, $year);
        return [
            'id' => $readerid,
            'name' => $name,
            'photo' => self::photo($reader, $user),
            'initial' => self::initial($name),
            'type' => (string) $reader->readertype,
            'group' => $group,
            'stage' => $member ? (string) $member->stage : '',
            'tutor' => $tutor ? fullname($tutor) : '',
            'email' => $reader->readertype !== 'student' ? (string) ($user->email ?? $reader->email ?? '') : '',
            'cardcode' => (string) $reader->cardcode,
            'barcode' => code39::can((string) $reader->cardcode) ? code39::svg((string) $reader->cardcode, 40, false) : '',
            'active' => $reader->status === 'active',
            'since' => (int) $reader->timecreated,
            'look' => $look,
            'flower' => $flower,
            'notes' => (string) ($reader->notes ?? ''),
            'loans' => self::loans($library, $readerid, $now),
            'holds' => holds::of_reader($readerid, $now),
            'year' => $year->name,
            'read' => self::read($readerid, (int) $year->id),
            'past' => self::past($readerid, (int) $year->id),
        ];
    }

    /**
     * Saves the staff's notes of a reader of this centre.
     *
     * @param \stdClass $library
     * @param int $readerid
     * @param string $notes
     */
    public static function save_notes(\stdClass $library, int $readerid, string $notes): void {
        global $DB;
        $notes = trim(\core_text::substr($notes, 0, self::NOTES));
        $DB->set_field_select(
            self::T . 'reader',
            'notes',
            $notes !== '' ? $notes : null,
            'id = :id AND entityid = :entityid',
            ['id' => $readerid, 'entityid' => $library->entityid]
        );
    }

    /**
     * What a reader has out now, in any library of the centre, the most urgent first.
     *
     * @param \stdClass $library
     * @param int $readerid
     * @param int $now
     * @return array[] recordid, title, library, due, late (days).
     */
    private static function loans(\stdClass $library, int $readerid, int $now): array {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT l.id, l.timedue, i.recordid, r.title, b.name AS library
               FROM {" . self::T . "loan} l
               JOIN {" . self::T . "item} i ON i.id = l.itemid
               JOIN {" . self::T . "record} r ON r.id = i.recordid
               JOIN {" . self::T . "library} b ON b.id = l.libraryid
              WHERE l.readerid = :readerid AND l.timereturned IS NULL AND b.entityid = :entityid
           ORDER BY l.timedue, l.id",
            ['readerid' => $readerid, 'entityid' => $library->entityid]
        );
        return array_values(array_map(fn($r) => ['recordid' => (int) $r->recordid, 'title' => $r->title,
            'library' => format_string($r->library), 'due' => (int) $r->timedue,
            'late' => max(0, (int) floor(($now - (int) $r->timedue) / DAYSECS))], $rows));
    }

    /**
     * What a reader took home this year, the last first (not the textbooks).
     *
     * @param int $readerid
     * @param int $yearid
     * @return array total, books (recordid, title, when, note).
     */
    private static function read(int $readerid, int $yearid): array {
        global $DB;
        $where = "l.readerid = :readerid AND l.yearid = :yearid AND " . bank::reading('l.libraryid');
        $params = ['readerid' => $readerid, 'yearid' => $yearid];
        $total = (int) $DB->count_records_sql("SELECT COUNT(1) FROM {" . self::T . "loan} l WHERE $where", $params);
        $rows = $DB->get_records_sql(
            "SELECT l.id, l.timeloaned, l.returnnote, i.recordid, r.title
               FROM {" . self::T . "loan} l
               JOIN {" . self::T . "item} i ON i.id = l.itemid
               JOIN {" . self::T . "record} r ON r.id = i.recordid
              WHERE $where
           ORDER BY l.timeloaned DESC, l.id DESC",
            $params,
            0,
            self::LISTED
        );
        return ['total' => $total, 'books' => array_values(array_map(fn($r) => ['recordid' => (int) $r->recordid,
            'title' => $r->title, 'when' => (int) $r->timeloaned, 'note' => (string) ($r->returnnote ?? '')], $rows))];
    }

    /**
     * How many books a reader took home in each year before, the last first.
     *
     * @param int $readerid
     * @param int $yearid This year, left out.
     * @return array[] year, loans.
     */
    private static function past(int $readerid, int $yearid): array {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT y.id, y.name, y.timestart, COUNT(l.id) AS loans
               FROM {" . self::T . "loan} l
               JOIN {" . self::T . "year} y ON y.id = l.yearid
              WHERE l.readerid = :readerid AND y.id <> :yearid AND " . bank::reading('l.libraryid') . "
           GROUP BY y.id, y.name, y.timestart
           ORDER BY y.timestart DESC",
            ['readerid' => $readerid, 'yearid' => $yearid]
        );
        return array_values(array_map(fn($r) => ['year' => $r->name, 'loans' => (int) $r->loans], $rows));
    }
}
