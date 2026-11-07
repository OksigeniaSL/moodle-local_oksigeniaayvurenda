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

    /** @var int How many loans a page of a reader's history shows. */
    public const HISTORY = 100;

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
     * Whether a reader without a photo gets a bichito here: not in the sober skins, where an initial is what fits.
     *
     * @param int $entityid
     * @return bool
     */
    public static function creatures_on(int $entityid): bool {
        return !in_array(skins::of($entityid), ['university', 'company'], true);
    }

    /**
     * A bichito for a reader without a photo, as an image: always the same one for each reader, and different for brothers
     * and sisters, whose ids follow one another.
     *
     * @param int $readerid
     * @return string The address of the image, or '' if the drawings are not there.
     */
    public static function creature(int $readerid): string {
        global $CFG;
        static $symbols = null;
        if ($symbols === null) {
            $file = $CFG->dirroot . '/local/oksigeniaayvurenda/pix/bichos.svg';
            $sprite = is_readable($file) ? (string) file_get_contents($file) : '';
            preg_match_all('/<symbol id="bicho-\d+"[^>]*>(.*?)<\/symbol>/s', $sprite, $found);
            $symbols = $found[1];
        }
        if (!$symbols) {
            return '';
        }
        return 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
            . $symbols[$readerid % count($symbols)] . '</svg>');
    }

    /**
     * The face of a reader: their photo, or a bichito where the skin of the centre has them.
     *
     * @param \stdClass $reader With id, userid and entityid.
     * @param \stdClass|null $user Their account, if already at hand.
     * @return string The address of the image, or ''.
     */
    public static function photo(\stdClass $reader, ?\stdClass $user = null): string {
        $picture = self::picture($reader, $user);
        if ($picture !== '' || empty($reader->id) || !self::creatures_on((int) $reader->entityid)) {
            return $picture;
        }
        return self::creature((int) $reader->id);
    }

    /**
     * The photo of a reader, from their Moodle account's picture; '' without one or where the centre does not show them.
     *
     * @param \stdClass $reader With userid and entityid.
     * @param \stdClass|null $user Their account, if already at hand.
     * @return string
     */
    public static function picture(\stdClass $reader, ?\stdClass $user = null): string {
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
        if (!$reader || $reader->status === 'deleted') {
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
        $tutoring = $reader->userid ? $DB->get_fieldset_select(
            self::T . 'tutor',
            'groupname',
            'yearid = :yearid AND userid = :userid',
            ['yearid' => $year->id, 'userid' => $reader->userid]
        ) : [];
        return [
            'id' => $readerid,
            'name' => $name,
            'photo' => self::photo($reader, $user),
            'initial' => self::initial($name),
            'type' => (string) $reader->readertype,
            'group' => $group,
            'stage' => $member ? (string) $member->stage : '',
            'tutor' => $tutor ? fullname($tutor) : '',
            'tutoring' => $tutoring,
            'hasaccount' => !empty($reader->userid),
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
     * Gives a reader another card, for one that was lost: the old code stops working everywhere at once, and everything
     * else stays theirs.
     *
     * @param \stdClass $library
     * @param int $readerid
     * @return string|null The new code, or null if the reader is not of this centre.
     */
    public static function new_card(\stdClass $library, int $readerid): ?string {
        global $DB;
        $entityid = (int) $library->entityid;
        if (!$DB->record_exists(self::T . 'reader', ['id' => $readerid, 'entityid' => $entityid])) {
            return null;
        }
        $settings = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        $codes = $DB->get_fieldset_select(self::T . 'reader', 'cardcode', 'entityid = :entityid', ['entityid' => $entityid]);
        $code = \local_oksigeniaayvurenda\core\readers\card_codes::next(
            array_merge($codes, cataloguer::below_first_code($entityid, 'card')),
            1,
            !empty($settings['controlletter'])
        )[0];
        $DB->update_record(self::T . 'reader', (object) ['id' => $readerid, 'cardcode' => $code, 'timemodified' => time()]);
        \local_oksigeniaayvurenda\event\reader_card_replaced::fire($entityid, $readerid);
        return $code;
    }

    /**
     * What the staff can change of a reader, for the form: what their account or the centre's platform brings stays
     * as it is, because the night would put it back.
     *
     * @param \stdClass $library
     * @param \stdClass $reader
     * @param int $now
     * @param bool $cantutor Whether whoever looks can say who tutors a group.
     * @return array firstname, lastname, email, type, types, group, groups, lockedgroup, istutor, tutorgroups, cantutor.
     */
    public static function form(\stdClass $library, \stdClass $reader, int $now, bool $cantutor): array {
        global $DB;
        $year = years::around((int) $library->entityid, $now);
        $member = $DB->get_record(self::T . 'member', ['readerid' => $reader->id, 'yearid' => $year->id], '*', IGNORE_MULTIPLE);
        $group = $member ? (string) $member->groupname : '';
        $tutors = $DB->get_records_menu(self::T . 'tutor', ['yearid' => $year->id], '', 'groupname, userid');
        $mine = array_keys(array_filter($tutors, fn($userid) => (int) $userid === (int) $reader->userid));
        $names = tutors::candidates((int) $library->entityid);
        $groups = array_column(readers::groups($library, $now), 'name');
        return [
            'firstname' => (string) $reader->firstname,
            'lastname' => (string) $reader->lastname,
            'email' => (string) $reader->email,
            'type' => (string) $reader->readertype,
            'types' => in_array($reader->readertype, readers::KINDS, true) ? readers::KINDS : [],
            'group' => $group,
            'groups' => $groups,
            // A group the platform brought comes back every night: it is changed there.
            'lockedgroup' => $member && !in_array((string) $member->source, ['', 'manual'], true) ? $group : '',
            'canteach' => !empty($reader->userid) && $reader->readertype !== 'student' && $cantutor,
            'tutorof' => (string) ($mine[0] ?? ''),
            'tutorgroups' => array_map(fn($name) => ['name' => $name,
                'who' => !empty($tutors[$name]) && (int) $tutors[$name] !== (int) $reader->userid
                    ? (string) ($names[(int) $tutors[$name]] ?? '') : ''], $groups),
        ];
    }

    /**
     * Changes what the staff can change of a reader of this centre.
     *
     * @param \stdClass $library
     * @param int $readerid
     * @param array $form firstname, lastname, email, type, group; tutorof when whoever saves can say who tutors a group.
     * @param int $now
     * @param bool $cantutor Whether whoever saves can say who tutors a group.
     * @return string The key of what to say: profiledatasaved, or what stopped it.
     */
    public static function change(\stdClass $library, int $readerid, array $form, int $now, bool $cantutor): string {
        global $DB;
        $entityid = (int) $library->entityid;
        $reader = $DB->get_record(self::T . 'reader', ['id' => $readerid, 'entityid' => $entityid]);
        if (!$reader || $reader->status === 'deleted') {
            return 'readernothere';
        }
        $row = (object) ['id' => $readerid, 'timemodified' => $now];
        // The name and the address of someone with an account are the account's.
        if (empty($reader->userid)) {
            $row->firstname = \core_text::substr(trim($form['firstname'] ?? ''), 0, 255);
            $row->lastname = \core_text::substr(trim($form['lastname'] ?? ''), 0, 255);
            $email = trim($form['email'] ?? '');
            if ($row->firstname === '' && $row->lastname === '') {
                return 'profiledatanoname';
            }
            if ($email !== '' && !validate_email($email)) {
                return 'profiledatabademail';
            }
            $row->email = $email !== '' ? $email : null;
        }
        $type = (string) ($form['type'] ?? '');
        if (in_array($type, readers::KINDS, true) && in_array($reader->readertype, readers::KINDS, true)) {
            $row->readertype = $type;
        }
        $form = $form + ['group' => '', 'tutorof' => ''];
        $transaction = $DB->start_delegated_transaction();
        $DB->update_record(self::T . 'reader', $row);
        $view = self::form($library, $reader, $now, $cantutor);
        if ($view['lockedgroup'] === '' && (string) $form['group'] !== $view['group']) {
            self::move($library, $readerid, (string) $form['group'], $now);
        }
        if ($view['canteach'] && (string) $form['tutorof'] !== $view['tutorof']) {
            $year = years::around($entityid, $now);
            if ($view['tutorof'] !== '') {
                tutors::set((int) $year->id, $view['tutorof'], 0);
            }
            if ((string) $form['tutorof'] !== '' && in_array($form['tutorof'], $view['groups'], true)) {
                tutors::set((int) $year->id, (string) $form['tutorof'], (int) $reader->userid);
            }
        }
        $transaction->allow_commit();
        \local_oksigeniaayvurenda\event\reader_changed::fire($entityid, $readerid, ['what' => 'data']);
        return 'profiledatasaved';
    }

    /**
     * Puts a reader in one of this year's groups, or in none. The row says it was done by hand: from where the group
     * would have come, nothing touches it.
     *
     * @param \stdClass $library
     * @param int $readerid
     * @param string $group
     * @param int $now
     */
    private static function move(\stdClass $library, int $readerid, string $group, int $now): void {
        global $DB;
        $year = years::around((int) $library->entityid, $now);
        $DB->delete_records(self::T . 'member', ['readerid' => $readerid, 'yearid' => $year->id]);
        foreach (readers::groups($library, $now) as $known) {
            if ($known['name'] === $group) {
                $DB->insert_record(self::T . 'member', ['readerid' => $readerid, 'yearid' => $year->id, 'groupname' => $group,
                    'stage' => $known['stage'] !== '' ? $known['stage'] : null, 'source' => 'manual', 'timecreated' => $now]);
                return;
            }
        }
    }

    /**
     * Someone who stopped coming leaves the lists and keeps everything; someone who comes back is as they were.
     *
     * @param \stdClass $library
     * @param int $readerid
     * @param bool $gone
     */
    public static function set_gone(\stdClass $library, int $readerid, bool $gone): void {
        global $DB;
        $entityid = (int) $library->entityid;
        $from = $gone ? 'active' : 'left';
        if (!$DB->record_exists(self::T . 'reader', ['id' => $readerid, 'entityid' => $entityid, 'status' => $from])) {
            return;
        }
        $DB->update_record(self::T . 'reader', (object) ['id' => $readerid, 'status' => $gone ? 'left' : 'active',
            'timemodified' => time()]);
        \local_oksigeniaayvurenda\event\reader_changed::fire($entityid, $readerid, ['what' => $gone ? 'left' : 'back']);
    }

    /**
     * Everything a reader took out, the last first: the books of the textbook bank too, because this is the record of
     * what they had, not of what they read.
     *
     * @param \stdClass $library
     * @param int $readerid
     * @param int $from
     * @param bool $onlyhere Only what this library lent.
     * @return array[] recordid, title, library, year, lent, due, returned, note.
     */
    public static function history(\stdClass $library, int $readerid, int $from = 0, bool $onlyhere = false): array {
        global $DB;
        $params = ['readerid' => $readerid, 'entityid' => $library->entityid];
        $here = '';
        if ($onlyhere) {
            $here = ' AND l.libraryid = :libraryid';
            $params['libraryid'] = $library->id;
        }
        $where = "l.readerid = :readerid AND b.entityid = :entityid$here";
        $join = "JOIN {" . self::T . "library} b ON b.id = l.libraryid";
        $rows = $DB->get_records_sql(
            "SELECT l.id, l.timeloaned, l.timedue, l.timereturned, l.returnnote, i.recordid, r.title, b.name AS library,
                    y.name AS year
               FROM {" . self::T . "loan} l
               $join
               JOIN {" . self::T . "item} i ON i.id = l.itemid
               JOIN {" . self::T . "record} r ON r.id = i.recordid
          LEFT JOIN {" . self::T . "year} y ON y.id = l.yearid
              WHERE $where
           ORDER BY l.timeloaned DESC, l.id DESC",
            $params,
            $from,
            self::HISTORY
        );
        return array_values(array_map(fn($r) => ['recordid' => (int) $r->recordid, 'title' => $r->title,
            'library' => format_string($r->library, true, ['escape' => false]), 'year' => (string) $r->year,
            'lent' => (int) $r->timeloaned, 'due' => (int) $r->timedue, 'returned' => (int) $r->timereturned,
            'note' => (string) ($r->returnnote ?? '')], $rows));
    }

    /**
     * How many loans a reader's history has.
     *
     * @param \stdClass $library
     * @param int $readerid
     * @param bool $onlyhere Only what this library lent.
     * @return int
     */
    public static function history_total(\stdClass $library, int $readerid, bool $onlyhere = false): int {
        global $DB;
        return (int) $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {" . self::T . "loan} l
               JOIN {" . self::T . "library} b ON b.id = l.libraryid
              WHERE l.readerid = :readerid AND b.entityid = :entityid" . ($onlyhere ? ' AND l.libraryid = :libraryid' : ''),
            ['readerid' => $readerid, 'entityid' => $library->entityid] + ($onlyhere ? ['libraryid' => $library->id] : [])
        );
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
            "SELECT l.id, l.timedue, l.libraryid, i.recordid, r.title, b.name AS library
               FROM {" . self::T . "loan} l
               JOIN {" . self::T . "item} i ON i.id = l.itemid
               JOIN {" . self::T . "record} r ON r.id = i.recordid
               JOIN {" . self::T . "library} b ON b.id = l.libraryid
              WHERE l.readerid = :readerid AND l.timereturned IS NULL AND b.entityid = :entityid
           ORDER BY l.timedue, l.id",
            ['readerid' => $readerid, 'entityid' => $library->entityid]
        );
        return array_values(array_map(fn($r) => ['loanid' => (int) $r->id, 'recordid' => (int) $r->recordid,
            'title' => $r->title, 'libraryid' => (int) $r->libraryid,
            'library' => format_string($r->library, true, ['escape' => false]), 'due' => (int) $r->timedue,
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
