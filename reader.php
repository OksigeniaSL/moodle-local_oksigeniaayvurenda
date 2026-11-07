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
 * A reader's record, for the library staff: who they are, with their photo, what they have now, what they took home and
 * the staff's notes.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\core\desk\sanction_rules;
use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\applink;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\covers;
use local_oksigeniaayvurenda\local\desk;
use local_oksigeniaayvurenda\local\profile;
use local_oksigeniaayvurenda\local\sanctions;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\zones;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
$id = required_param('id', PARAM_INT);
require_login(null, false);
$library = catalogue::library($ref);
// Whoever keeps only this library sees the readers it serves, as at its desk.
$own = $library && access::keeps($library);
if (!$own) {
    shell::require('local/oksigeniaayvurenda:desk', $library);
}
$PAGE->set_context(context_system::instance());
// Where the record was opened from, to go back there.
$from = array_filter(['group' => optional_param('group', '', PARAM_TEXT), 'type' => optional_param('type', '', PARAM_ALPHA),
    'list' => optional_param('list', '', PARAM_ALPHA), 'q' => optional_param('q', '', PARAM_TEXT)]);
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/reader.php', ['library' => $ref, 'id' => $id] + $from));
$str = \local_oksigeniaayvurenda\local\skins::speaker($library ?? null);
if (!$library) {
    shell::missing($str('readernothere'));
}
$now = time();
if (!(new desk($library, (int) $USER->id, $now, $own))->allowed($id)) {
    shell::missing($str('readernothere'), $library);
}
$notice = '';
$reader = $DB->get_record('local_oksigeniaayvurenda_reader', ['id' => $id, 'entityid' => $library->entityid]);
// A reader of another centre, or one erased, is nobody here: nothing is read, and nothing is written.
if (!$reader || $reader->status === 'deleted') {
    shell::missing($str('readernothere'), $library);
}
\local_oksigeniaayvurenda\event\reader_viewed::fire((int) $library->entityid, (int) $reader->id);
// Whoever only keeps this library sees what it lends them: not their history elsewhere, nor the staff's notes, and
// writes nothing here.
$limited = $own && !access::can('local/oksigeniaayvurenda:desk', $library);
if (!$limited && data_submitted() && confirm_sesskey()) {
    if (optional_param('do', '', PARAM_ALPHA) === 'app' && $reader) {
        // The way into the app, by e-mail.
        $notice = $str(applink::send($reader));
        $reader = $DB->get_record('local_oksigeniaayvurenda_reader', ['id' => $id]);
    } else if (optional_param('do', '', PARAM_ALPHA) === 'erase' && access::can('local/oksigeniaayvurenda:manage', $library)) {
        // At the reader's request: everything that says who they are goes, and their account if it has nothing else here.
        if (optional_param('sure', 0, PARAM_BOOL)) {
            $erased = \local_oksigeniaayvurenda\local\erasure::erase(
                $library,
                $id,
                optional_param('lost', 0, PARAM_BOOL),
                (int) $USER->id,
                $now
            );
            if ($erased['done']) {
                redirect(new moodle_url('/local/oksigeniaayvurenda/readers.php', ['library' => $library->slug,
                    'erased' => $erased['account'] ? 2 : 1]));
            }
            $notice = $erased['open'] === 1 ? $str('readereraseoneout') : $str('readererasebooksout', $erased['open']);
        } else {
            $notice = $str('readererasesure');
        }
    } else if (optional_param('do', '', PARAM_ALPHA) === 'needs') {
        \local_oksigeniaayvurenda\local\formats::mark($library, $id, optional_param('needs', 0, PARAM_BOOL));
        $notice = $str('profileneedssaved');
        $reader = $DB->get_record('local_oksigeniaayvurenda_reader', ['id' => $id]);
    } else if (optional_param('do', '', PARAM_ALPHA) === 'data') {
        $notice = $str(profile::change($library, $id, [
            'firstname' => optional_param('firstname', '', PARAM_TEXT),
            'lastname' => optional_param('lastname', '', PARAM_TEXT),
            'email' => optional_param('email', '', PARAM_RAW_TRIMMED),
            'type' => optional_param('newtype', '', PARAM_ALPHA),
            'band' => optional_param('newband', '', PARAM_ALPHA),
            'group' => optional_param('newgroup', '', PARAM_TEXT),
            'tutorof' => optional_param('tutorof', '', PARAM_TEXT),
        ], $now, access::can('local/oksigeniaayvurenda:manage', $library)));
        $reader = $DB->get_record('local_oksigeniaayvurenda_reader', ['id' => $id]);
    } else if (in_array(optional_param('do', '', PARAM_ALPHA), ['left', 'back'], true)) {
        $gone = optional_param('do', '', PARAM_ALPHA) === 'left';
        profile::set_gone($library, $id, $gone);
        $notice = $str($gone ? 'profileleavedone' : 'profilebackdone');
        $reader = $DB->get_record('local_oksigeniaayvurenda_reader', ['id' => $id]);
    } else if (optional_param('do', '', PARAM_ALPHA) === 'lost') {
        // A book the reader lost, from their record: as at the desk.
        $notice = (new desk($library, (int) $USER->id, $now, $own))->lose(required_param('loan', PARAM_INT))['message'];
    } else if (optional_param('do', '', PARAM_ALPHA) === 'newcard') {
        $notice = $str('profilenewcarddone', profile::new_card($library, $id));
    } else if (optional_param('do', '', PARAM_ALPHA) === 'lift') {
        sanctions::lift($library, $id, (int) $USER->id, $now);
        $notice = $str('sanctionrowlifted');
    } else if (in_array(optional_param('do', '', PARAM_ALPHA), ['pay', 'waive'], true)) {
        $paid = optional_param('do', '', PARAM_ALPHA) === 'pay';
        sanctions::settle(
            $library,
            $id,
            $paid ? sanctions::PAID : sanctions::WAIVED,
            (int) $USER->id,
            $now,
            optional_param('row', 0, PARAM_INT)
        );
        $notice = $str($paid ? 'sanctionrowpaid' : 'sanctionrowwaived');
    } else if (optional_param('do', '', PARAM_ALPHA) === 'reopen') {
        sanctions::reopen($library, optional_param('row', 0, PARAM_INT), $now);
        $notice = $str('sanctionreopened');
    } else if (optional_param('do', '', PARAM_ALPHA) === 'charge') {
        $cents = sanction_rules::to_cents(optional_param('amount', '', PARAM_RAW_TRIMMED));
        $note = trim(optional_param('note', '', PARAM_TEXT));
        if ($cents > 0 && $note !== '') {
            sanctions::charge($library, $id, $cents, $note, (int) $USER->id, $now);
            $currency = sanctions::settings((int) $library->entityid)['currency'];
            $notice = $str('sanctionchargedone', sanctions::money($cents, $currency));
        }
    } else {
        profile::save_notes($library, $id, optional_param('notes', '', PARAM_TEXT));
        $notice = $str('profilenotessaved');
    }
}
$app = $reader && !$limited ? applink::state($reader) : 'none';
$record = profile::of($library, $id, $now);
if (!$record) {
    shell::missing($str('readernothere'), $library);
}
if ($limited) {
    $record['loans'] = array_values(array_filter($record['loans'], fn($l) => $l['libraryid'] === (int) $library->id));
    $record['holds'] = [];
    $record['read'] = ['books' => [], 'total' => 0];
    $record['past'] = [];
    $record['notes'] = '';
}
$link = fn(string $script, array $params = []) => (new moodle_url(
    '/local/oksigeniaayvurenda/' . $script,
    ['library' => $library->slug] + $params
))->out(false);
$day = fn(int $time) => zones::date($time, 'strftimedate', $library);
$covers = covers::urls(array_merge(array_column($record['loans'], 'recordid'), array_column(
    $record['read']['books'],
    'recordid'
)));
$book = fn(array $b) => ['title' => $b['title'], 'url' => $link('record.php', ['id' => $b['recordid']]),
    'hascover' => isset($covers[$b['recordid']]), 'coverurl' => $covers[$b['recordid']] ?? '',
    'colour' => $b['recordid'] % 6];
