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
 * A centre back from its daily copy, on this site or any other with Ayvurenda: always as a new centre, so nothing there is
 * touched. What the copy has comes back with its ids made new and everything that pointed at them pointing at the new ones.
 * What it does not have stays out: the accounts of the site, the covers, the files of the digital collection and the voices.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class recover {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var string[] What comes back, parents first; a tutor is an account, and a digital file is not in the copy. */
    private const TABLES = ['library', 'year', 'authority', 'record', 'recauth', 'item', 'reader', 'member', 'loan', 'hold',
        'stocktake', 'lot', 'lotitem', 'withdrawal', 'withdrawn', 'stockread', 'rule', 'source'];

    /** @var string[] Fields that point at their own table, set in a second pass. */
    private const SELF = ['library' => 'parentid', 'authority' => 'parentid'];

    /** @var string[] What a centre's settings keep of the place they came from: none of it holds here. */
    private const LEFT_BEHIND = ['cohorts', 'courses', 'cifra', 'offsite', 'plan', 'closed', 'demo', 'ltiregister'];

    /** @var int The largest piece of a copy that is read, a guard against a file that is not one. */
    private const LIMIT = 1024 * 1048576;

    /**
     * Whether a file is a copy, and what it holds.
     *
     * @param string $path A zip.
     * @return array|null name, libraries, records, items, readers, loans; null if it is not a copy.
     */
    public static function look(string $path): ?array {
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::RDONLY) !== true) {
            return null;
        }
        $entity = self::rows($zip, 'entity');
        if (!$entity || $zip->locateName('library.csv') === false) {
            $zip->close();
            return null;
        }
        $look = ['name' => (string) ($entity[0]['name'] ?? ''), 'libraries' => count(self::rows($zip, 'library')),
            'records' => count(self::rows($zip, 'record')), 'items' => count(self::rows($zip, 'item')),
            'readers' => count(self::rows($zip, 'reader')),
            'loans' => count(array_filter(self::rows($zip, 'loan'), fn($l) => (string) ($l['timereturned'] ?? '') === ''))];
        $zip->close();
        return $look;
    }

    /**
     * Brings a copy back as a new centre.
     *
     * @param string $path A zip.
     * @param int $userid Who does it: what was done by an account of the other site, and has to say by whom, says so of them.
     * @param int|null $now
     * @return \stdClass Its first library.
     */
    public static function restore(string $path, int $userid, ?int $now = null): \stdClass {
        global $DB;
        $now = $now ?? time();
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::RDONLY) !== true) {
            throw new \moodle_exception('recovernotcopy', 'local_oksigeniaayvurenda');
        }
        $source = self::rows($zip, 'entity')[0] ?? null;
        if (!$source) {
            $zip->close();
            throw new \moodle_exception('recovernotcopy', 'local_oksigeniaayvurenda');
        }
        $keys = self::foreign_keys();
        $settings = (array) json_decode((string) ($source['settings'] ?? ''), true);
        foreach (self::LEFT_BEHIND as $key) {
            unset($settings[$key]);
        }
        $names = self::names($zip);
        $transaction = $DB->start_delegated_transaction();
        $entityid = (int) $DB->insert_record(self::T . 'entity', ['name' => (string) $source['name'],
            'yearstart' => (string) ($source['yearstart'] ?? '09-01'),
            'settings' => json_encode($settings + ['plan' => plans::defaults()]), 'timecreated' => $now, 'timemodified' => $now]);
        $map = ['entity' => [(int) $source['id'] => $entityid]];
        foreach (self::TABLES as $table) {
            $map[$table] = [];
            $columns = $DB->get_columns(self::T . $table);
            $later = [];
            foreach (self::rows($zip, $table) as $row) {
                $old = (int) ($row['id'] ?? 0);
                $new = self::row($table, $row, $columns, $keys[$table] ?? [], $map, $userid);
                if ($new === null) {
                    continue;
                }
                if ($table === 'library') {
                    $new->slug = libraries::slug((string) $row['slug']);
                }
                if ($table === 'reader' && !empty($row['userid']) && isset($names[(string) $row['cardcode']])) {
                    // An account does not cross over; its name and address, which the copy has apart, do.
                    [$new->firstname, $new->lastname, $new->email] = $names[(string) $row['cardcode']];
                }
                if ($table === 'source') {
                    $target = (string) $row['tablename'];
                    if (empty($map[$target][(int) $row['localid']])) {
                        continue;
                    }
                    $new->localid = $map[$target][(int) $row['localid']];
                }
                $map[$table][$old] = (int) $DB->insert_record(self::T . $table, $new);
                if (isset(self::SELF[$table]) && (int) ($row[self::SELF[$table]] ?? 0)) {
                    $later[$map[$table][$old]] = (int) $row[self::SELF[$table]];
                }
            }
            foreach ($later as $id => $parent) {
                $DB->set_field(self::T . $table, self::SELF[$table], $map[$table][$parent] ?? null, ['id' => $id]);
            }
        }
        $zip->close();
        $transaction->allow_commit();
        // On a site shared by several centres, it gets its category too.
        if (get_config('local_oksigeniaayvurenda', 'entitycategories')) {
            access::category($entityid);
        }
        $first = $DB->get_records(self::T . 'library', ['entityid' => $entityid], 'id', '*', 0, 1);
        return reset($first);
    }

    /**
     * A row ready for this site: its ids turned to the new ones, what pointed at an account of the other site cleared.
     *
     * @param string $table
     * @param array $row
     * @param array $columns
     * @param string[] $keys Field => the table it points at.
     * @param array $map Table => old id => new id.
     * @param int $userid
     * @return \stdClass|null Null when what it hangs from did not come.
     */
    private static function row(string $table, array $row, array $columns, array $keys, array $map, int $userid): ?\stdClass {
        $new = new \stdClass();
        foreach ($columns as $name => $column) {
            if ($name === 'id' || !array_key_exists($name, $row)) {
                continue;
            }
            $value = $row[$name];
            if ($value === '' && !$column->not_null) {
                $value = null;
            } else if ($value === '' && $column->meta_type === 'I') {
                $value = 0;
            }
            if (isset($keys[$name]) && $name !== (self::SELF[$table] ?? '')) {
                $target = $keys[$name];
                if ($target === 'user') {
                    $value = $column->not_null ? $userid : null;
                } else if ($value !== null) {
                    $value = $map[$target][(int) $value] ?? null;
                    if ($value === null && $column->not_null) {
                        return null;
                    }
                }
            }
            $new->$name = $value;
        }
        if (isset(self::SELF[$table])) {
            $new->{self::SELF[$table]} = null;
        }
        return $new;
    }

    /**
     * What each field of the plugin's tables points at, from the plugin's own description of them.
     *
     * @return array[] Table => field => the table it points at.
     */
    private static function foreign_keys(): array {
        global $CFG;
        $file = new \xmldb_file($CFG->dirroot . '/local/oksigeniaayvurenda/db/install.xml');
        $file->loadXMLStructure();
        $keys = [];
        foreach ($file->getStructure()->getTables() as $table) {
            $name = str_replace(self::T, '', $table->getName());
            foreach ($table->getKeys() as $key) {
                if (in_array($key->getType(), [XMLDB_KEY_FOREIGN, XMLDB_KEY_FOREIGN_UNIQUE], true)) {
                    $keys[$name][$key->getFields()[0]] = str_replace(self::T, '', $key->getRefTable());
                }
            }
        }
        return $keys;
    }

    /**
     * The rows of a table of the copy.
     *
     * @param \ZipArchive $zip
     * @param string $table
     * @return array[] Column => value.
     */
    private static function rows(\ZipArchive $zip, string $table): array {
        return self::csv($zip, $table . '.csv');
    }

    /**
     * The rows of a list in the copy.
     *
     * @param \ZipArchive $zip
     * @param string $name
     * @return array[] Column => value.
     */
    private static function csv(\ZipArchive $zip, string $name): array {
        $stat = $zip->statName($name);
        if (!$stat || $stat['size'] > self::LIMIT) {
            return [];
        }
        $stream = $zip->getStream($name);
        if (!$stream) {
            return [];
        }
        $temp = fopen('php://temp', 'w+');
        stream_copy_to_stream($stream, $temp);
        fclose($stream);
        rewind($temp);
        $rows = [];
        $columns = fgetcsv($temp, 0, ',', '"', '\\');
        if ($columns) {
            $columns[0] = ltrim((string) $columns[0], "\xEF\xBB\xBF");
            while (($line = fgetcsv($temp, 0, ',', '"', '\\')) !== false) {
                if (count($line) === count($columns)) {
                    $rows[] = array_combine($columns, $line);
                }
            }
        }
        fclose($temp);
        return $rows;
    }

    /**
     * The names of the readers who had an account, from the list for Koha that the copy carries.
     *
     * @param \ZipArchive $zip
     * @return array[] Card => first name, last name, email.
     */
    private static function names(\ZipArchive $zip): array {
        $names = [];
        foreach (self::csv($zip, 'koha/lectores.csv') as $row) {
            $names[(string) $row['cardnumber']] = [
                (string) $row['firstname'],
                (string) $row['surname'],
                (string) $row['email'] ?: null,
            ];
        }
        return $names;
    }
}
