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
 * The library's challenges with their own dates (the summer, the book week): what a public library measures most, and
 * what a school has nothing of between June and September. The library makes one with a name, its dates, for whom (all
 * the stages or some) and how many books; each reader joins from their card. Only what goes through the desk counts: each
 * loan of the centre between the two dates is a stamp, textbooks of the bank left out. No ranking: what is shown of
 * everyone is a sum.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class quests {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var int The most books a challenge may ask for. */
    public const MAXGOAL = 50;

    /** @var int Days a challenge that ended still shows on the card of whoever played it. */
    public const AFTER = 14;

    /**
     * The challenges of a library, the latest first.
     *
     * @param \stdClass $library
     * @return \stdClass[]
     */
    public static function of(\stdClass $library): array {
        global $DB;
        return array_values($DB->get_records(self::T . 'quest', ['libraryid' => $library->id], 'timestart DESC, id DESC'));
    }

    /**
     * Makes or changes a challenge of a library.
     *
     * @param \stdClass $library
     * @param array $data name, text, start and end (Y-m-d, in the centre's zone), goal and stages (names; none for all).
     * @param int $now
     * @param int $id The challenge to change; 0 makes one.
     * @return int|string Its id, or the key of what is wrong (questerrname, questerrdates, questerrgoal).
     */
    public static function save(\stdClass $library, array $data, int $now, int $id = 0) {
        global $DB;
        $name = \core_text::substr(trim((string) ($data['name'] ?? '')), 0, 120);
        if ($name === '') {
            return 'questerrname';
        }
        $zone = zones::of($library);
        $day = fn(string $date, string $time) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
            ? (new \DateTimeImmutable($date . ' ' . $time, $zone))->getTimestamp() : 0;
        $start = $day((string) ($data['start'] ?? ''), '00:00:00');
        $end = $day((string) ($data['end'] ?? ''), '23:59:59');
        if (!$start || !$end || $end <= $start) {
            return 'questerrdates';
        }
        $goal = (int) ($data['goal'] ?? 0);
        if ($goal < 1 || $goal > self::MAXGOAL) {
            return 'questerrgoal';
        }
        $known = self::stages($library, $now);
        $stages = array_values(array_intersect(array_map('strval', (array) ($data['stages'] ?? [])), $known));
        $row = ['libraryid' => $library->id, 'name' => $name,
            'text' => \core_text::substr(trim((string) ($data['text'] ?? '')), 0, 255) ?: null,
            'timestart' => $start, 'timeend' => $end, 'goal' => $goal,
            'stages' => $stages && count($stages) < count($known) ? json_encode($stages) : null, 'timemodified' => $now];
        if ($id && $DB->record_exists(self::T . 'quest', ['id' => $id, 'libraryid' => $library->id])) {
            $DB->update_record(self::T . 'quest', (object) (['id' => $id] + $row));
            return $id;
        }
        return (int) $DB->insert_record(self::T . 'quest', (object) ($row + ['timecreated' => $now]));
    }

    /**
     * Takes a challenge away, and who had joined it.
     *
     * @param \stdClass $library
     * @param int $id
     */
    public static function remove(\stdClass $library, int $id): void {
        global $DB;
        if ($DB->record_exists(self::T . 'quest', ['id' => $id, 'libraryid' => $library->id])) {
            $DB->delete_records(self::T . 'questreader', ['questid' => $id]);
            $DB->delete_records(self::T . 'quest', ['id' => $id]);
        }
    }

    /**
     * The stages of the centre's groups this school year, to choose for whom a challenge is.
     *
     * @param \stdClass $library
     * @param int $now
     * @return string[]
     */
    public static function stages(\stdClass $library, int $now): array {
        global $DB;
        $year = years::around((int) $library->entityid, $now);
        $stages = $DB->get_fieldset_sql(
            "SELECT DISTINCT m.stage
               FROM {" . self::T . "member} m
               JOIN {" . self::T . "reader} r ON r.id = m.readerid
              WHERE r.entityid = :entityid AND m.yearid = :yearid AND m.stage IS NOT NULL AND m.stage <> ''",
            ['entityid' => $library->entityid, 'yearid' => $year->id]
        );
        return \local_oksigeniaayvurenda\core\group_order::stages(array_map('strval', $stages));
    }

    /**
     * The challenges a reader can play now: running, of their centre and for their stage; and those they played that
     * ended a little while ago, to see how they went.
     *
     * @param \stdClass $reader
     * @param int $now
     * @return \stdClass[]
     */
    public static function open(\stdClass $reader, int $now): array {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT q.*, qr.id AS joinedid, qr.timedone
               FROM {" . self::T . "quest} q
               JOIN {" . self::T . "library} b ON b.id = q.libraryid
          LEFT JOIN {" . self::T . "questreader} qr ON qr.questid = q.id AND qr.readerid = :readerid
              WHERE b.entityid = :entityid AND q.timestart <= :now1
                    AND (q.timeend >= :now2 OR (qr.id IS NOT NULL AND q.timeend >= :since))
           ORDER BY q.timeend, q.id",
            ['readerid' => $reader->id, 'entityid' => $reader->entityid, 'now1' => $now, 'now2' => $now,
                'since' => $now - self::AFTER * DAYSECS]
        );
        $stage = self::stage_of($reader, $now);
        return array_values(array_filter($rows, function ($quest) use ($stage) {
            $stages = json_decode((string) $quest->stages, true);
            return !$stages || !empty($quest->joinedid) || in_array($stage, $stages, true);
        }));
    }

    /**
     * The stage of a reader's group this school year.
     *
     * @param \stdClass $reader
     * @param int $now
     * @return string '' if they have none.
     */
    public static function stage_of(\stdClass $reader, int $now): string {
        global $DB;
        $year = years::around((int) $reader->entityid, $now);
        return (string) $DB->get_field_select(
            self::T . 'member',
            'stage',
            'readerid = :readerid AND yearid = :yearid',
            ['readerid' => $reader->id, 'yearid' => $year->id],
            IGNORE_MULTIPLE
        );
    }

    /**
     * A reader joins a challenge that is running.
     *
     * @param int $questid
     * @param \stdClass $reader
     * @param int $now
     * @return bool Whether they are in it.
     */
    public static function join(int $questid, \stdClass $reader, int $now): bool {
        global $DB;
        foreach (self::open($reader, $now) as $quest) {
            if ((int) $quest->id !== $questid) {
                continue;
            }
            if (!empty($quest->joinedid)) {
                return true;
            }
            if ((int) $quest->timeend < $now) {
                return false;
            }
            $DB->insert_record(self::T . 'questreader', ['questid' => $questid, 'readerid' => $reader->id,
                'timejoined' => $now]);
            return true;
        }
        return false;
    }

    /**
     * The books a reader took home in a challenge's days: their stamps.
     *
     * @param \stdClass $quest
     * @param int $readerid
     * @param int $now
     * @return int
     */
    public static function stamps(\stdClass $quest, int $readerid, int $now): int {
        global $DB;
        [$sql, $params] = self::loans($quest, $now);
        return (int) $DB->count_records_sql(
            "SELECT COUNT(1) $sql AND l.readerid = :qreader",
            $params + ['qreader' => $readerid]
        );
    }

    /**
     * How a challenge goes, all added up: who joined, how many reached the goal, and the books of all of them.
     *
     * @param \stdClass $quest
     * @param int $now
     * @return array readers, done and books.
     */
    public static function totals(\stdClass $quest, int $now): array {
        global $DB;
        [$sql, $params] = self::loans($quest, $now);
        $byreader = $DB->get_records_sql_menu(
            "SELECT l.readerid, COUNT(1) $sql
                AND l.readerid IN (SELECT qr.readerid FROM {" . self::T . "questreader} qr WHERE qr.questid = :tquest)
           GROUP BY l.readerid",
            $params + ['tquest' => $quest->id]
        );
        return ['readers' => $DB->count_records(self::T . 'questreader', ['questid' => $quest->id]),
            'done' => count(array_filter($byreader, fn($n) => (int) $n >= (int) $quest->goal)),
            'books' => (int) array_sum($byreader)];
    }

    /**
     * Notes when a reader reached the goal, the first time it is seen.
     *
     * @param \stdClass $quest With joinedid and timedone, as open() gives it.
     * @param int $stamps
     * @param int $now
     */
    public static function note_done(\stdClass $quest, int $stamps, int $now): void {
        global $DB;
        if (!empty($quest->joinedid) && empty($quest->timedone) && $stamps >= (int) $quest->goal) {
            $DB->set_field(self::T . 'questreader', 'timedone', $now, ['id' => $quest->joinedid]);
            $quest->timedone = $now;
        }
    }

    /**
     * The loans that count for a challenge: of its centre, not of a textbook bank, between its two dates (and no later
     * than now).
     *
     * @param \stdClass $quest
     * @param int $now
     * @return array The FROM and WHERE of the SQL, and its parameters.
     */
    private static function loans(\stdClass $quest, int $now): array {
        return ["FROM {" . self::T . "loan} l
                WHERE l.libraryid IN (SELECT qb.id FROM {" . self::T . "library} qb
                                       WHERE qb.entityid = (SELECT qe.entityid FROM {" . self::T . "library} qe
                                                             WHERE qe.id = :qlibrary))
                  AND " . bank::reading('l.libraryid') . "
                  AND l.timeloaned >= :qstart AND l.timeloaned <= :qend",
            ['qlibrary' => $quest->libraryid, 'qstart' => $quest->timestart, 'qend' => min((int) $quest->timeend, $now)]];
    }
}
