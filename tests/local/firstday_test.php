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
 * Tests for the first day: the sample library, «Your first day here» and the notice to whoever is given a place.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\firstday
 */
#[\PHPUnit\Framework\Attributes\CoversClass(firstday::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(sample::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(welcome::class)]
final class firstday_test extends \advanced_testcase {
    /**
     * The sample library is made with one touch, full and closed, in English on an English site; it is not the site's own
     * library (start here stays, no steps to open), and it goes whole; on a site of several centres it is not offered.
     */
    public function test_sample(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->assertTrue(sample::allowed());
        $this->assertNotNull(libraries::sample_offer());
        $library = sample::make();
        $entityid = (int) $library->entityid;
        $this->assertTrue(sample::is($entityid));
        $this->assertTrue(access::closed($entityid));
        $this->assertGreaterThan(500, $DB->count_records('local_oksigeniaayvurenda_item', ['entityid' => $entityid]));
        $this->assertGreaterThan(0, $DB->count_records('local_oksigeniaayvurenda_loan', ['libraryid' => $library->id]));
        $title = $DB->get_field_sql(
            'SELECT title FROM {local_oksigeniaayvurenda_record} WHERE entityid = ? ORDER BY id',
            [$entityid],
            IGNORE_MULTIPLE
        );
        $this->assertMatchesRegularExpression('/^(The|A) /', $title);
        $this->assertTrue(libraries::none());
        $this->assertNull(libraries::sample_offer());
        $this->assertFalse(opening::shows($library, (int) get_admin()->id));
        $this->assertStringContainsString('ayv-sample-said', sample::line($library));
        $this->assertSame((int) $library->id, (int) sample::make()->id);

        sample::remove();
        $this->assertNull(sample::library());
        $this->assertFalse($DB->record_exists('local_oksigeniaayvurenda_entity', ['id' => $entityid]));
        set_config('entitycategories', 1, 'local_oksigeniaayvurenda');
        $this->assertFalse(sample::allowed());
    }

    /**
     * Someone new at the desk of an open library sees their first day until they have lent, taken back and printed;
     * not in a library still closed, and not someone who does not work at its desk.
     */
    public function test_first_day(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $generator->create_library();
        $new = $this->getDataGenerator()->create_user();
        role_assign(roles::ensure_librarian(), $new->id, access::context((int) $library->entityid)->id);
        $reader = $this->getDataGenerator()->create_user();
        $this->assertNull(firstday::context($library, (int) $reader->id));
        $context = firstday::context($library, (int) $new->id);
        $this->assertSame('0 of 3', $context['count']);
        $this->assertTrue($context['steps'][0]['now']);

        $item = $generator->create_item(['libraryid' => $library->id]);
        $holder = $generator->create_reader(['entityid' => $library->entityid]);
        $generator->create_loan(['libraryid' => $library->id, 'itemid' => $item->id, 'readerid' => $holder->id,
            'staffid' => $new->id, 'returnstaffid' => $new->id, 'timereturned' => time()]);
        $this->assertSame(['lend' => true, 'giveback' => true, 'cards' => false], firstday::done($library, (int) $new->id));
        $this->assertSame('2 of 3', firstday::context($library, (int) $new->id)['count']);

        access::set_closed((int) $library->entityid, true);
        $this->assertNull(firstday::context($library, (int) $new->id));
    }

    /**
     * Given the librarian's role, or the desk of one library, someone hears it, with the library's name and the way to
     * its desk; nobody hears it for giving it to themselves.
     */
    public function test_welcome(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $generator->create_library(['name' => 'Riverside Library']);
        $new = $this->getDataGenerator()->create_user();
        $sink = $this->redirectMessages();
        role_assign(roles::ensure_librarian(), $new->id, access::context((int) $library->entityid)->id);
        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertSame((int) $new->id, (int) $messages[0]->useridto);
        $this->assertStringContainsString('Riverside Library', $messages[0]->subject);
        $this->assertStringContainsString('desk.php', $messages[0]->contexturl);
        $sink->clear();
        $this->assertFalse(welcome::send((int) get_admin()->id, $library, (int) get_admin()->id));
        $this->assertCount(0, $sink->get_messages());
    }
}
