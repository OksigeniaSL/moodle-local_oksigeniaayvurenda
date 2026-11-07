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
 * Machines that speak SIP2: self-loan machines, return boxes and RFID pads.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;


/**
 * A machine is one more self-loan post: it uses the address of a post, through a small bridge that runs in the library
 * (sip2-bridge/), with the same rules, limits and record, and whatever it does can be undone from the post's page. It
 * stands in a public room, so it says what a post says: the first name and what was just taken, never which books someone
 * has, whether they are late or how much they owe. Why a card or a book is refused stays the same few neutral words.
 */
final class sip {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var array<string, string> What the protocol calls each state of a copy. */
    private const CIRCULATION = ['available' => '03', 'onloan' => '04', 'held' => '08', 'lost' => '12'];

    /**
     * Answers what a machine asks, one at a time at each post.
     *
     * @param \stdClass $post
     * @param \stdClass $library
     * @param string $op status, patron, item, checkout, checkin or renew.
     * @param array $values patron and item, as the machine read them.
     * @param int|null $now
     * @return array What the bridge turns into the protocol's answer.
     */
    public static function answer(\stdClass $post, \stdClass $library, string $op, array $values, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $lock = \core\lock\lock_config::get_lock_factory('local_oksigeniaayvurenda')->get_lock('selfpost_' . $post->id, 5);
        if (!$lock) {
            return self::busy($library, $op);
        }
        try {
            $post = $DB->get_record(self::T . 'selfpost', ['id' => $post->id], '*', MUST_EXIST);
            if (!empty($post->timestopped)) {
                return self::busy($library, $op, true);
            }
            $DB->set_field(self::T . 'selfpost', 'timelast', $now, ['id' => $post->id]);
            $patron = trim((string) ($values['patron'] ?? ''));
            $item = trim((string) ($values['item'] ?? ''));
            switch ($op) {
                case 'status':
                    return self::status($library);
                case 'patron':
                    return self::patron($post, $library, $patron, $now);
                case 'item':
                    return self::item($post, $library, $item, $now);
                case 'checkout':
                    return self::checkout($post, $library, $patron, $item, $now);
                case 'checkin':
                    return self::checkin($post, $library, $item, $now);
                case 'renew':
                    return self::renew($post, $library, $patron, $item, $now);
            }
            return ['ok' => false];
        } finally {
            $lock->release();
        }
    }

    /**
     * What is said when nothing can be done now: the post stopped, or it is busy.
     *
     * @param \stdClass $library
     * @param string $op
     * @param bool $stopped
     * @return array
     */
    private static function busy(\stdClass $library, string $op, bool $stopped = false): array {
        $message = get_string($stopped ? 'selfstopped' : 'selfwait', 'local_oksigeniaayvurenda');
        if ($op === 'status') {
            return ['online' => false] + self::names($library);
        }
        return ['ok' => false, 'valid' => false, 'flags' => ['denied' => true], 'circulation' => '01', 'message' => $message];
    }

    /**
     * How the machine names the library.
     *
     * @param \stdClass $library
     * @return array institution and name.
     */
    private static function names(\stdClass $library): array {
        return ['institution' => (string) $library->slug, 'name' => format_string($library->name, true, ['escape' => false])];
    }

    /**
     * What a machine can do here.
     *
     * @param \stdClass $library
     * @return array
     */
    private static function status(\stdClass $library): array {
        return ['online' => true, 'checkout' => true, 'checkin' => selfloan::settings($library)['returns'],
            'renew' => !empty(holds::settings((int) $library->entityid)['renewonline'])] + self::names($library);
    }

    /**
     * The reader of a card, if they may use this post. Reading every number in turn would tell who has a card, so each
     * different card counts, as at a post, and so does any code that is not good. The card of whoever is at the machine
     * does not count again: it comes with every book of a pile, and a busy morning would stop the post.
     *
     * @param \stdClass $post
     * @param \stdClass $library
     * @param string $code
     * @param int $now
     * @return \stdClass|null
     */
    private static function reader(\stdClass $post, \stdClass $library, string $code, int $now): ?\stdClass {
        $entityid = (int) $library->entityid;
        $reader = $code === '' ? null : desk::by_code('reader', 'cardcode', $entityid, $code, " AND status = 'active'");
        $good = $reader && selfloan::may($library, $reader, $now);
        $visit = \cache::make('local_oksigeniaayvurenda', 'selfvisit');
        if ($good && (int) $visit->get('post_' . $post->id) === (int) $reader->id) {
            return $reader;
        }
        if (selfloan::too_many($post, $now) || !$good) {
            return null;
        }
        $visit->set('post_' . $post->id, (int) $reader->id);
        return $reader;
    }

