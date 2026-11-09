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

$ref = \local_oksigeniaayvurenda\local\shell::library_ref();
$group = optional_param('group', null, PARAM_TEXT);
$type = optional_param('type', '', PARAM_ALPHA);
$list = optional_param('list', '', PARAM_ALPHA);
$type = in_array($type, readers::KINDS, true) ? $type : '';
$left = $list === 'left';
if ($group === null && ($type !== '' || $left)) {
    $group = '';
}
$query = trim(optional_param('q', '', PARAM_TEXT));
$from = max(0, optional_param('from', 0, PARAM_INT));
require_login(null, false);
$library = catalogue::library($ref);
$context = access::context($library);
shell::require('local/oksigeniaayvurenda:desk', $library);
$PAGE->set_context(context_system::instance());
$keep = optional_param('help', 0, PARAM_BOOL) ? ['help' => 1] : [];
$params = ['library' => $ref] + ($group !== null ? ['group' => $group] : []) + ($type !== '' ? ['type' => $type] : [])
    + ($left ? ['list' => 'left'] : []) + $keep;
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
        'life' => \local_oksigeniaayvurenda\local\life::readers($library, time()),
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
        $people = $desk->find_readers($query, 40);
        // Nobody by those letters: who it may have meant.
        $near = !$people;
        $people = array_map(
            fn($r) => $r + ['url' => $page('reader.php') . '&id=' . $r['id'] . '&q=' . rawurlencode($query), 'cardcode' => ''],
            $people ?: $desk->near_readers($query, 40)
        );
        $bycard = $DB->get_field('local_oksigeniaayvurenda_reader', 'id', ['entityid' => $entityid,
            'cardcode' => \core_text::strtoupper($query), 'status' => 'active']);
        if ($bycard && !in_array((int) $bycard, array_column($people, 'id'), true)) {
            $reader = $desk->reader((int) $bycard);
            $people[] = ['id' => (int) $bycard, 'name' => $reader['name'], 'group' => $reader['group'],
                'photo' => $reader['photo'] ?? '', 'initial' => $reader['initial'] ?? '',
                'url' => $page('reader.php') . '&id=' . $bycard . '&q=' . rawurlencode($query), 'cardcode' => ''];
        }
        $data['found'] = ['heading' => $str('readersfound', $query),
            'none' => $people ? ($near ? $str('readersnear') : '') : $str('readersfoundnone'),
            'people' => $people, 'clear' => $url([]), 'clearlabel' => $str('readersfindclear')];
    } else if ($total && !$groups) {
        // No classes: the readers themselves, a page at a time.
        $data['people'] = readers::in_group($library, '', null, $pagesize, $from);
        $data['pager'] = $pager($total, []);
    }
    if ($total && !$groups && $query === '') {
        // Without classes there is no group to print the cards of: those of everyone, from here.
        $data['tools'][] = ['url' => $page('cardprint.php'), 'label' => $str('printcards')];
    } else if ($groups && $query === '') {
        $data['tools'][] = ['url' => $page('cardprint.php'), 'label' => $str('printcards')];
    }
    if ($manage) {
        $unlinked = $DB->count_records_select(
            'local_oksigeniaayvurenda_reader',
            "entityid = :entityid AND userid IS NULL AND status = 'active'",
            ['entityid' => $library->entityid]
        );
        // Only where there are accounts to link them with: not on a shared site, nor where every account already has its
        // reader (a public library, a company or a school whose pupils have none would see it for ever); not on a search.
        $accounts = $unlinked && $query === '' && !\local_oksigeniaayvurenda\local\access::apart() ? $DB->count_records_sql(
            "SELECT COUNT(1) FROM {user} u
              WHERE u.deleted = 0 AND u.suspended = 0 AND u.id > 2
                    AND NOT EXISTS (SELECT 1 FROM {local_oksigeniaayvurenda_reader} r
                                     WHERE r.userid = u.id AND r.entityid = :entityid)",
            ['entityid' => $library->entityid]
        ) : 0;
        if ($unlinked && $accounts) {
            $data['match'] = ['url' => $page('match.php'), 'go' => $str('readersnoaccountgo'),
                'line' => $unlinked === 1 ? $str('readersnoaccountone') : $str('readersnoaccount', $number($unlinked)),
                'hint' => $str('readersnoaccounthint')];
        }
    }
    // Teachers and staff have no group, and no group is no problem: each kind on its own.
    if ($groups && $nogroup) {
        $tiles = [];
        $kinds = readers::without_group($library);
        foreach (['teacher', 'staff', 'student', 'family', 'other'] as $kind) {
            if (!empty($kinds[$kind])) {
                // Under «No group», the students are just that.
                $tiles[] = ['name' => \local_oksigeniaayvurenda\local\skins::say(
                    $entityid,
                    $kind === 'student' ? 'profiletypestudents' : 'readerstype' . $kind
                ),
                    'url' => $url(['type' => $kind]), 'count' => $number($kinds[$kind]), 'countlabel' => $howmany($kinds[$kind])];
            }
        }
        $data['stages'][] = ['name' => $str('readersnogroup'), 'named' => true, 'groups' => $tiles];
    }
    $gone = readers::in_group_count($library, '', null, ['status' => 'left']);
    if ($gone && $query === '') {
        $data['tools'][] = ['url' => $url(['list' => 'left']), 'label' => $str('readersleftcount', $number($gone))];
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
        $choices = \local_oksigeniaayvurenda\local\challenges::choices($challenge);
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
            'locked' => $off !== null,
            'kinds' => $choices['kinds'],
            'choose' => $choices['choose'],
            'choosehint' => $choices['choosehint'],
            'save' => $choices['save'],
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
    $filter = ['type' => $type] + ($left ? ['status' => 'left'] : []);
    $people = readers::in_group($library, $group, null, $paged ? $pagesize : 0, $paged ? $from : 0, $filter);
    $entityid = (int) $library->entityid;
    $data = [
        'pager' => $paged ? $pager(
            readers::in_group_count($library, '', null, $filter),
            ['group' => ''] + ($type !== '' ? ['type' => $type] : []) + ($left ? ['list' => 'left'] : [])
        ) : null,
        'heading' => $left ? $str('readersleft') : ($type !== ''
            ? \local_oksigeniaayvurenda\local\skins::say($entityid, 'readerstype' . $type)
            : ($group !== '' ? $group : $str('readersnogroup'))),
        'intro' => $left ? $str('readerslefthint') : ($type === 'student'
            ? \local_oksigeniaayvurenda\local\skins::say((int) $library->entityid, 'readersstudentshint') : ''),
        'find' => $paged ? ['action' => (new moodle_url('/local/oksigeniaayvurenda/readers.php'))->out(false),
            'library' => $library->slug, 'findlabel' => $str('readersfind'), 'findhint' => $str('readersfindhint'),
            'findgo' => $str('readersfindgo')] : null,
        'readers' => $people,
        'hasreaders' => (bool) $people,
        'cardlabel' => $str('cardcode'),
        'loanslabel' => $str('deskloans'),
        'back' => ['url' => $url([]), 'label' => $str('readers')],
        'backword' => $str('profileback'),
        'empty' => $people ? '' : $str('readersnone'),
        'print' => $people && $group !== '' && !$left ? ['url' => (new moodle_url(
            '/local/oksigeniaayvurenda/cardprint.php',
            ['library' => $library->slug, 'group' => $group]
        ))->out(false), 'label' => $str('printcards')] : null,
        'challenge' => $challengeview,
        'entry' => $group !== '' && !$left && \local_oksigeniaayvurenda\local\entry::waiting($library, $group, time())
            ? ['url' => (new moodle_url('/local/oksigeniaayvurenda/entries.php', ['library' => $library->slug,
                'group' => $group]))->out(false), 'label' => $str('entrygroupbutton')] : null,
    ];
    $html = $OUTPUT->render_from_template('local_oksigeniaayvurenda/group', $data);
}
$deskurl = (new moodle_url('/local/oksigeniaayvurenda/desk.php', ['library' => $library->slug]))->out(false);
$nav = $group === null ? [['url' => $deskurl, 'label' => $str('desktitle')],
    ['url' => (new moodle_url('/local/oksigeniaayvurenda/help.php', ['library' => $library->slug, 'piece' => 'readers']))
        ->out(false), 'label' => $str('helpnav')]] : [['url' => $deskurl, 'label' => $str('desktitle')]];
shell::output($data['heading'], $html, $library, '', ['search' => false, 'nav' => $keep ? [] : $nav]);
