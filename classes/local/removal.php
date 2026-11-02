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
 * A centre taken away whole, with everything that hangs from it: when it leaves the site, or an example before opening.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class removal {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var string[] The areas of files, each with the table its item id belongs to. */
    private const FILES = ['digital' => 'digital', 'cover' => 'record', 'reviewdrawing' => 'review', 'reviewvoice' => 'review',
        'libraryimage' => 'library', 'portrait' => 'entity', 'copies' => 'entity', 'filecopies' => 'entity',
        'recovermap' => 'entity'];

    /**
     * How much hangs from a centre, table by table.
     *
     * @param int $entityid
     * @return int[] Table => rows.
     */
    public static function count(int $entityid): array {
        return array_map('count', self::rows($entityid));
    }

    /**
     * The accounts that have nothing on the site but being readers or staff of a centre: when the centre goes, nobody needs
     * them.
     *
     * @param int $entityid
     * @return int[]
     */
    public static function lonely_accounts(int $entityid): array {
        global $DB, $CFG;
        $keep = array_merge(array_map('intval', explode(',', (string) $CFG->siteadmins)), [(int) $CFG->siteguest]);
        // Its staff have no reader's card: they are there by their roles in the centre's category, and they go too.
        $categoryid = (int) $DB->get_field(self::T . 'entity', 'categoryid', ['id' => $entityid]);
        $context = $categoryid ? \context_coursecat::instance($categoryid, IGNORE_MISSING) : null;
        $here = $context ? (int) $context->id : 0;
        $candidates = $DB->get_fieldset_select(self::T . 'reader', 'userid', 'entityid = ? AND userid IS NOT NULL', [$entityid]);
        if ($here) {
            $staff = $DB->get_fieldset_select('role_assignments', 'userid', 'contextid = ?', [$here]);
            $candidates = array_merge($candidates, $staff);
        }
        $lonely = [];
        foreach (array_unique(array_map('intval', $candidates)) as $userid) {
            if (
                !in_array($userid, $keep, true)
                && !$DB->record_exists_select(self::T . 'reader', 'userid = ? AND entityid <> ?', [$userid, $entityid])
                && !$DB->record_exists_select('role_assignments', 'userid = ? AND contextid <> ?', [$userid, $here])
                && !$DB->record_exists('user_enrolments', ['userid' => $userid])
            ) {
                $lonely[] = $userid;
            }
        }
        return $lonely;
    }

    /**
     * Takes a centre away. There is no way back but its copy.
     *
     * @param int $entityid
     * @return int[] Table => rows taken away.
     */
    public static function remove(int $entityid): array {
        global $DB;
        $rows = self::rows($entityid);
        $counts = array_map('count', $rows);
        if (!practice::is($entityid)) {
            \local_oksigeniaayvurenda\event\centre_removed::fire($entityid, null, ['readers' => $counts['reader'] ?? 0,
                'items' => $counts['item'] ?? 0]);
        }
        $categoryid = (int) $DB->get_field(self::T . 'entity', 'categoryid', ['id' => $entityid]);
        $context = \context_system::instance();
        $storage = get_file_storage();
        foreach (self::FILES as $area => $table) {
            foreach ($rows[$table] ?? [] as $id) {
                $storage->delete_area_files($context->id, 'local_oksigeniaayvurenda', $area, $id);
            }
        }
        $transaction = $DB->start_delegated_transaction();
        // The children first, then what they hung from.
        foreach (array_reverse(array_keys($rows)) as $table) {
            foreach (array_chunk($rows[$table], 500) as $ids) {
                [$in, $params] = $DB->get_in_or_equal($ids);
                $DB->delete_records_select(self::T . $table, 'id ' . $in, $params);
            }
        }
        $transaction->allow_commit();
        foreach (array_keys(self::settings_of($entityid)) as $name) {
            unset_config($name, 'local_oksigeniaayvurenda');
        }
        if ($categoryid && \core_course_category::get($categoryid, IGNORE_MISSING, true)) {
            \core_course_category::get($categoryid, MUST_EXIST, true)->delete_full(false);
        }
        \cache::make('local_oksigeniaayvurenda', 'access')->purge();
        return $counts;
    }

    /**
     * The keys of the centre kept in the site's settings: its connector's and those of its copies.
     *
     * @param int $entityid
     * @return array Name => value.
     */
    private static function settings_of(int $entityid): array {
        $all = (array) get_config('local_oksigeniaayvurenda');
        return array_filter(
            $all,
            fn($name) => preg_match('/^(cifrakey|cifrasecret)' . $entityid . '$|^offsite' . $entityid . '_/', (string) $name) === 1,
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * The ids that hang from a centre, following the plugin's own description of its tables, parents before children.
     *
     * @param int $entityid
     * @return array[] Table => ids.
     */
    private static function rows(int $entityid): array {
        global $CFG, $DB;
        $file = new \xmldb_file($CFG->dirroot . '/local/oksigeniaayvurenda/db/install.xml');
        $file->loadXMLStructure();
        $children = [];
        foreach ($file->getStructure()->getTables() as $table) {
            $name = str_replace(self::T, '', $table->getName());
            foreach ($table->getKeys() as $key) {
                if (in_array($key->getType(), [XMLDB_KEY_FOREIGN, XMLDB_KEY_FOREIGN_UNIQUE], true)) {
                    $parent = str_replace(self::T, '', $key->getRefTable());
                    $children[$parent][] = [$name, $key->getFields()[0]];
                }
            }
        }
        $rows = ['entity' => [$entityid]];
        $queue = ['entity'];
        while ($queue) {
            $parent = array_shift($queue);
            foreach ($children[$parent] ?? [] as [$table, $field]) {
                $ids = [];
                foreach (array_chunk($rows[$parent], 500) as $chunk) {
                    [$in, $params] = $DB->get_in_or_equal($chunk);
                    $found = $DB->get_fieldset_select(self::T . $table, 'id', $field . ' ' . $in, $params);
                    $ids = array_merge($ids, array_map('intval', $found));
                }
                $new = array_values(array_diff($ids, $rows[$table] ?? []));
                if ($new) {
                    // Moved to the end: a table found again from a later parent has to go before that parent is deleted.
                    $known = $rows[$table] ?? [];
                    unset($rows[$table]);
                    $rows[$table] = array_merge($known, $new);
                    $queue[] = $table;
                }
            }
        }
        // A year's rows that reach through what has gone are already in; a table with no key to these has nothing of theirs.
        return $rows;
    }
}
