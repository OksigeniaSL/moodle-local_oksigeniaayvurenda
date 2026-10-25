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
 * Tests for who may do what in each centre, with two centres on one site that must not see each other.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\access
 */
#[\PHPUnit\Framework\Attributes\CoversClass(access::class)]
final class access_test extends \advanced_testcase {
    /**
     * Two centres as on the shared site: each with its category and its librarian given the role there, a reader with
     * an account and a book lent in each; the authenticated user does not look up libraries at site level.
     *
     * @param bool $closed Whether the site took looking up away from the authenticated user, as its closures say.
     * @return \stdClass a and b (libraries), la and lb (librarians), ra and rb (readers' users), readera and readerb,
     *     itema and itemb, outsider.
     */
    private function two_centres(bool $closed = true): \stdClass {
        global $DB;
        set_config('entitycategories', 1, 'local_oksigeniaayvurenda');
        $gen = $this->getDataGenerator();
        $plugin = $gen->get_plugin_generator('local_oksigeniaayvurenda');
        $w = new \stdClass();
        $librarian = roles::ensure_librarian();
        foreach (['a', 'b'] as $side) {
            $w->$side = libraries::create_entity(['name' => 'Invented centre ' . strtoupper($side)]);
            $staff = $gen->create_user(['username' => 'l' . $side]);
            role_assign($librarian, $staff->id, access::context($w->$side)->id);
            $w->{'l' . $side} = $staff;
            $user = $gen->create_user(['username' => 'r' . $side]);
            $reader = $plugin->create_reader(['entityid' => $w->$side->entityid, 'userid' => $user->id]);
            $w->{'r' . $side} = $user;
            $w->{'reader' . $side} = $reader;
            $item = $plugin->create_item(['libraryid' => $w->$side->id]);
            $w->{'item' . $side} = $item;
            $plugin->create_loan(['libraryid' => $w->$side->id, 'itemid' => $item->id, 'readerid' => $reader->id]);
        }
        $w->outsider = $gen->create_user(['username' => 'outsider']);
        if ($closed) {
            $userrole = (int) $DB->get_field('role', 'id', ['shortname' => 'user']);
            unassign_capability('local/oksigeniaayvurenda:view', $userrole, \context_system::instance()->id);
        }
        accesslib_clear_all_caches_for_unit_testing();
        access::forget();
        return $w;
    }

    /**
     * Each centre has its own category, and its librarian works there and nowhere else.
     */
    public function test_staff_only_in_their_centre(): void {
        $this->resetAfterTest();
        $w = $this->two_centres();
        $this->assertSame(CONTEXT_COURSECAT, access::context($w->a)->contextlevel);
        $this->assertNotSame(access::context($w->a)->id, access::context($w->b)->id);
        foreach (['view', 'desk', 'catalogue'] as $cap) {
            $cap = 'local/oksigeniaayvurenda:' . $cap;
            $this->assertTrue(access::can($cap, $w->a, (int) $w->la->id), $cap);
            $this->assertFalse(access::can($cap, $w->b, (int) $w->la->id), $cap);
            $this->assertTrue(access::can($cap, $w->b, (int) $w->lb->id), $cap);
            $this->assertFalse(access::can($cap, $w->a, (int) $w->lb->id), $cap);
        }
        $this->assertFalse(access::can('local/oksigeniaayvurenda:manage', $w->a, (int) $w->la->id));
        $this->assertSame([(int) $w->a->entityid], access::entities('local/oksigeniaayvurenda:desk', (int) $w->la->id));
        $this->assertSame([(int) $w->a->id], array_map(fn($l) => (int) $l->id, catalogue::visible((int) $w->la->id)));

        // Someone given a role on the whole site works in every centre.
        $manager = $this->getDataGenerator()->create_user();
        role_assign(
            (int) $GLOBALS['DB']->get_field('role', 'id', ['shortname' => 'manager']),
            $manager->id,
            \context_system::instance()->id
        );
        $this->assertTrue(access::can('local/oksigeniaayvurenda:manage', $w->a, (int) $manager->id));
        $this->assertTrue(access::can('local/oksigeniaayvurenda:manage', $w->b, (int) $manager->id));
    }

