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

use local_oksigeniaayvurenda\core\callnumber;
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

    /** @var array[] More books, only for the help of weeding: barcode number, title, author, class. */
    private const SHELVED = [
        [100005, 'Cartas del viento', 'Cardo Olmo, Eva', 'N'],
        [100006, 'El reloj de sal', 'Brisa Galán, Hugo', 'N'],
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
            $itemid = self::book(
                (object) ['id' => $libraryid, 'entityid' => $entityid],
                $number,
                $title,
                $author,
                $class,
                (bool) $lent,
                $now
            );
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
     * What a guided help needs and the library as new does not have.
     *
     * @param \stdClass $library The practice library.
     * @param string $piece
     * @param int $userid Who is watching: the tutor in the help of the late books, whom the button names.
     */
    public static function prepare(\stdClass $library, string $piece, int $userid): void {
        global $DB;
        $now = time();
        $code = fn(int $number) => control_letter::append((string) $number);
        $items = [];
        foreach ($DB->get_records(self::T . 'item', ['libraryid' => $library->id]) as $item) {
            $items[$item->barcode] = $item;
        }
        $readers = $DB->get_records_menu(self::T . 'reader', ['entityid' => $library->entityid], '', 'cardcode, id');
        $year = years::around((int) $library->entityid, $now);
        if ($piece === 'weed') {
            // One lost, one worn, and some that nobody has taken for years, on a shelf with a name.
            $place = $DB->insert_record(self::T . 'authority', ['entityid' => $library->entityid, 'kind' => 'location',
                'label' => get_string('practiceshelf', 'local_oksigeniaayvurenda'), 'timecreated' => $now, 'timemodified' => $now]);
            $DB->set_field(self::T . 'item', 'locationid', $place, ['libraryid' => $library->id]);
            $DB->set_field(self::T . 'item', 'status', 'lost', ['id' => $items[$code(self::BOOKS[4][0])]->id]);
            $DB->set_field(self::T . 'item', 'itemcondition', 'worn', ['id' => $items[$code(self::BOOKS[0][0])]->id]);
            $old = $now - 8 * 365 * DAYSECS;
            $DB->set_field(self::T . 'item', 'timeacquired', $old, ['id' => $items[$code(self::BOOKS[1][0])]->id]);
            foreach (self::SHELVED as [$number, $title, $author, $class]) {
                $itemid = self::book($library, $number, $title, $author, $class, false, $now);
                $DB->update_record(self::T . 'item', (object) ['id' => $itemid, 'locationid' => $place,
                    'timeacquired' => $now - 9 * 365 * DAYSECS]);
            }
        } else if ($piece === 'late') {
            // More than a month, more than a fortnight and (as new) under one, in two groups; 3º A has its tutor.
            $late = [[self::BOOKS[0][0], self::READERS[0], 54], [self::BOOKS[1][0], self::READERS[1], 32]];
            foreach ($late as [$number, $who, $ago]) {
                $DB->set_field(self::T . 'item', 'status', 'onloan', ['id' => $items[$code($number)]->id]);
                $loaned = $now - $ago * DAYSECS;
                $DB->insert_record(self::T . 'loan', ['libraryid' => $library->id, 'itemid' => $items[$code($number)]->id,
                    'readerid' => $readers[$code($who[0])], 'yearid' => $year->id, 'groupname' => $who[3], 'timeloaned' => $loaned,
                    'timedue' => $loaned + 14 * DAYSECS, 'renewals' => 0, 'timecreated' => $loaned, 'timemodified' => $loaned]);
            }
            $DB->insert_record(self::T . 'tutor', ['yearid' => $year->id, 'groupname' => self::READERS[0][3], 'userid' => $userid,
                'timecreated' => $now, 'timemodified' => $now]);
        } else if ($piece === 'holds') {
            // One set aside for Ana, and two waiting for the book Noa has.
            $faro = $items[$code(self::BOOKS[0][0])];
            $DB->set_field(self::T . 'item', 'status', 'held', ['id' => $faro->id]);
            $DB->insert_record(self::T . 'hold', ['libraryid' => $library->id, 'recordid' => $faro->recordid,
                'readerid' => $readers[$code(self::READERS[0][0])], 'itemid' => $faro->id, 'status' => 'ready',
                'timecreated' => $now - 2 * DAYSECS, 'timeready' => $now - DAYSECS, 'timeexpires' => $now + 4 * DAYSECS,
                'timemodified' => $now - DAYSECS]);
            $lent = $items[$code(self::BOOKS[3][0])];
            foreach ([[self::READERS[1][0], 3], [self::READERS[0][0], 1]] as [$who, $ago]) {
                $DB->insert_record(self::T . 'hold', ['libraryid' => $library->id, 'recordid' => $lent->recordid,
                    'readerid' => $readers[$code($who)], 'status' => 'waiting', 'timecreated' => $now - $ago * DAYSECS,
                    'timemodified' => $now - $ago * DAYSECS]);
            }
        } else if ($piece === 'voices') {
            // A voice waiting: Ana's, with no permission of her family yet. It sounds if the art brings a clip to borrow.
            $itemid = $DB->insert_record(self::T . 'digital', ['entityid' => $library->entityid,
                'recordid' => $items[$code(self::BOOKS[0][0])]->recordid, 'kind' => 'voice', 'format' => 'audio',
                'title' => get_string('practicevoicetitle', 'local_oksigeniaayvurenda'), 'licence' => 'rights', 'audience' => '',
                'download' => 0, 'status' => 'pending', 'readerid' => $readers[$code(self::READERS[0][0])],
                'signature' => self::READERS[0][1], 'createdby' => $userid, 'timecreated' => $now, 'timemodified' => $now]);
            $art = \core_component::get_component_directory('local_oksigeniaayvurendaarte');
            $clip = $art ? $art . '/voz/desk/' . substr(current_language(), 0, 2) . '/01.mp3' : '';
            if ($clip !== '' && is_readable($clip)) {
                get_file_storage()->create_file_from_pathname(['contextid' => \context_system::instance()->id,
                    'component' => 'local_oksigeniaayvurenda', 'filearea' => digital::AREA, 'itemid' => $itemid,
                    'filepath' => '/', 'filename' => 'practica.mp3'], $clip);
            }
        }
    }

    /**
     * One more book on the shelf.
     *
     * @param \stdClass $library
     * @param int $number Its barcode, without the letter.
     * @param string $title
     * @param string $author
     * @param string $class
     * @param bool $lent
     * @param int $now
     * @return int The copy.
     */
    private static function book(
        \stdClass $library,
        int $number,
        string $title,
        string $author,
        string $class,
        bool $lent,
        int $now
    ): int {
        global $DB;
        $recordid = $DB->insert_record(self::T . 'record', ['entityid' => $library->entityid, 'doctype' => 'book',
            'title' => $title, 'searchtext' => text::normalise($title . ' ' . $author), 'timecreated' => $now,
            'timemodified' => $now]);
        return $DB->insert_record(self::T . 'item', ['entityid' => $library->entityid, 'libraryid' => $library->id,
            'recordid' => $recordid, 'barcode' => control_letter::append((string) $number), 'itemtype' => 'normal',
            'callnumber1' => $class, 'callnumber2' => callnumber::author($author),
            'callnumber3' => callnumber::title($title), 'status' => $lent ? 'onloan' : 'available', 'timecreated' => $now,
            'timemodified' => $now]);
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
        // The voice of its help, what an import left behind and the tutor of its help.
        foreach ($DB->get_fieldset_select(self::T . 'digital', 'id', 'entityid = ?', [$entityid]) as $digitalid) {
            get_file_storage()->delete_area_files(
                \context_system::instance()->id,
                'local_oksigeniaayvurenda',
                digital::AREA,
                (int) $digitalid
            );
        }
        $DB->delete_records(self::T . 'digital', ['entityid' => $entityid]);
        $DB->delete_records(self::T . 'source', ['entityid' => $entityid]);
        $years = $DB->get_fieldset_select(self::T . 'year', 'id', 'entityid = ?', [$entityid]);
        if ($years) {
            [$in, $params] = $DB->get_in_or_equal($years);
            $DB->delete_records_select(self::T . 'challenge', 'yearid ' . $in, $params);
            $DB->delete_records_select(self::T . 'tutor', 'yearid ' . $in, $params);
        }
        foreach (['item', 'record', 'reader', 'authority', 'year', 'library'] as $table) {
            $DB->delete_records(self::T . $table, ['entityid' => $entityid]);
        }
        $DB->delete_records(self::T . 'entity', ['id' => $entityid]);
        $transaction->allow_commit();
    }
}
