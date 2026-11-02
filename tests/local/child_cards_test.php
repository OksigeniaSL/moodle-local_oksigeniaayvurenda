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
 * What a family sees of a child's library in the app, and what the page of a book says to an adult who is not its reader.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(mine::class)]
final class child_cards_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * A child with a book out and another set aside, in a stage of the given look.
     *
     * @param string $stage
     * @return \stdClass
     */
    private function world(string $stage): \stdClass {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/oksigeniaayvurenda/lib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $w = new \stdClass();
        $w->gen = $gen;
        $w->library = $gen->create_library();
        $w->now = time();
        $entityid = (int) $w->library->entityid;
        $w->child = $this->getDataGenerator()->create_user();
        $w->adult = $this->getDataGenerator()->create_user();
        $w->reader = $gen->create_reader(['entityid' => $entityid, 'cardcode' => '10000H', 'userid' => $w->child->id]);
        $year = years::around($entityid, $w->now);
        $gen->create_member(['readerid' => $w->reader->id, 'yearid' => $year->id, 'groupname' => '3 A', 'stage' => $stage]);
        $record = $gen->create_record(['entityid' => $entityid, 'title' => 'El faro secreto']);
        $w->record = $record;
        $w->item = $gen->create_item(['libraryid' => $w->library->id, 'recordid' => $record->id, 'barcode' => '100000L',
            'status' => 'onloan']);
        $DB->insert_record(self::T . 'loan', ['libraryid' => $w->library->id, 'itemid' => $w->item->id,
            'readerid' => $w->reader->id,
            'yearid' => $year->id, 'timeloaned' => $w->now - DAYSECS, 'timedue' => $w->now + 5 * DAYSECS, 'renewals' => 0,
            'timecreated' => $w->now, 'timemodified' => $w->now]);
        return $w;
    }

    /**
     * Up to the flower, the family sees the title, and the row opens the book.
     */
    public function test_a_young_child(): void {
        $w = $this->world('Primaria');
        $cards = local_oksigeniaayvurenda_oksigeniaaty_child_cards((int) $w->adult->id, (int) $w->child->id);
        $this->assertCount(1, $cards);
        $this->assertCount(1, $cards[0]['items']);
        $row = $cards[0]['items'][0];
        $this->assertSame('El faro secreto', $row['title']);
        $this->assertFalse($row['urgent']);
        $this->assertSame('mobile_record_view', $row['method']);
        $this->assertSame($w->library->slug, $row['args']['library']);
    }

    /**
     * From the sky, there is a book and its date: what they read is theirs, and nothing in the row tells which one.
     */
    public function test_an_older_child(): void {
        $w = $this->world('Secundaria');
        $cards = local_oksigeniaayvurenda_oksigeniaaty_child_cards((int) $w->adult->id, (int) $w->child->id);
        $text = json_encode($cards, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('faro', $text);
        $this->assertStringNotContainsString((string) $w->record->id . ',', json_encode($cards[0]['items'][0]['args'] ?? []));
        $this->assertSame(get_string('mobilechildbook', 'local_oksigeniaayvurenda'), $cards[0]['items'][0]['title']);
        $this->assertTrue($cards[0]['items'][0]['plain']);
        $this->assertArrayNotHasKey('method', $cards[0]['items'][0]);
        $this->assertGreaterThan(0, $cards[0]['items'][0]['date']);
    }

    /**
     * Late says so; nothing out, no card; a child who is no reader, none.
     */
    public function test_late_and_nothing(): void {
        global $DB;
        $w = $this->world('Primaria');
        $DB->set_field(self::T . 'loan', 'timedue', $w->now - DAYSECS, ['itemid' => $w->item->id]);
        $row = mine::child_cards((int) $w->child->id, $w->now)[0]['items'][0];
        $this->assertTrue($row['urgent']);
        $this->assertStringContainsString(get_string('mobilechildlate', 'local_oksigeniaayvurenda', ''), $row['detail']);
        $DB->delete_records(self::T . 'loan');
        $this->assertSame([], mine::child_cards((int) $w->child->id, $w->now));
        $this->assertSame([], mine::child_cards((int) $w->adult->id, $w->now));
    }

    /**
     * An adult who opens the book of a child's loan is not told it is theirs, and cannot act as its reader.
     */
    public function test_the_book_as_the_adult(): void {
        global $DB;
        $w = $this->world('Primaria');
        $this->setUser($w->adult);
        $args = ['library' => $w->library->slug, 'id' => (int) $w->record->id];
        $page = \local_oksigeniaayvurenda\output\mobile::mobile_record_view($args);
        $html = json_encode($page, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('renew_loan', $html);
        $this->assertStringNotContainsString(get_string('mobilestateloan', 'local_oksigeniaayvurenda'), $html);
        $this->assertFalse($DB->record_exists(self::T . 'reader', ['userid' => $w->adult->id]), 'opening it makes nobody a reader');
    }
}
