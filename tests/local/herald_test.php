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
 * Tests for the herald at work: the book of the week, a word at the desk, the reminder to the young.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\screens
 * @covers     \local_oksigeniaayvurenda\local\desk
 * @covers     \local_oksigeniaayvurenda\local\notices
 */
#[\PHPUnit\Framework\Attributes\CoversClass(screens::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(desk::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(notices::class)]
final class herald_test extends \advanced_testcase {
    /**
     * The book most readers took this week, from anonymity::FEW readers on, with «several» rather than the number below
     * anonymity::SAY.
     */
    public function test_week_book(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $record = $gen->create_record(['entityid' => $library->entityid, 'title' => 'El faro dormido']);
        $now = time();
        $lend = function () use ($gen, $library, $record, $now) {
            $reader = $gen->create_reader(['entityid' => $library->entityid]);
            $item = $gen->create_item(['libraryid' => $library->id, 'recordid' => $record->id]);
            $gen->create_loan(['libraryid' => $library->id, 'itemid' => $item->id, 'readerid' => $reader->id,
                'timeloaned' => $now - DAYSECS]);
        };
        for ($i = 1; $i < anonymity::FEW; $i++) {
            $lend();
        }
        $this->assertNull(screens::week_book($library));
        $lend();
        $said = screens::week_book($library);
        $this->assertStringContainsString('El faro dormido', $said['text']);
        $this->assertStringContainsString('several', $said['text']);
        $this->assertStringNotContainsString((string) anonymity::FEW, $said['text']);
        $this->assertStringContainsString('record.php', $said['url']);
    }

    /**
     * A book that comes back gets a word of the herald at the desk; a textbook bank says nothing.
     */
    public function test_desk_word(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $reader = $gen->create_reader(['entityid' => $library->entityid, 'cardcode' => '10000H']);
        $item = $gen->create_item(['libraryid' => $library->id]);
        $this->setAdminUser();
        $desk = new desk($library, (int) get_admin()->id, time());
        $desk->scan('10000H');
        $lent = $desk->scan($item->barcode, (int) $reader->id);
        $this->assertSame('lent', $lent['kind']);
        $back = $desk->scan($item->barcode);
        $this->assertSame('returned', $back['kind']);
        $this->assertNotEmpty($back['herald']['text']);
        $bank = $gen->create_library(['entityid' => $library->entityid, 'kind' => 'bank']);
        $other = $gen->create_item(['libraryid' => $bank->id]);
        $bankdesk = new desk($bank, (int) get_admin()->id, time());
        $bankdesk->scan($other->barcode, (int) $reader->id);
        $this->assertNull($bankdesk->scan($other->barcode)['herald'] ?? null);
    }

    /**
     * A young reader (the card with the flower) hears of a late book from the herald, by her name.
     */
    public function test_young_reminder(): void {
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $dg = $this->getDataGenerator();
        $gen = $dg->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $now = time();
        $year = years::around((int) $library->entityid, $now);
        $user = $dg->create_user(['firstname' => 'Lucía']);
        $reader = $gen->create_reader(['entityid' => $library->entityid, 'userid' => $user->id, 'readertype' => 'student']);
        $gen->create_member(['readerid' => $reader->id, 'yearid' => $year->id, 'groupname' => '2º Primaria B',
            'stage' => 'Primaria']);
        $this->assertSame('flower', cards::look_of($reader, $now));
        $item = $gen->create_item(['libraryid' => $library->id, 'recordid' => $gen->create_record(['entityid' =>
            $library->entityid, 'title' => 'La nube de tinta'])->id]);
        $gen->create_loan(['libraryid' => $library->id, 'itemid' => $item->id, 'readerid' => $reader->id,
            'yearid' => $year->id, 'timeloaned' => $now - 20 * DAYSECS, 'timedue' => $now - 3 * DAYSECS]);
        $sink = $this->redirectMessages();
        notices::run($now);
        $sent = array_values(array_filter($sink->get_messages(), fn($m) => $m->eventtype === 'overdue'));
        $herald = characters::of((int) $library->entityid)['herald']['name'];
        $this->assertCount(1, $sent);
        $this->assertStringContainsString('Lucía', $sent[0]->fullmessage);
        // The herald says it in one of its two ways, a day each (jue-humor H26): its name, or the days the book is late.
        $this->assertTrue(
            str_contains($sent[0]->fullmessage, $herald) || str_contains($sent[0]->fullmessage, '3'),
            $sent[0]->fullmessage
        );
        $this->assertStringContainsString('La nube de tinta', $sent[0]->fullmessage);
        $sink->close();
    }
}
