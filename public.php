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

// The frame of the newest books on a website opens no session of Moodle at each visit: nobody signs in inside it, and
// a busy home page would fill the sessions table (seg-anon 06).
define('NO_MOODLE_COOKIES', !empty($_GET['embed']));
// phpcs:ignore moodle.Files.RequireLogin.Missing -- the window is for everyone; managing it checks the capability.
require(__DIR__ . '/../../config.php');

$ref = \local_oksigeniaayvurenda\local\shell::library_ref();
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
        s($str('windowclosedhint')) . '</p>' . ($library && \local_oksigeniaayvurenda\local\onlylibrary::on()
            ? '<p><a class="ayv-button" href="' . \local_oksigeniaayvurenda\local\onlylibrary::entry()->out() . '">'
            . s($str('entrargo')) . '</a></p>' : ''), null, '', ['search' => false]);
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
    'door' => ['name' => format_string($library->name, true, ['escape' => false]), 'motto' => format_string(
        $look['motto'],
        true,
        ['escape' => false]
    ),
        'colour' => $look['colour'], 'image' => $look['image'] ?? screens::art('portada'), 'kicker' => $str('windowkicker')],
    'notice' => $notice,
    'closednote' => !$settings['open'] ? $str('windowclosednote') : '',
    'query' => $query,
    'slug' => $library->slug,
    'action' => $here(),
    'manage' => $manage,
    'sesskey' => sesskey(),
    // The staff's own choice, kept for when the centre opens.
    'open' => window::settings($library, true)['open'],
    'voices' => $settings['voices'],
    'index' => $settings['index'],
    'catalogue' => $catalogue,
    'publicurl' => $here(),
    'oaiurl' => \local_oksigeniaayvurenda\local\oai::base($library),
    'sruurl' => $catalogue ? \local_oksigeniaayvurenda\local\sru::base($library) : '',
    'opds' => $opds,
    'loginurl' => (new moodle_url('/local/oksigeniaayvurenda/index.php', ['library' => $library->slug]))->out(false),
    'searchlabel' => $str($catalogue ? 'windowsearchall' : 'windowsearch'),
    'embedcode' => $catalogue ? '<iframe src="' . s($here(['embed' => 1])) . '" title="' . s(format_string(
        $library->name,
        true,
        ['escape' => false]
    )) .
        '" width="100%" height="390" style="border: 0" loading="lazy"></iframe>' : '',
];
foreach (
    ['windowsearchbutton', 'windowdownload', 'windowopds', 'windowopdshint', 'windowlogin', 'windownext',
        'windowprev', 'windowmanage', 'windowopen', 'windowopenhint', 'windowtext', 'windowtexthint', 'windowvoices',
        'windowindex', 'windowsave', 'windowaddress', 'voiceinvitecopy', 'windowoai', 'windowoaihint', 'windowsru',
        'windowsruhint', 'windowcatalogue',
        'windowcataloguehint', 'windowbackdoor', 'windowembed', 'windowembedhint'] as $key
) {
    $shared[$key] = $str($key);
}

