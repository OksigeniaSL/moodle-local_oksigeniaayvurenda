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
 * Tests for what the review of the whole project found (0.97.0).
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\digital
 * @covers     \local_oksigeniaayvurenda\local\matching
 * @covers     \local_oksigeniaayvurenda\local\libraries
 * @covers     \local_oksigeniaayvurenda\local\sources\cifra
 * @covers     \local_oksigeniaayvurenda\output\mobile
 */
#[\PHPUnit\Framework\Attributes\CoversClass(digital::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(matching::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(libraries::class)]
final class hardening_test extends \advanced_testcase {
    /**
     * A page dressed as a work never gets into the digital collection; an EPUB, PDF, audio or video does.
     */
    public function test_digital_only_works(): void {
        global $CFG;
        $this->resetAfterTest();
        $dir = make_request_directory();
        file_put_contents($dir . '/lectura.html', '<script>alert(1)</script>');
        file_put_contents($dir . '/lectura.pdf', '<html><script>alert(1)</script></html>');
        file_put_contents($dir . '/de-verdad.pdf', "%PDF-1.4\n1 0 obj << >> endobj\ntrailer << >>\n%%EOF\n");
        $this->assertFalse(digital::acceptable($dir . '/lectura.html', 'lectura.html'));
        $this->assertFalse(digital::acceptable($dir . '/lectura.pdf', 'lectura.pdf'));
        $this->assertTrue(digital::acceptable($dir . '/de-verdad.pdf', 'de-verdad.pdf'));
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $record = $gen->create_record(['entityid' => $library->entityid]);
        $this->expectException(\moodle_exception::class);
        digital::add_file(
            $library,
            (int) $record->id,
            $dir . '/lectura.html',
            'lectura.html',
            ['licence' => 'own', 'audience' => ['public']],
            (int) get_admin()->id
        );
    }

    /**
     * Linking a reader with an account that already had one brings everything along: a reservation of the account's
     * reader stays alive, with the reader that remains.
     */
    public function test_matching_keeps_everything(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $user = $this->getDataGenerator()->create_user();
        $old = $gen->create_reader(['entityid' => $library->entityid, 'firstname' => 'Ana', 'lastname' => 'Ruiz']);
        $account = $gen->create_reader(['entityid' => $library->entityid, 'userid' => $user->id]);
        $item = $gen->create_item(['libraryid' => $library->id, 'status' => 'onloan']);
        $hold = $DB->insert_record('local_oksigeniaayvurenda_hold', ['libraryid' => $library->id, 'recordid' => $item->recordid,
            'readerid' => $account->id, 'status' => 'waiting', 'timecreated' => time(), 'timemodified' => time()]);
        matching::link((int) $library->entityid, (int) $old->id, (int) $user->id);
        $this->assertFalse($DB->record_exists('local_oksigeniaayvurenda_reader', ['id' => $account->id]));
        $this->assertSame((int) $old->id, (int) $DB->get_field('local_oksigeniaayvurenda_hold', 'readerid', ['id' => $hold]));
        $this->assertSame((int) $user->id, (int) $DB->get_field('local_oksigeniaayvurenda_reader', 'userid', ['id' => $old->id]));
    }

    /**
     * A library cannot hang from one of its own.
     */
    public function test_no_loop_of_libraries(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $central = $gen->create_library();
        $room = libraries::create_library((int) $central->entityid, ['name' => 'Aula de 5.º', 'kind' => 'classroom',
            'parentid' => $central->id]);
        libraries::update_library((int) $central->id, ['parentid' => $room->id]);
        $this->assertEmpty($DB->get_field('local_oksigeniaayvurenda_library', 'parentid', ['id' => $central->id]));
        $this->assertTrue(libraries::descends((int) $room->id, (int) $central->id));
    }

    /**
     * The key of a connector is forgotten when its address changes, so it never goes to a server someone else set.
     */
    public function test_cifra_key_stays_with_its_address(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $entityid = (int) $gen->create_entity()->id;
        sources\cifra::save($entityid, ['enabled' => 1, 'url' => 'https://puente.example.org', 'mode' => 'key',
            'key' => 'llave-de-prueba']);
        $this->assertSame('llave-de-prueba', get_config('local_oksigeniaayvurenda', 'cifrakey' . $entityid));
        sources\cifra::save($entityid, ['enabled' => 1, 'url' => 'https://puente.example.org', 'mode' => 'key', 'key' => '']);
        $this->assertSame('llave-de-prueba', get_config('local_oksigeniaayvurenda', 'cifrakey' . $entityid));
        sources\cifra::save($entityid, ['enabled' => 1, 'url' => 'https://otro.example.net', 'mode' => 'key', 'key' => '']);
        $this->assertFalse(get_config('local_oksigeniaayvurenda', 'cifrakey' . $entityid));
    }

    /**
     * A title with braces reaches the app's templates without them, so the app cannot run it.
     */
    public function test_app_texts_are_inert(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $user = $this->getDataGenerator()->create_user();
        $gen->create_reader(['entityid' => $library->entityid, 'userid' => $user->id, 'readertype' => 'teacher']);
        $record = $gen->create_record(['entityid' => $library->entityid, 'title' => 'El faro {{ 7 * 7 }}']);
        $gen->create_item(['libraryid' => $library->id, 'recordid' => $record->id]);
        $this->setUser($user);
        $html = \local_oksigeniaayvurenda\output\mobile::mobile_record_view(['library' => $library->slug,
            'id' => $record->id])['templates'][0]['html'];
        $this->assertStringNotContainsString('{{ 7', $html);
        $this->assertStringContainsString('｛｛ 7 * 7 ｝｝', $html);
        // Nor an at sign: Angular reads «@let» and «@if» in a text without any brace.
        $record = $gen->create_record(['entityid' => $library->entityid, 'title' => '@let x = 7; @if (x']);
        $gen->create_item(['libraryid' => $library->id, 'recordid' => $record->id]);
        $html = \local_oksigeniaayvurenda\output\mobile::mobile_record_view(['library' => $library->slug,
            'id' => $record->id])['templates'][0]['html'];
        $this->assertDoesNotMatchRegularExpression('/@[a-z]/i', $html);
        $this->assertStringContainsString('＠let x = 7; ＠if (x', $html);
    }
}
