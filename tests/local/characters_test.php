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
 * Tests for the two characters.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\characters
 */
#[\PHPUnit\Framework\Attributes\CoversClass(characters::class)]
final class characters_test extends \advanced_testcase {
    /**
     * They come as the two of Ayvurenda; each centre names them, gives them a sex the words agree with, or turns them
     * off; the herald signs the notices when asked.
     */
    public function test_characters(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $entityid = (int) $library->entityid;
        $all = characters::of($entityid);
        $this->assertSame(
            [get_string('memorydefaultname', 'local_oksigeniaayvurenda'), 'Mainumby'],
            [$all['memory']['name'], $all['herald']['name']]
        );
        $this->assertTrue($all['memory']['enabled']);
        $this->assertFalse($all['herald']['signs']);
        $said = characters::say($entityid, 'memory', 'memorynothing', ['query' => 'dragones']);
        // The words that agree with her sex.
        $this->assertSame(get_string('memorynothingf', 'local_oksigeniaayvurenda', (object) ['query' => 'dragones',
            'name' => $all['memory']['name']]), $said['text']);
        $this->assertStringContainsString('«dragones»', $said['text']);

        characters::save($entityid, [
            'memory' => ['enabled' => 1, 'name' => ' Abuelo Tilo ', 'sex' => 'm'],
            'herald' => ['enabled' => 0, 'name' => '', 'sex' => 'x', 'signs' => 1],
        ]);
        $all = characters::of($entityid);
        $this->assertSame('Abuelo Tilo', $all['memory']['name']);
        $this->assertSame(get_string('memorynothingm', 'local_oksigeniaayvurenda', (object) ['query' => 'x',
            'name' => 'Abuelo Tilo']), characters::say($entityid, 'memory', 'memorynothing', ['query' => 'x'])['text']);
        $this->assertSame('Mainumby', $all['herald']['name']);
        // A sex that is not one of the two leaves the herald as he comes.
        $this->assertSame('m', $all['herald']['sex']);
        $this->assertNull(characters::say($entityid, 'herald', 'heraldnew'));
        $this->assertTrue(json_decode(
            $DB->get_field('local_oksigeniaayvurenda_entity', 'settings', ['id' => $entityid]),
            true
        )['characters']['herald']['signs']);
    }

    /**
     * The herald signs the notice to a reader when the centre wants.
     */
    public function test_signature(): void {
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $dg = $this->getDataGenerator();
        $gen = $dg->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $year = $gen->create_year(['entityid' => $library->entityid]);
        $now = make_timestamp(2026, 11, 10, 12);
        $user = $dg->create_user(['firstname' => 'Ana']);
        $reader = $gen->create_reader(['entityid' => $library->entityid, 'userid' => $user->id]);
        $gen->create_loan(['libraryid' => $library->id, 'readerid' => $reader->id, 'yearid' => $year->id,
            'itemid' => $gen->create_item(['libraryid' => $library->id])->id, 'timeloaned' => $now - 20 * DAYSECS,
            'timedue' => $now - 5 * DAYSECS]);
        characters::save((int) $library->entityid, ['memory' => ['enabled' => 1],
            'herald' => ['enabled' => 1, 'name' => 'Pía', 'sex' => 'f', 'signs' => 1]]);
        $sink = $this->redirectMessages();
        notices::run($now);
        $messages = $sink->get_messages();
        $sink->close();
        $this->assertCount(1, $messages);
        $this->assertStringEndsWith(
            get_string('heraldsignf', 'local_oksigeniaayvurenda', (object) ['name' => 'Pía']),
            $messages[0]->fullmessage
        );
    }

    /**
     * A character's puppet comes only with the art package's puppet: without it, nothing, and the pose stays.
     */
    public function test_puppet(): void {
        $this->resetAfterTest();
        $dir = \core_component::get_component_directory('local_oksigeniaayvurendaarte');
        $whole = $dir && is_readable($dir . '/pix/marionetas/memoria.anim.json');
        $puppet = characters::puppet('memory', 'senala');
        if (!$whole) {
            $this->assertSame('', $puppet);
            return;
        }
        $this->assertSame('point', json_decode($puppet, true)['gesture']);
        $this->assertSame('arrive', json_decode(characters::puppet('herald', 'saluda'), true)['gesture'] ?? 'arrive');
    }

    /**
     * A herald of the other sex keeps Gaudí's hummingbird when the art package has no portrait of her: never the
     * initial while there is a drawing.
     */
    public function test_portrait_of_either_sex(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $entityid = (int) $library->entityid;
        characters::save($entityid, ['herald' => ['name' => 'Rosa', 'sex' => 'f', 'enabled' => true]]);
        $herald = characters::of($entityid)['herald'];
        $drawn = screens::art('pregonero');
        $this->assertSame(screens::art('pregonero-f') ?? $drawn, $herald['portrait']);
        $this->assertSame('R', $herald['initial']);
    }