// A record, as anyone may see it: what it is, whether it is free and where it stands, and the books beside it.
if ($recordid) {
    $found = $catalogue ? catalogue::record($recordid, $library) : null;
    // Only the copies of the libraries that open their window: a classroom or a textbook bank that keeps it shut says
    // nothing of its shelves to anyone without an account (seg-anon 05).
    if ($found) {
        $open = window::open_libraries((int) $library->entityid);
        $found['copies'] = array_values(array_filter($found['copies'], fn($copy) => in_array($copy['libraryid'], $open, true)));
        $found = $found['copies'] || window::shows_record($recordid) ? $found : null;
    }
    if (!$found) {
        // A shared link or a poster's code of a book no longer here: the memory says so, with the way to the door
        // (jue-humor D2), as a page of the library with an account does.
        shell::missing($str('recordnotfound'), $library, [$here(), $str('windowbackdoor')], 'notheresaidbook');
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
        'description' => trim(implode(' · ', array_filter([$data['card']['authors'] ?? '', format_string(
            $library->name,
            true,
            ['escape' => false]
        )])) .
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

// Tales to read at home, by age (the families' library, part 1; docs/24), as Gaudí drew them: what the library looked at
// and offers to read together, for anyone, also for a family that has not joined the centre yet. Read aloud by a
// grown-up, never «Listen». The age comes in years (Eguahe's link, age=4) or as a band (age=3-5), also as edad.
if (optional_param('tales', 0, PARAM_BOOL)) {
    $asked = trim(optional_param('age', optional_param('edad', '', PARAM_RAW_TRIMMED), PARAM_RAW_TRIMMED));
    $years = preg_match('/^(\d{1,2})(?:-\d{1,2})?$/', $asked, $m) ? (int) $m[1] : -1;
    $band = $years >= 0 && $years <= 12 ? window::band($years) : null;
    $bandid = fn(int $from) => $from . '-' . ($from === 9 ? 12 : $from + 2);
    $entityid = (int) $library->entityid;
    $skin = \local_oksigeniaayvurenda\local\skins::of($entityid);
    $say = fn(string $key, $a = null) => \local_oksigeniaayvurenda\local\skins::say($entityid, $key, $a);
    $found = $band !== null ? window::records($library, '', 0, $band) : ['records' => []];
    $tales = [];
    foreach ($found['records'] as $record) {
        $item = reset($record['items']);
        $lines = json_decode((string) $item->homelines, true) ?: [];
        $parts = (int) $item->homeparts;
        $tales[] = [
            'order' => (int) ($item->homeorder ?: 1000),
            'length' => (int) ($item->homeminutes ?: 999),
            'title' => $record['title'],
            'cover' => (string) $record['cover'],
            'hascover' => (bool) $record['cover'],
            'colour' => $record['id'] % 6,
            // An e-book opens in the story reader (big letters, night, one page at a time); anything else, in the library's.
            'url' => (new moodle_url(
                '/local/oksigeniaayvurenda/' . ($item->format === 'epub' ? 'tale.php' : 'read.php'),
                ['library' => $library->slug, 'id' => $item->id] + ($item->format === 'epub' ? ['age' => $bandid($band)] : [])
            ))
                ->out(false),
            'minutes' => $item->homeminutes ? $str('talesminutes', (int) $item->homeminutes) : '',
            'parts' => !$parts ? '' : $str(($band === 9 ? 'taleschapters' : 'talespages') . ($parts === 1 ? 'one' : ''), $parts),
            'why' => (string) ($lines[(string) $band] ?? ''),
            'night' => !empty($item->homenight),
            // Only a recording the work brings, as an audio of its own record; never a made voice.
            'audio' => (bool) $DB->record_exists_select(
                'local_oksigeniaayvurenda_digital',
                "recordid = ? AND format = 'audio' AND kind <> 'voice' AND status = 'published'",
                [$record['id']]
            ),
        ];
    }
    // The order the library chose; without it, the shortest first.
    usort($tales, fn($a, $b) => [$a['order'], $a['length'], $a['title']] <=> [$b['order'], $b['length'], $b['title']]);
    $here = fn(array $params = []) => (new moodle_url('/local/oksigeniaayvurenda/public.php', ['library' => $library->slug] +
        $params))->out(false);
    // Who speaks, by skin (Gaudí): the memory whole and reading where the little ones are; a portrait at a secondary
    // school or a university; at a company, the library itself. A centre's own memory, as it drew her.
    $memory = \local_oksigeniaayvurenda\local\characters::of($entityid)['memory'] ?? null;
    $speaks = $memory && $memory['enabled'] && $skin !== 'company';
    $whole = $speaks && in_array($skin, ['school', 'public', 'mobile'], true);
    $own = $speaks && !empty($memory['own']);
    $figure = !$whole ? null : ($own ? \local_oksigeniaayvurenda\local\characters::own_figure($entityid, 'memory', 'lee')
        : (screens::art('personajes/recorte/memoria-lee') ?? \local_oksigeniaayvurenda\local\characters::figure('memory', 'lee')));
    $portrait = !$speaks || $whole ? null : ($own ? \local_oksigeniaayvurenda\local\images::url('portrait', $entityid, 'memory')
        : screens::art('personajes/recorte/' . ($skin === 'secondary' ? 'retrato-cielo' : 'retrato')));
    $tips = ['0' => ['sofa', 'voice', 'repeat'], '3' => ['moon', 'page', 'voice'], '6' => ['page', 'ask', 'moon'],
        '9' => ['bookmark', 'page', 'chat']];
    $next = $band === null ? null : window::AGES[(array_search($band, window::AGES, true) + 1) % count(window::AGES)];
    $stops = [];
    if ($skin === 'mobile') {
        $coming = \local_oksigeniaayvurenda\local\agenda::between($entityid, time(), time() + 30 * DAYSECS);
        foreach (array_slice($coming, 0, 4) as $stop) {
            $stops[] = ['when' => \local_oksigeniaayvurenda\local\agenda::when($stop, $library),
                'where' => (string) ($stop->place ?: $stop->title)];
        }
    }
    $data = [
        'pageclass' => ($band === null ? ' is-choosing' : '') . ($band !== null && $band <= 3 ? ' is-big' : ''),
        'heading' => $str('talesheading'),
        'lead' => $say('talesintro'),
        'figure' => (string) $figure,
        'hasfigure' => (bool) $figure,
        'say' => ['text' => $str($band === null ? 'talesmemorychoose' : 'talesmemory' . $band),
            'who' => $speaks ? format_string($memory['name'], true, ['escape' => false]) : $str('talesbylibrary'),
            'portrait' => $portrait, 'plain' => !$speaks],
        'agesaria' => $str('talesagesaria'),
        'choosetitle' => $str('talesagechoose'),
        'unit' => $str('talesunit'),
        'ages' => array_map(fn($from) => ['id' => $bandid($from), 'url' => $here(['tales' => 1, 'age' => $bandid($from)]),
            'current' => $band === $from, 'tab' => $from . '–' . ($from === 9 ? 12 : $from + 2),
            'hint' => $str('talesagehint' . $from)], window::AGES),
        'chosen' => $band !== null,
        'list' => $band === null ? null : [
            'heading' => $str('talesfor', $str('talesage' . $band)),
            'count' => count($tales) === 1 ? $str('talescountone') : $str('talescount', count($tales)),
            'tales' => $tales,
            'hastales' => (bool) $tales,
            // Four in rows of three leave one alone (Gaudí): rows of two, or of four on a very wide screen.
            'four' => count($tales) === 4,
            'empty' => $tales ? null : [
                'face' => $whole && !$own ? screens::art('personajes/recorte/memoria-piensa') : null,
                'title' => $str('talesemptytitle'),
                'text' => $str('talesemptytext') . ($manage ? ' ' . $str('talesemptystaff') : ''),
                'nexturl' => $here(['tales' => 1, 'age' => $bandid($next)]),
                'nextid' => $bandid($next),
                'nextlabel' => $str('talesemptynext', $str('talesage' . $next)),
            ],
        ],
        'together' => $band === null ? null : [
            'heading' => $str('talestogether'),
            'tips' => array_map(fn($n, $icon) => ['icon' => $icon, 'title' => $str('talestip' . $band . $n . 'title'),
                'text' => $str('talestip' . $band . $n . 'text')], [1, 2, 3], $tips[(string) $band]),
        ],
        'foot' => [
            'picture' => screens::skin_art($entityid),
            'name' => $shared['door']['name'],
            'emblem' => (new moodle_url('/local/oksigeniaayvurenda/app/emblema-pequeno.svg'))->out(false),
            'lines' => [],
            'stops' => $stops,
            'stopsheading' => $str('talesstops'),
            'catalogue' => $here(),
            'cataloguelabel' => $str('talescatalogue'),
        ],
        'readlabel' => $str('talesread'),
        'nightlabel' => $str('digitalhomenight'),
        'audiolabel' => $str('talesaudio'),
    ];
    $talesurl = $here(['tales' => 1] + ($band !== null ? ['age' => $bandid($band)] : []));
    shell::output($str('talesheading'), $OUTPUT->render_from_template('local_oksigeniaayvurenda/tales', $data), $library, '', [
        'search' => false,
        'index' => $settings['open'] && $settings['index'],
        'styles' => ['cuentos'],
        'scripts' => ['tales'],
        'share' => $settings['open'] ? ['title' => $str('talesheading') . ' · ' . $shared['door']['name'], 'url' => $talesurl,
            'image' => $shared['door']['image'], 'description' => $say('talesintro')] : null,
    ]);
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
        'nexturl' => $found['reachable'] > ($page + 1) * catalogue::PERPAGE ? $here(['q' => $query, 'page' => $page + 1]) : '',
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
    if ($oldest = window::oldest($library)) {
        $data['pulse'][] = ['text' => $str('windowpulseoldest', $oldest)];
    }
    $count = fn(int $n) => $str($n === 1 ? 'onebook' : 'books', \local_oksigeniaayvurenda\local\screens::number($n));
    $stacksurl = fn(array $params) => (new moodle_url('/local/oksigeniaayvurenda/stacks.php', ['library' => $library->slug] +
        $params))->out(false);
    $places = stacks::places($library);
    foreach (count($places) > 1 ? $places : [] as $place) {
        $data['places'][] = ['label' => $place['id'] ? $place['label'] : $str('stacksnoplace'),
            'countlabel' => $count($place['count']), 'url' => $stacksurl(['place' => $place['id']]),
            'colour' => $place['colour'], 'hascolour' => $place['colour'] !== null];
    }
    foreach (catalogue::corners($library, false, false) as $corner) {
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
    // What is coming at the library, for anyone at the door: the next four activities in a month.
    'agenda' => array_map(fn($a) => ['when' => \local_oksigeniaayvurenda\local\agenda::when($a, $library),
        'title' => format_string($a->title, true, ['escape' => false]), 'place' => (string) $a->place,
        'audience' => (string) $a->audience], array_slice(\local_oksigeniaayvurenda\local\agenda::between(
            (int) $library->entityid,
            time(),
            time() + 30 * DAYSECS
        ), 0, 4)),
    'agendaheading' => $str('agendasoon'),
    // Tales to read at home, when the library offers some.
    'tales' => window::tale_ages($library) ? ['url' => $here(['tales' => 1]), 'title' => $str('talesheading'),
        'hint' => $str('talesdoorhint')] : null,
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
