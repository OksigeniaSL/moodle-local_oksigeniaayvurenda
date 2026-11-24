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
 * «Propose an improvement»: for whoever works in the library, and only where the site has Oksigenia Marandu.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @category   test
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\improve
 */
#[\PHPUnit\Framework\Attributes\CoversClass(improve::class)]
final class improve_test extends \advanced_testcase {
    /**
     * Its staff, whoever keeps a library of the centre and the tutor of a class work here; a reader, another centre's staff
     * and a guest do not.
     */
    public function test_works_here(): void {
        $this->resetAfterTest();
        $dg = $this->getDataGenerator();
        $gen = $dg->get_plugin_generator('local_oksigeniaayvurenda');
        $central = $gen->create_library(['slug' => 'central']);
        $entityid = (int) $central->entityid;
        $room = $gen->create_library(['entityid' => $entityid, 'slug' => 'aula', 'kind' => 'classroom',
            'parentid' => $central->id]);
        $year = years::around($entityid, time());
        $reader = $dg->create_user();
        $card = $gen->create_reader(['entityid' => $entityid, 'userid' => $reader->id]);
        $gen->create_member(['readerid' => $card->id, 'yearid' => $year->id, 'groupname' => '3.º A']);
        $teacher = $dg->create_user();
        $gen->create_reader(['entityid' => $entityid, 'userid' => $teacher->id, 'readertype' => 'teacher']);
        $tutor = $dg->create_user();
        $staff = $dg->create_user();
        role_assign(roles::ensure_librarian(), $staff->id, access::context($entityid)->id);

        $this->assertTrue(improve::works_here($central, (int) $staff->id));
        $this->assertFalse(improve::works_here($central, (int) $reader->id));
        $this->assertFalse(improve::works_here($central, (int) $teacher->id));
        $this->assertFalse(improve::works_here($central, (int) $tutor->id));
        $this->assertFalse(improve::works_here($central, 0));
        $this->assertFalse(improve::works_here($central, (int) guest_user()->id));

        keepers::add($room, (int) $teacher->id, 2);
        $this->assertTrue(improve::works_here($central, (int) $teacher->id));
        tutors::set((int) $year->id, '3.º A', (int) $tutor->id);
        $this->assertTrue(improve::works_here($central, (int) $tutor->id));
        $other = $gen->create_library(['entityid' => $gen->create_entity()->id, 'slug' => 'otra']);
        $this->assertFalse(improve::works_here($other, (int) $teacher->id));
        $this->assertFalse(improve::works_here($other, (int) $tutor->id));
    }

    /**
     * Without Marandu, or for who does not work here, the page has no trace of it; with it, the button goes in «More» and
     * at the end of the page, and the data that Marandu reads is on the page. Never inside a guided help's frame.
     */
    public function test_on_the_page(): void {
        global $PAGE;
        $this->resetAfterTest();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $PAGE->set_url(new \moodle_url('/local/oksigeniaayvurenda/index.php'));
        $page = function () use ($library): string {
            ob_start();
            shell::output('Prueba «uno»', '<p>Algo</p>', $library);
            return (string) ob_get_clean();
        };
        $api = '\local_oksigeniamarandu\api';
        $there = class_exists($api) && method_exists($api, 'proposal_assets');
        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertNull(improve::assets($library, (int) $GLOBALS['USER']->id));
        $this->assertStringNotContainsString('data-marandu-proposal', $page());
        $this->setAdminUser();
        $this->assertSame($there, improve::assets($library, (int) $GLOBALS['USER']->id) !== null);
        $this->assertNull(improve::assets(null, (int) $GLOBALS['USER']->id));
        $html = $page();
        $this->assertSame($there ? 2 : 0, substr_count($html, 'data-marandu-proposal="local_oksigeniaayvurenda"'));
        $this->assertSame($there ? 1 : 0, substr_count($html, 'id="marandu-proposal"'));
        $_GET['help'] = 1;
        $this->assertStringNotContainsString('data-marandu-proposal', $page());
        unset($_GET['help']);
        if ($there) {
            $this->assertStringContainsString('data-screen="Prueba «uno»"', $html);
        }
    }
}
