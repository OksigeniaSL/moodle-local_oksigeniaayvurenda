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
 * The practice library of the guided help.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

use local_oksigeniaayvurenda\core\control_letter;
use local_oksigeniaayvurenda\core\text;

/**
 * A small made-up library where the guided help moves the real desk and the real adding screen: the same code, the
 * same words and sounds, and nothing of the centre touched. Its readers and books are always the same, so each help
 * knows what to read, and it is made again before each help. It is hidden from the list of libraries, from the copies
 * and from the notices.
 */
final class practice {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var string The address of the practice library. */
    public const SLUG = 'ayv-practica';

    /** @var array[] Its readers: card number (without the letter), first name, surnames, group. */
    public const READERS = [
        [10000, 'Ana', 'Montes Prado', '3º A'],
        [10001, 'Leo', 'Faro Valle', '3º A'],
        [10002, 'Noa', 'Ríos Lago', '5º B'],
    ];

    /** @var array[] Its books: barcode number, title, author, class, how many days ago it was lent to whom (or null). */
    public const BOOKS = [
        [100000, 'El faro dormido', 'Laurel Prado, Irene', 'N', null],
        [100001, 'La nube de tinta', 'Quintana Cardo, Marco', 'N', null],
        [100002, 'Mapas del sur', 'Estrada Brisa, Olivia', '91', [10001, 3]],
        [100003, 'La ladrona de la tormenta', 'Bosque Galán, Pablo', 'N', [10002, 20]],
        [100004, 'Atlas de los mares', 'Lago Montes, Teo', '91', null],
    ];

    /** @var array[] The made-up catalogue the adding screen asks in practice: ISBN, title, author, publisher, year. */
    public const ISBNS = [
        ['9798999999993', 'El jardín de las palabras', 'Duna Faro, Lía', 'Editorial Brújula', '2025'],
        ['9791099999993', 'Cuentos para leer a oscuras', 'Olmo Ríos, Bruno', 'Editorial Brújula', '2024'],
    ];

    /**
     * The practice library, made if it is not there.
     *
     * @return \stdClass
     */
    public static function library(): \stdClass {
        global $DB;
        $library = $DB->get_record(self::T . 'library', ['slug' => self::SLUG]);
        return $library ?: self::make();
    }

    /**
     * Makes the practice library again, as new.
     *
     * @return \stdClass
     */
    public static function reset(): \stdClass {
        global $DB;
        $library = $DB->get_record(self::T . 'library', ['slug' => self::SLUG]);
        if ($library) {
            self::forget((int) $library->entityid);
        }
        return self::make();
    }

    /**
     * Whether an entity is the practice one.
     *
     * @param int $entityid
     * @return bool
     */
    public static function is(int $entityid): bool {
        global $DB;
        static $cache = [];
        if (!isset($cache[$entityid])) {
            $settings = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
            $cache[$entityid] = !empty($settings['practice']);
        }
        return $cache[$entityid];
    }

    /**
     * What the made-up catalogue says of an ISBN, as a description.
     *
     * @param string $isbn
     * @return array|null
     */
    public static function lookup(string $isbn): ?array {
        foreach (self::ISBNS as [$code, $title, $author, $publisher, $year]) {
            if ($code === $isbn) {
                return ['record' => ['doctype' => 'book', 'title' => $title, 'isbn' => $isbn, 'pubyear' => $year,
                    'extent' => '96 p.', 'lang' => 'spa'], 'authorities' => ['author' => [$author],
                    'publisher' => [$publisher], 'class' => ['N'], 'subject' => [], 'collection' => [], 'audience' => []],
                    'extra' => []];
            }
        }
        return null;
    }

