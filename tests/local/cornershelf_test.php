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
 * Tests for the shelf of corners: each gesture changes only its own thing.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\cornershelf
 */
#[\PHPUnit\Framework\Attributes\CoversClass(cornershelf::class)]
final class cornershelf_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var \stdClass The library of the tests. */
    private \stdClass $library;

    /**
     * A record of the library with its class and its topics.
     *
     * @param string $title
     * @param string $class
     * @param string[] $topics
     */
    private function book(string $title, string $class, array $topics = []): void {
        global $DB;
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $entityid = (int) $this->library->entityid;
        $record = $gen->create_record(['entityid' => $entityid, 'title' => $title,
            'searchtext' => \core_text::strtolower($title)]);
        $now = time();
        $authority = function (string $kind, string $code, string $label) use ($DB, $entityid, $now): int {
            $id = $DB->get_field(self::T . 'authority', 'id', ['entityid' => $entityid, 'kind' => $kind, 'label' => $label]);
            return $id ? (int) $id : (int) $DB->insert_record(self::T . 'authority', ['entityid' => $entityid, 'kind' => $kind,
                'code' => $code, 'label' => $label, 'timecreated' => $now, 'timemodified' => $now]);
        };
        $DB->insert_record(self::T . 'recauth', ['recordid' => $record->id,
            'authorityid' => $authority('class', $class, $class), 'sortorder' => 0]);
        foreach ($topics as $i => $topic) {
            $DB->insert_record(self::T . 'recauth', ['recordid' => $record->id,
                'authorityid' => $authority('subject', '', $topic), 'sortorder' => $i + 1]);
        }
        $gen->create_item(['libraryid' => $this->library->id, 'recordid' => $record->id, 'callnumber1' => $class]);
    }

    /**
     * Does one gesture, as the page would, and reads the library again.
     *
     * @param array $post
     * @return string The anchor it comes back to.
     */
    private function touch(array $post): string {
        $_POST = $post;
        $anchor = cornershelf::apply($this->library);
        $_POST = [];
        $this->library = catalogue::library($this->library->slug);
        \cache::make('local_oksigeniaayvurenda', 'corners')->purge();
        return $anchor;
    }

    /**
     * The codes of the corners in their order, the hidden ones marked with «-».
     *
     * @return string[]
     */
    private function shelf(): array {
        return array_map(fn($c) => ($c['hidden'] ? '-' : '') . $c['code'], catalogue::corners($this->library, true));
    }

    /**
     * «◀» and «▶» move a corner one place (never past the ends), «Hide» turns it round and «Show» back, and its name on
     * its back is kept, or the classification's when blank.
     */
    public function test_corners(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $this->book('Las estrellas', '52');
        $this->book('La isla', '82-31');
        $this->book('Los volcanes', '91');
        $this->assertSame(['5', '8', '9'], $this->shelf());
        $this->assertSame('r8', $this->touch(['move' => '8:-1']));
        $this->assertSame(['8', '5', '9'], $this->shelf());
        $this->touch(['move' => '8:-1']);
        $this->assertSame(['8', '5', '9'], $this->shelf());
        $this->touch(['move' => '9:1']);
        $this->assertSame(['8', '5', '9'], $this->shelf());
        $this->assertSame('r5', $this->touch(['hide' => '5']));
        $this->assertSame(['8', '-5', '9'], $this->shelf());
        $this->assertSame(['8', '9'], array_column(catalogue::corners($this->library), 'code'));
        $this->touch(['show' => '5']);
        $this->assertSame(['8', '5', '9'], $this->shelf());
        $this->touch(['rename' => '5', 'label' => 'Naturaleza']);
        $byname = array_column(catalogue::corners($this->library, true), 'label', 'code');
        $this->assertSame('Naturaleza', $byname['5']);
        $this->touch(['rename' => '5', 'label' => '']);
        $byname = array_column(catalogue::corners($this->library, true), null, 'code');
        $this->assertSame($byname['5']['default'], $byname['5']['label']);
        // A gesture on one corner leaves the others as they were.
        $this->assertSame(['8', '5', '9'], $this->shelf());
        $page = cornershelf::context($this->library, 'https://example.com/corners.php', 'r8');
        $this->assertTrue($page['plates'][0]['first']);
        $this->assertTrue($page['plates'][0]['justsaved']);
        $this->assertTrue($page['plates'][2]['last']);
    }

    /**
     * A genre the catalogue suggests is added with one touch, changed and taken away; the lights say where the genres
     * take their books from, and the last one stays on.
     */
    public function test_genres(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        for ($i = 0; $i < genres::ENOUGH; $i++) {
            $this->book('El caso ' . $i, '82-31', ['Misterio']);
        }
        $this->book('Las estrellas', '52');
        $page = cornershelf::context($this->library, 'https://example.com/corners.php');
        $this->assertSame(['mystery'], array_column($page['genres']['proposals'], 'key'));
        $this->assertSame('ayv-ri-generos', $this->touch(['propose' => 'mystery']));
        $genres = genres::of($this->library);
        $this->assertCount(1, $genres);
        $code = $genres[0]['code'];
        $this->assertContains($code, $this->shelf());
        $this->touch(['genre' => $code, 'label' => 'Detectives', 'topics' => 'Misterio, Detectives', 'prefixes' => 'N MIS']);
        $genres = genres::of($this->library);
        $this->assertSame(
            ['Detectives', ['Misterio', 'Detectives'], ['N MIS']],
            [$genres[0]['label'], $genres[0]['topics'], $genres[0]['prefixes']]
        );
        // Renamed on its plate, the genre is renamed; blank, it keeps its name.
        $this->touch(['rename' => $code, 'label' => 'Enigmas']);
        $this->assertSame('Enigmas', genres::of($this->library)[0]['label']);
        $this->touch(['rename' => $code, 'label' => '']);
        $this->assertSame('Enigmas', genres::of($this->library)[0]['label']);
        $this->assertSame('ayv-ri-de', $this->touch(['from' => '5:1']));
        $this->assertContains('5', genres::from($this->library));
        $this->touch(['from' => '5:0']);
        $this->assertNotContains('5', genres::from($this->library));
        foreach (genres::from($this->library) as $from) {
            $this->touch(['from' => $from . ':0']);
        }
        $this->assertNotEmpty(genres::from($this->library));
        $this->touch(['genre' => 'new', 'label' => 'Humor', 'topics' => 'Humor', 'prefixes' => '']);
        $this->assertSame(['Enigmas', 'Humor'], array_column(genres::of($this->library), 'label'));
        // A topic touched on its back goes off, and on again; one more is written; the name alone leaves the topics.
        $topics = fn() => genres::of($this->library)[0]['topics'];
        $this->assertSame('ayv-ri-g' . $code, $this->touch(['topic' => $code . '|Detectives']));
        $this->assertSame(['Misterio'], $topics());
        $this->touch(['topic' => $code . '|Detectives']);
        $this->assertSame(['Misterio', 'Detectives'], $topics());
        $this->touch(['genretopic' => $code, 'addtopic' => 'Casos sin resolver']);
        $this->assertSame(['Misterio', 'Detectives', 'Casos sin resolver'], $topics());
        $this->touch(['genretopic' => $code, 'addtopic' => 'misterio']);
        $this->assertCount(3, $topics());
        $this->touch(['genre' => $code, 'label' => 'Casos', 'prefixes' => 'N CAS']);
        $this->assertSame(
            ['Casos', ['Misterio', 'Detectives', 'Casos sin resolver'], ['N CAS']],
            [genres::of($this->library)[0]['label'], $topics(), genres::of($this->library)[0]['prefixes']]
        );
        // Made with its name alone, its name is its first topic.
        $this->touch(['genre' => 'new', 'label' => 'Rimas']);
        $this->assertSame(['Rimas'], array_values(array_filter(
            genres::of($this->library),
            fn($g) => $g['label'] === 'Rimas'
        ))[0]['topics']);
        $this->touch(['genredrop' => $code]);
        $this->assertSame(['Humor', 'Rimas'], array_column(genres::of($this->library), 'label'));
    }
}
