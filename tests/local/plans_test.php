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

use local_oksigeniaayvurenda\local\sources\csvlist;

/**
 * Tests for the plan of each centre.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\plans
 */
#[\PHPUnit\Framework\Attributes\CoversClass(plans::class)]
final class plans_test extends \advanced_testcase {
    /** @var string */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * Sets the site's plan for new centres.
     *
     * @param string $name
     * @param int $copies
     * @param int $readers
     * @param array $features Feature => on.
     */
    private function site_plan(string $name, int $copies, int $readers, array $features = []): void {
        set_config('planname', $name, 'local_oksigeniaayvurenda');
        set_config('plancopies', $copies, 'local_oksigeniaayvurenda');
        set_config('planreaders', $readers, 'local_oksigeniaayvurenda');
        foreach ($features as $feature => $on) {
            set_config('plan' . $feature, (int) $on, 'local_oksigeniaayvurenda');
        }
    }

    /**
     * With nothing set nothing is limited; a new centre takes the site's plan and keeps it when the site's changes; a
     * centre from before plans follows the site's.
     */
    public function test_defaults_and_seed(): void {
        $this->resetAfterTest();
        $old = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $this->assertSame(['name' => '', 'copies' => 0, 'readers' => 0, 'lti' => true, 'connectors' => true,
            'voices' => true, 'madewith' => false], plans::defaults());
        $this->assertNull(plans::summary((int) $old->entityid));
        $this->assertNull(plans::left((int) $old->entityid, 'copies'));

        $this->site_plan('Free', 3, 2, ['lti' => false, 'madewith' => true]);
        $library = libraries::create_entity(['name' => 'Invented school']);
        $entityid = (int) $library->entityid;
        $this->assertSame(['name' => 'Free', 'copies' => 3, 'readers' => 2, 'lti' => false, 'connectors' => true,
            'voices' => true, 'madewith' => true], plans::of($entityid));
        $this->assertFalse(plans::allows($entityid, 'lti'));
        $this->assertSame('Plan Free: 0 of 3 copies · 0 of 2 readers', plans::summary($entityid));

        $this->site_plan('Annual', 0, 0, ['lti' => true]);
        $this->assertSame('Free', plans::of($entityid)['name']);
        $this->assertSame('Annual', plans::of((int) $old->entityid)['name']);
        plans::save($entityid, ['name' => 'Annual', 'copies' => -5]);
        $this->assertSame(['name' => 'Annual', 'copies' => 0, 'readers' => 0, 'lti' => true, 'connectors' => true,
            'voices' => true, 'madewith' => false], plans::of($entityid));
        $this->assertSame('Plan Annual: 0 copies · 0 readers', plans::summary($entityid));
    }

    /**
     * Copies come in while they fit; one more is refused whole, and a withdrawn copy leaves its place.
     */
    public function test_copies(): void {
        global $DB;
        $this->resetAfterTest();
        $this->site_plan('Free', 3, 0);
        $generator = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = libraries::create_entity(['name' => 'Invented school']);
        $entityid = (int) $library->entityid;
        $record = $generator->create_record(['entityid' => $entityid]);
        cataloguer::add($library, [], ['count' => 2], (int) $record->id);
        try {
            cataloguer::add($library, [], ['count' => 2], (int) $record->id);
            $this->fail('Two more copies do not fit.');
        } catch (plan_full $e) {
            $this->assertSame('planshortcopies', $e->errorcode);
            $this->assertStringContainsString('room for 1 more copies (2 of 3) and this brings 2', $e->getMessage());
        }
        $this->assertSame(2, $DB->count_records(self::T . 'item', ['entityid' => $entityid]));
        cataloguer::add($library, [], ['count' => 1], (int) $record->id);
        try {
            cataloguer::add($library, [], ['count' => 1], (int) $record->id);
            $this->fail('The plan is full.');
        } catch (plan_full $e) {
            $this->assertSame('planfullcopies', $e->errorcode);
        }
        $item = $DB->get_records(self::T . 'item', ['entityid' => $entityid], 'id', 'id', 0, 1);
        $DB->set_field(self::T . 'item', 'status', 'withdrawn', ['id' => key($item)]);
        $this->assertSame(1, plans::left($entityid, 'copies'));
        cataloguer::add($library, [], ['count' => 1], (int) $record->id);
        $this->assertSame(['copies' => 3, 'readers' => 0], plans::used($entityid));
    }

