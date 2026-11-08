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
namespace local_oksigeniaayvurenda\local;

/**
 * A machine that speaks SIP2 is one more post: the same rules, the same limits, and nothing said that a post would not say.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(sip::class)]
final class sip_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * A library with a post, two readers and three books.
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
        $w->entityid = (int) $w->library->entityid;
        $w->now = time();
        $w->ana = $gen->create_reader(['entityid' => $w->entityid, 'cardcode' => '10000H', 'firstname' => 'Ana',
            'lastname' => 'Montes']);
        $w->leo = $gen->create_reader(['entityid' => $w->entityid, 'cardcode' => '10001L', 'firstname' => 'Leo',
            'lastname' => 'Vera']);
        foreach (['B1', 'B2', 'B3'] as $code) {
            $record = $gen->create_record(['entityid' => $w->entityid, 'title' => 'Libro ' . $code]);
            $gen->create_item(['libraryid' => $w->library->id, 'recordid' => $record->id, 'barcode' => $code]);
        }
        $address = selfloan::create($w->library, 'Máquina', (int) get_admin()->id, null, true);
        $w->key = selfloan::key_of($address);
        $w->post = selfloan::by_key($w->key, true)['post'];
        $w->due = fn(string $code) => (int) $DB->get_field_sql(
            'SELECT l.timedue FROM {' . self::T . 'loan} l JOIN {' . self::T . 'item} i ON i.id = l.itemid
              WHERE i.barcode = ? AND l.timereturned IS NULL',
            [$code]
        );
        $w->ask = fn(string $op, array $values = [], int $at = 0) => sip::answer(
            $DB->get_record(self::T . 'selfpost', ['id' => $w->post->id]),
            $w->library,
            $op,
            $values,
            $at ?: $w->now
        );
        return $w;
    }

    /**
     * The address of a machine does not open a screen, nor the other way round: their limits differ.
     */
    public function test_kinds_of_post(): void {
        $w = $this->world();
        $this->assertNull(selfloan::by_key($w->key));
        $address = selfloan::create($w->library, 'Sala', (int) get_admin()->id);
        $this->assertStringContainsString('self.php#key=', $address);
        $this->assertNotNull(selfloan::by_key(selfloan::key_of($address)));
        $this->assertNull(selfloan::by_key(selfloan::key_of($address), true));
        // An address of before, with the key in its query, still says it.
        $this->assertSame('abc123', selfloan::key_of('https://x.example/local/oksigeniaayvurenda/self.php?key=abc123'));
    }

    /**
     * Status: what the machine can do here.
     */
    public function test_status(): void {
        $w = $this->world();
        $said = ($w->ask)('status');
        $this->assertTrue($said['online']);
        $this->assertTrue($said['checkout']);
        $this->assertTrue($said['checkin']);
        $this->assertFalse($said['renew']);
        $this->assertSame((string) $w->library->slug, $said['institution']);
        selfloan::save_settings($w->library, ['stages' => [], 'returns' => false]);
        $this->assertFalse(($w->ask)('status')['checkin']);
    }

    /**
     * A card: the first name and no more, whatever they have out or owe.
     */
    public function test_patron(): void {
        $w = $this->world();
        $this->assertFalse(($w->ask)('patron', ['patron' => 'NADIE'])['valid']);
        $said = ($w->ask)('patron', ['patron' => '10000H']);
        $this->assertTrue($said['valid']);
        $this->assertSame('Ana', $said['name']);
        $this->assertStringNotContainsString('Montes', json_encode($said));

        $lent = ($w->ask)('checkout', ['patron' => '10000H', 'item' => 'B1']);
        $this->assertTrue($lent['ok']);
        $late = ($w->ask)('patron', ['patron' => '10000H'], ($w->due)('B1') + 3 * DAYSECS);
        $this->assertSame(['valid' => true, 'name' => 'Ana'], $late, 'nothing about what they have, or that it is late');
    }

    /**
     * A book goes out and comes back, by the post's rules, and is on the post's record.
     */
    public function test_out_and_back(): void {
        global $DB;
        $w = $this->world();
        $lent = ($w->ask)('checkout', ['patron' => '10000H', 'item' => 'B1']);
        $this->assertTrue($lent['ok']);
        $this->assertSame('Libro B1', $lent['title']);
        $loan = $DB->get_record(self::T . 'loan', ['readerid' => $w->ana->id]);
        $this->assertSame((int) $w->post->id, (int) $loan->postid);
        $this->assertMatchesRegularExpression('/^\d{8}    \d{6}$/', $lent['due']);

        // Read again by the same reader, the same loan; by another, no.
        $this->assertTrue(($w->ask)('checkout', ['patron' => '10000H', 'item' => 'B1'])['ok']);
        $this->assertSame(1, $DB->count_records(self::T . 'loan', ['readerid' => $w->ana->id]));
        $other = ($w->ask)('checkout', ['patron' => '10001L', 'item' => 'B1']);
        $this->assertFalse($other['ok']);
        $this->assertStringNotContainsString('Ana', json_encode($other));

        // What a book says: out, and when, not who.
        $item = ($w->ask)('item', ['item' => 'B1']);
        $this->assertSame('04', $item['circulation']);
        $this->assertSame($lent['due'], $item['due']);
        $this->assertStringNotContainsString('Ana', json_encode($item));
        $this->assertSame('03', ($w->ask)('item', ['item' => 'B2'])['circulation']);
        // With nobody's card read at the machine, only whether it is in: not what it is nor when it is due.
        \cache::make('local_oksigeniaayvurenda', 'selfvisit')->purge();
        $bare = ($w->ask)('item', ['item' => 'B1']);
        $this->assertSame('04', $bare['circulation']);
        $this->assertSame('', $bare['title']);
        $this->assertSame('', $bare['due']);

        $back = ($w->ask)('checkin', ['item' => 'B1']);
        $this->assertTrue($back['ok']);
        $this->assertFalse($back['alert']);
        $this->assertSame((int) $w->post->id, (int) $DB->get_field(self::T . 'loan', 'returnpostid', ['id' => $loan->id]));
        $this->assertTrue(($w->ask)('checkin', ['item' => 'B1'])['ok'], 'a pad reads twice: it did come back');
        $this->assertFalse(($w->ask)('checkin', ['item' => 'B1'], $w->now + 2 * HOURSECS)['ok'], 'later, it is simply in');
        $this->assertFalse(($w->ask)('checkin', ['item' => 'B2'])['ok'], 'one that was never out');
    }

    /**
     * Checking in where the library does not let its posts; and with someone waiting.
     */
    public function test_checkin_rules_and_alert(): void {
        $w = $this->world();
        ($w->ask)('checkout', ['patron' => '10000H', 'item' => 'B1']);
        holds::save_settings($w->entityid, ['online' => true]);
        $record = (int) \local_oksigeniaayvurenda\local\desk::by_code('item', 'barcode', $w->entityid, 'B1')->recordid;
        $this->assertTrue(holds::add($w->library, $record, (int) $w->leo->id, null, $w->now)['ok']);
        selfloan::save_settings($w->library, ['stages' => [], 'returns' => false]);
        $this->assertFalse(($w->ask)('checkin', ['item' => 'B1'])['ok']);
        selfloan::save_settings($w->library, ['stages' => [], 'returns' => true]);
        $back = ($w->ask)('checkin', ['item' => 'B1']);
        $this->assertTrue($back['ok']);
        $this->assertTrue($back['alert'], 'someone waits for it');
        $this->assertStringNotContainsString('Leo', json_encode($back));
    }

    /**
     * Stopped by what a late book left: the machine does not lend, and says only to go to the desk.
     */
    public function test_a_stop_stays_neutral(): void {
        $w = $this->world();
        sanctions::save_settings($w->entityid, ['mode' => 'fine', 'cents' => 100, 'block' => 100]);
        $lent = ($w->ask)('checkout', ['patron' => '10000H', 'item' => 'B1']);
        $at = ($w->due)('B1') + 5 * DAYSECS;
        $this->assertTrue(($w->ask)('checkin', ['item' => 'B1'], $at)['ok']);
        $refused = ($w->ask)('checkout', ['patron' => '10000H', 'item' => 'B2'], $at);
        $this->assertFalse($refused['ok']);
        $this->assertStringNotContainsString('5', $refused['message'] . '', 'no sum');
        $this->assertSame(get_string('selfstopowes', 'local_oksigeniaayvurenda', 'Libro B2'), $refused['message']);
        $this->assertSame(['valid' => true, 'name' => 'Ana'], ($w->ask)('patron', ['patron' => '10000H'], $at));
    }

    /**
     * Renewing: only where the library lets its readers from home, and only their own book.
     */
    public function test_renew(): void {
        global $DB;
        $w = $this->world();
        $lent = ($w->ask)('checkout', ['patron' => '10000H', 'item' => 'B1']);
        $this->assertFalse(($w->ask)('renew', ['patron' => '10000H', 'item' => 'B1'])['ok'], 'not allowed here');
        holds::save_settings($w->entityid, ['renewonline' => true]);
        $this->assertFalse(($w->ask)('renew', ['patron' => '10001L', 'item' => 'B1'])['ok'], 'not theirs');
        $renewed = ($w->ask)('renew', ['patron' => '10000H', 'item' => 'B1']);
        $this->assertTrue($renewed['ok']);
        $this->assertGreaterThan($lent['due'], $renewed['due']);

        // Where a late book costs something, giving it more time at a machine would leave nothing.
        sanctions::save_settings($w->entityid, ['mode' => 'days']);
        $at = ($w->due)('B1') + DAYSECS;
        $this->assertFalse(($w->ask)('renew', ['patron' => '10000H', 'item' => 'B1'], $at)['ok']);
        $this->assertSame(($w->due)('B1'), (int) $DB->get_field(self::T . 'loan', 'timedue', ['readerid' => $w->ana->id]));
    }

    /**
     * Reading every number in turn: held back, and then the post stops, like any other.
     */
    public function test_limits(): void {
        global $DB;
        $w = $this->world();
        $tries = 0;
        do {
            $said = ($w->ask)('patron', ['patron' => '1000' . $tries . 'X']);
            $tries++;
            $stopped = !empty($DB->get_field(self::T . 'selfpost', 'timestopped', ['id' => $w->post->id]));
        } while (!$stopped && $tries < 1000);
        $this->assertTrue($stopped);
        $this->assertFalse(($w->ask)('patron', ['patron' => '10000H'])['valid'], 'a stopped post does not say');
        $this->assertFalse(($w->ask)('status')['online']);
        $this->assertFalse(($w->ask)('checkout', ['patron' => '10000H', 'item' => 'B1'])['ok']);
    }

    /**
     * Who is at the machine counts once, however many books; many different cards, or bad ones, stop it.
     */
    public function test_a_busy_machine(): void {
        global $DB;
        $w = $this->world();
        ($w->ask)('checkout', ['patron' => '10000H', 'item' => 'B1']);
        for ($i = 0; $i < selfloan::CARDSHOUR + 5; $i++) {
            $this->assertTrue(($w->ask)('checkout', ['patron' => '10000H', 'item' => 'B1'])['ok']);
        }
        for ($i = 0; $i < selfloan::CARDSHOUR + 5; $i++) {
            $this->assertTrue(($w->ask)('patron', ['patron' => '10000H'])['valid']);
        }
        $this->assertEmpty($DB->get_field(self::T . 'selfpost', 'timestopped', ['id' => $w->post->id]));
        for ($i = 0; $i < selfloan::CARDSHOUR + 5; $i++) {
            ($w->ask)('checkout', ['patron' => 'NADIE' . $i, 'item' => 'B2']);
        }
        $this->assertNotEmpty($DB->get_field(self::T . 'selfpost', 'timestopped', ['id' => $w->post->id]));
    }

    /**
     * Another library's or centre's: nothing.
     */
    public function test_apart(): void {
        $w = $this->world();
        $other = $w->gen->create_library();
        $record = $w->gen->create_record(['entityid' => (int) $other->entityid, 'title' => 'De otro centro']);
        $w->gen->create_item(['libraryid' => $other->id, 'recordid' => $record->id, 'barcode' => 'OTRO']);
        $w->gen->create_reader(['entityid' => (int) $other->entityid, 'cardcode' => '50000Z', 'firstname' => 'Otra',
            'lastname' => 'Persona']);
        $this->assertFalse(($w->ask)('patron', ['patron' => '50000Z'])['valid']);
        $this->assertSame('', ($w->ask)('item', ['item' => 'OTRO'])['title']);
        $this->assertFalse(($w->ask)('checkout', ['patron' => '10000H', 'item' => 'OTRO'])['ok']);
        $this->assertFalse(($w->ask)('checkin', ['item' => 'OTRO'])['ok']);
    }

    /**
     * Material does not go through a machine, in any direction.
     */
    public function test_material(): void {
        global $DB;
        $w = $this->world();
        $record = $w->gen->create_record(['entityid' => $w->entityid, 'title' => 'Tableta', 'doctype' => 'device']);
        $w->gen->create_item(['libraryid' => $w->library->id, 'recordid' => $record->id, 'barcode' => 'D1']);
        $this->assertFalse(($w->ask)('checkout', ['patron' => '10000H', 'item' => 'D1'])['ok']);
        holds::save_settings($w->entityid, ['renewonline' => true]);
        $this->assertFalse(($w->ask)('renew', ['patron' => '10000H', 'item' => 'D1'])['ok']);
        $this->assertFalse(($w->ask)('checkin', ['item' => 'D1'])['ok']);
        $this->assertSame(0, $DB->count_records(self::T . 'loan', ['readerid' => $w->ana->id]));
    }
}
