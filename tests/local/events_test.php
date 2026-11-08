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

use local_oksigeniaayvurenda\event\action;

/**
 * Each delicate action tells Moodle's logs who did it, with ids and codes only.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(action::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(activity::class)]
final class events_test extends \advanced_testcase {
    /** @var \local_oksigeniaayvurenda_generator */
    private $gen;

    /** @var \stdClass A library of its centre. */
    private \stdClass $library;

    /** @var int Its centre. */
    private int $entity;

    /**
     * A centre with its library, and someone with a session who does things.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/cohort/lib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $this->library = $this->gen->create_library(['slug' => 'central']);
        $this->entity = (int) $this->library->entityid;
    }

    /**
     * What something tells the logs, by the short name of each event.
     *
     * @param callable $work
     * @return array Short name => event.
     */
    private function told(callable $work): array {
        $sink = $this->redirectEvents();
        $work();
        $events = $sink->get_events();
        $sink->close();
        $told = [];
        foreach ($events as $event) {
            if ($event->component === 'local_oksigeniaayvurenda') {
                $told[substr(strrchr(get_class($event), '\\'), 1)] = $event;
            }
        }
        return $told;
    }

    /**
     * It told this, about this object, in the place of the centre.
     *
     * @param string $name
     * @param callable $work
     * @param int|null $objectid
     * @return \core\event\base
     */
    private function tells(string $name, callable $work, ?int $objectid = null): \core\event\base {
        $told = $this->told($work);
        $this->assertArrayHasKey($name, $told, implode(', ', array_keys($told)));
        $event = $told[$name];
        $this->assertSame((int) get_admin()->id, (int) $event->userid);
        $this->assertSame(access::context($this->entity)->id, $event->get_context()->id);
        if ($objectid !== null) {
            $this->assertSame($objectid, (int) $event->objectid);
        }
        $this->assertIsString($event->get_description());
        return $event;
    }

    /**
     * Every event has its name in both languages, belongs to a group of the page, and carries nothing readable.
     */
    public function test_every_event_is_named_and_in_a_group(): void {
        $all = array_merge(...array_values(activity::KINDS));
        $found = [];
        foreach (glob(__DIR__ . '/../../classes/event/*.php') as $file) {
            $name = basename($file, '.php');
            if ($name !== 'action') {
                $found[] = $name;
                $class = '\\local_oksigeniaayvurenda\\event\\' . $name;
                $this->assertNotEmpty($class::get_name());
            }
        }
        sort($found);
        sort($all);
        $this->assertSame($found, $all);
        foreach (['en', 'es'] as $lang) {
            $strings = get_string_manager()->load_component_strings('local_oksigeniaayvurenda', $lang);
            foreach ($found as $name) {
                $this->assertArrayHasKey('event' . str_replace('_', '', $name), $strings);
            }
        }
    }

    /**
     * A name or a title in an event is a mistake the event refuses.
     */
    public function test_nothing_readable_gets_in(): void {
        foreach (['Ana Inventada', 'Platero y yo', 'ana@example.com', str_repeat('a', 41)] as $text) {
            try {
                \local_oksigeniaayvurenda\event\reader_viewed::fire($this->entity, 1, ['what' => $text]);
                $this->fail('It let this in: ' . $text);
            } catch (\coding_exception $e) {
                $this->assertStringContainsString('only ids and codes', $e->getMessage());
            }
        }
    }

    /**
     * Joining two readers, by hand.
     */
    public function test_reader_merged(): void {
        $keep = $this->gen->create_reader(['entityid' => $this->entity, 'firstname' => 'Ana', 'lastname' => 'Uno']);
        $gone = $this->gen->create_reader(['entityid' => $this->entity, 'firstname' => 'Ana', 'lastname' => 'Una']);
        $event = $this->tells(
            'reader_merged',
            fn() => twins::join($this->entity, (int) $keep->id, (int) $gone->id),
            (int) $keep->id
        );
        $this->assertSame(['gone' => (int) $gone->id], $event->other);
    }

    /**
     * Pairing a reader with a person of a platform.
     */
    public function test_reader_paired(): void {
        $reader = $this->gen->create_reader(['entityid' => $this->entity, 'firstname' => 'Ana', 'lastname' => 'Uno']);
        $person = ['id' => 'x1', 'external' => 'x1', 'firstname' => 'Ana', 'lastname' => 'Uno', 'email' => ''];
        $this->tells('reader_paired', fn() => pairing::link($this->entity, (int) $reader->id, $person), (int) $reader->id);
    }

    /**
     * Matching a reader with an account.
     */
    public function test_reader_matched(): void {
        $reader = $this->gen->create_reader(['entityid' => $this->entity, 'firstname' => 'Ana', 'lastname' => 'Uno']);
        $user = $this->getDataGenerator()->create_user();
        $event = $this->tells(
            'reader_matched',
            fn() => matching::link($this->entity, (int) $reader->id, (int) $user->id),
            (int) $reader->id
        );
        $this->assertSame((int) $user->id, (int) $event->relateduserid);
    }

    /**
     * Bringing readers from cohorts.
     */
    public function test_readers_synced(): void {
        $cohort = $this->getDataGenerator()->create_cohort();
        cohort_add_member($cohort->id, $this->getDataGenerator()->create_user()->id);
        sources\cohorts::save($this->entity, [$cohort->id => ['type' => 'student', 'stage' => '', 'group' => false]]);
        $event = $this->tells('readers_synced', fn() => sources\cohorts::sync($this->entity));
        $this->assertSame(1, $event->other['created']);
        $this->assertSame('moodle', $event->other['source']);
    }

    /**
     * Changing where the people come from, without its keys.
     */
    public function test_connector_changed(): void {
        $event = $this->tells('connector_changed', fn() => sources\cifra::save($this->entity, [
            'enabled' => true, 'url' => 'https://cifra.ejemplo.org', 'mode' => 'key', 'key' => 'llave-secreta',
        ]));
        $this->assertStringNotContainsString('llave-secreta', json_encode($event->get_data()));
        $cohorts = $this->told(fn() => sources\cohorts::save($this->entity, []));
        $this->assertSame('cohorts', $cohorts['connector_changed']->other['kind']);
        $courses = $this->told(fn() => sources\courses::save($this->entity, []));
        $this->assertSame('courses', $courses['connector_changed']->other['kind']);
    }

    /**
     * Setting the key of the copies, made here or brought.
     */
    public function test_offsite_key_created(): void {
        $event = $this->tells('offsite_key_created', fn() => offsite::new_key($this->entity));
        $this->assertSame('made', $event->other['mode']);
        $recipient = \local_oksigeniaayvurenda\core\age::keypair()['public'];
        $event = $this->tells('offsite_key_created', fn() => offsite::use_key($this->entity, $recipient));
        $this->assertSame('brought', $event->other['mode']);
    }

    /**
     * Adding and taking away a place for the copies, without its address or its password.
     */
    public function test_offsite_places(): void {
        offsite::$network = fn(string $method, string $url, string $payload): array => [201, ''];
        try {
            $id = null;
            $event = $this->tells('offsite_place_added', function () use (&$id) {
                $id = offsite::add_webdav($this->entity, 'https://nube.ejemplo.org/dav/', 'biblioteca', 'clave-secreta');
            });
            $this->assertSame('webdav', $event->other['kind']);
            $this->assertStringNotContainsString('nube.ejemplo.org', json_encode($event->get_data()));
            $this->tells('offsite_place_removed', fn() => offsite::remove($this->entity, $id));
        } finally {
            offsite::$network = null;
        }
    }

    /**
     * Making and stopping the address of the library's computer, without the address.
     */
    public function test_offsite_pull(): void {
        $url = '';
        $event = $this->tells('offsite_pull_created', function () use (&$url) {
            $url = offsite::new_pull($this->entity);
        });
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertStringNotContainsString($query['key'], json_encode($event->get_data()));
        $this->tells('offsite_pull_stopped', fn() => offsite::stop_pull($this->entity));
    }

    /**
     * Publishing, sharing and deleting a voice.
     */
    public function test_voices(): void {
        $user = $this->getDataGenerator()->create_user();
        $record = $this->gen->create_record(['entityid' => $this->entity, 'title' => 'Platero y yo']);
        $reader = $this->gen->create_reader(['entityid' => $this->entity, 'userid' => $user->id]);
        $path = make_request_directory() . '/voz.webm';
        file_put_contents($path, 'una voz inventada');
        $id = voices::record($this->library, (int) $record->id, (int) $reader->id, (int) $user->id, $path, 'Capítulo 1');
        $event = $this->tells(
            'voice_consent_changed',
            fn() => voices::permission((int) $reader->id, true, (int) get_admin()->id),
            (int) $reader->id
        );
        $this->assertSame('given', $event->other['what']);
        $event = $this->tells(
            'voice_published',
            fn() => voices::approve($id, $this->library, ['licence' => 'pd', 'audience' => ['students']]),
            $id
        );
        $this->assertStringNotContainsString('Platero', json_encode($event->get_data()));
        $this->tells('voice_removed', fn() => voices::remove(digital::get($id, $this->library)), $id);
    }

    /**
     * Withdrawing copies and bringing one back.
     */
    public function test_weeding(): void {
        $item = $this->gen->create_item(['libraryid' => $this->library->id, 'barcode' => '100000L']);
        $id = 0;
        $event = $this->tells('copies_withdrawn', function () use (&$id, $item) {
            $id = weeding::withdraw($this->library, [$item->id], 'Fin de curso', [], (int) get_admin()->id);
        });
        $this->assertSame(1, $event->other['count']);
        $withdrawn = (int) weeding::record($id, $this->library)['copies'][0]['id'];
        $this->tells('copy_restored', fn() => weeding::restore($withdrawn, $this->library), $withdrawn);
    }

    /**
     * Giving up as lost what a stocktake did not find, and bringing it back.
     */
    public function test_stocktake_applied(): void {
        $this->gen->create_item(['libraryid' => $this->library->id, 'barcode' => '100000L']);
        $stocktake = stocktake::get(stocktake::start($this->library, 'Junio', null, (int) get_admin()->id), $this->library);
        $event = $this->tells('stocktake_applied', fn() => stocktake::lose($stocktake), (int) $stocktake->id);
        $this->assertSame(['what' => 'lost', 'count' => 1], $event->other);
        $this->assertSame([], $this->told(fn() => stocktake::lose($stocktake)));
    }

    /**
     * Importing a file into the catalogue, with what it brought but no titles.
     */
    public function test_catalogue_imported(): void {
        $event = $this->tells('catalogue_imported', fn() => (new import\catalogue_import($this->library, 'csv'))->run([]));
        $this->assertSame('csv', $event->other['how']);
    }

    /**
     * Taking back an addition.
     */
    public function test_addition_undone(): void {
        $record = $this->gen->create_record(['entityid' => $this->entity, 'title' => 'Platero y yo']);
        $item = $this->gen->create_item(['libraryid' => $this->library->id, 'recordid' => $record->id, 'barcode' => '100000L']);
        $event = $this->tells(
            'addition_undone',
            fn() => cataloguer::undo($this->entity, (int) $record->id, [(int) $item->id], true),
            (int) $record->id
        );
        $this->assertSame(1, $event->other['count']);
    }

    /**
     * Changing the plan, only when it changes.
     */
    public function test_plan_changed(): void {
        $event = $this->tells('plan_changed', fn() => plans::save($this->entity, plans::preset('free')));
        $this->assertSame(['copies' => 1000, 'readers' => 150], $event->other);
        $this->assertSame([], $this->told(fn() => plans::save($this->entity, plans::preset('free'))));
    }

    /**
     * Changing the skin, only when it changes.
     */
    public function test_skin_changed(): void {
        $event = $this->tells('skin_changed', fn() => libraries::update_entity($this->entity, ['skin' => 'public']));
        $this->assertSame(['from' => 'school', 'to' => 'public'], $event->other);
        $this->assertSame([], $this->told(fn() => libraries::update_entity($this->entity, ['skin' => 'public'])));
    }

    /**
     * Closing a centre to its readers and opening it, only when it changes.
     */
    public function test_centre_closed_and_opened(): void {
        $this->tells('centre_closed', fn() => access::set_closed($this->entity, true));
        $this->assertSame([], $this->told(fn() => access::set_closed($this->entity, true)));
        $this->tells('centre_opened', fn() => access::set_closed($this->entity, false));
    }

    /**
     * Creating a centre, and removing a whole one.
     */
    public function test_centre_created_and_removed(): void {
        $sink = $this->redirectEvents();
        $library = libraries::create_entity(['name' => 'Otro centro']);
        $names = array_map(fn($e) => get_class($e), $sink->get_events());
        $sink->close();
        $this->assertContains('local_oksigeniaayvurenda\event\centre_created', $names);
        $this->gen->create_reader(['entityid' => $library->entityid, 'firstname' => 'Ana']);
        $event = $this->told(fn() => removal::remove((int) $library->entityid))['centre_removed'];
        $this->assertSame(['readers' => 1, 'items' => 0], $event->other);
    }

    /**
     * Deleting an empty library.
     */
    public function test_library_deleted(): void {
        $room = libraries::create_library($this->entity, ['name' => 'Aula', 'kind' => 'classroom']);
        $this->tells('library_deleted', fn() => libraries::delete_library((int) $room->id), (int) $room->id);
    }

    /**
     * Opening the public door, without what it says.
     */
    public function test_window_changed(): void {
        $event = $this->tells(
            'window_changed',
            fn() => window::save($this->library, ['open' => true, 'text' => 'Bienvenidos a Ana Inventada']),
            (int) $this->library->id
        );
        $this->assertSame('open', $event->other['what']);
        $this->assertStringNotContainsString('Bienvenidos', json_encode($event->get_data()));
    }

    /**
     * Giving and taking away who keeps a library.
     */
    public function test_keepers(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->gen->create_reader(['entityid' => $this->entity, 'userid' => $user->id]);
        $event = $this->tells(
            'keeper_added',
            fn() => keepers::add($this->library, (int) $user->id, (int) get_admin()->id),
            (int) $this->library->id
        );
        $this->assertSame((int) $user->id, (int) $event->relateduserid);
        $this->tells('keeper_removed', fn() => keepers::remove($this->library, (int) $user->id), (int) $this->library->id);
    }

    /**
     * Changing the lending rules.
     */
    public function test_rules_changed(): void {
        $event = $this->tells('rules_changed', fn() => rules::save((int) $this->library->id, [
            ['readertype' => '*', 'itemtype' => '*', 'loandays' => 14, 'maxloans' => 3, 'renewals' => 1],
        ]), (int) $this->library->id);
        $this->assertSame(['count' => 1], $event->other);
    }

    /**
     * Changing who may ask for books online.
     */
    public function test_hold_settings_changed(): void {
        $this->tells('hold_settings_changed', fn() => holds::save_settings($this->entity, ['online' => true]));
    }

    /**
     * Saving and deleting a platform that opens the library.
     */
    public function test_lti_platform(): void {
        $id = 0;
        $this->tells('lti_platform_saved', function () use (&$id) {
            $id = ltitool::save_platform($this->entity, ['name' => 'Plataforma', 'issuer' => 'https://plataforma.ejemplo.org',
                'clientid' => 'abc', 'authloginurl' => 'https://plataforma.ejemplo.org/auth',
                'authtokenurl' => 'https://plataforma.ejemplo.org/token', 'keyseturl' => 'https://plataforma.ejemplo.org/keys']);
        });
        $this->tells('lti_platform_deleted', fn() => ltitool::delete_platform($this->entity, $id), $id);
    }

    /**
     * Sending the link of the app, where the app is.
     */
    public function test_applink_sent(): void {
        if (!applink::available()) {
            $this->markTestSkipped('Without the plugin of the app.');
        }
        $user = $this->getDataGenerator()->create_user();
        $reader = $this->gen->create_reader(['entityid' => $this->entity, 'userid' => $user->id]);
        $sink = $this->redirectEmails();
        $event = $this->tells('applink_sent', fn() => applink::send($reader), (int) $reader->id);
        $sink->close();
        $this->assertSame('sent', $event->other['what']);
        $this->assertSame((int) $user->id, (int) $event->relateduserid);
    }

    /**
     * Giving and taking away the tutor of a group, without its name.
     */
    public function test_tutor_changed(): void {
        $year = \local_oksigeniaayvurenda\core\school_year::around(time(), '09-01', \core_date::get_server_timezone_object());
        $year = $this->gen->create_year(['entityid' => $this->entity] + $year);
        $user = $this->getDataGenerator()->create_user();
        $event = $this->tells('tutor_changed', fn() => tutors::set((int) $year->id, '3º A', (int) $user->id), (int) $year->id);
        $this->assertSame('set', $event->other['what']);
        $this->assertSame((int) $user->id, (int) $event->relateduserid);
        $this->assertStringNotContainsString('3º', json_encode($event->get_data()));
        $event = $this->tells('tutor_changed', fn() => tutors::set((int) $year->id, '3º A', 0), (int) $year->id);
        $this->assertSame('removed', $event->other['what']);
    }

    /**
     * Giving up a textbook as lost.
     */
    public function test_textbook_lost(): void {
        $bank = libraries::create_library($this->entity, ['name' => 'Banco', 'kind' => 'bank']);
        $reader = $this->gen->create_reader(['entityid' => $this->entity, 'firstname' => 'Ana']);
        $item = $this->gen->create_item(['libraryid' => $bank->id, 'barcode' => '100000L']);
        $loan = $this->gen->create_loan(['libraryid' => $bank->id, 'itemid' => $item->id, 'readerid' => $reader->id]);
        $this->tells('textbook_lost', fn() => bankround::lost($bank, (int) $loan->id, 2, time()), (int) $loan->id);
    }

    /**
     * Adding and taking out a work of the digital collection, without its title or its address.
     */
    public function test_digital(): void {
        $record = $this->gen->create_record(['entityid' => $this->entity, 'title' => 'Platero y yo']);
        $id = 0;
        $event = $this->tells('digital_added', function () use (&$id, $record) {
            $id = digital::add_link($this->library, (int) $record->id, 'https://ejemplo.org/platero', ['title' => 'Platero'], 2);
        });
        $this->assertSame('link', $event->other['kind']);
        $this->assertStringNotContainsString('platero', strtolower(json_encode($event->get_data())));
        $changed = $this->tells('digital_changed', fn() => digital::update($id, $this->library, ['licence' => 'cc-by',
            'audience' => ['public'], 'homeages' => [3]]), $id);
        $this->assertSame('3', $changed->other['homeages']);
        $this->tells('digital_removed', fn() => digital::delete($id, $this->library), $id);
    }

    /**
     * Who keeps a centre reads what was done in it, and not what was done in another; opening records goes apart.
     */
    public function test_the_page_reads_the_log_of_its_centre(): void {
        // The log waits for the transaction a test runs in.
        $this->preventResetByRollback();
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        set_config('buffersize', 0, 'logstore_standard');
        get_log_manager(true);
        set_config('entitycategories', 1, 'local_oksigeniaayvurenda');
        access::category($this->entity);
        $other = $this->gen->create_library(['slug' => 'otra']);
        access::category((int) $other->entityid);
        \local_oksigeniaayvurenda\event\keeper_removed::fire((int) $other->entityid, (int) $other->id, [], 3);
        $reader = $this->gen->create_reader(['entityid' => $this->entity, 'firstname' => 'Ana', 'lastname' => 'Inventada']);
        \local_oksigeniaayvurenda\event\reader_merged::fire($this->entity, (int) $reader->id, ['gone' => 9]);
        \local_oksigeniaayvurenda\event\reader_viewed::fire($this->entity, (int) $reader->id);
        \local_oksigeniaayvurenda\event\rules_changed::fire($this->entity, (int) $this->library->id, ['count' => 3]);

        $found = activity::events($this->entity, '', 0);
        $this->assertCount(2, $found['events']);
        $this->assertFalse($found['more']);
        $lines = activity::days($found['events'], $this->entity)[0]['lines'];
        $this->assertSame(get_string('eventruleschanged', 'local_oksigeniaayvurenda'), $lines[0]['name']);
        $detail = get_string('eventruleschangeddetail', 'local_oksigeniaayvurenda', (object) ['count' => 3]);
        $this->assertSame($detail, $lines[0]['detail']);
        $this->assertSame('Ana Inventada', $lines[1]['about']);
        // Who did it, once: the second line by the same person does not repeat the name.
        $this->assertSame(fullname(get_admin()), $lines[0]['who']);
        $this->assertSame('', $lines[1]['who']);

        $views = activity::events($this->entity, 'views', 0);
        $this->assertCount(1, $views['events']);
        get_log_manager(true);
    }
}
