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
 * A book in another format: large print, audio, braille or a text that can be read aloud.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * A reader asks for a book in the format they need; the library answers, with an accessible item of its digital
 * collection or in some other way, and tells them. A reader can be marked as needing accessible formats: only they see the
 * items made for them. Nobody says why: the mark is all there is.
 */
final class formats {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var string[] What can be asked for. */
    public const FORMATS = ['large', 'audio', 'braille', 'text', 'other'];

    /** @var int How many answered asks a page shows. */
    public const PAGE = 50;

    /**
     * Asks for a book in a format. One open ask per reader and book: asking again changes its format.
     *
     * @param \stdClass $library Where it is asked.
     * @param \stdClass $reader
     * @param int $recordid
     * @param string $format
     * @param int|null $now
     * @return int The ask, or 0 if it could not be asked.
     */
    public static function ask(\stdClass $library, \stdClass $reader, int $recordid, string $format, ?int $now = null): int {
        global $DB;
        $now = $now ?? time();
        if (
            !in_array($format, self::FORMATS, true) || (int) $reader->entityid !== (int) $library->entityid
            || $reader->status !== 'active' || practice::is((int) $library->entityid)
            || !$DB->record_exists(self::T . 'record', ['id' => $recordid, 'entityid' => $library->entityid])
        ) {
            return 0;
        }
        $open = self::open_of((int) $reader->id, $recordid);
        if ($open) {
            $DB->update_record(self::T . 'formatask', (object) ['id' => $open->id, 'format' => $format,
                'timemodified' => $now]);
            return (int) $open->id;
        }
        $id = (int) $DB->insert_record(self::T . 'formatask', (object) ['entityid' => $library->entityid,
            'libraryid' => $library->id, 'readerid' => $reader->id, 'recordid' => $recordid, 'format' => $format,
            'status' => 'waiting', 'timecreated' => $now, 'timemodified' => $now]);
        \local_oksigeniaayvurenda\event\format_asked::fire((int) $library->entityid, $id);
        return $id;
    }

    /**
     * What a reader asked of a book and is still waiting for.
     *
     * @param int $readerid
     * @param int $recordid
     * @return \stdClass|null
     */
    public static function open_of(int $readerid, int $recordid): ?\stdClass {
        global $DB;
        return $DB->get_record(self::T . 'formatask', ['readerid' => $readerid, 'recordid' => $recordid,
            'status' => 'waiting'], '*', IGNORE_MULTIPLE) ?: null;
    }

    /**
     * How many asks wait in a library.
     *
     * @param \stdClass $library
     * @return int
     */
    public static function waiting(\stdClass $library): int {
        global $DB;
        return $DB->count_records(self::T . 'formatask', ['libraryid' => $library->id, 'status' => 'waiting']);
    }

