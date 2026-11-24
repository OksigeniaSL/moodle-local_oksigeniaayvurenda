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

namespace local_oksigeniaayvurenda\external;

use core_external\external_api;
use local_oksigeniaayvurenda\local\digital;
use local_oksigeniaayvurenda\local\libraries;
use local_oksigeniaayvurenda\local\lots;
use local_oksigeniaayvurenda\local\relay;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\stocktake;

/**
 * Tests for what the library's own screens ask without loading again, called as Moodle's service calls it.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\external\stocktake_read
 * @covers     \local_oksigeniaayvurenda\external\stocktake_return
 * @covers     \local_oksigeniaayvurenda\external\lot_suggest
 * @covers     \local_oksigeniaayvurenda\external\digital_place
 * @covers     \local_oksigeniaayvurenda\external\howto_ask
 * @covers     \local_oksigeniaayvurenda\external\hunt_found
 * @covers     \local_oksigeniaayvurenda\external\relay_push
 * @covers     \local_oksigeniaayvurenda\external\relay_pull
 */
#[\PHPUnit\Framework\Attributes\CoversClass(stocktake_read::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(stocktake_return::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(lot_suggest::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(digital_place::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(howto_ask::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(hunt_found::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(relay_push::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(relay_pull::class)]
final class screens_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables and functions. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * Asks as a screen does: through Moodle's service, with the session key, and what comes back cleaned by its description.
     *
     * @param string $function
     * @param array $args
     * @return array error, and data or exception.
     */
    private function ask(string $function, array $args): array {
        $_POST['sesskey'] = sesskey();
        return external_api::call_external_function(self::T . $function, $args, true);
    }

    /**
     * What it answers when nothing went wrong.
     *
     * @param string $function
     * @param array $args
     * @return array
     */
    private function answer(string $function, array $args): array {
        $answer = $this->ask($function, $args);
        $this->assertFalse($answer['error'], $function . ': ' . ($answer['exception']->message ?? ''));
        return $answer['data'];
    }

    /**
     * Why it says no.
     *
     * @param string $function
     * @param array $args
     * @return string The code of the error.
     */
    private function refusal(string $function, array $args): string {
        $answer = $this->ask($function, $args);
        $this->assertTrue($answer['error'], $function);
        return $answer['exception']->errorcode;
    }

    /**
     * The generator of the plugin.
     *
     * @return \local_oksigeniaayvurenda_generator
     */
    private function gen(): \local_oksigeniaayvurenda_generator {
        return $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
    }

    /**
     * Where a screen asks: Moodle's service, with the function's whole name and the session key.
     */
    public function test_service(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $url = new \moodle_url(shell::service('howto_ask'));
        $this->assertStringEndsWith('/lib/ajax/service.php', $url->get_path());
        $this->assertSame(self::T . 'howto_ask', $url->get_param('info'));
        $this->assertTrue(confirm_sesskey($url->get_param('sesskey')));
        // Every function a screen asks for is one Moodle knows, and lets a screen ask.
        $plugin = \core_component::get_plugin_directory('local', 'oksigeniaayvurenda');
        $asked = [];
        foreach (array_merge(glob($plugin . '/*.php'), glob($plugin . '/classes/local/*.php')) as $file) {
            preg_match_all("/(?:shell|self)::service\('(\w+)'\)/", file_get_contents($file), $found);
            $asked = array_merge($asked, $found[1]);
        }
        $this->assertNotEmpty($asked);
        foreach (array_unique($asked) as $function) {
            $info = external_api::external_function_info(self::T . $function);
            $this->assertTrue($info->allowed_from_ajax && $info->loginrequired, $function);
        }
    }

    /**
     * A stocktake: what each code is, in its order, and how it goes; a copy thought lent comes back; a closed one, another
     * library's and whoever does not catalogue get a no.
     */
    public function test_stocktake(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $library = $this->gen()->create_library();
        $place = (int) $DB->insert_record(self::T . 'authority', ['entityid' => $library->entityid, 'kind' => 'location',
            'label' => 'Poesía <1>', 'timecreated' => time(), 'timemodified' => time()]);
        $copy = fn(string $status) => $this->gen()->create_item(['libraryid' => $library->id, 'locationid' => $place,
            'status' => $status]);
        $one = $copy('available');
        $DB->set_field(self::T . 'record', 'title', 'Menos < que <b>más</b> & «todo»', ['id' => $one->recordid]);
        $lent = $copy('onloan');
        $id = stocktake::start($library, 'Junio', null, (int) $USER->id);
        $args = ['library' => $library->slug, 'stocktake' => $id];

        $answer = $this->answer('stocktake_read', $args + ['reads' => [['code' => strtolower($one->barcode), 'here' => $place],
            ['code' => $lent->barcode], ['code' => '999999Z', 'here' => 0]]]);
        $this->assertSame(['seen', 'lent', 'unknown'], array_column($answer['answers'], 'kind'));
        $this->assertSame('Menos < que <b>más</b> & «todo»', $answer['answers'][0]['title'], 'as it is written, nothing taken');
        $this->assertSame('Poesía <1>', $answer['answers'][0]['where']);
        $this->assertSame(['expected' => 2, 'seen' => 2, 'missing' => 0, 'misplaced' => 0, 'lent' => 1, 'found' => 0,
            'unknown' => 1], array_map('intval', $answer['counts']));
        $this->assertEquals([['place' => $place, 'count' => 2, 'seen' => 2]], $answer['places']);
        $this->assertSame(stocktake::counts(stocktake::get($id, $library)), $answer['counts']);

        // Returned there and then, with what the desk says.
        $reader = $this->gen()->create_reader(['entityid' => $library->entityid]);
        $this->gen()->create_loan(['itemid' => $lent->id, 'readerid' => $reader->id, 'libraryid' => $library->id]);
        $back = $this->answer('stocktake_return', $args + ['code' => $lent->barcode]);
        $this->assertNotSame('', $back['kind']);
        $this->assertNotSame('', $back['message']);
        $this->assertSame('available', $DB->get_field(self::T . 'item', 'status', ['id' => $lent->id]));
        $this->assertArrayHasKey('expected', $back['counts']);

        $this->assertSame('nolibrary', $this->refusal('stocktake_read', ['library' => 'nowhere'] + $args + ['reads' => []]));
        stocktake::close(stocktake::get($id, $library));
        $this->assertSame('stocktakeclosed', $this->refusal('stocktake_read', $args + ['reads' => []]));
        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertSame('nopermissions', $this->refusal('stocktake_read', $args + ['reads' => []]));
        $this->assertSame('nopermissions', $this->refusal('stocktake_return', $args + ['code' => $lent->barcode]));
    }

    /**
     * A box: the free copies of the books with those words, for whoever lends.
     */
    public function test_lot_suggest(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $library = $this->gen()->create_library();
        $item = $this->gen()->create_item(['libraryid' => $library->id]);
        $title = 'El bosque de los dinosaurios & compañía';
        $DB->update_record(self::T . 'record', (object) ['id' => $item->recordid, 'title' => $title,
            'searchtext' => \local_oksigeniaayvurenda\core\text::normalise($title)]);
        $aula = libraries::create_library((int) $library->entityid, ['name' => 'Aula de 4º B', 'kind' => 'classroom',
            'group' => '4º B']);
        $lot = lots::create($library, 'Dinosaurios', (int) $aula->id, time() + WEEKSECS, (int) $USER->id, time());
        $args = ['library' => $library->slug, 'lot' => $lot];
        $found = $this->answer('lot_suggest', $args + ['q' => ' dinosaurios ']);
        $this->assertSame([$item->barcode], array_column($found['suggestions'], 'barcode'));
        $this->assertSame($title, $found['suggestions'][0]['title']);
        $this->assertSame([], $this->answer('lot_suggest', $args + ['q' => '12'])['suggestions']);
        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertSame('nopermissions', $this->refusal('lot_suggest', $args + ['q' => 'dinosaurios']));
    }

    /**
     * Where someone is in a book is kept apart from their bookmarks, as they were sent; a guest keeps nothing here, and
     * whoever may not read it, neither.
     */
    public function test_digital_place(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $library = $this->gen()->create_library();
        $record = $this->gen()->create_record(['entityid' => $library->entityid, 'title' => 'El faro dormido']);
        $user = $this->getDataGenerator()->create_user();
        $this->gen()->create_reader(['entityid' => $library->entityid, 'userid' => $user->id]);
        $item = digital::add_link($library, (int) $record->id, 'https://example.org/faro', ['licence' => 'pd',
            'audience' => ['students']], 2);
        $this->setUser($user);
        $this->assertTrue((bool) $this->answer('digital_place', ['item' => $item, 'position' => ' epubcfi(/6/4[a]!/4/2) '])['ok']);
        $this->assertSame('epubcfi(/6/4[a]!/4/2)', digital::place($item, (int) $user->id)['position']);
        $marks = [['place' => 'epubcfi(/6/8!/4)', 'label' => 'Página 8']];
        $this->answer('digital_place', ['item' => $item, 'marked' => true, 'bookmarks' => $marks]);
        $kept = digital::place($item, (int) $user->id);
        $this->assertSame('epubcfi(/6/4[a]!/4/2)', $kept['position'], 'the bookmarks leave the place as it was');
        $this->assertSame($marks, $kept['bookmarks']);
        $this->answer('digital_place', ['item' => $item, 'position' => '12']);
        $this->assertSame($marks, digital::place($item, (int) $user->id)['bookmarks'], 'and the place, the bookmarks');
        $this->answer('digital_place', ['item' => $item, 'marked' => true, 'bookmarks' => []]);
        $this->assertSame([], digital::place($item, (int) $user->id)['bookmarks']);

        $this->setGuestUser();
        $this->assertFalse((bool) $this->answer('digital_place', ['item' => $item, 'position' => '3'])['ok']);
        $this->assertSame(1, $DB->count_records(self::T . 'digitalread', ['digitalid' => $item]));
        $stranger = $this->getDataGenerator()->create_user();
        $this->setUser($stranger);
        $this->ask('digital_place', ['item' => $item, 'position' => '3']);
        $this->assertFalse($DB->record_exists(self::T . 'digitalread', ['userid' => $stranger->id]));
    }

    /**
     * «What do you want to do?»: the same answers as the class gives, whole; nothing for who is not staff.
     */
    public function test_howto_ask(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $library = $this->gen()->create_library();
        $word = get_string('howtolend', 'local_oksigeniaayvurenda');
        $expected = \local_oksigeniaayvurenda\local\howto::answers($word, $library, (int) $USER->id);
        $this->assertNotEmpty($expected['answers']);
        $this->assertEquals($expected, $this->answer('howto_ask', ['library' => $library->slug, 'q' => ' ' . $word . ' ']));
        $nothing = $this->answer('howto_ask', ['library' => $library->slug, 'q' => 'zzqqxx']);
        $this->assertSame([], $nothing['answers']);
        $this->assertNotSame('', $nothing['none']);
        $this->assertSame(['answers' => []], $this->answer('howto_ask', ['library' => $library->slug, 'q' => ' ']));
        $this->assertSame(['answers' => []], $this->answer('howto_ask', ['library' => 'nowhere', 'q' => $word]));
        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertSame(['answers' => []], $this->answer('howto_ask', ['library' => $library->slug, 'q' => $word]));
    }

    /**
     * A book that was not hidden is not kept, and nobody without a card keeps any; the orientation comes described.
     */
    public function test_hunt_found(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $library = $this->gen()->create_library();
        $record = $this->gen()->create_record(['entityid' => $library->entityid, 'title' => 'El faro dormido']);
        $user = $this->getDataGenerator()->create_user();
        $this->gen()->create_reader(['entityid' => $library->entityid, 'userid' => $user->id]);
        $args = ['library' => $library->slug, 'corner' => '8', 'turn' => 0, 'record' => (int) $record->id, 'lanterns' => 2];
        $this->assertEquals(['kept' => false], $this->answer('hunt_found', $args), 'no card');
        $this->setUser($user);
        $this->assertEquals(['kept' => false], $this->answer('hunt_found', $args), 'not the hidden one');
        $this->assertSame('nolibrary', $this->refusal('hunt_found', ['library' => 'nowhere'] + $args));
        $shown = ['kept' => true, 'orientation' => ['done' => 1, 'left' => 2, 'finished' => false, 'says' => 'Quedan <2>',
            'html' => '<p class="x">1 & 2</p>']];
        $this->assertEquals($shown, external_api::clean_returnvalue(hunt_found::execute_returns(), $shown));
    }

    /**
     * A phone as the reader of a desk: its codes reach its own user's screen, once, and nobody else's.
     */
    public function test_relay(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->gen()->create_library();
        $this->assertTrue((bool) $this->answer('relay_push', ['code' => ' 10000H '])['ok']);
        $this->answer('relay_push', ['code' => '9788490000014']);
        relay::push((int) $this->getDataGenerator()->create_user()->id, '7');
        $this->assertSame(['10000H', '9788490000014'], $this->answer('relay_pull', [])['codes']);
        $this->assertSame([], $this->answer('relay_pull', [])['codes']);
        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertSame('nopermissions', $this->refusal('relay_push', ['code' => '10000H']));
        $this->assertSame('nopermissions', $this->refusal('relay_pull', []));
    }

    /**
     * Without the session key nobody is answered, nor without an account.
     */
    public function test_session(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->assertFalse($this->ask('relay_pull', [])['error']);
        $_POST['sesskey'] = 'other';
        $answer = external_api::call_external_function(self::T . 'relay_pull', [], true);
        $this->assertSame('invalidsesskey', $answer['exception']->errorcode);
        $this->setUser(0);
        $this->assertSame('servicerequireslogin', $this->refusal('relay_pull', []));
    }
}
