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
 * Ayvurenda: reader cards in print: of whom and how, and what is ready to take.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\cardprint;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\screens;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
$stage = optional_param('stage', '', PARAM_TEXT);
$group = optional_param('group', '', PARAM_TEXT);
$readerid = optional_param('reader', 0, PARAM_INT);
$type = optional_param('type', '', PARAM_ALPHA);
$type = in_array($type, ['teacher', 'staff'], true) ? $type : '';
$how = optional_param('how', 'press', PARAM_ALPHA);
require_login(null, false);
$library = catalogue::library($ref);
shell::require('local/oksigeniaayvurenda:desk', $library);
// The practice library shows this page only inside its guided help, and nothing is kept or made there.
if (
    !optional_param('help', 0, PARAM_BOOL) || data_submitted() || optional_param('test', 0, PARAM_BOOL)
        || optional_param('file', 0, PARAM_INT)
) {
    shell::no_practice($library);
}
$PAGE->set_context(context_system::instance());
$who = $readerid ? 'reader' : ($group !== '' ? 'group' : ($stage !== '' ? 'stage' : ($type !== '' ? 'type' : 'all')));
$how = in_array($how, cardprint::FORMATS, true) ? $how : 'press';
$state = ['library' => $ref, 'how' => $how] + array_filter(['stage' => $stage, 'group' => $group, 'reader' => $readerid,
    'type' => $type]);
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/cardprint.php', $state));
if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$entityid = (int) $library->entityid;
$str = \local_oksigeniaayvurenda\local\skins::speaker($library);
$manage = access::can('local/oksigeniaayvurenda:manage', $library);
$url = fn(array $params = []) => (new moodle_url('/local/oksigeniaayvurenda/cardprint.php', $params + ['library' => $ref]))
    ->out(false);
// A whole stage or everyone means every photo of the centre: for whoever manages it.
if (in_array($who, ['all', 'stage', 'type'], true) && !$manage) {
    $who = 'pick';
}
$now = time();

// What was made, to take.
$fileid = optional_param('file', 0, PARAM_INT);
if ($fileid) {
    foreach (cardprint::files($entityid) as $file) {
        if ((int) $file->get_id() === $fileid) {
            send_stored_file($file, 0, 0, true);
        }
    }
    shell::missing($str('cardprintgone'), $library);
}
if (optional_param('test', 0, PARAM_BOOL) && cardprint::ready()) {
    $pdf = cardprint::test_sheet($library);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . clean_filename($str('cardprinttestfile')) . '.pdf"');
    echo $pdf;
    exit;
}

$scope = ['who' => $who, 'stage' => $stage, 'group' => $group, 'reader' => $readerid, 'type' => $type];
$people = $who === 'pick' || !cardprint::ready() ? [] : cardprint::people($library, $scope, $now);
$one = $who === 'reader' && $people ? $people[0] : null;
$oneuser = $one && $one->userid ? core_user::get_user((int) $one->userid) : null;
$onename = $one ? ($oneuser ? fullname($oneuser) : trim($one->firstname . ' ' . $one->lastname)) : '';
$typename = $type !== '' ? $str('readerstype' . $type) : '';
$label = ['all' => $str('cardprintall'), 'stage' => $stage, 'group' => $group, 'reader' => $onename, 'type' => $typename,
    'pick' => ''][$who] ?? '';