    /**
     * The asks of a library: those that wait, the oldest first; or those answered, the newest first.
     *
     * @param \stdClass $library
     * @param bool $answered
     * @return array Each with id, recordid, title, readerid, name, format, status, digitalid, timecreated, timemodified.
     */
    public static function of_library(\stdClass $library, bool $answered = false): array {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT f.id, f.recordid, f.readerid, f.format, f.status, f.digitalid, f.timecreated, f.timemodified,
                    r.title, d.userid, d.firstname, d.lastname
               FROM {" . self::T . "formatask} f
               JOIN {" . self::T . "record} r ON r.id = f.recordid
               JOIN {" . self::T . "reader} d ON d.id = f.readerid
              WHERE f.libraryid = :libraryid AND f.status " . ($answered ? "<> 'waiting'" : "= 'waiting'") . "
           ORDER BY " . ($answered ? 'f.timemodified DESC' : 'f.timecreated ASC') . ', f.id',
            ['libraryid' => $library->id],
            0,
            $answered ? self::PAGE : 0
        );
        // The same in Moodle 4.5 and 5.3, where user_get_users_by_id() is deprecated.
        $users = $DB->get_records_list('user', 'id', array_values(array_filter(array_unique(array_map(
            'intval',
            array_column($rows, 'userid')
        )))));
        return array_values(array_map(fn($row) => [
            'id' => (int) $row->id,
            'recordid' => (int) $row->recordid,
            'title' => (string) $row->title,
            'readerid' => (int) $row->readerid,
            'name' => $row->userid && isset($users[$row->userid]) ? fullname($users[$row->userid])
                : trim($row->firstname . ' ' . $row->lastname),
            'format' => (string) $row->format,
            'status' => (string) $row->status,
            'digitalid' => (int) $row->digitalid,
            'timecreated' => (int) $row->timecreated,
            'timemodified' => (int) $row->timemodified,
        ], $rows));
    }

    /**
     * What a reader asked, for their record and their data.
     *
     * @param int $readerid
     * @return array Each with title, format and status.
     */
    public static function of_reader(int $readerid): array {
        global $DB;
        return array_values(array_map(fn($row) => ['title' => (string) $row->title, 'format' => (string) $row->format,
            'status' => (string) $row->status, 'when' => (int) $row->timecreated], $DB->get_records_sql(
                "SELECT f.id, r.title, f.format, f.status, f.timecreated
                   FROM {" . self::T . "formatask} f
                   JOIN {" . self::T . "record} r ON r.id = f.recordid
                  WHERE f.readerid = ?
               ORDER BY f.timecreated DESC, f.id DESC",
                [$readerid]
            )));
    }

    /**
     * The accessible items of a book, to answer with.
     *
     * @param \stdClass $library
     * @param int $recordid
     * @return \stdClass[]
     */
    public static function items_of(\stdClass $library, int $recordid): array {
        global $DB;
        return array_values($DB->get_records_select(
            self::T . 'digital',
            "recordid = ? AND entityid = ? AND accessformat = 1 AND kind <> 'voice'",
            [$recordid, $library->entityid],
            'timecreated, id'
        ));
    }

    /**
     * Answers an ask, and tells the reader. With an accessible item of that book, the reader becomes one who needs
     * accessible formats, or they would not see it.
     *
     * @param int $id
     * @param \stdClass $library
     * @param bool $served
     * @param int $digitalid 0 when it was served some other way (on paper, by hand).
     * @param int $userid Who answers.
     * @param int|null $now
     * @return bool Whether it was answered now.
     */
    public static function answer(
        int $id,
        \stdClass $library,
        bool $served,
        int $digitalid,
        int $userid,
        ?int $now = null
    ): bool {
        global $DB;
        $now = $now ?? time();
        $ask = $DB->get_record(self::T . 'formatask', ['id' => $id, 'libraryid' => $library->id]);
        if (!$ask || $ask->status !== 'waiting') {
            return false;
        }
        $item = $served && $digitalid ? $DB->get_record(self::T . 'digital', ['id' => $digitalid,
            'recordid' => $ask->recordid, 'entityid' => $library->entityid, 'accessformat' => 1]) : null;
        $DB->update_record(self::T . 'formatask', (object) ['id' => $ask->id, 'status' => $served ? 'served' : 'declined',
            'digitalid' => $item ? $item->id : null, 'handledby' => $userid, 'timemodified' => $now]);
        if ($item) {
            self::mark($library, (int) $ask->readerid, true);
        }
        \local_oksigeniaayvurenda\event\format_answered::fire((int) $library->entityid, (int) $ask->id, ['what' => $served
            ? 'served' : 'declined']);
        self::tell($library, $ask, $served, (bool) $item);
        return true;
    }

    /**
     * Says whether a reader needs accessible formats.
     *
     * @param \stdClass $library
     * @param int $readerid
     * @param bool $needs
     */
    public static function mark(\stdClass $library, int $readerid, bool $needs): void {
        global $DB;
        $reader = $DB->get_record(self::T . 'reader', ['id' => $readerid, 'entityid' => $library->entityid]);
        if (!$reader || (bool) $reader->accessneeds === $needs) {
            return;
        }
        $DB->set_field(self::T . 'reader', 'accessneeds', $needs ? 1 : 0, ['id' => $reader->id]);
        \local_oksigeniaayvurenda\event\reader_needs_changed::fire((int) $library->entityid, (int) $reader->id, ['what' =>
            $needs ? 'on' : 'off']);
    }

    /**
     * Tells the reader, in their language, what the library did.
     *
     * @param \stdClass $library
     * @param \stdClass $ask
     * @param bool $served
     * @param bool $online Whether it is in the digital collection now.
     */
    private static function tell(\stdClass $library, \stdClass $ask, bool $served, bool $online): void {
        global $DB;
        $reader = $DB->get_record(self::T . 'reader', ['id' => $ask->readerid, 'status' => 'active']);
        $user = $reader && $reader->userid ? \core_user::get_user($reader->userid) : null;
        if (!$user || $user->deleted || $user->suspended) {
            return;
        }
        $language = force_current_language((string) $user->lang);
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        $title = (string) $DB->get_field(self::T . 'record', 'title', ['id' => $ask->recordid]);
        $name = format_string($library->name, true, ['escape' => false]);
        $url = (new \moodle_url('/local/oksigeniaayvurenda/record.php', ['library' => $library->slug,
            'id' => $ask->recordid]))->out(false);
        $kind = !$served ? 'no' : ($online ? 'online' : 'desk');
        $subject = $str('formatready' . $kind . 'subject');
        $lead = $str('formatready' . $kind . 'lead', (object) ['format' => \core_text::strtolower($str('format' . $ask->format
            . 'label'))]);
        $sign = mail::sign((int) $library->entityid, $reader, true, $library);
        notices::send(
            'formatready',
            $user,
            $subject,
            $lead . "\n\n" . $title . "\n" . $url . ($sign ? "\n\n" . $sign['text'] : ''),
            $url,
            $title,
            $name,
            [
                'library' => $library,
                'young' => mail::young($reader),
                'preheader' => $subject,
                'hello' => $str('mailhello', $user->firstname),
                'heading' => $subject,
                'lead' => [$lead],
                'book' => mail::book((int) $ask->recordid, $title, catalogue::authors_of((int) $ask->recordid)),
                'place' => ['label' => $str('mailwhere'), 'value' => $name],
                'button' => ['url' => $url, 'text' => $str('formatreadybutton')],
                'sign' => $sign,
                'why' => $str('mailwhyreader'),
                'prefs' => true,
            ]
        );
        force_current_language($language);
    }
}