    /**
     * A moment as the protocol writes it, at the hour of the centre: the bridge may well run in another zone.
     *
     * @param int $time
     * @param \stdClass $library
     * @return string
     */
    private static function stamp(int $time, \stdClass $library): string {
        return (new \DateTimeImmutable('@' . $time))->setTimezone(zones::of($library))->format('Ymd    His');
    }

    /**
     * A copy of this library by its code. Material is noted by someone at the desk: it does not go through a machine.
     *
     * @param \stdClass $library
     * @param string $code
     * @return array The copy, or null, and its title.
     */
    private static function copy(\stdClass $library, string $code): array {
        global $DB;
        $item = $code === '' ? null : desk::by_code('item', 'barcode', (int) $library->entityid, $code);
        if (!$item || (int) $item->libraryid !== (int) $library->id) {
            return [null, ''];
        }
        return [$item, (string) $DB->get_field(self::T . 'record', 'title', ['id' => $item->recordid])];
    }

    /**
     * Whether a copy is material, for someone at the desk.
     *
     * @param \stdClass $item
     * @return bool
     */
    private static function material(\stdClass $item): bool {
        global $DB;
        return $DB->get_field(self::T . 'record', 'doctype', ['id' => $item->recordid]) === 'device';
    }

    /**
     * A card: whether it is good, and the first name, as at a post. Typing someone's code at a machine tells nothing more:
     * if they are stopped, that shows when they try to take a book.
     *
     * @param \stdClass $post
     * @param \stdClass $library
     * @param string $code
     * @param int $now
     * @return array
     */
    private static function patron(\stdClass $post, \stdClass $library, string $code, int $now): array {
        $reader = self::reader($post, $library, $code, $now);
        if (!$reader) {
            return ['valid' => false, 'flags' => ['denied' => true],
                'message' => get_string('selfunknown', 'local_oksigeniaayvurenda')];
        }
        $user = $reader->userid ? \core_user::get_user($reader->userid) : null;
        return ['valid' => true, 'name' => trim((string) ($user ? $user->firstname : $reader->firstname))];
    }

    /**
     * What is known of a book: its title, whether it is in and when it is due. Not who has it.
     *
     * @param \stdClass $post
     * @param \stdClass $library
     * @param string $code
     * @param int $now
     * @return array
     */
    private static function item(\stdClass $post, \stdClass $library, string $code, int $now): array {
        global $DB;
        [$item, $title] = self::copy($library, $code);
        if (!$item) {
            // Only what is not known counts: going over one's own books is what such a machine is for.
            selfloan::too_many($post, $now);
            return ['circulation' => '01', 'title' => '', 'message' => get_string('selfunknown', 'local_oksigeniaayvurenda')];
        }
        $due = $DB->get_field_select(
            self::T . 'loan',
            'timedue',
            'itemid = ? AND timereturned IS NULL',
            [$item->id],
            IGNORE_MULTIPLE
        );
        return ['circulation' => self::CIRCULATION[$item->status] ?? '01', 'title' => $title,
            'due' => $due ? self::stamp((int) $due, $library) : ''];
    }

    /**
     * A book goes out. Read twice by the same reader, it is the same loan: a pad reads a pile more than once.
     *
     * @param \stdClass $post
     * @param \stdClass $library
     * @param string $patron
     * @param string $code
     * @param int $now
     * @return array
     */
    private static function checkout(\stdClass $post, \stdClass $library, string $patron, string $code, int $now): array {
        global $DB;
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        $reader = self::reader($post, $library, $patron, $now);
        if (!$reader) {
            return ['ok' => false, 'message' => $str('selfunknown')];
        }
        [$item, $title] = self::copy($library, $code);
        if (!$item) {
            selfloan::too_many($post, $now);
            return ['ok' => false, 'message' => $str('selfunknown')];
        }
        if (self::material($item)) {
            return ['ok' => false, 'title' => $title, 'message' => $str('selfmaterial', $title)];
        }
        $open = $DB->get_record_select(self::T . 'loan', 'itemid = ? AND timereturned IS NULL', [$item->id], '*', IGNORE_MULTIPLE);
        if ($open && (int) $open->readerid === (int) $reader->id) {
            return ['ok' => true, 'title' => $title, 'due' => self::stamp((int) $open->timedue, $library)];
        }
        // A copy on reserve for a course may be for the room only: that is for someone to say.
        if ($open || $item->status === 'onloan' || reserves::active((int) $item->id, $now)) {
            return ['ok' => false, 'title' => $title, 'message' => $str('selfasks', $title)];
        }
        $answer = (new desk($library, 0, $now, true))->scan($item->barcode, (int) $reader->id);
        if ($answer['kind'] === 'lent') {
            $DB->set_field(self::T . 'loan', 'postid', $post->id, ['id' => $answer['loanid']]);
            $due = (int) $DB->get_field(self::T . 'loan', 'timedue', ['id' => $answer['loanid']]);
            return ['ok' => true, 'title' => $title, 'due' => self::stamp($due, $library)];
        }
        $reason = $answer['kind'] === 'stop' ? (string) ($answer['reason'] ?? '') : '';
        return ['ok' => false, 'title' => $title,
            'message' => $str(selfloan::is_stop($reason) ? 'selfstop' . $reason : 'selfasks', $title)];
    }

