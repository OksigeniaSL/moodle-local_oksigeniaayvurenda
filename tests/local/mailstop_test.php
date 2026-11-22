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
 * Tests for «no more mail, please» from a mail's link, for adults without an account.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\mailstop
 */
#[\PHPUnit\Framework\Attributes\CoversClass(mailstop::class)]
final class mailstop_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * The link is sealed for its centre and its address; only a hash of the address is kept, and twice is once.
     */
    public function test_link_and_list(): void {
        global $DB;
        $this->resetAfterTest();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $entityid = (int) $library->entityid;
        $hash = mailstop::hash(' Ivan.Familia@Example.org ');
        $this->assertSame(mailstop::hash('ivan.familia@example.org'), $hash);
        $link = mailstop::link($entityid, 'ivan.familia@example.org');
        $this->assertStringContainsString('h=' . $hash, $link);
        $this->assertStringContainsString('s=' . mailstop::seal($entityid, $hash), $link);
        $this->assertStringNotContainsString('example.org', $link);
        $this->assertNotSame(mailstop::seal($entityid, $hash), mailstop::seal($entityid + 1, $hash));
        $this->assertFalse(mailstop::stopped($entityid, 'ivan.familia@example.org'));
        mailstop::set($entityid, $hash, true);
        mailstop::set($entityid, $hash, true);
        $this->assertSame(1, $DB->count_records(self::T . 'mailstop'));
        $this->assertTrue(mailstop::stopped($entityid, 'IVAN.familia@example.org'));
        $this->assertFalse(mailstop::stopped($entityid + 1, 'ivan.familia@example.org'));
        $this->assertFalse($DB->record_exists_select(self::T . 'mailstop', $DB->sql_like('emailhash', ':m'), ['m' => '%@%']));
        mailstop::set($entityid, $hash, false);
        $this->assertFalse(mailstop::stopped($entityid, 'ivan.familia@example.org'));
    }

    /**
     * A mail carries the link at its foot, in the page and in the words; once asked, nothing more is sent to that address.
     */
    public function test_send(): void {
        $this->resetAfterTest();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $entityid = (int) $library->entityid;
        $design = ['library' => $library, 'entityid' => $entityid, 'heading' => 'Un libro que vuelve',
            'lead' => ['El libro ya está en la biblioteca.'], 'why' => 'Te llega porque figuras como familia.'];
        $sink = $this->redirectEmails();
        $this->assertTrue(mailstop::send(
            $entityid,
            'ivan.familia@example.org',
            'Iván',
            'Inventado',
            'Biblioteca',
            'Un libro que vuelve',
            'El libro ya está en la biblioteca.',
            $design
        ));
        $mails = $sink->get_messages();
        $this->assertCount(1, $mails);
        $this->assertStringContainsString('stop.php?e=' . $entityid, quoted_printable_decode($mails[0]->body));
        $sink->clear();
        mailstop::set($entityid, mailstop::hash('ivan.familia@example.org'), true);
        $this->assertFalse(mailstop::send(
            $entityid,
            'ivan.familia@example.org',
            'Iván',
            'Inventado',
            'Biblioteca',
            'Un libro que vuelve',
            'El libro ya está en la biblioteca.',
            $design
        ));
        $this->assertCount(0, $sink->get_messages());
        $sink->close();
    }

    /**
     * The foot without a link still says how to stop; with the link, it does not say it twice.
     */
    public function test_foot(): void {
        $this->resetAfterTest();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $base = ['library' => $library, 'heading' => 'Hola', 'why' => 'Te llega porque figuras como familia.'];
        $without = mail::html($base);
        $this->assertStringContainsString(get_string('mailwhystop', 'local_oksigeniaayvurenda'), $without);
        $with = mail::html($base + ['stopurl' => 'https://example.com/local/oksigeniaayvurenda/stop.php?e=1']);
        $this->assertStringNotContainsString(get_string('mailwhystop', 'local_oksigeniaayvurenda'), $with);
        $this->assertStringContainsString(get_string('mailstoptext', 'local_oksigeniaayvurenda'), $with);
    }
}
