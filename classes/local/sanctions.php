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
 * Days without lending and fines, for a centre that wants them.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

use local_oksigeniaayvurenda\core\desk\policy;
use local_oksigeniaayvurenda\core\desk\sanction_rules;
use local_oksigeniaayvurenda\core\spreadsheet;

/**
 * Off unless a centre turns it on. On, a book that comes back late leaves days without lending or a fine, worked out as
 * it comes back, in one row per book. The screen helps the conversation and does not replace it: the desk says so before
 * lending and the librarian may lend anyway, lift the days, take the money or forgive it. Money is only written down:
 * nothing here takes a payment.
 */
final class sanctions {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var string Days without lending. */
    public const KIND_DAYS = 'days';

    /** @var string The fine of a late book. */
    public const KIND_FINE = 'fine';

    /** @var string An amount the staff wrote down, as for a lost book. */
    public const KIND_CHARGE = 'charge';

    /** @var string Still counting, or still owed. */
    public const OPEN = 'open';

    /** @var string The days lifted before their time. */
    public const LIFTED = 'lifted';

    /** @var string The amount, paid at the desk. */
    public const PAID = 'paid';

    /** @var string The amount, forgiven. */
    public const WAIVED = 'waived';

    /** @var int How many rows a page lists. */
    public const LISTED = 200;

    /** @var string A row with the book it came from. */
    private const SELECT = 'SELECT s.*, r.title
               FROM {' . self::T . 'sanction} s
          LEFT JOIN {' . self::T . 'loan} l ON l.id = s.loanid
          LEFT JOIN {' . self::T . 'item} i ON i.id = l.itemid
          LEFT JOIN {' . self::T . 'record} r ON r.id = i.recordid';

    /**
     * The settings of a centre.
     *
     * @param int $entityid
     * @return array See sanction_rules::settings().
     */
    public static function settings(int $entityid): array {
        global $DB;
        $entity = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        return sanction_rules::settings((array) ($entity['sanctions'] ?? []));
    }

    /**
     * Saves the settings of a centre.
     *
     * @param int $entityid
     * @param array $settings
     */
    public static function save_settings(int $entityid, array $settings): void {
        global $DB;
        $entity = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        $entity['sanctions'] = sanction_rules::settings($settings);
        $DB->set_field(self::T . 'entity', 'settings', json_encode($entity), ['id' => $entityid]);
        \local_oksigeniaayvurenda\event\sanction_settings_changed::fire($entityid);
    }

    /**
     * Whether a late book costs anything at a centre.
     *
     * @param int $entityid
     * @return bool
     */
    public static function on(int $entityid): bool {
        return self::settings($entityid)['mode'] !== sanction_rules::NONE;
    }

    /**
     * An amount in the centre's currency, as the reader's language writes it.
     *
     * @param int $cents
     * @param string $currency
     * @return string
     */
    public static function money(int $cents, string $currency): string {
        $amount = $cents / 100;
        $format = class_exists('\NumberFormatter')
            ? new \NumberFormatter(get_string('localecldr', 'langconfig'), \NumberFormatter::CURRENCY) : null;
        $text = $format ? $format->formatCurrency($amount, $currency) : false;
        return $text === false ? number_format($amount, 2) . ' ' . $currency : $text;
    }

