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

namespace local_oksigeniaayvurenda\core;

/**
 * Tests for who sees a digital book and who may download it.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\core\digital_rules
 */
#[\PHPUnit\Framework\Attributes\CoversClass(digital_rules::class)]
final class digital_rules_test extends \basic_testcase {
    /**
     * Formats by name; the licence wins over the audience and the download; each one sees what is for them.
     */
    public function test_rules(): void {
        $this->assertSame('epub', digital_rules::format('El faro.EPUB'));
        $this->assertSame('audio', digital_rules::format('capitulo-1.m4a'));
        $this->assertSame('video', digital_rules::format('obra.webm'));
        $this->assertSame('other', digital_rules::format('notas.docx'));

        $this->assertTrue(digital_rules::can_be_public('pd'));
        $this->assertTrue(digital_rules::can_be_public('own'));
        $this->assertFalse(digital_rules::can_be_public('rights'));
        $this->assertFalse(digital_rules::can_be_public('made-up'));
        $this->assertTrue(digital_rules::can_download('cc-by-sa', true));
        $this->assertFalse(digital_rules::can_download('cc-by-sa', false));
        $this->assertFalse(digital_rules::can_download('rights', true));
        $this->assertSame(['students', 'staff'], digital_rules::audience(['public', 'staff', 'students', 'aliens'], 'rights'));
        $this->assertSame(['families', 'public'], digital_rules::audience(['public', 'families'], 'pd'));

        $this->assertTrue(digital_rules::can_see(['staff'], [], 'desk', null));
        $this->assertTrue(digital_rules::can_see(['public'], [], 'nobody', null));
        $this->assertFalse(digital_rules::can_see(['students'], [], 'nobody', null));
        $this->assertTrue(digital_rules::can_see(['students'], [], 'students', 'Infantil'));
        $this->assertTrue(digital_rules::can_see(['students'], ['Secundaria'], 'students', 'secundaria'));
        $this->assertFalse(digital_rules::can_see(['students'], ['Secundaria'], 'students', 'Primaria'));
        $this->assertTrue(digital_rules::can_see(['families'], ['Secundaria'], 'families', null), 'stages are for students');
        $this->assertFalse(digital_rules::can_see(['students'], [], 'families', null));
    }
}
