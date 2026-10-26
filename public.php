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
 * The public window of a library, without an account: its door. The library's own picture, colour and words; what is
 * moving in it today (never who); its places and corners as doors to the shelves, what came in last and the catalogue
 * with what is free, when the centre shows them; and what it has for everyone to read, hear or download. Whoever
 * manages the library opens it, closes it and says what it shows from the same page.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\core\digital_rules;
use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\digital;
use local_oksigeniaayvurenda\local\libraries;
use local_oksigeniaayvurenda\local\screens;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\stacks;
use local_oksigeniaayvurenda\local\window;

// phpcs:ignore moodle.Files.RequireLogin.Missing -- the window is for everyone; managing it checks the capability.
require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
$query = trim(optional_param('q', '', PARAM_TEXT));
$page = max(0, optional_param('page', 0, PARAM_INT));
$recordid = optional_param('record', 0, PARAM_INT);
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/public.php', ['library' => $ref]));
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);

$library = catalogue::library($ref);
$manage = $library && isloggedin() && !isguestuser() && access::can('local/oksigeniaayvurenda:manage', $library);
$notice = '';
if ($manage && data_submitted() && confirm_sesskey()) {
    window::save($library, ['open' => optional_param('open', 0, PARAM_BOOL), 'text' => optional_param('text', '', PARAM_TEXT),
        'voices' => optional_param('voices', 0, PARAM_BOOL), 'index' => optional_param('index', 0, PARAM_BOOL),
        'catalogue' => optional_param('catalogue', 0, PARAM_BOOL)]);
    $notice = $str('windowsaved');
}
$settings = $library ? window::settings($library) : null;
if (!$library || (!$settings['open'] && !$manage)) {
    http_response_code(404);
    shell::output($str('windowclosed'), '<h1 class="ayv-results-title">' . s($str('windowclosed')) . '</h1><p>' .
        s($str('windowclosedhint')) . '</p>', null, '', ['search' => false]);
    die;
}
// The catalogue and the shelves, for anyone when the centre shows them; whoever manages it sees how they will look.
$catalogue = $settings['catalogue'];
// The newest books as a shelf for the school's website, in a frame.
if (optional_param('embed', 0, PARAM_BOOL) && window::catalogue_open($library)) {
    shell::embed($library);
    die;
}
$here = fn(array $params = []) => (new moodle_url('/local/oksigeniaayvurenda/public.php', ['library' => $library->slug] +
    $params))->out(false);
$public = function (array $cards) use ($here, $str) {
    foreach ($cards as &$card) {
        $card['url'] = $here(['record' => $card['id']]);
        $card['statelabel'] = $str('state' . $card['state']);
        $card['stateclass'] = $card['state'];
        if ($card['state'] !== 'free') {
            $card['location'] = null;
        }
    }
    return $cards;
};
$opds = (new moodle_url('/local/oksigeniaayvurenda/opds.php', ['library' => $library->slug]))->out(false);
$look = libraries::look($library);
$shared = [
    'door' => ['name' => format_string($library->name), 'motto' => format_string($look['motto']),
        'colour' => $look['colour'], 'image' => $look['image'] ?? screens::art('portada'), 'kicker' => $str('windowkicker')],
    'notice' => $notice,
    'closednote' => !$settings['open'] ? $str('windowclosednote') : '',
    'query' => $query,
    'slug' => $library->slug,
    'action' => $here(),
    'manage' => $manage,
    'sesskey' => sesskey(),
    'open' => $settings['open'],
    'voices' => $settings['voices'],
    'index' => $settings['index'],
    'catalogue' => $catalogue,
    'publicurl' => $here(),
    'oaiurl' => \local_oksigeniaayvurenda\local\oai::base($library),
    'opds' => $opds,
    'loginurl' => (new moodle_url('/local/oksigeniaayvurenda/index.php', ['library' => $library->slug]))->out(false),
    'searchlabel' => $str($catalogue ? 'windowsearchall' : 'windowsearch'),
    'embedcode' => $catalogue ? '<iframe src="' . s($here(['embed' => 1])) . '" title="' . s(format_string($library->name)) .
        '" width="100%" height="390" style="border: 0" loading="lazy"></iframe>' : '',
];
foreach (
    ['windowsearchbutton', 'windowdownload', 'windowopds', 'windowopdshint', 'windowlogin', 'windownext',
        'windowprev', 'windowmanage', 'windowopen', 'windowopenhint', 'windowtext', 'windowtexthint', 'windowvoices',
        'windowindex', 'windowsave', 'windowaddress', 'voiceinvitecopy', 'windowoai', 'windowoaihint', 'windowcatalogue',
        'windowcataloguehint', 'windowbackdoor', 'windowembed', 'windowembedhint'] as $key
) {
    $shared[$key] = $str($key);
}

