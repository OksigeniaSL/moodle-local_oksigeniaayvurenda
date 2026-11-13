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
 * Tests for «Su primer libro»: the family of a young reader hears of their first book, the morning after.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\firstbook
 */
#[\PHPUnit\Framework\Attributes\CoversClass(firstbook::class)]
final class firstbook_test extends \advanced_testcase {
    /**
     * Only the first book, and only the day after it went home; the second book is no news. Each adult of the family hears
     * once, through their account or to their e-mail, with the book and the way to record it with their voice. A centre
     * that does not write to families, or turned it off, sends nothing.
     */
    public function test_first(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        if (!class_exists('\\local_oksigeniafamily\\api')) {
            require_once($CFG->dirroot . '/local/oksigeniaayvurenda/tests/fixtures/family_api.php');
        }
        if (method_exists('\\local_oksigeniafamily\\api', 'reset')) {
            \local_oksigeniafamily\api::reset();
        }
        $dg = $this->getDataGenerator();
        $gen = $dg->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $year = $gen->create_year(['entityid' => $library->entityid]);
        $now = make_timestamp(2026, 11, 10, 8);
        $child = $dg->create_user(['firstname' => 'Leo', 'lastname' => 'Inventado']);
        $reader = $gen->create_reader(['entityid' => $library->entityid, 'userid' => $child->id]);
        $gen->create_member(['readerid' => $reader->id, 'yearid' => $year->id, 'groupname' => '1º A']);
        $this->assertSame('flower', cards::look_of(
            $DB->get_record('local_oksigeniaayvurenda_reader', ['id' => $reader->id]),
            $now
        ));
        $loan = fn(string $title, int $when) => $gen->create_loan(['libraryid' => $library->id, 'readerid' => $reader->id,
            'yearid' => $year->id, 'itemid' => $gen->create_item(['libraryid' => $library->id, 'recordid' =>
            $gen->create_record(['entityid' => $library->entityid, 'title' => $title])->id])->id,
            'timeloaned' => $when, 'timedue' => $when + 14 * DAYSECS]);
        $loan('El faro dormido', $now - 5 * HOURSECS);
        $loan('La nube de tinta', $now - 4 * HOURSECS);
        $this->assertSame(['El faro dormido'], array_column(firstbook::due($now), 'title'));
        $this->assertSame([], firstbook::due($now + 2 * DAYSECS));
        $mother = $dg->create_user(['firstname' => 'Rosa']);
        $family = \local_oksigeniafamily\api::create_family();
        \local_oksigeniafamily\api::add_adult($family, ['firstname' => 'Rosa', 'email' => $mother->email,
            'userid' => (int) $mother->id]);
        \local_oksigeniafamily\api::add_adult($family, ['firstname' => 'Iván', 'email' => 'ivan.familia@example.org']);
        \local_oksigeniafamily\api::add_child($family, (int) $child->id);
        // A school writes to families by default; this one has turned it off.
        firstbook::save((int) $library->entityid, false);
        $this->assertSame(0, firstbook::run($now));
        firstbook::save((int) $library->entityid, true);
        $messages = $this->redirectMessages();
        $emails = $this->redirectEmails();
        $this->assertSame(2, firstbook::run($now));
        $tomother = array_values(array_filter($messages->get_messages(), fn($m) => (int) $m->useridto === (int) $mother->id));
        $this->assertCount(1, $tomother);
        $this->assertSame('firstbook', $tomother[0]->eventtype);
        $this->assertStringContainsString('El faro dormido', $tomother[0]->fullmessage);
        $this->assertStringContainsString('Leo', $tomother[0]->subject);
        $toivan = array_values(array_filter($emails->get_messages(), fn($m) => $m->to === 'ivan.familia@example.org'));
        $this->assertCount(1, $toivan);
        // The way to record it with their voice, if the centre has voices: one link for the family.
        if (plans::allows((int) $library->entityid, 'voices')) {
            $this->assertStringContainsString('family.php', $tomother[0]->fullmessage);
            $this->assertSame(1, $DB->count_records('local_oksigeniaayvurenda_voiceinvite', ['readerid' => $reader->id]));
        }
        $messages->close();
        $emails->close();
        if (method_exists('\\local_oksigeniafamily\\api', 'reset')) {
            \local_oksigeniafamily\api::reset();
        }
    }
}
