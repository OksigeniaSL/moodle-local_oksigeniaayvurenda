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
 * Tests for the voices of «give this book a voice».
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\voices
 */
#[\PHPUnit\Framework\Attributes\CoversClass(voices::class)]
final class voices_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * A minor's voice: the recorder, their tutor and the library hear it; nobody else, not even once approved, until
     * the family's permission is noted; taken back, it is private again. An adult's voice goes where they choose. The
     * recorder deletes it; the centre's days delete the old ones.
     */
    public function test_voices(): void {
        global $DB;
        $this->resetAfterTest();
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $entityid = (int) $library->entityid;
        $now = time();
        $record = $gen->create_record(['entityid' => $entityid, 'title' => 'El faro dormido']);
        $year = $gen->create_year(['entityid' => $entityid] + \local_oksigeniaayvurenda\core\school_year::around(
            $now,
            '09-01',
            \core_date::get_server_timezone_object()
        ));
        $user = fn() => $this->getDataGenerator()->create_user();
        [$ana, $leo, $tutor, $teacher, $stranger] = [$user(), $user(), $user(), $user(), $user()];
        $reader = function ($account, string $type, string $group) use ($gen, $entityid, $year) {
            $reader = $gen->create_reader(['entityid' => $entityid, 'userid' => $account->id, 'readertype' => $type]);
            if ($group !== '') {
                $gen->create_member(['readerid' => $reader->id, 'yearid' => $year->id, 'groupname' => $group]);
            }
            return $reader;
        };
        $anareader = $reader($ana, 'student', '4º B');
        $reader($leo, 'student', '5º A');
        $teacherreader = $reader($teacher, 'teacher', '');
        $DB->insert_record(self::T . 'tutor', ['yearid' => $year->id, 'groupname' => '4º B', 'userid' => $tutor->id,
            'timecreated' => $now, 'timemodified' => $now]);
        $hears = fn(int $id, $who) => voices::can_hear(digital::get($id, $library), digital::viewer($entityid, (int) $who->id));
        $path = make_request_directory() . '/voz.webm';

        file_put_contents($path, 'made-up recording');
        $id = voices::record($library, (int) $record->id, (int) $anareader->id, (int) $ana->id, $path, 'Capítulo 1');
        $voice = digital::get($id, $library);
        $this->assertSame(['voice', 'audio', 'pending', ''], [$voice->kind, $voice->format, $voice->status, $voice->audience]);
        $this->assertTrue($hears($id, $ana));
        $this->assertTrue($hears($id, $tutor));
        $this->assertFalse($hears($id, $leo));
        $this->assertTrue(voices::can_approve($voice, (int) $tutor->id));
        $this->assertFalse(voices::can_approve($voice, (int) $teacher->id));
        $this->assertCount(1, voices::pending($library, (int) $tutor->id));
        $this->assertSame([], voices::pending($library, (int) $teacher->id));

        voices::approve($id, $library, ['licence' => 'pd', 'audience' => ['students', 'public']]);
        $this->assertSame('published', digital::get($id, $library)->status);
        $this->assertSame('', digital::get($id, $library)->audience, 'a minor without permission: private');
        $this->assertFalse($hears($id, $leo));

        voices::permission((int) $anareader->id, true, (int) $tutor->id);
        voices::approve($id, $library, ['licence' => 'pd', 'audience' => ['students', 'public']]);
        $this->assertTrue($hears($id, $leo));
        $this->assertTrue($hears($id, $stranger), 'public, and in the public domain');
        voices::permission((int) $anareader->id, false, (int) $tutor->id);
        $this->assertFalse($hears($id, $leo), 'taken back: private again');
        $this->assertFalse(voices::has_permission((int) $anareader->id));

        // An adult chooses; someone else's rights never go public.
        file_put_contents($path, 'made-up recording');
        $adult = voices::record($library, (int) $record->id, (int) $teacherreader->id, (int) $teacher->id, $path, '');
        voices::approve($adult, $library, ['licence' => 'rights', 'audience' => ['students', 'public']]);
        $this->assertSame('students', digital::get($adult, $library)->audience);
        $this->assertTrue($hears($adult, $leo));
        $this->assertFalse($hears($adult, $stranger));

        voices::remove(digital::get($adult, $library));
        $this->assertFalse($DB->record_exists(self::T . 'digital', ['id' => $adult]));
        $DB->set_field(self::T . 'entity', 'settings', json_encode(['voicekeepdays' => 30]), ['id' => $entityid]);
        $DB->set_field(self::T . 'digital', 'timecreated', $now - 40 * DAYSECS, ['id' => $id]);
        $this->assertSame(1, voices::expire($now));
        $this->assertFalse($DB->record_exists(self::T . 'digital', ['id' => $id]));
    }

    /**
     * A family without an account records once with the link a teacher sent: the teacher hears and approves it, never
     * for more people than the family chose; the link does not record twice and an unused one expires.
     */
    public function test_family_link(): void {
        global $DB;
        $this->resetAfterTest();
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $entityid = (int) $library->entityid;
        $now = time();
        $record = $gen->create_record(['entityid' => $entityid, 'title' => 'El faro dormido']);
        $teacher = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();
        $gen->create_reader(['entityid' => $entityid, 'userid' => $teacher->id, 'readertype' => 'teacher']);
        $gen->create_reader(['entityid' => $entityid, 'userid' => $other->id, 'readertype' => 'teacher']);
        $gen->create_reader(['entityid' => $entityid, 'userid' => $student->id, 'readertype' => 'student']);

        $token = voices::invite($library, (int) $record->id, (int) $teacher->id, 'La familia de Ana', $now);
        $this->assertNull(voices::invitation('nonsense'));
        $invite = voices::invitation($token, $now);
        $this->assertSame('La familia de Ana', $invite->name);
        $path = make_request_directory() . '/voz.ogg';
        file_put_contents($path, 'made-up recording');
        $id = voices::record_invited($invite, $path, 'Capítulo 1', 'ogg', ['students', 'staff', 'nonsense'], $now);
        $voice = digital::get($id, $library);
        $this->assertSame(['pending', null, null], [$voice->status, $voice->createdby, $voice->readerid]);
        $this->assertSame('students', voices::invite_of($id)->audience, 'only what a family may choose');
        $this->assertTrue(voices::can_hear($voice, digital::viewer($entityid, (int) $teacher->id)));
        $this->assertFalse(voices::can_hear($voice, digital::viewer($entityid, (int) $other->id)));
        $this->assertTrue(voices::can_approve($voice, (int) $teacher->id));
        $this->assertFalse(voices::can_approve($voice, (int) $other->id));
        try {
            voices::record_invited(voices::invitation($token, $now), $path, '', 'ogg', [], $now);
            $this->fail('a link records once');
        } catch (\moodle_exception $e) {
            $this->assertSame('voiceinviteused', $e->errorcode);
        }
        // The family chose students: approving for the public too gives students only.
        voices::approve($id, $library, ['licence' => 'pd', 'audience' => ['students', 'public']]);
        $this->assertSame('students', digital::get($id, $library)->audience);
        $this->assertTrue(voices::can_hear(digital::get($id, $library), digital::viewer($entityid, (int) $student->id)));
        // Deleted with the link, the link stays used and still opens to say so.
        voices::remove(digital::get($id, $library));
        $this->assertNotNull(voices::invitation($token, $now + 30 * DAYSECS));
        $this->assertNull(voices::invitation($token, $now + 30 * DAYSECS)->digitalid);
        // An unused link expires, and is cleared.
        $unused = voices::invite($library, (int) $record->id, (int) $teacher->id, 'Otra familia', $now);
        $this->assertNull(voices::invitation($unused, $now + (voices::INVITEDAYS + 1) * DAYSECS));
        voices::expire($now + (voices::INVITEDAYS + 1) * DAYSECS);
        $this->assertFalse($DB->record_exists(self::T . 'voiceinvite', ['token' => $unused]));
        $this->assertTrue($DB->record_exists(self::T . 'voiceinvite', ['token' => $token]));
    }
}
