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

use local_oksigeniaayvurenda\core\age;

/**
 * What the security review of 2 November 2026 found, so that it does not come back.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(practice::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(access::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(secrets::class)]
final class security_test extends \advanced_testcase {
    /**
     * The generator of the plugin.
     *
     * @return \local_oksigeniaayvurenda_generator
     */
    private function gen() {
        return $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
    }

    /**
     * A practice library is its owner's alone, and nothing of the site comes through it.
     */
    public function test_a_practice_library_opens_nothing(): void {
        global $DB;
        $this->resetAfterTest();
        $dg = $this->getDataGenerator();
        set_config('entitycategories', 1, 'local_oksigeniaayvurenda');
        $roleid = $dg->create_role();
        assign_capability('local/oksigeniaayvurenda:manage', CAP_ALLOW, $roleid, \context_system::instance()->id, true);
        // Two people who manage a centre each.
        $one = $this->gen()->create_library(['slug' => 'una']);
        $two = $this->gen()->create_library(['slug' => 'otra']);
        access::category((int) $one->entityid);
        $catwo = access::category((int) $two->entityid);
        $ana = $dg->create_user(['email' => 'ana@example.com']);
        $leo = $dg->create_user();
        role_assign($roleid, $ana->id, access::context((int) $one->entityid)->id);
        role_assign($roleid, $leo->id, access::context((int) $two->entityid)->id);
        access::forget();

        $this->setUser($ana);
        $practice = practice::reset();
        $entity = (int) $practice->entityid;
        $this->assertTrue(access::can('local/oksigeniaayvurenda:manage', $practice, (int) $ana->id));
        $this->assertFalse(access::can('local/oksigeniaayvurenda:manage', $practice, (int) $leo->id));
        $this->assertSame([], access::categories($entity));

        // The courses and cohorts of another centre do not come through it.
        $course = $dg->create_course(['category' => $catwo]);
        $student = $dg->create_user();
        $dg->enrol_user($student->id, $course->id, 'student');
        sources\courses::save($entity, [$course->id => ['stage' => '', 'group' => false]]);
        $this->assertSame([], sources\courses::chosen($entity));
        $this->assertSame([], array_column(sources\courses::options($entity), 'id'));
        sources\cohorts::sync($entity);
        $brought = ['entityid' => $entity, 'userid' => $student->id];
        $this->assertFalse($DB->record_exists('local_oksigeniaayvurenda_reader', $brought));

        // A list does not find out who has an account, nor link it.
        $people = [['firstname' => 'Ana', 'lastname' => 'Uno', 'email' => 'ana@example.com']];
        $stats = sources\csvlist::load($entity, $people, 'student');
        $this->assertSame(0, $stats['linked']);
        $this->assertSame([], (fn() => self::accounts($entity))->bindTo(null, matching::class)());
    }

    /**
     * A library called as a practice library cannot be had, and making one does not take a centre away.
     */
    public function test_the_address_of_a_practice_library_is_reserved(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->assertStringStartsNotWith(practice::SLUG, libraries::slug(practice::slug()));
        $library = $this->gen()->create_library(['slug' => 'central']);
        $DB->set_field('local_oksigeniaayvurenda_library', 'slug', practice::slug(), ['id' => $library->id]);
        $record = $this->gen()->create_record(['entityid' => $library->entityid, 'title' => 'Platero y yo']);
        try {
            practice::reset();
        } catch (\Throwable $e) {
            $this->assertInstanceOf(\dml_exception::class, $e);
        }
        $this->assertTrue($DB->record_exists('local_oksigeniaayvurenda_record', ['id' => $record->id]));
    }

    /**
     * Nothing brought from a catalogue can run in a template of the app, however it is written.
     */
    public function test_nothing_can_run_in_the_app(): void {
        $inert = new \ReflectionMethod(\local_oksigeniaayvurenda\output\mobile::class, 'inert');
        $cases = ['{{7*7}}', '&#123;&#123;7*7&#125;&#125;', '&#x7b;&#X7B;7*7&#x7D;&#x7d;', '&amp;#123;&amp;#123;7*7', '&#38;#123;',
            '&lbrace;&lbrace;7*7&rbrace;&rbrace;', 'data:{{7*7}}', 'https://x.io/{{7*7}}', '@if (a) {', 'https://x @if (a)',
            '&commat;if (a)', '&#9999999999;', '&#x110000;', '&#&#1;123;'];
        foreach ($cases as $title) {
            $out = s($inert->invoke(null, ['title' => $title])['title']);
            $running = '/[{}@]|&#\d+;|&#x[0-9a-f]+;|&lbrace|&rbrace|&commat/i';
            $this->assertDoesNotMatchRegularExpression($running, $out, $title);
        }
        // Twice written, it still reads as it should.
        $this->assertSame('｛｛9*9｝｝', $inert->invoke(null, ['title' => '&#38;#123;&#38;#123;9*9&#38;#125;&#38;#125;'])['title']);
        // An address stays one where one is expected.
        $this->assertSame('data:image/png;base64,AA', $inert->invoke(null, ['cover' => 'data:image/png;base64,AA'])['cover']);
        $this->assertSame('https://a@b.org/c', $inert->invoke(null, ['url' => 'https://a@b.org/c'])['url']);
        $this->assertStringNotContainsString('{', $inert->invoke(null, ['url' => 'https://x.io/{{a}}'])['url']);
        // What was looked for goes as data, never as a piece of an expression.
        $template = (string) file_get_contents(__DIR__ . '/../../templates/mobile_library.mustache');
        $this->assertStringNotContainsString('memoryquery', $template);
    }

    /**
     * A key of low order is no key: with it, the night would fail for this centre and for those after it.
     */
    public function test_a_key_of_low_order_is_refused(): void {
        $this->resetAfterTest();
        $library = $this->gen()->create_library();
        $bech32 = new \ReflectionMethod(age::class, 'bech32');
        $zeros = $bech32->invoke(null, 'age', str_repeat("\0", 32));
        $this->assertFalse(age::is_recipient($zeros));
        $this->assertFalse(offsite::use_key((int) $library->entityid, $zeros));
        $this->assertTrue(age::is_recipient(age::keypair()['public']));
    }

    /**
     * A centre that fails does not leave the others without their copy.
     */
    public function test_one_centre_does_not_stop_the_night(): void {
        global $DB;
        $this->resetAfterTest();
        $first = $this->gen()->create_library(['slug' => 'primera']);
        $second = $this->gen()->create_library(['slug' => 'segunda']);
        // A key that cannot encrypt, as an older version could keep, and somewhere to send to.
        $bech32 = new \ReflectionMethod(age::class, 'bech32');
        $settings = ['offsite' => ['recipient' => $bech32->invoke(null, 'age', str_repeat("\0", 32)),
            'places' => [['id' => 'a', 'kind' => 'webdav', 'url' => 'https://nube.ejemplo.org/', 'user' => 'b', 'label' => 'n']]]];
        $DB->set_field('local_oksigeniaayvurenda_entity', 'settings', json_encode($settings), ['id' => $first->entityid]);
        ob_start();
        (new \local_oksigeniaayvurenda\task\make_copies())->execute();
        ob_end_clean();
        $this->assertNotEmpty(copies::list((int) $second->entityid));
    }

    /**
     * A reminder only to one's own.
     */
    public function test_a_reminder_stays_in_its_centre(): void {
        $this->resetAfterTest();
        $mine = $this->gen()->create_library(['slug' => 'mia']);
        $theirs = $this->gen()->create_library(['slug' => 'suya']);
        $user = $this->getDataGenerator()->create_user();
        $reader = $this->gen()->create_reader(['entityid' => $theirs->entityid, 'userid' => $user->id]);
        $item = $this->gen()->create_item(['libraryid' => $theirs->id, 'barcode' => '100000L', 'status' => 'onloan']);
        $now = time();
        $this->gen()->create_loan(['libraryid' => $theirs->id, 'itemid' => $item->id, 'readerid' => $reader->id,
            'timeloaned' => $now - 30 * DAYSECS, 'timedue' => $now - 10 * DAYSECS]);
        $sink = $this->redirectMessages();
        $this->assertFalse(notices::remind((int) $reader->id, $now, (int) $mine->entityid));
        $this->assertCount(0, $sink->get_messages());
        $this->assertTrue(notices::remind((int) $reader->id, $now, (int) $theirs->entityid));
        $this->assertCount(1, $sink->get_messages());
        $sink->close();
    }

    /**
     * Only the courses that already read a book can have it set aside for them.
     */
    public function test_only_the_courses_that_read_it(): void {
        global $DB;
        $this->resetAfterTest();
        $library = $this->gen()->create_library();
        $record = $this->gen()->create_record(['entityid' => $library->entityid, 'title' => 'Platero y yo']);
        $course = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        $list = $DB->insert_record('local_oksigeniaayvurenda_readinglist', ['courseid' => $course->id, 'libraryid' => $library->id,
            'timecreated' => 1, 'timemodified' => 1]);
        $DB->insert_record('local_oksigeniaayvurenda_readingitem', ['listid' => $list, 'recordid' => $record->id, 'required' => 1,
            'sortorder' => 1, 'timecreated' => 1]);
        $this->assertTrue(readings::asks($library, (int) $record->id, (int) $course->id));
        $this->assertFalse(readings::asks($library, (int) $record->id, (int) $other->id));
        $this->assertArrayHasKey($course->id, fragments::courses($library, (int) $record->id));
        $this->assertArrayNotHasKey($other->id, fragments::courses($library, (int) $record->id));
    }

    /**
     * The daily copy does not say where the copies go.
     */
    public function test_the_daily_copy_keeps_its_settings_to_itself(): void {
        $this->resetAfterTest();
        $library = $this->gen()->create_library();
        $entity = (int) $library->entityid;
        offsite::$network = fn(string $method, string $url, string $payload): array => [201, ''];
        try {
            offsite::new_key($entity);
            offsite::add_webdav($entity, 'https://nube.ejemplo.org/dav/', 'biblioteca', 'clave-secreta');
            $path = make_request_directory() . '/copia.zip';
            copies::make($entity)->copy_content_to($path);
        } finally {
            offsite::$network = null;
        }
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path, \ZipArchive::RDONLY));
        $this->assertStringNotContainsString('nube.ejemplo.org', (string) $zip->getFromName('entity.csv'));
        $this->assertStringNotContainsString('biblioteca"', (string) $zip->getFromName('entity.csv'));
        $zip->close();
    }

    /**
     * The passwords of the centres are not in the database in clear, and those of before still work.
     */
    public function test_secrets(): void {
        $this->resetAfterTest();
        secrets::put('offsite1_a', 'clave-secreta');
        $this->assertStringNotContainsString('clave-secreta', (string) get_config('local_oksigeniaayvurenda', 'offsite1_a'));
        $this->assertSame('clave-secreta', secrets::get('offsite1_a'));
        set_config('offsite1_b', 'de-antes', 'local_oksigeniaayvurenda');
        $this->assertSame('de-antes', secrets::get('offsite1_b'));
        $this->assertSame('', secrets::get('offsite1_c'));
    }

    /**
     * Without the plan, the link does not serve and the centre can take everything away.
     */
    public function test_without_the_plan(): void {
        $this->resetAfterTest();
        $library = $this->gen()->create_library();
        $entity = (int) $library->entityid;
        offsite::$network = fn(string $method, string $url, string $payload): array => [201, ''];
        try {
            offsite::new_key($entity);
            offsite::add_webdav($entity, 'https://nube.ejemplo.org/dav/', 'biblioteca', 'clave');
            parse_str((string) parse_url(offsite::new_pull($entity), PHP_URL_QUERY), $query);
            $this->assertTrue(offsite::pull_allowed($entity, $query['key']));
            plans::save($entity, ['offsite' => false] + plans::of($entity));
            $this->assertFalse(offsite::pull_allowed($entity, $query['key']));
            $this->assertTrue(offsite::has_any($entity));
            offsite::clear($entity);
            $this->assertFalse(offsite::has_any($entity));
        } finally {
            offsite::$network = null;
        }
    }

    /**
     * Who keeps a library stops when they stop being a reader.
     */
    public function test_a_keeper_who_left(): void {
        global $DB;
        $this->resetAfterTest();
        $library = $this->gen()->create_library();
        $user = $this->getDataGenerator()->create_user();
        $reader = $this->gen()->create_reader(['entityid' => $library->entityid, 'userid' => $user->id]);
        $this->assertTrue(keepers::add($library, (int) $user->id, 2));
        $this->assertTrue(keepers::is((int) $library->id, (int) $user->id));
        $DB->set_field('local_oksigeniaayvurenda_reader', 'status', 'left', ['id' => $reader->id]);
        access::forget();
        $this->assertFalse(keepers::is((int) $library->id, (int) $user->id));
    }

    /**
     * Taking a centre away can take the accounts that have nothing else, and only those.
     */
    public function test_lonely_accounts(): void {
        $this->resetAfterTest();
        $dg = $this->getDataGenerator();
        $library = $this->gen()->create_library(['slug' => 'una']);
        $other = $this->gen()->create_library(['slug' => 'otra']);
        $alone = $dg->create_user();
        $both = $dg->create_user();
        $enrolled = $dg->create_user();
        foreach ([$alone, $both, $enrolled] as $user) {
            $this->gen()->create_reader(['entityid' => $library->entityid, 'userid' => $user->id]);
        }
        $this->gen()->create_reader(['entityid' => $other->entityid, 'userid' => $both->id]);
        $dg->enrol_user($enrolled->id, $dg->create_course()->id);
        $this->assertSame([(int) $alone->id], removal::lonely_accounts((int) $library->entityid));
    }

    /**
     * The staff of a centre have no reader's card: they go with it, if they have nothing else.
     */
    public function test_lonely_staff(): void {
        global $DB;
        $this->resetAfterTest();
        $dg = $this->getDataGenerator();
        $library = $this->gen()->create_library(['slug' => 'una']);
        $category = $dg->create_category();
        $DB->set_field('local_oksigeniaayvurenda_entity', 'categoryid', $category->id, ['id' => $library->entityid]);
        $role = (int) $dg->create_role();
        $context = \context_coursecat::instance($category->id);
        $only = $dg->create_user();
        $elsewhere = $dg->create_user();
        role_assign($role, $only->id, $context->id);
        role_assign($role, $elsewhere->id, $context->id);
        role_assign($role, $elsewhere->id, \context_coursecat::instance($dg->create_category()->id)->id);
        $this->assertSame([(int) $only->id], removal::lonely_accounts((int) $library->entityid));
    }

    /**
     * A recovered centre does not come back as a practice library, and its links are clean.
     */
    public function test_recover_leaves_practice_behind(): void {
        global $DB;
        $this->resetAfterTest();
        $library = $this->gen()->create_library();
        $settings = json_encode(['skin' => 'public']);
        $DB->set_field('local_oksigeniaayvurenda_entity', 'settings', $settings, ['id' => $library->entityid]);
        $path = make_request_directory() . '/copia.zip';
        copies::make((int) $library->entityid)->copy_content_to($path);
        // The copy, changed by someone.
        $zip = new \ZipArchive();
        $zip->open($path);
        $entity = str_replace(
            '{""skin"":""public""}',
            '{""skin"":""public"",""practice"":true}',
            (string) $zip->getFromName('entity.csv')
        );
        $this->assertStringContainsString('practice', $entity);
        $zip->addFromString('entity.csv', $entity);
        $zip->close();
        $back = recover::restore($path, 2);
        $this->assertFalse(practice::is((int) $back->entityid));
        $this->assertSame('public', skins::of((int) $back->entityid));
    }

    /**
     * A copy that cannot be written whole is an error, not a short copy that looks fine.
     */
    public function test_copy_on_a_full_disk(): void {
        if (!file_exists('/dev/full')) {
            $this->markTestSkipped('This system has no full disk to try.');
        }
        $plain = make_request_directory() . '/claro.txt';
        file_put_contents($plain, 'una copia');
        $this->expectException(\RuntimeException::class);
        age::encrypt($plain, '/dev/full', age::keypair()['public']);
    }
}
