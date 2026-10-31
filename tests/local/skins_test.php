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
 * Tests for the skin of a centre's library.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\skins
 */
#[\PHPUnit\Framework\Attributes\CoversClass(skins::class)]
final class skins_test extends \advanced_testcase {
    /**
     * A centre is a school until it chooses; at a university or academy its characters are a portrait and a signature,
     * standing nowhere whole and with no voice; back to a school, they come alive again.
     */
    public function test_skin(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $entityid = (int) $library->entityid;
        $this->assertSame('school', skins::of($entityid));
        $this->assertTrue(skins::lively($entityid));
        libraries::update_entity($entityid, ['skin' => 'university']);
        $this->assertSame('university', skins::of($entityid));
        $this->assertFalse(skins::lively($entityid));
        $said = characters::say($entityid, 'herald', 'heraldnew', [], 'saluda');
        $this->assertNull($said['figure']);
        $this->assertSame('', $said['puppet']);
        $this->assertSame(characters::of($entityid)['herald']['portrait'], $said['portrait']);
        libraries::update_entity($entityid, ['skin' => 'nonsense']);
        $this->assertSame('school', skins::of($entityid));
        $this->assertSame(characters::figure('herald', 'saluda'), characters::say(
            $entityid,
            'herald',
            'heraldnew',
            [],
            'saluda'
        )['figure']);
    }

    /**
     * What is on comes from the skin, and the centre changes it: only what differs is kept, and a new skin brings its
     * own back. With the visit off there is no way in; with the figures on, a university's characters stand whole.
     */
    public function test_play(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $entityid = (int) $library->entityid;
        $this->assertSame(skins::PLAY['school'], skins::play($entityid));
        libraries::update_entity($entityid, ['skin' => 'university']);
        $this->assertSame(
            ['visit' => true, 'games' => true, 'figures' => false, 'voices' => false, 'sound' => false],
            skins::play($entityid)
        );
        $this->assertSame(['figures' => true], skins::changes('university', ['visit', 'games', 'figures']));
        libraries::update_entity($entityid, ['skin' => 'university', 'play' => ['games', 'figures']]);
        $this->assertSame(
            ['visit' => false, 'figures' => true],
            array_diff_assoc(skins::play($entityid), skins::PLAY['university'])
        );
        $this->assertTrue(skins::lively($entityid));
        $this->assertFalse(visit::available(null, $entityid));
        $this->assertNull(visit::card($library, false));
        // Another skin starts with what it brings.
        libraries::update_entity($entityid, ['skin' => 'secondary', 'play' => ['games']]);
        $this->assertSame(skins::PLAY['secondary'], skins::play($entityid));
    }

    /**
     * What is said to a reader comes in the tone of the centre's skin; where a skin has no words of its own, the
     * school's.
     */
    public function test_words(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $entityid = (int) $library->entityid;
        $this->assertSame('noticereaderbody', skins::words($entityid, 'noticereaderbody'));
        libraries::update_entity($entityid, ['skin' => 'university']);
        $this->assertSame('noticereaderbodyuniversity', skins::words($entityid, 'noticereaderbody'));
        $this->assertSame('holdreadysubjectuniversity', skins::words($entityid, 'holdreadysubject'));
        $this->assertSame('noticetutorbody', skins::words($entityid, 'noticetutorbody'));
    }

    /**
     * The reader's card speaks in the tone of the skin: a secondary school's and a university's say the same in their
     * own words.
     */
    public function test_card_words(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $entityid = (int) $library->entityid;
        $reader = $gen->create_reader(['entityid' => $entityid]);
        $year = years::around($entityid, time());
        $said = fn() => garden::classic($reader, $year)['back'];
        $this->assertSame(get_string('cardnovisit', 'local_oksigeniaayvurenda'), $said());
        libraries::update_entity($entityid, ['skin' => 'secondary']);
        $this->assertSame(get_string('cardnovisitsecondary', 'local_oksigeniaayvurenda'), $said());
        libraries::update_entity($entityid, ['skin' => 'university']);
        $this->assertSame(get_string('cardnovisituniversity', 'local_oksigeniaayvurenda'), $said());
        // The memory too, when it has nothing.
        $nothing = characters::say($entityid, 'memory', 'memorynothing', ['query' => 'faro']);
        $this->assertSame(get_string('memorynothinguniversity', 'local_oksigeniaayvurenda', (object) ['query' => 'faro',
            'name' => $nothing['name']]), $nothing['text']);
    }
}
