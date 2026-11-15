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
        $cards = mine::child_cards((int) $w->child->id, $w->now);
        $this->assertCount(1, $cards);
        $this->assertCount(1, $cards[0]['items']);
        $row = $cards[0]['items'][0];
        $this->assertSame('El faro secreto', $row['title']);
        $this->assertFalse($row['urgent']);
        $this->assertSame('mobile_record_view', $row['method']);
        $this->assertSame($w->library->slug, $row['args']['library']);
        $this->assertArrayNotHasKey('cover', $row, 'a book without a cover sends none');
        $this->assertTrue(covers::store((int) $w->record->id, self::jpeg(), 'test'));
        $row = mine::child_cards((int) $w->child->id, $w->now)[0]['items'][0];
        $this->assertStringStartsWith('data:image/jpeg;base64,', $row['cover']);
    }

    /**
     * From the sky, there is a book and its date: what they read is theirs, and nothing in the row tells which one.
     */
    public function test_an_older_child(): void {
        $w = $this->world('Secundaria');
        $cards = mine::child_cards((int) $w->child->id, $w->now);
        $text = json_encode($cards, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('faro', $text);
        $this->assertStringNotContainsString((string) $w->record->id . ',', json_encode($cards[0]['items'][0]['args'] ?? []));
        $this->assertSame(get_string('mobilechildbook', 'local_oksigeniaayvurenda'), $cards[0]['items'][0]['title']);
        $this->assertTrue($cards[0]['items'][0]['plain']);
        $this->assertArrayNotHasKey('method', $cards[0]['items'][0]);
        $this->assertGreaterThan(0, $cards[0]['items'][0]['date']);
        $this->assertTrue(covers::store((int) $w->record->id, self::jpeg(), 'test'));
        $this->assertArrayNotHasKey('cover', mine::child_cards((int) $w->child->id, $w->now)[0]['items'][0], 'nor its cover');
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
     * The card asks for the shelf; a book due in a few days is red, here and in the child's line on the family's home,
     * which says how many there are; a book waiting at the desk, a striped spine «to collect».
     */
    public function test_the_shelf_and_the_line(): void {
        global $DB;
        $w = $this->world('Primaria');
        $start = fn(string $key) => get_string($key, 'local_oksigeniaayvurenda', '');
        $card = mine::child_cards((int) $w->child->id, $w->now)[0];
        $this->assertSame('shelf', $card['layout']);
        $this->assertSame('fas-book-open-reader', $card['facet']['icon']);
        $this->assertStringStartsWith($start('mobilechildfacetout'), $card['facet']['text']);
        $this->assertFalse($card['facet']['urgent']);
        $this->assertFalse($card['items'][0]['urgent']);
        $DB->set_field(self::T . 'loan', 'timedue', $w->now + DAYSECS, ['itemid' => $w->item->id]);
        $record = $w->gen->create_record(['entityid' => $w->library->entityid, 'title' => 'La isla del cometa']);
        $item = $w->gen->create_item(['libraryid' => $w->library->id, 'recordid' => $record->id, 'barcode' => '100001L',
            'status' => 'onloan']);
        $loan = $DB->get_record(self::T . 'loan', ['itemid' => $w->item->id]);
        unset($loan->id);
        $loan->itemid = $item->id;
        $loan->timedue = $w->now + 20 * DAYSECS;
        $DB->insert_record(self::T . 'loan', $loan);
        $card = mine::child_cards((int) $w->child->id, $w->now)[0];
        $this->assertTrue($card['items'][0]['urgent'], 'due tomorrow');
        $this->assertFalse($card['items'][1]['urgent'], 'due in twenty days');
        $this->assertTrue($card['facet']['urgent']);
        $this->assertStringStartsWith($start('mobilechildfacetsoon'), $card['facet']['text']);
        $this->assertStringEndsWith(get_string(
            'mobilechildfacetcount',
            'local_oksigeniaayvurenda',
            (object) ['line' => '', 'count' => 2]
        ), $card['facet']['text']);

        // An older reader's book set aside: «A book to collect», nothing that says which, and the line says so.
        $w = $this->world('Secundaria');
        $DB->delete_records(self::T . 'loan');
        $DB->insert_record(self::T . 'hold', ['libraryid' => $w->library->id, 'recordid' => $w->record->id,
            'readerid' => $w->reader->id, 'status' => 'ready', 'timecreated' => $w->now - DAYSECS, 'timemodified' => $w->now,
            'timeready' => $w->now - HOURSECS, 'timeexpires' => $w->now + 3 * DAYSECS]);
        $card = mine::child_cards((int) $w->child->id, $w->now)[0];
        $row = $card['items'][0];
        $this->assertSame(get_string('mobilechildpickup', 'local_oksigeniaayvurenda'), $row['title']);
        $this->assertTrue($row['pickup']);
        $this->assertTrue($row['plain']);
        $this->assertStringStartsWith($start('mobilechildready'), $row['detail']);
        $this->assertStringNotContainsString('faro', json_encode($card, JSON_UNESCAPED_UNICODE));
        $this->assertStringStartsWith($start('mobilechildfacetready'), $card['facet']['text']);
        $this->assertFalse($card['facet']['urgent']);
    }

    /**
     * The card leads to the child's page; an adult who is not of the child's family, as the family record says, gets
     * neither the card nor the page.
     */
    public function test_only_the_family(): void {
        $w = $this->world('Primaria');
        $card = mine::child_cards((int) $w->child->id, $w->now)[0];
        $this->assertSame('mobile_child_view', $card['seeall']['method']);
        $this->assertSame(['child' => (int) $w->child->id], $card['seeall']['args']);
        $this->assertSame('fas-book-open-reader', $card['icon']);
        if (!\local_oksigeniaayvurenda\output\mobile::is_family((int) $w->adult->id, (int) $w->child->id)) {
            $this->assertSame([], local_oksigeniaayvurenda_oksigeniaaty_child_cards((int) $w->adult->id, (int) $w->child->id));
            $this->setUser($w->adult);
            $page = \local_oksigeniaayvurenda\output\mobile::mobile_child_view(['child' => (int) $w->child->id]);
            $html = $page['templates'][0]['html'];
            $this->assertStringContainsString(s(get_string('mobilechildrefused', 'local_oksigeniaayvurenda')), $html);
            $this->assertStringNotContainsString('faro', $html);
        }
    }

    /**
     * The child's page: up to the flower, the books with their titles and covers and what they read this year; from the
     * sky, a book and its date, and why not which one.
     */
    public function test_the_child_page(): void {
        global $DB;
        $w = $this->world('Primaria');
        $this->assertTrue(covers::store((int) $w->record->id, self::jpeg(), 'test'));
        $year = years::around((int) $w->library->entityid, $w->now);
        $read = $w->gen->create_record(['entityid' => $w->library->entityid, 'title' => 'La isla del cometa']);
        $item = $w->gen->create_item(['libraryid' => $w->library->id, 'recordid' => $read->id, 'barcode' => '100002L']);
        foreach ([20, 10] as $ago) {
            $DB->insert_record(self::T . 'loan', ['libraryid' => $w->library->id, 'itemid' => $item->id,
                'readerid' => $w->reader->id, 'yearid' => $year->id, 'timeloaned' => $w->now - $ago * DAYSECS,
                'timedue' => $w->now - ($ago - 7) * DAYSECS, 'timereturned' => $w->now - ($ago - 5) * DAYSECS, 'renewals' => 0,
                'timecreated' => $w->now, 'timemodified' => $w->now]);
        }
        $page = mine::child_page((int) $w->child->id, $w->now)[0];
        $this->assertTrue($page['young']);
        $this->assertSame('El faro secreto', $page['books'][0]['title']);
        $this->assertTrue($page['books'][0]['hascover']);
        $this->assertTrue($page['books'][0]['opens']);
        $this->assertCount(1, $page['read'], 'a book read twice counts once');
        $this->assertSame('La isla del cometa', $page['read'][0]['title']);
        $this->assertSame(get_string('mobilechildreadone', 'local_oksigeniaayvurenda'), $page['readline']);

        $w = $this->world('Secundaria');
        $this->assertTrue(covers::store((int) $w->record->id, self::jpeg(), 'test'));
        $page = mine::child_page((int) $w->child->id, $w->now)[0];
        $this->assertFalse($page['young']);
        $this->assertSame(get_string('mobilechildbook', 'local_oksigeniaayvurenda'), $page['books'][0]['title']);
        $this->assertFalse($page['books'][0]['hascover']);
        $this->assertFalse($page['books'][0]['opens']);
        $this->assertSame([], $page['read']);
        $this->assertStringNotContainsString('faro', json_encode($page, JSON_UNESCAPED_UNICODE));
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

    /**
     * A small JPEG, as a cover.
     *
     * @return string
     */
    private static function jpeg(): string {
        $image = imagecreatetruecolor(20, 30);
        ob_start();
        imagejpeg($image);
        return (string) ob_get_clean();
    }
}
