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

/**
 * Lending between libraries of different centres, whatever program the other one uses: what is asked of another library for
 * a reader here, from the asking to its going home; and what another library asks of this one, which goes out at the desk
 * to a reader that stands for that library, and comes back at the desk like any book. A book that comes from outside stays
 * out of the catalogue: it is only here for a while, and for one person.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ill {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var string The type of the reader that stands for another library. */
    public const READERTYPE = 'library';

    /** @var string[][] What can happen next to a book asked of another library, from each state. */
    private const NEXT = ['asked' => ['arrived', 'cancelled'], 'arrived' => ['lent', 'done'], 'lent' => ['back'],
        'back' => ['done']];

    /**
     * A date as a form gives it, at the end of that day; null if it is none.
     *
     * @param string $text
     * @param \stdClass $library
     * @return int|null
     */
    public static function date(string $text, \stdClass $library): ?int {
        $date = \DateTime::createFromFormat('!Y-m-d', trim($text), zones::of($library));
        return $date ? $date->getTimestamp() + DAYSECS - 1 : null;
    }

    /**
     * A book is asked of another library.
     *
     * @param \stdClass $library
     * @param array $data title, author, isbn, note, partner and card (the code of the reader's card, if it is for someone).
     * @param int $userid
     * @param int|null $now
     * @return string|int The new id, or what is wrong: notitle, nopartner or nocard.
     */
    public static function ask(\stdClass $library, array $data, int $userid, ?int $now = null) {
        global $DB;
        $now = $now ?? time();
        $title = \core_text::substr(trim((string) ($data['title'] ?? '')), 0, 255);
        $partner = \core_text::substr(trim((string) ($data['partner'] ?? '')), 0, 255);
        if ($title === '') {
            return 'notitle';
        }
        if ($partner === '') {
            return 'nopartner';
        }
        $readerid = (int) ($data['readerid'] ?? 0);
        $card = trim((string) ($data['card'] ?? ''));
        if ($card !== '') {
            $reader = desk::by_code('reader', 'cardcode', (int) $library->entityid, $card, " AND status = 'active'");
            if (!$reader) {
                return 'nocard';
            }
            $readerid = (int) $reader->id;
        }
        return (int) $DB->insert_record(self::T . 'ill', [
            'libraryid' => $library->id,
            'direction' => 'in',
            'partner' => $partner,
            'title' => $title,
            'author' => \core_text::substr(trim((string) ($data['author'] ?? '')), 0, 255) ?: null,
            'isbn' => purchases::isbn((string) ($data['isbn'] ?? '')) ?: null,
            'note' => \core_text::substr(trim((string) ($data['note'] ?? '')), 0, 255) ?: null,
            'readerid' => $readerid ?: null,
            'status' => 'asked',
            'createdby' => $userid,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * What a reader asked to buy is asked of another library instead, for the first who asked.
     *
     * @param \stdClass $library
     * @param int $purchaseid
     * @param string $partner
     * @param int $userid
     * @param int|null $now
     * @return bool
     */
    public static function from_purchase(
        \stdClass $library,
        int $purchaseid,
        string $partner,
        int $userid,
        ?int $now = null
    ): bool {
        global $DB;
        $now = $now ?? time();
        $row = $DB->get_record(self::T . 'purchase', ['id' => $purchaseid, 'libraryid' => $library->id, 'status' => 'asked']);
        if (!$row) {
            return false;
        }
        $first = $DB->get_records(self::T . 'purchasewho', ['purchaseid' => $purchaseid], 'id', 'id, readerid', 0, 1);
        $made = self::ask($library, ['title' => $row->title, 'author' => $row->author, 'isbn' => $row->isbn, 'note' => $row->note,
            'partner' => $partner, 'readerid' => $first ? (int) reset($first)->readerid : 0], $userid, $now);
        if (!is_int($made)) {
            return false;
        }
        $DB->update_record(self::T . 'purchase', (object) ['id' => $purchaseid, 'status' => 'borrowed', 'decidedby' => $userid,
            'timemodified' => $now]);
        return true;
    }

    /**
     * What comes next for a book asked of another library.
     *
     * @param \stdClass $library
     * @param int $id
     * @param string $state arrived, lent, back, done or cancelled.
     * @param int|null $due When it has to go home, on arriving.
     * @param int|null $now
     * @return bool
     */
    public static function move(\stdClass $library, int $id, string $state, ?int $due = null, ?int $now = null): bool {
        global $DB;
        $now = $now ?? time();
        $row = $DB->get_record(self::T . 'ill', ['id' => $id, 'libraryid' => $library->id, 'direction' => 'in']);
        if (!$row || !in_array($state, self::NEXT[$row->status] ?? [], true)) {
            return false;
        }
        $changes = ['id' => $id, 'status' => $state, 'timemodified' => $now];
        if ($state === 'arrived') {
            $changes['timedue'] = $due;
        }
        $DB->update_record(self::T . 'ill', (object) $changes);
        if ($state === 'arrived' && $row->readerid) {
            self::tell($library, $row, $due);
        }
        return true;
    }

    /**
     * Tells whoever it is for that it is here.
     *
     * @param \stdClass $library
     * @param \stdClass $row
     * @param int|null $due
     */
    private static function tell(\stdClass $library, \stdClass $row, ?int $due): void {
        global $DB;
        $reader = $DB->get_record(self::T . 'reader', ['id' => $row->readerid, 'status' => 'active']);
        $user = $reader && $reader->userid ? \core_user::get_user($reader->userid) : null;
        if (!$user || $user->deleted || $user->suspended) {
            return;
        }
        $language = force_current_language((string) $user->lang);
        $name = format_string($library->name, true, ['escape' => false]);
        $str = fn(string $key) => get_string($key, 'local_oksigeniaayvurenda');
        $url = (new \moodle_url('/local/oksigeniaayvurenda/index.php', ['library' => $library->slug]))->out(false);
        $sign = mail::sign((int) $library->entityid, $reader, true, $library);
        notices::send(
            'illarrived',
            $user,
            $str('illarrivedsubject'),
            get_string('illarrivedbody', 'local_oksigeniaayvurenda', (object) ['title' => $row->title, 'library' => $name])
                . ($sign ? "\n\n" . $sign['text'] : ''),
            $url,
            $name,
            $name,
            [
                'library' => $library,
                'young' => mail::young($reader),
                'preheader' => $str('illarrivedsubject'),
                'hello' => get_string('mailhello', 'local_oksigeniaayvurenda', $user->firstname),
                'heading' => $str('illarrivedsubject'),
                'lead' => [get_string('illarrivedlead', 'local_oksigeniaayvurenda', $row->title)],
                'place' => ['label' => get_string('mailwhere', 'local_oksigeniaayvurenda'), 'value' => $name],
                'button' => ['url' => $url, 'text' => $str('illarrivedbutton')],
                'sign' => $sign,
                'why' => get_string('mailwhyreader', 'local_oksigeniaayvurenda'),
                'prefs' => true,
            ]
        );
        force_current_language($language);
    }

    /**
     * The reader that stands for another library, made the first time.
     *
     * @param \stdClass $library
     * @param string $name
     * @param int $now
     * @return int
     */
    private static function partner_reader(\stdClass $library, string $name, int $now): int {
        global $DB;
        $entityid = (int) $library->entityid;
        foreach ($DB->get_records(self::T . 'reader', ['entityid' => $entityid, 'readertype' => self::READERTYPE]) as $reader) {
            if (\core_text::strtolower((string) $reader->firstname) === \core_text::strtolower($name)) {
                $DB->set_field(self::T . 'reader', 'status', 'active', ['id' => $reader->id]);
                return (int) $reader->id;
            }
        }
        $settings = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        $codes = $DB->get_fieldset_select(self::T . 'reader', 'cardcode', 'entityid = :entityid', ['entityid' => $entityid]);
        return (int) $DB->insert_record(self::T . 'reader', ['entityid' => $entityid, 'readertype' => self::READERTYPE,
            'firstname' => $name, 'lastname' => '', 'status' => 'active',
            'cardcode' => \local_oksigeniaayvurenda\core\readers\card_codes::next($codes, 1, !empty($settings['controlletter']))[0],
            'timecreated' => $now, 'timemodified' => $now]);
    }

    /**
     * Lends a copy of this library to another one. It goes out as a loan, so nobody else takes it and it shows as out; it
     * comes back at the desk like any book.
     *
     * @param \stdClass $library
     * @param string $code The code of the copy.
     * @param string $partner
     * @param int|null $due
     * @param int $userid
     * @param int|null $now
     * @return string|int The new id, or what is wrong: nopartner, nocopy or stop.
     */
    public static function lend(\stdClass $library, string $code, string $partner, ?int $due, int $userid, ?int $now = null) {
        global $DB;
        $now = $now ?? time();
        $partner = \core_text::substr(trim($partner), 0, 255);
        if ($partner === '') {
            return 'nopartner';
        }
        $item = desk::by_code('item', 'barcode', (int) $library->entityid, trim($code));
        if (!$item || (int) $item->libraryid !== (int) $library->id) {
            return 'nocopy';
        }
        if ($item->status !== 'available') {
            return 'stop';
        }
        $readerid = self::partner_reader($library, $partner, $now);
        $answer = (new desk($library, $userid, $now))->scan($item->barcode, $readerid, true);
        if ($answer['kind'] !== 'lent') {
            return 'stop';
        }
        if ($due) {
            $DB->set_field(self::T . 'loan', 'timedue', $due, ['id' => $answer['loanid']]);
        }
        $record = $DB->get_record(self::T . 'record', ['id' => $item->recordid], 'title, isbn');
        $until = (int) $DB->get_field(self::T . 'loan', 'timedue', ['id' => $answer['loanid']]);
        return (int) $DB->insert_record(self::T . 'ill', ['libraryid' => $library->id, 'direction' => 'out', 'partner' => $partner,
            'title' => (string) $record->title, 'isbn' => $record->isbn ?: null, 'readerid' => $readerid, 'itemid' => $item->id,
            'loanid' => $answer['loanid'], 'status' => 'sent', 'timedue' => $until,
            'createdby' => $userid, 'timecreated' => $now, 'timemodified' => $now]);
    }

    /**
     * What is under way in one direction, and what has ended lately. A copy lent out that came back at the desk has ended.
     *
     * @param \stdClass $library
     * @param string $direction in or out.
     * @param int|null $now
     * @return array open and closed, each a list of rows with who it is for.
     */
    public static function rows(\stdClass $library, string $direction, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        if ($direction === 'out') {
            self::settle($library, $now);
        }
        $rows = $DB->get_records_sql(
            "SELECT i.*, COALESCE(u.firstname, r.firstname) AS firstname, COALESCE(u.lastname, r.lastname) AS lastname
               FROM {" . self::T . "ill} i
          LEFT JOIN {" . self::T . "reader} r ON r.id = i.readerid
          LEFT JOIN {user} u ON u.id = r.userid
              WHERE i.libraryid = :libraryid AND i.direction = :direction
           ORDER BY i.timemodified DESC, i.id DESC",
            ['libraryid' => $library->id, 'direction' => $direction],
            0,
            300
        );
        $open = [];
        $closed = [];
        foreach ($rows as $row) {
            $one = (array) $row + ['late' => $row->timedue && (int) $row->timedue < $now && !in_array($row->status, ['done',
                'cancelled'], true),
                'who' => $direction === 'in' ? trim($row->firstname . ' ' . $row->lastname) : ''];
            if (in_array($row->status, ['done', 'cancelled'], true)) {
                if (count($closed) < 30) {
                    $closed[] = $one;
                }
            } else {
                $open[] = $one;
            }
        }
        usort($open, fn($a, $b) => [$b['late'], (int) $a['timedue'] ?: PHP_INT_MAX] <=> [$a['late'],
            (int) $b['timedue'] ?: PHP_INT_MAX]);
        return ['open' => $open, 'closed' => $closed];
    }

    /**
     * What was lent out and has come back at the desk, or whose loan was undone, has ended.
     *
     * @param \stdClass $library
     * @param int $now
     */
    private static function settle(\stdClass $library, int $now): void {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT i.id
               FROM {" . self::T . "ill} i
          LEFT JOIN {" . self::T . "loan} l ON l.id = i.loanid
              WHERE i.libraryid = :libraryid AND i.direction = 'out' AND i.status = 'sent'
                    AND (l.id IS NULL OR l.timereturned IS NOT NULL)",
            ['libraryid' => $library->id]
        );
        foreach (array_keys($rows) as $id) {
            $DB->update_record(self::T . 'ill', (object) ['id' => $id, 'status' => 'done', 'timemodified' => $now]);
        }
    }

    /**
     * How many books from other libraries should have gone home by now, for the desk.
     *
     * @param \stdClass $library
     * @param int|null $now
     * @return int
     */
    public static function overdue(\stdClass $library, ?int $now = null): int {
        global $DB;
        return $DB->count_records_select(
            self::T . 'ill',
            "libraryid = ? AND direction = 'in' AND status IN ('arrived', 'lent', 'back') AND timedue IS NOT NULL AND timedue < ?",
            [$library->id, $now ?? time()]
        );
    }

    /**
     * The other libraries this one has dealt with, to choose from.
     *
     * @param \stdClass $library
     * @return string[]
     */
    public static function partners(\stdClass $library): array {
        global $DB;
        return array_values($DB->get_fieldset_sql(
            'SELECT DISTINCT partner FROM {' . self::T . 'ill} WHERE libraryid = ? ORDER BY partner',
            [$library->id]
        ));
    }
}
