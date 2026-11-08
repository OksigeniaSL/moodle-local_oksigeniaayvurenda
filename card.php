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

use local_oksigeniaayvurenda\core\card_look;
use local_oksigeniaayvurenda\core\flower;
use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\cards;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\garden;
use local_oksigeniaayvurenda\local\goals;
use local_oksigeniaayvurenda\local\newsletter;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = optional_param('library', '', PARAM_ALPHANUMEXT);
require_login(null, false);
// Opened without its library (a bookmark, an old link): the library of the reader's card, where everything is.
if ($ref === '' && !optional_param('reader', 0, PARAM_INT)) {
    $own = $DB->get_record_select(
        'local_oksigeniaayvurenda_reader',
        "userid = ? AND status = 'active'",
        [$USER->id],
        'id, entityid',
        IGNORE_MULTIPLE
    );
    $home = $own ? \local_oksigeniaayvurenda\local\mail::main_library((int) $own->entityid) : null;
    if ($home) {
        redirect(new moodle_url('/local/oksigeniaayvurenda/card.php', ['library' => $home->slug]));
    }
}
$library = catalogue::library($ref);
$context = access::context($library);
shell::require('local/oksigeniaayvurenda:view', $library);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/card.php', ['library' => $ref]));

$str = \local_oksigeniaayvurenda\local\skins::speaker($library ?? null);
$conditions = ['userid' => $USER->id, 'status' => 'active'];
if ($library) {
    $conditions['entityid'] = $library->entityid;
}
// The reader's own choice of how the card looks (not with the flower, which the centre decides).
$pref = 'local_oksigeniaayvurenda_cardlook';
$saved = '';
if (data_submitted() && confirm_sesskey()) {
    $do = optional_param('do', 'look', PARAM_ALPHA);
    if ($do === 'newbooks') {
        newsletter::subscribe((int) $USER->id, optional_param('newbooks', 0, PARAM_BOOL), (int) $reader->entityid);
    } else if ($do === 'goal') {
        // A number written, or one of those offered.
        $wanted = optional_param('goalother', 0, PARAM_INT) ?: optional_param('goal', 0, PARAM_INT);
        goals::set((int) $USER->id, required_param('year', PARAM_INT), $wanted);
    } else {
        $chosen = optional_param('look', '', PARAM_ALPHA);
        set_user_preference($pref, in_array($chosen, card_look::OWN, true) ? $chosen : null);
    }
    $saved = get_string('cardsaved', 'local_oksigeniaayvurenda');
}
$own = get_user_preferences($pref, null);
// The library staff may see any reader's card, in any look, as the reader would see it.
$isdesk = has_capability('local/oksigeniaayvurenda:desk', $context);
$previewid = $isdesk ? optional_param('reader', 0, PARAM_INT) : 0;
$previewlook = $isdesk ? optional_param('look', '', PARAM_ALPHA) : '';
$preview = $previewid ? $DB->get_record('local_oksigeniaayvurenda_reader', ['id' => $previewid,
    'entityid' => $library->entityid]) : null;
