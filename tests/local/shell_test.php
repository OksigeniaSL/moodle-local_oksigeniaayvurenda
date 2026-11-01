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
 * Tests for the frame of every page: the site's notice.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\shell
 */
#[\PHPUnit\Framework\Attributes\CoversClass(shell::class)]
final class shell_test extends \advanced_testcase {
    /**
     * Nothing until the site writes a notice; then the notice, as text and never as markup.
     */
    public function test_site_notice(): void {
        $this->resetAfterTest();
        $this->assertSame('', shell::site_notice());
        set_config('sitenotice', '   ', 'local_oksigeniaayvurenda');
        $this->assertSame('', shell::site_notice());
        set_config('sitenotice', 'Demo abierta: <b>todo</b> se borra cada noche.', 'local_oksigeniaayvurenda');
        $html = shell::site_notice();
        $this->assertStringContainsString('class="ayv-sitenotice"', $html);
        $this->assertStringContainsString('Demo abierta: &lt;b&gt;todo&lt;/b&gt; se borra cada noche.', $html);
        $this->assertStringNotContainsString('<b>', $html);
    }
}
