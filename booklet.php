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
 * The reader's booklet (Jujol, 13 November), as a page of its own:
 * - the family's, from the sealed link they got by e-mail, without an account (b and s), and its cover for the chats
 *   (cover); the voice in it, sealed (voice and s);
 * - the staff's, from the reader's record (library, reader, and year or life);
 * - the tutor's, every sheet of a class to print at once (library, group, and life for the whole lives).
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// A sealed link opens no session of Moodle: the family has no account.
define('NO_MOODLE_COOKIES', isset($_GET['s']));

// phpcs:ignore moodle.Files.RequireLogin.Missing -- a sealed link is for the family; the rest checks below.
require(__DIR__ . '/../../config.php');

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\booklet;
use local_oksigeniaayvurenda\local\bookletview;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\challenges;
use local_oksigeniaayvurenda\local\digital;
use local_oksigeniaayvurenda\local\mail;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\years;

$seal = optional_param('s', '', PARAM_ALPHANUM);
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$app = fn(string $file) => (new moodle_url('/local/oksigeniaayvurenda/app/' . $file))->out(false);
$now = time();

// A voice of a booklet: a reader's, published, with its seal.
$voiceid = optional_param('voice', 0, PARAM_INT);
if ($voiceid) {
    $voice = hash_equals(mail::seal('bookletvoice', $voiceid, 'v'), $seal) ? $DB->get_record(
        'local_oksigeniaayvurenda_digital',
        ['id' => $voiceid, 'kind' => 'voice', 'status' => 'published']
    ) : null;
    $file = $voice && $voice->readerid ? digital::file($voice) : null;
    if (!$file) {
        http_response_code(404);
        die;
    }
    @header('X-Content-Type-Options: nosniff');
    send_stored_file($file, 0, 0, false, ['cacheability' => 'private']);
}

$PAGE->set_context(context_system::instance());
$sheets = [];
$lives = [];
$bar = null;
$classhead = null;
$foot = null;
$ogimage = '';
$title = '';
$look = 'flower';
$edition = 'curso';

