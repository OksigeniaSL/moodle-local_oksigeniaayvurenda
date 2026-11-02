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
 * A book in another format, and the readers who need accessible formats.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(formats::class)]
final class formats_test extends \advanced_testcase {
    /**
     * A centre with a library, a book and two readers with accounts.
     *
     * @return \stdClass library, record, reader, other, user, otheruser.
     */
    private function world(): \stdClass {
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $this->setAdminUser();
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $w = new \stdClass();
        $w->library = $gen->create_library();
        $entityid = (int) $w->library->entityid;
        $w->record = $gen->create_record(['entityid' => $entityid, 'title' => 'El faro dormido']);
        $w->user = $this->getDataGenerator()->create_user();
        $w->otheruser = $this->getDataGenerator()->create_user();
        $w->reader = $gen->create_reader(['entityid' => $entityid, 'userid' => $w->user->id, 'readertype' => 'teacher']);
        $w->other = $gen->create_reader(['entityid' => $entityid, 'userid' => $w->otheruser->id, 'readertype' => 'teacher']);
        return $w;
    }

    /**
     * Asked once per book, answered with an accessible item: the reader is told, and from then on sees it; nobody else does.
     */
    public function test_ask_and_serve(): void {
        global $DB;
        $w = $this->world();
        $this->assertSame(0, formats::ask($w->library, $w->reader, (int) $w->record->id, 'smoke'));
        $id = formats::ask($w->library, $w->reader, (int) $w->record->id, 'large');
        $this->assertGreaterThan(0, $id);
        $this->assertSame($id, formats::ask($w->library, $w->reader, (int) $w->record->id, 'audio'), 'one open ask per book');
        $this->assertSame('audio', $DB->get_field('local_oksigeniaayvurenda_formatask', 'format', ['id' => $id]));
        $this->assertSame(1, formats::waiting($w->library));
        $this->assertCount(1, formats::of_library($w->library));

        $item = digital::add_link($w->library, (int) $w->record->id, 'https://example.org/faro-daisy', ['title' => 'DAISY',
            'licence' => 'rights', 'audience' => ['staff', 'students'], 'accessible' => 1], 2);
        $this->assertSame([$item], array_map(fn($i) => (int) $i->id, formats::items_of($w->library, (int) $w->record->id)));
        $entityid = (int) $w->library->entityid;
        $seen = fn(int $userid) => count(digital::of_record(
            (int) $w->record->id,
            $w->library,
            digital::viewer($entityid, $userid)
        ));
        $this->assertSame(0, $seen((int) $w->user->id), 'not marked yet');
        $this->assertSame(1, $seen(2), 'the library sees it');

        $sink = $this->redirectMessages();
        $this->assertTrue(formats::answer($id, $w->library, true, $item, 2));
        $this->assertFalse(formats::answer($id, $w->library, true, $item, 2), 'once');
        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertSame('formatready', $messages[0]->eventtype);
        $this->assertEquals($w->user->id, $messages[0]->useridto);
        $this->assertSame('served', $DB->get_field('local_oksigeniaayvurenda_formatask', 'status', ['id' => $id]));
        $this->assertEquals(1, $DB->get_field('local_oksigeniaayvurenda_reader', 'accessneeds', ['id' => $w->reader->id]));
        $this->assertSame(1, $seen((int) $w->user->id));
        $this->assertSame(0, $seen((int) $w->otheruser->id));
        $this->assertSame(0, formats::waiting($w->library));
        $this->assertCount(1, formats::of_library($w->library, true));
        $this->assertSame('served', formats::of_reader((int) $w->reader->id)[0]['status']);

        // The item goes: the ask keeps its answer, without it.
        digital::delete($item, $w->library);
        $this->assertNull($DB->get_field('local_oksigeniaayvurenda_formatask', 'digitalid', ['id' => $id]));
    }

    /**
     * Declined, the reader is told too; marking by hand, both ways; another centre's library answers nothing.
     */
    public function test_decline_mark_and_centres(): void {
        global $DB;
        $w = $this->world();
        $id = formats::ask($w->library, $w->reader, (int) $w->record->id, 'braille');
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $elsewhere = $gen->create_library();
        $this->assertFalse(formats::answer($id, $elsewhere, false, 0, 2));
        $this->assertSame(0, formats::ask($elsewhere, $w->reader, (int) $w->record->id, 'large'), 'not their reader');
        $sink = $this->redirectMessages();
        $this->assertTrue(formats::answer($id, $w->library, false, 0, 2));
        $this->assertCount(1, $sink->get_messages());
        $this->assertSame('declined', $DB->get_field('local_oksigeniaayvurenda_formatask', 'status', ['id' => $id]));
        $this->assertEquals(0, $DB->get_field('local_oksigeniaayvurenda_reader', 'accessneeds', ['id' => $w->reader->id]));

        formats::mark($w->library, (int) $w->other->id, true);
        $this->assertEquals(1, $DB->get_field('local_oksigeniaayvurenda_reader', 'accessneeds', ['id' => $w->other->id]));
        formats::mark($elsewhere, (int) $w->other->id, false);
        $this->assertEquals(1, $DB->get_field('local_oksigeniaayvurenda_reader', 'accessneeds', ['id' => $w->other->id]));
        formats::mark($w->library, (int) $w->other->id, false);
        $this->assertEquals(0, $DB->get_field('local_oksigeniaayvurenda_reader', 'accessneeds', ['id' => $w->other->id]));
    }

    /**
     * An accessible format never goes to the public window, whatever its licence.
     */
    public function test_never_public(): void {
        global $DB;
        $w = $this->world();
        $item = digital::add_link($w->library, (int) $w->record->id, 'https://example.org/faro-mp3', ['title' => 'Audio',
            'licence' => 'pd', 'audience' => ['public', 'students'], 'accessible' => 1], 2);
        $this->assertSame('students', $DB->get_field('local_oksigeniaayvurenda_digital', 'audience', ['id' => $item]));
        digital::update($item, $w->library, ['title' => 'Audio', 'licence' => 'pd', 'audience' => ['public'],
            'accessible' => 1]);
        $this->assertSame('staff', $DB->get_field('local_oksigeniaayvurenda_digital', 'audience', ['id' => $item]));
        digital::update($item, $w->library, ['title' => 'Audio', 'licence' => 'pd', 'audience' => ['public']]);
        $this->assertSame('public', $DB->get_field('local_oksigeniaayvurenda_digital', 'audience', ['id' => $item]));
    }
}
