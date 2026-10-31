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
 * Tests for readers renewing their own loans online.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\renewals
 */
#[\PHPUnit\Framework\Attributes\CoversClass(renewals::class)]
final class renewals_test extends \advanced_testcase {
    /**
     * Off by default; on, a reader renews their own loan once (as their rules say), not someone else's, not while
     * another reader waits for the book, and never in a textbook bank. The app's service says why when it cannot.
     */
    public function test_mine(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $now = time();
        $DB->insert_record('local_oksigeniaayvurenda_rule', ['libraryid' => $library->id, 'readertype' => '*', 'itemtype' => '*',
            'loandays' => 14, 'maxloans' => 0, 'renewals' => 1, 'renewdays' => 7, 'timecreated' => $now, 'timemodified' => $now]);
        $ana = $this->getDataGenerator()->create_user();
        $reader = $gen->create_reader(['entityid' => $library->entityid, 'userid' => $ana->id, 'readertype' => 'teacher']);
        $other = $gen->create_reader(['entityid' => $library->entityid, 'readertype' => 'teacher']);
        $item = $gen->create_item(['libraryid' => $library->id, 'status' => 'onloan']);
        $loan = $gen->create_loan(['libraryid' => $library->id, 'itemid' => $item->id, 'readerid' => $reader->id,
            'timedue' => $now + DAYSECS]);
        $this->assertSame(get_string('renewnotallowed', 'local_oksigeniaayvurenda'), renewals::mine(
            (int) $loan->id,
            (int) $ana->id
        )['message']);
        $this->assertFalse(mine::summary((int) $ana->id)[0]['loans'][0]['canrenew']);
        holds::save_settings((int) $library->entityid, ['renewonline' => true]);
        $this->assertTrue(mine::summary((int) $ana->id)[0]['loans'][0]['canrenew']);
        // In the app, the button that asks the service.
        $this->setUser($ana);
        $html = \local_oksigeniaayvurenda\output\mobile::mobile_library_view([])['templates'][0]['html'];
        $this->assertStringContainsString('local_oksigeniaayvurenda_renew_loan', $html);
        $this->assertSame(get_string('renewnotyours', 'local_oksigeniaayvurenda'), renewals::mine(
            (int) $loan->id,
            (int) $this->getDataGenerator()->create_user()->id
        )['message']);
        // Another reader waits for it: not now.
        $hold = $DB->insert_record('local_oksigeniaayvurenda_hold', ['libraryid' => $library->id, 'recordid' => $item->recordid,
            'readerid' => $other->id, 'status' => 'waiting', 'timecreated' => $now, 'timemodified' => $now]);
        $this->assertFalse(renewals::mine((int) $loan->id, (int) $ana->id)['ok']);
        $DB->delete_records('local_oksigeniaayvurenda_hold', ['id' => $hold]);
        // The book's page in the app: she has it, until when, and more time; not a button to ask for it.
        $this->setUser($ana);
        $page = \local_oksigeniaayvurenda\output\mobile::mobile_record_view(['library' => $library->slug,
            'id' => $item->recordid])['templates'][0]['html'];
        $this->assertStringContainsString('local_oksigeniaayvurenda_renew_loan', $page);
        $this->assertStringNotContainsString('local_oksigeniaayvurenda_ask_hold', $page);
        $renewed = renewals::mine((int) $loan->id, (int) $ana->id);
        $this->assertTrue($renewed['ok']);
        $this->assertGreaterThan($now + DAYSECS, $renewed['due']);
        // No renewal left: no button.
        $this->assertFalse(mine::summary((int) $ana->id)[0]['loans'][0]['canrenew']);
        // Once, as the rules say; the app's service says why not.
        $this->setUser($ana);
        try {
            \local_oksigeniaayvurenda\external\renew_loan::execute((int) $loan->id);
            $this->fail('A second renewal went through');
        } catch (\moodle_exception $e) {
            $this->assertSame('renewfailed', $e->errorcode);
        }
        // Never in a textbook bank.
        $DB->set_field('local_oksigeniaayvurenda_library', 'kind', bank::KIND, ['id' => $library->id]);
        $this->assertFalse(holds::renew_online(
            $DB->get_record('local_oksigeniaayvurenda_library', ['id' => $library->id]),
            (int) $reader->id,
            $now
        ));
    }
}
