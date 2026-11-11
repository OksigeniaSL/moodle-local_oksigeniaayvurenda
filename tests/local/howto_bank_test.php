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
 * «What do you want to do?», measured: a bank of 844 phrasings of lost people in Spanish (of Spain and of America), English,
 * Portuguese and French, written without seeing the search (tests/fixtures/howto/bank-*.jsonl), each with the task or
 * setting that answers it, or none (a book's title, a reader's code, something of Moodle's). A third of it (crc32 of the
 * phrase % 3 == 0) is held out: it is never looked at to tune the words, only counted, so that it says how the search
 * does with what nobody has seen.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\howto
 */
#[\PHPUnit\Framework\Attributes\CoversClass(howto::class)]
final class howto_bank_test extends \advanced_testcase {
    /** @var array[] What also answers a need: the task of a setting's page, or the staff's twin of a reader's task. */
    private const ALSO = ['loandays' => ['rules'], 'maxloans' => ['rules'], 'renewcount' => ['rules'],
        'renewfrom' => ['rules', 'renew'], 'classification' => ['settings', 'classify'], 'controlletter' => ['settings'],
        'numbering' => ['settings'], 'history' => ['settings'], 'reviewsign' => ['settings', 'reviews'],
        'cardlook' => ['settings', 'cards'], 'photos' => ['settings', 'profile'], 'entitykind' => ['settings'],
        'timezone' => ['settings'], 'sources' => ['settings', 'add'], 'features' => ['settings'],
        'challenges' => ['settings', 'myclass'], 'corners' => ['settings', 'libraries'], 'drive' => ['offsite', 'copies'],
        'z3950' => ['add', 'settings'], 'readerhold' => ['holds'], 'readerrenew' => ['renew'],
        'readercard' => ['profile', 'cards'], 'readerreview' => ['reviews'], 'readervoice' => ['voices'],
        'readerformat' => ['formats']];

    /** @var float[] How much of each part the first answer has to get right, at least. */
    private const AT_LEAST = ['tune' => 0.90, 'held' => 0.84];

    /**
     * The first answer is the right one for most of the bank, in both parts, in the four languages together.
     */
    public function test_bank(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();
        // The plugin brings its strings in the four languages; Moodle only reads them for a language it knows, so a
        // site without their packs (the CI) gets the least of each: its name.
        foreach (['es', 'pt_br', 'pt', 'fr'] as $lang) {
            if (!get_string_manager()->translation_exists($lang, false)) {
                make_writable_directory($CFG->dataroot . '/lang/' . $lang);
                file_put_contents($CFG->dataroot . '/lang/' . $lang . '/langconfig.php', "<?php\n\$string['thislanguage'] = '"
                    . $lang . "';\n\$string['thislanguageint'] = '" . $lang . "';\n\$string['parentlanguage'] = '';\n");
            }
        }
        get_string_manager()->reset_caches(true);
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        // What a reader asks («rate this book», «my loans»), a reader asks: they see the tasks of readers, not the staff's.
        $reader = $this->getDataGenerator()->create_user();
        $right = ['tune' => 0, 'held' => 0];
        $all = ['tune' => 0, 'held' => 0];
        $dir = $CFG->dirroot . '/local/oksigeniaayvurenda/tests/fixtures/howto';
        foreach (['es', 'en', 'pt', 'fr'] as $file) {
            foreach (file($dir . "/bank-$file.jsonl", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $row = json_decode($line, true);
                $lang = $file === 'pt' ? ($row['region'] === 'PT' ? 'pt' : 'pt_br') : $file;
                $need = $row['need'];
                $asker = str_starts_with($need, 'reader') && $need !== 'readers' && $need !== 'readerslist' ? $reader : get_admin();
                // Changing the user starts their session again, and the language with it: the user first.
                $this->setUser($asker);
                force_current_language($lang);
                $keys = array_column(howto::find($row['q'], $library, (int) $asker->id), 'key');
                $good = str_starts_with($need, 'none') ? !$keys
                    : in_array($keys[0] ?? '', array_merge([$need], self::ALSO[$need] ?? []), true);
                $part = crc32($row['q']) % 3 === 0 ? 'held' : 'tune';
                $all[$part]++;
                $right[$part] += $good ? 1 : 0;
            }
        }
        force_current_language('en');
        foreach ($right as $part => $ok) {
            $this->assertGreaterThanOrEqual(self::AT_LEAST[$part], $ok / $all[$part], $part . ': ' . $ok . '/' . $all[$part]);
        }
    }
}
