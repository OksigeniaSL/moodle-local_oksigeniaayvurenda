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
 * Tests for loans written on paper while the desk could not be used.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\paper_loans
 * @covers     \local_oksigeniaayvurenda\local\desk
 */
#[\PHPUnit\Framework\Attributes\CoversClass(paper_loans::class)]
final class paper_loans_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * A library with a week's loans for students, some readers (two namesakes in different groups) and some books.
     *
     * @return \stdClass
     */
    private function make_world(): \stdClass {
        global $DB;
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $w = new \stdClass();
        $w->library = $gen->create_library();
        $entityid = $w->library->entityid;
        $DB->insert_record(self::T . 'rule', ['libraryid' => $w->library->id, 'readertype' => 'student', 'itemtype' => 'normal',
            'loandays' => 7, 'maxloans' => 3, 'renewals' => 1, 'renewdays' => 7, 'timecreated' => 1, 'timemodified' => 1]);
        $year = $gen->create_year(['entityid' => $entityid]);
        $reader = function (string $first, string $last, string $group) use ($gen, $entityid, $year) {
            $reader = $gen->create_reader(['entityid' => $entityid, 'firstname' => $first, 'lastname' => $last]);
            $gen->create_member(['readerid' => $reader->id, 'yearid' => $year->id, 'groupname' => $group]);
            return $reader;
        };
        $w->ana = $reader('Ana', 'Montes Prado', '3º A');
        $w->leo = $reader('Leo', 'Montes Prado', '3º A');
        $w->iker = $reader('Iker', 'Ruiz Sol', '3º B');
        $w->other = $reader('Iker', 'Ruiz Gil', '4º B');
        $book = function (string $title, array $codes, array $extra = []) use ($gen, $w, $entityid) {
            $record = $gen->create_record(['entityid' => $entityid, 'title' => $title, 'extra' => json_encode($extra),
                'searchtext' => \local_oksigeniaayvurenda\core\text::normalise($title)]);
            foreach ($codes as $code) {
                $gen->create_item(['libraryid' => $w->library->id, 'recordid' => $record->id, 'barcode' => $code]);
            }
            return $record;
        };
        $w->faro = $book('El faro dormido', ['100000L', '100001C']);
        $w->potter1 = $book('Harry Potter y la piedra filosofal', ['100002K'], ['seriesnumber' => '1']);
        $w->potter2 = $book('Harry Potter y la cámara secreta', ['100003E'], ['seriesnumber' => '2']);
        $w->sirenita = $book('La sirenita', ['100004T']);
        return $w;
    }

    /**
     * The day a line means, at midday in the centre's time zone, as the list writes it.
     *
     * @param \stdClass $library
     * @param string $day
     * @return int
     */
    private function noon(\stdClass $library, string $day): int {
        return (new \DateTimeImmutable($day . ' 12:00', zones::of($library)))->getTimestamp();
    }

    /**
     * Each line finds its reader and its book, sure, close or doubtful; the sure and close ones are done in date order
     * as the desk would have done them on their day, and a second run repeats nothing.
     */
    public function test_list(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_world();
        $csv = "lector;grupo;titulo;accion;fecha\n" .
            "\"Montes Prado, Ana\";;el faro dormido;saca;21/10/2026\n" .
            "\"Montes Prado, Laura\";;faro;saca;21/10/2026\n" .
            "\"Monte Prado, Leo\";;harry potter 2;saca;22/10/2026\n" .
            "\"Ruiz Sol, Iker\";3º B;la sirenita (Disney);saca;23/10/2026\n" .
            "\"Montes Prado, Ana\";;El faro dormido;devuelve;25/10/2026\n" .
            "\"Montes Prado, Ana\";;harry potter;devuelve;26/10/2026\n" .
            "\"Ruiz Gil, Iker\";;el faro dormido;devuelve;26/10/2026\n" .
            "\"Ruiz Sol, Iker\";;la sirenita;renueva;28/10/2026\n" .
            "\"Ruiz Gil, Iker\";;el faro;renueva;29/10/2026\n" .
            "\"Montes Prado, Ana\";;el faro dormido;saca;el martes\n";
        $loans = new paper_loans($w->library);
        $plan = $loans->plan($loans->lines($csv));
        $byline = array_column($plan, null, 'line');

        $this->assertSame(['sure', 'sure'], [$byline[2]['readerhow'], $byline[2]['recordhow']]);
        $this->assertSame('reader', $byline[3]['problem']);
        $this->assertSame(['close', 'close'], [$byline[4]['readerhow'], $byline[4]['recordhow']]);
        $this->assertSame((int) $w->potter2->id, $byline[4]['recordid']);
        $this->assertSame([(int) $w->iker->id, (int) $w->sirenita->id], [$byline[5]['readerid'], $byline[5]['recordid']]);
        $this->assertSame('record', $byline[7]['problem']);
        $this->assertCount(2, $byline[7]['records']);
        $this->assertSame('date', $byline[11]['problem']);

        $results = $loans->apply($plan);
        $done = array_column($results, 'done', 'line');
        $this->assertSame(
            ['lent', 'lent', 'lent', 'returned', 'notout', 'renewed', 'lentonrenewal'],
            [$done[2], $done[4], $done[5], $done[6], $done[8], $done[9], $done[10]]
        );

        $ana = $DB->get_record(self::T . 'loan', ['readerid' => $w->ana->id], '*', MUST_EXIST);
        $this->assertSame($this->noon($w->library, '2026-10-21'), (int) $ana->timeloaned);
        $this->assertSame($this->noon($w->library, '2026-10-25'), (int) $ana->timereturned);
        $leo = $DB->get_record(self::T . 'loan', ['readerid' => $w->leo->id], '*', MUST_EXIST);
        $this->assertNull($leo->timereturned);
        $this->assertSame(1, (int) $leo->copyguessed);
        $this->assertGreaterThan($this->noon($w->library, '2026-10-28'), (int) $leo->timedue);
        $iker = $DB->get_record(self::T . 'loan', ['readerid' => $w->iker->id], '*', MUST_EXIST);
        $this->assertSame(1, (int) $iker->renewals);
        $other = $DB->get_record(self::T . 'loan', ['readerid' => $w->other->id], '*', MUST_EXIST);
        $this->assertSame($this->noon($w->library, '2026-10-29'), (int) $other->timeloaned);
        $this->assertSame('100000L', $DB->get_field(self::T . 'item', 'barcode', ['id' => $other->itemid]));
        $this->assertSame('onloan', $DB->get_field(self::T . 'item', 'status', ['id' => $other->itemid]));

        // Again, with all due on one day: nothing is repeated, and what is out is due then.
        $due = $loans->day_end('15/11/2026');
        $again = array_count_values(array_column($loans->apply($loans->plan($loans->lines($csv)), $due), 'done'));
        ksort($again);
        $this->assertSame(['already' => 6, 'notout' => 1, 'skipped' => 3], $again);
        $this->assertSame(4, $DB->count_records(self::T . 'loan', ['libraryid' => $w->library->id]));
        $this->assertSame(3, $DB->count_records_select(
            self::T . 'loan',
            'libraryid = ? AND timereturned IS NULL AND timedue = ?',
            [$w->library->id, $due]
        ));
    }

    /**
     * A copy nobody wrote down: when another copy of its record comes back with the reader open, that one was it; when
     * someone else takes the one it took, it moves to another free copy; with no reader open, the desk says who has it.
     */
    public function test_desk_swaps(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_world();
        $loans = new paper_loans($w->library);
        $loans->apply($loans->plan($loans->lines("lector;titulo;fecha\nMontes Prado, Leo;El faro dormido;21/10/2026\n")));
        $loan = $DB->get_record(self::T . 'loan', ['readerid' => $w->leo->id], '*', MUST_EXIST);
        $a = $DB->get_record(self::T . 'item', ['barcode' => '100000L'], '*', MUST_EXIST);
        $b = $DB->get_record(self::T . 'item', ['barcode' => '100001C'], '*', MUST_EXIST);
        $this->assertSame((int) $a->id, (int) $loan->itemid);

        $desk = new desk($w->library, 0);
        // Without a reader, the other copy says who has one.
        $asked = $desk->scan('100001C');
        $this->assertSame('needreader', $asked['kind']);
        $this->assertStringContainsString('Leo', $asked['message']);

        // Ana takes the copy the list took: Leo's loan goes to the other one.
        $lent = $desk->scan('100000L', (int) $w->ana->id);
        $this->assertSame('lent', $lent['kind']);
        $moved = $DB->get_record(self::T . 'loan', ['id' => $loan->id], '*', MUST_EXIST);
        $this->assertSame((int) $b->id, (int) $moved->itemid);
        $this->assertNull($moved->timereturned);
        $this->assertSame(1, (int) $moved->copyguessed);
        $this->assertSame('onloan', $DB->get_field(self::T . 'item', 'status', ['id' => $b->id]));

        // Ana returns hers; Leo comes with the copy he really had, the one Ana just brought back.
        $desk->scan('100000L');
        $back = $desk->scan('100000L', (int) $w->leo->id);
        $this->assertSame('returned', $back['kind']);
        $closed = $DB->get_record(self::T . 'loan', ['id' => $loan->id], '*', MUST_EXIST);
        $this->assertSame((int) $a->id, (int) $closed->itemid);
        $this->assertNotNull($closed->timereturned);
        $this->assertSame(0, (int) $closed->copyguessed);
        $this->assertSame('available', $DB->get_field(self::T . 'item', 'status', ['id' => $a->id]));
        $this->assertSame('available', $DB->get_field(self::T . 'item', 'status', ['id' => $b->id]));
    }

    /**
     * At the desk, a book without a label by its title: with the reader open, those on the shelf to lend; without one,
     * those out to bring back; and in the reader's list, a touch brings one back.
     */
    public function test_desk_titles(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_world();
        $desk = new desk($w->library, 0);

        $books = $desk->scan('faro dormido', (int) $w->ana->id);
        $this->assertSame('choose', $books['kind']);
        $this->assertSame([[(int) $w->faro->id, 2]], array_map(fn($b) => [$b['recordid'], $b['free']], $books['books']));
        $this->assertSame('unknown', $desk->scan('ningun titulo asi', (int) $w->ana->id)['kind']);

        // Two copies: which one went is not known, and the loan says so; one copy, it is known.
        $lent = $desk->lend_record((int) $w->faro->id, (int) $w->ana->id);
        $this->assertSame('lent', $lent['kind']);
        $this->assertSame(1, (int) $DB->get_field(self::T . 'loan', 'copyguessed', ['id' => $lent['loanid']]));
        $one = $desk->lend_record((int) $w->sirenita->id, (int) $w->ana->id);
        $this->assertSame(0, (int) $DB->get_field(self::T . 'loan', 'copyguessed', ['id' => $one['loanid']]));
        $this->assertSame('stop', $desk->lend_record((int) $w->sirenita->id, (int) $w->leo->id)['kind']);

        // Without a reader, the title finds who has it, and a touch brings it back.
        $out = $desk->scan('sirenita');
        $this->assertSame('choose', $out['kind']);
        $this->assertSame([(int) $one['loanid']], array_column($out['out'], 'loanid'));
        $this->assertStringContainsString('Ana', $out['out'][0]['who']);
        $back = $desk->give_back_loan((int) $one['loanid']);
        $this->assertSame('returned', $back['kind']);
        $this->assertNotNull($DB->get_field(self::T . 'loan', 'timereturned', ['id' => $one['loanid']]));
        $this->assertSame('stop', $desk->give_back_loan((int) $one['loanid'])['kind']);

        // Without a reader, what is on the shelf too, to know it is there; nothing, says so.
        $shelf = $desk->scan('piedra filosofal');
        $this->assertSame('choose', $shelf['kind']);
        $this->assertSame([(int) $w->potter1->id], array_column($shelf['shelf'], 'recordid'));
        $this->assertStringContainsString('first open the reader', $shelf['message']);
        // One out and one on the shelf: both.
        $both = $desk->scan('faro dormido');
        $this->assertCount(1, $both['out']);
        $this->assertSame([(int) $w->faro->id], array_column($both['shelf'], 'recordid'));
        $none = $desk->scan('ningun titulo asi');
        $this->assertSame('unknown', $none['kind']);
        $this->assertStringContainsString('No reader and no book', $none['message']);

        // More than fit: it says how many more.
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        for ($i = 1; $i <= 10; $i++) {
            $record = $gen->create_record(['entityid' => $w->library->entityid, 'title' => 'Caperucita roja ' . $i,
                'searchtext' => 'caperucita roja ' . $i]);
            $gen->create_item(['libraryid' => $w->library->id, 'recordid' => $record->id]);
        }
        $many = $desk->scan('caperucita');
        $this->assertCount(8, $many['shelf']);
        $this->assertSame(2, $many['more']);

        // A reader's name still opens the reader.
        $this->assertSame('reader', $desk->scan('Ruiz Sol')['kind']);
    }

    /**
     * Readers with an account take their name from it; the same name once with an account and once without (from an old
     * program, not matched yet) is the one with the account.
     */
    public function test_accounts(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_world();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $marta = $this->getDataGenerator()->create_user(['firstname' => 'Marta', 'lastname' => 'Sol Ruiz']);
        $pablo = $this->getDataGenerator()->create_user(['firstname' => 'Pablo', 'lastname' => 'Gil Mar']);
        $withaccount = $gen->create_reader(['entityid' => $w->library->entityid, 'userid' => $marta->id,
            'firstname' => null, 'lastname' => null]);
        $gen->create_reader(['entityid' => $w->library->entityid, 'firstname' => 'Marta', 'lastname' => 'Sol Ruiz']);
        $onlyaccount = $gen->create_reader(['entityid' => $w->library->entityid, 'userid' => $pablo->id,
            'firstname' => '', 'lastname' => '']);
        $loans = new paper_loans($w->library);
        $plan = array_column($loans->plan($loans->lines("lector;titulo;fecha\n\"Sol Ruiz, Marta\";El faro dormido;21/10/2026\n" .
            "\"Gil Mar, Pablo\";La sirenita;22/10/2026\n")), null, 'line');
        $this->assertSame([(int) $withaccount->id, 'sure'], [$plan[2]['readerid'], $plan[2]['readerhow']]);
        $this->assertSame([(int) $onlyaccount->id, 'sure'], [$plan[3]['readerid'], $plan[3]['readerhow']]);
        $this->assertSame('', $plan[3]['problem']);
    }

    /**
     * A scan kept without a network and sent twice, its answer lost, is known; one at another moment is not.
     */
    public function test_passed_at(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $reader = $gen->create_reader(['entityid' => $library->entityid, 'cardcode' => 'C1']);
        $record = $gen->create_record(['entityid' => $library->entityid, 'title' => 'Libro']);
        $gen->create_item(['libraryid' => $library->id, 'recordid' => $record->id, 'barcode' => '77']);
        $at = time() - 600;
        $desk = new desk($library, 0, $at);
        $this->assertFalse($desk->passed_at('77'));
        $desk->scan('77', (int) $reader->id);
        $this->assertTrue($desk->passed_at('77'));
        $this->assertFalse((new desk($library, 0, $at + 1))->passed_at('77'));
    }
}
