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
 * Readers that are the same person: the likely ones, and any two by their card codes, put together.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\twins;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
$codea = optional_param('a', '', PARAM_ALPHANUMEXT);
$codeb = optional_param('b', '', PARAM_ALPHANUMEXT);
$query = optional_param('q', '', PARAM_TEXT);
require_login(null, false);
$library = catalogue::library($ref);
$context = access::context($library);
shell::require('local/oksigeniaayvurenda:manage', $library);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/twins.php', ['library' => $ref]));

if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$entityid = (int) $library->entityid;
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$notice = '';
$error = '';
if (data_submitted() && confirm_sesskey()) {
    $keep = required_param('keep', PARAM_INT);
    $all = optional_param_array('ids', [], PARAM_INT);
    try {
        $joined = 0;
        foreach ($all as $id) {
            if ((int) $id !== $keep) {
                twins::join($entityid, $keep, (int) $id);
                $joined++;
            }
        }
        $kept = twins::describe($entityid, [$keep])[$keep] ?? null;
        $notice = $kept ? $str('twinsjoined', (object) ['name' => $kept['name'], 'card' => $kept['cardcode'], 'count' => $joined])
            : '';
        $codea = $codeb = '';
    } catch (moodle_exception $e) {
        $error = $e->getMessage();
    }
}
$typelabel = fn(string $type) => get_string_manager()->string_exists('readerkind' . $type, 'local_oksigeniaayvurenda')
    ? $str('readerkind' . $type) : $type;
$card = function (array $reader, bool $first) use ($str, $typelabel) {
    return $reader + [
        'typelabel' => $typelabel($reader['type']),
        'loanslabel' => $str('twinsloans', (object) ['all' => $reader['loans'], 'out' => $reader['out']]),
        'accountlabel' => $reader['account'] ? $str('twinsaccount') : '',
        'checked' => $first,
    ];
};
$sets = [];
$likely = twins::likely($entityid, $query);
foreach (array_slice($likely, 0, 40) as $n => $ids) {
    $readers = array_values(twins::describe($entityid, $ids));
    // The one with more history is offered to stay.
    usort($readers, fn($x, $y) => [$y['account'], $y['loans']] <=> [$x['account'], $x['loans']]);
    $sets[] = ['setid' => $n, 'members' => array_map(fn($r, $i) => $card($r, $i === 0), $readers, array_keys($readers))];
}
$pair = null;
$lookupnote = '';
if ($codea !== '' || $codeb !== '') {
    $ida = twins::by_code($entityid, $codea);
    $idb = twins::by_code($entityid, $codeb);
    if (!$ida || !$idb) {
        $lookupnote = $str('twinsnocard', s(!$ida ? $codea : $codeb));
    } else if ($ida === $idb) {
        $lookupnote = $str('twinssame');
    } else {
        $described = twins::describe($entityid, [$ida, $idb]);
        $pair = ['members' => [$card($described[$ida], true), $card($described[$idb], false)]];
    }
}
$data = [
    'heading' => $str('twinsheading'),
    'intro' => $str('twinsintro'),
    'notice' => $notice,
    'error' => $error,
    'action' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
    'slug' => $library->slug,
    'likelyheading' => $str('twinslikely', count($likely)),
    'more' => count($likely) > count($sets) ? $str('twinsmore', count($likely) - count($sets)) : '',
    'query' => $query,
    'filterlabel' => $str('twinsfilter'),
    'filterbutton' => $str('windowsearchbutton'),
    'likelyintro' => $str('twinslikelyintro'),
    'sets' => $sets,
    'hassets' => (bool) $sets,
    'nosets' => $str('twinsnone'),
    'keeplabel' => $str('twinskeep'),
    'joinlabel' => $str('twinsjoin'),
    'handheading' => $str('twinshand'),
    'handintro' => $str('twinshandintro'),
    'codealabel' => $str('twinscodea'),
    'codeblabel' => $str('twinscodeb'),
    'codea' => $codea,
    'codeb' => $codeb,
    'lookuplabel' => $str('twinslookup'),
    'lookupnote' => $lookupnote,
    'pair' => $pair,
    'cardlabel' => $str('cardcode'),
];
$readersurl = (new moodle_url('/local/oksigeniaayvurenda/readers.php', ['library' => $library->slug]))->out(false);
shell::output(
    $data['heading'],
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/twins', $data),
    $library,
    '',
    ['search' => false, 'nav' => [['url' => $readersurl, 'label' => $str('readers')]]]
);
