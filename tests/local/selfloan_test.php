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
 * A self-loan post: what it lends and what it never says.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(selfloan::class)]
final class selfloan_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * A library with a post, two readers and some books.
     *
     * @return \stdClass
     */
    private function world(): \stdClass {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $w = new \stdClass();
        $w->gen = $gen;
        $w->library = $gen->create_library();
        $entityid = (int) $w->library->entityid;
        $w->ana = $gen->create_reader(['entityid' => $entityid, 'cardcode' => '10000H', 'firstname' => 'Ana',
            'lastname' => 'Secreto']);
        $w->leo = $gen->create_reader(['entityid' => $entityid, 'cardcode' => '10001L', 'firstname' => 'Leo',
            'lastname' => 'Oculto']);
        foreach (['faro' => '100000L', 'isla' => '100001C', 'mapa' => '100002K'] as $name => $code) {
            $record = $gen->create_record(['entityid' => $entityid, 'title' => 'Libro ' . $name]);
            $w->$name = $gen->create_item(['libraryid' => $w->library->id, 'recordid' => $record->id, 'barcode' => $code]);
        }
        $address = selfloan::create($w->library, 'Sala', (int) get_admin()->id);
        $w->key = selfloan::key_of($address);
        $w->post = selfloan::by_key($w->key)['post'];
        $w->now = time();
        return $w;
    }

    /**
     * Reads a code at the post, as the page would.
     *
     * @param \stdClass $w
     * @param string $code
     * @param string $ticket
     * @return array
     */
    private function read(\stdClass $w, string $code, string $ticket = ''): array {
        global $DB;
        $post = $DB->get_record(self::T . 'selfpost', ['id' => $w->post->id], '*', MUST_EXIST);
        return selfloan::scan($post, $w->library, $code, $ticket, $w->now);
    }

    /**
     * The key opens the post, and only while it is there and its centre open; the hash is all that stays.
     */
    public function test_the_key(): void {
        global $DB;
        $w = $this->world();
        $this->assertNull(selfloan::by_key(''));
        $this->assertNull(selfloan::by_key('nothing'));
        $this->assertSame((int) $w->library->id, (int) selfloan::by_key($w->key)['library']->id);
        $this->assertSame(hash('sha256', $w->key), $w->post->keyhash);
        $this->assertStringNotContainsString($w->key, json_encode($DB->get_record(self::T . 'selfpost', ['id' => $w->post->id])));
        access::set_closed((int) $w->library->entityid, true);
        $this->assertNull(selfloan::by_key($w->key), 'a centre that is closed has no posts');
        access::set_closed((int) $w->library->entityid, false);
        $this->assertNotNull(selfloan::by_key($w->key));
        selfloan::remove($w->library, (int) $w->post->id);
        $this->assertNull(selfloan::by_key($w->key), 'taken away, its address opens nothing');
    }

    /**
     * Card, then books: each goes out with its date. A book goes out to a card really read, never to what the screen says.
     */
    public function test_card_and_books(): void {
        global $DB;
        $w = $this->world();
        $this->assertSame('look', $this->read($w, $w->faro->barcode)['sound'], 'a book with no card asks for the card');
        $this->assertSame(0, $DB->count_records(self::T . 'loan'));

        $card = $this->read($w, $w->ana->cardcode);
        $this->assertSame('reader', $card['state']);
        $this->assertSame('Ana', $card['name']);
        $lent = $this->read($w, $w->faro->barcode, $card['ticket']);
        $this->assertSame('ok', $lent['sound']);
        $this->assertSame('Libro faro', $lent['title']);
        $loan = $DB->get_record(self::T . 'loan', ['itemid' => $w->faro->id], '*', MUST_EXIST);
        $this->assertSame((int) $w->ana->id, (int) $loan->readerid);
        $this->assertSame((int) $w->post->id, (int) $loan->postid);
        $this->assertNull($loan->staffid, 'nobody of the staff lent it');

        // Forged, of another post, or old: as if no card had been read.
        [, $until, $mac] = explode('.', $card['ticket']);
        $forged = $w->leo->id . '.' . $until . '.' . $mac;
        $this->assertSame('idle', $this->read($w, $w->isla->barcode, $forged)['state']);
        $this->assertSame('idle', $this->read($w, $w->isla->barcode, 'nonsense')['state']);
        $w->now += 3600;
        $this->assertSame('idle', $this->read($w, $w->isla->barcode, $card['ticket'])['state']);
        $w->now -= 3600;
        $this->assertSame(1, $DB->count_records(self::T . 'loan'));

        // A reader who left stops counting at once.
        $DB->set_field(self::T . 'reader', 'status', 'left', ['id' => $w->ana->id]);
        $this->assertSame('idle', $this->read($w, $w->isla->barcode, $card['ticket'])['state']);
        $this->assertArrayNotHasKey('ticket', $this->read($w, $w->ana->cardcode));
    }

    /**
     * A book read again at the same post soon after it was taken, with no card, is still «yours»; later, it comes back.
     * A copy whose loan was noted by its title goes to the desk.
     */
    public function test_read_again_and_guessed(): void {
        global $DB;
        $w = $this->world();
        selfloan::save_settings($w->library, ['returns' => 1] + selfloan::settings($w->library));
        $card = $this->read($w, $w->ana->cardcode);
        $this->assertSame('ok', $this->read($w, $w->faro->barcode, $card['ticket'])['sound']);
        $w->now += 400;
        $again = $this->read($w, $w->faro->barcode);
        $this->assertSame('look', $again['sound']);
        $this->assertSame(1, $DB->count_records_select(self::T . 'loan', 'timereturned IS NULL'));
        $w->now += selfloan::DOUBLE_ALONE;
        $this->assertSame('ok', $this->read($w, $w->faro->barcode)['sound']);
        $this->assertSame(0, $DB->count_records_select(self::T . 'loan', 'timereturned IS NULL'));

        $loan = $w->gen->create_loan(['libraryid' => $w->library->id, 'itemid' => $w->isla->id, 'readerid' => $w->leo->id]);
        $DB->set_field(self::T . 'loan', 'copyguessed', 1, ['id' => $loan->id]);
        $this->assertSame('no', $this->read($w, $w->isla->barcode)['sound']);
        $this->assertNull($DB->get_field(self::T . 'loan', 'timereturned', ['id' => $loan->id]));
    }

    /**
     * What the screen is told, whatever happens, never has anyone's surname, nor another person's name.
     */
    public function test_it_says_nothing_of_anyone(): void {
        global $DB;
        $w = $this->world();
        $ana = $this->read($w, $w->ana->cardcode);
        $this->read($w, $w->faro->barcode, $ana['ticket']);
        // Leo has it set aside; Ana has another.
        $seen = [$ana, $this->read($w, $w->leo->cardcode)];
        $w->now += selfloan::DOUBLE_ALONE;
        $seen[] = $this->read($w, $w->faro->barcode);
        $DB->insert_record(self::T . 'hold', ['libraryid' => $w->library->id, 'recordid' => $w->faro->recordid,
            'readerid' => $w->leo->id, 'status' => 'waiting', 'timecreated' => $w->now, 'timemodified' => $w->now]);
        $DB->delete_records(self::T . 'loan');
        $DB->set_field(self::T . 'item', 'status', 'available', ['id' => $w->faro->id]);
        $ana = $this->read($w, $w->ana->cardcode);
        $this->read($w, $w->faro->barcode, $ana['ticket']);
        $w->now += selfloan::DOUBLE_ALONE;
        $seen[] = $held = $this->read($w, $w->faro->barcode);
        $this->assertSame('look', $held['sound'], 'someone waits for it');
        $DB->set_field(self::T . 'item', 'status', 'held', ['id' => $w->faro->id]);
        $ana = $this->read($w, $w->ana->cardcode);
        $seen[] = $stopped = $this->read($w, $w->faro->barcode, $ana['ticket']);
        $this->assertSame('no', $stopped['sound'], 'set aside for someone else');
        $text = json_encode($seen, JSON_UNESCAPED_UNICODE);
        foreach (['Secreto', 'Oculto'] as $surname) {
            $this->assertStringNotContainsString($surname, $text);
        }
        foreach ($seen as $answer) {
            $this->assertArrayNotHasKey('reader', $answer);
            $this->assertArrayNotHasKey('readers', $answer);
            $this->assertArrayNotHasKey('loans', $answer);
        }
        // A name finds nobody here.
        $this->assertArrayNotHasKey('ticket', $this->read($w, 'Ana'));
        $this->assertArrayNotHasKey('ticket', $this->read($w, 'Ana Secreto'));
    }

    /**
     * Giving back: with or without a card, unless the library says not to; a book just taken and read again stays out.
     */
    public function test_giving_back(): void {
        global $DB;
        $w = $this->world();
        $card = $this->read($w, $w->ana->cardcode);
        $this->read($w, $w->faro->barcode, $card['ticket']);
        $this->assertSame('look', $this->read($w, $w->faro->barcode, $card['ticket'])['sound'], 'read twice in a row');
        $this->assertSame(1, $DB->count_records_select(self::T . 'loan', 'timereturned IS NULL'));

        // A while later: read again right away with no card, it would only say it is hers.
        $w->now += selfloan::DOUBLE_ALONE;
        selfloan::save_settings($w->library, ['stages' => [], 'returns' => false]);
        $this->assertSame('no', $this->read($w, $w->faro->barcode)['sound']);
        $this->assertSame(1, $DB->count_records_select(self::T . 'loan', 'timereturned IS NULL'));

        selfloan::save_settings($w->library, ['stages' => [], 'returns' => true]);
        $back = $this->read($w, $w->faro->barcode);
        $this->assertSame('ok', $back['sound']);
        $this->assertSame('idle', $back['state']);
        $loan = $DB->get_record(self::T . 'loan', ['itemid' => $w->faro->id], '*', MUST_EXIST);
        $this->assertSame((int) $w->post->id, (int) $loan->returnpostid);
        $this->assertNull($loan->returnstaffid);
    }

    /**
     * What it cannot decide goes to the desk: the limit, a late book, material, another library's copy.
     */
    public function test_what_goes_to_the_desk(): void {
        global $DB;
        $w = $this->world();
        $DB->insert_record(self::T . 'rule', ['libraryid' => $w->library->id, 'readertype' => '*', 'itemtype' => '*',
            'loandays' => 14, 'maxloans' => 1, 'renewals' => 1, 'renewdays' => 7, 'timecreated' => $w->now,
            'timemodified' => $w->now]);
        $card = $this->read($w, $w->ana->cardcode);
        $this->assertSame('ok', $this->read($w, $w->faro->barcode, $card['ticket'])['sound']);
        $over = $this->read($w, $w->isla->barcode, $card['ticket']);
        $this->assertSame('no', $over['sound'], 'over the limit, and nobody can force it');
        $this->assertSame(1, $DB->count_records(self::T . 'loan'));
        $DB->delete_records(self::T . 'rule');

        $other = $w->gen->create_library(['entityid' => $w->library->entityid]);
        $record = $w->gen->create_record(['entityid' => $w->library->entityid, 'title' => 'De otra']);
        $far = $w->gen->create_item(['libraryid' => $other->id, 'recordid' => $record->id, 'barcode' => '200000X']);
        $answer = $this->read($w, $far->barcode, $card['ticket']);
        $this->assertSame('no', $answer['sound']);
        $this->assertStringContainsString('De otra', $answer['text']);

        $tablet = $w->gen->create_record(['entityid' => $w->library->entityid, 'title' => 'Tableta', 'doctype' => 'device']);
        $unit = $w->gen->create_item(['libraryid' => $w->library->id, 'recordid' => $tablet->id, 'barcode' => '300000V']);
        $this->assertSame('no', $this->read($w, $unit->barcode, $card['ticket'])['sound']);
        $this->assertSame(1, $DB->count_records(self::T . 'loan'));
    }

    /**
     * Stages: the children of the stages chosen, and adults always.
     */
    public function test_stages(): void {
        $w = $this->world();
        $year = years::around((int) $w->library->entityid, $w->now);
        $w->gen->create_member(['readerid' => $w->ana->id, 'yearid' => $year->id, 'groupname' => '3 A', 'stage' => 'Primaria']);
        $w->gen->create_member(['readerid' => $w->leo->id, 'yearid' => $year->id, 'groupname' => '1 A', 'stage' => 'Infantil']);
        $teacher = $w->gen->create_reader(['entityid' => $w->library->entityid, 'cardcode' => '10002K', 'firstname' => 'Eva',
            'readertype' => 'teacher']);
        selfloan::save_settings($w->library, ['stages' => ['Primaria'], 'returns' => true]);
        $this->assertArrayHasKey('ticket', $this->read($w, $w->ana->cardcode));
        $this->assertArrayNotHasKey('ticket', $this->read($w, $w->leo->cardcode));
        $this->assertArrayHasKey('ticket', $this->read($w, $teacher->cardcode));
    }

    /**
     * Reading every number in turn stops telling names: a queue does not read twenty cards in a minute.
     */
    public function test_trying_every_number(): void {
        $w = $this->world();
        for ($i = 0; $i < 20; $i++) {
            $this->assertArrayHasKey('ticket', $this->read($w, $w->ana->cardcode), 'try ' . $i);
        }
        $answer = $this->read($w, $w->leo->cardcode);
        $this->assertArrayNotHasKey('name', $answer);
        $this->assertStringNotContainsString('Leo', json_encode($answer));
        $w->now += 61;
        $this->assertSame('Leo', $this->read($w, $w->leo->cardcode)['name']);
        // Nor every book: one that is neither lent nor given back counts too, and waits with the rest (seg-anon 02); a
        // minute later, it is read again.
        for ($i = 0; $i < 25; $i++) {
            $this->read($w, 'nothing' . $i);
        }
        $this->assertSame('no', $this->read($w, $w->faro->barcode)['sound']);
        $w->now += 61;
        $this->assertSame('look', $this->read($w, $w->faro->barcode)['sound']);
    }

    /**
     * Whoever runs the library sees what was done and undoes it; a book that has changed hands stays as it is.
     */
    public function test_going_over_it(): void {
        global $DB;
        $w = $this->world();
        $card = $this->read($w, $w->ana->cardcode);
        $this->read($w, $w->faro->barcode, $card['ticket']);
        $this->read($w, $w->isla->barcode, $card['ticket']);
        $w->now += selfloan::DOUBLE_ALONE;
        $this->read($w, $w->isla->barcode);
        $rows = selfloan::recent($w->library, $w->now);
        $this->assertCount(3, $rows);
        $this->assertSame('returned', $rows[0]['what']);
        $this->assertSame('Ana Secreto', $rows[0]['reader']);
        $this->assertSame('Sala', $rows[0]['post']);

        $faro = (int) $DB->get_field(self::T . 'loan', 'id', ['itemid' => $w->faro->id]);
        $this->assertTrue(selfloan::undo($w->library, $faro, 'lent', (int) get_admin()->id));
        $this->assertSame('available', $DB->get_field(self::T . 'item', 'status', ['id' => $w->faro->id]));
        $this->assertFalse($DB->record_exists(self::T . 'loan', ['id' => $faro]));

        $isla = (int) $DB->get_field(self::T . 'loan', 'id', ['itemid' => $w->isla->id]);
        $leo = $this->read($w, $w->leo->cardcode);
        $this->read($w, $w->isla->barcode, $leo['ticket']);
        $this->assertFalse(selfloan::undo($w->library, $isla, 'returned', (int) get_admin()->id), 'Leo has it now');
        (new desk($w->library, (int) get_admin()->id, $w->now))->scan($w->isla->barcode);
        $this->assertFalse(selfloan::undo($w->library, $isla, 'returned', (int) get_admin()->id), 'and it went to Leo after her');

        // The mapa: back at the post, and undone while nothing else has happened to it; not if it was lost meanwhile.
        $this->read($w, $w->mapa->barcode, $this->read($w, $w->ana->cardcode)['ticket']);
        $w->now += selfloan::DOUBLE_ALONE;
        $this->read($w, $w->mapa->barcode);
        $mapa = (int) $DB->get_field(self::T . 'loan', 'id', ['itemid' => $w->mapa->id]);
        $DB->set_field(self::T . 'item', 'status', 'lost', ['id' => $w->mapa->id]);
        $this->assertFalse(selfloan::undo($w->library, $mapa, 'returned', (int) get_admin()->id));
        $DB->set_field(self::T . 'item', 'status', 'available', ['id' => $w->mapa->id]);
        $this->assertTrue(selfloan::undo($w->library, $mapa, 'returned', (int) get_admin()->id));
        $this->assertNull($DB->get_field(self::T . 'loan', 'timereturned', ['id' => $mapa]));
        $this->assertSame('onloan', $DB->get_field(self::T . 'item', 'status', ['id' => $w->mapa->id]));

        // Only in this library.
        $other = $w->gen->create_library();
        $this->assertFalse(selfloan::undo($other, $isla, 'lent', (int) get_admin()->id));
    }

    /**
     * With its address, someone could give back the whole library: held back by the minute, stopped by the hour until the
     * staff look, and all of it can be undone, however many it was.
     */
    public function test_an_address_that_got_out(): void {
        global $DB;
        $w = $this->world();
        $year = years::around((int) $w->library->entityid, $w->now);
        $lend = function (int $i, int $ago) use ($w, $DB, $year): \stdClass {
            $record = $w->gen->create_record(['entityid' => $w->library->entityid, 'title' => 'Tanda ' . $i]);
            $item = $w->gen->create_item(['libraryid' => $w->library->id, 'recordid' => $record->id,
                'barcode' => 'T' . $i, 'status' => $ago ? 'available' : 'onloan']);
            $DB->insert_record(self::T . 'loan', ['libraryid' => $w->library->id, 'itemid' => $item->id, 'readerid' => $w->leo->id,
                'yearid' => $year->id, 'timeloaned' => $w->now - DAYSECS, 'timedue' => $w->now + DAYSECS, 'renewals' => 0,
                'timereturned' => $ago ? $w->now - $ago : null, 'returnpostid' => $ago ? $w->post->id : null,
                'timecreated' => $w->now - DAYSECS, 'timemodified' => $w->now]);
            return $item;
        };
        // Thirty in the last minute: the next one waits, and does not go back.
        for ($i = 0; $i < 30; $i++) {
            $lend($i, 30);
        }
        $next = $lend(1000, 0);
        $this->assertSame('no', $this->read($w, $next->barcode)['sound']);
        $this->assertNull($DB->get_field(self::T . 'loan', 'timereturned', ['itemid' => $next->id]));
        $this->assertSame(0, selfloan::stopped($w->library));
        // Three hundred in the hour: it stops, for everything, until the staff open it.
        for ($i = 100; $i < 370; $i++) {
            $lend($i, 1800);
        }
        $this->assertSame('no', $this->read($w, $next->barcode)['sound']);
        $this->assertSame(1, selfloan::stopped($w->library));
        $this->assertArrayNotHasKey('ticket', $this->read($w, $w->ana->cardcode));
        selfloan::reopen($w->library, (int) $w->post->id);
        $this->assertSame(0, selfloan::stopped($w->library));

        // All of it undone, not only what the list shows.
        $this->assertCount(100, selfloan::recent($w->library, $w->now));
        $this->assertSame(300, selfloan::undo_since($w->library, (int) $w->post->id, $w->now - HOURSECS, (int) get_admin()->id));
        $this->assertSame(0, $DB->count_records_select(self::T . 'loan', 'returnpostid IS NOT NULL'));
        $this->assertSame(0, selfloan::undo_since($w->gen->create_library(), (int) $w->post->id, 0, (int) get_admin()->id));

        // Cards, by the hour: slower than the minute's limit, it stops too.
        $DB->update_record(self::T . 'selfpost', (object) ['id' => $w->post->id, 'hourtries' => 200, 'timehour' => $w->now]);
        $w->now += 61;
        $this->assertArrayNotHasKey('name', $this->read($w, $w->leo->cardcode));
        $this->assertSame(1, selfloan::stopped($w->library));
    }

    /**
     * A card that was refused ends the visit on screen: it is someone else, and their books must not go on the card before.
     * What it says is the same as for a code nobody knows.
     */
    public function test_a_refused_card_ends_the_visit(): void {
        $w = $this->world();
        $year = years::around((int) $w->library->entityid, $w->now);
        $w->gen->create_member(['readerid' => $w->ana->id, 'yearid' => $year->id, 'groupname' => '3 A', 'stage' => 'Primaria']);
        $w->gen->create_member(['readerid' => $w->leo->id, 'yearid' => $year->id, 'groupname' => '1 A', 'stage' => 'Infantil']);
        selfloan::save_settings($w->library, ['stages' => ['Primaria'], 'returns' => true]);
        $ana = $this->read($w, $w->ana->cardcode);
        $refused = $this->read($w, $w->leo->cardcode, $ana['ticket']);
        $this->assertSame('idle', $refused['state']);
        $this->assertArrayNotHasKey('ticket', $refused);
        $this->assertSame($this->read($w, 'NOEXISTE')['text'], $refused['text']);
    }

    /**
     * Nothing about a loan or a limit is said aloud on the screen, whoever's number was typed.
     */
    public function test_it_says_nothing_of_a_limit(): void {
        global $DB;
        $w = $this->world();
        $DB->insert_record(self::T . 'rule', ['libraryid' => $w->library->id, 'readertype' => '*', 'itemtype' => '*',
            'loandays' => 14, 'maxloans' => 1, 'renewals' => 1, 'renewdays' => 7, 'timecreated' => $w->now,
            'timemodified' => $w->now]);
        $card = $this->read($w, $w->ana->cardcode);
        $this->read($w, $w->faro->barcode, $card['ticket']);
        $limit = $this->read($w, $w->isla->barcode, $card['ticket'])['text'];
        $DB->delete_records(self::T . 'rule');
        $DB->set_field(self::T . 'loan', 'timedue', $w->now - DAYSECS, ['itemid' => $w->faro->id]);
        $late = $this->read($w, $w->isla->barcode, $card['ticket'])['text'];
        $this->assertSame(str_replace('faro', 'isla', $limit), $late);
        $this->assertSame(get_string('selfstoplate', 'local_oksigeniaayvurenda', 'Libro isla'), $late);
    }

    /**
     * Material does not go back by itself, nor a copy on reserve out, nor one marked out with no loan.
     */
    public function test_more_for_the_desk(): void {
        global $DB;
        $w = $this->world();
        $tablet = $w->gen->create_record(['entityid' => $w->library->entityid, 'title' => 'Tableta', 'doctype' => 'device']);
        $unit = $w->gen->create_item(['libraryid' => $w->library->id, 'recordid' => $tablet->id, 'barcode' => '300000V']);
        $year = years::around((int) $w->library->entityid, $w->now);
        $DB->set_field(self::T . 'item', 'status', 'onloan', ['id' => $unit->id]);
        $DB->insert_record(self::T . 'loan', ['libraryid' => $w->library->id, 'itemid' => $unit->id, 'readerid' => $w->leo->id,
            'yearid' => $year->id, 'timeloaned' => $w->now - DAYSECS, 'timedue' => $w->now + DAYSECS, 'renewals' => 0,
            'timecreated' => $w->now, 'timemodified' => $w->now]);
        $this->assertSame('no', $this->read($w, $unit->barcode)['sound']);
        $this->assertNull($DB->get_field(self::T . 'loan', 'timereturned', ['itemid' => $unit->id]));

        $DB->set_field(self::T . 'item', 'status', 'onloan', ['id' => $w->mapa->id]);
        $this->assertSame('no', $this->read($w, $w->mapa->barcode)['sound'], 'out, with no loan');
        $this->assertSame('onloan', $DB->get_field(self::T . 'item', 'status', ['id' => $w->mapa->id]));

        $DB->insert_record(self::T . 'reserve', ['libraryid' => $w->library->id, 'itemid' => $w->isla->id,
            'courseid' => SITEID, 'days' => 0, 'timeend' => $w->now + DAYSECS, 'timecreated' => $w->now]);
        $card = $this->read($w, $w->ana->cardcode);
        $this->assertSame('no', $this->read($w, $w->isla->barcode, $card['ticket'])['sound'], 'on reserve for a course');
        $this->assertSame('available', $DB->get_field(self::T . 'item', 'status', ['id' => $w->isla->id]));

        // Only teachers, staff and family are free of the stages: a class card or a type an import kept is not.
        $card = $w->gen->create_reader(['entityid' => $w->library->entityid, 'cardcode' => '10009Z', 'firstname' => 'Aula',
            'readertype' => 'classroom']);
        selfloan::save_settings($w->library, ['stages' => ['Primaria'], 'returns' => true]);
        $this->assertArrayNotHasKey('ticket', $this->read($w, $card->cardcode));
    }

    /**
     * A library that goes takes its posts with it.
     */
    public function test_a_library_that_goes(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $other = $gen->create_library(['entityid' => $library->entityid]);
        selfloan::create($other, 'Sala', (int) get_admin()->id);
        $this->assertTrue(libraries::delete_library((int) $other->id));
        $this->assertSame(0, $DB->count_records(self::T . 'selfpost'));
    }

    /**
     * A card that was lost: the old code stops working at once, and what the reader had stays.
     */
    public function test_a_lost_card(): void {
        global $DB;
        $w = $this->world();
        $card = $this->read($w, $w->ana->cardcode);
        $this->read($w, $w->faro->barcode, $card['ticket']);
        $old = $w->ana->cardcode;
        $code = profile::new_card($w->library, (int) $w->ana->id);
        $this->assertNotSame($old, $code);
        $this->assertArrayNotHasKey('ticket', $this->read($w, $old));
        $this->assertSame('Ana', $this->read($w, $code)['name']);
        $this->assertSame((int) $w->ana->id, (int) $DB->get_field(self::T . 'loan', 'readerid', ['itemid' => $w->faro->id]));
        $other = $w->gen->create_library();
        $this->assertNull(profile::new_card($other, (int) $w->ana->id), 'only in its own centre');
    }

    /**
     * A textbook bank has none, and a centre that goes takes its posts.
     */
    public function test_a_bank_and_a_centre_that_goes(): void {
        global $DB;
        $w = $this->world();
        $this->assertTrue(selfloan::available($w->library));
        $bank = $w->gen->create_library(['entityid' => $w->library->entityid, 'kind' => bank::KIND]);
        $this->assertFalse(selfloan::available($bank));
        removal::remove((int) $w->library->entityid);
        $this->assertSame(0, $DB->count_records(self::T . 'selfpost'));
    }
}
