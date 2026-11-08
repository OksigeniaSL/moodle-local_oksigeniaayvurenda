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
 * Tests for the library's agenda.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\agenda
 */
#[\PHPUnit\Framework\Attributes\CoversClass(agenda::class)]
final class agenda_test extends \advanced_testcase {
    /**
     * Activities with an hour, until an hour, over days and without an hour; what is said of when; what is coming and
     * what goes on; what is wrong is told; taken away; and what the plugin's API gives the screens.
     */
    public function test_agenda(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $entityid = (int) $library->entityid;
        libraries::update_entity($entityid, ['timezone' => 'Europe/Madrid']);
        $now = (new \DateTimeImmutable('2026-11-09 10:00:00', new \DateTimeZone('Europe/Madrid')))->getTimestamp();

        $this->assertSame('agendaerrtitle', agenda::save($library, ['title' => ' ', 'day' => '2026-11-14'], $now));
        $this->assertSame('agendaerrwhen', agenda::save($library, ['title' => 'Cuentos', 'day' => 'mañana'], $now));
        $this->assertSame('agendaerrwhen', agenda::save($library, ['title' => 'Cuentos', 'day' => '2026-11-14',
            'time' => '11:00', 'endtime' => '10:00'], $now));
        $tales = agenda::save($library, ['title' => 'Cuentacuentos', 'day' => '2026-11-14', 'time' => '11:00',
            'endtime' => '12:30', 'place' => 'Sala infantil', 'audience' => 'De 4 a 8 años', 'seats' => 20], $now);
        $fair = agenda::save($library, ['title' => 'Feria del libro', 'day' => '2026-11-08', 'endday' => '2026-11-12'], $now);
        $club = agenda::save($library, ['title' => 'Club de lectura', 'day' => '2026-11-10', 'time' => '18:00'], $now);
        $later = agenda::save($library, ['title' => 'Visita de una autora', 'day' => '2026-12-20'], $now);
        $this->assertIsInt($tales);

        // What is said of when, in the centre's zone.
        $this->setUser(null);
        force_current_language('en');
        $row = fn(int $id) => $DB->get_record('local_oksigeniaayvurenda_activity', ['id' => $id]);
        $this->assertStringContainsString('11:00', agenda::when($row($tales), $library));
        $this->assertStringContainsString('12:30', agenda::when($row($tales), $library));
        $this->assertStringContainsString('6:00 PM', agenda::when($row($club), $library), 'the hour as the language says it');
        $this->assertStringNotContainsString('00:00', agenda::when($row($later), $library), 'a day without an hour');
        $this->assertStringStartsWith('From ', agenda::when($row($fair), $library));

        // This week: the fair that goes on, the club and the storytelling; the visit is later.
        $week = agenda::between($entityid, $now, $now + WEEKSECS);
        $this->assertSame(['Feria del libro', 'Club de lectura', 'Cuentacuentos'], array_column($week, 'title'));
        $this->assertSame('Sala infantil', $week[2]->place);
        $this->assertEquals(20, $week[2]->seats);

        // The screens of the centre.
        $api = \local_oksigeniaayvurenda\api::activities($now, $now + WEEKSECS, $entityid, 2);
        $this->assertSame(['Feria del libro', 'Club de lectura'], array_column($api, 'title'));
        $this->assertNull($api[1]['timeend']);
        $this->assertStringContainsString('/local/oksigeniaayvurenda/agenda.php', $api[0]['url']);
        $this->assertSame($entityid, $api[0]['entityid']);

        // Changed, and taken away.
        agenda::save($library, ['title' => 'Club de lectura juvenil', 'day' => '2026-11-10', 'time' => '18:30'], $now, $club);
        $this->assertSame('Club de lectura juvenil', $row($club)->title);
        agenda::remove($library, $club);
        $this->assertFalse($DB->record_exists('local_oksigeniaayvurenda_activity', ['id' => $club]));
        // Another library's cannot be taken away from this one.
        $other = $gen->create_library();
        agenda::remove($other, $tales);
        $this->assertTrue($DB->record_exists('local_oksigeniaayvurenda_activity', ['id' => $tales]));
    }
}
