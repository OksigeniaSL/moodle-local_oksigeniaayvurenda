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
 * The library's challenges with their own dates: each in a card with how it goes, made and changed in its window.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\cartilla;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\quests;
use local_oksigeniaayvurenda\local\screens;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\zones;

require(__DIR__ . '/../../config.php');

$ref = shell::library_ref();
require_login(null, false);
$library = catalogue::library($ref);
$context = access::context($library);
shell::require('local/oksigeniaayvurenda:manage', $library);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/quests.php', ['library' => $ref]));
if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$now = time();
$notice = '';
$problem = '';
if (data_submitted() && confirm_sesskey()) {
    $do = optional_param('do', '', PARAM_ALPHA);
    if ($do === 'save') {
        $saved = quests::save($library, [
            'name' => optional_param('name', '', PARAM_TEXT),
            'text' => optional_param('text', '', PARAM_TEXT),
            'start' => optional_param('start', '', PARAM_TEXT),
            'end' => optional_param('end', '', PARAM_TEXT),
            'goal' => optional_param('goal', 0, PARAM_INT),
            'stages' => optional_param_array('stages', [], PARAM_TEXT),
        ], $now, optional_param('id', 0, PARAM_INT));
        if (is_int($saved)) {
            $notice = $str('questsaved');
        } else {
            $problem = $str($saved);
        }
    } else if ($do === 'remove') {
        quests::remove($library, required_param('id', PARAM_INT));
        $notice = $str('questremoved');
    } else if ($do === 'newcartilla') {
        // A cartilla starts as a draft on the table: its nine places empty, four weeks from today, for every stage but
        // the youngest.
        $id = quests::save($library, ['name' => $str('cartillanewname'), 'start' => zones::date($now, '%Y-%m-%d', $library),
            'end' => zones::date($now + 27 * DAYSECS, '%Y-%m-%d', $library), 'squares' => array_fill(0, cartilla::SIZE, ''),
            'stages' => array_values(array_filter(quests::stages($library, $now), fn($s) => !cartilla::infant($s)))], $now);
        if (is_int($id)) {
            redirect(new moodle_url($PAGE->url, ['cartilla' => $id], 'rt-' . $id));
        }
        $problem = $str($id);
    } else if ($do === 'cartilla') {
        // One gesture on the table: a square in or out, shuffled or filled, a day, a length, a stage, the name or the line.
        $id = required_param('id', PARAM_INT);
        $quest = $DB->get_record('local_oksigeniaayvurenda_quest', ['id' => $id, 'libraryid' => $library->id], '*', MUST_EXIST);
        $squares = cartilla::squares($quest) ?: array_fill(0, cartilla::SIZE, '');
        foreach (optional_param_array('squares', [], PARAM_RAW_TRIMMED) as $at => $spec) {
            if ((int) $at >= 0 && (int) $at < cartilla::SIZE) {
                $squares[(int) $at] = cartilla::valid((string) $spec) ? (string) $spec : '';
            }
        }
        if (optional_param('shuffle', 0, PARAM_BOOL)) {
            shuffle($squares);
        }
        if (optional_param('fill', 0, PARAM_BOOL)) {
            $squares = cartilla::fill($squares, cartilla::options($library, $str, $now));
        }
        $start = (int) $quest->timestart;
        $end = (int) $quest->timeend;
        $start += ['menos' => -DAYSECS, 'mas' => DAYSECS][optional_param('start', '', PARAM_ALPHA)] ?? 0;
        $end += ['menos' => -DAYSECS, 'mas' => DAYSECS][optional_param('end', '', PARAM_ALPHA)] ?? 0;
        $length = optional_param('length', '', PARAM_ALPHANUM);
        if ($length === 'term') {
            $year = \local_oksigeniaayvurenda\local\years::around((int) $library->entityid, $start);
            $end = \local_oksigeniaayvurenda\local\challenges::bounds(
                $year,
                \local_oksigeniaayvurenda\local\challenges::term($year, $start)
            )[1] - 1;
        } else if (in_array($length, ['7', '14', '28'], true)) {
            $end = $start + ((int) $length - 1) * DAYSECS;
        }
        $known = quests::stages($library, $now);
        $for = json_decode((string) $quest->stages, true) ?: $known;
        // By its place in the list: a stage's name is not a key a form may send.
        foreach (optional_param_array('stages', [], PARAM_INT) as $index => $on) {
            $stage = $known[(int) $index] ?? null;
            if ($stage !== null) {
                $for = $on ? array_values(array_unique(array_merge($for, [$stage]))) : array_values(array_diff($for, [$stage]));
            }
        }
        $saved = quests::save($library, [
            'name' => optional_param('name', (string) $quest->name, PARAM_TEXT),
            'text' => optional_param('text', (string) $quest->text, PARAM_TEXT),
            'start' => zones::date($start, '%Y-%m-%d', $library),
            'end' => zones::date(max($end, $start), '%Y-%m-%d', $library),
            'squares' => $squares,
            'stages' => $for,
        ], $now, $id);
        if (is_int($saved)) {
            redirect(new moodle_url($PAGE->url, ['cartilla' => $id, 'saved' => 1], 'rt-' . $id));
        }
        $problem = $str($saved);
    }
}
$stages = quests::stages($library, $now);
$date = fn(int $time, string $format) => zones::date($time, $format, $library);
$long = 'strftimedate';
// A cartilla on the list: its card small (only its drawings), its sums without names, and the square filled the least.
$options = null;
$box = function () use (&$options, $library, $str, $now) {
    $options ??= cartilla::options($library, $str, $now);
    return $options;
};
$art = fn(string $spec) => array_column($box(), 'art', 'spec')[$spec] ?? null;
$card = function (\stdClass $quest) use ($library, $str, $now, $art) {
    $stats = cartilla::stats($quest, $library, $now, $str);
    return [
        'mini' => array_map(
            fn($spec) => ['art' => $spec !== '' ? $art($spec) : null, 'empty' => $spec === ''],
            cartilla::squares($quest)
        ),
        'draft' => !cartilla::complete($quest),
        'sums' => $str('cartillaplayers', (object) ['players' => screens::number($stats['players']),
            'lines' => screens::number($stats['lines']), 'full' => screens::number($stats['full'])]),
        'hardest' => $stats['hardest'] ? $str('cartillahardest', (object) $stats['hardest']) : '',
        'url' => (new moodle_url('/local/oksigeniaayvurenda/quests.php', ['library' => $library->slug,
            'cartilla' => $quest->id], 'rt-' . $quest->id))->out(false),
    ];
};
$cards = [];
foreach (quests::of($library) as $quest) {
    $totals = quests::totals($quest, $now);
    $for = json_decode((string) $quest->stages, true) ?: [];
    $state = $quest->timestart > $now ? 'soon' : ($quest->timeend < $now ? 'over' : 'on');
    // A cartilla without its nine squares is a draft, whatever its dates say: it shows on no card.
    $state = cartilla::squares($quest) && !cartilla::complete($quest) ? 'draft' : $state;
    $cards[] = [
        'id' => (int) $quest->id,
        'name' => format_string($quest->name, true, ['escape' => false]),
        'text' => (string) $quest->text,
        'when' => $str('questwhen', (object) ['from' => $date((int) $quest->timestart, $long),
            'to' => $date((int) $quest->timeend, $long)]),
        'for' => $for ? implode(', ', $for) : $str('questforall'),
        'goal' => (int) $quest->goal,
        'goalline' => (int) $quest->goal === 1 ? $str('questgoallineone') : $str('questgoalline', (int) $quest->goal),
        'state' => $str('queststate' . $state), 'stateclass' => $state,
        'readers' => screens::number($totals['readers']),
        'done' => screens::number($totals['done']),
        'books' => screens::number($totals['books']),
        'startvalue' => $date((int) $quest->timestart, '%Y-%m-%d'),
        'endvalue' => $date((int) $quest->timeend, '%Y-%m-%d'),
        'stages' => array_map(fn($s) => ['name' => $s, 'on' => in_array($s, $for, true)], $stages),
        'removesure' => format_string($quest->name) . ': ' . $str('questremovesure'),
        'cartilla' => cartilla::squares($quest) ? $card($quest) : null,
    ];
}
// The table of a cartilla: its card with nine places, what it is, and the box of squares.
$mesa = null;
$open = optional_param('cartilla', 0, PARAM_INT);
$quest = $open ? $DB->get_record('local_oksigeniaayvurenda_quest', ['id' => $open, 'libraryid' => $library->id]) : null;
if ($quest && cartilla::squares($quest)) {
    $form = 'rt-cartilla-' . $quest->id;
    $squares = cartilla::squares($quest);
    $next = array_search('', $squares, true);
    $labels = array_column($box(), 'label', 'spec');
    $places = [];
    foreach ($squares as $at => $spec) {
        $places[] = $spec === '' ? ['at' => $at, 'number' => $at + 1, 'empty' => true, 'next' => $at === $next,
            'word' => $str($at === $next ? 'cartillanext' : 'cartillalater'), 'sr' => $str('cartillaempty', $at + 1)]
            : ['at' => $at, 'empty' => false, 'label' => $labels[$spec] ?? $spec, 'art' => $art($spec),
            'remove' => $str('cartillaremove', (object) ['label' => $labels[$spec] ?? $spec, 'at' => $at + 1])];
    }
    $units = ['new' => 'cartillanewcount', 'voice' => 'cartillavoicecount', 'rescued' => 'cartillarescuedcount'];
    $tiles = ['corners' => [], 'genres' => [], 'every' => []];
    foreach ($box() as $option) {
        $at = array_search($option['spec'], $squares, true);
        $count = isset($units[$option['spec']]) ? $str($units[$option['spec']], screens::number($option['count']))
            : $str('cartillabooks', screens::number($option['count']));
        $tiles[$option['group']][] = ['spec' => $option['spec'], 'label' => $option['label'], 'art' => $option['art'],
            'in' => $at !== false, 'at' => $at !== false ? $at : $next, 'value' => $at !== false ? '' : $option['spec'],
            'few' => $option['count'] < 10, 'count' => $option['count'] < 10 ? $str('cartillafew', $count) : $count,
            'off' => $at === false && $next === false];
    }
    $sheet = fn(int $time) => ['month' => \core_text::strtolower(zones::date($time, '%b', $library)),
        'day' => zones::date($time, '%e', $library), 'week' => zones::date($time, '%A', $library)];
    $days = (int) round(((int) $quest->timeend - (int) $quest->timestart) / DAYSECS);
    $known = quests::stages($library, $now);
    $for = json_decode((string) $quest->stages, true) ?: $known;
    // Where it stands: how many squares are placed, and when it shows on the cards.
    $placed = count(array_filter($squares));
    $startday = zones::date((int) $quest->timestart, 'strftimedaydate', $library);
    if ($placed === cartilla::SIZE) {
        $state = (int) $quest->timestart <= $now ? $str('cartillastatelive') : $str('cartillastatefull', $startday);
    } else {
        $state = (int) $quest->timestart <= $now ? $str('cartillastatenow', $placed)
            : $str('cartillastate', (object) ['placed' => $placed, 'day' => $startday]);
    }
    $mesa = [
        'id' => (int) $quest->id,
        'form' => $form,
        'places' => $places,
        'hidden' => array_map(fn($at, $spec) => ['at' => $at, 'spec' => $spec], array_keys($squares), $squares),
        'state' => $state,
        'saved' => optional_param('saved', 0, PARAM_BOOL) ? $str('hiwsaved') : '',
        'name' => format_string($quest->name, true, ['escape' => false]),
        'text' => (string) $quest->text,
        'start' => $sheet((int) $quest->timestart),
        'end' => $sheet((int) $quest->timeend),
        'lengths' => array_map(fn($value) => ['value' => $value, 'label' => $str('questlength' . $value),
            'pressed' => (string) $days === $value], ['7', '14', '28', 'term']),
        'stages' => array_map(fn($index, $stage) => ['index' => $index, 'label' => $stage,
            'infant' => cartilla::infant($stage), 'on' => in_array($stage, $for, true) && !cartilla::infant($stage),
            'value' => in_array($stage, $for, true) ? 0 : 1], array_keys($known), $known),
        'hasstages' => (bool) $known,
        'next' => $next !== false ? $next + 1 : 0,
        'corners' => $tiles['corners'],
        'genres' => $tiles['genres'],
        'every' => $tiles['every'],
        'hascorners' => (bool) $tiles['corners'],
        'hasgenres' => (bool) $tiles['genres'],
    ];
}
$data = [
    'mesa' => $mesa,
    'heading' => $str('queststitle'),
    'intro' => $str('questsintro'),
    'notice' => $notice,
    'problem' => $problem,
    'action' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
    'quests' => $cards,
    'hasquests' => (bool) $cards,
    'stages' => array_map(fn($s) => ['name' => $s, 'on' => false], $stages),
    'hasstages' => (bool) $stages,
    'maxgoal' => quests::MAXGOAL,
    'newstart' => $date($now, '%Y-%m-%d'),
    'newend' => $date($now + 30 * DAYSECS, '%Y-%m-%d'),
];
foreach (
    ['questsnone', 'questsnew', 'questname', 'questnamehint', 'questtext', 'questtexthint', 'queststart', 'questend',
        'questgoal', 'questfor', 'questforhint', 'questsave', 'questclose', 'questedit', 'questremove', 'questreaders',
        'questdone', 'questbooks', 'cartilladraft', 'cartillachange', 'questkindbooks', 'questkindbooksdesc',
        'questkindcartilla', 'questkindcartilladesc', 'cartillanewtitle', 'cartillaname', 'cartillasave', 'cartillastarts',
        'cartillaends', 'cartilladayless', 'cartilladaymore', 'questlength', 'cartillafor', 'cartillanoinfant', 'cartillaline',
        'cartillashuffle', 'cartillafill', 'cartillabox', 'cartillaboxcorners', 'cartillaboxgenres', 'cartillaboxevery',
        'cartillaincard', 'cartillawhat', 'cartillasquares'] as $key
) {
    $data[$key] = $str($key);
}
$data['cartillaboxhint'] = $mesa && $mesa['next'] ? $str('cartillaboxhint', $mesa['next']) : $str('cartillaboxfull');
shell::output(
    $str('queststitle'),
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/quests', $data),
    $library,
    '',
    ['search' => false]
);
