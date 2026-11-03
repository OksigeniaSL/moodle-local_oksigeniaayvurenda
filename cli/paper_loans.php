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

/**
 * Brings in loans written on paper while the desk could not be used.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_oksigeniaayvurenda\local\paper_loans;

[$options, $unrecognized] = cli_get_params(
    ['help' => false, 'library' => 0, 'file' => '', 'apply' => false, 'due' => ''],
    ['h' => 'help']
);

if ($unrecognized) {
    cli_error(get_string('cliunknowoption', 'admin', implode("\n  ", $unrecognized)), 2);
}

if ($options['help'] || !$options['library'] || $options['file'] === '') {
    echo "Brings in loans written on paper (or in a document) while the desk could not be used: each line, a loan, a
return or a renewal, done as the desk would have done it on its day. Without --apply it only says what it would do,
line by line: who and what it found, sure, close (a slip of a letter) or doubtful, with the candidates. With --apply it
does the sure and close lines, in date order, and lists the rest; running it again does not repeat what is done. A
doubtful line is made sure by writing in the list the reader's card code and the record's number (or a copy code).

The list, saved as CSV (comma, semicolon or tab), with a first row naming its columns:
  lector / reader      card code, account, or name («Surname, Name» or «Name Surname»)
  grupo / group        optional, to choose between namesakes
  titulo / title       or isbn, ficha / record (its number), ejemplar / copy (its code)
  accion / action      saca / loan, devuelve / return, renueva / renew (empty: a loan)
  fecha / date         21/10/2026, 21-10-26 or 2026-10-21

A copy nobody wrote down is the first free one of its record; when another copy of that record comes back, the desk
takes it as the one lent (with the reader open at the desk). A return of a loan that is not here (it came before) only
says so: the book is on the shelf. A renewal of a loan that is not here lends it from that day.

Options:
  --library=ID     The library
  --file=PATH      The list
  --apply          Do it
  --due=DATE       Every loan out of the list due on that day, instead of the rules': after days without the desk
  -h, --help       This help
";
    exit(0);
}

$library = $DB->get_record('local_oksigeniaayvurenda_library', ['id' => (int) $options['library']]);
if (!$library) {
    cli_error('There is no library ' . $options['library'] . '.');
}
if (!is_readable($options['file'])) {
    cli_error('The file cannot be read: ' . $options['file']);
}
$loans = new paper_loans($library, (int) get_admin()->id);
$due = null;
if ($options['due'] !== '') {
    $due = $loans->day_end((string) $options['due']);
    if ($due === null) {
        cli_error('--due is a date: 15/11/2026 or 2026-11-15.');
    }
}
$plan = $loans->plan($loans->lines((string) file_get_contents($options['file'])));
$when = fn($line) => $line['date'] ? userdate($line['date'], '%Y-%m-%d') : '?' . $line['rawdate'];
$who = function (array $line): string {
    $text = '[' . $line['readerhow'] . '] ' . $line['reader'];
    foreach ($line['readers'] as $c) {
        $text .= "\n        ? " . $c['card'] . ' ' . $c['name'] . ($c['group'] !== '' ? ' (' . $c['group'] . ')' : '');
    }
    return $text;
};
$what = function (array $line): string {
    $text = '[' . $line['recordhow'] . '] ' . ($line['title'] ?: $line['isbn'] ?: $line['record'] ?: $line['copy']);
    if ($line['recordid'] && $line['recordhow'] !== 'sure') {
        $text .= ' = #' . $line['recordid'];
    }
    foreach ($line['records'] as $c) {
        $text .= "\n        ? #" . $c['id'] . ' ' . $c['title'];
    }
    return $text;
};

if (!$options['apply']) {
    cli_heading('What it would do (nothing done yet)');
    foreach ($plan as $line) {
        cli_writeln(sprintf('%4d %s %-6s', $line['line'], $when($line), $line['action'] ?: '?') .
            ($line['problem'] !== '' ? '  NOT DONE: ' . $line['problem'] : ''));
        cli_writeln('     ' . $who($line));
        cli_writeln('     ' . $what($line));
    }
    $ready = count(array_filter($plan, fn($line) => $line['problem'] === ''));
    cli_writeln("\n" . $ready . ' of ' . count($plan) . ' lines ready. Run again with --apply to do them.');
    exit(0);
}

$counts = [];
cli_heading('Done');
foreach ($loans->apply($plan, $due) as $line) {
    $counts[$line['done']] = ($counts[$line['done']] ?? 0) + 1;
    if ($line['done'] !== 'skipped') {
        cli_writeln(sprintf('%4d %s %-6s %s', $line['line'], $when($line), $line['action'], $line['done']) .
            ($line['why'] !== '' ? ': ' . $line['why'] : ''));
    }
}
$left = array_filter($plan, fn($line) => $line['problem'] !== '');
if ($left) {
    cli_heading('Left for the list to say better');
    foreach ($left as $line) {
        cli_writeln(sprintf('%4d %s %-6s  %s', $line['line'], $when($line), $line['action'] ?: '?', $line['problem']));
        cli_writeln('     ' . $who($line));
        cli_writeln('     ' . $what($line));
    }
}
cli_writeln("\n" . json_encode($counts));
