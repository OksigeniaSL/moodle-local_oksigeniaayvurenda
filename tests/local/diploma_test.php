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
 * Tests for the diploma of a full bingo card.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\diploma
 */
#[\PHPUnit\Framework\Attributes\CoversClass(diploma::class)]
final class diploma_test extends \advanced_testcase {
    /** @var string Prefix of the tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * A card is due the morning after its ninth square, and only then: not before it is full, not the day after. The
     * morning notes the day it filled (also for whoever never looked at their card), and the family of a young reader
     * hears once the centre turned it on; the picture, only with the art package's diploma.
     */
    public function test_due_and_run(): void {
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
        $entityid = (int) $library->entityid;
        $year = $gen->create_year(['entityid' => $entityid]);
        $now = make_timestamp(2026, 11, 10, 8);
        $child = $dg->create_user(['firstname' => 'Leo', 'lastname' => 'Inventado']);
        $reader = $gen->create_reader(['entityid' => $entityid, 'userid' => $child->id]);
        $gen->create_member(['readerid' => $reader->id, 'yearid' => $year->id, 'groupname' => '1º A']);
        $corners = ['0', '1', '2', '3', '5', '6', '7', '8', '9'];
        $id = quests::save($library, ['name' => 'El verano lector', 'start' => date('Y-m-d', $now - 9 * DAYSECS),
            'end' => date('Y-m-d', $now + 9 * DAYSECS), 'squares' => array_map(fn($c) => 'corner:' . $c, $corners)], $now);
        quests::join($id, $reader, $now - 8 * DAYSECS);
        $joinedid = diploma::joined($id, (int) $reader->id);
        $this->assertGreaterThan(0, $joinedid);
        // A book of each corner, the first eight days ago and the ninth five hours before the morning.
        foreach ($corners as $i => $code) {
            $record = $gen->create_record(['entityid' => $entityid, 'title' => 'Libro ' . $code]);
            $class = $DB->insert_record(self::T . 'authority', ['entityid' => $entityid, 'kind' => 'class',
                'code' => $code . '1', 'label' => $code, 'timecreated' => $now, 'timemodified' => $now]);
            $DB->insert_record(self::T . 'recauth', ['recordid' => $record->id, 'authorityid' => $class, 'role' => 'class',
                'sortorder' => 0]);
            $item = $gen->create_item(['libraryid' => $library->id, 'recordid' => $record->id]);
            $when = $i < 8 ? $now - (8 - $i) * DAYSECS : $now - 5 * HOURSECS;
            $gen->create_loan(['libraryid' => $library->id, 'itemid' => $item->id, 'readerid' => $reader->id,
                'yearid' => $year->id, 'timeloaned' => $when, 'timedue' => $when + 14 * DAYSECS]);
        }
        $this->assertSame([], diploma::due($now - 6 * HOURSECS));
        $due = diploma::due($now);
        $this->assertCount(1, $due);
        $this->assertSame([$joinedid, $now - 5 * HOURSECS], [$due[0]['joinedid'], $due[0]['day']]);
        $this->assertSame([], diploma::due($now + DAYSECS));
        $this->assertSame([], diploma::due($now, [$entityid + 1]));
        // The card says it was finished the day of its ninth book, as the diploma, whenever its reader looks.
        $say = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        $view = cartilla::view(quests::open($reader, $now + 2 * DAYSECS)[0], $library, $reader, $now + 2 * DAYSECS, $say);
        $this->assertSame($say('cartillafullon', zones::date($now - 5 * HOURSECS, 'strftimedate', $entityid)), $view['when']);

        $mother = $dg->create_user(['firstname' => 'Rosa']);
        $family = \local_oksigeniafamily\api::create_family();
        \local_oksigeniafamily\api::add_adult($family, ['firstname' => 'Rosa', 'email' => $mother->email,
            'userid' => (int) $mother->id]);
        \local_oksigeniafamily\api::add_adult($family, ['firstname' => 'Iván', 'email' => 'ivan.familia@example.org']);
        \local_oksigeniafamily\api::add_child($family, (int) $child->id);
        // Off until the centre turns it on; the day it filled is noted all the same.
        $this->assertFalse(diploma::on($entityid));
        $this->assertSame(0, diploma::run($now));
        $this->assertEquals($now - 5 * HOURSECS, $DB->get_field(self::T . 'questreader', 'timedone', ['id' => $joinedid]));
        diploma::save($entityid, true);
        $this->assertTrue(diploma::on($entityid));
        $messages = $this->redirectMessages();
        $emails = $this->redirectEmails();
        $told = diploma::run($now);
        $jpeg = diploma::make($joinedid, diploma::SMALL, $now);
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        if (!diploma::drawable()) {
            // Without Gaudí's diploma there is nothing to send nor to show.
            $this->assertSame([0, null, []], [$told, $jpeg, diploma::of_reader($reader, $now, $str)]);
        } else {
            $this->assertSame(2, $told);
            $tomother = array_values(array_filter(
                $messages->get_messages(),
                fn($m) => (int) $m->useridto === (int) $mother->id
            ));
            $this->assertCount(1, $tomother);
            $this->assertSame('diploma', $tomother[0]->eventtype);
            $this->assertStringContainsString('El verano lector', $tomother[0]->fullmessage);
            $this->assertStringContainsString('mailimage.php', $tomother[0]->fullmessage);
            $this->assertCount(1, array_filter($emails->get_messages(), fn($m) => $m->to === 'ivan.familia@example.org'));
            $this->assertSame("\xFF\xD8", substr((string) $jpeg, 0, 2));
            $this->assertSame(diploma::SMALL, getimagesizefromstring($jpeg)[0]);
            $this->assertCount(1, diploma::of_reader($reader, $now, $str));
        }
        $messages->close();
        $emails->close();
        $this->assertStringContainsString('s=' . mail::seal('diploma', $joinedid, 'd'), mail::diploma($joinedid));
        // Whoever has no full card has no diploma.
        $other = $gen->create_reader(['entityid' => $entityid]);
        quests::join($id, $other, $now);
        $this->assertNull(diploma::make(diploma::joined($id, (int) $other->id), 0, $now));
        $this->assertNull(diploma::of(diploma::joined($id, (int) $other->id), $now));
        if (method_exists('\\local_oksigeniafamily\\api', 'reset')) {
            \local_oksigeniafamily\api::reset();
        }
    }
}