// A record, as anyone may see it: what it is, whether it is free and where it stands, and the books beside it.
if ($recordid) {
    $found = $catalogue ? catalogue::record($recordid, $library) : null;
    if (!$found) {
        http_response_code(404);
        shell::output($str('recordnotfound'), '<h1 class="ayv-results-title">' . s($str('recordnotfound')) . '</h1><p><a href="' .
            s($here()) . '">' . s($str('windowbackdoor')) . '</a></p>', $library, '', ['search' => false]);
        die;
    }
    $data = screens::record($found, $library);
    $data['card'] = $public([$found['card']])[0];
    $data['cite'] = null;
    foreach ($data['subjects'] as &$subject) {
        $subject['url'] = $here(['q' => $subject['label']]);
    }
    unset($subject);
    $data['back'] = ['url' => $here(), 'label' => $str('windowbackdoor')];
    $data['where'] = screens::where($library, $recordid);
    $beside = stacks::neighbours($library, $recordid);
    $data['beside'] = $beside && count($beside['spines']) > 1 ? [
        'heading' => $str('stacksbeside'),
        'spines' => screens::spines(screens::public_spines($beside['spines'], $library)),
        'walk' => $str('stackswalkhere'),
        'walkurl' => (new moodle_url('/local/oksigeniaayvurenda/stacks.php', ['library' => $library->slug,
            'place' => $beside['place'], 'at' => $recordid]))->out(false),
        'bird' => screens::art('estanteria/mainumby', 'svg'),
        'bookend' => screens::art('estanteria/sujetalibros', 'svg'),
    ] : null;
    $share = ['title' => $found['record']->title, 'url' => $here(['record' => $recordid]),
        'description' => trim(implode(' · ', array_filter([$data['card']['authors'] ?? '', format_string($library->name)])) .
            ($found['record']->summary ? '. ' . $found['record']->summary : '')),
        'image' => \local_oksigeniaayvurenda\local\covers::url($recordid)];
    shell::output(
        $found['record']->title,
        $OUTPUT->render_from_template('local_oksigeniaayvurenda/record', $data),
        $library,
        '',
        ['search' => false, 'index' => $settings['open'] && $settings['index'], 'scripts' => $data['beside'] ? ['stacks'] : [],
            'share' => $settings['open'] ? $share : null]
    );
    die;
}