    /**
     * What a book coming back leaves, if anything. Never at a textbook bank, whose books have their own campaign.
     *
     * @param \stdClass $library
     * @param \stdClass $reader
     * @param \stdClass $loan
     * @param int $dayslate
     * @param int $staffid
     * @param int $now
     * @return array|null What the desk shows: id, text and what can be done with it; nothing to whoever may not know.
     */
    public static function after_return(
        \stdClass $library,
        \stdClass $reader,
        \stdClass $loan,
        int $dayslate,
        int $staffid,
        int $now
    ): ?array {
        global $DB;
        if ($dayslate <= 0 || bank::is($library)) {
            return null;
        }
        $settings = self::settings((int) $library->entityid);
        if (!sanction_rules::applies($settings, (string) $reader->readertype, self::stage($reader, $now))) {
            return null;
        }
        $row = ['entityid' => $library->entityid, 'readerid' => $reader->id, 'libraryid' => $library->id, 'loanid' => $loan->id,
            'dayslate' => $dayslate, 'status' => self::OPEN, 'createdby' => $staffid ?: null, 'timecreated' => $now,
            'timemodified' => $now];
        if ($settings['mode'] === sanction_rules::DAYS) {
            $days = sanction_rules::days($settings, $dayslate);
            if (!$days) {
                return null;
            }
            // Today does not count: from tomorrow, so many days, ending at the end of the last one.
            $until = (new \DateTimeImmutable('@' . $now))->setTimezone(zones::of($library))->setTime(0, 0)
                ->modify('+' . ($days + 1) . ' days')->getTimestamp();
            $row += ['kind' => self::KIND_DAYS, 'timeuntil' => $until];
        } else {
            $cents = sanction_rules::cents($settings, $dayslate);
            if (!$cents) {
                return null;
            }
            $row += ['kind' => self::KIND_FINE, 'cents' => $cents];
        }
        $row['id'] = $DB->insert_record(self::T . 'sanction', $row);
        return self::may_know($library, $staffid) ? self::answer((object) $row, $settings) : null;
    }

    /**
     * What the desk says of what a book just left, and what the librarian can do about it there and then.
     *
     * @param \stdClass $row
     * @param array $settings
     * @return array id, text and actions.
     */
    private static function answer(\stdClass $row, array $settings): array {
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        if ($row->kind === self::KIND_DAYS) {
            $text = $str('sanctionleftdays', self::day((int) $row->timeuntil, (int) $row->entityid));
            $actions = [['do' => 'lift', 'label' => $str('sanctionlift')]];
        } else {
            $text = $str('sanctionleftfine', self::money((int) $row->cents, $settings['currency']));
            $actions = [['do' => 'pay', 'label' => $str('sanctionpay')], ['do' => 'waive', 'label' => $str('sanctionwaive')]];
        }
        return ['id' => (int) $row->id, 'text' => $text, 'actions' => $actions];
    }

    /**
     * Whether someone at a desk may know what a reader owes or how long they wait: the staff of the centre; not whoever
     * only keeps this library, nor a post, which has its own neutral words.
     *
     * @param \stdClass $library
     * @param int $staffid
     * @return bool
     */
    public static function may_know(\stdClass $library, int $staffid): bool {
        return $staffid && access::can('local/oksigeniaayvurenda:desk', $library, $staffid);
    }

    /**
     * A return undone leaves nothing behind.
     *
     * @param int $loanid
     */
    public static function undo_return(int $loanid): void {
        global $DB;
        // What was paid was paid, even if the book turns out not to have come back.
        $DB->delete_records(self::T . 'sanction', ['loanid' => $loanid, 'status' => self::OPEN]);
    }

    /**
     * Where a reader stands now.
     *
     * @param int $entityid
     * @param int $readerid
     * @param int $now
     * @return array until (the moment they may borrow again, or 0) and owes (in hundredths).
     */
    public static function state(int $entityid, int $readerid, int $now): array {
        global $DB;
        // What a centre no longer does stops counting; what is owed is still owed, so that it can be closed.
        $until = self::settings($entityid)['mode'] === sanction_rules::DAYS ? (int) $DB->get_field_sql(
            'SELECT MAX(timeuntil) FROM {' . self::T . 'sanction}
              WHERE readerid = :readerid AND entityid = :entityid AND kind = :kind AND status = :status AND timeuntil > :now',
            ['readerid' => $readerid, 'entityid' => $entityid, 'kind' => self::KIND_DAYS, 'status' => self::OPEN, 'now' => $now]
        ) : 0;
        $owes = (int) $DB->get_field_sql(
            'SELECT SUM(cents) FROM {' . self::T . 'sanction}
              WHERE readerid = :readerid AND entityid = :entityid AND kind <> :kind AND status = :status',
            ['readerid' => $readerid, 'entityid' => $entityid, 'kind' => self::KIND_DAYS, 'status' => self::OPEN]
        );
        return ['until' => $until, 'owes' => $owes];
    }

