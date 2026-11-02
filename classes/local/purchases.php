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
 * What a library buys: what readers ask for and what the staff see missing, what is decided, what is ordered and from whom,
 * what arrives, and what it all costs against the year's budget. When a book arrives and enters the catalogue, whoever asked
 * for it is told. Not accounting: what a library needs to know what to buy next and what it has left.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class purchases {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var string[] The states of a purchase, in their order. */
    public const STATES = ['asked', 'approved', 'ordered', 'received', 'declined'];

    /** @var string[] Still open: someone asking for one of these is not asking for anything new. */
    private const OPEN = ['asked', 'approved', 'ordered'];

    /** @var int What one reader may have waiting for an answer at once. */
    public const MAXOPEN = 5;

    /** @var int People waiting for a book, from which it is worth another copy. */
    private const QUEUE = 3;

    /** @var string[] Reader types without a stage, so the stages chosen do not apply to them. */
    private const ADULTS = ['teacher', 'staff', 'family'];

    /**
     * Whether readers may ask a library for books, and which.
     *
     * @param \stdClass $library
     * @return array suggest and stages (none: every stage).
     */
    public static function settings(\stdClass $library): array {
        $all = json_decode((string) ($library->settings ?? ''), true) ?: [];
        $mine = (array) ($all['purchases'] ?? []);
        return ['suggest' => !empty($mine['suggest']),
            'stages' => array_values(array_filter(array_map('strval', (array) ($mine['stages'] ?? [])), 'strlen'))];
    }

    /**
     * Saves whether readers may ask.
     *
     * @param \stdClass $library
     * @param array $settings suggest and stages.
     */
    public static function save_settings(\stdClass $library, array $settings): void {
        global $DB;
        $all = json_decode((string) $DB->get_field(self::T . 'library', 'settings', ['id' => $library->id]), true) ?: [];
        $stages = array_values(array_filter(array_map('strval', (array) ($settings['stages'] ?? [])), 'strlen'));
        $all['purchases'] = ['suggest' => !empty($settings['suggest']), 'stages' => $stages];
        $DB->set_field(self::T . 'library', 'settings', json_encode($all), ['id' => $library->id]);
        $library->settings = json_encode($all);
    }

    /**
     * Whether this reader may ask this library for a book.
     *
     * @param \stdClass $library
     * @param \stdClass|null $reader
     * @param int $now
     * @return bool
     */
    public static function may_ask(\stdClass $library, ?\stdClass $reader, int $now): bool {
        global $DB;
        $settings = self::settings($library);
        if (!$reader || !$settings['suggest'] || bank::is($library) || $reader->status !== 'active') {
            return false;
        }
        if ((int) $reader->entityid !== (int) $library->entityid) {
            return false;
        }
        if (!$settings['stages'] || in_array($reader->readertype, self::ADULTS, true)) {
            return true;
        }
        $year = years::around((int) $library->entityid, $now);
        $stage = $DB->get_field(self::T . 'member', 'stage', ['readerid' => $reader->id, 'yearid' => $year->id], IGNORE_MULTIPLE);
        return in_array((string) $stage, $settings['stages'], true);
    }

    /**
     * The ISBN of a text, as the catalogue keeps it, or ''.
     *
     * @param string $text
     * @return string
     */
    public static function isbn(string $text): string {
        $digits = preg_replace('/[^0-9X]/i', '', $text);
        if (strlen($digits) === 13) {
            return $digits;
        }
        if (strlen($digits) === 10) {
            $body = '978' . substr($digits, 0, 9);
            $sum = 0;
            foreach (str_split($body) as $i => $digit) {
                $sum += (int) $digit * ($i % 2 ? 3 : 1);
            }
            return $body . ((10 - $sum % 10) % 10);
        }
        return '';
    }

    /**
     * A reader asks for a book. If it is already asked for and not settled, they join whoever asked.
     *
     * @param \stdClass $library
     * @param \stdClass $reader
     * @param array $data title, author, isbn and note.
     * @param int|null $now
     * @return string What happened: asked, joined, known, full or empty.
     */
    public static function ask(\stdClass $library, \stdClass $reader, array $data, ?int $now = null): string {
        global $DB;
        $now = $now ?? time();
        $title = \core_text::substr(trim((string) ($data['title'] ?? '')), 0, 255);
        $isbn = self::isbn((string) ($data['isbn'] ?? ''));
        if ($title === '' && $isbn === '') {
            return 'empty';
        }
        $found = self::open_for($library, $title, $isbn);
        if ($found && $DB->record_exists(self::T . 'purchasewho', ['purchaseid' => $found->id, 'readerid' => $reader->id])) {
            return 'known';
        }
        [$in, $params] = $DB->get_in_or_equal(self::OPEN, SQL_PARAMS_NAMED);
        $mine = $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {" . self::T . "purchasewho} w
               JOIN {" . self::T . "purchase} p ON p.id = w.purchaseid
              WHERE w.readerid = :readerid AND p.status $in",
            $params + ['readerid' => $reader->id]
        );
        if ($mine >= self::MAXOPEN) {
            return 'full';
        }
        $id = $found ? (int) $found->id : (int) $DB->insert_record(self::T . 'purchase', [
            'libraryid' => $library->id,
            'title' => $title !== '' ? $title : $isbn,
            'author' => \core_text::substr(trim((string) ($data['author'] ?? '')), 0, 255) ?: null,
            'isbn' => $isbn ?: null,
            'note' => \core_text::substr(trim((string) ($data['note'] ?? '')), 0, 255) ?: null,
            'source' => 'reader',
            'copies' => 1,
            'status' => 'asked',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $DB->insert_record(self::T . 'purchasewho', ['purchaseid' => $id, 'readerid' => $reader->id, 'timecreated' => $now]);
        return $found ? 'joined' : 'asked';
    }

    /**
     * The purchase not yet settled that is this same book, if any.
     *
     * @param \stdClass $library
     * @param string $title
     * @param string $isbn
     * @return \stdClass|null
     */
    private static function open_for(\stdClass $library, string $title, string $isbn): ?\stdClass {
        global $DB;
        [$in, $params] = $DB->get_in_or_equal(self::OPEN, SQL_PARAMS_NAMED);
        $rows = $DB->get_records_select(self::T . 'purchase', "libraryid = :libraryid AND status $in", $params
            + ['libraryid' => $library->id], 'id');
        $same = \core_text::strtolower($title);
        foreach ($rows as $row) {
            if ($isbn !== '' && $row->isbn === $isbn) {
                return $row;
            }
            if ($same !== '' && \core_text::strtolower((string) $row->title) === $same) {
                return $row;
            }
        }
        return null;
    }

    /**
     * Someone of the staff puts a book on the list, ready to order.
     *
     * @param \stdClass $library
     * @param array $data title, author, isbn, note, source, recordid, copies, price and vendor.
     * @param int $userid
     * @param int|null $now
     * @return int Its id, 0 without a title.
     */
    public static function add(\stdClass $library, array $data, int $userid, ?int $now = null): int {
        global $DB;
        $now = $now ?? time();
        $title = \core_text::substr(trim((string) ($data['title'] ?? '')), 0, 255);
        if ($title === '') {
            return 0;
        }
        $recordid = (int) ($data['recordid'] ?? 0);
        if ($recordid && !$DB->record_exists(self::T . 'record', ['id' => $recordid, 'entityid' => $library->entityid])) {
            $recordid = 0;
        }
        $year = years::around((int) $library->entityid, $now);
        return (int) $DB->insert_record(self::T . 'purchase', [
            'libraryid' => $library->id,
            'yearid' => $year->id,
            'title' => $title,
            'author' => \core_text::substr(trim((string) ($data['author'] ?? '')), 0, 255) ?: null,
            'isbn' => self::isbn((string) ($data['isbn'] ?? '')) ?: null,
            'note' => \core_text::substr(trim((string) ($data['note'] ?? '')), 0, 255) ?: null,
            'source' => in_array($data['source'] ?? '', ['course', 'queue'], true) ? $data['source'] : 'staff',
            'recordid' => $recordid ?: null,
            'copies' => max(1, min(500, (int) ($data['copies'] ?? 1))),
            'price' => self::cents((string) ($data['price'] ?? '')),
            'vendor' => \core_text::substr(trim((string) ($data['vendor'] ?? '')), 0, 100) ?: null,
            'status' => 'approved',
            'decidedby' => $userid,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * A price as someone writes it, in cents; null if there is none.
     *
     * @param string $text
     * @return int|null
     */
    public static function cents(string $text): ?int {
        $text = trim(preg_replace('/[^0-9,.]/', '', $text));
        if ($text === '') {
            return null;
        }
        // The last separator is the decimal one, if two figures or fewer follow it; any other only groups thousands.
        if (preg_match('/^(.*)[,.](\d{1,2})$/', $text, $parts)) {
            return ((int) preg_replace('/\D/', '', $parts[1])) * 100 + (int) str_pad($parts[2], 2, '0');
        }
        return ((int) preg_replace('/\D/', '', $text)) * 100;
    }

    /**
     * A purchase of this library.
     *
     * @param \stdClass $library
     * @param int $id
     * @return \stdClass|null
     */
    private static function one(\stdClass $library, int $id): ?\stdClass {
        global $DB;
        return $DB->get_record(self::T . 'purchase', ['id' => $id, 'libraryid' => $library->id]) ?: null;
    }

    /**
     * Yes to something asked: how many, at what price and from whom.
     *
     * @param \stdClass $library
     * @param int $id
     * @param array $data copies, price and vendor.
     * @param int $userid
     * @param int|null $now
     * @return bool
     */
    public static function approve(\stdClass $library, int $id, array $data, int $userid, ?int $now = null): bool {
        global $DB;
        $now = $now ?? time();
        $row = self::one($library, $id);
        if (!$row || !in_array($row->status, ['asked', 'approved'], true)) {
            return false;
        }
        $DB->update_record(self::T . 'purchase', (object) ['id' => $id, 'status' => 'approved',
            'yearid' => years::around((int) $library->entityid, $now)->id,
            'copies' => max(1, min(500, (int) ($data['copies'] ?? 1))),
            'price' => self::cents((string) ($data['price'] ?? '')),
            'vendor' => \core_text::substr(trim((string) ($data['vendor'] ?? '')), 0, 100) ?: null,
            'decidedby' => $userid, 'timemodified' => $now]);
        return true;
    }

    /**
     * No to something asked. It can be asked again.
     *
     * @param \stdClass $library
     * @param int $id
     * @param int $userid
     * @param int|null $now
     * @return bool
     */
    public static function decline(\stdClass $library, int $id, int $userid, ?int $now = null): bool {
        global $DB;
        $row = self::one($library, $id);
        if (!$row || !in_array($row->status, ['asked', 'approved'], true)) {
            return false;
        }
        $DB->update_record(self::T . 'purchase', (object) ['id' => $id, 'status' => 'declined', 'decidedby' => $userid,
            'timemodified' => $now ?? time()]);
        return true;
    }

    /**
     * Turns what is approved for a vendor into an order.
     *
     * @param \stdClass $library
     * @param string $vendor '' for what has none.
     * @param int $userid
     * @param int|null $now
     * @return int The order, 0 if nothing was waiting.
     */
    public static function order(\stdClass $library, string $vendor, int $userid, ?int $now = null): int {
        global $DB;
        $now = $now ?? time();
        $rows = array_filter(
            $DB->get_records(self::T . 'purchase', ['libraryid' => $library->id, 'status' => 'approved']),
            fn($r) => (string) $r->vendor === $vendor
        );
        if (!$rows) {
            return 0;
        }
        $transaction = $DB->start_delegated_transaction();
        $order = (int) $DB->insert_record(self::T . 'purchaseorder', ['libraryid' => $library->id, 'vendor' => $vendor ?: null,
            'createdby' => $userid, 'timecreated' => $now]);
        foreach ($rows as $row) {
            $DB->update_record(self::T . 'purchase', (object) ['id' => $row->id, 'status' => 'ordered', 'orderid' => $order,
                'timemodified' => $now]);
        }
        $transaction->allow_commit();
        \local_oksigeniaayvurenda\event\purchase_ordered::fire((int) $library->entityid, $order, ['count' => count($rows)]);
        return $order;
    }

    /**
     * An order of this library, with what is in it.
     *
     * @param \stdClass $library
     * @param int $id
     * @return array|null order and rows.
     */
    public static function order_of(\stdClass $library, int $id): ?array {
        global $DB;
        $order = $DB->get_record(self::T . 'purchaseorder', ['id' => $id, 'libraryid' => $library->id]);
        return $order ? ['order' => $order, 'rows' => array_values($DB->get_records(
            self::T . 'purchase',
            ['orderid' => $id, 'libraryid' => $library->id],
            'title, id'
        ))] : null;
    }

    /**
     * It has arrived. With an ISBN or a code from the catalogue it is linked to its record, and whoever asked is told.
     *
     * @param \stdClass $library
     * @param int $id
     * @param string $code The code of a copy, if it is already catalogued, or ''.
     * @param int|null $now
     * @return bool
     */
    public static function received(\stdClass $library, int $id, string $code = '', ?int $now = null): bool {
        global $DB;
        $now = $now ?? time();
        $row = self::one($library, $id);
        if (!$row || !in_array($row->status, ['ordered', 'approved'], true)) {
            return false;
        }
        $recordid = (int) $row->recordid;
        $code = trim($code);
        if ($code !== '') {
            $item = desk::by_code('item', 'barcode', (int) $library->entityid, $code);
            $recordid = $item ? (int) $item->recordid : $recordid;
        }
        if (!$recordid && $row->isbn) {
            $found = $DB->get_records(self::T . 'record', ['entityid' => $library->entityid,
                'isbn' => $row->isbn], 'id', 'id', 0, 1);
            $recordid = $found ? (int) reset($found)->id : 0;
        }
        $DB->update_record(self::T . 'purchase', (object) ['id' => $id, 'status' => 'received', 'recordid' => $recordid ?: null,
            'timereceived' => $now, 'timemodified' => $now]);
        if ($recordid) {
            self::tell($library, $id, $recordid);
        }
        return true;
    }

    /**
     * A record has just entered the catalogue or got more copies: what was bought for it counts as arrived.
     *
     * @param \stdClass $library
     * @param int $recordid
     * @param int|null $now
     */
    public static function catalogued(\stdClass $library, int $recordid, ?int $now = null): void {
        global $DB;
        $now = $now ?? time();
        $isbn = (string) $DB->get_field(self::T . 'record', 'isbn', ['id' => $recordid, 'entityid' => $library->entityid]);
        if ($isbn === '') {
            return;
        }
        [$in, $params] = $DB->get_in_or_equal(['approved', 'ordered', 'received'], SQL_PARAMS_NAMED);
        $rows = $DB->get_records_select(
            self::T . 'purchase',
            "libraryid = :libraryid AND isbn = :isbn AND status $in AND recordid IS NULL",
            $params + ['libraryid' => $library->id, 'isbn' => $isbn]
        );
        foreach ($rows as $row) {
            $DB->update_record(self::T . 'purchase', (object) ['id' => $row->id, 'status' => 'received', 'recordid' => $recordid,
                'timereceived' => $row->timereceived ?: $now, 'timemodified' => $now]);
            self::tell($library, (int) $row->id, $recordid);
        }
    }

    /**
     * Tells those who asked for a book that it is here, once.
     *
     * @param \stdClass $library
     * @param int $id
     * @param int $recordid
     * @return int How many were told.
     */
    private static function tell(\stdClass $library, int $id, int $recordid): int {
        global $DB;
        $told = 0;
        $title = (string) $DB->get_field(self::T . 'record', 'title', ['id' => $recordid]);
        $url = (new \moodle_url('/local/oksigeniaayvurenda/record.php', ['library' => $library->slug,
            'id' => $recordid]))->out(false);
        $name = format_string($library->name, true, ['escape' => false]);
        foreach ($DB->get_records_select(self::T . 'purchasewho', 'purchaseid = ? AND timetold IS NULL', [$id]) as $who) {
            $reader = $DB->get_record(self::T . 'reader', ['id' => $who->readerid, 'status' => 'active']);
            $user = $reader && $reader->userid ? \core_user::get_user($reader->userid) : null;
            if (!$user || $user->deleted || $user->suspended) {
                continue;
            }
            $language = force_current_language((string) $user->lang);
            $str = fn(string $key) => get_string($key, 'local_oksigeniaayvurenda');
            $sign = mail::sign((int) $library->entityid, $reader, true, $library);
            $sent = notices::send(
                'purchasearrived',
                $user,
                $str('purchasearrivedsubject'),
                get_string('purchasearrivedbody', 'local_oksigeniaayvurenda', (object) ['title' => $title, 'library' => $name])
                    . ($sign ? "\n\n" . $sign['text'] : ''),
                $url,
                $title,
                $name,
                [
                    'library' => $library,
                    'young' => mail::young($reader),
                    'preheader' => $str('purchasearrivedsubject'),
                    'hello' => get_string('mailhello', 'local_oksigeniaayvurenda', $user->firstname),
                    'heading' => $str('purchasearrivedsubject'),
                    'lead' => [$str('purchasearrivedlead')],
                    'book' => mail::book($recordid, $title, catalogue::authors_of($recordid)),
                    'place' => ['label' => get_string('mailwhere', 'local_oksigeniaayvurenda'), 'value' => $name],
                    'button' => ['url' => $url, 'text' => $str('purchasearrivedbutton')],
                    'sign' => $sign,
                    'why' => get_string('mailwhyreader', 'local_oksigeniaayvurenda'),
                    'prefs' => true,
                ]
            );
            force_current_language($language);
            if ($sent) {
                $DB->set_field(self::T . 'purchasewho', 'timetold', time(), ['id' => $who->id]);
                $told++;
            }
        }
        return $told;
    }

    /**
     * The purchases of a library in a state, the oldest first, with who asked and why.
     *
     * @param \stdClass $library
     * @param string $status
     * @param int $limit
     * @return array[]
     */
    public static function list(\stdClass $library, string $status, int $limit = 200): array {
        global $DB;
        $rows = $DB->get_records(self::T . 'purchase', ['libraryid' => $library->id,
            'status' => $status], 'timemodified DESC, id DESC', '*', 0, $limit);
        if (!$rows) {
            return [];
        }
        [$in, $params] = $DB->get_in_or_equal(array_keys($rows), SQL_PARAMS_NAMED);
        $who = $DB->get_records_sql(
            "SELECT w.id, w.purchaseid, COALESCE(u.firstname, r.firstname) AS firstname,
                    COALESCE(u.lastname, r.lastname) AS lastname
               FROM {" . self::T . "purchasewho} w
               JOIN {" . self::T . "reader} r ON r.id = w.readerid
          LEFT JOIN {user} u ON u.id = r.userid
              WHERE w.purchaseid $in
           ORDER BY w.id",
            $params
        );
        $names = [];
        foreach ($who as $one) {
            $names[$one->purchaseid][] = trim($one->firstname . ' ' . $one->lastname);
        }
        $out = [];
        foreach ($rows as $row) {
            $out[] = (array) $row + ['names' => $names[$row->id] ?? []];
        }
        return $out;
    }

    /**
     * How many purchases there are in each state.
     *
     * @param \stdClass $library
     * @return int[]
     */
    public static function counts(\stdClass $library): array {
        global $DB;
        $rows = $DB->get_records_sql_menu(
            'SELECT status, COUNT(1) FROM {' . self::T . 'purchase} WHERE libraryid = ? GROUP BY status',
            [$library->id]
        );
        return array_map('intval', $rows) + array_fill_keys(self::STATES, 0);
    }

    /**
     * What is worth buying, seen from what the library already knows: required readings with too few copies for their
     * students, and books with a queue. Leaves out what is already on the list.
     *
     * @param \stdClass $library
     * @return array[] recordid, title, isbn, source, students, copies and waiting; the most needed first.
     */
    public static function ideas(\stdClass $library): array {
        global $DB;
        [$in, $params] = $DB->get_in_or_equal(self::OPEN, SQL_PARAMS_NAMED);
        $listed = array_map('intval', $DB->get_fieldset_select(
            self::T . 'purchase',
            'recordid',
            "libraryid = :libraryid AND recordid IS NOT NULL AND status $in",
            $params + ['libraryid' => $library->id]
        ));
        $out = [];
        foreach (readings::demand($library) as $row) {
            if (!in_array($row['recordid'], $listed, true) && $row['students'] > max(1, $row['copies'])) {
                $out[$row['recordid']] = ['recordid' => $row['recordid'], 'title' => $row['title'], 'source' => 'course',
                    'students' => $row['students'], 'copies' => $row['copies'], 'waiting' => $row['waiting']];
            }
        }
        $queues = $DB->get_records_sql(
            "SELECT h.recordid, COUNT(1) AS waiting, r.title
               FROM {" . self::T . "hold} h
               JOIN {" . self::T . "record} r ON r.id = h.recordid
              WHERE h.libraryid = :libraryid AND h.status = 'waiting'
           GROUP BY h.recordid, r.title
             HAVING COUNT(1) >= :queue
           ORDER BY COUNT(1) DESC",
            ['libraryid' => $library->id, 'queue' => self::QUEUE],
            0,
            50
        );
        foreach ($queues as $row) {
            $recordid = (int) $row->recordid;
            if (!isset($out[$recordid]) && !in_array($recordid, $listed, true)) {
                $copies = $DB->count_records_select(
                    self::T . 'item',
                    "recordid = ? AND libraryid = ? AND status <> 'lost' AND status <> 'withdrawn'",
                    [$recordid, $library->id]
                );
                $out[$recordid] = ['recordid' => $recordid, 'title' => $row->title, 'source' => 'queue', 'students' => 0,
                    'copies' => $copies, 'waiting' => (int) $row->waiting];
            }
        }
        $ids = array_keys($out);
        if ($ids) {
            [$in, $params] = $DB->get_in_or_equal($ids);
            $isbns = $DB->get_records_select_menu(self::T . 'record', "id $in", $params, '', 'id, isbn');
            foreach ($out as $id => $row) {
                $out[$id]['isbn'] = (string) ($isbns[$id] ?? '');
            }
        }
        return array_values($out);
    }

    /**
     * The budget of the year and what is spent of it: what is ordered and what has arrived.
     *
     * @param \stdClass $library
     * @param int|null $now
     * @return array yearid, year, amount (cents, null if none), spent (cents), currency.
     */
    public static function budget(\stdClass $library, ?int $now = null): array {
        global $DB;
        $year = years::around((int) $library->entityid, $now ?? time());
        $row = $DB->get_record(self::T . 'budget', ['libraryid' => $library->id, 'yearid' => $year->id]);
        $spent = (int) $DB->get_field_sql(
            "SELECT COALESCE(SUM(copies * price), 0)
               FROM {" . self::T . "purchase}
              WHERE libraryid = :libraryid AND yearid = :yearid AND status IN ('ordered', 'received') AND price IS NOT NULL",
            ['libraryid' => $library->id, 'yearid' => $year->id]
        );
        return ['yearid' => (int) $year->id, 'year' => (string) $year->name, 'amount' => $row ? (int) $row->amount : null,
            'spent' => $spent, 'currency' => $row ? (string) $row->currency : self::currency($library)];
    }

    /**
     * The currency a library last used, or the one of the site's language.
     *
     * @param \stdClass $library
     * @return string
     */
    private static function currency(\stdClass $library): string {
        global $DB;
        $found = $DB->get_records(self::T . 'budget', ['libraryid' => $library->id], 'id DESC', 'id, currency', 0, 1);
        return $found ? (string) reset($found)->currency : 'EUR';
    }

    /**
     * Saves the budget of the year.
     *
     * @param \stdClass $library
     * @param string $amount
     * @param string $currency
     * @param int|null $now
     */
    public static function save_budget(\stdClass $library, string $amount, string $currency, ?int $now = null): void {
        global $DB;
        $now = $now ?? time();
        $year = years::around((int) $library->entityid, $now);
        $cents = self::cents($amount);
        $currency = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : 'EUR';
        $row = $DB->get_record(self::T . 'budget', ['libraryid' => $library->id, 'yearid' => $year->id]);
        if ($cents === null) {
            if ($row) {
                $DB->delete_records(self::T . 'budget', ['id' => $row->id]);
            }
        } else if ($row) {
            $DB->update_record(self::T . 'budget', (object) ['id' => $row->id, 'amount' => $cents, 'currency' => $currency,
                'timemodified' => $now]);
        } else {
            $DB->insert_record(self::T . 'budget', ['libraryid' => $library->id, 'yearid' => $year->id, 'amount' => $cents,
                'currency' => $currency, 'timecreated' => $now, 'timemodified' => $now]);
        }
        \local_oksigeniaayvurenda\event\budget_changed::fire((int) $library->entityid);
    }

    /**
     * Money as the language of the page writes it.
     *
     * @param int $cents
     * @param string $currency
     * @return string
     */
    public static function money(int $cents, string $currency): string {
        if (class_exists('NumberFormatter')) {
            $format = new \NumberFormatter(str_replace('_', '-', current_language()), \NumberFormatter::CURRENCY);
            $text = $format->formatCurrency($cents / 100, $currency);
            if ($text !== false) {
                return $text;
            }
        }
        return number_format($cents / 100, 2, ',', '.') . ' ' . $currency;
    }

    /**
     * What a reader has asked for, the latest first, for their card.
     *
     * @param int $readerid
     * @param int $limit
     * @return array[] title and status.
     */
    public static function of_reader(int $readerid, int $limit = 10): array {
        global $DB;
        return array_map(fn($r) => ['title' => (string) $r->title,
            'status' => (string) $r->status], array_values($DB->get_records_sql(
                "SELECT p.id, p.title, p.status
               FROM {" . self::T . "purchasewho} w
               JOIN {" . self::T . "purchase} p ON p.id = w.purchaseid
              WHERE w.readerid = :readerid
           ORDER BY w.id DESC",
                ['readerid' => $readerid],
                0,
                $limit
            )));
    }
}
