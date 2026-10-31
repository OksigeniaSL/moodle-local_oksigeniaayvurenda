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

namespace local_oksigeniaayvurenda\output;

/**
 * Tests for the library in the Moodle app.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\output\mobile
 */
#[\PHPUnit\Framework\Attributes\CoversClass(mobile::class)]
final class mobile_test extends \advanced_testcase {
    /**
     * A reader gets their books, their card's code as letters, barcode and QR, and the ways in that leave the app;
     * someone who is no reader is told so.
     */
    public function test_library_view(): void {
        $this->resetAfterTest();
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $ana = $this->getDataGenerator()->create_user();
        $reader = $gen->create_reader(['entityid' => $library->entityid, 'userid' => $ana->id, 'readertype' => 'student',
            'cardcode' => '10000H']);
        $record = $gen->create_record(['entityid' => $library->entityid, 'title' => 'El faro dormido']);
        $item = $gen->create_item(['entityid' => $library->entityid, 'libraryid' => $library->id, 'recordid' => $record->id]);
        $gen->create_loan(['libraryid' => $library->id, 'itemid' => $item->id, 'readerid' => $reader->id]);

        $this->setUser($ana);
        $page = mobile::mobile_library_view(['userid' => $ana->id]);
        $html = $page['templates'][0]['html'];
        $this->assertStringContainsString('El faro dormido', $html);
        // Each book says until when.
        $this->assertStringContainsString(get_string('mobiledue', 'local_oksigeniaayvurenda', ''), $html);
        $this->assertStringContainsString('10000H', $html);
        $this->assertStringContainsString('data:image/svg+xml;base64,', $html);
        $this->assertStringContainsString('data:image/png;base64,', $html);
        $this->assertStringContainsString('core-link', $html);

        $this->setUser($this->getDataGenerator()->create_user());
        $html = mobile::mobile_library_view([])['templates'][0]['html'];
        $this->assertStringContainsString(get_string('mobilenotreader', 'local_oksigeniaayvurenda'), $html);
    }

    /**
     * In the app, a reader searches the catalogue and opens a book: whether it is free, its copies and, when it is out
     * and they may ask online, the button to ask for it, which the web service answers; asked, the page says so.
     */
    public function test_search_record_and_hold(): void {
        $this->resetAfterTest();
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $ana = $this->getDataGenerator()->create_user();
        $gen->create_reader(['entityid' => $library->entityid, 'userid' => $ana->id, 'readertype' => 'teacher']);
        $record = $gen->create_record(['entityid' => $library->entityid, 'title' => 'El faro dormido',
            'searchtext' => 'el faro dormido']);
        $gen->create_item(['libraryid' => $library->id, 'recordid' => $record->id, 'status' => 'onloan']);
        \local_oksigeniaayvurenda\local\catalogue::purge((int) $library->entityid);
        $this->setUser($ana);
        $search = mobile::mobile_search_view(['library' => $library->slug, 'q' => 'faro']);
        $this->assertStringContainsString('El faro dormido', $search['templates'][0]['html']);
        $this->assertStringContainsString('mobile_record_view', $search['templates'][0]['html']);
        $this->assertSame('faro', $search['otherdata']['q']);
        $html = mobile::mobile_record_view(['library' => $library->slug, 'id' => $record->id])['templates'][0]['html'];
        $this->assertStringNotContainsString('local_oksigeniaayvurenda_ask_hold', $html);
        \local_oksigeniaayvurenda\local\holds::save_settings((int) $library->entityid, ['online' => true]);
        $html = mobile::mobile_record_view(['library' => $library->slug, 'id' => $record->id])['templates'][0]['html'];
        $this->assertStringContainsString('local_oksigeniaayvurenda_ask_hold', $html);
        $answer = \local_oksigeniaayvurenda\external\ask_hold::execute($library->slug, (int) $record->id);
        $this->assertTrue($answer['ok']);
        $answer = \local_oksigeniaayvurenda\external\ask_hold::execute($library->slug, (int) $record->id);
        $this->assertFalse($answer['ok']);
        $this->assertSame(get_string('holdhas', 'local_oksigeniaayvurenda'), $answer['message']);
        $html = mobile::mobile_record_view(['library' => $library->slug, 'id' => $record->id])['templates'][0]['html'];
        $this->assertStringContainsString(get_string('holdminewaiting', 'local_oksigeniaayvurenda'), $html);
        // Someone who may not see the library is told they are no reader.
        $this->setUser($this->getDataGenerator()->create_user());
        $html = mobile::mobile_record_view(['library' => 'no-such-library', 'id' => $record->id])['templates'][0]['html'];
        $this->assertStringContainsString(get_string('mobilenotreader', 'local_oksigeniaayvurenda'), $html);
    }
}