$data = $shared + [
    'heading' => $str('windowheading'),
    'text' => $settings['text'],
    'pulse' => [],
    'places' => [],
    'corners' => [],
    'cards' => [],
    'results' => null,
    'records' => [],
    'visitcard' => null,
];
if ($catalogue && $query !== '') {
    // Searching the catalogue: what there is, whether it is free and where.
    $found = catalogue::search($library, $query, $page);
    $data['results'] = [
        'heading' => $str('windowresults', $query),
        'cards' => $public($found['cards']),
        'hascards' => (bool) $found['cards'],
        'empty' => $str('windownothingcatalogue'),
        'nexturl' => $found['total'] > ($page + 1) * catalogue::PERPAGE ? $here(['q' => $query, 'page' => $page + 1]) : '',
        'prevurl' => $page > 0 ? $here(['q' => $query, 'page' => $page - 1]) : '',
    ];
} else if ($catalogue) {
    $pulse = window::pulse($library);
    if ($pulse['today']) {
        $data['pulse'][] = ['text' => $str($pulse['today'] === 1 ? 'windowpulsetodayone' : 'windowpulsetoday', $pulse['today'])];
    }
    if ($pulse['corner'] !== '') {
        $data['pulse'][] = ['text' => $str('windowpulsecorner', $pulse['corner'])];
    }
    if ($pulse['new']) {
        $data['pulse'][] = ['text' => $str($pulse['new'] === 1 ? 'windowpulsenewone' : 'windowpulsenew', $pulse['new'])];
    }
    $count = fn(int $n) => $str($n === 1 ? 'onebook' : 'books', $n);
    $stacksurl = fn(array $params) => (new moodle_url('/local/oksigeniaayvurenda/stacks.php', ['library' => $library->slug] +
        $params))->out(false);
    $places = stacks::places($library);
    foreach (count($places) > 1 ? $places : [] as $place) {
        $data['places'][] = ['label' => $place['id'] ? $place['label'] : $str('stacksnoplace'),
            'countlabel' => $count($place['count']), 'url' => $stacksurl(['place' => $place['id']]),
            'colour' => $place['colour'], 'hascolour' => $place['colour'] !== null];
    }
    foreach (catalogue::corners($library) as $corner) {
        $data['corners'][] = ['label' => $corner['label'], 'countlabel' => $count($corner['count']),
            'url' => $stacksurl(['corner' => $corner['code']]), 'colour' => $corner['colour'],
            'art' => screens::art('rincon-' . $corner['code'])];
    }
    $data['cards'] = $public(catalogue::recent($library));
    $data['visitcard'] = \local_oksigeniaayvurenda\local\visit::card($library, true);
}
// What the window has to read, hear or download, as always: below the catalogue, or alone without it.
$found = window::records($library, $catalogue ? '' : $query, $page);
foreach ($found['records'] as $record) {
    $items = [];
    foreach ($record['items'] as $item) {
        $label = $item->kind === 'voice' ? $str('windowvoice') : $str('format' . $item->format);
        $items[] = [
            'label' => $label . ($item->title !== '' && $item->title !== null ? ' · ' . $item->title : ''),
            'licence' => $str('licence' . str_replace('-', '', $item->licence)),
            'action' => $str('digitalaction' . $item->format),
            'url' => (new moodle_url('/local/oksigeniaayvurenda/read.php', ['library' => $library->slug, 'id' => $item->id]))
                ->out(false),
            'download' => $item->kind === 'file' && digital_rules::can_download($item->licence, (bool) $item->download)
                ? digital::file_url($item, true) : '',
        ];
    }
    $data['records'][] = [
        'title' => $record['title'],
        'subtitle' => $record['subtitle'],
        'authors' => implode(', ', $record['authors']),
        'cover' => $record['cover'],
        'hascover' => (bool) $record['cover'],
        'colour' => $record['id'] % 6,
        'items' => $items,
    ];
}
$data += [
    'haspulse' => (bool) $data['pulse'],
    'hasdoors' => $data['places'] || $data['corners'],
    'hascards' => (bool) $data['cards'],
    'hasrecords' => (bool) $data['records'],
    'showdigital' => $data['records'] || !$catalogue,
    'empty' => $str($query !== '' && !$catalogue ? 'windownothingfound' : 'windowempty'),
    'nexturl' => !$catalogue && $found['more'] ? $here(['q' => $query, 'page' => $page + 1]) : '',
    'prevurl' => !$catalogue && $page > 0 ? $here(['q' => $query, 'page' => $page - 1]) : '',
    'doorsheading' => $str('windowdoors'),
    'stacksplaces' => $str('stacksplaces'),
    'stackscorners' => $str('stackscorners'),
    'recentheading' => $str('windowrecent'),
    'digitalheading' => $str('windowdigital'),
    'shelfprev' => $str('shelfprev'),
    'shelfnext' => $str('shelfnext'),
];
shell::output($data['door']['name'], $OUTPUT->render_from_template('local_oksigeniaayvurenda/public', $data), $library, '', [
    'search' => false,
    'index' => $settings['open'] && $settings['index'],
    'feed' => $settings['open'] ? $opds : '',
    'share' => $settings['open'] ? ['title' => $data['door']['name'], 'url' => $here(), 'image' => $data['door']['image'],
        'description' => $data['door']['motto'] !== '' ? $data['door']['motto'] : $settings['text']] : null,
    'scripts' => $manage ? ['voice'] : [],
    'config' => ['strings' => ['copied' => $str('voiceinvitecopied')]],
]);