$notice = '';
if (data_submitted() && confirm_sesskey()) {
    $do = optional_param('do', '', PARAM_ALPHA);
    if ($do === 'back' && $manage) {
        cardprint::save($entityid, ['where' => core_text::substr(trim(optional_param('where', '', PARAM_TEXT)), 0, 200),
            'notes' => core_text::substr(trim(optional_param('notes', '', PARAM_TEXT)), 0, 300)]);
        $notice = $str('cardprintsaved');
    } else if ($do === 'sheet' && $manage) {
        $number = fn(string $name) => (float) str_replace(',', '.', optional_param($name, '0', PARAM_RAW_TRIMMED));
        cardprint::save($entityid, ['left' => $number('left'), 'top' => $number('top'), 'gap' => $number('gap')]);
        $notice = $str('cardprintsaved');
    } else if ($do === 'plastic' && $manage) {
        $number = fn(string $name) => (float) str_replace(',', '.', optional_param($name, '0', PARAM_RAW_TRIMMED));
        cardprint::save($entityid, ['pwidth' => max(40, $number('pwidth')), 'pheight' => max(30, $number('pheight'))]);
        $notice = $str('cardprintsaved');
    } else if ($do === 'model' && $manage) {
        cardprint::choose_model($entityid, optional_param('model', 'other', PARAM_ALPHANUMEXT));
        $notice = $str('cardprintsaved');
    } else if ($do === 'remove') {
        foreach (cardprint::files($entityid) as $file) {
            if ((int) $file->get_id() === optional_param('remove', 0, PARAM_INT)) {
                $file->delete();
                $notice = $str('cardprintremoved');
            }
        }
    } else if ($do === 'design' && $manage) {
        $designs = optional_param_array('design', [], PARAM_ALPHA);
        $chosen = [];
        foreach (optional_param_array('designstage', [], PARAM_TEXT) as $index => $key) {
            $chosen[$key] = (string) ($designs[$index] ?? '');
        }
        cardprint::choose_designs($entityid, $chosen);
        $notice = $str('cardprintsaved');
    } else if ($do === 'logo' && $manage) {
        $kept = cardprint::keep_logo($entityid, 'logo');
        $notice = $kept === null ? '' : $str($kept ? 'cardprintsaved' : 'cardprintlogobad');
    } else if ($do === 'logogone' && $manage) {
        \local_oksigeniaayvurenda\local\images::remove(cardprint::LOGO, $entityid, 'logo');
        $notice = $str('cardprintremoved');
    } else if ($do === 'faces' && $manage) {
        cardprint::save($entityid, ['backs' => optional_param('backs', 0, PARAM_BOOL) ? 1 : 0]);
    } else if ($do === 'make' && $people) {
        if (count($people) <= cardprint::NOW) {
            raise_memory_limit(MEMORY_EXTRA);
            $file = cardprint::make($library, $scope, $how, $label, (int) $USER->id, $now);
            // To the page, not to the file: a download leaves the page as it was, with its button still turning.
            redirect($url($state + ['done' => $file->get_id()]) . '#ayv-cards-ready');
        }
        cardprint::queue($library, $scope, $how, $label, (int) $USER->id);
        $notice = $str('cardprintqueued');
    }
}
$tile = fn(string $title, int $count, array $params, bool $on) => ['title' => $title, 'count' => screens::number($count),
    'url' => $url($params + ['how' => $how]), 'on' => $on];
$whom = [];
if ($manage) {
    $total = count($who === 'all' ? $people : cardprint::people($library, ['who' => 'all'], $now));
    $whom[] = $tile($str('cardprintall'), $total, [], $who === 'all');
    foreach (cardprint::stages($library, $now) as $name => $count) {
        $whom[] = $tile($name, $count, ['stage' => $name], $stage === $name);
    }
    $kinds = \local_oksigeniaayvurenda\local\readers::without_group($library, $now);
    foreach (['teacher', 'staff'] as $kind) {
        if (!empty($kinds[$kind])) {
            $whom[] = $tile($str('readerstype' . $kind), $kinds[$kind], ['type' => $kind], $who === 'type' && $type === $kind);
        }
    }
}
// The classes of the stage chosen; with none, all of them under their stages. Teachers and staff have no class to choose.
$classes = [];
if ($type === '') {
    foreach (\local_oksigeniaayvurenda\local\readers::groups($library, $now) as $row) {
        if ($stage === '' || $row['stage'] === $stage) {
            $classes[$row['stage']] ??= ['stage' => $row['stage'], 'named' => $stage === '' && $row['stage'] !== '', 'rows' => []];
            $classes[$row['stage']]['rows'][] = ['name' => $row['name'], 'selected' => $row['name'] === $group];
        }
    }
}
$own = cardprint::settings($entityid);
$ways = [];
foreach (cardprint::FORMATS as $way) {
    $ways[] = ['title' => $str('cardprint' . $way), 'hint' => $str('cardprint' . $way . 'hint' . ($way === 'sheet' ? 'model' : '')),
        'on' => $how === $way,
        'url' => $url(['how' => $way] + array_filter(['stage' => $stage, 'group' => $group, 'reader' => $readerid,
            'type' => $type])),
        'ispress' => $way === 'press', 'isplastic' => $way === 'plastic', 'issheet' => $way === 'sheet',
        'isplain' => $way === 'plain'];
}
// The designs, each with who gets it, and what the centre can change.
$logourl = \local_oksigeniaayvurenda\local\images::url(cardprint::LOGO, $entityid, 'logo') ?? '';
$map = cardprint::design_map($library, $now);
$whoname = fn(string $key) => $key === cardprint::ADULTS ? $str('cardprintadults') : ($key === cardprint::EVERYONE
    ? $str('cardprintall') : $key);
