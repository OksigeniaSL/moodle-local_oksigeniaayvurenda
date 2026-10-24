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
 * My card: the flower of the year, how it grows, the code for the desk, and the album.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\core\flower;
use local_oksigeniaayvurenda\core\card_look;
use local_oksigeniaayvurenda\local\cards;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\garden;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = optional_param('library', '', PARAM_ALPHANUMEXT);
require_login(null, false);
$context = context_system::instance();
require_capability('local/oksigeniaayvurenda:view', $context);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/card.php', ['library' => $ref]));

$library = catalogue::library($ref);
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$conditions = ['userid' => $USER->id, 'status' => 'active'];
if ($library) {
    $conditions['entityid'] = $library->entityid;
}
// The reader's own choice of how the card looks (not with the flower, which the centre decides).
$pref = 'local_oksigeniaayvurenda_cardlook';
if (data_submitted() && confirm_sesskey()) {
    $chosen = optional_param('look', '', PARAM_ALPHA);
    set_user_preference($pref, in_array($chosen, card_look::OWN, true) ? $chosen : null);
}
$own = get_user_preferences($pref, null);
$cardsdata = [];
foreach ($DB->get_records('local_oksigeniaayvurenda_reader', $conditions, 'id') as $reader) {
    $album = garden::album($reader);
    $member = $DB->get_record(
        'local_oksigeniaayvurenda_member',
        ['readerid' => $reader->id, 'yearid' => $album['year']->id],
        'id, groupname, stage',
        IGNORE_MULTIPLE
    );
    $group = $member ? (string) $member->groupname : '';
    $entityrow = $DB->get_record('local_oksigeniaayvurenda_entity', ['id' => $reader->entityid], 'name, settings');
    $entity = format_string($entityrow->name);
    $esettings = json_decode((string) $entityrow->settings, true) ?: [];
    $stagename = $member ? (string) $member->stage : '';
    $look = card_look::resolve(
        card_look::guess($reader->readertype, $stagename, $group),
        $esettings['cardlooks'][$stagename] ?? null,
        $own
    );
    $sky = $look === 'sky' || $look === 'skysober';
    $sober = $look === 'skysober';
    $stage = $album['flower']['stage'];
    $card = cards::card($reader, fullname($USER), $group, $entity, $stage);
    $card['stage'] = $sky ? $str('stagesky' . $stage) : ($look === 'flower' ? $card['stage'] : '');
    $card['flowersvg'] = $look === 'flower' ? cards::flower_svg($stage, $album['flower']['levels'], $card['stage'], 160)
        : ($sky ? cards::sky_svg($stage, $album['flower']['levels'], $card['stage'], 160, $sober) : '');
    $paths = [];
    foreach ($look === 'none' ? [] : flower::PATHS as $path) {
        $level = $album['flower']['levels'][$path];
        $name = $sky ? $str('constellation' . $path) . ' · ' . core_text::strtolower($str('path' . $path)) : $str('path' . $path);
        $levellabel = $str('pathlevel', (object) ['path' => $name, 'level' => $level]);
        $marks = $look === 'flower' ? cards::path_svg($path, $level, $levellabel)
            : ($sky ? cards::sky_path_svg($path, $level, $levellabel, $sober) : '');
        $paths[] = ['label' => $name, 'hint' => $str('path' . $path . 'hint'), 'level' => $level, 'marks' => $marks,
            'leveltext' => $marks === '' ? $str('cardlevel', $level) : ''];
    }
    $past = [];
    foreach ($look === 'flower' || $sky ? $album['past'] : [] as $p) {
        $label = ($sky ? $str('stagesky' . $p['stage']) : $str('stage' . flower::STAGES[$p['stage']])) . ' · ' . $p['year'];
        $past[] = ['year' => $p['year'], 'flower' => cards::flower_url($p['stage']), 'stage' => $label,
            'svg' => $sky ? cards::sky_svg($p['stage'], $p['levels'], $label, 72, $sober)
                : cards::flower_svg($p['stage'], $p['levels'], $label, 72)];
    }
    $herald = $look === 'flower' || $sky ? \local_oksigeniaayvurenda\local\characters::say(
        (int) $reader->entityid,
        'herald',
        ($sky ? 'heraldsky' : 'heraldstage') . $stage
    ) : null;
    // From twelve on, and for adults, each reader chooses: the sky, the classic look, or none.
    $choices = $look === 'flower' ? [] : array_map(fn($o) => ['value' => $o, 'label' => $str('cardlook' . $o),
        'checked' => $o === ($sky ? 'sky' : $look)], card_look::OWN);
    $cardsdata[] = $card + [
        'herald' => $herald,
        'paths' => $paths,
        'haspaths' => (bool) $paths,
        'pathsheading' => $str($look === 'flower' ? 'cardpaths' : ($sky ? 'cardpathssky' : 'cardpathsclassic')),
        'pastheading' => $str($sky ? 'cardpastsky' : 'cardpast'),
        'choices' => $choices,
        'haschoices' => (bool) $choices,
        'sky' => $sky,
        'books' => $album['books'],
        'hasbooks' => (bool) $album['books'],
        'past' => $past,
        'haspast' => (bool) $past,
        'yearname' => $album['year']->name,
    ];
}
$data = [
    'heading' => $str('cardtitle'),
    'cards' => $cardsdata,
    'hascards' => (bool) $cardsdata,
    'none' => $str('cardnone'),
    'action' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
    'lookheading' => $str('cardlookheading'),
    'looksave' => $str('cardlooksave'),
    'booksheading' => $str('cardbooks'),
    'nobooks' => $str('cardnobooks'),
    'pastheading' => $str('cardpast'),
    'showcode' => $str('cardshowcode'),
];
shell::output($str('cardtitle'), $OUTPUT->render_from_template('local_oksigeniaayvurenda/card', $data), $library, '');
