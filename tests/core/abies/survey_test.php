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

namespace local_oksigeniaayvurenda\core\abies;

/**
 * Tests for the survey of an Abies export before importing it.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\core\abies\survey
 */
#[\PHPUnit\Framework\Attributes\CoversClass(survey::class)]
final class survey_test extends \basic_testcase {
    /**
     * It counts what comes and finds what the librarian should know.
     */
    public function test_run(): void {
        $report = survey::run(new export_reader(__DIR__ . '/../../fixtures/abies/small.xml'));
        $this->assertSame(['Libro' => 3, 'Audio' => 1], $report['records']);
        $this->assertSame(6, $report['items']);
        $this->assertSame(1, $report['readers']);
        $this->assertSame(1, $report['openloans']);
        $issues = $report['issues'];
        $this->assertSame(['100002K' => 2], $issues['duplicatebarcodes']);
        $this->assertSame(['100005A'], $issues['badletters']);
        $this->assertSame(['100004T'], $issues['orphanitems']);
        $this->assertSame(1, $issues['recordswithoutitems']);
        $this->assertSame(1, $issues['orphanauthorlinks']);
        $this->assertSame(1, $issues['itemswithoutlocation']);
        $this->assertSame(['klingon' => 1], $issues['unknownlanguages']);
        $this->assertSame(['4' => 1], $issues['unknownstatuses']);
        $this->assertSame(1, $issues['recordswithisbn']);
    }
}
