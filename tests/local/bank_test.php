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
 * Tests for the textbook bank.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\bank
 */
#[\PHPUnit\Framework\Attributes\CoversClass(bank::class)]
final class bank_test extends \advanced_testcase {
    /**
     * A set for the first grade: a pupil of its group sees what she is still to get, takes her books until the year
     * ends, with no limit of copies and their state noted, and the family's contract lists them; a pupil of another
     * group has no set; what is short counts the broken copy out.
     */
    public function test_bank(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $central = $gen->create_library(['slug' => 'central']);
        $entityid = (int) $central->entityid;
        $bank = libraries::create_library($entityid, ['name' => 'Banco de libros', 'kind' => 'bank']);
        $this->assertTrue(bank::is($bank));
        $this->assertFalse(bank::is($central));
        $now = time();
        $year = \local_oksigeniaayvurenda\core\school_year::around($now, '09-01', \core_date::get_server_timezone_object());
        $year = $gen->create_year(['entityid' => $entityid] + $year);
        $ana = $gen->create_reader(['entityid' => $entityid, 'cardcode' => '10000H', 'firstname' => 'Ana',
            'lastname' => 'Inventada']);
        $leo = $gen->create_reader(['entityid' => $entityid, 'cardcode' => '10001L', 'firstname' => 'Leo',
            'lastname' => 'Inventado']);
        $gen->create_member(['readerid' => $ana->id, 'yearid' => $year->id, 'groupname' => '1º ESO A']);
        $gen->create_member(['readerid' => $leo->id, 'yearid' => $year->id, 'groupname' => '2º ESO B']);
        $maths = $gen->create_record(['entityid' => $entityid, 'title' => 'Matemáticas 1']);
        $language = $gen->create_record(['entityid' => $entityid, 'title' => 'Lengua 1']);
        $gen->create_item(['libraryid' => $bank->id, 'recordid' => $maths->id, 'barcode' => '500001K']);
        $gen->create_item(['libraryid' => $bank->id, 'recordid' => $maths->id, 'barcode' => '500002E',
            'itemcondition' => 'broken']);
        $gen->create_item(['libraryid' => $bank->id, 'recordid' => $language->id, 'barcode' => '500003T']);

        $setid = bank::save_set($bank, '1.º de ESO', ['1º ESO A', ' ']);
        $this->assertTrue(bank::add_title($bank, $setid, (int) $maths->id));
        $this->assertTrue(bank::add_title($bank, $setid, (int) $language->id));
        $this->assertFalse(bank::add_title($bank, $setid, (int) $maths->id));
        $this->assertSame(['1º ESO A'], bank::sets($bank)[0]['groups']);
        $this->assertSame(2, bank::of_reader($bank, (int) $ana->id, $now)['missing']);
        $this->assertNull(bank::of_reader($bank, (int) $leo->id, $now));

        $desk = new desk($bank, 2, $now);
        $panel = $desk->scan('10000H')['reader'];
        $this->assertSame('1.º de ESO', $panel['bank']['set']);
        $this->assertSame(['Matemáticas 1', 'Lengua 1'], $panel['bank']['pending']);
        $this->assertStringContainsString('contract.php', $panel['bank']['contract']);
        $this->assertSame('lent', $desk->scan('500001K', (int) $ana->id)['kind']);
        $this->assertSame('lent', $desk->scan('500003T', (int) $ana->id)['kind']);
        $loan = $DB->get_record('local_oksigeniaayvurenda_loan', ['readerid' => $ana->id, 'itemid' =>
            $DB->get_field('local_oksigeniaayvurenda_item', 'id', ['barcode' => '500001K'])], '*', MUST_EXIST);
        $this->assertSame((int) $year->timeend - 1, (int) $loan->timedue);
        $this->assertSame('good', $loan->conditionout);
        $this->assertSame(0, bank::of_reader($bank, (int) $ana->id, $now)['missing']);

        $needs = bank::needs($bank, $now)[0];
        $this->assertSame(1, $needs['pupils']);
        $this->assertSame([1, 0, 0], [$needs['titles'][0]['out'], $needs['titles'][0]['free'], $needs['titles'][0]['short']]);

        $contract = bank::contract($bank, (int) $ana->id, $now);
        $this->assertSame('Ana Inventada', $contract['name']);
        $this->assertSame('1º ESO A', $contract['group']);
        $this->assertSame(['Lengua 1', 'Matemáticas 1'], array_column($contract['copies'], 'title'));

        bank::remove_set($bank, $setid);
        $this->assertSame([], bank::sets($bank));
    }
}
