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
 * Tests for what a notice looks like by e-mail.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\mail
 */
#[\PHPUnit\Framework\Attributes\CoversClass(mail::class)]
final class mail_test extends \advanced_testcase {
    /**
     * A page whole, with what it was given and nothing of it unescaped.
     */
    public function test_html(): void {
        $this->resetAfterTest();
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library(['slug' => 'central', 'name' => 'Sala <script>alert(1)</script>']);
        $html = mail::html([
            'library' => $library,
            'hello' => 'Hola, Ana:',
            'heading' => 'Tu libro <b>te espera</b>',
            'lead' => ['Ha vuelto.'],
            'book' => mail::book(7, 'El faro & la mar', 'Ana Montes'),
            'stamp' => ['label' => 'Hasta', 'value' => '6 de noviembre'],
            'place' => ['label' => 'Dónde', 'value' => 'Sala'],
            'button' => ['url' => 'https://example.com/?a=1&b=2', 'text' => 'Ver el libro'],
            'why' => 'Porque sí.',
            'prefs' => true,
        ]);
        $this->assertStringStartsWith('<!DOCTYPE html>', trim($html));
        $this->assertStringContainsString('El faro &amp; la mar', $html);
        $this->assertStringContainsString('https://example.com/?a=1&amp;b=2', $html);
        $this->assertStringContainsString('6 de noviembre', $html);
        // In the elements' own styles the paper is near white: a program that darkens by itself turns anything warmer mustard.
        $this->assertSame(0, preg_match('/style="[^"]*background-color:#(efe6d3|f7efdc)/', $html));
        $this->assertStringContainsString('notificationpreferences.php', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<b>te espera', $html);
        $this->assertStringNotContainsString('src=""', $html);
        // A cover without a picture carries its title; nothing here asks for a session.
        $this->assertStringNotContainsString('pluginfile.php', $html);
    }

    /**
     * The seal is the same for the same picture and different for any other, and only pictures of a centre come out; never one
     * in WebP, which some mail programs do not show.
     */
    public function test_seal(): void {
        $this->resetAfterTest();
        $seal = mail::seal('cover', 7, 'cover');
        $this->assertSame($seal, mail::seal('cover', 7, 'cover'));
        $this->assertNotSame($seal, mail::seal('cover', 8, 'cover'));
        $this->assertNotSame($seal, mail::seal('portrait', 7, 'cover'));
        $context = \context_system::instance();
        $file = ['contextid' => $context->id, 'component' => 'local_oksigeniaayvurenda', 'filepath' => '/', 'itemid' => 7];
        $fs = get_file_storage();
        $fs->create_file_from_string($file + ['filearea' => 'cover', 'filename' => 'cover.jpg'], 'x');
        $fs->create_file_from_string($file + ['filearea' => 'libraryimage', 'filename' => 'image.webp'], 'x');
        $fs->create_file_from_string($file + ['filearea' => 'digital', 'filename' => 'cover.jpg'], 'x');
        $this->assertNotNull(mail::file_of('cover', 7, 'cover'));
        $this->assertNull(mail::file_of('libraryimage', 7, 'image'));
        $this->assertNull(mail::file_of('digital', 7, 'cover'));
        $this->assertStringContainsString('mailimage.php', mail::image('cover', 7, 'cover'));
        $this->assertStringContainsString('s=' . $seal, mail::book(7, 'El faro')['cover']);
    }

    /**
     * A reservation by e-mail: the design, and the same in plain words.
     */
    public function test_a_hold_reaches_the_mail(): void {
        global $DB;
        $this->resetAfterTest();
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library(['slug' => 'central', 'name' => 'Biblioteca de Riosalto']);
        $user = $this->getDataGenerator()->create_user(['firstname' => 'Hugo']);
        $reader = $gen->create_reader(['entityid' => $library->entityid, 'userid' => $user->id]);
        $record = $gen->create_record(['entityid' => $library->entityid, 'title' => 'El faro dormido']);
        $now = time();
        $hold = $DB->insert_record('local_oksigeniaayvurenda_hold', ['libraryid' => $library->id, 'recordid' => $record->id,
            'readerid' => $reader->id, 'status' => 'ready', 'timecreated' => $now, 'timeready' => $now,
            'timeexpires' => $now + 5 * DAYSECS, 'timemodified' => $now]);
        $sink = $this->redirectMessages();
        $this->assertTrue(holds::notify($hold));
        $message = $sink->get_messages()[0];
        $this->assertStringStartsWith('<!DOCTYPE html>', trim($message->fullmessagehtml));
        $this->assertStringContainsString('El faro dormido', $message->fullmessagehtml);
        $this->assertStringContainsString('Biblioteca de Riosalto', $message->fullmessagehtml);
        $this->assertStringContainsString('Hugo', $message->fullmessagehtml);
        $this->assertStringContainsString('/local/oksigeniaayvurenda/record.php?library=central', $message->fullmessagehtml);
        $this->assertStringContainsString('lang=' . current_language(), $message->fullmessagehtml);
        $this->assertStringNotContainsString('lang=', (string) $message->contexturl);
        $this->assertStringNotContainsString('<', $message->fullmessage);
        $this->assertStringContainsString('El faro dormido', $message->fullmessage);
        $this->assertStringContainsString('Biblioteca de Riosalto', $message->fullmessage);
    }

    /**
     * The button stays on the page for whoever has no app; with Aty on their phone it goes by way of the app, and only for a page
     * the app knows how to open.
     */
    public function test_the_button_and_the_app(): void {
        global $DB;
        $this->resetAfterTest();
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library(['slug' => 'central']);
        $user = $this->getDataGenerator()->create_user();
        $make = fn(string $page) => mail::html([
            'library' => $library,
            'heading' => 'Aviso',
            'userid' => $user->id,
            'button' => [
                'url' => (new \moodle_url('/local/oksigeniaayvurenda/' . $page, ['library' => 'central']))->out(false),
                'text' => 'Ver',
            ],
        ]);
        $this->assertStringContainsString('/local/oksigeniaayvurenda/record.php?library=central', $make('record.php'));
        if (!method_exists('\\local_oksigeniaaty\\api', 'app_link')) {
            return;
        }
        $DB->insert_record('user_devices', [
            'userid' => $user->id,
            'appid' => get_config('tool_mobile', 'androidappid') ?: 'com.moodle.moodlemobile',
            'name' => 'x',
            'model' => 'x',
            'platform' => 'Android',
            'version' => '14',
            'pushid' => 'x',
            'uuid' => 'x',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $this->assertStringContainsString('/local/oksigeniaaty/go.php', $make('record.php'));
        $this->assertStringNotContainsString('/local/oksigeniaaty/go.php', $make('desk.php'));
    }

    /**
     * A notice without its design still comes in the same clothes, paragraph by paragraph.
     */
    public function test_plain(): void {
        $this->assertSame(
            ['heading' => 'Asunto', 'lead' => ['Uno.', 'Dos.']],
            mail::plain('Asunto', "Uno.\n\n  Dos.  \n")
        );
    }

    /**
     * The herald signs in a school; in the other skins, the library does, by its name and without a picture, unless the centre
     * asks the herald to; and nobody signs what does not have to be signed.
     */
    public function test_sign(): void {
        $this->resetAfterTest();
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library(['name' => 'Biblioteca de Riosalto']);
        $entityid = (int) $library->entityid;
        $this->assertNull(mail::sign($entityid, null, false, $library));
        $this->assertStringContainsString('Mainumby', mail::sign($entityid, null, true, $library)['text']);
        foreach (['public', 'university', 'company', 'mobile'] as $skin) {
            libraries::update_entity($entityid, ['skin' => $skin]);
            skins::forget($entityid);
            $signed = mail::sign($entityid, null, true, $library);
            $this->assertSame('— Biblioteca de Riosalto', $signed['text'], $skin);
            $this->assertSame('', $signed['portrait'], $skin);
            $this->assertSame('', $signed['initial'], $skin);
            $this->assertNull(mail::sign($entityid, null, true), $skin);
        }
        characters::save($entityid, ['herald' => ['enabled' => true, 'name' => 'Rosa', 'sex' => 'f', 'signs' => true]]);
        $this->assertStringContainsString('Rosa', mail::sign($entityid, null, false, $library)['text']);
    }

    /**
     * A mobile library speaks with its own words where it has them, and with those of a public library where it does not.
     */
    public function test_a_mobile_library_has_its_words(): void {
        $this->resetAfterTest();
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $entityid = (int) $library->entityid;
        libraries::update_entity($entityid, ['skin' => 'mobile']);
        skins::forget($entityid);
        $this->assertSame('visitpulsemobile', skins::words($entityid, 'visitpulse'));
        $this->assertSame('holdreadyheadingsecondary', skins::words($entityid, 'holdreadyheading'));
    }

    /**
     * The name of a library has its own article, or none («Biblioteca de Riosalto», «La Casa del Libro»): in Spanish, no
     * sentence puts a preposition right before it.
     */
    public function test_no_preposition_before_a_name(): void {
        global $CFG;
        $string = [];
        include($CFG->dirroot . '/local/oksigeniaayvurenda/lang/es/local_oksigeniaayvurenda.php');
        $bad = [];
        foreach ($string as $key => $text) {
            if (preg_match('/\b(en|de|a)\s+\{\$a->library\}/iu', $text)) {
                $bad[] = $key;
            }
        }
        $this->assertSame([], $bad);
    }
}
