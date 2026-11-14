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
 * Tests for the genres of the stories: corners a library makes of its own.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\genres
 */
#[\PHPUnit\Framework\Attributes\CoversClass(genres::class)]
final class genres_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var \stdClass The library of the tests. */
    private \stdClass $library;

    /**
     * A record of the library with its class, its topics and a copy with its shelf mark.
     *
     * @param string $title
     * @param string $class
     * @param string[] $topics
     * @param string $mark The first line of its shelf mark.
     * @return int Its id.
     */
    private function book(string $title, string $class, array $topics, string $mark = ''): int {
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
        $gen->create_item(['libraryid' => $this->library->id, 'recordid' => $record->id, 'callnumber1' => $mark ?: $class]);
        return (int) $record->id;
    }

    /**
     * A genre takes the books of its topics (any form of them) or of its shelf mark, only from the corners of stories;
     * the corner of stories keeps the rest; the corner page, the search and its facets know it.
     */
    public function test_genre_corner(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $this->book('El cometa dormido', '82-31', ['Misterio']);
        $this->book('La isla del cometa', '82-31', ['Detectives', 'Amistad']);
        $this->book('Cometa de huellas', '82-32', ['misterios']);
        $spine = $this->book('La casa del cometa', 'N', [], 'N MIS');
        $this->book('Los volcanes', '55', ['Misterio']);
        $plain = $this->book('Cometa de verano', '82-31', ['Amistad']);

        genres::save($this->library, [['label' => 'Misterio', 'topics' => ['Misterio', 'Detectives'],
            'prefixes' => ['n mis']]]);
        $genres = genres::of($this->library);
        $this->assertSame('g1', $genres[0]['code']);
        $this->assertSame(['N MIS'], $genres[0]['prefixes']);
        $this->assertSame(['Misterio', 'Detectives'], $genres[0]['topics']);

        $corners = array_column(catalogue::corners($this->library), null, 'code');
        // Four stories (two topics, the plural form of one, and the shelf mark); the volcanoes are science.
        $this->assertSame(4, $corners['g1']['count']);
        $this->assertSame('Misterio', $corners['g1']['label']);
        $this->assertSame(1, $corners['8']['count'], 'the 8 keeps the story that is in no genre');
        $this->assertArrayNotHasKey('N', $corners, 'the letter corner gave its only book');
        $this->assertSame(1, $corners['5']['count']);
        // The genre comes just before the corner of stories it takes from.
        $codes = array_column(catalogue::corners($this->library), 'code');
        $this->assertSame(array_search('8', $codes) - 1, array_search('g1', $codes));

        $page = catalogue::corner($this->library, 'g1');
        $this->assertSame(4, $page['total']);
        $this->assertSame([$plain], array_map(fn($card) => (int) $card['id'], catalogue::corner($this->library, '8')['cards']));

        $found = catalogue::search($this->library, 'cometa');
        $this->assertSame(['8' => 1, 'g1' => 4], $found['facets']['corners']);
        // Each genre is asked only about the results, not for all its books on every search.
        $one = array_key_first(genres::records($this->library)['g1']);
        $this->assertSame([$one], array_keys(genres::records($this->library, [$one, 999999])['g1']));
        $this->assertSame([], genres::records($this->library, [])['g1']);
        $narrowed = catalogue::search($this->library, 'cometa', 0, false, 'g1');
        $this->assertSame(4, $narrowed['total']);
        $this->assertContains($spine, array_map(fn($card) => (int) $card['id'], $narrowed['cards']));
        $this->assertSame([$plain], array_map(
            fn($card) => (int) $card['id'],
            catalogue::search($this->library, 'cometa', 0, false, '8')['cards']
        ));

        // Where they take books from: only the 8, and the shelf mark in the letter corner no longer counts.
        genres::save($this->library, genres::of($this->library), ['8']);
        $corners = array_column(catalogue::corners($this->library), null, 'code');
        $this->assertSame(3, $corners['g1']['count']);
        $this->assertSame(1, $corners['N']['count']);
    }

    /**
     * The genres it has topics for are proposed, with how many books, those already made left out; the codes stay with
     * their genres, and a library has twelve at most.
     */
    public function test_proposals_and_codes(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        for ($i = 0; $i < genres::ENOUGH; $i++) {
            $this->book('Dragones ' . $i, '82-31', [$i % 2 ? 'Magia' : 'Fantasía']);
            $this->book('Pistas ' . $i, '82-31', ['Intriga']);
        }
        $this->book('Una risa', '82-31', ['Humor']);
        $proposals = array_column(genres::proposals($this->library), null, 'key');
        $this->assertSame(genres::ENOUGH, $proposals['fantasy']['count']);
        $this->assertEqualsCanonicalizing(['Fantasía', 'Magia'], $proposals['fantasy']['topics']);
        $this->assertArrayHasKey('mystery', $proposals);
        $this->assertArrayNotHasKey('humour', $proposals, 'one book is not enough');

        genres::save($this->library, [['label' => $proposals['mystery']['label'], 'kind' => 'mystery',
            'topics' => $proposals['mystery']['topics']]]);
        $this->assertArrayNotHasKey('mystery', array_column(genres::proposals($this->library), null, 'key'));
        $this->assertSame('mystery', genres::of($this->library)[0]['kind'], 'its kind, for its drawing');
        // A new one takes the next code; the first keeps its own when another is taken away before it.
        $genres = genres::of($this->library);
        $genres[] = ['label' => 'Fantasía', 'topics' => ['Fantasía']];
        array_unshift($genres, ['label' => 'Otro', 'topics' => ['Nada']]);
        genres::save($this->library, $genres);
        $this->assertSame(['g2', 'g1', 'g3'], array_column(genres::of($this->library), 'code'));
        genres::save($this->library, array_slice(genres::of($this->library), 1));
        $this->assertSame(['g1', 'g3'], array_column(genres::of($this->library), 'code'));
        // Twelve at most.
        genres::save($this->library, array_map(fn($n) => ['label' => 'G' . $n, 'topics' => ['T' . $n]], range(1, 15)));
        $this->assertCount(genres::MAX, genres::of($this->library));
    }

    /**
     * The topics of a catalogue in French or Portuguese make their genres too, as their keys come out, plurals included.
     */
    public function test_known_in_four_languages(): void {
        $known = [];
        foreach (genres::KNOWN as $genre => $keys) {
            foreach ($keys as $key) {
                $known[$key] = $genre;
            }
        }
        $topics = ['Aventures' => 'adventure', "Romans d'aventures" => 'adventure', 'Bandes dessinées' => 'comic',
            'Mystères' => 'mystery', 'Romans policiers' => 'mystery', 'Science-fiction' => 'scifi', 'Animaux' => 'animals',
            'Ficção científica' => 'scifi', 'Histórias em quadrinhos' => 'comic', 'Banda desenhada' => 'comic',
            'Dragões' => 'fantasy', 'Animais' => 'animals', 'Esportes' => 'sport', 'Aventuras' => 'adventure'];
        foreach ($topics as $topic => $genre) {
            $this->assertSame($genre, $known[subjects::key($topic)] ?? null, $topic);
        }
    }
}
