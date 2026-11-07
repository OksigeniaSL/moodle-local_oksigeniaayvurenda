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
     * The card's page: the reader's name and code, with their pictures, and nothing a template could run; for anyone
     * else, that they are not a reader.
     */
    public function test_card_view(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $user = $this->getDataGenerator()->create_user(['firstname' => 'Ana', 'lastname' => 'Duna {{ 7 }} @if']);
        $reader = $gen->create_reader(['entityid' => $library->entityid, 'userid' => $user->id]);
        $this->setUser($user);
        $html = mobile::mobile_card_view([])['templates'][0]['html'];
        $this->assertStringContainsString((string) $reader->cardcode, $html);
        $this->assertStringContainsString('data:image/png;base64', $html);
        $this->assertStringContainsString('background: #fff', $html);
        $this->assertStringContainsString('Ana Duna', $html);
        $this->assertStringNotContainsString('{{ 7', $html);
        $this->assertDoesNotMatchRegularExpression('/@[a-z]/i', $html);
        // Whoever is not a reader is told so.
        $this->setUser($this->getDataGenerator()->create_user());
        $html = mobile::mobile_card_view([])['templates'][0]['html'];
        $this->assertStringContainsString(get_string('mobilenotreader', 'local_oksigeniaayvurenda'), $html);
        $this->assertStringNotContainsString('data:image', $html);
    }

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
        // A second time it is refused, and the app shows why instead of «Done».
        try {
            \local_oksigeniaayvurenda\external\ask_hold::execute($library->slug, (int) $record->id);
            $this->fail('a refused hold must throw');
        } catch (\moodle_exception $e) {
            $this->assertSame('holdfailed', $e->errorcode);
            $this->assertStringContainsString(get_string('holdhas', 'local_oksigeniaayvurenda'), $e->getMessage());
        }
        $html = mobile::mobile_record_view(['library' => $library->slug, 'id' => $record->id])['templates'][0]['html'];
        $this->assertStringContainsString(get_string('holdminewaiting', 'local_oksigeniaayvurenda'), $html);
        // Let go of it from the app: the page asks for it again.
        $this->assertStringContainsString('local_oksigeniaayvurenda_cancel_hold', $html);
        global $DB;
        $holdid = (int) $DB->get_field('local_oksigeniaayvurenda_hold', 'id', ['recordid' => $record->id, 'status' => 'waiting']);
        $this->assertTrue(\local_oksigeniaayvurenda\external\cancel_hold::execute($holdid)['ok']);
        $html = mobile::mobile_record_view(['library' => $library->slug, 'id' => $record->id])['templates'][0]['html'];
        $this->assertStringContainsString('local_oksigeniaayvurenda_ask_hold', $html);
        try {
            \local_oksigeniaayvurenda\external\cancel_hold::execute($holdid);
            $this->fail('A reservation was cancelled twice');
        } catch (\moodle_exception $e) {
            $this->assertSame('holdnotyours', $e->errorcode);
        }
        // Someone who may not see the library is told they are no reader.
        $this->setUser($this->getDataGenerator()->create_user());
        $html = mobile::mobile_record_view(['library' => 'no-such-library', 'id' => $record->id])['templates'][0]['html'];
        $this->assertStringContainsString(get_string('mobilenotreader', 'local_oksigeniaayvurenda'), $html);
    }

    /**
     * A page that does not open says the truth: to someone who reads at another library, that this one is not theirs; to someone
     * who reads nowhere, that they do not.
     */
    public function test_a_page_of_another_library(): void {
        $this->resetAfterTest();
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $ana = $this->getDataGenerator()->create_user();
        $gen->create_reader(['entityid' => $library->entityid, 'userid' => $ana->id]);
        $args = ['library' => $library->slug, 'id' => 999999];
        $this->setUser($ana);
        $html = mobile::mobile_record_view($args)['templates'][0]['html'];
        $this->assertStringContainsString(get_string('mobilenotyours', 'local_oksigeniaayvurenda'), $html);
        $this->setUser($this->getDataGenerator()->create_user());
        $html = mobile::mobile_record_view($args)['templates'][0]['html'];
        $this->assertStringContainsString(get_string('mobilenotreader', 'local_oksigeniaayvurenda'), $html);
    }

    /**
     * A book's page says how it stands for this reader: set aside until a date, out with them until a date or late, or free with
     * where it is; and says each thing once.
     */
    public function test_a_book_as_it_stands(): void {
        global $DB;
        $this->resetAfterTest();
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $user = $this->getDataGenerator()->create_user();
        $reader = $gen->create_reader(['entityid' => $library->entityid, 'userid' => $user->id]);
        $this->setUser($user);
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        $page = fn($record) => mobile::mobile_record_view(
            ['library' => $library->slug, 'id' => $record->id]
        )['templates'][0]['html'];
        $now = time();
        // Free: where it is, and its call number named.
        $free = $gen->create_record(['entityid' => $library->entityid, 'title' => 'Libre']);
        $gen->create_item(['libraryid' => $library->id, 'recordid' => $free->id, 'callnumber1' => 'N', 'callnumber2' => 'R3']);
        $this->assertStringContainsString($str('mobilecallnumber', 'N R3'), $page($free));
        // Set aside: the date on its own, and no call number that says nothing to them.
        $held = $gen->create_record(['entityid' => $library->entityid, 'title' => 'Apartado']);
        $gen->create_item(['libraryid' => $library->id, 'recordid' => $held->id, 'callnumber1' => 'N', 'callnumber2' => 'R3']);
        $DB->insert_record('local_oksigeniaayvurenda_hold', ['libraryid' => $library->id, 'recordid' => $held->id,
            'readerid' => $reader->id, 'status' => 'ready', 'timecreated' => $now, 'timeready' => $now,
            'timeexpires' => $now + 5 * DAYSECS, 'timemodified' => $now]);
        $html = $page($held);
        $this->assertStringContainsString($str('holdreadyuntil'), $html);
        $this->assertStringContainsString(userdate($now + 5 * DAYSECS, get_string('strftimedateshort', 'langconfig')), $html);
        $this->assertStringNotContainsString($str('mobilecallnumber', 'N R3'), $html);
        $this->assertStringNotContainsString($str('copies'), $html);
        // With them: until when, or since when it is late.
        foreach ([[$now + 9 * DAYSECS, 'mobileloanuntil'], [$now - 9 * DAYSECS, 'mobileloanlate']] as [$due, $label]) {
            $book = $gen->create_record(['entityid' => $library->entityid, 'title' => 'Prestado']);
            $item = $gen->create_item(['libraryid' => $library->id, 'recordid' => $book->id, 'status' => 'onloan']);
            $DB->insert_record('local_oksigeniaayvurenda_loan', ['libraryid' => $library->id, 'itemid' => $item->id,
                'readerid' => $reader->id, 'timeloaned' => $now - DAYSECS, 'timedue' => $due, 'renewals' => 0,
                'timecreated' => $now, 'timemodified' => $now]);
            $html = $page($book);
            $this->assertStringContainsString($str('mobilestateloan'), $html);
            $this->assertStringContainsString($str($label), $html);
        }
    }

    /**
     * What the app runs at the start opens the links of the notices inside it, and the plugin tells the app to run it.
     */
    public function test_init(): void {
        global $CFG;
        $javascript = mobile::mobile_init([])['javascript'];
        $this->assertStringContainsString('CoreContentLinksDelegate.registerHandler', $javascript);
        $this->assertStringContainsString('mobile_record_view', $javascript);
        $addons = [];
        include($CFG->dirroot . '/local/oksigeniaayvurenda/db/mobile.php');
        $this->assertSame('mobile_init', $addons['local_oksigeniaayvurenda']['handlers']['library']['init']);
    }
}
