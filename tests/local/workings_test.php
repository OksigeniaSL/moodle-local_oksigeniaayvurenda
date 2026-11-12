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
 * Tests for «How your library works»: each gesture changes only what it touches.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\workings
 */
#[\PHPUnit\Framework\Attributes\CoversClass(workings::class)]
final class workings_test extends \advanced_testcase {
    /**
     * Sends a gesture as the page would.
     *
     * @param \stdClass $library
     * @param array $post
     * @return string The anchor it goes back to.
     */
    private function gesture(\stdClass $library, array $post): string {
        $_POST = $post;
        $anchor = workings::apply($library);
        $_POST = [];
        return $anchor;
    }

    /**
     * The rules of the library: a figure changed with + leaves the rest, a new rule starts as the general one, two rules
     * for the same readers are not made, and one goes when taken away.
     */
    public function test_rules(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        rules::save((int) $library->id, [['readertype' => '*', 'itemtype' => '*', 'loandays' => 14, 'maxloans' => 2,
            'renewals' => 1, 'renewdays' => 7]]);
        $this->assertSame('ayv-hiw-rule-0', $this->gesture($library, ['rule' => '0', 'loandays' => '15']));
        $rule = rules::of((int) $library->id)[0];
        $this->assertSame(15, (int) $rule['loandays']);
        $this->assertSame(2, (int) $rule['maxloans']);
        $this->gesture($library, ['rule' => 'new', 'readertype' => 'teacher']);
        $all = rules::of((int) $library->id);
        $this->assertCount(2, $all);
        $teacher = array_values(array_filter($all, fn($r) => $r['readertype'] === 'teacher'))[0];
        $this->assertSame(15, (int) $teacher['loandays']);
        // The same readers and copies as another rule: nothing changes.
        $at = (string) array_search($teacher, $all, true);
        $this->gesture($library, ['rule' => $at, 'readertype' => 'any']);
        $this->assertCount(1, array_filter(rules::of((int) $library->id), fn($r) => $r['readertype'] === '*'));
        $this->gesture($library, ['rule' => $at, 'remove' => '1']);
        $this->assertCount(1, rules::of((int) $library->id));
    }

    /**
     * The centre's settings: the door, a light, the class challenge and the card of a stage, the classification, the
     * start of the year and the name, each alone.
     */
    public function test_centre(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $entityid = (int) $library->entityid;
        access::set_closed($entityid, true);
        $this->assertSame('ayv-hiw-door', $this->gesture($library, ['door' => '1']));
        $this->assertFalse(access::closed($entityid));
        $this->gesture($library, ['play' => ['sound' => '1']]);
        $this->assertTrue(skins::play($entityid)['sound']);
        $this->gesture($library, ['play' => ['sound' => '0']]);
        $this->assertFalse(skins::play($entityid)['sound']);
        $this->assertSame('ayv-hiw-classification', $this->gesture($library, ['set' => ['classification' => 'dewey']]));
        $settings = json_decode((string) $DB->get_field('local_oksigeniaayvurenda_entity', 'settings', ['id' => $entityid]), true);
        $this->assertSame('dewey', $settings['classification']);
        $this->gesture($library, ['set' => ['startmonth' => '2']]);
        $this->assertSame('02-01', $DB->get_field('local_oksigeniaayvurenda_entity', 'yearstart', ['id' => $entityid]));
        $this->gesture($library, ['set' => ['startday' => '31']]);
        $this->assertSame(
            '02-28',
            $DB->get_field('local_oksigeniaayvurenda_entity', 'yearstart', ['id' => $entityid]),
            'the last day February has'
        );
        $name = $DB->get_field('local_oksigeniaayvurenda_entity', 'name', ['id' => $entityid]);
        $this->gesture($library, ['set' => ['name' => '']]);
        $this->assertSame($name, $DB->get_field('local_oksigeniaayvurenda_entity', 'name', ['id' => $entityid]));
        $this->gesture($library, ['set' => ['name' => 'Biblioteca de la Plaza']]);
        $this->assertSame('Biblioteca de la Plaza', $DB->get_field('local_oksigeniaayvurenda_entity', 'name', ['id' => $entityid]));
        // Nothing of the rest moved.
        $settings = json_decode((string) $DB->get_field('local_oksigeniaayvurenda_entity', 'settings', ['id' => $entityid]), true);
        $this->assertSame('dewey', $settings['classification']);
        $this->assertFalse(access::closed($entityid));
    }

    /**
     * The page says it all in sentences, and the one just changed says so.
     */
    public function test_context(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        rules::save((int) $library->id, [['readertype' => 'student', 'itemtype' => '*', 'loandays' => 7, 'maxloans' => 1,
            'renewals' => 2, 'renewdays' => 7]]);
        $data = workings::context($library, 'https://example.com', 'ayv-hiw-door');
        $this->assertCount(3, $data['shelves']);
        $phrases = array_merge(...array_column($data['shelves'], 'phrases'));
        $ids = array_column($phrases, 'id');
        foreach (
            ['ayv-hiw-rule-0', 'ayv-hiw-rule-new', 'ayv-hiw-door', 'ayv-hiw-lights', 'ayv-hiw-sign', 'ayv-hiw-history',
            'ayv-hiw-photos', 'ayv-hiw-skin', 'ayv-hiw-name', 'ayv-hiw-year', 'ayv-hiw-classification', 'ayv-hiw-letter',
            'ayv-hiw-zone'] as $id
        ) {
            $this->assertContains($id, $ids);
        }
        $this->assertStringContainsString('<b>7', $phrases[0]['says']);
        $this->assertTrue(array_values(array_filter($phrases, fn($p) => $p['id'] === 'ayv-hiw-door'))[0]['justsaved']);
        $html = $GLOBALS['OUTPUT']->render_from_template('local_oksigeniaayvurenda/howitworks', $data);
        $this->assertStringContainsString('ayv-hiw-rule-0-f', $html);
    }
}
