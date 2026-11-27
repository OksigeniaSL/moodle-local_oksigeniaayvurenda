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
 * Tests for the listens of the voices: counted without who, and told as an audience.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\listens
 */
#[\PHPUnit\Framework\Attributes\CoversClass(listens::class)]
final class listens_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * A voice of a reader, approved or not.
     *
     * @param \stdClass $library
     * @param bool $approved
     * @return array The voice and the account of who recorded it.
     */
    private function voice(\stdClass $library, bool $approved = true): array {
        global $DB;
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $record = $gen->create_record(['entityid' => $library->entityid, 'title' => 'El faro dormido']);
        $ana = $this->getDataGenerator()->create_user();
        $reader = $gen->create_reader(['entityid' => $library->entityid, 'userid' => $ana->id, 'readertype' => 'teacher']);
        $path = make_request_directory() . '/voz.webm';
        file_put_contents($path, 'made-up recording');
        $id = voices::record($library, (int) $record->id, (int) $reader->id, (int) $ana->id, $path, 'Capítulo 1');
        if ($approved) {
            $DB->set_field(self::T . 'digital', 'status', 'published', ['id' => $id]);
        }
        return [digital::get($id, $library), $ana];
    }

    /**
     * Heard for a while by someone else, it counts once per visit and day; its own reader and a voice still waiting do
     * not; the family's link, without a session, counts each time.
     */
    public function test_heard(): void {
        global $DB;
        $this->resetAfterTest();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        [$waiting] = $this->voice($library, false);
        $this->assertFalse(voices::heard($waiting, 0));
        [$voice, $ana] = $this->voice($library);
        $leo = $this->getDataGenerator()->create_user();
        $visit = new \stdClass();
        $today = strtotime('2026-11-27 10:00');
        $this->assertFalse(voices::heard($voice, (int) $ana->id, new \stdClass(), $today));
        $this->assertTrue(voices::heard($voice, (int) $leo->id, $visit, $today));
        $this->assertFalse(voices::heard($voice, (int) $leo->id, $visit, $today + HOURSECS));
        $this->assertTrue(voices::heard($voice, (int) $leo->id, $visit, $today + DAYSECS));
        $this->assertTrue(voices::heard($voice, 0));
        $this->assertTrue(voices::heard($voice, 0));
        $this->assertSame(4, (int) $DB->get_field(self::T . 'digital', 'listens', ['id' => $voice->id]));
        $this->assertSame(0, (int) $DB->get_field(self::T . 'digital', 'listens', ['id' => $waiting->id]));
    }

    /**
     * From nobody to a sports hall: the number, the biggest place already filled, and what is left only for the reader
     * and the family; whoever listens sees a few words once a sofa is full, never the number.
     */
    public function test_context(): void {
        $this->resetAfterTest();
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        $is = fn(string $place) => $str('listensis', $str('listensshort_' . $place));

        $none = listens::context(0, $str, ['who' => 'mine']);
        $this->assertSame(
            [false, $str('listensnone'), $str('listensnextnone'), 'sofa', 0],
            [$none['hasnumber'], $none['phrase'], $none['next'], $none['place'], $none['lit']]
        );
        $this->assertStringContainsString('<svg', $none['svg']);

        $this->assertSame($str('listensfirst'), listens::context(1, $str)['phrase']);
        $this->assertFalse(listens::context(1, $str, ['who' => 'public'])['small']);
        $this->assertFalse(listens::context(2, $str, ['who' => 'public'])['small']);

        $sofa = listens::context(3, $str, ['who' => 'public']);
        $this->assertSame([$is('sofa'), $is('sofa'), false], [$sofa['phrase'], $sofa['small'], $sofa['next']]);
        $this->assertStringNotContainsString('3', $sofa['small']);
        $this->assertStringContainsString('3', listens::context(3, $str, ['who' => 'staff'])['small']);

        $more = listens::context(4, $str, ['who' => 'family']);
        $this->assertSame($str('listensmore', $str('listensshort_sofa')), $more['phrase']);
        $this->assertSame($str('listensnext', (object) ['place' => $str('listensplace_corro'), 'n' => '8']), $more['next']);
        $this->assertSame(['corro', 4, 12], [$more['place'], $more['lit'], $more['capacity']]);
        $this->assertFalse(listens::context(4, $str, ['who' => 'staff'])['next']);

        $full = listens::context(25, $str, ['who' => 'mine']);
        $this->assertSame([$is('clase'), 'clase', false], [$full['phrase'], $full['place'], $full['next']]);

        $huge = listens::context(5000, $str, ['who' => 'mine', 'number' => '5.000']);
        $this->assertSame(['polideportivo', 1340, '5.000'], [$huge['place'], $huge['lit'], $huge['digits']]);
        $this->assertSame($str('listensmore', $str('listensshort_polideportivo')), $huge['phrase']);
    }

    /**
     * A voice still waiting has no counter; at a university, whoever listens sees none; a reading in parts counts as
     * its most heard part.
     */
    public function test_of(): void {
        global $DB;
        $this->resetAfterTest();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        [$waiting] = $this->voice($library, false);
        [$voice] = $this->voice($library);
        $this->assertNull(listens::of($waiting, 'mine'));
        $DB->set_field(self::T . 'digital', 'listens', 30, ['id' => $voice->id]);
        $voice = digital::get((int) $voice->id, $library);
        $this->assertSame('autobus', listens::of($voice, 'mine')['place']);
        $this->assertSame('noche', listens::of($voice, 'mine', 'noche')['look']);
        $this->assertSame('autobus', listens::reading([$voice, $waiting], 'public')['place']);
        $this->assertNull(listens::reading([$waiting], 'public'));
        libraries::update_entity((int) $library->entityid, ['skin' => 'university']);
        $this->assertNull(listens::of($voice, 'public'));
        $this->assertNotNull(listens::of($voice, 'mine'));
    }
}
