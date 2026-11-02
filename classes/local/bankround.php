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
 * The two big moments of a textbook bank: collecting at the end of the year, with a date, reminders and what was lost; and handing
 * out at the start of the next, with what each set is short of and which classes are waiting.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class bankround {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var string The plugin. */
    private const COMPONENT = 'local_oksigeniaayvurenda';

    /** @var int How long before the end of the year collecting begins. */
    private const LEAD = 8 * WEEKSECS;

    /** @var string What a loan says when the book did not come back. */
    public const LOST = 'lost';

    /**
     * Which of the two moments it is now: the page opens there.
     *
     * @param \stdClass $library
     * @param int $now
     * @return string collect or hand.
     */
    public static function moment(\stdClass $library, int $now): string {
        return $now >= self::since($library, $now) ? 'collect' : 'hand';
    }

    /**
     * When collecting begins: some weeks before the end of the year or before the date set, if that comes sooner.
     *
     * @param \stdClass $library
     * @param int $now
     * @return int
     */
    private static function since(\stdClass $library, int $now): int {
        $year = years::around((int) $library->entityid, $now);
        $deadline = (int) self::state($library)['deadline'];
        return min((int) $year->timeend, $deadline ?: (int) $year->timeend) - self::LEAD;
    }

    /**
     * What is kept about the round: its date and when the reminders went.
     *
     * @param \stdClass $library
     * @return array deadline, readers (when the reminder to readers went), tutors, readerssent, tutorssent.
     */
    public static function state(\stdClass $library): array {
        global $DB;
        $settings = json_decode((string) $DB->get_field(self::T . 'library', 'settings', ['id' => $library->id]), true) ?: [];
        return (array) ($settings['bankround'] ?? []) + ['deadline' => 0, 'readers' => 0, 'tutors' => 0, 'readerssent' => 0,
            'tutorssent' => 0];
    }

    /**
     * Keeps something of the round.
     *
     * @param \stdClass $library
     * @param array $changes
     */
    public static function save_state(\stdClass $library, array $changes): void {
        global $DB;
        $settings = json_decode((string) $DB->get_field(self::T . 'library', 'settings', ['id' => $library->id]), true) ?: [];
        $settings['bankround'] = $changes + self::state($library);
        $DB->set_field(self::T . 'library', 'settings', json_encode($settings), ['id' => $library->id]);
    }

    /**
     * How the collecting goes: the books still out and those already back this time, what is left over from an earlier year, who
     * has no account to be told by, and each class.
     *
     * @param \stdClass $library
     * @param int $now
     * @return array out, back, total, percent, holders, noaccount, old, classes (group, holding, out).
     */
    public static function collection(\stdClass $library, int $now): array {
        global $DB;
        $year = years::around((int) $library->entityid, $now);
        $since = self::since($library, $now);
        $out = $DB->count_records(self::T . 'loan', ['libraryid' => $library->id, 'timereturned' => null]);
        $back = $DB->count_records_select(
            self::T . 'loan',
            'libraryid = :libraryid AND timereturned >= :since AND (conditionin IS NULL OR conditionin <> :lost)',
            ['libraryid' => $library->id, 'since' => $since, 'lost' => self::LOST]
        );
        $old = $DB->count_records_select(
            self::T . 'loan',
            'libraryid = :libraryid AND timereturned IS NULL AND timedue < :start',
            ['libraryid' => $library->id, 'start' => (int) $year->timestart]
        );
        $holders = self::holders($library);
        $total = $out + $back;
        return [
            'out' => $out,
            'back' => $back,
            'total' => $total,
            'percent' => $total ? (int) floor(100 * $back / $total) : 0,
            'holders' => count($holders),
            'noaccount' => count(array_filter($holders, fn($h) => empty($h->userid))),
            'old' => $old,
            'classes' => array_values(array_filter(bank::classes($library, $now), fn($c) => $c['out'] > 0)),
        ];
    }

    /**
     * How the handing out stands: who waits, what each set is short of, and the groups that do not match this year's.
     *
     * @param \stdClass $library
     * @param int $now
     * @return array waiting, short, sets (from bank::needs(), with short), classes, nosets (groups without a set), empty (groups
     *     of a set with no pupils this year).
     */
    public static function handout(\stdClass $library, int $now): array {
        global $DB;
        $year = years::around((int) $library->entityid, $now);
        $classes = bank::classes($library, $now);
        $sets = bank::needs($library, $now);
        $short = 0;
        foreach ($sets as &$set) {
            $set['short'] = array_sum(array_column($set['titles'], 'short'));
            $short += $set['short'];
        }
        unset($set);
        $groupsofsets = [];
        foreach (bank::sets($library) as $set) {
            $groupsofsets = array_merge($groupsofsets, $set['groups']);
        }
        $thisyear = $DB->get_fieldset_select(self::T . 'member', 'DISTINCT groupname', 'yearid = ?', [$year->id]);
        return [
            'waiting' => array_sum(array_column($classes, 'waiting')),
            'short' => $short,
            'sets' => $sets,
            'classes' => array_values(array_filter($classes, fn($c) => $c['pupils'] > 0)),
            'nosets' => array_values(array_diff(array_map('strval', $thisyear), $groupsofsets)),
            'empty' => array_values(array_diff($groupsofsets, array_map('strval', $thisyear))),
        ];
    }

    /**
     * Who still has books from the bank.
     *
     * @param \stdClass $library
     * @return \stdClass[] Readers: id, userid, firstname, lastname.
     */
    public static function holders(\stdClass $library): array {
        global $DB;
        return $DB->get_records_sql(
            "SELECT DISTINCT r.id, r.userid, r.firstname, r.lastname
               FROM {" . self::T . "reader} r
               JOIN {" . self::T . "loan} l ON l.readerid = r.id
              WHERE l.libraryid = :libraryid AND l.timereturned IS NULL",
            ['libraryid' => $library->id]
        );
    }

    /**
     * A book that does not come back: the loan ends and the copy is lost, to replace it. What it says can be undone with found().
     *
     * @param \stdClass $library
     * @param int $loanid
     * @param int $staffid
     * @param int $now
     * @return bool Whether it was a loan of this bank still open.
     */
    public static function lost(\stdClass $library, int $loanid, int $staffid, int $now): bool {
        global $DB;
        $loan = $DB->get_record(self::T . 'loan', ['id' => $loanid, 'libraryid' => $library->id, 'timereturned' => null]);
        if (!$loan) {
            return false;
        }
        $DB->update_record(self::T . 'loan', (object) ['id' => $loan->id, 'timereturned' => $now, 'returnstaffid' => $staffid,
            'conditionin' => self::LOST, 'timemodified' => $now]);
        $DB->set_field(self::T . 'item', 'status', 'lost', ['id' => $loan->itemid]);
        return true;
    }

    /**
     * A book given up for lost turns up: it goes back on the shelf.
     *
     * @param \stdClass $library
     * @param int $loanid
     * @param int $now
     * @return bool Whether it was a lost book of this bank.
     */
    public static function found(\stdClass $library, int $loanid, int $now): bool {
        global $DB;
        $loan = $DB->get_record(self::T . 'loan', ['id' => $loanid, 'libraryid' => $library->id, 'conditionin' => self::LOST]);
        if (!$loan) {
            return false;
        }
        $DB->update_record(self::T . 'loan', (object) ['id' => $loan->id, 'conditionin' => null, 'timemodified' => $now]);
        $DB->set_field(self::T . 'item', 'status', 'available', ['id' => $loan->itemid, 'status' => 'lost']);
        return true;
    }

    /**
     * What was lost this year, and by whom: the bank charges nothing, it says what is missing.
     *
     * @param \stdClass $library
     * @param int $now
     * @return array[] loanid, title, barcode, name, group, when.
     */
    public static function losses(\stdClass $library, int $now): array {
        global $DB;
        $year = years::around((int) $library->entityid, $now);
        $rows = $DB->get_records_sql(
            "SELECT l.id, l.timereturned, l.groupname, i.barcode, rec.title, r.firstname, r.lastname
               FROM {" . self::T . "loan} l
               JOIN {" . self::T . "item} i ON i.id = l.itemid
               JOIN {" . self::T . "record} rec ON rec.id = i.recordid
               JOIN {" . self::T . "reader} r ON r.id = l.readerid
              WHERE l.libraryid = :libraryid AND l.conditionin = :lost AND l.timereturned >= :start
           ORDER BY l.groupname, r.lastname, rec.title",
            ['libraryid' => $library->id, 'lost' => self::LOST, 'start' => (int) $year->timestart]
        );
        return array_values(array_map(fn($r) => [
            'loanid' => (int) $r->id,
            'title' => $r->title,
            'barcode' => $r->barcode,
            'name' => trim($r->firstname . ' ' . $r->lastname),
            'group' => (string) $r->groupname,
            'when' => (int) $r->timereturned,
        ], $rows));
    }

    /**
     * Tells each reader who still has books which ones and by when. Who has no account is on the list of their class.
     *
     * @param \stdClass $library
     * @param int $now
     * @return int Messages sent.
     */
    public static function remind_readers(\stdClass $library, int $now): int {
        global $DB;
        $deadline = (int) self::state($library)['deadline'];
        $url = (new \moodle_url('/local/oksigeniaayvurenda/index.php', ['library' => $library->slug]))->out(false);
        $name = format_string($library->name, true, ['escape' => false]);
        $sent = 0;
        foreach (self::holders($library) as $reader) {
            $user = $reader->userid ? \core_user::get_user((int) $reader->userid) : null;
            if (!$user || $user->deleted || $user->suspended) {
                continue;
            }
            $language = force_current_language((string) $user->lang);
            $str = fn(string $key, $a = null) => get_string($key, self::COMPONENT, $a);
            $loans = $DB->get_records_sql(
                "SELECT l.id, rec.id AS recordid, rec.title, i.barcode
                   FROM {" . self::T . "loan} l
                   JOIN {" . self::T . "item} i ON i.id = l.itemid
                   JOIN {" . self::T . "record} rec ON rec.id = i.recordid
                  WHERE l.libraryid = :libraryid AND l.readerid = :readerid AND l.timereturned IS NULL
               ORDER BY rec.title",
                ['libraryid' => $library->id, 'readerid' => $reader->id]
            );
            $when = $deadline ? userdate(
                $deadline,
                get_string('strftimedateshort', 'langconfig'),
                \core_date::get_user_timezone($user)
            ) : '';
            $lead = $when ? $str('bankreturnlead', $when) : $str('bankreturnleadnodate');
            $rows = [];
            $lines = [];
            foreach ($loans as $loan) {
                $rows[] = ['line' => $loan->barcode] + mail::book((int) $loan->recordid, $loan->title);
                $lines[] = '- ' . $loan->title . ' (' . $loan->barcode . ')';
            }
            $subject = $str('bankreturnsubject');
            $hello = get_string('mailhello', self::COMPONENT, $user->firstname);
            if (
                notices::send(
                    'bankreturn',
                    $user,
                    $subject,
                    $hello . "\n\n" . $lead . "\n" . implode("\n", $lines) . "\n\n" . $name,
                    $url,
                    $name,
                    $name,
                    [
                    'library' => $library,
                    'hello' => $hello,
                    'heading' => $subject,
                    'lead' => [$lead],
                    'books' => $rows,
                    'after' => [$str('bankreturnafter')],
                    'why' => $str('mailwhyreader'),
                    'prefs' => true,
                    ]
                )
            ) {
                $sent++;
            }
            force_current_language($language);
        }
        self::save_state($library, ['readerssent' => $sent]);
        return $sent;
    }

    /**
     * Tells the tutor of each class who still has books, and how many: a reminder in class helps, and not every pupil reads their
     * e-mail. Without titles, as with late books.
     *
     * @param \stdClass $library
     * @param int $now
     * @return int Messages sent.
     */
    public static function remind_tutors(\stdClass $library, int $now): int {
        global $DB;
        $year = years::around((int) $library->entityid, $now);
        $deadline = (int) self::state($library)['deadline'];
        $main = mail::main_library((int) $library->entityid) ?? $library;
        $sent = 0;
        foreach (bank::classes($library, $now) as $class) {
            if (!$class['out']) {
                continue;
            }
            $tutors = $DB->get_records(self::T . 'tutor', ['yearid' => $year->id, 'groupname' => $class['group']]);
            $held = array_values(array_filter(bank::roll($library, $class['group'], $now), fn($p) => $p['held']));
            foreach ($tutors as $tutor) {
                $user = \core_user::get_user((int) $tutor->userid);
                if (!$user || $user->deleted || $user->suspended) {
                    continue;
                }
                $language = force_current_language((string) $user->lang);
                $str = fn(string $key, $a = null) => get_string($key, self::COMPONENT, $a);
                $people = array_map(fn($p) => ['name' => $p['name'], 'line' => $str(
                    count($p['held']) === 1 ? 'bankreturnrowone' : 'bankreturnrow',
                    count($p['held'])
                )], $held);
                $when = $deadline ? userdate(
                    $deadline,
                    get_string('strftimedateshort', 'langconfig'),
                    \core_date::get_user_timezone($user)
                ) : '';
                $lead = $when ? $str('bankreturntutorlead', $when) : $str('bankreturntutorleadnodate');
                $subject = $str('bankreturntutorsubject', $class['group']);
                $url = (new \moodle_url('/local/oksigeniaayvurenda/class.php', ['library' => $main->slug,
                    'group' => $class['group']]))->out(false);
                $lines = array_map(fn($p) => '- ' . $p['name'] . ': ' . $p['line'], $people);
                $name = format_string($library->name, true, ['escape' => false]);
                if (
                    notices::send(
                        'bankreturn',
                        $user,
                        $subject,
                        get_string('mailhello', self::COMPONENT, $user->firstname) . "\n\n" . $lead . "\n" . implode("\n", $lines),
                        $url,
                        $class['group'],
                        $name,
                        [
                        'library' => $library,
                        'hello' => get_string('mailhello', self::COMPONENT, $user->firstname),
                        'heading' => $subject,
                        'lead' => [$lead],
                        'people' => $people,
                        'after' => [$str('bankreturntutorafter')],
                        'why' => $str('mailwhytutor'),
                        'prefs' => true,
                        ]
                    )
                ) {
                    $sent++;
                }
                force_current_language($language);
            }
        }
        self::save_state($library, ['tutorssent' => $sent]);
        return $sent;
    }

    /**
     * Queues a reminder: to many people, and mail takes time.
     *
     * @param \stdClass $library
     * @param string $who readers or tutors.
     * @param int $now
     * @return bool False if it already went in the last day.
     */
    public static function queue_reminder(\stdClass $library, string $who, int $now): bool {
        if (!in_array($who, ['readers', 'tutors'], true) || (int) self::state($library)[$who] > $now - DAYSECS) {
            return false;
        }
        $task = new \local_oksigeniaayvurenda\task\bank_reminder();
        $task->set_custom_data(['library' => (int) $library->id, 'who' => $who]);
        \core\task\manager::queue_adhoc_task($task);
        self::save_state($library, [$who => $now, $who . 'sent' => 0]);
        return true;
    }
}
