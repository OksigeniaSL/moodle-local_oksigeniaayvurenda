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
 * Tests for «Leyó para los pequeños».
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\forlittle
 */
#[\PHPUnit\Framework\Attributes\CoversClass(forlittle::class)]
final class forlittle_test extends \advanced_testcase {
    /** @var string Prefix of the tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * A voice of a reader, with its file.
     *
     * @param \stdClass $library
     * @param \stdClass $reader
     * @param string $title The book's.
     * @param string $status
     * @param int $now
     * @return int
     */
    private function voice(\stdClass $library, \stdClass $reader, string $title, string $status, int $now): int {
        global $DB;
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $record = $gen->create_record(['entityid' => $library->entityid, 'title' => $title]);
        $id = (int) $DB->insert_record(self::T . 'digital', ['entityid' => $library->entityid, 'recordid' => $record->id,
            'kind' => 'voice', 'format' => 'audio', 'title' => 'Entero', 'status' => $status, 'readerid' => $reader->id,
            'audience' => 'students', 'licence' => 'rights', 'createdby' => $reader->userid, 'timecreated' => $now,
            'timemodified' => $now]);
        get_file_storage()->create_file_from_string(['contextid' => \context_system::instance()->id,
            'component' => 'local_oksigeniaayvurenda', 'filearea' => 'digital', 'itemid' => $id, 'filepath' => '/',
            'filename' => 'voz.webm'], 'voz');
        return $id;
    }

    /**
     * Only a reader past the flower reads for the little ones, and their family hears it only where the centre turned it
     * on and the reader has a family; the little ones' shelf has what was approved for them, one per book; the sealed link
     * opens only a published voice meant for the family; and the family hears of it once, the morning after.
     */
    public function test_forlittle(): void {
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
        $now = make_timestamp(2026, 11, 10, 8);
        $teen = $dg->create_user(['firstname' => 'Iker']);
        $older = $gen->create_reader(['entityid' => $entityid, 'userid' => $teen->id, 'band' => 'sky']);
        $kid = $dg->create_user(['firstname' => 'Leo']);
        $young = $gen->create_reader(['entityid' => $entityid, 'userid' => $kid->id, 'band' => 'flower']);
        $this->assertTrue(forlittle::can_read($older, $now));
        $this->assertFalse(forlittle::can_read($young, $now));

        // Marked at recording: the little one cannot; without the centre's light or a family, no family.
        $mine = $this->voice($library, $young, 'Mi cuento', 'pending', $now);
        forlittle::mark($mine, $young, true, true, $now);
        $this->assertEquals([0, 0], array_values((array) $DB->get_record(
            self::T . 'digital',
            ['id' => $mine],
            'forlittle, tellfamily'
        )));
        // Someone of the staff reads for the little ones too, with no family to tell.
        $staffvoice = $this->voice($library, $young, 'Un cuento del profe', 'pending', $now);
        forlittle::mark($staffvoice, null, true, true, $now, true);
        $this->assertEquals([1, 0], array_values((array) $DB->get_record(
            self::T . 'digital',
            ['id' => $staffvoice],
            'forlittle, tellfamily'
        )));
        $DB->delete_records(self::T . 'digital', ['id' => $staffvoice]);
        $read = $this->voice($library, $older, 'La isla del cometa', 'pending', $now);
        forlittle::mark($read, $older, true, true, $now);
        $this->assertEquals([1, 0], array_values((array) $DB->get_record(
            self::T . 'digital',
            ['id' => $read],
            'forlittle, tellfamily'
        )));
        $mother = $dg->create_user(['firstname' => 'Rosa']);
        $family = \local_oksigeniafamily\api::create_family();
        \local_oksigeniafamily\api::add_adult($family, ['firstname' => 'Rosa', 'email' => $mother->email,
            'userid' => (int) $mother->id]);
        \local_oksigeniafamily\api::add_adult($family, ['firstname' => 'Iván', 'email' => 'ivan.familia@example.org']);
        \local_oksigeniafamily\api::add_child($family, (int) $teen->id);
        forlittle::save($entityid, true);
        $this->assertTrue(forlittle::can_tell($older));
        forlittle::mark($read, $older, true, true, $now);
        $this->assertEquals(1, $DB->get_field(self::T . 'digital', 'tellfamily', ['id' => $read]));

        // The shelf: only what is published, for whoever may hear it.
        $staff = ['who' => 'desk', 'userid' => 0, 'stage' => '', 'readerid' => 0];
        $nobody = ['who' => 'nobody', 'userid' => 0, 'stage' => '', 'readerid' => 0];
        $this->assertSame([], forlittle::shelf($library, $staff));
        $this->assertSame([], forlittle::due($now));
        $this->assertNull(forlittle::heard($read, mail::seal('hear', $read, 'h')));
        $DB->set_field(self::T . 'digital', 'status', 'published', ['id' => $read]);
        $this->assertSame(['La isla del cometa'], array_column(forlittle::shelf($library, $staff), 'title'));
        $this->assertSame([], forlittle::shelf($library, $nobody));
        // Unsigned, «an older one» reads it; and it is the story for today of a little one who may hear it.
        $this->assertSame(get_string('littlereadbyanon', 'local_oksigeniaayvurenda'), forlittle::shelf($library, $staff)[0]['by']);
        $this->assertSame('La isla del cometa', forlittle::today($library, $staff, $now)['title']);
        $this->assertNull(forlittle::today($library, $nobody, $now));

        // The sealed link: only with its seal.
        $this->assertNull(forlittle::heard($read, 'abc'));
        $this->assertNull(forlittle::heard($mine, mail::seal('hear', $mine, 'h')));
        $heard = forlittle::heard($read, mail::seal('hear', $read, 'h'));
        $this->assertSame('La isla del cometa', $heard['record']->title);
        $this->assertStringContainsString('s=' . mail::seal('hear', $read, 'h'), forlittle::url($read));

        // The morning: once, and not what was approved weeks ago.
        $this->assertCount(1, forlittle::due($now));
        $this->assertSame([], forlittle::due($now + (forlittle::NEWS + 1) * DAYSECS));
        $messages = $this->redirectMessages();
        $emails = $this->redirectEmails();
        $this->assertSame(2, forlittle::run($now));
        $tomother = array_values(array_filter($messages->get_messages(), fn($m) => (int) $m->useridto === (int) $mother->id));
        $this->assertCount(1, $tomother);
        $this->assertSame('forlittle', $tomother[0]->eventtype);
        $this->assertStringContainsString('La isla del cometa', $tomother[0]->fullmessage);
        $this->assertStringContainsString('hear.php', $tomother[0]->fullmessage);
        $this->assertCount(1, array_filter($emails->get_messages(), fn($m) => $m->to === 'ivan.familia@example.org'));
        $this->assertEquals($now, $DB->get_field(self::T . 'digital', 'timetold', ['id' => $read]));
        $this->assertSame(0, forlittle::run($now + DAYSECS));
        $messages->close();
        $emails->close();
        if (method_exists('\\local_oksigeniafamily\\api', 'reset')) {
            \local_oksigeniafamily\api::reset();
        }
    }
}
