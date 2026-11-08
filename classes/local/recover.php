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
 * What it does not have stays out: the accounts of the site and the voices. The covers and the other files have their own copy,
 * which comes back after it (bring_files).
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class recover {
    /** @var string[] Columns that had another name in copies made before 0.155.0. */
    private const OLD_NAMES = ['origin' => 'system', 'groupnames' => 'groups'];

    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var string[] What comes back, parents first; a tutor is an account, and a digital file is not in the copy. */
    private const TABLES = ['library', 'year', 'authority', 'record', 'recauth', 'item', 'reader', 'member', 'keepsake', 'loan',
        'hold',
        'stocktake', 'lot', 'lotitem', 'withdrawal', 'withdrawn', 'stockread', 'rule', 'challenge', 'review', 'wish', 'roomread',
        'bankset', 'banktitle', 'digital', 'source', 'purchaseorder', 'purchase', 'purchasewho', 'budget', 'ill', 'sanction',
        'formatask'];

    /** @var string Where a recovered centre keeps which of its ids were which, for its files to come after. */
    private const MAPAREA = 'recovermap';

    /** @var string[] What each area of files may hold; a copy is a file someone brings, and nothing else gets served. */
    private const KINDS = ['cover' => '/\.(jpe?g|png|webp)$/i', 'reviewdrawing' => '/\.png$/i',
        'libraryimage' => '/\.(jpe?g|png|webp)$/i', 'portrait' => '/\.(jpe?g|png|webp|json)$/i'];

    /** @var string[] Fields that point at their own table, set in a second pass. */
    private const SELF = ['library' => 'parentid', 'authority' => 'parentid'];

    /** @var string[] What a centre's settings keep of the place they came from: none of it holds here. */
    private const LEFT_BEHIND = ['cohorts', 'courses', 'cifra', 'onerosterapi', 'offsite', 'plan', 'closed', 'demo', 'ltiregister',
        'practice'];

    /** @var int The largest piece of a copy that is read, a guard against a file that is not one. */
    private const LIMIT = 1024 * 1048576;

    /**
     * Whether a file is a copy, and what it holds.
     *
     * @param string $path A zip.
     * @return array|null name, libraries, records, items, readers, loans, missing; null if it is not a copy.
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
            'loans' => count(array_filter(self::rows($zip, 'loan'), fn($l) => (string) ($l['timereturned'] ?? '') === '')),
            'missing' => self::missing($zip)];
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
                // A file of the digital collection comes with its file, after; a link needs nothing else.
                if ($table === 'digital' && ($row['kind'] ?? '') !== 'link') {
                    continue;
                }
                $new = self::row($table, $row, $columns, $keys[$table] ?? [], $map, $userid);
                if ($new === null) {
                    continue;
                }
                if ($table === 'library') {
                    $new->slug = libraries::slug((string) $row['slug']);
                }
                if ($table === 'digital') {
                    $new->url = clean_param((string) ($new->url ?? ''), PARAM_URL);
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
        self::keep_map($entityid, $map);
        \local_oksigeniaayvurenda\event\copy_recovered::fire($entityid);
        $first = $DB->get_records(self::T . 'library', ['entityid' => $entityid], 'id', '*', 0, 1);
        return reset($first);
    }

    /**
     * Keeps which of a recovered centre's ids were which, for its files to come after.
     *
     * @param int $entityid
     * @param array $map
     */
    private static function keep_map(int $entityid, array $map): void {
        $fs = get_file_storage();
        $context = \context_system::instance();
        $fs->delete_area_files($context->id, 'local_oksigeniaayvurenda', self::MAPAREA, $entityid);
        $fs->create_file_from_string(['contextid' => $context->id, 'component' => 'local_oksigeniaayvurenda',
            'filearea' => self::MAPAREA, 'itemid' => $entityid, 'filepath' => '/', 'filename' => 'map.json'], (string) json_encode(
                array_intersect_key($map, array_flip(['entity', 'record', 'review', 'library', 'year', 'reader', 'digital']))
            ));
    }

    /**
     * Which of a recovered centre's ids were which.
     *
     * @param int $entityid
     * @return array|null Null if it is not a recovered centre.
     */
    private static function map_of(int $entityid): ?array {
        $file = get_file_storage()->get_file(
            \context_system::instance()->id,
            'local_oksigeniaayvurenda',
            self::MAPAREA,
            $entityid,
            '/',
            'map.json'
        );
        return $file ? json_decode($file->get_content(), true) : null;
    }

    /**
     * Whether a centre was recovered, and so can take its files.
     *
     * @param int $entityid
     * @return bool
     */
    public static function recovered(int $entityid): bool {
        return self::map_of($entityid) !== null;
    }

    /**
     * Brings a centre's files back from their copy. It can be done again: what is there already stays.
     *
     * @param int $entityid The recovered centre.
     * @param string $path The zip.
     * @param int $userid Who does it.
     * @return array|null files, digital; null if it is not the copy of the files of this centre.
     */
    public static function bring_files(int $entityid, string $path, int $userid): ?array {
        global $DB;
        $map = self::map_of($entityid);
        $zip = new \ZipArchive();
        if ($map === null || $zip->open($path, \ZipArchive::RDONLY) !== true) {
            return null;
        }
        $origin = json_decode((string) $zip->getFromName('origen.json'), true);
        if ((int) ($map['entity'][(int) ($origin['entity'] ?? 0)] ?? 0) !== $entityid) {
            $zip->close();
            return null;
        }
        $columns = $DB->get_columns(self::T . 'digital');
        $keys = self::foreign_keys()['digital'] ?? [];
        $digital = 0;
        foreach (self::csv($zip, 'digital.csv') as $row) {
            $old = (int) ($row['id'] ?? 0);
            if (isset($map['digital'][$old]) || ($row['kind'] ?? '') !== 'file') {
                continue;
            }
            $new = self::row('digital', $row, $columns, $keys, $map, $userid);
            if ($new !== null) {
                $map['digital'][$old] = (int) $DB->insert_record(self::T . 'digital', $new);
                $digital++;
            }
        }
        self::keep_map($entityid, $map);
        $fs = get_file_storage();
        $context = \context_system::instance();
        $dir = make_request_directory();
        $files = 0;
        // By batches: file by file, a database writes each one to disk, and fifteen thousand covers take minutes.
        $transaction = $DB->start_delegated_transaction();
        foreach (self::csv($zip, 'ficheros.csv') as $row) {
            $area = (string) ($row['area'] ?? '');
            $name = clean_filename((string) ($row['file'] ?? ''));
            $table = filecopy::AREAS[$area] ?? '';
            $item = (int) ($map[$table][(int) ($row['item'] ?? 0)] ?? 0);
            $inzip = (string) ($row['path'] ?? '');
            $allowed = $name !== '' && (!isset(self::KINDS[$area]) || preg_match(self::KINDS[$area], $name));
            $there = $item && $fs->file_exists($context->id, 'local_oksigeniaayvurenda', $area, $item, '/', $name);
            if (!$table || !$item || !$allowed || $there) {
                continue;
            }
            $stat = $zip->statName($inzip);
            // A file that says it holds more than the disk has is not going to fit.
            if (!$stat || $stat['size'] > disk_free_space($dir)) {
                continue;
            }
            $stream = $zip->getStream($inzip);
            if (!$stream) {
                continue;
            }
            $temp = $dir . '/' . $files . '.tmp';
            file_put_contents($temp, $stream);
            fclose($stream);
            // The same door as a file uploaded by hand.
            if ($area !== 'digital' || digital::acceptable($temp, $name)) {
                $fs->create_file_from_pathname(['contextid' => $context->id, 'component' => 'local_oksigeniaayvurenda',
                    'filearea' => $area, 'itemid' => $item, 'filepath' => '/', 'filename' => $name], $temp);
                $files++;
                if ($files % 500 === 0) {
                    $transaction->allow_commit();
                    $transaction = $DB->start_delegated_transaction();
                }
            }
            @unlink($temp);
        }
        $transaction->allow_commit();
        $zip->close();
        return ['files' => $files, 'digital' => $digital];
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
            $from = array_key_exists($name, $row) ? $name : (self::OLD_NAMES[$name] ?? $name);
            if ($name === 'id' || !array_key_exists($from, $row)) {
                continue;
            }
            $value = $row[$from];
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
        // Copies made before 0.154.0 escaped with a backslash.
        [$rows, $bad] = self::parse($temp, '');
        if ($bad) {
            [$old, $oldbad] = self::parse($temp, '\\');
            if ($oldbad < $bad) {
                $rows = $old;
            }
        }
        fclose($temp);
        return $rows;
    }

    /**
     * Reads a table of a copy.
     *
     * @param resource $stream
     * @param string $escape
     * @return array The rows, and how many could not be read.
     */
    private static function parse($stream, string $escape): array {
        rewind($stream);
        $rows = [];
        $bad = 0;
        $columns = fgetcsv($stream, 0, ',', '"', $escape);
        if ($columns) {
            $columns[0] = ltrim((string) $columns[0], "\xEF\xBB\xBF");
            while (($line = fgetcsv($stream, 0, ',', '"', $escape)) !== false) {
                if (count($line) === count($columns)) {
                    $rows[] = array_combine($columns, $line);
                } else {
                    $bad++;
                }
            }
        }
        return [$rows, $bad];
    }

    /**
     * How many rows the copy has fewer than it says it has.
     *
     * @param \ZipArchive $zip
     * @return int
     */
    private static function missing(\ZipArchive $zip): int {
        $said = [];
        if (preg_match('/\{[^{}]*\}/', (string) $zip->getFromName('LEEME.txt'), $found)) {
            $said = json_decode($found[0], true) ?: [];
        }
        $missing = 0;
        foreach (['library', 'record', 'item', 'reader', 'loan'] as $table) {
            if (isset($said[$table])) {
                $missing += max(0, (int) $said[$table] - count(self::rows($zip, $table)));
            }
        }
        return $missing;
    }

    /**
     * The names of the readers who had an account: from the list of accounts of the copy (since 0.176, also those who
     * left), and from the list for Koha in an older copy.
     *
     * @param \ZipArchive $zip
     * @return array[] Card => first name, last name, email.
     */
    private static function names(\ZipArchive $zip): array {
        $names = [];
        foreach (self::csv($zip, 'cuentas.csv') as $row) {
            $names[(string) $row['cardcode']] = [
                (string) $row['firstname'],
                (string) $row['lastname'],
                (string) $row['email'] ?: null,
            ];
        }
        if ($names) {
            return $names;
        }
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
