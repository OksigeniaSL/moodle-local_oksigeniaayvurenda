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
        // For one request: a static one would outlive the tests, whose ids come again.
        $cache = \cache::make('local_oksigeniaayvurenda', 'access');
        $practice = $cache->get('practice_' . $entityid);
        if ($practice === false) {
            $settings = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
            $practice = empty($settings['practice']) ? 0 : 1;
            $cache->set('practice_' . $entityid, $practice);
        }
        return (bool) $practice;
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
        // What the helps of the reviews, the class books and My class show: Leo and Noa told about a book (the reviews
        // are there as sent; no loan is added, so the reports keep their numbers); 3º A met this term's challenge and
        // has not chosen its book; 4º C met it and asked for one.
        $records = $DB->get_records_menu(self::T . 'record', ['entityid' => $entityid], '', 'title, id');
        $cloud = (int) $records[self::BOOKS[1][1]];
        $told = [[10001, $cloud, 'practicereviewleo', 3], [10002, (int) $records[self::BOOKS[3][1]], 'practicereviewnoa', 1]];
        foreach ($told as [$reader, $recordid, $key, $hoursago]) {
            $DB->insert_record(self::T . 'review', ['entityid' => $entityid, 'recordid' => $recordid,
                'readerid' => $readers[$reader], 'text' => get_string($key, 'local_oksigeniaayvurenda'), 'status' => 'pending',
                'forlittle' => 0, 'timecreated' => $now - $hoursago * HOURSECS, 'timemodified' => $now - $hoursago * HOURSECS]);
        }
        $term = challenges::term($year, $now);
        foreach (['3º A' => 'corners', '4º C' => 'everyone'] as $group => $kind) {
            $challengeid = $DB->insert_record(self::T . 'challenge', ['yearid' => $year->id, 'groupname' => $group,
                'term' => $term, 'kind' => $kind, 'timecompleted' => $now - DAYSECS, 'timecreated' => $now - 30 * DAYSECS,
                'timemodified' => $now - DAYSECS]);
            if ($group === '4º C') {
                $DB->insert_record(self::T . 'wish', ['entityid' => $entityid, 'challengeid' => $challengeid,
                    'title' => get_string('practicewishtitle', 'local_oksigeniaayvurenda'),
                    'note' => get_string('practicewishnote', 'local_oksigeniaayvurenda'), 'status' => 'asked',
                    'timecreated' => $now - 5 * HOURSECS, 'timemodified' => $now - 5 * HOURSECS]);
            }
        }
        $transaction->allow_commit();
        return $DB->get_record(self::T . 'library', ['id' => $libraryid], '*', MUST_EXIST);
    }

    /**
     * The groups of the practice library, for the help of My class.
     *
     * @return string[]
     */
    public static function groups(): array {
        return array_values(array_unique(array_column(self::READERS, 3)));
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
        $lots = $libraries ? $DB->get_fieldset_select(self::T . 'lot', 'id', 'libraryid IN (' .
            implode(',', array_map('intval', $libraries)) . ')') : [];
        $transaction = $DB->start_delegated_transaction();
        foreach (
            [['stockread', 'stocktakeid', $stocktakes], ['stocktake', 'libraryid', $libraries],
                ['withdrawn', 'withdrawalid', $withdrawals], ['withdrawal', 'libraryid', $libraries],
                ['lotitem', 'lotid', $lots], ['lot', 'libraryid', $libraries],
                ['hold', 'libraryid', $libraries], ['loan', 'libraryid', $libraries], ['rule', 'libraryid', $libraries],
                ['roomread', 'libraryid', $libraries],
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
        // The reviews (with their drawings), the class books and the challenges of its years.
        foreach ($DB->get_fieldset_select(self::T . 'review', 'id', 'entityid = ?', [$entityid]) as $reviewid) {
            images::remove(reviews::AREA, (int) $reviewid, 'drawing');
        }
        $DB->delete_records(self::T . 'review', ['entityid' => $entityid]);
        $DB->delete_records(self::T . 'wish', ['entityid' => $entityid]);
        $years = $DB->get_fieldset_select(self::T . 'year', 'id', 'entityid = ?', [$entityid]);
        if ($years) {
            [$in, $params] = $DB->get_in_or_equal($years);
            $DB->delete_records_select(self::T . 'challenge', 'yearid ' . $in, $params);
        }
        foreach (['item', 'record', 'reader', 'authority', 'year', 'library'] as $table) {
            $DB->delete_records(self::T . $table, ['entityid' => $entityid]);
        }
        $DB->delete_records(self::T . 'entity', ['id' => $entityid]);
        $transaction->allow_commit();
    }
}