    /**
     * Why the desk should stop before lending to a reader, if at all.
     *
     * @param int $entityid
     * @param int $readerid
     * @param int $now
     * @return array|null reason (of policy), and the words the desk says it with: until or owes.
     */
    public static function stop(int $entityid, int $readerid, int $now): ?array {
        $settings = self::settings($entityid);
        if ($settings['mode'] === sanction_rules::NONE) {
            return null;
        }
        $state = self::state($entityid, $readerid, $now);
        if ($state['until'] > $now) {
            return ['reason' => policy::SUSPENDED, 'until' => self::day($state['until'], $entityid)];
        }
        if ($settings['mode'] === sanction_rules::FINE && $state['owes'] >= $settings['block']) {
            return ['reason' => policy::OWES, 'owes' => self::money($state['owes'], $settings['currency'])];
        }
        return null;
    }

    /**
     * What the desk shows of a reader.
     *
     * @param \stdClass $library
     * @param int $readerid
     * @param int $now
     * @param int $staffid
     * @return array|null text and actions; nothing to whoever may not know.
     */
    public static function panel(\stdClass $library, int $readerid, int $now, int $staffid): ?array {
        if (!self::may_know($library, $staffid)) {
            return null;
        }
        $entityid = (int) $library->entityid;
        $state = self::state($entityid, $readerid, $now);
        if (!$state['until'] && !$state['owes']) {
            return null;
        }
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        $lines = [];
        $actions = [];
        if ($state['until']) {
            $lines[] = $str('sanctionpanelday', self::day($state['until'], $entityid));
            $actions[] = ['do' => 'lift', 'label' => $str('sanctionlift')];
        }
        if ($state['owes']) {
            $lines[] = $str('sanctionpanelowes', self::money($state['owes'], self::settings($entityid)['currency']));
            $actions[] = ['do' => 'pay', 'label' => $str('sanctionpay')];
            $actions[] = ['do' => 'waive', 'label' => $str('sanctionwaive')];
        }
        return ['text' => implode(' ', $lines), 'actions' => $actions];
    }

    /**
     * What a reader is told of themselves, where they look at their own books.
     *
     * @param int $entityid
     * @param int $readerid
     * @param int $now
     * @return string Empty when there is nothing to say.
     */
    public static function for_reader(int $entityid, int $readerid, int $now): string {
        $state = self::state($entityid, $readerid, $now);
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        $lines = [];
        if ($state['until']) {
            $lines[] = $str('sanctionreaderdays', self::day($state['until'], $entityid));
        }
        if ($state['owes']) {
            $lines[] = $str('sanctionreaderowes', self::money($state['owes'], self::settings($entityid)['currency']));
        }
        return implode(' ', $lines);
    }

    /**
     * What a notice of a late book warns of, for a reader who answers for it; '' otherwise.
     *
     * @param int $entityid
     * @param \stdClass $reader
     * @param int $now
     * @return string
     */
    public static function warning(int $entityid, \stdClass $reader, int $now): string {
        $settings = self::settings($entityid);
        if (!sanction_rules::applies($settings, (string) $reader->readertype, self::stage($reader, $now))) {
            return '';
        }
        return $settings['mode'] === sanction_rules::DAYS
            ? get_string('sanctionwarndays', 'local_oksigeniaayvurenda')
            : get_string(
                'sanctionwarnfine',
                'local_oksigeniaayvurenda',
                self::money((int) $settings['cents'], $settings['currency'])
            );
    }

    /**
     * Lets a reader borrow again today.
     *
     * @param \stdClass $library
     * @param int $readerid
     * @param int $staffid
     * @param int $now
     * @param int $id One row only.
     * @return int How many rows closed.
     */
    public static function lift(\stdClass $library, int $readerid, int $staffid, int $now, int $id = 0): int {
        $where = ['readerid' => $readerid, 'kind' => self::KIND_DAYS] + ($id ? ['id' => $id] : []);
        $closed = self::close_rows($library, $where, self::LIFTED, $staffid, $now);
        if ($closed) {
            \local_oksigeniaayvurenda\event\sanction_lifted::fire((int) $library->entityid, $readerid);
        }
        return $closed;
    }

