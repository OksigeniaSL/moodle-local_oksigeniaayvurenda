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
 * Tests for the library's weekly news and the reader's own goal.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\newsletter
 * @covers     \local_oksigeniaayvurenda\local\goals
 */
#[\PHPUnit\Framework\Attributes\CoversClass(newsletter::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(goals::class)]
final class newsletter_test extends \advanced_testcase {
    /**
     * Only who signed up gets the news, once a week at most and only when something came, from the libraries and not
     * from a textbook bank; and nothing while the centre is open only to its staff.
     */
    public function test_news(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $entityid = (int) $library->entityid;
        $bank = libraries::create_library($entityid, ['name' => 'Banco de libros', 'kind' => 'bank']);
        $user = $this->getDataGenerator()->create_user();
        $gen->create_reader(['entityid' => $entityid, 'userid' => $user->id]);
        $now = time();
        $novel = $gen->create_record(['entityid' => $entityid, 'title' => 'El faro dormido']);
        $textbook = $gen->create_record(['entityid' => $entityid, 'title' => 'Matemáticas 1']);
        $gen->create_item(['libraryid' => $library->id, 'recordid' => $novel->id]);
        $gen->create_item(['libraryid' => $bank->id, 'recordid' => $textbook->id]);
        $sink = $this->redirectMessages();
        $this->assertSame(['subscribers' => 0, 'sent' => 0], newsletter::run($now));
        newsletter::subscribe((int) $user->id, true);
        $this->assertTrue(newsletter::subscribed((int) $user->id));
        $this->assertSame(['subscribers' => 1, 'sent' => 1], newsletter::run($now));
        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertSame('newbooks', $messages[0]->eventtype);
        $this->assertStringContainsString('El faro dormido', $messages[0]->fullmessage);
        $this->assertStringNotContainsString('Matemáticas', $messages[0]->fullmessage);
        $this->assertStringContainsString('/local/oksigeniaayvurenda/index.php?library=', $messages[0]->contexturl);
        // Not again the same week, and not without anything new.
        $this->assertSame(0, newsletter::run($now + DAYSECS)['sent']);
        $this->assertSame(0, newsletter::run($now + 8 * DAYSECS)['sent']);
        access::set_closed($entityid, true);
        $gen->create_item(['libraryid' => $library->id, 'recordid' => $novel->id]);
        $this->assertSame(0, newsletter::run($now + 9 * DAYSECS)['sent']);
        newsletter::subscribe((int) $user->id, false);
        $this->assertSame(['subscribers' => 0, 'sent' => 0], newsletter::run($now + 30 * DAYSECS));
    }

    /**
     * A goal is the reader's own, for one school year; it counts the books taken home, once each and without the
     * textbooks, and only the last three years are kept.
     */
    public function test_goal(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $entityid = (int) $library->entityid;
        $bank = libraries::create_library($entityid, ['name' => 'Banco de libros', 'kind' => 'bank']);
        $year = $gen->create_year(['entityid' => $entityid] +
            \local_oksigeniaayvurenda\core\school_year::around(time(), '09-01', \core_date::get_server_timezone_object()));
        $user = $this->getDataGenerator()->create_user();
        $reader = $gen->create_reader(['entityid' => $entityid, 'userid' => $user->id]);
        $novel = $gen->create_record(['entityid' => $entityid, 'title' => 'El faro dormido']);
        $textbook = $gen->create_record(['entityid' => $entityid, 'title' => 'Matemáticas 1']);
        $copy = $gen->create_item(['libraryid' => $library->id, 'recordid' => $novel->id]);
        $bankcopy = $gen->create_item(['libraryid' => $bank->id, 'recordid' => $textbook->id]);
        foreach ([[$library, $copy], [$library, $copy], [$bank, $bankcopy]] as [$where, $item]) {
            $gen->create_loan(['libraryid' => $where->id, 'itemid' => $item->id, 'readerid' => $reader->id,
                'yearid' => $year->id, 'timeloaned' => time() - DAYSECS]);
        }
        $this->assertSame(0, goals::of((int) $user->id, (int) $year->id));
        goals::set((int) $user->id, (int) $year->id, 20);
        $this->assertSame(20, goals::of((int) $user->id, (int) $year->id));
        $this->assertSame(1, goals::done((int) $reader->id, (int) $year->id));
        goals::set((int) $user->id, (int) $year->id, 1000);
        $this->assertSame(goals::MOST, goals::of((int) $user->id, (int) $year->id));
        foreach ([1, 2, 3] as $older) {
            goals::set((int) $user->id, (int) $year->id - $older, 5);
        }
        $this->assertSame(goals::MOST, goals::of((int) $user->id, (int) $year->id));
        $this->assertSame(0, goals::of((int) $user->id, (int) $year->id - 3));
        goals::set((int) $user->id, (int) $year->id, 0);
        $this->assertSame(0, goals::of((int) $user->id, (int) $year->id));
    }
}
