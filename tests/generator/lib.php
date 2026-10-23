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
 * Test data generator.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_oksigeniaayvurenda_generator extends component_generator_base {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var int Counter for codes that must not repeat. */
    private int $next = 1;

    /**
     * Inserts a row with the times filled in.
     *
     * @param string $table Without the prefix.
     * @param array $defaults
     * @param array $record
     * @return stdClass
     */
    private function insert(string $table, array $defaults, array $record): stdClass {
        global $DB;
        $record = (object) array_merge($defaults, $record);
        $record->id = $DB->insert_record(self::T . $table, $record);
        return $record;
    }

    /**
     * An entity.
     *
     * @param array $record
     * @return stdClass
     */
    public function create_entity(array $record = []): stdClass {
        $now = time();
        return $this->insert('entity', ['name' => 'Entity ' . $this->next++, 'yearstart' => '09-01',
            'timecreated' => $now, 'timemodified' => $now], $record);
    }

    /**
     * A library; it creates its entity when none is given.
     *
     * @param array $record
     * @return stdClass
     */
    public function create_library(array $record = []): stdClass {
        $now = time();
        $record['entityid'] = $record['entityid'] ?? $this->create_entity()->id;
        $n = $this->next++;
        return $this->insert('library', ['name' => 'Library ' . $n, 'slug' => 'library-' . $n, 'kind' => 'central',
            'timecreated' => $now, 'timemodified' => $now], $record);
    }

    /**
     * A record of the entity.
     *
     * @param array $record Needs entityid.
     * @return stdClass
     */
    public function create_record(array $record): stdClass {
        $now = time();
        return $this->insert('record', ['doctype' => 'book', 'title' => 'Title ' . $this->next++,
            'timecreated' => $now, 'timemodified' => $now], $record);
    }

    /**
     * A copy in a library; it creates its record when none is given.
     *
     * @param array $record Needs libraryid.
     * @return stdClass
     */
    public function create_item(array $record): stdClass {
        global $DB;
        $now = time();
        $entityid = $DB->get_field(self::T . 'library', 'entityid', ['id' => $record['libraryid']], MUST_EXIST);
        $record['recordid'] = $record['recordid'] ?? $this->create_record(['entityid' => $entityid])->id;
        $barcode = \local_oksigeniaayvurenda\core\control_letter::append(sprintf('%06d', 500000 + $this->next++));
        return $this->insert('item', ['entityid' => $entityid, 'barcode' => $barcode, 'itemtype' => 'normal',
            'status' => 'available', 'timecreated' => $now, 'timemodified' => $now], $record);
    }

    /**
     * A reader of an entity.
     *
     * @param array $record Needs entityid.
     * @return stdClass
     */
    public function create_reader(array $record): stdClass {
        $now = time();
        $code = \local_oksigeniaayvurenda\core\control_letter::append(sprintf('%05d', 50000 + $this->next++));
        return $this->insert('reader', ['cardcode' => $code, 'readertype' => 'student', 'status' => 'active',
            'timecreated' => $now, 'timemodified' => $now], $record);
    }

    /**
     * A school year of an entity.
     *
     * @param array $record Needs entityid.
     * @return stdClass
     */
    public function create_year(array $record): stdClass {
        $start = make_timestamp(2026, 9, 1);
        return $this->insert('year', ['name' => '2026-2027', 'timestart' => $start,
            'timeend' => make_timestamp(2027, 9, 1)], $record);
    }

    /**
     * A reader's group in a school year.
     *
     * @param array $record Needs readerid and yearid.
     * @return stdClass
     */
    public function create_member(array $record): stdClass {
        return $this->insert('member', ['groupname' => '3º Primaria A', 'stage' => 'Primaria', 'source' => 'test',
            'timecreated' => time()], $record);
    }

    /**
     * A loan, out for a week from now unless told otherwise.
     *
     * @param array $record Needs libraryid, itemid and readerid.
     * @return stdClass
     */
    public function create_loan(array $record): stdClass {
        $now = time();
        return $this->insert('loan', ['timeloaned' => $now, 'timedue' => $now + WEEKSECS, 'renewals' => 0,
            'timecreated' => $now, 'timemodified' => $now], $record);
    }
}
