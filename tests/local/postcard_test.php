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
 * Tests for the postcard «Mi primer libro»: drawn from a loan, a JPEG, nothing kept.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\postcard
 */
#[\PHPUnit\Framework\Attributes\CoversClass(postcard::class)]
final class postcard_test extends \advanced_testcase {
    /**
     * With the art package's postcard, a JPEG of the size of Gaudí's background; without it, none. A loan that is not
     * there, none; and its address is sealed as the other pictures of a notice.
     */
    public function test_make(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $child = $this->getDataGenerator()->create_user(['firstname' => 'Leo Andrés']);
        $reader = $gen->create_reader(['entityid' => $library->entityid, 'userid' => $child->id]);
        $loan = $gen->create_loan(['libraryid' => $library->id, 'readerid' => $reader->id, 'itemid' => $gen->create_item([
            'libraryid' => $library->id, 'recordid' => $gen->create_record(['entityid' => $library->entityid,
            'title' => 'El faro dormido'])->id])->id, 'timeloaned' => time()]);
        $this->assertNull(postcard::make((int) $loan->id + 1000));
        $jpeg = postcard::make((int) $loan->id);
        if (!screens::art('postal/huecos', 'json') || !function_exists('imagettftext')) {
            $this->assertNull($jpeg);
            return;
        }
        $this->assertSame("\xFF\xD8", substr((string) $jpeg, 0, 2));
        $this->assertSame([1200, 800], array_slice(getimagesizefromstring($jpeg), 0, 2));
        $this->assertStringContainsString('s=' . mail::seal('postcard', (int) $loan->id, 'p'), mail::postcard((int) $loan->id));
    }
}
