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

use local_oksigeniaayvurenda\local\sources\cifra;

/**
 * Tests for pairing the readers already there with the people of a management platform.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\pairing
 */
#[\PHPUnit\Framework\Attributes\CoversClass(pairing::class)]
final class pairing_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    #[\Override]
    protected function tearDown(): void {
        cifra::$fetcher = null;
        parent::tearDown();
    }

    /**
     * Readers from Abies against Cifra: the exact names pair by themselves, a name with a surname missing is in doubt,
     * one Cifra lacks can leave. Then Cifra brings its readers up to date, with their card and loans, instead of twice.
     */
    public function test_pair_then_sync(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $generator->create_library();
        $entityid = (int) $library->entityid;
        $irene = $generator->create_reader(['entityid' => $entityid, 'firstname' => 'Irene', 'lastname' => 'Duna Sauce']);
        $generator->create_reader(['entityid' => $entityid, 'firstname' => 'Noa', 'lastname' => 'Lago']);
        $bruno = $generator->create_reader(['entityid' => $entityid, 'firstname' => 'Bruno', 'lastname' => 'Olmo']);
        $marta = $generator->create_reader(['entityid' => $entityid, 'firstname' => 'Marta', 'lastname' => 'Cedro']);
        cifra::save($entityid, ['enabled' => 1, 'url' => 'http://cifra-puente.invalid:8080/', 'mode' => 'key',
            'example' => 1, 'key' => 'made-up-key']);
        $people = json_decode(file_get_contents(__DIR__ . '/../fixtures/cifra/alumnos.json'), true);
        $asked = 0;
        cifra::$fetcher = function (string $method, string $url) use ($people, &$asked) {
            $asked++;
            return json_encode(str_contains($url, 'page=1&') ? $people : []);
        };
        $this->assertSame(4, pairing::unpaired($entityid));

        $piles = pairing::piles($entityid);
        $this->assertCount(2, $piles['sure']);
        $this->assertSame('Irene Duna Sauce', $piles['people'][$piles['sure'][$irene->id]]['name']);
        $this->assertArrayHasKey($bruno->id, $piles['doubt']);
        $this->assertSame([(int) $marta->id], $piles['none']);
        // The people are asked for once, then kept a while.
        $calls = $asked;
        pairing::piles($entityid);
        $this->assertSame($calls, $asked);

        foreach ($piles['sure'] as $readerid => $number) {
            $this->assertTrue(pairing::link($entityid, $readerid, $piles['people'][$number]['person']));
        }
        $olmo = $piles['doubt'][$bruno->id][0];
        $this->assertSame('Bruno Olmo Ríos', $piles['people'][$olmo]['name']);
        $this->assertTrue(pairing::link($entityid, (int) $bruno->id, $piles['people'][$olmo]['person']));
        // Neither side twice.
        $this->assertFalse(pairing::link($entityid, (int) $bruno->id, $piles['people'][$olmo]['person']));
        pairing::leave($entityid, $piles['none']);
        $this->assertSame('left', $DB->get_field(self::T . 'reader', 'status', ['id' => $marta->id]));
        $this->assertSame(0, pairing::unpaired($entityid));
        $this->assertSame([], pairing::piles($entityid)['sure']);

        $stats = cifra::sync($entityid);
        $this->assertSame(3, $stats['updated']);
        $this->assertSame(1, $stats['created']);
        $this->assertSame(5, $DB->count_records(self::T . 'reader', ['entityid' => $entityid]));
        $this->assertSame($irene->cardcode, $DB->get_field(self::T . 'reader', 'cardcode', ['id' => $irene->id]));
        $this->assertSame('Olmo Ríos', $DB->get_field(self::T . 'reader', 'lastname', ['id' => $bruno->id]));
    }
}