    /**
     * A reader looks up the libraries of their centre only; someone of no centre, none.
     */
    public function test_readers_see_their_centre(): void {
        $this->resetAfterTest();
        $w = $this->two_centres();
        $view = 'local/oksigeniaayvurenda:view';
        $this->assertTrue(access::can($view, $w->a, (int) $w->ra->id));
        $this->assertFalse(access::can($view, $w->b, (int) $w->ra->id));
        $this->assertFalse(access::can('local/oksigeniaayvurenda:desk', $w->a, (int) $w->ra->id));
        $this->assertSame([(int) $w->a->id], array_map(fn($l) => (int) $l->id, catalogue::visible((int) $w->ra->id)));
        $this->assertSame([], catalogue::visible((int) $w->outsider->id));
        $this->assertFalse(access::can($view, null, (int) $w->outsider->id));
        // A reader who leaves stops seeing it.
        $GLOBALS['DB']->set_field('local_oksigeniaayvurenda_reader', 'status', 'left', ['userid' => $w->ra->id]);
        access::forget();
        $this->assertFalse(access::can($view, $w->a, (int) $w->ra->id));
    }

    /**
     * With centres apart, the authenticated user's grant on the whole site opens no centre, even if the site forgot to
     * take it away; a role given in a centre's category does.
     */
    public function test_apart_without_the_closure(): void {
        $this->resetAfterTest();
        $w = $this->two_centres(false);
        $view = 'local/oksigeniaayvurenda:view';
        $this->assertFalse(access::can($view, $w->b, (int) $w->ra->id));
        $this->assertFalse(access::can($view, $w->a, (int) $w->outsider->id));
        $this->assertFalse(access::can($view, null, (int) $w->outsider->id));
        $this->assertSame([], catalogue::visible((int) $w->outsider->id));
        $this->assertTrue(access::can($view, $w->b, (int) $w->lb->id));
        // A teacher of centre B who is not a reader, given a role there.
        $teacher = $this->getDataGenerator()->create_user();
        $role = $this->getDataGenerator()->create_role();
        assign_capability($view, CAP_ALLOW, $role, \context_system::instance()->id);
        role_assign($role, $teacher->id, access::context($w->b)->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertTrue(access::can($view, $w->b, (int) $teacher->id));
        $this->assertFalse(access::can($view, $w->a, (int) $teacher->id));
    }

    /**
     * Ids of the other centre go nowhere: its readers at the desk, undoing its records, its places, its published
     * voices, its platforms.
     */
    public function test_ids_of_the_other_centre(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->two_centres();
        $plugin = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $desk = new desk($w->a, (int) $w->la->id);
        $this->assertNotEmpty($desk->reader((int) $w->readera->id));
        try {
            $desk->reader_panel((int) $w->readerb->id);
            $this->fail('A reader of the other centre at the desk.');
        } catch (\dml_missing_record_exception $e) {
            $this->assertStringContainsString('local_oksigeniaayvurenda_reader', $e->getMessage());
        }
        // Undoing an addition with the other centre's record touches nothing of it.
        $this->assertFalse(cataloguer::undo((int) $w->a->entityid, (int) $w->itemb->recordid, [(int) $w->itemb->id], true));
        $this->assertTrue($DB->record_exists('local_oksigeniaayvurenda_record', ['id' => $w->itemb->recordid]));
        // A place of the other centre is no place here.
        $place = $DB->insert_record('local_oksigeniaayvurenda_authority', ['entityid' => $w->b->entityid,
            'kind' => 'location', 'label' => 'Shelf B', 'timecreated' => time(), 'timemodified' => time()]);
        $this->assertNull(cataloguer::own_location((int) $w->a->entityid, (int) $place));
        $this->assertSame((int) $place, cataloguer::own_location((int) $w->b->entityid, (int) $place));
        // A published voice of the other centre is not heard; another reader of its own centre hears it.
        $review = (object) ['id' => 0, 'entityid' => $w->b->entityid, 'readerid' => $w->readerb->id,
            'status' => 'published'];
        $DB->set_field('local_oksigeniaayvurenda_reader', 'readertype', 'teacher', ['id' => $w->readerb->id]);
        $neighbour = $this->getDataGenerator()->create_user();
        $plugin->create_reader(['entityid' => $w->b->entityid, 'userid' => $neighbour->id]);
        access::forget();
        $this->assertFalse(reviews::can_hear($review, (int) $w->ra->id));
        $this->assertTrue(reviews::can_hear($review, (int) $neighbour->id));
        $this->assertTrue(reviews::can_hear($review, (int) $w->lb->id));
    }

    /**
     * A centre brings readers only from the cohorts and courses of its category.
     */
    public function test_cohorts_and_courses_of_the_centre(): void {
        $this->resetAfterTest();
        $w = $this->two_centres();
        $gen = $this->getDataGenerator();
        $categorya = access::context($w->a)->instanceid;
        $mine = $gen->create_cohort(['contextid' => access::context($w->a)->id, 'name' => 'Cohort A']);
        $theirs = $gen->create_cohort(['contextid' => access::context($w->b)->id, 'name' => 'Cohort B']);
        $site = $gen->create_cohort(['name' => 'Cohort of the site']);
        $entityid = (int) $w->a->entityid;
        $this->assertSame([(int) $mine->id], array_column(sources\cohorts::options($entityid), 'id'));
        sources\cohorts::save($entityid, [$mine->id => ['type' => 'student'], $theirs->id => ['type' => 'student'],
            $site->id => ['type' => 'student']]);
        $this->assertSame([(int) $mine->id], array_column(array_filter(
            sources\cohorts::options($entityid),
            fn($c) => $c['chosen']
        ), 'id'));
        $course = $gen->create_course(['category' => $categorya]);
        $other = $gen->create_course(['category' => access::context($w->b)->instanceid]);
        $this->assertSame([(int) $course->id], array_column(sources\courses::options($entityid), 'id'));
        sources\courses::save($entityid, [$course->id => [], $other->id => []]);
        $this->assertSame([(int) $course->id], array_keys(sources\courses::chosen($entityid)));
    }

    /**
     * A platform registered by one centre cannot be registered by another: people log in by it.
     */
    public function test_platform_of_one_centre(): void {
        $this->resetAfterTest();
        $w = $this->two_centres();
        $platform = ['name' => 'Invented platform', 'issuer' => 'https://lms.invented.example', 'clientid' => 'one',
            'authloginurl' => 'https://lms.invented.example/auth', 'keyseturl' => 'https://lms.invented.example/jwks'];
        ltitool::save_platform((int) $w->a->entityid, $platform);
        ltitool::save_platform((int) $w->a->entityid, ['clientid' => 'two'] + $platform);
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('ltiissuertaken', 'local_oksigeniaayvurenda'));
        ltitool::save_platform((int) $w->b->entityid, ['clientid' => 'three'] + $platform);
    }