    /**
     * An import that would pass the plan is undone whole; one that fits stays.
     */
    public function test_within(): void {
        global $DB;
        $this->resetAfterTest();
        $this->site_plan('Free', 4, 0);
        $generator = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = libraries::create_entity(['name' => 'Invented school']);
        $entityid = (int) $library->entityid;
        $import = function (int $count) use ($generator, $library) {
            for ($i = 0; $i < $count; $i++) {
                $generator->create_item(['libraryid' => $library->id]);
            }
            return $count;
        };
        $this->assertSame(3, plans::within($entityid, fn() => $import(3)));
        try {
            plans::within($entityid, fn() => $import(2));
            $this->fail('Two more copies do not fit.');
        } catch (plan_full $e) {
            $this->assertSame('planshortcopies', $e->errorcode);
        }
        $this->assertSame(3, $DB->count_records(self::T . 'item', ['entityid' => $entityid]));
    }

    /**
     * A sync leaves out who does not fit; a list loaded by hand is refused whole.
     */
    public function test_readers(): void {
        global $DB;
        $this->resetAfterTest();
        $this->site_plan('Free', 0, 2);
        $library = libraries::create_entity(['name' => 'Invented school']);
        $entityid = (int) $library->entityid;
        $list = [['firstname' => 'Ana', 'lastname' => 'Montes'], ['firstname' => 'Leo', 'lastname' => 'Faro'],
            ['firstname' => 'Noa', 'lastname' => 'Ríos']];
        try {
            plans::within($entityid, fn() => csvlist::load($entityid, $list, 'student', null, 'csv', false));
            $this->fail('Three readers do not fit.');
        } catch (plan_full $e) {
            $this->assertSame('planshortreaders', $e->errorcode);
        }
        $this->assertSame(0, $DB->count_records(self::T . 'reader', ['entityid' => $entityid]));

        $stats = csvlist::load($entityid, $list);
        $this->assertSame([2, 1], [$stats['created'], $stats['overlimit']]);
        $this->assertSame(' Left out by the plan: 1 (they come in when there is room).', plans::left_out($stats));
        // Someone who leaves frees a place for the next sync.
        $DB->set_field(self::T . 'reader', 'status', 'left', ['id' => $stats['ids'][0]]);
        $again = csvlist::load($entityid, array_slice($list, 1));
        $this->assertSame([1, 0], [$again['created'], $again['overlimit']]);
        $this->assertSame(2, plans::used($entityid)['readers']);
    }

    /**
     * What the plan does not bring is stopped where it happens, not only hidden.
     */
    public function test_features(): void {
        global $DB;
        $this->resetAfterTest();
        $this->site_plan('Free', 0, 0, ['lti' => false, 'connectors' => false, 'voices' => false]);
        $library = libraries::create_entity(['name' => 'Invented school']);
        $entityid = (int) $library->entityid;
        $this->assertSame(
            'The Free plan does not include recorded voices. The site\'s administrators can change it.',
            plans::without($entityid, 'voices')
        );
        $reader = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')
            ->create_reader(['entityid' => $entityid]);
        $record = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')
            ->create_record(['entityid' => $entityid]);
        $review = reviews::write($entityid, (int) $record->id, (int) $reader->id, 'A good book');
        $path = make_request_directory() . '/voice.webm';
        file_put_contents($path, 'not really a voice');
        $this->assertFalse(reviews::speak($review, $path));
        $this->assertFalse(sources\cifra::enabled($entityid));
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('does not include LTI');
        plans::require($entityid, 'lti');
    }
}
