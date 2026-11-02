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
 * A self-loan post: a screen in the library where readers lend to themselves with their card, and give books back. It
 * opens with its own key, so no account stays open on a machine anyone can touch. Standing in the open, it says only what
 * the person in front of it needs: their first name and what they have just taken; never their other loans, their group,
 * nor anyone else's name. What it cannot decide it sends to the desk, and whoever runs the library goes over it afterwards.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class selfloan {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var int Seconds a read card lasts, from the last thing read. */
    private const TICKET = 300;

    /** @var int Cards and unknown codes a post takes in a minute: a queue is under this, trying every number is not. */
    private const CARDS = 20;

    /** @var int Cards and unknown codes in an hour: over this the post stops until someone of the staff looks. */
    public const CARDSHOUR = 200;

    /** @var int Books given back in a minute: nobody with books in their hands goes faster. */
    private const RETURNS = 30;

    /** @var int Books given back in an hour: over this the post stops until someone of the staff looks. */
    private const RETURNSHOUR = 300;

    /** @var int Books given back in a minute at a machine: a return box takes them as fast as it can. */
    private const MACHINERETURNS = 60;

    /** @var int Books given back in an hour at a machine: a morning after a holiday stays under this. */
    private const MACHINERETURNSHOUR = 1500;

    /** @var string[] Reader types without a stage, so the stages chosen do not apply to them. */
    private const ADULTS = ['teacher', 'staff', 'family'];

    /** @var int Seconds in which reading a book just taken again is a double read, not giving it back. */
    public const DOUBLE = 300;

    /** @var int Days of what a post did that are gone over. */
    public const DAYS = 7;

    /** @var string[] Why a book may not go out, as the desk's policy says it. */
    private const STOPS = ['held', 'late', 'limit', 'notlendable', 'unavailable', 'suspended', 'owes'];

    /**
     * Whether a reason of the desk is one a post answers in its own neutral words.
     *
     * @param string $reason
     * @return bool
     */
    public static function is_stop(string $reason): bool {
        return in_array($reason, self::STOPS, true);
    }

    /**
     * Whether a library can have posts: a textbook bank hands out whole sets, at its desk.
     *
     * @param \stdClass $library
     * @return bool
     */
    public static function available(\stdClass $library): bool {
        return !bank::is($library);
    }

    /**
     * Who may use the posts of a library, and whether they take books back too.
     *
     * @param \stdClass $library
     * @return array stages (none: every stage) and returns.
     */
    public static function settings(\stdClass $library): array {
        $all = json_decode((string) ($library->settings ?? ''), true) ?: [];
        $mine = (array) ($all['selfloan'] ?? []);
        return [
            'stages' => array_values(array_filter(array_map('strval', (array) ($mine['stages'] ?? [])), 'strlen')),
            'returns' => !array_key_exists('returns', $mine) || !empty($mine['returns']),
        ];
    }

    /**
     * Saves who may use the posts.
     *
     * @param \stdClass $library
     * @param array $settings stages and returns.
     */
    public static function save_settings(\stdClass $library, array $settings): void {
        global $DB;
        $all = json_decode((string) $DB->get_field(self::T . 'library', 'settings', ['id' => $library->id]), true) ?: [];
        $stages = array_values(array_filter(array_map('strval', (array) ($settings['stages'] ?? [])), 'strlen'));
        $all['selfloan'] = ['stages' => $stages, 'returns' => !empty($settings['returns'])];
        $DB->set_field(self::T . 'library', 'settings', json_encode($all), ['id' => $library->id]);
        $library->settings = json_encode($all);
        \local_oksigeniaayvurenda\event\self_settings_changed::fire((int) $library->entityid);
    }

    /**
     * The posts of a library.
     *
     * @param \stdClass $library
     * @return \stdClass[]
     */
    public static function posts(\stdClass $library): array {
        global $DB;
        return array_values($DB->get_records(self::T . 'selfpost', ['libraryid' => $library->id], 'name, id'));
    }

    /**
     * Makes a post. Its key is only known now: what stays is its hash.
     *
     * @param \stdClass $library
     * @param string $name
     * @param int $userid
     * @param int|null $now
     * @param bool $machine A machine that speaks SIP2, through the bridge, rather than a screen.
     * @return string The address that opens it.
     */
    public static function create(\stdClass $library, string $name, int $userid, ?int $now = null, bool $machine = false): string {
        global $DB;
        $key = random_string(48);
        $id = $DB->insert_record(self::T . 'selfpost', ['libraryid' => $library->id, 'name' => \core_text::substr($name, 0, 100),
            'keyhash' => hash('sha256', $key), 'machine' => $machine ? 1 : 0, 'lang' => current_language(), 'tries' => 0,
            'timetries' => 0,
            'createdby' => $userid ?: null, 'timecreated' => $now ?? time()]);
        \local_oksigeniaayvurenda\event\self_post_created::fire((int) $library->entityid, (int) $id);
        return (new \moodle_url('/local/oksigeniaayvurenda/self.php', ['key' => $key]))->out(false);
    }

    /**
     * Takes a post away: its key stops working at once.
     *
     * @param \stdClass $library
     * @param int $id
     */
    public static function remove(\stdClass $library, int $id): void {
        global $DB;
        if ($DB->record_exists(self::T . 'selfpost', ['id' => $id, 'libraryid' => $library->id])) {
            $DB->delete_records(self::T . 'selfpost', ['id' => $id]);
            \local_oksigeniaayvurenda\event\self_post_removed::fire((int) $library->entityid, $id);
        }
    }

    /**
     * The post a key opens and its library, if it can be used.
     *
     * @param string $key
     * @param bool $machine Asked by the bridge: only a machine's key works there, and only a screen's elsewhere.
     * @return array|null post and library.
     */
    public static function by_key(string $key, bool $machine = false): ?array {
        global $DB;
        if ($key === '') {
            return null;
        }
        // The address of a screen does not work for a machine, nor the other way round: their limits differ.
        $post = $DB->get_record(self::T . 'selfpost', ['keyhash' => hash('sha256', $key), 'machine' => $machine ? 1 : 0]);
        $library = $post ? $DB->get_record(self::T . 'library', ['id' => $post->libraryid]) : null;
        if (!$library || !self::available($library) || access::closed((int) $library->entityid)) {
            return null;
        }
        return ['post' => $post, 'library' => $library];
    }

    /**
     * Something was read at a post.
     *
     * @param \stdClass $post
     * @param \stdClass $library
     * @param string $code
     * @param string $ticket What the post was given when a card was read, or ''.
     * @param int|null $now
     * @return array sound, state (idle or reader), text and, as they apply, name, ticket, title and due. Nothing else of the
     *               reader, whatever the desk may have answered.
     */
    public static function scan(\stdClass $post, \stdClass $library, string $code, string $ticket, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        // One read at a time at each post, so counts and limits hold whatever is sent at once.
        $lock = \core\lock\lock_config::get_lock_factory('local_oksigeniaayvurenda')->get_lock('selfpost_' . $post->id, 5);
        if (!$lock) {
            return self::say('no', 'selfwait') + ['state' => 'idle'];
        }
        try {
            $post = $DB->get_record(self::T . 'selfpost', ['id' => $post->id], '*', MUST_EXIST);
            return empty($post->timestopped) ? self::read($post, $library, $code, $ticket, $now)
                : self::say('no', 'selfstopped') + ['state' => 'idle'];
        } finally {
            $lock->release();
        }
    }

    /**
     * Reads a code at a post that is open, with nobody else reading at it.
     *
     * @param \stdClass $post
     * @param \stdClass $library
     * @param string $code
     * @param string $ticket
     * @param int $now
     * @return array
     */
    private static function read(\stdClass $post, \stdClass $library, string $code, string $ticket, int $now): array {
        global $DB;
        $code = trim(\core_text::substr($code, 0, 40));
        $holder = self::holder($post, $library, $ticket, $now);
        $DB->set_field(self::T . 'selfpost', 'timelast', $now, ['id' => $post->id]);
        $entityid = (int) $library->entityid;
        $still = fn(array $answer): array => $answer + ($holder
            ? ['state' => 'reader', 'ticket' => self::ticket($post, $holder, $now)] : ['state' => 'idle']);
        $item = $code === '' ? null : desk::by_code('item', 'barcode', $entityid, $code);
        if ($item) {
            return $still(self::book($post, $library, $item, $holder, $now));
        }
        // Reading every number in turn would tell who has a card: it is held back here, and nobody queues this fast.
        if (self::too_many($post, $now)) {
            return self::say('no', empty($post->timestopped) ? 'selfwait' : 'selfstopped') + ['state' => 'idle'];
        }
        // Only a code: at the desk a name finds a reader, and here that would hand anyone's loans to whoever types it.
        $reader = $code === '' ? null : desk::by_code('reader', 'cardcode', $entityid, $code, " AND status = 'active'");
        if (!$reader) {
            return $still(self::say('no', 'selfunknown'));
        }
        // Refused as if it were not known. The visit on screen ends: this is someone else, and their books must not go on it.
        if (!self::may($library, $reader, $now)) {
            return self::say('no', 'selfunknown') + ['state' => 'idle'];
        }
        $user = $reader->userid ? \core_user::get_user($reader->userid) : null;
        $name = trim((string) ($user ? $user->firstname : $reader->firstname));
        return ['sound' => 'ok', 'state' => 'reader', 'name' => $name, 'ticket' => self::ticket($post, (int) $reader->id, $now),
            'text' => $name !== '' ? get_string('selfhello', 'local_oksigeniaayvurenda', $name)
                : get_string('selfhellononame', 'local_oksigeniaayvurenda')];
    }

    /**
     * A book was read at a post.
     *
     * @param \stdClass $post
     * @param \stdClass $library
     * @param \stdClass $item
     * @param int $holder The reader whose card was read, 0 if none.
     * @param int $now
     * @return array
     */
    private static function book(\stdClass $post, \stdClass $library, \stdClass $item, int $holder, int $now): array {
        global $DB;
        $record = $DB->get_record(self::T . 'record', ['id' => $item->recordid], 'title, doctype', MUST_EXIST);
        $title = (string) $record->title;
        if ((int) $item->libraryid !== (int) $library->id) {
            return self::say('no', 'selfother', $title);
        }
        // The state of material is noted by someone, at the desk: going out and coming back.
        if ($record->doctype === 'device') {
            return self::say('no', 'selfmaterial', $title);
        }
        $desk = new desk($library, 0, $now, true);
        $open = $DB->get_record_select(
            self::T . 'loan',
            'itemid = :itemid AND timereturned IS NULL',
            ['itemid' => $item->id],
            '*',
            IGNORE_MULTIPLE
        );
        if ($open || $item->status === 'onloan') {
            // Read twice in a row, a book just taken must not go back by itself.
            if ($open && $holder && (int) $open->readerid === $holder && $now - (int) $open->timeloaned < self::DOUBLE) {
                $due = self::date((int) $open->timedue);
                return self::say('look', 'selfalready', (object) ['title' => $title, 'due' => $due]);
            }
            if (!self::settings($library)['returns']) {
                return self::say('no', 'selfnoreturn', $title);
            }
            // Marked as out with no loan, as an import may leave it: nothing here would say it had come back.
            if (!$open) {
                return self::say('no', 'selfasks', $title);
            }
            if (self::too_many_back($post, $now)) {
                return self::say('no', empty($post->timestopped) ? 'selfwait' : 'selfstopped');
            }
            $answer = $desk->scan($item->barcode);
            if ($answer['kind'] !== 'returned') {
                return self::say('no', 'selfasks', $title);
            }
            $DB->set_field(self::T . 'loan', 'returnpostid', $post->id, ['id' => $answer['loanid']]);
            return !empty($answer['held']) ? self::say('look', 'selfreturnedheld', $title)
                : self::say('ok', 'selfreturned', $title);
        }
        if (!$holder) {
            return self::say('look', 'selfneedcard');
        }
        // A copy on reserve for a course may be for the room only: that is for someone to say.
        if (reserves::active((int) $item->id, $now)) {
            return self::say('no', 'selfasks', $title);
        }
        $answer = $desk->scan($item->barcode, $holder);
        if ($answer['kind'] === 'lent') {
            $DB->set_field(self::T . 'loan', 'postid', $post->id, ['id' => $answer['loanid']]);
            $due = self::date((int) $DB->get_field(self::T . 'loan', 'timedue', ['id' => $answer['loanid']]));
            return self::say('ok', 'selflent', (object) ['title' => $title, 'due' => $due], ['title' => $title, 'due' => $due]);
        }
        if ($answer['kind'] === 'stop' && in_array($answer['reason'] ?? '', self::STOPS, true)) {
            return self::say('no', 'selfstop' . $answer['reason'], $title);
        }
        return self::say('no', 'selfasks', $title);
    }

    /**
     * Whether a reader may use the posts of a library: of its groups if it has them, and of the stages chosen.
     * Adults have no stage: only the first says. Any other type (a class card, a name an import kept) does have to be chosen.
     *
     * @param \stdClass $library
     * @param \stdClass $reader
     * @param int $now
     * @return bool
     */
    public static function may(\stdClass $library, \stdClass $reader, int $now): bool {
        global $DB;
        if (!(new desk($library, 0, $now, true))->allowed((int) $reader->id)) {
            return false;
        }
        $stages = self::settings($library)['stages'];
        if (!$stages || in_array($reader->readertype, self::ADULTS, true)) {
            return true;
        }
        $year = years::around((int) $library->entityid, $now);
        $stage = $DB->get_field(self::T . 'member', 'stage', ['readerid' => $reader->id, 'yearid' => $year->id], IGNORE_MULTIPLE);
        return in_array((string) $stage, $stages, true);
    }

    /**
     * One more card or unknown code at this post, and whether it is over what a minute allows.
     *
     * @param \stdClass $post
     * @param int $now
     * @return bool
     */
    public static function too_many(\stdClass $post, int $now): bool {
        global $DB;
        $fresh = $now - (int) $post->timetries >= 60;
        $post->tries = $fresh ? 1 : (int) $post->tries + 1;
        $post->timetries = $fresh ? $now : (int) $post->timetries;
        $freshhour = $now - (int) $post->timehour >= HOURSECS;
        $post->hourtries = $freshhour ? 1 : (int) $post->hourtries + 1;
        $post->timehour = $freshhour ? $now : (int) $post->timehour;
        $DB->update_record(self::T . 'selfpost', (object) ['id' => $post->id, 'tries' => $post->tries,
            'timetries' => $post->timetries, 'hourtries' => $post->hourtries, 'timehour' => $post->timehour]);
        if ($post->hourtries > self::CARDSHOUR) {
            self::stop($post, $now);
            return true;
        }
        return $post->tries > self::CARDS;
    }

    /**
     * Whether too many books have come back at this post: nobody gives back a whole library from a tablet, but anyone with
     * its address could try.
     *
     * @param \stdClass $post
     * @param int $now
     * @return bool
     */
    public static function too_many_back(\stdClass $post, int $now): bool {
        global $DB;
        $since = fn(int $seconds) => $DB->count_records_select(
            self::T . 'loan',
            'returnpostid = ? AND timereturned >= ?',
            [$post->id, $now - $seconds]
        );
        $machine = !empty($post->machine);
        if ($since(HOURSECS) >= ($machine ? self::MACHINERETURNSHOUR : self::RETURNSHOUR)) {
            self::stop($post, $now);
            return true;
        }
        return $since(MINSECS) >= ($machine ? self::MACHINERETURNS : self::RETURNS);
    }

    /**
     * Stops a post until someone of the staff looks at it.
     *
     * @param \stdClass $post
     * @param int $now
     */
    private static function stop(\stdClass $post, int $now): void {
        global $DB;
        $post->timestopped = $now;
        $DB->set_field(self::T . 'selfpost', 'timestopped', $now, ['id' => $post->id]);
    }

    /**
     * Opens a post that had stopped.
     *
     * @param \stdClass $library
     * @param int $id
     */
    public static function reopen(\stdClass $library, int $id): void {
        global $DB;
        if ($DB->record_exists(self::T . 'selfpost', ['id' => $id, 'libraryid' => $library->id])) {
            $DB->update_record(self::T . 'selfpost', (object) ['id' => $id, 'timestopped' => null, 'tries' => 0, 'timetries' => 0,
                'hourtries' => 0, 'timehour' => 0]);
            \local_oksigeniaayvurenda\event\self_settings_changed::fire((int) $library->entityid);
        }
    }

    /**
     * How many posts of a library have stopped, for the desk.
     *
     * @param \stdClass $library
     * @return int
     */
    public static function stopped(\stdClass $library): int {
        global $DB;
        return $DB->count_records_select(
            self::T . 'selfpost',
            'libraryid = ? AND timestopped IS NOT NULL',
            [$library->id]
        );
    }

    /**
     * The secret of the tickets, made the first time.
     *
     * @return string
     */
    private static function secret(): string {
        $secret = (string) get_config('local_oksigeniaayvurenda', 'selfsecret');
        if ($secret === '') {
            $secret = random_string(64);
            set_config('selfsecret', $secret, 'local_oksigeniaayvurenda');
        }
        return $secret;
    }

    /**
     * What a post holds on to between one read and the next: a card was read here, a moment ago. Without it, no reader id the
     * screen could send would count: a book only goes out to a card that was really read.
     *
     * @param \stdClass $post
     * @param int $readerid
     * @param int $now
     * @return string
     */
    private static function ticket(\stdClass $post, int $readerid, int $now): string {
        $until = $now + self::TICKET;
        return $readerid . '.' . $until . '.' . hash_hmac('sha256', $post->id . '.' . $readerid . '.' . $until, self::secret());
    }

    /**
     * The reader a ticket stands for, if it is good, recent and still a reader who may be here.
     *
     * @param \stdClass $post
     * @param \stdClass $library
     * @param string $ticket
     * @param int $now
     * @return int 0 if none.
     */
    private static function holder(\stdClass $post, \stdClass $library, string $ticket, int $now): int {
        global $DB;
        if (!preg_match('/^(\d+)\.(\d+)\.([0-9a-f]{64})$/', $ticket, $parts)) {
            return 0;
        }
        [, $readerid, $until, $mac] = $parts;
        $good = hash_hmac('sha256', $post->id . '.' . $readerid . '.' . $until, self::secret());
        if (!hash_equals($good, $mac) || (int) $until < $now) {
            return 0;
        }
        $reader = $DB->get_record(self::T . 'reader', ['id' => $readerid, 'entityid' => $library->entityid,
            'status' => 'active']);
        return $reader && self::may($library, $reader, $now) ? (int) $reader->id : 0;
    }

    /**
     * What a post did in the last days, the latest first, for whoever goes over it.
     *
     * @param \stdClass $library
     * @param int|null $now
     * @return array[] loanid, what (lent or returned), at, title, reader, post and whether it can be undone.
     */
    public static function recent(\stdClass $library, ?int $now = null): array {
        global $DB;
        $since = ($now ?? time()) - self::DAYS * DAYSECS;
        $rows = $DB->get_records_sql(
            "SELECT l.id, l.itemid, l.postid, l.returnpostid, l.timeloaned, l.timereturned, r.title,
                    COALESCE(u.firstname, rd.firstname) AS firstname, COALESCE(u.lastname, rd.lastname) AS lastname,
                    p.name AS postname, q.name AS returnpostname
               FROM {" . self::T . "loan} l
               JOIN {" . self::T . "item} i ON i.id = l.itemid
               JOIN {" . self::T . "record} r ON r.id = i.recordid
               JOIN {" . self::T . "reader} rd ON rd.id = l.readerid
          LEFT JOIN {user} u ON u.id = rd.userid
          LEFT JOIN {" . self::T . "selfpost} p ON p.id = l.postid
          LEFT JOIN {" . self::T . "selfpost} q ON q.id = l.returnpostid
              WHERE l.libraryid = :libraryid
                    AND ((l.postid IS NOT NULL AND l.timeloaned >= :a) OR (l.returnpostid IS NOT NULL AND l.timereturned >= :b))
           ORDER BY l.timemodified DESC, l.id DESC",
            ['libraryid' => $library->id, 'a' => $since, 'b' => $since],
            0,
            100
        );
        $out = [];
        foreach ($rows as $row) {
            $reader = trim($row->firstname . ' ' . $row->lastname);
            if ($row->postid && (int) $row->timeloaned >= $since) {
                $out[] = ['loanid' => (int) $row->id, 'what' => 'lent', 'at' => (int) $row->timeloaned, 'title' => $row->title,
                    'reader' => $reader, 'post' => (string) $row->postname, 'undo' => $row->timereturned === null];
            }
            if ($row->returnpostid && $row->timereturned !== null && (int) $row->timereturned >= $since) {
                $out[] = ['loanid' => (int) $row->id, 'what' => 'returned', 'at' => (int) $row->timereturned,
                    'title' => $row->title, 'reader' => $reader, 'post' => (string) $row->returnpostname,
                    'undo' => self::can_come_undone($row)];
            }
        }
        usort($out, fn($a, $b) => $b['at'] <=> $a['at']);
        return $out;
    }

    /**
     * Undoes what a post did.
     *
     * @param \stdClass $library
     * @param int $loanid
     * @param string $what lent or returned.
     * @param int $staffid
     * @return bool Whether it could be.
     */
    public static function undo(\stdClass $library, int $loanid, string $what, int $staffid): bool {
        global $DB;
        $loan = $DB->get_record(self::T . 'loan', ['id' => $loanid, 'libraryid' => $library->id]);
        if (!$loan) {
            return false;
        }
        $desk = new desk($library, $staffid);
        if ($what === 'lent' && $loan->postid && $loan->timereturned === null) {
            $desk->undo($loanid, 'lent');
            return true;
        }
        if ($what === 'returned' && $loan->returnpostid && self::can_come_undone($loan)) {
            $desk->undo($loanid, 'returned');
            $DB->set_field(self::T . 'loan', 'returnpostid', null, ['id' => $loanid]);
            return true;
        }
        return false;
    }

    /**
     * Whether a book given back can go back to the person: not if it has gone to someone since, nor if it was lost or
     * withdrawn meanwhile.
     *
     * @param \stdClass $loan
     * @return bool
     */
    private static function can_come_undone(\stdClass $loan): bool {
        global $DB;
        return $loan->timereturned !== null
            && in_array($DB->get_field(self::T . 'item', 'status', ['id' => $loan->itemid]), ['available', 'held'], true)
            && !$DB->record_exists_select(
                self::T . 'loan',
                'itemid = ? AND id <> ? AND (timereturned IS NULL OR timeloaned >= ?)',
                [$loan->itemid, $loan->id, $loan->timeloaned]
            );
    }

    /**
     * Undoes everything a post did since a moment: for an address that got out, whatever the list shows.
     *
     * @param \stdClass $library
     * @param int $id
     * @param int $since
     * @param int $staffid
     * @return int How many things were undone.
     */
    public static function undo_since(\stdClass $library, int $id, int $since, int $staffid): int {
        global $DB;
        if (!$DB->record_exists(self::T . 'selfpost', ['id' => $id, 'libraryid' => $library->id])) {
            return 0;
        }
        \core_php_time_limit::raise(300);
        $count = 0;
        $rows = $DB->get_records_select(
            self::T . 'loan',
            'libraryid = ? AND ((returnpostid = ? AND timereturned >= ?) OR (postid = ? AND timeloaned >= ?))',
            [$library->id, $id, $since, $id, $since],
            'timemodified DESC, id DESC',
            'id, postid, returnpostid, timeloaned, timereturned'
        );
        foreach ($rows as $row) {
            if ((int) $row->returnpostid === $id && (int) $row->timereturned >= $since) {
                $count += self::undo($library, (int) $row->id, 'returned', $staffid) ? 1 : 0;
            }
            if ((int) $row->postid === $id && (int) $row->timeloaned >= $since) {
                $count += self::undo($library, (int) $row->id, 'lent', $staffid) ? 1 : 0;
            }
        }
        \local_oksigeniaayvurenda\event\self_post_undone::fire((int) $library->entityid, $id, ['count' => $count]);
        return $count;
    }

    /**
     * A date as a post says it.
     *
     * @param int $time
     * @return string
     */
    private static function date(int $time): string {
        return userdate($time, get_string('strftimedateshort', 'langconfig'), \core_date::get_server_timezone());
    }

    /**
     * An answer for the post.
     *
     * @param string $sound ok, look or no.
     * @param string $key A string of the plugin.
     * @param mixed $a
     * @param array $extra
     * @return array
     */
    private static function say(string $sound, string $key, $a = null, array $extra = []): array {
        return ['sound' => $sound, 'text' => get_string($key, 'local_oksigeniaayvurenda', $a)] + $extra;
    }
}
