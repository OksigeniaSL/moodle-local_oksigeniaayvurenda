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
shell::require('local/oksigeniaayvurenda:view');
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
// The library staff may see any reader's card, in any look, as the reader would see it.
$isdesk = has_capability('local/oksigeniaayvurenda:desk', $context);
$previewid = $isdesk ? optional_param('reader', 0, PARAM_INT) : 0;
$previewlook = $isdesk ? optional_param('look', '', PARAM_ALPHA) : '';
$readers = $previewid ? [$DB->get_record('local_oksigeniaayvurenda_reader', ['id' => $previewid], '*', MUST_EXIST)]
    : $DB->get_records('local_oksigeniaayvurenda_reader', $conditions, 'id');
$cardsdata = [];
foreach ($readers as $reader) {
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
    $look = in_array($previewlook, card_look::LOOKS, true) ? $previewlook : card_look::resolve(
        card_look::guess($reader->readertype, $stagename, $group),
        $esettings['cardlooks'][$stagename] ?? null,
        $previewid ? null : $own
    );
    $sky = $look === 'sky' || $look === 'skysober';
    $sober = $look === 'skysober';
    $stage = $album['flower']['stage'];
    $previewlibrary = $library ?: \local_oksigeniaayvurenda\local\practice::library();
    $name = $previewid ? (new \local_oksigeniaayvurenda\local\desk($previewlibrary, 0))->reader((int) $reader->id)['name']
        : fullname($USER);
    $card = cards::card($reader, $name, $group, $entity, $stage);
    $card['stage'] = $sky ? $str('stagesky' . $stage) : ($look === 'flower' ? $card['stage'] : '');
    $card['flowersvg'] = $look === 'flower' ? cards::flower_svg($stage, $album['flower']['levels'], $card['stage'], 160)
        : ($sky ? cards::sky_svg($stage, $album['flower']['levels'], $card['stage'], 160, $sober) : '');
    $classic = $look === 'classic' ? garden::classic($reader, $album['year']) : [];
    $paths = [];
    foreach ($look === 'none' ? [] : flower::PATHS as $path) {
        $level = $album['flower']['levels'][$path];
        $name = $sky ? $str('constellation' . $path) . ' · ' . core_text::strtolower($str('path' . $path)) : $str('path' . $path);
        $levellabel = $str('pathlevel', (object) ['path' => $name, 'level' => $level]);
        $marks = $look === 'flower' ? cards::path_svg($path, $level, $levellabel)
            : ($sky ? cards::sky_path_svg($path, $level, $levellabel, $sober) : '');
        // The classic look says what the path means, concretely, without counts.
        $paths[] = ['label' => $name, 'hint' => $str('path' . $path . 'hint'), 'level' => $level, 'marks' => $marks,
            'detail' => $classic[$path] ?? ''];
    }
    $past = [];
    foreach ($look === 'flower' || $sky ? $album['past'] : [] as $p) {
        $label = ($sky ? $str('stagesky' . $p['stage']) : $str('stage' . flower::STAGES[$p['stage']])) . ' · ' . $p['year'];
        $past[] = ['year' => $p['year'], 'flower' => cards::flower_url($p['stage']), 'stage' => $label,
            'svg' => $sky ? cards::sky_svg($p['stage'], $p['levels'], $label, 72, $sober)
                : cards::flower_svg($p['stage'], $p['levels'], $label, 72)];
    }
    // The class garden: what the class met this school year, a flower (or a full sky) for each; nothing in classic.
    $classgarden = [];
    if ($group !== '' && ($look === 'flower' || $sky)) {
        foreach (\local_oksigeniaayvurenda\local\challenges::garden((int) $reader->entityid, $group) as $planted) {
            $label = $str('challengeplanted', $planted['term']) . ' · ' . $str('challengekind' . $planted['kind']);
            $classgarden[] = ['label' => $label, 'svg' => $sky ? cards::sky_svg(3, [], $label, 56, $sober)
                : cards::flower_svg(3, [], $label, 56)];
        }
    }
    $herald = $look === 'flower' || $sky ? \local_oksigeniaayvurenda\local\characters::say(
        (int) $reader->entityid,
        'herald',
        ($sky ? 'heraldsky' : 'heraldstage') . $stage
    ) : null;
    // From twelve on, and for adults, each reader chooses: the sky, the classic look, or none.
    $choices = $look === 'flower' || $previewid ? [] : array_map(fn($o) => ['value' => $o, 'label' => $str('cardlook' . $o),
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
        'classgarden' => $classgarden,
        'hasclassgarden' => (bool) $classgarden,
        'classgardenheading' => $str('challengegarden'),
        'classgardenhint' => $str('challengegardenhint'),
        'yearname' => $album['year']->name,
    ];
}
// Previewing: the reader's name and the looks to switch between. Without a card: a practice reader's, for the staff.
$preview = null;
if ($previewid && $cardsdata) {
    $base = ['library' => $ref, 'reader' => $previewid];
    $preview = ['note' => $str('cardpreviewnote', $cardsdata[0]['name']), 'label' => $str('cardpreviewas'),
        'looks' => array_map(fn($l) => ['url' => (new moodle_url('/local/oksigeniaayvurenda/card.php', $base + ['look' => $l]))
            ->out(false), 'label' => $str('cardlookopt' . $l), 'current' => $l === $previewlook], card_look::LOOKS)];
}
$practice = null;
if (!$cardsdata && $isdesk) {
    $plibrary = \local_oksigeniaayvurenda\local\practice::library();
    $preader = $DB->get_field_sql(
        'SELECT r.id FROM {local_oksigeniaayvurenda_reader} r WHERE r.entityid = ? AND EXISTS
        (SELECT 1 FROM {local_oksigeniaayvurenda_loan} l WHERE l.readerid = r.id) ORDER BY r.id',
        [$plibrary->entityid],
        IGNORE_MULTIPLE
    );
    if ($preader) {
        $practice = ['url' => (new moodle_url('/local/oksigeniaayvurenda/card.php', ['library' => $plibrary->slug,
            'reader' => $preader]))->out(false), 'label' => $str('cardpreviewpractice')];
    }
}
$data = [
    'heading' => $str('cardtitle'),
    'preview' => $preview,
    'practice' => $practice,
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
// The staff find here the guided help of the card (not inside the help itself).
$nav = $isdesk && !optional_param('help', 0, PARAM_BOOL) ? [['url' => (new moodle_url(
    '/local/oksigeniaayvurenda/help.php',
    ['library' => $library->slug, 'piece' => 'card']
))->out(false), 'label' => $str('helpnav')]] : [];
shell::output(
    $str('cardtitle'),
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/card', $data),
    $library,
    '',
    ['nav' => $nav]
);
