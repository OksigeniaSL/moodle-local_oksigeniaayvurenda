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

use local_oksigeniaayvurenda\core\desk\sanction_rules;

/**
 * Tests for the rules with every setting in sight: each gesture changes only what it sends.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\rulesheet
 */
#[\PHPUnit\Framework\Attributes\CoversClass(rulesheet::class)]
final class rulesheet_test extends \advanced_testcase {
    /**
     * Sends a gesture as the page would.
     *
     * @param \stdClass $library
     * @param array $sent
     * @return string Where the page goes back to.
     */
    private function send(\stdClass $library, array $sent): string {
        $_POST = $sent;
        $anchor = rulesheet::apply($library);
        $_POST = [];
        return $anchor;
    }

    /**
     * A rule: a day more, no limit, only in the library and back with the general days; a new one for any reader
     * («any» is a star, not a kind of reader called «any»), the same one twice is the one there; and taken away.
     */
    public function test_rules(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        rules::save((int) $library->id, [
            ['readertype' => '*', 'itemtype' => '*', 'loandays' => 14, 'maxloans' => 2, 'renewals' => 1, 'renewdays' => 7],
            ['readertype' => 'student', 'itemtype' => '*', 'loandays' => 7, 'maxloans' => 1, 'renewals' => 0, 'renewdays' => 7],
        ]);
        $at = fn(string $who, string $what) => array_search(true, array_map(fn($r) => $r['readertype'] === $who
            && $r['itemtype'] === $what, rules::of((int) $library->id)), true);
        $student = $at('student', '*');
        $this->assertSame('ayv-nr-norma-' . $student, $this->send($library, ['rule' => (string) $student, 'loandays' => 8]));
        $rule = rules::of((int) $library->id)[$student];
        $this->assertSame([8, 1, 0], [(int) $rule['loandays'], (int) $rule['maxloans'], (int) $rule['renewals']]);
        $this->send($library, ['rule' => (string) $student, 'maxloans' => 0]);
        $this->assertSame(0, (int) rules::of((int) $library->id)[$student]['maxloans']);
        $this->send($library, ['rule' => (string) $student, 'loandays' => 0]);
        $this->assertSame(0, (int) rules::of((int) $library->id)[$student]['loandays']);
        // Back home, with the days of the general rule, as its light sends them.
        $context = rulesheet::context($library, 'https://example.com/rules.php');
        $card = array_values(array_filter($context['cards'], fn($c) => $c['index'] === (string) $student))[0];
        $this->assertSame(14, $card['lights'][0]['value']);
        $this->assertSame([], $card['steps']);

        $this->send($library, ['rule' => 'new', 'readertype' => 'any', 'itemtype' => 'device']);
        $this->assertNotFalse($at('*', 'device'));
        $this->assertFalse($at('any', 'device'));
        $count = count(rules::of((int) $library->id));
        $this->assertSame('ayv-nr-norma-' . $at('*', 'device'), $this->send($library, ['rule' => 'new',
            'readertype' => 'any', 'itemtype' => 'device']));
        $this->assertCount($count, rules::of((int) $library->id));
        // A rule cannot become the twin of another: it stays as it was.
        $this->send($library, ['rule' => (string) $at('*', 'device'), 'itemtype' => 'any']);
        $this->assertNotFalse($at('*', 'device'));
        $this->assertSame('ayv-nr-las', $this->send($library, ['rule' => (string) $at('*', 'device'), 'remove' => 1]));
        $this->assertFalse($at('*', 'device'));
    }

    /**
     * The notices and what a late book leaves: each gesture keeps the rest; some dates with none yet start with the
     * thirds of the year; a stage alone, then all of them again; a currency that is none is not taken.
     */
    public function test_settings(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $entityid = (int) $library->entityid;
        $this->assertSame('ayv-nr-avisos', $this->send($library, ['set' => 'notices', 'noticeafter' => 3]));
        $settings = sanctions::settings($entityid);
        $this->assertSame([3, 'week', true], [$settings['noticeafter'], $settings['noticeevery'], $settings['noticetutors']]);
        $this->send($library, ['set' => 'notices', 'noticetutors' => 0]);
        $this->send($library, ['set' => 'notices', 'noticeevery' => 'dates']);
        $settings = sanctions::settings($entityid);
        $this->assertSame([3, 'dates', false], [$settings['noticeafter'], $settings['noticeevery'], $settings['noticetutors']]);
        $this->assertNotEmpty($settings['noticedates']);
        $this->send($library, ['set' => 'notices', 'dates' => 1, 'noticedates' => ['2026-12-18', '']]);
        $this->assertSame(['12-18'], sanctions::settings($entityid)['noticedates']);

        $this->assertSame('ayv-nr-tarde', $this->send($library, ['set' => 'late', 'mode' => sanction_rules::FINE]));
        $this->send($library, ['set' => 'late', 'cents' => 15]);
        $this->send($library, ['set' => 'late', 'currency' => 'usd']);
        $this->send($library, ['set' => 'late', 'currency' => 'xyz']);
        $settings = sanctions::settings($entityid);
        $this->assertSame([sanction_rules::FINE, 15, 'USD', 3], [$settings['mode'], $settings['cents'], $settings['currency'],
            $settings['noticeafter']]);
        $stages = holds::stages($entityid, time());
        if (count($stages) > 1) {
            $this->send($library, ['set' => 'late', 'stage' => '0']);
            $this->assertSame([$stages[0]], sanctions::settings($entityid)['stages']);
            $this->send($library, ['set' => 'late', 'stage' => 'all']);
            $this->assertSame([], sanctions::settings($entityid)['stages']);
        }
        $this->send($library, ['set' => 'late', 'adults' => 0]);
        $this->assertFalse(sanctions::settings($entityid)['adults']);
        $this->send($library, ['set' => 'renew', 'renewfrom' => 'due']);
        $this->assertSame('due', rules::renew_from($entityid));
    }
}