$samplelang = substr(current_language(), 0, 2) === 'es' ? 'es' : 'en';
$designs = [];
foreach (cardprint::DESIGNS as $design) {
    $designs[] = ['title' => $str('cardprintdesign' . $design),
        'sampleurl' => screens::art('carne/imprenta/muestra/' . $samplelang . '/' . $design)
            ?? screens::art('carne/imprenta/' . $design . '-anverso', 'png'),
        'used' => (bool) $map[$design],
        'whofor' => $map[$design] ? implode(' · ', array_map($whoname, $map[$design])) : $str('cardprintdesignnone')];
}
$chosen = $own['designs'];
$option = fn(string $value, string $title, string $on) => ['value' => $value, 'title' => $title, 'selected' => $value === $on];
$choices = fn(string $none, string $on) => array_merge([$option('', $none, $on)], array_map(
    fn($design) => $option($design, $str('cardprintdesign' . $design), $on),
    cardprint::DESIGNS
));
$designrows = [['key' => cardprint::EVERYONE, 'label' => $str('cardprintdesigneveryone'),
    'options' => $choices($str('cardprintdesignauto'), (string) ($chosen[cardprint::EVERYONE] ?? ''))]];
$stagenames = array_map('strval', array_keys(cardprint::stages($library, $now)));
foreach ($stagenames ? array_merge($stagenames, [cardprint::ADULTS]) : [] as $key) {
    $designrows[] = ['key' => $key, 'label' => $whoname($key),
        'options' => $choices($str('cardprintdesignsame'), (string) ($chosen[$key] ?? ''))];
}
$waiting = cardprint::waiting($library);
$ready = [];
$done = optional_param('done', 0, PARAM_INT);
foreach (cardprint::files($entityid) as $file) {
    $ready[] = ['id' => $file->get_id(), 'name' => $file->get_filename(), 'size' => display_size($file->get_filesize()),
        'fresh' => (int) $file->get_id() === $done,
        'url' => $url($state + ['file' => $file->get_id()]),
        'when' => userdate($file->get_timecreated(), get_string('strftimedatetimeshort', 'langconfig'))];
}
$count = count($people);
$data = [
    'ready' => cardprint::ready(),
    'notready' => $str('cardprintnodesign'),
    'plainurl' => (new moodle_url('/local/oksigeniaayvurenda/print.php', ['library' => $ref, 'group' => $group]))->out(false),
    'plainlabel' => $str('printcards'),
    'heading' => $str('cardprintheading'),
    'intro' => $str('cardprintintro'),
    'notice' => $notice,
    'backurl' => (new moodle_url('/local/oksigeniaayvurenda/readers.php', ['library' => $ref]))->out(false),
    'backlabel' => $str('readers'),
    'backword' => $str('profileback'),
    'whomheading' => $str('cardprintwhom'),
    'designheading' => $str('cardprintdesign'),
    'designhint' => $str($stagenames ? 'cardprintdesignhint' : 'cardprintdesignhintone'),
    'designs' => $designs,
    'designrows' => $designrows,
    'logoheading' => $str('cardprintlogo'),
    'logohint' => $str('cardprintlogohint'),
    'logourl' => $logourl,
    'whom' => $whom,
    'haswhom' => (bool) $whom,
    'manage' => $manage,
    'classes' => array_values($classes),
    'hasclasses' => (bool) $classes,
    'classlabel' => $stage !== '' ? $str('cardprintclassof', $stage) : $str('cardprintclass'),
    'classnone' => $stage !== '' ? $str('cardprintclassall') : $str('cardprintclasspick'),
    'stage' => $stage,
    'action' => (new moodle_url('/local/oksigeniaayvurenda/cardprint.php'))->out(false),
    'library' => $ref,
    'how' => $how,
    'onereader' => $onename,
    'onehint' => $str('cardprintonehint'),
    'waysheading' => $str('cardprintways'),
    'ways' => $ways,
    'sheet' => $how === 'sheet' ? ['left' => $own['left'], 'top' => $own['top'], 'gap' => $own['gap'],
        'testurl' => $url($state + ['test' => 1]), 'canset' => $manage,
        'models' => array_map(fn($key, $model) => ['value' => $key, 'label' => $key === 'other' ? $str('cardprintmodelother')
            : $model['name'] . ' · ' . str_replace('.', ',', (string) $model['width']) . ' × ' . $model['height'] . ' mm',
            'selected' => $key === $own['model']], array_keys(cardprint::MODELS), cardprint::MODELS)] : null,
    'modellabel' => $str('cardprintmodel'),
    'sheetheading' => $str('cardprintsheetheading'),
    'sheethint' => $str('cardprintsheethint'),
    'sheetleft' => $str('cardprintsheetleft'),
    'sheettop' => $str('cardprintsheettop'),
    'sheetgap' => $str('cardprintsheetgap'),
    'sheettest' => $str('cardprintsheettest'),
    'save' => $str('profilesave'),
    'pageurl' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
    'where' => $own['where'],
    'notes' => $own['notes'],
    'hasback' => $own['where'] !== '' || $own['notes'] !== '',
    'backheading' => $str('cardprintback'),
    'backhint' => $str('cardprintbackhint'),
    'backempty' => $str('cardprintbackempty'),
    'backchange' => $str('cardprintbackchange'),
    'wherelabel' => $str('cardprintwhere'),
    'noteslabel' => $str('cardprintnotes'),
    'close' => $str('selfclose'),
    'cando' => $count > 0,
    'makelabel' => $count === 1 ? $str('cardprintmakeone') : $str('cardprintmake', screens::number($count)),
    'makebusy' => $str('cardprintbusy'),
    'makebusyhint' => $str('cardprintbusyhint'),
    'makehint' => $count > cardprint::NOW ? $str('cardprintlong') : '',
    'doneword' => in_array(true, array_column($ready, 'fresh'), true) ? $str('cardprintdone') : '',
    'nobody' => $who === 'pick' ? $str('cardprintpick') : ($count ? '' : $str('readersnone')),
    'readyheading' => $str('cardprintready'),
    'readyhint' => $str('cardprintreadyhint', cardprint::DAYS),
    'removelabel' => $str('cardprintremove'),
    'files' => $ready,
    'hasfiles' => (bool) $ready,
    'waiting' => $waiting ? $str('cardprintwaiting') : '',
    'download' => $str('copydownload'),
    'plastic' => $how === 'plastic' ? ['width' => $own['pwidth'], 'height' => $own['pheight'], 'canset' => $manage] : null,
    'plasticheading' => $str('cardprintplasticheading'),
    'plasticwidth' => $str('cardprintplasticwidth'),
    'plasticheight' => $str('cardprintplasticheight'),
    'faces' => in_array($how, ['press', 'plastic'], true) ? ['backson' => !empty($own['backs']), 'canset' => $manage] : null,
    'faceslabel' => $str('cardprintbacks'),
    'faceshint' => $str('cardprintbackshint'),
];
shell::output(
    $data['heading'],
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/cardprint', $data),
    $library,
    '',
    ['search' => false, 'nav' => optional_param('help', 0, PARAM_BOOL) ? [] : [['url' => (new moodle_url(
        '/local/oksigeniaayvurenda/help.php',
        ['library' => $library->slug, 'piece' => 'cardprint']
    ))->out(false), 'label' => get_string('helpnav', 'local_oksigeniaayvurenda')]]]
);
