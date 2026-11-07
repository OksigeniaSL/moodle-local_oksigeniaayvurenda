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
 * Records of the same book, found and joined.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\duplicates
 */
#[\PHPUnit\Framework\Attributes\CoversClass(duplicates::class)]
final class duplicates_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * A record of the library's centre.
     *
     * @param \stdClass $library
     * @param string $title
     * @param string $isbn
     * @param string[] $authors
     * @param string $summary
     * @return int
     */
    private function book(\stdClass $library, string $title, string $isbn, array $authors, string $summary = ''): int {
        return cataloguer::create_record((int) $library->entityid, ['record' => ['title' => $title, 'isbn' => $isbn ?: null,
            'summary' => $summary ?: null], 'authorities' => ['author' => $authors]], time());
    }

    /**
     * The same ISBN, or the same title and author with one without ISBN, are offered; other editions are not; what
     * someone said is not the same is not offered again.
     */
    public function test_groups(): void {
        $this->resetAfterTest();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $entityid = (int) $library->entityid;
        $a = $this->book($library, 'El faro dormido', '9780306406157', ['Montes, Ana']);
        $b = $this->book($library, 'El faro dormido', '9780306406157', ['Ana Montes']);
        $c = $this->book($library, 'La isla', '', ['Prado, Luis']);
        $d = $this->book($library, 'la ISLA', '9781861972712', ['Luis Prado']);
        $this->book($library, 'Poesía', '9788498798708', ['Ríos, Eva']);
        $this->book($library, 'Poesía', '9780743273565', ['Ríos, Eva']);
        $this->book($library, 'La isla', '', ['Otro, Autor']);
        $groups = array_map(function ($ids) {
            sort($ids);
            return $ids;
        }, duplicates::groups($entityid));
        $this->assertCount(2, $groups);
        $this->assertContains([$a, $b], $groups);
        $this->assertContains([$c, $d], $groups);
        duplicates::apart($entityid, [$c, $d]);
        $this->assertSame([[$a, $b]], array_map(function ($ids) {
            sort($ids);
            return $ids;
        }, duplicates::groups($entityid)));
    }

    /**
     * Joining: the copies (with their loans), reviews, readings and subjects go to the one that stays, which takes the
     * summary it lacked and the link to the program it came from; the other goes; another centre is never touched.
     */
    public function test_join(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $entityid = (int) $library->entityid;
        $keep = $this->book($library, 'El faro dormido', '9780306406157', ['Montes, Ana']);
        $gone = $this->book($library, 'El faro dormido', '', ['Montes, Ana'], 'Un faro que duerme.');
        $item = $gen->create_item(['libraryid' => $library->id, 'recordid' => $gone]);
        $gen->create_item(['libraryid' => $library->id, 'recordid' => $keep]);
        $reader = $gen->create_reader(['entityid' => $entityid]);
        $loan = $gen->create_loan(['libraryid' => $library->id, 'itemid' => $item->id, 'readerid' => $reader->id]);
        reviews::write($entityid, $gone, (int) $reader->id, 'Me gustó.');
        reviews::write($entityid, $keep, (int) $reader->id, 'Muy bueno.');
        $other = $gen->create_reader(['entityid' => $entityid]);
        reviews::write($entityid, $gone, (int) $other->id, 'Bonito.');
        $now = time();
        $DB->insert_record(self::T . 'source', ['entityid' => $entityid, 'origin' => 'abies', 'tablename' => 'record',
            'externalid' => '55', 'localid' => $gone, 'hash' => sha1('x'), 'timecreated' => $now, 'timemodified' => $now]);
        $elsewhere = $gen->create_library();
        $theirs = $this->book($elsewhere, 'El faro dormido', '9780306406157', ['Montes, Ana']);

        $this->assertSame(0, duplicates::join($entityid, $keep, [$theirs]));
        $this->assertTrue($DB->record_exists(self::T . 'record', ['id' => $theirs]));
        $this->assertSame(1, duplicates::join($entityid, $keep, [$gone, $keep]));
        $this->assertFalse($DB->record_exists(self::T . 'record', ['id' => $gone]));
        $this->assertEquals($keep, $DB->get_field(self::T . 'item', 'recordid', ['id' => $item->id]));
        $this->assertEquals($item->id, $DB->get_field(self::T . 'loan', 'itemid', ['id' => $loan->id]));
        $this->assertSame(2, $DB->count_records(self::T . 'item', ['recordid' => $keep]));
        $this->assertSame(2, $DB->count_records(self::T . 'review', ['recordid' => $keep]));
        $this->assertSame('Muy bueno.', $DB->get_field(self::T . 'review', 'text', ['recordid' => $keep,
            'readerid' => $reader->id]));
        $this->assertSame(1, $DB->count_records(self::T . 'recauth', ['recordid' => $keep]));
        $this->assertSame('Un faro que duerme.', $DB->get_field(self::T . 'record', 'summary', ['id' => $keep]));
        $this->assertSame('9780306406157', $DB->get_field(self::T . 'record', 'isbn', ['id' => $keep]));
        $this->assertEquals($keep, $DB->get_field(self::T . 'source', 'localid', ['externalid' => '55']));
        $this->assertSame([], duplicates::groups($entityid));
    }
}