    /**
     * A book comes back, where the library lets its posts take them.
     *
     * @param \stdClass $post
     * @param \stdClass $library
     * @param string $code
     * @param int $now
     * @return array
     */
    private static function checkin(\stdClass $post, \stdClass $library, string $code, int $now): array {
        global $DB;
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        [$item, $title] = self::copy($library, $code);
        if (!$item) {
            selfloan::too_many($post, $now);
            return ['ok' => false, 'message' => $str('selfunknown')];
        }
        if (!selfloan::settings($library)['returns']) {
            return ['ok' => false, 'title' => $title, 'message' => $str('selfnoreturn', $title)];
        }
        if (self::material($item)) {
            return ['ok' => false, 'title' => $title, 'message' => $str('selfmaterial', $title)];
        }
        $open = $DB->record_exists_select(self::T . 'loan', 'itemid = ? AND timereturned IS NULL', [$item->id]);
        // A loan noted by its title, without knowing which copy went: the desk sorts out which one this is.
        if ($DB->record_exists_select(self::T . 'loan', 'itemid = ? AND timereturned IS NULL AND copyguessed = 1', [$item->id])) {
            return ['ok' => false, 'title' => $title, 'message' => $str('selfasks', $title)];
        }
        if (!$open) {
            // A pad reads a pile twice, and a machine that got no answer in time sends it again: it did come back.
            $again = $DB->record_exists_select(
                self::T . 'loan',
                'itemid = ? AND returnpostid = ? AND timereturned >= ?',
                [$item->id, $post->id, $now - selfloan::DOUBLE]
            );
            return $again ? ['ok' => true, 'title' => $title, 'alert' => $item->status === 'held',
                'message' => $str($item->status === 'held' ? 'selfreturnedheld' : 'selfreturned', $title)]
                : ['ok' => false, 'title' => $title, 'message' => $str('sipnotout', $title)];
        }
        if (selfloan::too_many_back($post, $now)) {
            return ['ok' => false, 'title' => $title, 'message' => $str(empty($post->timestopped) ? 'selfwait' : 'selfstopped')];
        }
        $answer = (new desk($library, 0, $now, true))->scan($item->barcode);
        if ($answer['kind'] !== 'returned') {
            return ['ok' => false, 'title' => $title, 'message' => $str('selfasks', $title)];
        }
        $DB->set_field(self::T . 'loan', 'returnpostid', $post->id, ['id' => $answer['loanid']]);
        return ['ok' => true, 'title' => $title, 'alert' => !empty($answer['held']),
            'message' => $str(!empty($answer['held']) ? 'selfreturnedheld' : 'selfreturned', $title)];
    }

    /**
     * More time for a book, by the same rules as from home.
     *
     * @param \stdClass $post
     * @param \stdClass $library
     * @param string $patron
     * @param string $code
     * @param int $now
     * @return array
     */
    private static function renew(\stdClass $post, \stdClass $library, string $patron, string $code, int $now): array {
        global $DB;
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        $reader = self::reader($post, $library, $patron, $now);
        [$item, $title] = self::copy($library, $code);
        if (!$reader || !$item) {
            if ($reader) {
                selfloan::too_many($post, $now);
            }
            return ['ok' => false, 'message' => $str('selfunknown')];
        }
        if (self::material($item)) {
            return ['ok' => false, 'title' => $title, 'message' => $str('selfmaterial', $title)];
        }
        $loan = $DB->get_record_select(
            self::T . 'loan',
            'itemid = ? AND readerid = ? AND timereturned IS NULL',
            [$item->id, $reader->id],
            '*',
            IGNORE_MULTIPLE
        );
        if (
            !$loan || !holds::renew_online($library, (int) $reader->id, $now)
            || renewals::late_counts($library, (int) $loan->id, $now)
        ) {
            return ['ok' => false, 'title' => $title, 'message' => $str('sipgodesk')];
        }
        $answer = (new desk($library, 0, $now, true))->renew((int) $loan->id);
        if ($answer['kind'] !== 'renewed') {
            return ['ok' => false, 'title' => $title, 'message' => $str('sipgodesk')];
        }
        return ['ok' => true, 'title' => $title, 'due' => self::stamp((int) $DB->get_field(
            self::T . 'loan',
            'timedue',
            ['id' => $loan->id]
        ), $library)];
    }
}