    /**
     * Closes what a reader owes, all of it or one row.
     *
     * @param \stdClass $library
     * @param int $readerid
     * @param string $status PAID or WAIVED.
     * @param int $staffid
     * @param int $now
     * @param int $id One row only.
     * @return int How many rows closed.
     */
    public static function settle(\stdClass $library, int $readerid, string $status, int $staffid, int $now, int $id = 0): int {
        $where = ['readerid' => $readerid] + ($id ? ['id' => $id] : []);
        $closed = self::close_rows($library, $where, $status, $staffid, $now, true);
        if ($closed) {
            $event = $status === self::PAID ? \local_oksigeniaayvurenda\event\fine_paid::class
                : \local_oksigeniaayvurenda\event\fine_waived::class;
            $event::fire((int) $library->entityid, $readerid);
        }
        return $closed;
    }

    /**
     * Closes the open rows that match, in the library's centre only.
     *
     * @param \stdClass $library
     * @param array $where
     * @param string $status
     * @param int $staffid
     * @param int $now
     * @param bool $money Only the rows with an amount.
     * @return int
     */
    private static function close_rows(
        \stdClass $library,
        array $where,
        string $status,
        int $staffid,
        int $now,
        bool $money = false
    ): int {
        global $DB;
        $rows = $DB->get_records(self::T . 'sanction', $where + ['entityid' => $library->entityid, 'status' => self::OPEN]);
        $closed = 0;
        foreach ($rows as $row) {
            if ($money && $row->kind === self::KIND_DAYS) {
                continue;
            }
            $DB->update_record(self::T . 'sanction', (object) ['id' => $row->id, 'status' => $status,
                'closedby' => $staffid ?: null, 'timeclosed' => $now, 'timemodified' => $now]);
            $closed++;
        }
        return $closed;
    }

    /**
     * Opens again what was closed by mistake.
     *
     * @param \stdClass $library
     * @param int $id
     * @param int $now
     * @return bool
     */
    public static function reopen(\stdClass $library, int $id, int $now): bool {
        global $DB;
        $row = $DB->get_record(self::T . 'sanction', ['id' => $id, 'entityid' => $library->entityid]);
        if (!$row || $row->status === self::OPEN) {
            return false;
        }
        $DB->update_record(self::T . 'sanction', (object) ['id' => $row->id, 'status' => self::OPEN, 'closedby' => null,
            'timeclosed' => null, 'timemodified' => $now]);
        \local_oksigeniaayvurenda\event\sanction_reopened::fire((int) $library->entityid, (int) $row->readerid);
        return true;
    }

    /**
     * Writes down an amount a reader owes, as for a lost book.
     *
     * @param \stdClass $library
     * @param int $readerid
     * @param int $cents
     * @param string $note
     * @param int $staffid
     * @param int $now
     * @return int The row, or 0 for a reader of another centre.
     */
    public static function charge(\stdClass $library, int $readerid, int $cents, string $note, int $staffid, int $now): int {
        global $DB;
        if (!$DB->record_exists(self::T . 'reader', ['id' => $readerid, 'entityid' => $library->entityid])) {
            return 0;
        }
        $id = $DB->insert_record(self::T . 'sanction', ['entityid' => $library->entityid, 'readerid' => $readerid,
            'libraryid' => $library->id, 'kind' => self::KIND_CHARGE, 'dayslate' => 0, 'cents' => $cents,
            'note' => \core_text::substr(trim($note), 0, 255) ?: null, 'status' => self::OPEN,
            'createdby' => $staffid ?: null, 'timecreated' => $now, 'timemodified' => $now]);
        \local_oksigeniaayvurenda\event\charge_added::fire((int) $library->entityid, $readerid);
        return (int) $id;
    }

