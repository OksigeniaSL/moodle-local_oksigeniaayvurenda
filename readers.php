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
 * The readers of a library: the groups of this school year and who is in each.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\readers;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
$group = optional_param('group', null, PARAM_TEXT);
$query = trim(optional_param('q', '', PARAM_TEXT));
$from = max(0, optional_param('from', 0, PARAM_INT));
require_login(null, false);
$library = catalogue::library($ref);
$context = access::context($library);
shell::require('local/oksigeniaayvurenda:desk', $library);
$PAGE->set_context(context_system::instance());
$keep = optional_param('help', 0, PARAM_BOOL) ? ['help' => 1] : [];
$params = ['library' => $ref] + ($group !== null ? ['group' => $group] : []) + $keep;
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/readers.php', $params));

if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$url = fn(array $params) => (new moodle_url('/local/oksigeniaayvurenda/readers.php', ['library' => $library->slug] + $params
    + $keep))->out(false);
$page = fn(string $file) => (new moodle_url('/local/oksigeniaayvurenda/' . $file, ['library' => $library->slug]))->out(false);
$number = fn(int $n) => \local_oksigeniaayvurenda\local\screens::number($n);
$howmany = fn(int $n) => $n === 1 ? $str('onereader') : $str('readerscount', $number($n));
// A page of readers, where a library without classes may have thousands.
$pagesize = 60;
$pager = function (int $total, array $params) use ($pagesize, $from, $url, $str, $number) {
    if ($total <= $pagesize) {
        return null;
    }
    return [
        'prev' => $from > 0 ? $url($params + ['from' => max(0, $from - $pagesize)]) : '',
        'next' => $from + $pagesize < $total ? $url($params + ['from' => $from + $pagesize]) : '',
        'line' => $str('readerspage', (object) ['from' => $number($from + 1), 'to' => $number(min($from + $pagesize, $total)),
            'total' => $number($total)]),
        'prevlabel' => $str('readersprev'),
        'nextlabel' => $str('readersnext'),
    ];
};
if ($group === null) {
    $manage = has_capability('local/oksigeniaayvurenda:manage', $context);
    $entityid = (int) $library->entityid;
    $total = \local_oksigeniaayvurenda\local\readers::total($library);
    $groups = readers::groups($library);
    $stages = [];
    foreach ($groups as $row) {
        $stages[$row['stage']] ??= ['name' => $row['stage'], 'named' => $row['stage'] !== '', 'groups' => []];
        $stages[$row['stage']]['groups'][] = ['name' => $row['name'], 'url' => $url(['group' => $row['name']]),
            'count' => $number($row['count']), 'countlabel' => $howmany($row['count'])];
    }
    $howgroups = count($groups) === 1 ? $str('readersgroupsone') : $str('readersgroupsmany', $number(count($groups)));
    $nogroup = readers::in_group_count($library, '');
    $erased = optional_param('erased', 0, PARAM_INT);
    $data = [
        'heading' => $str('readers'),
        'notice' => $erased ? $str($erased === 2 ? 'readererasedaccount' : 'readererased') : '',
        'summary' => $howmany($total) . ($groups ? ' · ' . $howgroups : ''),
        'bring' => $manage ? ['url' => (new moodle_url(
            '/local/oksigeniaayvurenda/sources.php',
            ['library' => $library->slug] + $keep
        ))->out(false), 'label' => $str('readersbring')] : null,
        'hasreadersatall' => $total > 0,
        'action' => (new moodle_url('/local/oksigeniaayvurenda/readers.php'))->out(false),
        'library' => $library->slug,
        'query' => $query,
        'findlabel' => $str('readersfind'),
        'findhint' => $str('readersfindhint'),
        'findgo' => $str('readersfindgo'),
        'stages' => array_values($stages),
        'cardlabel' => $str('cardcode'),
        'loanslabel' => $str('deskloans'),
        'said' => $total ? null
            : \local_oksigeniaayvurenda\local\characters::say($entityid, 'memory', 'readersmemory', [], 'piensa'),
        'match' => null,
        'found' => null,
        'people' => null,
        'pager' => null,
        'tools' => [],
    ];
    if ($query !== '') {
        // By name, or by the card itself: a reader may be standing there with it.
        $desk = new \local_oksigeniaayvurenda\local\desk($library, (int) $USER->id);
        $people = array_map(
            fn($r) => $r + ['url' => $page('reader.php') . '&id=' . $r['id'], 'cardcode' => ''],
            $desk->find_readers($query, 40)
        );
        $bycard = $DB->get_field('local_oksigeniaayvurenda_reader', 'id', ['entityid' => $entityid,
            'cardcode' => \core_text::strtoupper($query), 'status' => 'active']);
        if ($bycard && !in_array((int) $bycard, array_column($people, 'id'), true)) {
            $reader = $desk->reader((int) $bycard);
            $people[] = ['id' => (int) $bycard, 'name' => $reader['name'], 'group' => $reader['group'],
                'photo' => $reader['photo'] ?? '', 'initial' => $reader['initial'] ?? '',
                'url' => $page('reader.php') . '&id=' . $bycard, 'cardcode' => ''];
        }
        $data['found'] = ['heading' => $str('readersfound', $query), 'none' => $people ? '' : $str('readersfoundnone'),
            'people' => $people, 'clear' => $url([]), 'clearlabel' => $str('readersfindclear')];
    } else if ($total && !$groups) {
        // No classes: the readers themselves, a page at a time.
        $data['people'] = readers::in_group($library, '', null, $pagesize, $from);
        $data['pager'] = $pager($total, []);
    }
    if ($manage) {
        $unlinked = $DB->count_records_select(
            'local_oksigeniaayvurenda_reader',
            "entityid = :entityid AND userid IS NULL AND status = 'active'",
            ['entityid' => $library->entityid]
        );
        if ($unlinked) {
            $data['match'] = ['url' => $page('match.php'), 'go' => $str('readersnoaccountgo'),
                'line' => $unlinked === 1 ? $str('readersnoaccountone') : $str('readersnoaccount', $number($unlinked)),
                'hint' => $str('readersnoaccounthint')];
        }
    }
    if ($groups && $nogroup) {
        $data['tools'][] = ['url' => $url(['group' => '']), 'label' => $str('readersnogroupcount', $number($nogroup))];
    }
    if ($manage) {
        $data['tools'][] = ['url' => $page('twins.php'), 'label' => $str('twinsnav')];
        if (!\local_oksigeniaayvurenda\local\skins::no_classes($entityid)) {
            $data['tools'][] = ['url' => $page('tutors.php'), 'label' => $str('tutorsnav')];
        }
    }
    $html = $OUTPUT->render_from_template('local_oksigeniaayvurenda/readers', $data);
} else {
    // The challenge of the class this term: the staff choose it, or none.
    $notice = '';
    if ($group !== '' && data_submitted() && confirm_sesskey()) {
        $kind = optional_param('challenge', '', PARAM_ALPHA);
        if ($kind === 'off' || isset(\local_oksigeniaayvurenda\local\challenges::KINDS[$kind])) {
            \local_oksigeniaayvurenda\local\challenges::choose((int) $library->entityid, $group, $kind);
            $notice = $str('challengesaved');
        }
    }
    $challengeview = null;
    if ($group !== '') {
        $challenge = \local_oksigeniaayvurenda\local\challenges::of((int) $library->entityid, $group);
        $kinds = [];
        foreach (array_merge(array_keys(\local_oksigeniaayvurenda\local\challenges::KINDS), ['off']) as $kind) {
            $kinds[] = ['value' => $kind, 'label' => $str('challengekind' . $kind),
                'checked' => $challenge ? $challenge->kind === $kind : $kind === 'off'];
        }
        $garden = array_map(
            fn($p) => ['label' => $str('challengeplanted', $p['term']) . ' · ' . $str('challengekind' . $p['kind'])],
            \local_oksigeniaayvurenda\local\challenges::garden((int) $library->entityid, $group)
        );
        $off = \local_oksigeniaayvurenda\local\challenges::stage_off(
            (int) $library->entityid,
            (int) \local_oksigeniaayvurenda\local\years::around((int) $library->entityid, time())->id,
            $group
        );
        $challengeview = [
            'offstage' => $off !== null ? $str('classoff', $off) : '',
            'current' => $challenge ? \local_oksigeniaayvurenda\local\challenges::view($challenge, (int) $library->entityid, true)
                : null,
            'locked' => ($challenge && $challenge->complete) || $off !== null,
            'kinds' => $kinds,
            'choose' => $str('challengechoose'),
            'choosehint' => $str('challengechoosehint'),
            'save' => $str('challengesave'),
            'action' => $PAGE->url->out(false),
            'sesskey' => sesskey(),
            'garden' => $garden,
            'hasgarden' => (bool) $garden,
            'gardenheading' => $str('challengegarden'),
            'gardenempty' => $str('challengegardenempty'),
            'notice' => $notice,
        ];
    }
    // Those without a group may be many; a class, never.
    $paged = $group === '';
    $list = readers::in_group($library, $group, null, $paged ? $pagesize : 0, $paged ? $from : 0);
    $data = [
        'pager' => $paged ? $pager(readers::in_group_count($library, ''), ['group' => '']) : null,
        'heading' => $group !== '' ? $group : $str('readersnogroup'),
        'readers' => $list,
        'hasreaders' => (bool) $list,
        'cardlabel' => $str('cardcode'),
        'loanslabel' => $str('deskloans'),
        'back' => ['url' => $url([]), 'label' => $str('readersback')],
        'empty' => $list ? '' : $str('readersnone'),
        'print' => $list ? ['url' => (new moodle_url('/local/oksigeniaayvurenda/print.php', ['library' => $library->slug,
            'group' => $group]))->out(false), 'label' => $str('printcards')] : null,
        'challenge' => $challengeview,
    ];
    $html = $OUTPUT->render_from_template('local_oksigeniaayvurenda/group', $data);
}
$deskurl = (new moodle_url('/local/oksigeniaayvurenda/desk.php', ['library' => $library->slug]))->out(false);
$nav = $group === null ? [['url' => $deskurl, 'label' => $str('desktitle')],
    ['url' => (new moodle_url('/local/oksigeniaayvurenda/help.php', ['library' => $library->slug, 'piece' => 'readers']))
        ->out(false), 'label' => $str('helpnav')]] : [['url' => $deskurl, 'label' => $str('desktitle')]];
shell::output($data['heading'], $html, $library, '', ['search' => false, 'nav' => $keep ? [] : $nav]);