    /**
     * Makes the entity, its library, rules, readers and books, with two of them lent.
     *
     * @return \stdClass
     */
    private static function make(): \stdClass {
        global $DB;
        $now = time();
        $transaction = $DB->start_delegated_transaction();
        $entityid = $DB->insert_record(self::T . 'entity', ['name' => get_string('practicename', 'local_oksigeniaayvurenda'),
            'yearstart' => '09-01', 'settings' => json_encode(['practice' => true, 'controlletter' => true]),
            'timecreated' => $now, 'timemodified' => $now]);
        $libraryid = $DB->insert_record(self::T . 'library', ['entityid' => $entityid, 'slug' => self::SLUG,
            'name' => get_string('practicename', 'local_oksigeniaayvurenda'), 'kind' => 'central',
            'timecreated' => $now, 'timemodified' => $now]);
        $DB->insert_record(self::T . 'rule', ['libraryid' => $libraryid, 'readertype' => '*', 'itemtype' => '*',
            'loandays' => 14, 'maxloans' => 3, 'renewals' => 1, 'renewdays' => 7, 'timecreated' => $now,
            'timemodified' => $now]);
        $year = years::around($entityid, $now);
        $readers = [];
        $groups = [];
        foreach (self::READERS as [$number, $first, $last, $group]) {
            $groups[$number] = $group;
            $readers[$number] = $DB->insert_record(self::T . 'reader', ['entityid' => $entityid,
                'cardcode' => control_letter::append((string) $number), 'readertype' => 'student', 'firstname' => $first,
                'lastname' => $last, 'status' => 'active', 'timecreated' => $now, 'timemodified' => $now]);
            $DB->insert_record(self::T . 'member', ['readerid' => $readers[$number], 'yearid' => $year->id,
                'groupname' => $group, 'stage' => 'Primaria', 'source' => 'practice', 'timecreated' => $now]);
        }
        foreach (self::BOOKS as [$number, $title, $author, $class, $lent]) {
            $recordid = $DB->insert_record(self::T . 'record', ['entityid' => $entityid, 'doctype' => 'book',
                'title' => $title, 'searchtext' => text::normalise($title . ' ' . $author), 'timecreated' => $now,
                'timemodified' => $now]);
            $itemid = $DB->insert_record(self::T . 'item', ['entityid' => $entityid, 'libraryid' => $libraryid,
                'recordid' => $recordid, 'barcode' => control_letter::append((string) $number), 'itemtype' => 'normal',
                'callnumber1' => $class, 'status' => $lent ? 'onloan' : 'available', 'timecreated' => $now,
                'timemodified' => $now]);
            if ($lent) {
                [$reader, $daysago] = $lent;
                $loaned = $now - $daysago * DAYSECS;
                $DB->insert_record(self::T . 'loan', ['libraryid' => $libraryid, 'itemid' => $itemid,
                    'readerid' => $readers[$reader], 'yearid' => $year->id, 'groupname' => $groups[$reader],
                    'timeloaned' => $loaned,
                    'timedue' => $loaned + 14 * DAYSECS, 'renewals' => 0, 'timecreated' => $loaned,
                    'timemodified' => $loaned]);
            }
        }
        $transaction->allow_commit();
        return $DB->get_record(self::T . 'library', ['id' => $libraryid], '*', MUST_EXIST);
    }

    /**
     * Takes the practice entity away, with everything it holds.
     *
     * @param int $entityid
     */
    private static function forget(int $entityid): void {
        global $DB;
        $libraries = $DB->get_fieldset_select(self::T . 'library', 'id', 'entityid = ?', [$entityid]);
        $records = $DB->get_fieldset_select(self::T . 'record', 'id', 'entityid = ?', [$entityid]);
        $readers = $DB->get_fieldset_select(self::T . 'reader', 'id', 'entityid = ?', [$entityid]);
        $stocktakes = $libraries ? $DB->get_fieldset_select(self::T . 'stocktake', 'id', 'libraryid IN (' .
            implode(',', array_map('intval', $libraries)) . ')') : [];
        $withdrawals = $libraries ? $DB->get_fieldset_select(self::T . 'withdrawal', 'id', 'libraryid IN (' .
            implode(',', array_map('intval', $libraries)) . ')') : [];
        $transaction = $DB->start_delegated_transaction();
        foreach (
            [['stockread', 'stocktakeid', $stocktakes], ['stocktake', 'libraryid', $libraries],
                ['withdrawn', 'withdrawalid', $withdrawals], ['withdrawal', 'libraryid', $libraries],
                ['hold', 'libraryid', $libraries], ['loan', 'libraryid', $libraries], ['rule', 'libraryid', $libraries],
                ['recauth', 'recordid', $records],
                ['member', 'readerid', $readers]] as [$table, $field, $ids]
        ) {
            if ($ids) {
                [$in, $params] = $DB->get_in_or_equal($ids);
                $DB->delete_records_select(self::T . $table, $field . ' ' . $in, $params);
            }
        }
        foreach ($records as $recordid) {
            covers::remove((int) $recordid);
        }
        foreach (['item', 'record', 'reader', 'authority', 'year', 'library'] as $table) {
            $DB->delete_records(self::T . $table, ['entityid' => $entityid]);
        }
        $DB->delete_records(self::T . 'entity', ['id' => $entityid]);
        $transaction->allow_commit();
    }
}
