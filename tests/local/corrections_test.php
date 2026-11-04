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
 * Correcting what was catalogued.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\corrections
 */
#[\PHPUnit\Framework\Attributes\CoversClass(corrections::class)]
final class corrections_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * A record corrected is found by its new words, and its authors are the ones written.
     */
    public function test_record(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $record = $gen->create_record(['entityid' => $library->entityid, 'title' => 'El lazarilo']);
        $form = ['title' => 'El Lazarillo de Tormes', 'authors' => "Anónimo\nOtro, Autor\nAnónimo",
            'subjects' => 'Novela picaresca'];
        $this->assertTrue(corrections::record($library, (int) $record->id, $form));
        $saved = $DB->get_record(self::T . 'record', ['id' => $record->id]);
        $this->assertSame('El Lazarillo de Tormes', $saved->title);
        $this->assertStringContainsString('picaresca', $saved->searchtext);
        $this->assertStringContainsString('anonimo', $saved->searchtext);
        $this->assertSame("Anónimo\nOtro, Autor", corrections::record_form($saved)['authors']);
        $this->assertFalse(corrections::record($library, (int) $record->id, ['title' => ' ']));
        $other = $gen->create_library(['slug' => 'otra']);
        $this->assertFalse(corrections::record($other, (int) $record->id, ['title' => 'Ajeno']));
    }

    /**
     * A copy's code cannot be another's, and a copy of another library is not touched.
     */
    public function test_copy(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $record = $gen->create_record(['entityid' => $library->entityid, 'title' => 'Libro']);
        $one = $gen->create_item(['libraryid' => $library->id, 'recordid' => $record->id, 'barcode' => 'A1']);
        $gen->create_item(['libraryid' => $library->id, 'recordid' => $record->id, 'barcode' => 'A2']);
        $this->assertSame('correctcodetaken', corrections::copy($library, (int) $one->id, ['barcode' => 'A2']));
        $this->assertSame('correctnocode', corrections::copy($library, (int) $one->id, ['barcode' => '']));
        $this->assertSame('', corrections::copy($library, (int) $one->id, ['barcode' => 'B7', 'callnumber1' => '82',
            'callnumber2' => 'LAZ']));
        $saved = $DB->get_record(self::T . 'item', ['id' => $one->id]);
        $this->assertSame('B7', $saved->barcode);
        $this->assertNotEmpty($saved->shelfkey);
        $other = $gen->create_library(['slug' => 'otra']);
        $this->assertSame('recordnotfound', corrections::copy($other, (int) $one->id, ['barcode' => 'C1']));
    }

    /**
     * What was lent stays; what has nothing behind it goes.
     */
    public function test_remove(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $reader = $gen->create_reader(['entityid' => $library->entityid, 'cardcode' => 'C1']);
        $record = $gen->create_record(['entityid' => $library->entityid, 'title' => 'Libro']);
        $lent = $gen->create_item(['libraryid' => $library->id, 'recordid' => $record->id, 'barcode' => 'A1']);
        $spare = $gen->create_item(['libraryid' => $library->id, 'recordid' => $record->id, 'barcode' => 'A2']);
        $gen->create_loan(['libraryid' => $library->id, 'itemid' => $lent->id, 'readerid' => $reader->id]);
        $this->assertSame('correcthashistory', corrections::remove_copy($library, (int) $lent->id));
        $this->assertSame('correcthashistory', corrections::remove_record($library, (int) $record->id));
        $this->assertSame('', corrections::remove_copy($library, (int) $spare->id));
        $mistake = $gen->create_record(['entityid' => $library->entityid, 'title' => 'Error']);
        $gen->create_item(['libraryid' => $library->id, 'recordid' => $mistake->id, 'barcode' => 'A3']);
        $this->assertSame('', corrections::remove_record($library, (int) $mistake->id));
        $this->assertFalse($DB->record_exists(self::T . 'record', ['id' => $mistake->id]));
        $this->assertFalse($DB->record_exists(self::T . 'item', ['barcode' => 'A3']));
    }
}
