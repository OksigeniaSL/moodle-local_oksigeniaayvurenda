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
 * Tests for the home of a library: what the class reads and the ideas to start a search.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\catalogue
 */
#[\PHPUnit\Framework\Attributes\CoversClass(catalogue::class)]
final class home_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * A book two of a group borrowed is what the class reads; one only one borrowed is not shown, nor one of another
     * group. The ideas start with the subject of the reader's last book.
     */
    public function test_class_reads_and_ideas(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $generator->create_library();
        $entityid = (int) $library->entityid;
        $year = years::around($entityid, time());
        $readers = [];
        foreach (['3.º B', '3.º B', '3.º B', '4.º A'] as $i => $group) {
            $readers[$i] = $generator->create_reader(['entityid' => $entityid]);
            $generator->create_member(['readerid' => $readers[$i]->id, 'yearid' => $year->id, 'groupname' => $group]);
        }
        $animals = $DB->insert_record(self::T . 'authority', ['entityid' => $entityid, 'kind' => 'subject', 'label' => 'Animales',
            'timecreated' => time(), 'timemodified' => time()]);
        $sea = $DB->insert_record(self::T . 'authority', ['entityid' => $entityid, 'kind' => 'subject', 'label' => 'El mar',
            'timecreated' => time(), 'timemodified' => time()]);
        $book = function (string $title, int $subject) use ($generator, $entityid, $library, $DB) {
            $record = $generator->create_record(['entityid' => $entityid, 'title' => $title]);
            $DB->insert_record(self::T . 'recauth', ['recordid' => $record->id, 'authorityid' => $subject, 'role' => 'subject',
                'sortorder' => 0]);
            return $generator->create_item(['libraryid' => $library->id, 'recordid' => $record->id]);
        };
        $faro = $book('El faro', $animals);
        $isla = $book('La isla', $sea);
        $lend = fn($item, $reader, $ago) => $generator->create_loan(['libraryid' => $library->id, 'itemid' => $item->id,
            'readerid' => $reader->id, 'timeloaned' => time() - $ago, 'timereturned' => time() - $ago + DAYSECS]);
        $lend($faro, $readers[0], 3 * DAYSECS);
        $lend($faro, $readers[1], 2 * DAYSECS);
        $lend($isla, $readers[2], DAYSECS);
        $lend($isla, $readers[3], DAYSECS);

        $reads = catalogue::class_reads($library, '3.º B', (int) $year->id);
        $this->assertSame(['El faro'], array_column($reads, 'title'));
        $this->assertSame([], catalogue::class_reads($library, '4.º A', (int) $year->id));

        $ideas = catalogue::ideas($library, time() - 30 * DAYSECS, (int) $readers[2]->id);
        $this->assertSame('El mar', $ideas[0]['label']);
        $this->assertTrue($ideas[0]['last']);
        $this->assertSame(['El mar', 'Animales'], array_column($ideas, 'label'));
        $this->assertFalse(catalogue::ideas($library, time() - 30 * DAYSECS)[0]['last']);
    }

    /**
     * The home of a reader with an account: greeted by name, with their card as it grows, and what their class reads;
     * a guest gets the library's name and no card.
     */
    public function test_home_of_a_reader(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $generator->create_library();
        $user = $this->getDataGenerator()->create_user(['firstname' => 'Leo']);
        $generator->create_reader(['entityid' => $library->entityid, 'userid' => $user->id]);
        $this->setUser($user);
        $home = screens::home($library);
        $this->assertSame(get_string('homehello', 'local_oksigeniaayvurenda', 'Leo'), $home['hero']['greeting']);
        $this->assertNotEmpty($home['hero']['you']['title']);
        $this->assertNull($home['classblock']);
        $this->setGuestUser();
        $home = screens::home($library);
        $this->assertNull($home['hero']['you']);
        $this->assertSame(format_string($library->name), $home['hero']['greeting']);
    }

    /**
     * «Listen» only finds clips inside the art package's voices, and has nothing to say without them.
     */
    public function test_listen_paths(): void {
        $this->assertNull(screens::spoken('../../config'));
        $this->assertNull(screens::spoken('ui/es/../../x'));
        $this->assertNull(screens::spoken('nobody/es/nothing'));
        $this->assertNull(screens::listen(['nobody/{lang}/nothing', 'none/{lang}/at-all']));
    }
}
