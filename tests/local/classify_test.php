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
 * The class while it is typed.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\classify
 */
#[\PHPUnit\Framework\Attributes\CoversClass(classify::class)]
final class classify_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * An authority of a centre.
     *
     * @param int $entityid
     * @param string $kind
     * @param string $label
     * @param string|null $code
     * @return int
     */
    private function authority(int $entityid, string $kind, string $label, ?string $code = null): int {
        global $DB;
        return (int) $DB->insert_record(self::T . 'authority', ['entityid' => $entityid, 'kind' => $kind, 'code' => $code,
            'label' => $label, 'timecreated' => time(), 'timemodified' => time()]);
    }

    /**
     * A word brings the classes whose name has it, with a letter wrong too; a number those that start with it; a subject
     * brings what the library gave to its books; a centre with nothing of its own has the main classes.
     */
    public function test_suggest(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $entityid = (int) $library->entityid;
        $this->authority($entityid, 'class', 'BIOLOGÍA.', '57');
        $this->authority($entityid, 'class', 'Biología general. Biología teórica', '573');
        $this->authority($entityid, 'class', 'Zoología', '59');
        $this->authority($entityid, 'class', 'Literatura', '82');
        $this->authority($entityid, 'class', '821', '821');
        $mystery = $this->authority($entityid, 'subject', 'Misterio');
        $animals = $this->authority($entityid, 'subject', 'Animales');
        $book = function (string $call, int $subject) use ($gen, $library, $entityid, $DB) {
            $record = $gen->create_record(['entityid' => $entityid]);
            $gen->create_item(['libraryid' => $library->id, 'recordid' => $record->id, 'callnumber1' => $call]);
            $DB->insert_record(self::T . 'recauth', ['recordid' => $record->id, 'authorityid' => $subject, 'sortorder' => 0]);
        };
        $book('82-312.4', $mystery);
        $book('82-312.4', $mystery);
        $book('N', $mystery);
        $book('N', $mystery);
        $book('59', $animals);
        $book('59', $animals);
        $book('57', $animals);

        $codes = fn(array $list) => array_column($list, 'code');
        $biology = classify::suggest($library, 'biologia');
        $this->assertSame(['57', '573'], $codes($biology));
        $this->assertSame('Biología', $biology[0]['label']);
        $this->assertSame(1, $biology[0]['count']);
        $this->assertSame('57', classify::suggest($library, 'biolojia')[0]['code']);
        $this->assertSame(['57', '573'], $codes(classify::suggest($library, '57')));
        // The main class in the plugin's words; a class named only by its number («821») tells nothing.
        $this->assertSame(['8', '82'], $codes(classify::suggest($library, '8')));

        // What the library gave to books on that subject, never a section such as «N».
        $mystery = classify::suggest($library, 'misterio');
        $this->assertSame('82-312.4', $mystery[0]['code']);
        $this->assertSame(2, $mystery[0]['used']);
        $this->assertSame('Literatura', $mystery[0]['label']);
        $this->assertNotContains('N', $codes($mystery));
        $animals = classify::suggest($library, 'animales');
        $this->assertSame(['59'], array_slice($codes($animals), 0, 1));
        $this->assertSame([], classify::suggest($library, ''));

        // Another centre has nothing of its own: the main classes (the tests speak English), and none of the first one's.
        $other = $gen->create_library();
        $sciences = $codes(classify::suggest($other, 'sciences'));
        $this->assertContains('5', $sciences);
        $this->assertContains('6', $sciences);
        $this->assertNotContains('57', $codes(classify::suggest($other, 'biologia')));
    }
}