    /**
     * On a site with one centre and roles given on the whole site, nothing changes: everyone with an account looks it
     * up and the librarian works in it.
     */
    public function test_one_centre_site(): void {
        $this->resetAfterTest();
        $library = libraries::create_entity(['name' => 'Invented school']);
        $this->assertSame(CONTEXT_SYSTEM, access::context($library)->contextlevel);
        $user = $this->getDataGenerator()->create_user();
        $staff = $this->getDataGenerator()->create_user();
        role_assign(roles::ensure_librarian(), $staff->id, \context_system::instance()->id);
        $this->assertTrue(access::can('local/oksigeniaayvurenda:view', $library, (int) $user->id));
        $this->assertTrue(access::can('local/oksigeniaayvurenda:desk', $library, (int) $staff->id));
        $this->assertFalse(access::can('local/oksigeniaayvurenda:desk', $library, (int) $user->id));
    }

    /**
     * The daily notice of what waits goes to each centre's own staff, never to the other's.
     */
    public function test_staff_notices(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->two_centres();
        $plugin = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $record = $plugin->create_record(['entityid' => $w->a->entityid]);
        $reader = $DB->get_record('local_oksigeniaayvurenda_reader', ['userid' => $w->ra->id], '*', MUST_EXIST);
        reviews::write((int) $w->a->entityid, (int) $record->id, (int) $reader->id, 'A good book');
        $sink = $this->redirectMessages();
        staffnotices::run();
        $this->assertSame([(int) $w->la->id], array_map(fn($m) => (int) $m->useridto, $sink->get_messages()));
    }

    /**
     * Giving a centre its category is done once, and the practice centre of the help is for whoever works anywhere.
     */
    public function test_category_and_practice(): void {
        $this->resetAfterTest();
        $w = $this->two_centres();
        $categoryid = access::category((int) $w->a->entityid);
        $this->assertSame($categoryid, access::category((int) $w->a->entityid));
        $this->assertSame(0, (int) \core_course_category::get($categoryid, MUST_EXIST, true)->visible);
        $practice = practice::reset();
        $this->assertTrue(access::can('local/oksigeniaayvurenda:desk', $practice, (int) $w->la->id));
        $this->assertFalse(access::can('local/oksigeniaayvurenda:desk', $practice, (int) $w->outsider->id));
    }
}
