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

namespace local_oksigeniaayvurenda;

/**
 * Every capability of the plugin has its name in each language.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
final class capabilities_test extends \advanced_testcase {
    /**
     * Moodle looks for «oksigeniaayvurenda:<name>»: without it, a refused page shows a notice instead of its message.
     */
    public function test_names(): void {
        $capabilities = [];
        include(__DIR__ . '/../db/access.php');
        foreach (['en', 'es'] as $lang) {
            $string = [];
            include(__DIR__ . '/../lang/' . $lang . '/local_oksigeniaayvurenda.php');
            foreach (array_keys($capabilities) as $capability) {
                $this->assertArrayHasKey(str_replace('local/', '', $capability), $string, $lang . ' ' . $capability);
            }
        }
    }
}
