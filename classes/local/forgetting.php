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
 * What a child read at seven is not tied to their name at seventeen (priv-menores 4): the loans given back more than
 * the centre's school years ago (two, unless it says otherwise) stop saying who took them. Each reader's loans of such a
 * year go to a stand-in of their own for that year, with no name nor account, in the same group and stage: the figures
 * of that year (how many came, by group and by stage) stay as they were, and nobody can join two years of one person.
 * What the reader won that year (their flower, the corners of their album) is kept with them, without the books; the
 * notes at the desk go, and so do the reservations, sanctions, accessible formats asked and reading places of then.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class forgetting {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var int School years a loan keeps its reader, unless the centre says otherwise. */
    public const KEEP = 2;

    /** @var int[] What a centre may choose: school years, or 0 to keep them always. */
    public const CHOICES = [1, 2, 3, 5, 0];

    /** @var int Readers' years undone in one night, so the night stays short; the next goes on. */
    public const BATCH = 3000;

    /**
     * How many school years a centre keeps a loan with its reader.
     *
     * @param int $entityid
     * @return int 0 for always.
     */
    public static function years(int $entityid): int {
        global $DB;
        $settings = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        $years = $settings['historyyears'] ?? self::KEEP;
        return in_array((int) $years, self::CHOICES, true) ? (int) $years : self::KEEP;
    }

    /**
     * Where the history of a centre that still says who starts: the beginning of the oldest school year kept.
     *
     * @param int $entityid
     * @param int $now
     * @return int|null Null when the centre keeps it always.
     */
    public static function since(int $entityid, int $now): ?int {
        $keep = self::years($entityid);
        if (!$keep) {
            return null;
        }
        $start = (int) years::around($entityid, $now)->timestart;
        for ($i = 1; $i < $keep; $i++) {
            $start = (int) years::around($entityid, $start - 1)->timestart;
        }
        return $start;
    }

    /**
     * The night's turn, centre after centre, until the batch is done.
     *
     * @param int|null $now
     * @return int Readers' years undone.
     */
    public static function run(?int $now = null): int {
        global $DB;
        $now = $now ?? time();
        $done = 0;
        foreach ($DB->get_fieldset_select(self::T . 'entity', 'id', '1 = 1') as $entityid) {
            if ($done >= self::BATCH) {
                break;
            }
            try {
                $done += self::entity((int) $entityid, $now, self::BATCH - $done);
            } catch (\Throwable $e) {
                // A centre that fails tonight does not stop the others.
                mtrace('Ayvurenda: forgetting the history of centre ' . $entityid . ' failed: ' . $e->getMessage());
            }
        }
        return $done;
    }

    /**
     * Undoes the old history of a centre.
     *
     * @param int $entityid
     * @param int $now
     * @param int $limit Readers' years at most.
     * @return int Readers' years undone.
     */
    public static function entity(int $entityid, int $now, int $limit = self::BATCH): int {
        global $DB;
        $since = self::since($entityid, $now);
        if ($since === null) {
            return 0;
        }
        $rows = $DB->get_recordset_sql(
            "SELECT l.readerid, l.yearid, COUNT(1) AS loans
               FROM {" . self::T . "loan} l
               JOIN {" . self::T . "library} b ON b.id = l.libraryid
               JOIN {" . self::T . "reader} r ON r.id = l.readerid
              WHERE b.entityid = :entityid AND l.timereturned IS NOT NULL AND l.timeloaned < :since
                    AND l.yearid IS NOT NULL AND r.status <> 'deleted'
           GROUP BY l.readerid, l.yearid
           ORDER BY l.yearid, l.readerid",
            ['entityid' => $entityid, 'since' => $since]
        );
        $pairs = [];
        foreach ($rows as $row) {
            $pairs[] = [(int) $row->readerid, (int) $row->yearid];
            if (count($pairs) >= $limit) {
                break;
            }
        }
        $rows->close();
        foreach ($pairs as [$readerid, $yearid]) {
            self::reader_year($entityid, $readerid, $yearid, $since, $now);
        }
        // What was asked or owed back then goes with it: reservations and sanctions closed, formats answered, places read.
        $old = ['since' => $since, 'entityid' => $entityid];
        $DB->delete_records_select(
            self::T . 'hold',
            "status IN ('done', 'cancelled', 'expired') AND timecreated < :since
             AND libraryid IN (SELECT id FROM {" . self::T . "library} WHERE entityid = :entityid)",
            $old
        );
        $DB->delete_records_select(
            self::T . 'sanction',
            "status <> 'open' AND timecreated < :since AND entityid = :entityid",
            $old
        );
        $DB->delete_records_select(
            self::T . 'formatask',
            "status <> 'waiting' AND timecreated < :since AND entityid = :entityid",
            $old
        );
        $DB->delete_records_select(
            self::T . 'digitalread',
            "timemodified < :since AND digitalid IN (SELECT id FROM {" . self::T . "digital} WHERE entityid = :entityid)",
            $old
        );
        return count($pairs);
    }

    /**
     * One reader's loans of one school year, to a stand-in of their own for that year; what they won, kept with them.
     *
     * @param int $entityid
     * @param int $readerid
     * @param int $yearid
     * @param int $since
     * @param int $now
     */
    private static function reader_year(int $entityid, int $readerid, int $yearid, int $since, int $now): void {
        global $DB;
        $reader = $DB->get_record(self::T . 'reader', ['id' => $readerid]);
        $year = $DB->get_record(self::T . 'year', ['id' => $yearid]);
        if (!$reader || !$year) {
            return;
        }
        $transaction = $DB->start_delegated_transaction();
        if (!$DB->record_exists(self::T . 'keepsake', ['readerid' => $readerid, 'yearid' => $yearid])) {
            $DB->insert_record(self::T . 'keepsake', [
                'readerid' => $readerid,
                'yearid' => $yearid,
                'flower' => json_encode(garden::flower($reader, $year)),
                'corners' => json_encode((object) garden::corners_won($reader, $yearid)),
                'timecreated' => $now,
            ]);
        }
        $standin = $DB->insert_record(self::T . 'reader', [
            'entityid' => $entityid,
            'userid' => null,
            'cardcode' => 'past-' . random_string(24),
            'readertype' => $reader->readertype,
            'status' => 'deleted',
            'accessneeds' => 0,
            'band' => $reader->band ?? null,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $member = $DB->get_record(
            self::T . 'member',
            ['readerid' => $readerid, 'yearid' => $yearid],
            'groupname, stage',
            IGNORE_MULTIPLE
        );
        if ($member) {
            $DB->insert_record(self::T . 'member', ['readerid' => $standin, 'yearid' => $yearid,
                'groupname' => $member->groupname, 'stage' => $member->stage, 'source' => 'past', 'timecreated' => $now]);
        }
        $DB->execute(
            "UPDATE {" . self::T . "loan}
                SET readerid = :standin, returnnote = NULL
              WHERE readerid = :readerid AND yearid = :yearid AND timereturned IS NOT NULL AND timeloaned < :since",
            ['standin' => $standin, 'readerid' => $readerid, 'yearid' => $yearid, 'since' => $since]
        );
        $transaction->allow_commit();
    }
}