    /**
     * The characters grow with the reader: a reader of the flower sees the flower's, one of secondary and the adults
     * the sky's; whoever is not a reader of the centre (the staff, a visitor), the flower's. Without the sky's drawing,
     * the flower's, and its puppet goes with it.
     */
    public function test_step(): void {
        $this->resetAfterTest();
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $entityid = (int) $library->entityid;
        $year = $gen->create_year(['entityid' => $entityid]);
        $people = [];
        foreach (
            [
                'young' => ['student', 'Primaria', '3º Primaria A'],
                'older' => ['student', 'ESO', '2º ESO B'],
                'teacher' => ['teacher', '', ''],
            ] as $who => [$type, $stage, $group]
        ) {
            $user = $this->getDataGenerator()->create_user();
            $reader = $gen->create_reader(['entityid' => $entityid, 'userid' => $user->id, 'readertype' => $type]);
            if ($stage !== '') {
                $gen->create_member(['readerid' => $reader->id, 'yearid' => $year->id, 'stage' => $stage,
                    'groupname' => $group]);
            }
            $people[$who] = $user;
        }
        $this->setAdminUser();
        $this->assertSame('', characters::step($entityid), 'the staff see the flower\'s');
        $this->setUser($people['young']);
        $this->assertSame('', characters::step($entityid));
        \cache::make('local_oksigeniaayvurenda', 'access')->purge();
        $this->setUser($people['older']);
        $this->assertSame('cielo', characters::step($entityid));
        $this->setUser($people['teacher']);
        $this->assertSame('cielo', characters::step($entityid));
        $this->assertSame('', characters::step($entityid + 1), 'not a reader of another centre');
        $this->setUser(0);
        $this->assertSame('', characters::step($entityid));

        $dir = \core_component::get_component_directory('local_oksigeniaayvurendaarte');
        if (!$dir || !is_readable($dir . '/pix/personajes/memoria-cielo-senala.webp')) {
            $this->assertSame(characters::figure('memory', 'senala'), characters::figure('memory', 'senala', 'cielo'));
            $this->assertSame(characters::puppet('memory', 'senala'), characters::puppet('memory', 'senala', 'cielo'));
        } else {
            $this->assertStringContainsString('memoria-cielo-senala', characters::figure('memory', 'senala', 'cielo'));
        }
    }

    /**
     * A centre gives its own character whole, in a zip: it then stands in its own poses and moves as its own puppet;
     * taken away, Ayvurenda's comes back. What is not a piece with its name is not read.
     */
    public function test_own_pack(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $entityid = (int) $library->entityid;
        $dir = make_request_directory();
        $picture = $dir . '/p.png';
        $image = imagecreatetruecolor(8, 8);
        imagepng($image, $picture);
        $zip = new \ZipArchive();
        $zip->open($dir . '/rosita.zip', \ZipArchive::CREATE);
        foreach (['retrato', 'saluda', 'senala', 'celebra', 'lee', 'piensa', 'marioneta'] as $piece) {
            $zip->addFile($picture, 'rosita/' . $piece . '.png');
        }
        foreach (['atlas', 'rig', 'anim'] as $part) {
            $zip->addFromString('rosita/marioneta.' . $part . '.json', '{"made": "up"}');
        }
        $zip->addFromString('rosita/../../config.php', '<?php');
        $zip->addFromString('rosita/marioneta.rig.json.bak', 'no');
        $zip->close();
        $this->assertSame(10, characters::store_pack($entityid, 'herald', $dir . '/rosita.zip'));
        $this->assertNull(characters::store_pack($entityid, 'herald', $picture));
        $this->assertTrue(characters::of($entityid)['herald']['own']);
        $said = characters::say($entityid, 'herald', 'heraldnew', [], 'saluda');
        $this->assertStringContainsString('/herald-saluda.png', $said['figure']);
        $puppet = json_decode($said['puppet'], true);
        $this->assertSame('arrive', $puppet['gesture']);
        $this->assertStringContainsString('/herald-marioneta.atlas.json', $puppet['atlas']);
        $this->assertStringContainsString('/herald.png', characters::of($entityid)['herald']['portrait']);
        characters::remove_pack($entityid, 'herald');
        $this->assertFalse(characters::of($entityid)['herald']['own']);
        $said = characters::say($entityid, 'herald', 'heraldnew', [], 'saluda');
        $this->assertSame(characters::figure('herald', 'saluda'), $said['figure']);
    }
}