    /**
     * What a reader has had, for their record: what is open first.
     *
     * @param int $entityid
     * @param int $readerid
     * @param int $now
     * @param int $limit How many, the newest first; 0 for all.
     * @return array[] id, text, when, state, open, money.
     */
    public static function of_reader(int $entityid, int $readerid, int $now, int $limit = self::LISTED): array {
        global $DB;
        $rows = $DB->get_records_sql(self::SELECT . ' WHERE s.entityid = :entityid AND s.readerid = :readerid
           ORDER BY s.timecreated DESC, s.id DESC', ['entityid' => $entityid, 'readerid' => $readerid], 0, $limit);
        $currency = self::settings($entityid)['currency'];
        $out = array_map(fn($row) => self::row_of($row, $currency, $now), array_values($rows));
        usort($out, fn($a, $b) => $b['open'] <=> $a['open']);
        return $out;
    }

    /**
     * A row in words.
     *
     * @param \stdClass $row
     * @param string $currency
     * @param int $now
     * @return array
     */
    private static function row_of(\stdClass $row, string $currency, int $now): array {
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        $late = (int) $row->dayslate;
        $why = (string) ($row->note ?? '');
        if ($row->kind !== self::KIND_CHARGE) {
            $why = $str($late === 1 ? 'sanctionwhyone' : 'sanctionwhy', (object) ['title' => (string) ($row->title ?? ''),
                'days' => $late]);
        }
        $counting = $row->kind === self::KIND_DAYS && $row->status === self::OPEN && (int) $row->timeuntil > $now;
        $open = $row->kind === self::KIND_DAYS ? $counting : $row->status === self::OPEN;
        if ($row->kind === self::KIND_DAYS) {
            $what = $str('sanctionrowdays', self::day((int) $row->timeuntil, (int) $row->entityid));
            $state = $row->status === self::LIFTED ? $str('sanctionstatelifted') : ($counting ? '' : $str('sanctionstateover'));
        } else {
            $what = self::money((int) $row->cents, $currency);
            $state = $row->status === self::OPEN ? '' : $str('sanctionstate' . $row->status);
        }
        return ['id' => (int) $row->id, 'kind' => $row->kind, 'what' => $what, 'why' => $why, 'state' => $state, 'open' => $open,
            'money' => $row->kind !== self::KIND_DAYS, 'closedrow' => $row->status !== self::OPEN,
            'when' => zones::date((int) $row->timecreated, 'strftimedate', (int) $row->entityid)];
    }

    /**
     * What is open now in a centre, and the sums of this year.
     *
     * @param int $entityid
     * @param int $now
     * @return array suspended (name, id, until), owing (id, cents, money, rows), more, and paid, waived, pending and lifted.
     */
    public static function now(int $entityid, int $now): array {
        global $DB;
        $settings = self::settings($entityid);
        $year = years::around($entityid, $now);
        $suspended = [];
        if ($settings['mode'] === sanction_rules::DAYS) {
            $rows = $DB->get_records_sql(
                'SELECT readerid, MAX(timeuntil) AS until FROM {' . self::T . 'sanction}
                  WHERE entityid = :entityid AND kind = :kind AND status = :status AND timeuntil > :now
               GROUP BY readerid ORDER BY MAX(timeuntil) DESC',
                ['entityid' => $entityid, 'kind' => self::KIND_DAYS, 'status' => self::OPEN, 'now' => $now],
                0,
                self::LISTED
            );
            foreach ($rows as $row) {
                $suspended[] = ['id' => (int) $row->readerid, 'until' => self::day((int) $row->until, $entityid)];
            }
        }
        $rows = $DB->get_records_sql(
            'SELECT readerid, SUM(cents) AS cents, COUNT(1) AS howmany FROM {' . self::T . 'sanction}
              WHERE entityid = :entityid AND kind <> :kind AND status = :status
           GROUP BY readerid ORDER BY SUM(cents) DESC',
            ['entityid' => $entityid, 'kind' => self::KIND_DAYS, 'status' => self::OPEN],
            0,
            self::LISTED
        );
        $owing = [];
        foreach ($rows as $row) {
            $owing[] = ['id' => (int) $row->readerid, 'cents' => (int) $row->cents, 'rows' => (int) $row->howmany,
                'money' => self::money((int) $row->cents, $settings['currency'])];
        }
        $sum = fn(string $status, string $clock) => (int) $DB->get_field_sql(
            'SELECT SUM(cents) FROM {' . self::T . "sanction}
              WHERE entityid = :entityid AND kind <> :kind AND status = :status AND $clock >= :start AND $clock < :end",
            ['entityid' => $entityid, 'kind' => self::KIND_DAYS, 'status' => $status, 'start' => $year->timestart,
                'end' => $year->timeend]
        );
        $pending = (int) $DB->get_field_sql(
            'SELECT SUM(cents) FROM {' . self::T . 'sanction} WHERE entityid = :entityid AND kind <> :kind AND status = :status',
            ['entityid' => $entityid, 'kind' => self::KIND_DAYS, 'status' => self::OPEN]
        );
        $hadthisyear = $DB->count_records_select(
            self::T . 'sanction',
            'entityid = :entityid AND kind = :kind AND timecreated >= :start AND timecreated < :end',
            ['entityid' => $entityid, 'kind' => self::KIND_DAYS, 'start' => $year->timestart, 'end' => $year->timeend]
        );
        return ['suspended' => $suspended, 'owing' => $owing, 'paid' => $sum(self::PAID, 'timeclosed'),
            'waived' => $sum(self::WAIVED, 'timeclosed'), 'pending' => $pending, 'daysthisyear' => $hadthisyear];
    }

    /**
     * What has been closed lately, for the report.
     *
     * @param int $entityid
     * @param int $now
     * @return array[] readerid and the row in words.
     */
    public static function closed(int $entityid, int $now): array {
        global $DB;
        $rows = $DB->get_records_sql(self::SELECT . ' WHERE s.entityid = :entityid AND s.status <> :status
           ORDER BY s.timeclosed DESC, s.id DESC', ['entityid' => $entityid, 'status' => self::OPEN], 0, self::LISTED);
        $currency = self::settings($entityid)['currency'];
        return array_map(
            fn($row) => ['readerid' => (int) $row->readerid] + self::row_of($row, $currency, $now),
            array_values($rows)
        );
    }

    /**
     * Every row of a centre, to take away in a file: amounts as plain numbers, nothing a spreadsheet would run.
     *
     * @param int $entityid
     * @param int $now
     * @return array[] One list per row.
     */
    public static function export(int $entityid, int $now): array {
        global $DB;
        $desk = null;
        $names = [];
        $library = $DB->get_record(self::T . 'library', ['entityid' => $entityid], '*', IGNORE_MULTIPLE);
        if ($library) {
            $desk = new desk($library, 0, $now);
        }
        $currency = self::settings($entityid)['currency'];
        $zone = zones::name($entityid);
        $out = [];
        $rows = $DB->get_recordset_sql(
            self::SELECT . ' WHERE s.entityid = :entityid ORDER BY s.timecreated, s.id',
            ['entityid' => $entityid]
        );
        foreach ($rows as $row) {
            $id = (int) $row->readerid;
            if (!isset($names[$id])) {
                $names[$id] = $desk ? $desk->reader($id) : ['name' => '', 'cardcode' => ''];
            }
            $out[] = spreadsheet::row([
                userdate((int) $row->timecreated, '%Y-%m-%d', $zone, false),
                $names[$id]['name'],
                $names[$id]['cardcode'],
                $row->kind,
                (string) ($row->title ?? $row->note ?? ''),
                (int) $row->dayslate,
                $row->timeuntil ? userdate((int) $row->timeuntil - 1, '%Y-%m-%d', $zone, false) : '',
                $row->cents !== null ? number_format((int) $row->cents / 100, 2, '.', '') : '',
                $row->cents !== null ? $currency : '',
                $row->status,
                $row->timeclosed ? userdate((int) $row->timeclosed, '%Y-%m-%d', $zone, false) : '',
            ]);
        }
        $rows->close();
        return $out;
    }

    /**
     * Tells the readers what came back late has left, and what the staff wrote down.
     *
     * @param int|null $now
     * @param int[]|null $centres Only these centres (those whose morning it is); all without it.
     * @return int Messages sent.
     */
    public static function notify(?int $now = null, ?array $centres = null): int {
        global $DB;
        $now = $now ?? time();
        $sent = 0;
        // Only what is recent: a copy that returns tells nobody about the past. A month, so that what a centre getting
        // ready left untold goes when it opens.
        // Page after page, so that a busy night of one centre does not leave the others waiting for tomorrow.
        $after = 0;
        do {
            $rows = $DB->get_records_select(
                self::T . 'sanction',
                'timetold IS NULL AND status = :status AND timecreated > :recent AND id > :after',
                ['status' => self::OPEN, 'recent' => $now - 30 * DAYSECS, 'after' => $after],
                'id',
                '*',
                0,
                self::LISTED
            );
            foreach ($rows as $row) {
                $after = (int) $row->id;
                $entityid = (int) $DB->get_field(self::T . 'library', 'entityid', ['id' => $row->libraryid]);
                // Not marked while its centre is closed (it is told when it opens), nor before its morning.
                if (($entityid && access::closed($entityid)) || ($centres !== null && !in_array($entityid, $centres, true))) {
                    continue;
                }
                $DB->set_field(self::T . 'sanction', 'timetold', $now, ['id' => $row->id]);
                $sent += self::tell($row, $now) ? 1 : 0;
            }
        } while (count($rows) === self::LISTED);
        return $sent;
    }

    /**
     * Tells a reader about one row.
     *
     * @param \stdClass $row
     * @param int $now
     * @return bool
     */
    private static function tell(\stdClass $row, int $now): bool {
        global $DB;
        $reader = $DB->get_record(self::T . 'reader', ['id' => $row->readerid]);
        $user = $reader && $reader->userid ? \core_user::get_user($reader->userid) : null;
        if (!$user || !empty($user->deleted) || !empty($user->suspended)) {
            return false;
        }
        $library = $DB->get_record(self::T . 'library', ['id' => $row->libraryid]);
        if (!$library || access::closed((int) $library->entityid)) {
            return false;
        }
        $language = force_current_language((string) $user->lang);
        $entityid = (int) $library->entityid;
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        $record = $row->loanid ? $DB->get_record_sql(
            'SELECT r.id, r.title FROM {' . self::T . 'loan} l
               JOIN {' . self::T . 'item} i ON i.id = l.itemid
               JOIN {' . self::T . 'record} r ON r.id = i.recordid
              WHERE l.id = ?',
            [$row->loanid]
        ) : null;
        $title = $record ? (string) $record->title : (string) ($row->note ?? '');
        $name = format_string($library->name, true, ['escape' => false]);
        if ($row->kind === self::KIND_DAYS) {
            $value = zones::date((int) $row->timeuntil - 1, 'strftimedateshort', $entityid);
            $key = 'sanctionnoticedays';
            $label = $str('sanctionnoticeuntil');
        } else {
            $value = self::money((int) $row->cents, self::settings($entityid)['currency']);
            $key = $row->kind === self::KIND_FINE ? 'sanctionnoticefine' : 'sanctionnoticecharge';
            $label = $str('sanctionnoticeamount');
        }
        $a = (object) ['title' => $title, 'library' => $name, 'value' => $value, 'days' => (int) $row->dayslate];
        $sign = mail::sign($entityid, $reader, true, $library);
        $url = (new \moodle_url('/local/oksigeniaayvurenda/card.php', ['library' => $library->slug]))->out(false);
        $design = [
            'library' => $library,
            'young' => mail::young($reader),
            'preheader' => $str($key . 'body', $a),
            'hello' => $str('mailhello', $user->firstname),
            'heading' => $str($key . 'subject', $a),
            'lead' => [$str($key . 'body', $a)],
            'stamp' => ['label' => $label, 'value' => $value],
            'button' => ['url' => $url, 'text' => $str('sanctionnoticebutton')],
            'after' => [$str('sanctionnoticeafter')],
            'sign' => $sign,
            'why' => $str('mailwhyreader'),
            'prefs' => true,
        ] + ($record ? ['book' => mail::book((int) $record->id, $title, catalogue::authors_of((int) $record->id))] : []);
        $sent = notices::send(
            'sanction',
            $user,
            $str($key . 'subject', $a),
            $str($key . 'body', $a) . ($sign ? "\n\n" . $sign['text'] : ''),
            $url,
            $name,
            $name,
            $design
        );
        force_current_language($language);
        return $sent;
    }

    /**
     * A reader's stage this year.
     *
     * @param \stdClass $reader
     * @param int $now
     * @return string|null
     */
    private static function stage(\stdClass $reader, int $now): ?string {
        global $DB;
        $year = years::around((int) $reader->entityid, $now);
        return $DB->get_field(self::T . 'member', 'stage', ['readerid' => $reader->id,
            'yearid' => $year->id], IGNORE_MULTIPLE) ?: null;
    }

    /**
     * The last day of a stretch that ends at a moment, in words.
     *
     * @param int $until
     * @param int $entityid
     * @return string
     */
    private static function day(int $until, int $entityid): string {
        return zones::date($until - 1, 'strftimedate', $entityid);
    }
}
