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
 * The subjects of a centre as one vocabulary.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\subjects
 */
#[\PHPUnit\Framework\Attributes\CoversClass(subjects::class)]
final class subjects_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * A record of the centre with these subjects.
     *
     * @param \stdClass $library
     * @param string $title
     * @param string[] $subjects
     * @return int
     */
    private function book(\stdClass $library, string $title, array $subjects): int {
        $description = ['record' => ['title' => $title], 'authorities' => ['subject' => $subjects]];
        return (int) cataloguer::add($library, $description, ['count' => 1])['recordid'];
    }

    /**
     * Capitals, accents and number make no other subject: «misterios» is «Misterio», «Animales» is «animal».
     */
    public function test_same_word(): void {
        global $DB;
        $this->resetAfterTest();
        $this->assertSame(subjects::key('Misterio'), subjects::key('misterios'));
        $this->assertSame(subjects::key('Animales'), subjects::key('animal'));
        $this->assertSame(subjects::key('Ciencias naturales'), subjects::key('ciencia natural'));
        $this->assertNotSame(subjects::key('Misterio'), subjects::key('Historia'));
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $one = $this->book($library, 'El faro dormido', ['Misterio', 'Aventuras']);
        $two = $this->book($library, 'La isla', ['misterios', 'aventura']);
        $this->assertSame(2, $DB->count_records(self::T . 'authority', ['entityid' => $library->entityid, 'kind' => 'subject']));
        $ids = fn(int $record) => array_map('intval', $DB->get_fieldset_select(
            self::T . 'recauth',
            'authorityid',
            'recordid = ?',
            [$record]
        ));
        $this->assertEqualsCanonicalizing($ids($one), $ids($two));
    }

    /**
     * What starts like what is typed, the most used first; with a letter wrong, the near ones; joining moves the books and
     * takes the others away, also from what the search finds.
     */
    public function test_suggest_alike_join_rename(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $entityid = (int) $library->entityid;
        $this->book($library, 'Uno', ['Misterio']);
        $this->book($library, 'Dos', ['Misterio']);
        $three = $this->book($library, 'Tres', ['Mistrios']);
        $this->book($library, 'Cuatro', ['Mitología']);
        // Subjects that are figures (the classroom library has years): never a typo of each other, and no fall.
        $this->book($library, 'Seis', ['1914']);
        $this->book($library, 'Siete', ['1915']);
        $this->book($library, 'Ocho', ['Siglo XX']);
        $this->book($library, 'Nueve', ['Siglo XI']);
        $five = $this->book($library, 'Cinco', ['Mitsério']);
        $this->assertSame(['Misterio'], array_column(subjects::suggest($entityid, 'mister'), 'label'));
        $this->assertSame(2, subjects::suggest($entityid, 'mister')[0]['count']);
        $this->assertContains('Misterio', array_column(subjects::suggest($entityid, 'misteiro'), 'label'));

        $groups = subjects::alike($entityid);
        $this->assertCount(1, $groups);
        $this->assertSame(['Misterio', 'Mistrios', 'Mitsério'], array_column($groups[0], 'label'));

        $keep = (int) $groups[0][0]['id'];
        $this->assertSame(2, subjects::join($entityid, $keep, array_column($groups[0], 'id')));
        $this->assertNotEmpty($five);
        $this->assertSame([$keep], array_map('intval', $DB->get_fieldset_select(
            self::T . 'recauth',
            'authorityid',
            'recordid = ?',
            [$three]
        )));
        $this->assertStringContainsString('misterio', $DB->get_field(self::T . 'record', 'searchtext', ['id' => $three]));
        $this->assertStringNotContainsString('mistrios', $DB->get_field(self::T . 'record', 'searchtext', ['id' => $three]));
        $this->assertSame([], subjects::alike($entityid));

        // A new name that the centre already has makes them one.
        $myth = (int) $DB->get_field(self::T . 'authority', 'id', ['entityid' => $entityid, 'label' => 'Mitología']);
        $this->assertSame($keep, subjects::rename($entityid, $myth, 'misterios'));
        $this->assertFalse($DB->record_exists(self::T . 'authority', ['id' => $myth]));
        $this->assertSame('misterios', $DB->get_field(self::T . 'authority', 'label', ['id' => $keep]));
        $this->assertSame($keep, subjects::rename($entityid, $keep, 'Misterio'));

        // Another centre's subject is never joined.
        $other = $gen->create_library();
        $theirs = $this->book($other, 'Ajeno', ['Misterio']);
        $theirid = (int) $DB->get_field(self::T . 'recauth', 'authorityid', ['recordid' => $theirs]);
        $this->assertSame(0, subjects::join($entityid, $keep, [$theirid]));
        $this->assertTrue($DB->record_exists(self::T . 'authority', ['id' => $theirid]));
    }
}
