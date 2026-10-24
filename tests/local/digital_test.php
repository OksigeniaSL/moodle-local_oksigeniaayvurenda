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
 * Tests for the digital collection.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\digital
 */
#[\PHPUnit\Framework\Attributes\CoversClass(digital::class)]
final class digital_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * A file and a link on a record; who is who; who sees what; the licence that keeps a work inside; where each one is
     * in it; and removing it with its file.
     */
    public function test_digital(): void {
        global $DB;
        $this->resetAfterTest();
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $entityid = (int) $library->entityid;
        $record = $gen->create_record(['entityid' => $entityid, 'title' => 'El faro dormido']);
        $year = $gen->create_year(['entityid' => $entityid] + \local_oksigeniaayvurenda\core\school_year::around(
            time(),
            '09-01',
            \core_date::get_server_timezone_object()
        ));
        $student = $this->getDataGenerator()->create_user();
        $family = $this->getDataGenerator()->create_user();
        $stranger = $this->getDataGenerator()->create_user();
        $reader = $gen->create_reader(['entityid' => $entityid, 'userid' => $student->id]);
        $gen->create_member(['readerid' => $reader->id, 'yearid' => $year->id, 'stage' => 'Primaria']);
        $gen->create_reader(['entityid' => $entityid, 'userid' => $family->id, 'readertype' => 'family']);

        $this->assertSame(['who' => 'students', 'stage' => 'Primaria'], digital::viewer($entityid, (int) $student->id));
        $this->assertSame('families', digital::viewer($entityid, (int) $family->id)['who']);
        $this->assertSame('nobody', digital::viewer($entityid, (int) $stranger->id)['who']);
        $this->assertSame('desk', digital::viewer($entityid, 2)['who']);

        $path = make_request_directory() . '/faro.epub';
        file_put_contents($path, 'made-up epub');
        $book = digital::add_file($library, (int) $record->id, $path, 'faro.epub', ['licence' => 'rights',
            'audience' => ['students', 'public'], 'stages' => ['Primaria'], 'download' => 1], 2);
        $item = digital::get($book, $library);
        $this->assertSame('epub', $item->format);
        $this->assertSame('students', $item->audience, 'someone else\'s rights: never public');
        $this->assertEquals(0, $item->download, 'nor downloaded');
        $this->assertNotNull(digital::file($item));
        $this->assertStringContainsString('/digital/' . $book . '/faro.epub', digital::file_url($item));

        $link = digital::add_link($library, (int) $record->id, 'https://example.org/faro', ['licence' => 'pd',
            'audience' => ['public'], 'download' => 1], 2);
        $see = fn($user) => array_map(fn($i) => (int) $i->id, digital::of_record(
            (int) $record->id,
            $library,
            digital::viewer($entityid, (int) $user->id)
        ));
        $this->assertSame([$book, $link], $see($student));
        $this->assertSame([$link], $see($family));
        $this->assertSame([$link], $see($stranger));

        digital::update($book, $library, ['licence' => 'cc-by-sa', 'audience' => ['students', 'families'], 'download' => 1]);
        $this->assertSame([$book, $link], $see($family));
        $this->assertEquals(1, digital::get($book, $library)->download);

        digital::keep_place($book, (int) $student->id, 'epubcfi(/6/4)', null);
        digital::keep_place($book, (int) $student->id, null, [['place' => 'epubcfi(/6/8)', 'label' => 'Marca 1']]);
        $this->assertSame(
            ['position' => 'epubcfi(/6/4)', 'bookmarks' => [['place' => 'epubcfi(/6/8)', 'label' => 'Marca 1']]],
            digital::place($book, (int) $student->id)
        );

        digital::delete($book, $library);
        $this->assertFalse($DB->record_exists(self::T . 'digital', ['id' => $book]));
        $this->assertFalse($DB->record_exists(self::T . 'digitalread', ['digitalid' => $book]));
        $this->assertSame([], get_file_storage()->get_area_files(
            \context_system::instance()->id,
            'local_oksigeniaayvurenda',
            'digital',
            $book,
            'id',
            false
        ));
    }
}