if ($previewid && (!$preview || $preview->status === 'deleted')) {
    shell::missing(get_string('readernothere', 'local_oksigeniaayvurenda'), $library);
}
$readers = $preview ? [$preview]
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
    $entity = format_string($entityrow->name, true, ['escape' => false]);
    $esettings = json_decode((string) $entityrow->settings, true) ?: [];
    $stagename = $member ? (string) $member->stage : '';
    [$guess, $centre] = \local_oksigeniaayvurenda\local\cards::base($reader, $stagename, $group, $esettings);
    $look = in_array($previewlook, card_look::LOOKS, true) ? $previewlook
        : card_look::resolve($guess, $centre, $previewid ? null : $own);
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
        // The flower's marks say their level in words too: three small drawings are hard to read at a glance.
        $paths[] = ['label' => $name, 'hint' => $str('path' . $path . 'hint'), 'level' => $level, 'marks' => $marks,
            'detail' => $classic[$path] ?? ($look === 'flower'
                ? $str('pathlevelof', (object) ['level' => $level, 'of' => 3]) : '')];
    }
    $past = [];
    foreach ($look === 'flower' || $sky ? $album['past'] : [] as $p) {
        $label = ($sky ? $str('stagesky' . $p['stage']) : $str('stage' . flower::STAGES[$p['stage']])) . ' · ' . $p['year'];
        $past[] = ['year' => $p['year'], 'flower' => cards::flower_url($p['stage']), 'stage' => $label,
            'pastclass' => $p['met'] ? $str('cardpastclass', (object) ['group' => $p['group'], 'met' => $p['met']]) : '',
            'svg' => $sky ? cards::sky_svg($p['stage'], $p['levels'], $label, 72, $sober)
                : cards::flower_svg($p['stage'], $p['levels'], $label, 72)];
    }
    // The class garden: what the class met this school year, a flower (or a full sky) for each; nothing in classic.
    $classgarden = [];
    $classisland = null;
    if ($group !== '' && ($look === 'flower' || $sky)) {
        $plantedall = \local_oksigeniaayvurenda\local\challenges::garden((int) $reader->entityid, $group);
        foreach ($plantedall as $planted) {
            $label = $str('challengeplanted', $planted['term']) . ' · ' . $str('challengekind' . $planted['kind']);
            $classgarden[] = ['label' => $label, 'svg' => $sky ? cards::sky_svg(3, [], $label, 56, $sober)
                : cards::flower_svg(3, [], $label, 56)];
        }
        // A young reader sees the class's island grow, the same one the tutor sees, drawn (D10).
        $layers = $look === 'flower' && $plantedall ? \local_oksigeniaayvurenda\local\screens::island(
            count($plantedall),
            in_array('everyone', array_column($plantedall, 'kind'), true),
            false
        ) : null;
        $classisland = $layers ? ['islandlayers' => array_map(fn($u) => ['layer' => $u], $layers),
            'islandlabel' => $str('islandlabel', count($plantedall))] : null;
    }
    $herald = $look === 'flower' || $sky ? \local_oksigeniaayvurenda\local\characters::say(
        (int) $reader->entityid,
        'herald',
        ($sky ? 'heraldsky' : 'heraldstage') . $stage
    ) : null;
    // The day of the year of their first loan here, a year or more ago: the herald remembers it (jue-humor H31-H33).
    $first = $DB->get_record_sql(
        "SELECT l.timeloaned, r.title
           FROM {local_oksigeniaayvurenda_loan} l
           JOIN {local_oksigeniaayvurenda_item} i ON i.id = l.itemid
           JOIN {local_oksigeniaayvurenda_record} r ON r.id = i.recordid
          WHERE l.readerid = :readerid
       ORDER BY l.timeloaned, l.id",
        ['readerid' => $reader->id],
        IGNORE_MULTIPLE
    );
    $zone = \local_oksigeniaayvurenda\local\zones::of((int) $reader->entityid);
    $day = fn(int $time) => (new \DateTimeImmutable('@' . $time))->setTimezone($zone);
    if (
        $first && !$previewid && $day((int) $first->timeloaned)->format('m-d') === $day(time())->format('m-d')
            && ($years = (int) $day(time())->format('Y') - (int) $day((int) $first->timeloaned)->format('Y')) >= 1
    ) {
        $herald = \local_oksigeniaayvurenda\local\characters::say(
            (int) $reader->entityid,
            'herald',
            $sky || $look !== 'flower' ? 'heraldfirstloandaysecondary' : 'heraldfirstloanday',
            ['title' => format_string($first->title, true, ['escape' => false]), 'years' => $years],
            'celebra'
        ) ?? $herald;
    }
    // The young (the flower) hear what they may not read yet: the herald, the paths, the garden and the empty album.
    $young = $look === 'flower';
    $sex = (\local_oksigeniaayvurenda\local\characters::of((int) $reader->entityid)['herald']['sex'] ?? 'm') === 'f' ? 'f' : 'm';
    if ($herald && $young) {
        $herald['listen'] = \local_oksigeniaayvurenda\local\screens::listen(['herald-' . $sex . '/{lang}/heraldstage' . $stage]);
    }
    // The collector cards of the corners: pictures, so not in the classic look.
    // A school's album: the flower's, and the sky's of a school or a high school; never an adult's at a university.
    $collects = $look === 'flower' || ($sky && in_array(
        \local_oksigeniaayvurenda\local\skins::of((int) $reader->entityid),
        ['school', 'secondary'],
        true
    ));
    $cromos = $collects ? (garden::cromos(
        $reader,
        $library ?? \local_oksigeniaayvurenda\local\mail::main_library((int) $reader->entityid)
    ) ?? []) : [];
    // Mainumby whole beside an empty album: where the skin has the characters whole, and at a public library for the young.
    $whole = \local_oksigeniaayvurenda\local\skins::lively((int) $reader->entityid) && (!in_array(
        \local_oksigeniaayvurenda\local\skins::of((int) $reader->entityid),
        \local_oksigeniaayvurenda\local\skins::FIGURES_YOUNG,
        true
    ) || $look === 'flower');
    foreach ($cromos as &$cromo) {
        // A grey sticker already says it is not won: only the won ones carry their date.
        $cromo['state'] = $cromo['won'] ? $cromo['when'] : '';
        $cromo['label'] = $str($cromo['won'] ? 'cromowon' : 'cromomissing', (object) ['name' => $cromo['name'],
            'when' => $cromo['when']]);
    }
    unset($cromo);
    $woncromos = count(array_filter(array_column($cromos, 'won')));
    // From twelve on, and for adults, each reader chooses: the sky, the classic look, or none.
    $choices = $look === 'flower' || $previewid ? [] : array_map(fn($o) => ['value' => $o, 'title' => $str('cardlooktitle' . $o),
        'hint' => $str('cardlookhint' . $o), 'art' => $o === 'sky' ? (\local_oksigeniaayvurenda\local\screens::art(
            'carne/impreso/luna-nueva'
        ) ?? '') : '', 'isclassic' => $o === 'classic', 'isnone' => $o === 'none',
        'checked' => $o === ($sky ? 'sky' : $look)], card_look::OWN);
    // From twelve on, and for adults: their own goal for the year, which only they see, and the library's news.
    $mine = false;
    if ($look !== 'flower' && !$previewid) {
        $goal = goals::of((int) $USER->id, (int) $album['year']->id);
        $done = goals::done((int) $reader->id, (int) $album['year']->id);
        $mine = ['yearid' => (int) $album['year']->id, 'done' => $done, 'hasgoal' => $goal > 0,
            'goalreached' => $goal > 0 && $done >= $goal,
            'goalwidth' => $goal ? (int) round(min($done, $goal) * 100 / $goal) : 0,
            'goalstarted' => $goal > 0 && $done > 0,
            'goalunder' => $goal ? $str('goalof', $goal) : $str($done === 1 ? 'goalbook' : 'goalbooks'),
            'goalline' => $goal ? $str($done >= $goal ? 'goalmet' : 'goalprogress', (object) ['done' => $done, 'goal' => $goal])
                : $str('goalask'),
            // What most people choose, and what this person chose if it is another number.
            'presets' => array_map(
                fn($n) => ['number' => $n, 'on' => $n === $goal],
                array_values(array_unique(array_filter(array_merge([5, 10, 15, 20, 30, 50], [$goal]), fn($n) => $n > 0)))
            ),
            'newbookson' => newsletter::subscribed((int) $USER->id, (int) $reader->entityid)];
    }
    $cardsdata[] = $card + [
        'sanctionline' => \local_oksigeniaayvurenda\local\sanctions::for_reader((int) $reader->entityid, (int) $reader->id, time()),
        'herald' => $herald,
        'paths' => $paths,
        'haspaths' => (bool) $paths,
        'listenpaths' => $young ? \local_oksigeniaayvurenda\local\screens::listen(array_map(
            fn($p) => 'ui/{lang}/path' . $p,
            ['back', 'explore', 'tell', 'stay']
        )) : null,
        'listennobooks' => $young ? \local_oksigeniaayvurenda\local\screens::listen([
            $library && \local_oksigeniaayvurenda\local\characters::band((int) $library->entityid) === 'inf'
                ? 'ui/{lang}/cardnobooksinf' : 'ui/{lang}/cardnobooks',
        ]) : null,
        // Mainumby reading beside an empty album, when the herald is on and has no portrait of the centre's own.
        'nobooksfigure' => ($heraldchar = \local_oksigeniaayvurenda\local\characters::of((int) $reader->entityid)['herald'] ?? null)
            && $heraldchar['enabled'] && empty($heraldchar['own']) && $whole
            ? \local_oksigeniaayvurenda\local\characters::figure('herald', 'lee', $look === 'flower' ? '' : 'cielo') : null,
        'nobookspuppet' => !empty($heraldchar['enabled']) && empty($heraldchar['own']) && $whole
            ? \local_oksigeniaayvurenda\local\characters::puppet('herald', 'lee', $look === 'flower' ? '' : 'cielo') : '',
        'listengarden' => $young ? \local_oksigeniaayvurenda\local\screens::listen(
            ['ui/{lang}/challengegarden', 'ui/{lang}/challengegardenhint']
        ) : null,
        'pathsheading' => $str($look === 'flower' ? 'cardpaths' : ($sky ? 'cardpathssky' : 'cardpathsclassic')),
        'pastheading' => $str($sky ? 'cardpastsky' : 'cardpast'),
        'choices' => $choices,
        'haschoices' => (bool) $choices,
        // Whoever has never chosen how to see their card sees the choice above it, not after the whole game.
        'choosefirst' => (bool) $choices && ($own === null || $own === ''),
        'mine' => $mine,
        'sky' => $sky,
        'books' => $album['books'],
        'hasbooks' => (bool) $album['books'],
        'past' => $past,
        'haspast' => (bool) $past,
        'classgarden' => $classgarden,
        'classisland' => $classisland,
        'hasclassgarden' => (bool) $classgarden,
        'classgardenheading' => $str('challengegarden'),
        'classgardenhint' => $str('challengegardenhint'),
        'yearname' => $album['year']->name,
        'cromos' => $cromos,
        'hascromos' => (bool) $cromos,
        'cromosheading' => $str('cromosheading'),
        'cromoscount' => $str('cromoscount', (object) ['won' => $woncromos, 'all' => count($cromos)]),
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
    'athome' => $library ? \local_oksigeniaayvurenda\local\screens::at_home($library) : null,
    'preview' => $preview,
    'practice' => $practice,
    'cards' => $cardsdata,
    'hascards' => (bool) $cardsdata,
    'none' => $str('cardnone'),
    'action' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
    'lookheading' => $str('cardlookheading'),
    'booksheading' => $str('cardbooks'),
    // The empty album, told to Infantil as a plate before dinner (jue-humor H53).
    'nobooks' => $str($library && \local_oksigeniaayvurenda\local\characters::band((int) $library->entityid) === 'inf'
        ? 'cardnobooksinf' : 'cardnobooks'),
    'pastheading' => $str('cardpast'),
    'showcode' => $str('cardshowcode'),
    'showbig' => $str('cardshowbig'),
    'showbighint' => $str('cardshowbighint'),
    'showbigclose' => $str('cardshowbigclose'),
    'saved' => $saved,
    'mineheading' => $str('mineheading'),
    'goaltitle' => $str('goaltitle'),
    'goallabel' => $str('goallabel'),
    'goalhint' => $str('goalhint'),
    'goalother' => $str('goalother'),
    'goaloff' => $str('goaloff'),
    'newbookstitle' => $str('newbookstitle'),
    'newbookslabel' => $str('newbookslabel'),
    'newbookshint' => $str('newbookshint'),
];
// The staff find here the guided help of the card (not inside the help itself).
$flower = in_array(\local_oksigeniaayvurenda\local\skins::of((int) $library->entityid), ['school', 'secondary'], true);
$nav = $flower && $isdesk && !optional_param('help', 0, PARAM_BOOL) ? [['url' => (new moodle_url(
    '/local/oksigeniaayvurenda/help.php',
    ['library' => $library->slug, 'piece' => 'card']
))->out(false), 'label' => $str('helpnav')]] : [];
// Whoever may ask the library for a book finds the way from their card too.
$asker = $library ? ($DB->get_record('local_oksigeniaayvurenda_reader', ['entityid' => $library->entityid, 'userid' => $USER->id,
    'status' => 'active'], '*', IGNORE_MULTIPLE) ?: null) : null;
$canask = $library && \local_oksigeniaayvurenda\local\purchases::may_ask($library, $asker, time());
if ($canask && !optional_param('help', 0, PARAM_BOOL)) {
    $nav[] = ['url' => (new moodle_url('/local/oksigeniaayvurenda/suggest.php', ['library' => $library->slug]))->out(false),
        'label' => $str('suggesttitle')];
}
// The second factor of their account, where the site is only a library.
if ($security = \local_oksigeniaayvurenda\local\onlylibrary::security_url()) {
    $nav[] = ['url' => $security, 'label' => $str('menusecurity')];
}
shell::output(
    $str('cardtitle'),
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/card', $data),
    $library,
    '',
    ['nav' => $nav]
);
