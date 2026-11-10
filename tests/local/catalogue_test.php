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
 * Tests for looking up the catalogue.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\catalogue
 */
#[\PHPUnit\Framework\Attributes\CoversClass(catalogue::class)]
final class catalogue_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * Two libraries of one entity, three books with authors and classes, copies in each.
     *
     * @return \stdClass
     */
    private function make_world(): \stdClass {
        global $DB;
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $w = new \stdClass();
        $w->central = $gen->create_library(['slug' => 'central']);
        $w->classroom = $gen->create_library(['entityid' => $w->central->entityid, 'slug' => 'aula-3a', 'kind' => 'classroom']);
        $now = time();
        $author = $DB->insert_record(self::T . 'authority', ['entityid' => $w->central->entityid, 'kind' => 'author',
            'label' => 'Montes, Ana', 'timecreated' => $now, 'timemodified' => $now]);
        $novel = $DB->insert_record(self::T . 'authority', ['entityid' => $w->central->entityid, 'kind' => 'class',
            'code' => '821.134.2-3', 'label' => 'Novela', 'timecreated' => $now, 'timemodified' => $now]);
        $science = $DB->insert_record(self::T . 'authority', ['entityid' => $w->central->entityid, 'kind' => 'class',
            'code' => '5', 'label' => 'Ciencias', 'timecreated' => $now, 'timemodified' => $now]);
        $shelf = $DB->insert_record(self::T . 'authority', ['entityid' => $w->central->entityid, 'kind' => 'location',
            'label' => 'Estantería infantil', 'timecreated' => $now, 'timemodified' => $now]);
        $books = [
            ['El faro dormido', 'el faro dormido ana montes aventuras', $novel],
            ['El volcán despierto', 'el volcan despierto ana montes ciencia', $science],
            ['Faros del mundo', 'faros del mundo geografia', $science],
        ];
        $w->records = [];
        foreach ($books as [$title, $searchtext, $class]) {
            $record = $gen->create_record(['entityid' => $w->central->entityid, 'title' => $title, 'searchtext' => $searchtext]);
            $DB->insert_record(self::T . 'recauth', ['recordid' => $record->id, 'authorityid' => $author, 'role' => 'author']);
            $DB->insert_record(self::T . 'recauth', ['recordid' => $record->id, 'authorityid' => $class, 'sortorder' => 1]);
            $w->records[] = $record;
        }
        $gen->create_item(['libraryid' => $w->central->id, 'recordid' => $w->records[0]->id, 'locationid' => $shelf]);
        $gen->create_item(['libraryid' => $w->central->id, 'recordid' => $w->records[1]->id, 'status' => 'onloan']);
        $gen->create_item(['libraryid' => $w->classroom->id, 'recordid' => $w->records[2]->id]);
        $gen->create_item(['libraryid' => $w->central->id, 'recordid' => $w->records[2]->id, 'itemtype' => 'reference']);
        return $w;
    }

    /**
     * With more matches than are scored, the title that starts with what was typed is never left out.
     */
    public function test_search_keeps_the_best_of_many(): void {
        global $DB;
        $this->resetAfterTest();
        // At the desk: records without copies are found (a reader finds only what can be borrowed or read).
        $this->setAdminUser();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $now = time();
        $many = (new \ReflectionClassConstant(catalogue::class, 'CANDIDATES'))->getValue() + 50;
        $rows = [];
        for ($i = 0; $i < $many; $i++) {
            $rows[] = ['entityid' => $library->entityid, 'doctype' => 'book', 'title' => 'Cuaderno de agua ' . $i,
                'searchtext' => 'cuaderno de agua ' . $i, 'timecreated' => $now, 'timemodified' => $now];
        }
        $DB->insert_records('local_oksigeniaayvurenda_record', $rows);
        $gen->create_record(['entityid' => $library->entityid, 'title' => 'El agua clara', 'searchtext' => 'el agua clara']);
        $found = catalogue::search($library, 'agua');
        $this->assertSame($many + 1, $found['total']);
        $this->assertSame('El agua clara', $found['cards'][0]['title']);
    }

    /**
     * Every word has to be there, as the start of a word, without accents; the title counts most.
     */
    public function test_search(): void {
        $this->resetAfterTest();
        $w = $this->make_world();
        $found = catalogue::search($w->central, 'faro');
        $this->assertSame(2, $found['total']);
        $this->assertSame('El faro dormido', $found['cards'][0]['title']);
        $this->assertSame(1, catalogue::search($w->central, 'Volcán ANA')['total']);
        $this->assertSame(2, catalogue::search($w->central, 'montes')['total']);
        $this->assertSame(0, catalogue::search($w->central, 'aro')['total']);
        $this->assertSame(0, catalogue::search($w->central, '   ')['total']);
    }

    /**
     * Only the free ones: records with a copy to take home from this library.
     */
    public function test_only_free(): void {
        $this->resetAfterTest();
        $w = $this->make_world();
        $this->assertSame(2, catalogue::search($w->central, 'montes')['total']);
        $free = catalogue::search($w->central, 'montes', 0, true);
        $this->assertSame(1, $free['total']);
        $this->assertSame('El faro dormido', $free['cards'][0]['title']);
        // A plural finds the singular too: the free one here is «El faro dormido».
        $this->assertSame(['El faro dormido'], array_column(catalogue::search($w->central, 'faros', 0, true)['cards'], 'title'));
        $this->assertSame(1, catalogue::search($w->classroom, 'faros', 0, true)['total']);
        $this->assertSame(0, catalogue::corner($w->central, '5', 0, true)['total']);
    }

    /**
     * A search that finds nothing suggests a way to write it that finds something.
     */
    public function test_suggestions(): void {
        $this->resetAfterTest();
        $w = $this->make_world();
        $found = catalogue::search($w->central, 'bolcan');
        $this->assertSame(0, $found['total']);
        $this->assertSame(['volcan'], $found['suggestions']);
    }

    /**
     * The search page shows the closest words at once, with the words as typed one link away.
     */
    public function test_results_closest(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $w = $this->make_world();
        $data = screens::results($w->central, 'bolcan', '', 0);
        $this->assertSame('volcan', $data['searched']);
        $this->assertTrue($data['hascards']);
        $this->assertStringContainsString('«bolcan»', $data['corrected']['correctedtext']);
        $this->assertStringContainsString('asis=1', $data['corrected']['asisurl']);
        $this->assertNull($data['memory']);
        $asis = screens::results($w->central, 'bolcan', '', 0, false, 0, true);
        $this->assertFalse($asis['hascards']);
        $this->assertNull($asis['corrected']);
        $this->assertSame('volcan', $asis['suggestions'][0]['text']);
    }

    /**
     * Cards tell, for the library the reader is in, whether the book is free and where, out, only to read there, or
     * kept in another library of the entity.
     */
    public function test_cards(): void {
        $this->resetAfterTest();
        $w = $this->make_world();
        $cards = [];
        foreach (catalogue::search($w->central, 'el')['cards'] as $card) {
            $cards[$card['title']] = $card;
        }
        $this->assertSame('free', $cards['El faro dormido']['state']);
        $this->assertSame('Estantería infantil', $cards['El faro dormido']['location']);
        $this->assertSame('Ana Montes', $cards['El faro dormido']['authors']);
        $this->assertSame('out', $cards['El volcán despierto']['state']);
        $faros = catalogue::search($w->central, 'faros')['cards'][0];
        $this->assertSame('consult', $faros['state']);
        $this->assertSame('free', catalogue::search($w->classroom, 'faros')['cards'][0]['state']);
        $this->assertSame('elsewhere', catalogue::search($w->classroom, 'faro dormido')['cards'][0]['state']);
    }

    /**
     * Corners are the top classes with their books; a corner lists its books. With the UDC they take the plugin's short
     * names; with another classification, the catalogue's, tidied.
     */
    public function test_corners(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_world();
        $corners = catalogue::corners($w->central);
        $this->assertSame(['5', '8'], array_column($corners, 'code'));
        $this->assertSame(get_string('udc5', 'local_oksigeniaayvurenda'), $corners[0]['label']);
        $this->assertSame(2, $corners[0]['count']);
        $this->assertSame(get_string('udc8', 'local_oksigeniaayvurenda'), $corners[1]['label']);
        $this->assertSame(2, catalogue::corner($w->central, '5')['total']);
        $DB->set_field(
            'local_oksigeniaayvurenda_entity',
            'settings',
            json_encode(['classification' => 'other']),
            ['id' => $w->central->entityid]
        );
        $this->assertSame('Ciencias', catalogue::corners($w->central)[0]['label']);
    }

    /**
     * A record shows its authors and its copies, those of this library first.
     */
    public function test_record(): void {
        $this->resetAfterTest();
        $w = $this->make_world();
        $found = catalogue::record($w->records[2]->id, $w->classroom);
        $this->assertSame('Ana Montes', $found['authorities']['author'][0]['label']);
        $this->assertCount(2, $found['copies']);
        $this->assertTrue($found['copies'][0]['here']);
        $this->assertSame('free', $found['copies'][0]['state']);
        $this->assertSame('consult', $found['copies'][1]['state']);
        $this->assertNull(catalogue::record($w->records[2]->id + 1000, $w->classroom));
    }

    /**
     * What came in last, and the library found by its short name or alone.
     */
    public function test_recent_and_library(): void {
        $this->resetAfterTest();
        $w = $this->make_world();
        $this->assertCount(3, catalogue::recent($w->central));
        $this->assertSame((int) $w->classroom->id, (int) catalogue::library('aula-3a')->id);
        $this->assertNull(catalogue::library(''));
        $this->assertSame('Ana Montes', catalogue::display_name('Montes, Ana'));
        $this->assertSame('Ediciones Colibrí', catalogue::display_name('Ediciones Colibrí'));
    }

    /**
     * An ISBN typed or read in the search box finds its book, with hyphens or without, in 10 or 13 figures.
     */
    public function test_search_by_isbn(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $record = $gen->create_record(['entityid' => $library->entityid, 'title' => 'El gran Gatsby', 'isbn' => '9780743273565',
            'searchtext' => 'el gran gatsby']);
        $gen->create_item(['libraryid' => $library->id, 'recordid' => $record->id]);
        foreach (['9780743273565', '978-0-7432-7356-5', '0743273567'] as $typed) {
            $found = catalogue::search($library, $typed);
            $this->assertSame(1, $found['total'], $typed);
        }
        $this->assertSame(0, catalogue::search($library, '9780306406157')['total']);
    }

    /**
     * A record whose only copy was withdrawn is no longer found by a reader; the staff still find it.
     */
    public function test_withdrawn_records(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_world();
        $DB->set_field(self::T . 'item', 'status', 'withdrawn', ['recordid' => $w->records[1]->id]);
        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertSame(0, catalogue::search($w->central, 'volcan')['total']);
        $this->setAdminUser();
        $this->assertSame(1, catalogue::search($w->central, 'volcan')['total']);
    }

    /**
     * What a child writes: a plural finds the singular, and «un libro de…» finds what follows.
     */
    public function test_child_search(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $entityid = (int) $library->entityid;
        cataloguer::create_record($entityid, ['record' => ['title' => 'El dragón antiguo']], time());
        cataloguer::create_record($entityid, ['record' => ['title' => 'La pirata y el pez']], time());
        $this->assertSame(1, catalogue::search($library, 'dragones')['total']);
        $this->assertSame(1, catalogue::search($library, 'un libro de dragones')['total']);
        $this->assertSame(1, catalogue::search($library, 'piratas')['total']);
        $this->assertSame(1, catalogue::search($library, 'peces')['total']);
        $this->assertSame(0, catalogue::search($library, 'caballos')['total']);
    }
}
