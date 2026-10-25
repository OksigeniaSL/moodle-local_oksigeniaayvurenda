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
 * Tests for how libraries and characters look: their own pictures, colours, lines and the featured library.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\images
 * @covers     \local_oksigeniaayvurenda\local\libraries
 */
#[\PHPUnit\Framework\Attributes\CoversClass(images::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(libraries::class)]
final class looks_test extends \advanced_testcase {
    /**
     * A picture is kept and replaced; what is not a picture is refused; the portrait of a centre wins over the
     * default.
     */
    public function test_pictures(): void {
        $this->resetAfterTest();
        $dir = make_request_directory();
        $png = $dir . '/a.png';
        $image = imagecreatetruecolor(8, 8);
        imagepng($image, $png);
        $text = $dir . '/b.png';
        file_put_contents($text, 'not a picture');
        $this->assertFalse(images::store('portrait', 5, 'memory', $text));
        $this->assertNull(images::url('portrait', 5, 'memory'));
        $this->assertTrue(images::store('portrait', 5, 'memory', $png));
        $this->assertStringContainsString('/portrait/5/memory.png', images::url('portrait', 5, 'memory'));
        $this->assertTrue(images::store('portrait', 5, 'memory', $png));
        $this->assertCount(1, get_file_storage()->get_area_files(
            \context_system::instance()->id,
            'local_oksigeniaayvurenda',
            'portrait',
            5,
            'filename',
            false
        ));
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $this->assertFalse(characters::of((int) $library->entityid)['memory']['own']);
        images::store('portrait', (int) $library->entityid, 'memory', $png);
        $this->assertTrue(characters::of((int) $library->entityid)['memory']['own']);
        images::remove('portrait', 5, 'memory');
        $this->assertNull(images::url('portrait', 5, 'memory'));
    }

    /**
     * A library's colour and line; featured, it is the only one of its entity; the featured library to show first is
     * the person's own, then the site's featured one, then a central one.
     */
    public function test_look_and_featured(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $central = $generator->create_library(['kind' => 'central']);
        $classroom = $generator->create_library(['entityid' => $central->entityid, 'kind' => 'classroom']);
        $other = $generator->create_library(['kind' => 'central']);
        libraries::save_look((int) $classroom->id, ['colour' => 9, 'motto' => '  El lugar de la palabra ', 'featured' => 1]);
        $look = libraries::look($DB->get_record('local_oksigeniaayvurenda_library', ['id' => $classroom->id]));
        $this->assertSame([5, 'El lugar de la palabra', true, null], [$look['colour'], $look['motto'], $look['featured'],
            $look['image']]);
        libraries::save_look((int) $central->id, ['featured' => 1]);
        $after = $DB->get_record('local_oksigeniaayvurenda_library', ['id' => $classroom->id]);
        $this->assertFalse(libraries::look($after)['featured']);

        $all = array_values($DB->get_records('local_oksigeniaayvurenda_library', null, 'id'));
        $this->assertSame((int) $central->id, (int) libraries::featured($all, 0)->id);
        $user = $this->getDataGenerator()->create_user();
        $generator->create_reader(['entityid' => $other->entityid, 'userid' => $user->id]);
        $this->assertSame((int) $other->id, (int) libraries::featured($all, (int) $user->id)->id);
        $this->assertNull(libraries::featured([], 0));
    }
}