$id = optional_param('b', 0, PARAM_INT);
if ($id) {
    // The family's page, from its sealed link.
    $opened = booklet::opened($id, $seal);
    if (!$opened) {
        http_response_code(404);
        die;
    }
    $row = $opened['booklet'];
    $reader = $opened['reader'];
    $library = mail::main_library((int) $reader->entityid);
    $year = $DB->get_record('local_oksigeniaayvurenda_year', ['id' => $row->yearid]);
    if (!$library || !$year) {
        http_response_code(404);
        die;
    }
    $PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/booklet.php', ['b' => $id, 's' => $seal]));
    if (optional_param('cover', 0, PARAM_BOOL)) {
        $jpeg = booklet::cover_of($row, $reader, $library, $year);
        if ($jpeg === null) {
            http_response_code(404);
            die;
        }
        @header('Content-Type: image/jpeg');
        @header('Cache-Control: private, max-age=86400');
        @header('X-Content-Type-Options: nosniff');
        echo $jpeg;
        die;
    }
    $name = bookletview::name($reader);
    if ($row->kind === 'life' && ($life = bookletview::life($reader, $library))) {
        $lives[] = $life;
        $look = $life['look'];
        $edition = 'vida';
        $title = $str('bookletlifesubject', (object) ['child' => $name]);
        $sharetext = $str('bookletlifesharetext', $name);
        $foottext = $str('bookletfootlife');
    } else {
        $sheet = bookletview::sheet($reader, $year, $library, $now);
        $sheets[] = $sheet;
        $look = $sheet['look'];
        $title = $str('bookletyearsubject', (object) ['child' => $name]);
        $sharetext = $str($look === 'sky' ? 'bookletsharetextsky' : 'bookletsharetextflower', $name);
        $foottext = $str('bookletfootcourse');
    }
    $ogimage = (new moodle_url('/local/oksigeniaayvurenda/booklet.php', ['b' => $id, 's' => $seal, 'cover' => 1]))->out(false);
    $bar = ['print' => $str('bookletprint'), 'share' => $str('bookletshare'), 'sharetitle' => $title,
        'sharetext' => $sharetext, 'copied' => $str('familypagecopied')];
    $foot = ['foottext' => $foottext, 'footlink' => $str('familypagefootlink'), 'madewith' => $str('planmadewithfoot')];
} else {
    // The staff's, and the tutor's: inside the library, with its permissions.
    $ref = shell::library_ref();
    require_login(null, false);
    $library = catalogue::library($ref);
    if (!$library) {
        throw new moodle_exception('invalidparameter', 'debug');
    }
    $entityid = (int) $library->entityid;
    $group = optional_param('group', '', PARAM_TEXT);
    $readerid = optional_param('reader', 0, PARAM_INT);
    $wholelife = optional_param('life', 0, PARAM_BOOL);
    $staff = access::can('local/oksigeniaayvurenda:desk', $library);
    $asked = $group !== '' ? ['group' => $group] : ['reader' => $readerid];
    $PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/booklet.php', ['library' => $ref] + $asked
        + ($wholelife ? ['life' => 1] : [])));
    $year = years::around($entityid, $now);
    $yearid = optional_param('year', 0, PARAM_INT);
    if ($yearid) {
        $year = $DB->get_record('local_oksigeniaayvurenda_year', ['id' => $yearid, 'entityid' => $entityid], '*', MUST_EXIST);
    }
    if ($group !== '') {
        // The tutor of the group, or the library's staff.
        if (!$staff && !in_array($group, challenges::tutored($entityid, (int) $USER->id, $now), true)) {
            shell::require('local/oksigeniaayvurenda:desk', $library);
        }
        $readers = $DB->get_records_sql(
            "SELECT r.*
               FROM {local_oksigeniaayvurenda_reader} r
               JOIN {local_oksigeniaayvurenda_member} m ON m.readerid = r.id
              WHERE r.entityid = :entityid AND r.status = 'active' AND m.yearid = :yearid AND m.groupname = :groupname
           ORDER BY r.lastname, r.firstname, r.id",
            ['entityid' => $entityid, 'yearid' => $year->id, 'groupname' => $group]
        );
        foreach ($readers as $reader) {
            if ($wholelife) {
                $life = bookletview::life($reader, $library);
                if ($life) {
                    $lives[] = $life;
                }
            } else {
                $sheets[] = bookletview::sheet($reader, $year, $library, $now);
            }
        }
        $count = count($sheets) + count($lives);
        $look = ($sheets[0]['look'] ?? ($lives[0]['look'] ?? 'flower'));
        $title = $str($wholelife ? 'bookletclasslifeheading' : 'bookletclassheading', $group);
        $classhead = ['heading' => $title, 'lead' => $str($wholelife ? 'bookletclasslifelead' : 'bookletclasslead', $count),
            'printall' => $str($wholelife ? 'bookletclasslifeprint' : 'bookletclassprintall', $count),
            'back' => $str('bookletclassback'),
            'backurl' => (new moodle_url('/local/oksigeniaayvurenda/class.php', ['library' => $library->slug]))->out(false)];
        $edition = $wholelife ? 'vida' : 'curso';
    } else {
        shell::require('local/oksigeniaayvurenda:desk', $library);
        $reader = $DB->get_record('local_oksigeniaayvurenda_reader', ['id' => $readerid, 'entityid' => $entityid], '*', MUST_EXIST);
        $name = bookletview::name($reader);
        if ($wholelife && ($life = bookletview::life($reader, $library))) {
            $lives[] = $life;
            $look = $life['look'];
            $edition = 'vida';
            $title = $str('bookletlifesubject', (object) ['child' => $name]);
        } else {
            $sheet = bookletview::sheet($reader, $year, $library, $now);
            $sheets[] = $sheet;
            $look = $sheet['look'];
            $title = $str('bookletyearsubject', (object) ['child' => $name]);
        }
        $bar = ['print' => $str('bookletprint'), 'share' => '', 'sharetitle' => '', 'sharetext' => '', 'copied' => ''];
    }
}

@header('Content-Type: text/html; charset=utf-8');
@header('Cache-Control: private, no-store');
@header('X-Content-Type-Options: nosniff');
@header('Referrer-Policy: no-referrer');
@header('X-Robots-Tag: noindex, nofollow');
echo $OUTPUT->render_from_template('local_oksigeniaayvurenda/booklet', [
    'lang' => str_replace('_', '-', current_language()),
    'title' => $title,
    'ogdescription' => format_string($library->name, true, ['escape' => false]),
    'ogimage' => $ogimage,
    'url' => $PAGE->url->out(false),
    'css' => $app('librito.css'),
    'js' => $app('librito.js'),
    'edition' => $edition,
    'skin' => $look === 'sky' ? 'cielo' : 'flor',
    'library' => format_string($library->name, true, ['escape' => false]),
    'emblem' => $app('emblema-pequeno.svg'),
    'bar' => $bar,
    'classhead' => $classhead,
    'sheets' => $sheets,
    'lives' => $lives,
    'foot' => $foot,
]);
