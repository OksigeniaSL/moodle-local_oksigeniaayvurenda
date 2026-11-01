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
 * The readers of a library, as the screen asks for them.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\readers
 */
#[\PHPUnit\Framework\Attributes\CoversClass(readers::class)]
final class readers_page_test extends \advanced_testcase {
    /**
     * Who has a group goes in it; who has none, or a member row with no group name, goes in «no group»; pages cut by name;
     * each place counts what it brought.
     */
    public function test_groups_pages_and_places(): void {
        global $DB;
        $this->resetAfterTest();
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $t = 'local_oksigeniaayvurenda_';
        $now = time();
        $year = years::around((int) $library->entityid, $now);
        $make = function (string $last, ?string $group, string $source = 'csv') use ($gen, $library, $DB, $year, $t, $now) {
            $reader = $gen->create_reader(['entityid' => $library->entityid, 'firstname' => 'Ana', 'lastname' => $last]);
            if ($group !== null) {
                $DB->insert_record($t . 'member', ['readerid' => $reader->id, 'yearid' => $year->id, 'groupname' => $group,
                    'source' => $source, 'timecreated' => $now]);
            }
            return (int) $reader->id;
        };
        $make('Alba', '3º A', 'cohort');
        $make('Brisa', '3º A', 'cohort');
        $make('Cardo', null);
        $make('Duna', '');
        $make('Estrada', null);
        $gone = $make('Faro', null);
        $DB->set_field($t . 'reader', 'status', 'left', ['id' => $gone]);
        $DB->insert_record($t . 'source', ['entityid' => $library->entityid, 'system' => 'csv', 'tablename' => 'reader',
            'externalid' => 'x', 'localid' => $gone, 'timecreated' => $now, 'timemodified' => $now]);

        $this->assertSame(5, readers::total($library));
        $groups = readers::groups($library);
        $this->assertCount(1, $groups);
        $this->assertSame('3º A', $groups[0]['name']);
        $this->assertSame(2, readers::in_group_count($library, '3º A'));
        $this->assertSame(3, readers::in_group_count($library, ''));

        $names = fn(array $list) => array_map(fn($r) => $r['name'], $list);
        $this->assertSame(['Ana Cardo', 'Ana Duna'], $names(readers::in_group($library, '', null, 2)));
        $this->assertSame(['Ana Estrada'], $names(readers::in_group($library, '', null, 2, 2)));
        $this->assertCount(3, readers::in_group($library, ''));

        $from = readers::from_sources((int) $library->entityid);
        $this->assertSame(2, $from['cohort']);
        $this->assertSame(1, $from['csv']);
        $this->assertArrayNotHasKey('abies', $from);
    }
}
