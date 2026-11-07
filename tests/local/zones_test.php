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
namespace local_oksigeniaayvurenda\local;

/**
 * Each centre's day ends at its own midnight, wherever the site runs.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(zones::class)]
final class zones_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * A library on a site in Madrid, whose centre is in Bogotá, with a reader and a book.
     *
     * @return \stdClass
     */
    private function world(): \stdClass {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->setTimezone('Europe/Madrid');
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $w = new \stdClass();
        $w->gen = $gen;
        $w->library = $gen->create_library();
        $w->staff = (int) get_admin()->id;
        $w->entityid = (int) $w->library->entityid;
        libraries::update_entity($w->entityid, ['name' => 'Centro de ejemplo', 'timezone' => 'America/Bogota']);
        $w->user = $this->getDataGenerator()->create_user();
        $w->ana = $gen->create_reader(['entityid' => $w->entityid, 'cardcode' => '10000H', 'userid' => $w->user->id,
            'firstname' => 'Ana', 'lastname' => 'Montes']);
        $record = $gen->create_record(['entityid' => $w->entityid, 'title' => 'El faro']);
        $gen->create_item(['libraryid' => $w->library->id, 'recordid' => $record->id, 'barcode' => 'B1']);
        return $w;
    }

    /**
     * A moment in Bogotá.
     *
     * @param string $when
     * @return int
     */
    private function bogota(string $when): int {
        return (new \DateTimeImmutable($when, new \DateTimeZone('America/Bogota')))->getTimestamp();
    }

    /**
     * The setting: what is known is kept, and nothing chosen is the site's.
     */
    public function test_setting(): void {
        $w = $this->world();
        $this->assertSame('America/Bogota', zones::name($w->entityid));
        $this->assertSame('America/Bogota', zones::of($w->library)->getName());
        libraries::update_entity($w->entityid, ['name' => 'Centro de ejemplo', 'timezone' => 'Nowhere/Land']);
        $this->assertSame(\core_date::get_server_timezone(), zones::name($w->entityid));
        libraries::update_entity($w->entityid, ['name' => 'Centro de ejemplo', 'timezone' => '']);
        $this->assertSame(\core_date::get_server_timezone(), zones::name($w->entityid));
        $choices = zones::choices($w->entityid);
        $this->assertSame('', $choices[0]['value']);
        $this->assertTrue($choices[0]['selected']);
        $this->assertContains('America/Bogota', array_column($choices, 'value'));
        $other = $w->gen->create_library();
        $this->assertSame(\core_date::get_server_timezone(), zones::name((int) $other->entityid), 'each centre has its own');
    }

    /**
     * A loan is due at the end of the centre's day; at 18:30 there, on that day, it is not late, though it is tomorrow in
     * Madrid; and everyone sees the same date, whatever their own zone.
     */
    public function test_a_book_on_its_last_day(): void {
        global $DB;
        $w = $this->world();
        $desk = new desk($w->library, $w->staff, $this->bogota('2026-11-02 10:00'));
        $lent = $desk->scan('B1', (int) $w->ana->id);
        $this->assertSame('lent', $lent['kind']);
        $due = (int) $DB->get_field(self::T . 'loan', 'timedue', ['id' => $lent['loanid']]);
        $last = (new \DateTimeImmutable('@' . $due))->setTimezone(new \DateTimeZone('America/Bogota'));
        $this->assertSame('23:59', $last->format('H:i'), 'the end of the day there');
        $evening = $this->bogota($last->format('Y-m-d') . ' 18:30');
        $madrid = (new \DateTimeImmutable('@' . $evening))->setTimezone(new \DateTimeZone('Europe/Madrid'));
        $this->assertNotSame($last->format('Y-m-d'), $madrid->format('Y-m-d'), 'it is already tomorrow on the server');

        sanctions::save_settings($w->entityid, ['mode' => 'fine', 'cents' => 10]);
        $back = (new desk($w->library, $w->staff, $evening))->scan('B1');
        $this->assertSame('returned', $back['kind']);
        $this->assertSame(0, $back['late']);
        $this->assertNull($back['sanction'], 'no fine for a book in on its last day');

        // Shown to anyone: the day it is due there.
        $this->assertStringContainsString($last->format('j'), zones::date($due, '%e', $w->entityid));
        $this->setTimezone('Asia/Tokyo');
        \core\session\manager::set_user(get_admin());
        $this->assertSame(
            userdate($due, '%Y-%m-%d', 'America/Bogota', false),
            zones::date($due, '%Y-%m-%d', $w->library)
        );
    }

    /**
     * Days without lending end at the centre's midnight, and its notices wait for its own day.
     */
    public function test_days_and_notices(): void {
        global $DB;
        $w = $this->world();
        sanctions::save_settings($w->entityid, ['mode' => 'days', 'perday' => 1, 'maxdays' => 30]);
        $lent = (new desk($w->library, $w->staff, $this->bogota('2026-11-02 10:00')))->scan('B1', (int) $w->ana->id);
        $due = (int) $DB->get_field(self::T . 'loan', 'timedue', ['id' => $lent['loanid']]);
        $last = (new \DateTimeImmutable('@' . $due))->setTimezone(new \DateTimeZone('America/Bogota'));

        // Evening of the last day there: tomorrow in Madrid, and nobody is told yet.
        $sink = $this->redirectMessages();
        $this->assertSame(0, notices::run($this->bogota($last->format('Y-m-d') . ' 18:30'))['readers']);
        // The morning after, there: late.
        $after = $this->bogota($last->modify('+1 day')->format('Y-m-d') . ' 10:00');
        $this->assertSame(1, notices::run($after)['readers']);
        $sink->close();

        $later = $this->bogota($last->modify('+3 days')->format('Y-m-d') . ' 10:00');
        $return = (new desk($w->library, $w->staff, $later))->scan('B1');
        $until = (int) $DB->get_field(self::T . 'sanction', 'timeuntil', ['id' => $return['sanction']['id']]);
        $midnight = (new \DateTimeImmutable('@' . $until))->setTimezone(new \DateTimeZone('America/Bogota'));
        $this->assertSame('00:00', $midnight->format('H:i'));
    }

    /**
     * The school year changes at the centre's midnight too.
     */
    public function test_year(): void {
        $w = $this->world();
        $year = years::around($w->entityid, $this->bogota('2026-11-02 10:00'));
        $start = (new \DateTimeImmutable('@' . $year->timestart))->setTimezone(new \DateTimeZone('America/Bogota'));
        $this->assertSame('00:00', $start->format('H:i'));
    }

    /**
     * A date as a date field reads it keeps the zero of a day from 1 to 9 (the reports started empty every 1st of a month);
     * a date in words still drops it.
     */
    public function test_iso_keeps_the_zero(): void {
        $this->resetAfterTest();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $time = make_timestamp(2026, 9, 1, 12);
        $this->assertSame('2026-09-01', zones::date($time, '%Y-%m-%d', $library));
        $this->assertSame('20260901', zones::date($time, '%Y%m%d', $library));
        $this->assertStringNotContainsString(' 01 ', ' ' . zones::date($time, 'strftimedate', $library) . ' ');
    }

    /**
     * Each centre is told in its own morning: at 8:30 in Madrid it is 2:30 in Bogotá, so not yet; at 8:30 in Bogotá it
     * is; once a day, and not on Saturday.
     */
    public function test_mornings(): void {
        $w = $this->world();
        $madrid = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        libraries::update_entity((int) $madrid->entityid, ['timezone' => 'Europe/Madrid']);
        $monday = $this->bogota('2026-11-09 02:30');
        $this->assertNotContains($w->entityid, zones::mornings($monday));
        $this->assertContains((int) $madrid->entityid, zones::mornings($monday));
        $eight = $this->bogota('2026-11-09 08:30');
        $this->assertContains($w->entityid, zones::mornings($eight));
        zones::mornings_done([$w->entityid], $eight);
        $this->assertNotContains($w->entityid, zones::mornings($this->bogota('2026-11-09 09:30')));
        $this->assertContains($w->entityid, zones::mornings($this->bogota('2026-11-10 08:30')));
        $this->assertNotContains($w->entityid, zones::mornings($this->bogota('2026-11-14 08:30')));
        // What expires goes every day, Saturday too.
        $this->assertContains($w->entityid, zones::mornings($this->bogota('2026-11-14 08:30'), false));
    }
}