$facts = array_values(array_filter([
    $record['group'] !== '' ? $record['group'] . ($record['stage'] !== '' ? ' · ' . $record['stage'] : '') : '',
    !$limited && $record['tutor'] !== '' ? $str('profiletutor', $record['tutor']) : '',
    $limited ? '' : $record['email'],
    !$limited && !$record['hasaccount'] ? $str('profilenoaccount') : '',
]));
$manages = access::can('local/oksigeniaayvurenda:manage', $library);
$form = profile::form($library, $reader, $now, $manages);
$say = fn(string $key) => \local_oksigeniaayvurenda\local\skins::say((int) $library->entityid, $key);
// The way back, to where the record was opened from.
$backlabel = $str('readers');
if (($from['list'] ?? '') === 'left') {
    $backlabel = $str('readersleft');
} else if (isset($from['type']) && in_array($from['type'], \local_oksigeniaayvurenda\local\readers::KINDS, true)) {
    $backlabel = $say('readerstype' . $from['type']);
} else if (isset($from['group'])) {
    $backlabel = $from['group'];
} else if (isset($from['q'])) {
    $backlabel = $str('profilebackfound', $from['q']);
}
$historytotal = profile::history_total($library, $id, $limited);
$looks = ['flower' => 'flower', 'sky' => 'sky', 'skysober' => 'sky'];
$drawing = '';
$stagename = '';
if (isset($looks[$record['look']])) {
    $stage = (int) $record['flower']['stage'];
    $drawing = $looks[$record['look']] === 'flower'
        ? \local_oksigeniaayvurenda\local\cards::flower_svg($stage, $record['flower']['levels'], $str('profileflower'), 120)
        : \local_oksigeniaayvurenda\local\cards::sky_svg(
            $stage,
            $record['flower']['levels'],
            $str('profilesky'),
            120,
            $record['look'] === 'skysober'
        );
    $stagename = $looks[$record['look']] === 'flower' ? $str('stage' . \local_oksigeniaayvurenda\core\flower::STAGES[$stage])
        : $str('stagesky' . $stage);
}
$notes = ['loved' => $str('noteloved'), 'unfinished' => $str('noteunfinished'), 'more' => $str('notemore')];
// What late books left: only for the staff of the centre, and only where it means anything.
$lateon = sanctions::settings((int) $library->entityid);
$laterows = $limited ? [] : sanctions::of_reader((int) $library->entityid, $id, $now);
$data = [
    'name' => $record['name'],
    'photo' => $record['photo'],
    'hasphoto' => $record['photo'] !== '',
    'initial' => $record['initial'],
    'type' => get_string_manager()->string_exists('profiletype' . $record['type'], 'local_oksigeniaayvurenda')
        ? \local_oksigeniaayvurenda\local\skins::say((int) $library->entityid, 'profiletype' . $record['type']) : '',
    'facts' => array_map(fn($f) => ['fact' => $f], $facts),
    'tutorof' => $limited ? [] : array_map(fn($g) => ['label' => $str('profiletutorof', $g),
        'url' => $link('readers.php', ['group' => $g])], $record['tutoring']),
    // Whoever only keeps this library has no list of readers: they came from their desk.
    'backurl' => $limited ? $link('desk.php') : $link('readers.php', $from),
    'backlabel' => $limited ? $str('desktitle') : $backlabel,
    'historyurl' => $historytotal ? $link('history.php', ['id' => $id] + $from) : '',
    'historylabel' => $str('profilehistory', $historytotal),
    'canedit' => !$limited,
    'hasaccount' => $record['hasaccount'],
    'formfirst' => $form['firstname'],
    'formlast' => $form['lastname'],
    'formemail' => $form['email'],
    'formtypes' => array_map(
        fn($t) => ['value' => $t, 'label' => $say('profiletype' . $t), 'selected' => $t === $form['type']],
        $form['types']
    ),
    'formhastypes' => (bool) $form['types'],
    'formhasbands' => $form['bandable'],
    'formbands' => array_map(
        fn($b) => ['value' => $b, 'label' => $str($b === '' ? 'profilebandauto' : 'cardlookopt' . $b),
            'selected' => $b === $form['band']],
        array_merge([''], \local_oksigeniaayvurenda\local\cards::BANDS)
    ),
    'formgroups' => array_map(fn($g) => ['name' => $g, 'selected' => $g === $form['group']], $form['groups']),
    'formlocked' => $form['lockedgroup'] !== '' ? $str('profiledatalocked', $form['lockedgroup']) : '',
    'formhasgroups' => (bool) $form['groups'],
    'formcanteach' => $form['canteach'],
    'formtutors' => array_map(
        fn($g) => ['name' => $g['name'], 'selected' => $g['name'] === $form['tutorof'],
        'label' => $g['who'] !== '' ? $str('profiledatatutoris', (object) ['group' => $g['name'], 'who' => $g['who']])
            : $g['name']],
        $form['tutorgroups']
    ),
    'gonenow' => $reader->status === 'left',
    'stayaccount' => $record['hasaccount'] ? $str('profileleaveaccount') : '',
    'canmatch' => !$limited && $manages && !$record['hasaccount'] ? $link('match.php') : '',
    'cantwin' => !$limited && $manages ? $link('twins.php', ['a' => $record['cardcode']]) : '',
    'cardcode' => $record['cardcode'],
    'barcode' => $record['barcode'],
    'since' => $str('profilesince', $day($record['since'])),
    'gone' => !$record['active'],
    'drawing' => $drawing,
    'stagename' => $stagename,
    'loans' => array_map(fn($l) => $book($l) + ['due' => $str('profiledue', $day($l['due'])), 'library' => $l['library'],
        'late' => $l['late'] ? $str($l['late'] === 1 ? 'profilelateone' : 'profilelate', $l['late']) : '',
        'loanid' => $l['loanid'], 'canlose' => !$limited], $record['loans']),
    'lostlabel' => $str('desklost'),
    'lostsure' => $str('desklostsure'),
    'hasloans' => (bool) $record['loans'],
    'holds' => array_map(fn($h) => ['title' => $h['title'], 'state' => $h['ready'] ? $str('profileholdready', $h['until'])
        : $str('profileholdwaiting')], $record['holds']),
    'hasholds' => (bool) $record['holds'],
    'readheading' => $str($record['read']['total'] === 1 ? 'profilereadone' : 'profileread', (object) ['year' => $record['year'],
        'count' => $record['read']['total']]),
    'read' => array_map(
        fn($b) => $book($b) + ['when' => $day($b['when']), 'note' => $notes[$b['note']] ?? ''],
        $record['read']['books']
    ),
    'hasread' => (bool) $record['read']['books'],
    'readmore' => $record['read']['total'] > count($record['read']['books'])
        ? $str('profilereadmore', $record['read']['total'] - count($record['read']['books'])) : '',
    'past' => array_map(fn($p) => ['year' => $p['year'], 'loans' => $p['loans'] === 1 ? $str('profilepastone')
        : $str('profilepast', $p['loans'])], $record['past']),
    'haspast' => (bool) $record['past'],
    'notes' => $record['notes'],
    'staffonly' => !$limited,
    'needs' => !empty($reader->accessneeds),
    'canerase' => !$limited && access::can('local/oksigeniaayvurenda:manage', $library) && $reader->status !== 'deleted',
    'erasetitle' => $str('readererasetitle', $record['name']),
    'erasedo' => $str('readererasedo', $record['name']),
    'eraselost' => (function () use ($id, $str) {
        $open = \local_oksigeniaayvurenda\local\erasure::open_loans($id);
        return $open ? ($open === 1 ? $str('readereraselostone') : $str('readereraselost', $open)) : '';
    })(),
    'asks' => $limited ? [] : array_map(
        fn($a) => ['title' => format_string($a['title'], true, ['escape' => false]),
        'format' => $str('format' . $a['format'] . 'label'), 'state' => $str('formatsstate' . $a['status'])],
        \local_oksigeniaayvurenda\local\formats::of_reader($id)
    ),
    // What is open goes first, for the staff to see; what is closed or empty, after what they have now.
    'lateopen' => !$limited && array_filter(array_column($laterows, 'open')),
    'latequiet' => !$limited && !array_filter(array_column($laterows, 'open'))
        && ($laterows || $lateon['mode'] === sanction_rules::FINE),
    'laterows' => $laterows,
    'hasnolaterows' => !$laterows,
    'cancharge' => !$limited && $lateon['mode'] === sanction_rules::FINE,
    'notice' => $notice,
    'action' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
    'deskurl' => $link('desk.php', ['reader' => $id]),
    'cardurl' => $link('card.php', ['reader' => $id]),
    'printurl' => $limited || $reader->status !== 'active' ? '' : $link('cardprint.php', ['reader' => $id]),
    'canrecard' => !$limited,
    'profilenewcardintro' => $str('profilenewcardintro', (string) $record['cardcode']),
    'entry' => $reader && !$limited && \local_oksigeniaayvurenda\local\entry::may($reader) ? [
        'url' => $link('entries.php', ['reader' => $id]),
        'hint' => $str('entryhint'),
        'button' => $str('entrybutton'),
        'heading' => $str('entryheading'),
    ] : false,
    'app' => $app !== 'none' ? [
        'can' => $app !== 'noemail',
        'needsemail' => $app === 'noemail',
        'button' => $str($app === 'create' ? 'appcreatebutton' : 'appsendbutton'),
        'hint' => $str($app === 'noemail' ? 'appnoemailhint' : 'apphint', applink::DAYS),
    ] : false,
];
foreach (
    ['profiledesk', 'profilecard', 'profilenow', 'profilenothing', 'profileholds', 'profilenoread', 'profilepastheading',
        'profilenotes', 'profilenoteshint', 'profilesave', 'profilesaved', 'profilegone', 'profilecode', 'appheading',
        'profilenewcard', 'profilenewcardgo', 'selfclose', 'sanctionstitle', 'sanctionsboxnone', 'sanctionlift',
        'sanctionpay', 'sanctionwaive', 'sanctionreopen', 'sanctionchargeadd', 'sanctionchargeintro', 'sanctionchargeamount',
        'sanctionchargenote', 'sanctionchargego', 'profileneeds', 'profileneedslabel', 'profileneedshint',
        'profileneedsasks', 'readererase', 'readererasehint', 'readererasego', 'readererasetitle', 'readererasegoes',
        'readererasestays', 'readererasecheck', 'readererasekeep', 'profiledata', 'profiledatafirst', 'profiledatalast',
        'profiledataemail', 'profiledatatype', 'profiledataband', 'profiledatagroup', 'profiledatanogroup', 'profiledataaccount',
        'profiledatatutor',
        'profiledatatutornone', 'profiledatatutorhint', 'profileback', 'profileleaveheading', 'profileleavehint', 'profileleavego',
        'profilebackheading', 'profilebackhint', 'profilebackgo', 'profilematch', 'profiletwin', 'appaddemail',
        'profileprint'] as $key
) {
    $data[$key] = $str($key);
}
$readers = (new moodle_url('/local/oksigeniaayvurenda/readers.php', ['library' => $library->slug]))->out(false);
shell::output(
    $record['name'],
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/reader', $data),
    $library,
    '',
    ['search' => false, 'nav' => [['url' => $link('desk.php'), 'label' => $str('desktitle')],
        ['url' => $readers, 'label' => $str('readers')]]]
);
