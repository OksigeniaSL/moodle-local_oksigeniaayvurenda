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
 * A library reads the words of its kind; what needs classes or courses is not there without them; and each person has their
 * own practice library.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(skins::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(practice::class)]
final class skins_words_test extends \advanced_testcase {
    /**
     * A library of a skin, and someone who keeps it.
     *
     * @param string $skin
     * @return \stdClass
     */
    private function library(string $skin): \stdClass {
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        libraries::update_entity((int) $library->entityid, ['skin' => $skin]);
        return $library;
    }

    /**
     * The same string, for each kind of library.
     */
    public function test_the_words_of_each_kind(): void {
        $this->resetAfterTest();
        $say = fn(string $skin, string $key, $a = null) => skins::say((int) $this->library($skin)->entityid, $key, $a);
        $this->assertSame(get_string('readertypestudent', 'local_oksigeniaayvurenda'), $say('school', 'readertypestudent'));
        $this->assertSame(get_string('readertypestudentpublic', 'local_oksigeniaayvurenda'), $say('public', 'readertypestudent'));
        $this->assertSame(get_string('readertypestudentpublic', 'local_oksigeniaayvurenda'), $say('mobile', 'readertypestudent'));
        $this->assertSame(get_string('readertypestudentcompany', 'local_oksigeniaayvurenda'), $say('company', 'readertypestudent'));
        $student = get_string('readertypestudentuniversity', 'local_oksigeniaayvurenda');
        $this->assertSame($student, $say('university', 'readertypestudent'));
        // The year, where there are no courses: and a university does have them.
        $this->assertSame(get_string('memoirnavlay', 'local_oksigeniaayvurenda'), $say('public', 'memoirnav'));
        $this->assertSame(get_string('memoirnavlay', 'local_oksigeniaayvurenda'), $say('company', 'memoirnav'));
        $this->assertSame(get_string('memoirnav', 'local_oksigeniaayvurenda'), $say('university', 'memoirnav'));
        // Where it has one of its own and no plain one, the skin's tone is kept.
        $this->assertSame(get_string('cardnovisitpublic', 'local_oksigeniaayvurenda'), $say('public', 'cardnovisit'));
        $this->assertSame(get_string('cardnovisitcompany', 'local_oksigeniaayvurenda'), $say('company', 'cardnovisit'));
        $this->assertSame(get_string('cardnovisitsecondary', 'local_oksigeniaayvurenda'), $say('secondary', 'cardnovisit'));
    }

    /**
     * Every word said without classes or courses leaves those words behind, in both languages.
     */
    public function test_a_variant_does_not_speak_of_a_school(): void {
        $school = ['es' => '/\b(cursos?|clases?|tutor(es)?|alumn[oa]s?|colegio)\b/iu',
            'en' => '/\b(academic|school year|pupils?|tutors?)\b/iu'];
        foreach ($school as $lang => $words) {
            $strings = get_string_manager()->load_component_strings('local_oksigeniaayvurenda', $lang);
            foreach ($strings as $key => $text) {
                if (str_ends_with($key, 'lay') && isset($strings[substr($key, 0, -3)])) {
                    $this->assertDoesNotMatchRegularExpression($words, $text, $key);
                }
            }
        }
    }

    /**
     * Without classes there are no challenges nor a class to tutor; and neither of them in the menu or in the guide.
     */
    public function test_without_classes(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $user = get_admin();
        $skins = ['public' => true, 'company' => true, 'mobile' => true, 'university' => true, 'school' => false];
        foreach ($skins as $skin => $without) {
            $library = $this->library($skin);
            $entity = (int) $library->entityid;
            $this->assertSame($without, skins::no_classes($entity));
            $year = years::around($entity, time());
            $DB->insert_record('local_oksigeniaayvurenda_tutor', ['yearid' => $year->id, 'groupname' => '3º A',
                'userid' => $user->id, 'timecreated' => 1, 'timemodified' => 1]);
            $DB->insert_record('local_oksigeniaayvurenda_challenge', ['yearid' => $year->id, 'groupname' => '3º A',
                'term' => challenges::term($year, time()), 'kind' => 'corners', 'timecompleted' => time(), 'timecreated' => 1,
                'timemodified' => 1]);
            $this->assertSame($without, challenges::of($entity, '3º A') === null, $skin);
            $this->assertSame($without, challenges::tutored($entity, (int) $user->id) === [], $skin);
            $this->assertSame($without, challenges::garden($entity, '3º A') === [], $skin);
            $tasks = array_column(howto::find('', $library, (int) $user->id), 'key');
            $this->assertSame(!$without, in_array('tutors', $tasks, true), $skin);
            $this->assertSame(!$without, in_array('myclass', $tasks, true), $skin);
            $this->assertSame(!skins::no_courses($entity), in_array('readings', $tasks, true), $skin);
        }
    }

    /**
     * Each person has their own practice library, with the skin and the language they asked it in; those of the day before go.
     */
    public function test_a_practice_library_for_each_person(): void {
        global $DB;
        $this->resetAfterTest();
        $one = $this->getDataGenerator()->create_user();
        $two = $this->getDataGenerator()->create_user();
        $this->setUser($one);
        $mine = practice::reset('public');
        $this->setUser($two);
        $theirs = practice::reset();
        $this->assertNotSame($mine->slug, $theirs->slug);
        // Making one again does not touch the other.
        $this->setUser($one);
        $again = practice::reset('public');
        $this->assertNotEquals($mine->id, $again->id);
        $this->assertTrue($DB->record_exists('local_oksigeniaayvurenda_library', ['id' => $theirs->id]));

        $this->assertSame('public', skins::of((int) $again->entityid));
        $this->assertSame(0, $DB->count_records_sql(
            'SELECT COUNT(1) FROM {local_oksigeniaayvurenda_member} m JOIN {local_oksigeniaayvurenda_reader} r ON r.id = m.readerid
              WHERE r.entityid = ?',
            [$again->entityid]
        ));
        $this->assertSame('school', skins::of((int) $theirs->entityid));
        $this->assertSame(3, $DB->count_records_sql(
            'SELECT COUNT(1) FROM {local_oksigeniaayvurenda_member} m JOIN {local_oksigeniaayvurenda_reader} r ON r.id = m.readerid
              WHERE r.entityid = ?',
            [$theirs->entityid]
        ));

        // The tests run in English; its made-up books too.
        $english = practice::reset();
        $this->assertTrue($DB->record_exists('local_oksigeniaayvurenda_record', ['entityid' => $english->entityid,
            'title' => 'The Sleeping Lighthouse']));

        $this->assertSame(0, practice::sweep());
        $this->assertSame(2, practice::sweep(time() + 2 * DAYSECS));
        $this->assertFalse($DB->record_exists('local_oksigeniaayvurenda_library', ['id' => $english->id]));
    }
}
