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
 * The centres of the site and their libraries: the first one, adding more, renaming, removing the empty ones.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\libraries;
use local_oksigeniaayvurenda\local\plans;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

require_login(null, false);
$context = context_system::instance();
// Whoever manages a centre sees and changes only theirs; new centres and the site's main seat are for the site's managers.
shell::require('local/oksigeniaayvurenda:manage');
$sitewide = has_capability('local/oksigeniaayvurenda:manage', $context);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/libraries.php'));
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$notice = '';

if (data_submitted() && confirm_sesskey()) {
    $do = required_param('do', PARAM_ALPHA);
    // The centre each action touches: the one given, or the one of the library given.
    if ($do === 'newentity') {
        require_capability('local/oksigeniaayvurenda:manage', $context);
    } else if ($do === 'category') {
        require_capability('moodle/category:manage', $context);
    } else if (in_array($do, ['entity', 'newlibrary'], true)) {
        access::require('local/oksigeniaayvurenda:manage', required_param('entity', PARAM_INT));
    } else {
        access::require('local/oksigeniaayvurenda:manage', (int) $DB->get_field(
            'local_oksigeniaayvurenda_library',
            'entityid',
            ['id' => required_param('library', PARAM_INT)],
            MUST_EXIST
        ));
    }
    if ($do === 'newentity') {
        $library = libraries::create_entity([
            'name' => required_param('name', PARAM_TEXT),
            'library' => optional_param('library', '', PARAM_TEXT),
            'yearstart' => optional_param('yearstart', '09-01', PARAM_TEXT),
            'classification' => optional_param('classification', 'udc', PARAM_ALPHA),
            'controlletter' => optional_param('controlletter', 0, PARAM_BOOL),
        ]);
        $notice = $str('librariescreated', format_string($library->name));
    } else if ($do === 'entity') {
        libraries::update_entity(required_param('entity', PARAM_INT), [
            'name' => required_param('name', PARAM_TEXT),
            'yearstart' => optional_param('yearstart', '09-01', PARAM_TEXT),
            'classification' => optional_param('classification', 'udc', PARAM_ALPHA),
            'controlletter' => optional_param('controlletter', 0, PARAM_BOOL),
            // Stage names go as values: array keys in Moodle's parameters only take letters, digits, - and _.
            'cardlooks' => array_combine(
                optional_param_array('cardstage', [], PARAM_TEXT),
                optional_param_array('cardlook', [], PARAM_ALPHA)
            ) ?: [],
            'reviewsign' => optional_param('reviewsign', 'name', PARAM_ALPHA),
            // The stages listed on the form whose box is left empty play no class challenge.
            'challengesoff' => array_values(array_diff(
                optional_param_array('challengestage', [], PARAM_TEXT),
                optional_param_array('challengeon', [], PARAM_TEXT)
            )),
        ]);
        // The plan only goes with the form of whoever may set it.
        if (optional_param('planform', 0, PARAM_BOOL) && has_capability('local/oksigeniaayvurenda:plans', $context)) {
            $plan = ['name' => optional_param('planname', '', PARAM_TEXT), 'copies' => optional_param('plancopies', 0, PARAM_INT),
                'readers' => optional_param('planreaders', 0, PARAM_INT)];
            foreach (plans::FEATURES as $feature) {
                $plan[$feature] = optional_param('plan' . $feature, 0, PARAM_BOOL);
            }
            plans::save(required_param('entity', PARAM_INT), $plan);
            access::set_closed(required_param('entity', PARAM_INT), !optional_param('readersopen', 0, PARAM_BOOL));
        }
        $notice = $str('librariessaved');
    } else if ($do === 'category') {
        access::category(required_param('entity', PARAM_INT));
        $notice = $str('librariessaved');
    } else if ($do === 'newlibrary') {
        $library = libraries::create_library(required_param('entity', PARAM_INT), [
            'name' => required_param('name', PARAM_TEXT),
            'kind' => optional_param('kind', 'central', PARAM_ALPHA),
            'parentid' => optional_param('parentid', 0, PARAM_INT),
            'slug' => optional_param('slug', '', PARAM_ALPHANUMEXT),
            'groups' => optional_param_array('groups', [], PARAM_TEXT),
        ]);
        $notice = $str('librariescreated', format_string($library->name));
    } else if ($do === 'library') {
        libraries::update_library(required_param('library', PARAM_INT), [
            'name' => required_param('name', PARAM_TEXT),
            'kind' => optional_param('kind', 'central', PARAM_ALPHA),
            'parentid' => optional_param('parentid', 0, PARAM_INT),
            'groups' => optional_param_array('groups', [], PARAM_TEXT),
        ]);
        $notice = $str('librariessaved');
    } else if ($do === 'look') {
        $libraryid = required_param('library', PARAM_INT);
        libraries::save_look($libraryid, [
            'colour' => optional_param('colour', 0, PARAM_INT),
            'motto' => optional_param('motto', '', PARAM_TEXT),
            'featured' => $sitewide ? optional_param('featured', 0, PARAM_BOOL) : libraries::look(
                $DB->get_record('local_oksigeniaayvurenda_library', ['id' => $libraryid], '*', MUST_EXIST)
            )['featured'],
            'scene' => optional_param('scene', '', PARAM_ALPHANUMEXT),
        ]);
        if (optional_param('noimage', 0, PARAM_BOOL)) {
            \local_oksigeniaayvurenda\local\images::remove('libraryimage', $libraryid, 'image');
        }
        $kept = \local_oksigeniaayvurenda\local\images::store_upload('image', 'libraryimage', $libraryid, 'image');
        $notice = $str($kept === false ? 'librarieslookbadimage' : 'librariessaved');
    } else if ($do === 'delete') {
        $notice = $str(libraries::delete_library(required_param('library', PARAM_INT)) ? 'librariesdeleted'
            : 'librariesnotempty');
    }
}

$kinds = fn(string $chosen) => array_map(fn($k) => ['value' => $k, 'label' => $str('librarykind' . $k),
    'selected' => $k === $chosen], libraries::KINDS);
$classifications = fn(string $chosen) => array_map(fn($c) => ['value' => $c, 'label' => $str('classification' . $c),
    'selected' => $c === $chosen], libraries::CLASSIFICATIONS);
$setsplans = has_capability('local/oksigeniaayvurenda:plans', $context);
// Who runs the site gives each centre its category and the roles of its staff there.
$setsstaff = has_capability('moodle/category:manage', $context) && has_capability('moodle/role:assign', $context);
$librarian = (int) $DB->get_field('role', 'id', ['shortname' => \local_oksigeniaayvurenda\local\roles::LIBRARIAN]);
$entities = [];
foreach (libraries::all() as $one) {
    $entity = $one['entity'];
    if (!access::can('local/oksigeniaayvurenda:manage', (int) $entity->id)) {
        continue;
    }
    $settings = json_decode((string) $entity->settings, true) ?: [];
    $groups = libraries::groups((int) $entity->id);
    $options = fn(int $chosen, int $self = 0) => array_values(array_map(
        fn($l) => ['value' => (int) $l->id,
        'label' => format_string($l->name), 'selected' => (int) $l->id === $chosen],
        array_filter($one['libraries'], fn($l) => (int) $l->id !== $self)
    ));
    $groupoptions = fn(array $chosen) => array_map(
        fn($g) => ['value' => $g, 'label' => $g, 'selected' => in_array($g, $chosen, true)],
        array_values(array_unique(array_merge($groups, $chosen)))
    );
    $libs = [];
    foreach ($one['libraries'] as $library) {
        $lgroups = \local_oksigeniaayvurenda\local\shelf::groups($library);
        $card = libraries::look($library);
        $libs[] = [
            // The card: its colour or its picture, whether it is the main seat, its groups and its copies.
            'colour' => $card['colour'],
            'hascardimage' => $card['image'] !== null,
            'cardimage' => (string) $card['image'],
            'featured' => $card['featured'],
            'groupchips' => array_map(fn($g) => ['chip' => $g], $lgroups),
            'hasgroupchips' => (bool) $lgroups,
            'count' => number_format((int) $library->copies, 0, '', get_string('thousandssep', 'langconfig')),
            'countword' => $str((int) $library->copies === 1 ? 'librariescopyone' : 'librariescopymany'),
            'id' => (int) $library->id,
            'name' => format_string($library->name),
            'rawname' => $library->name,
            'url' => (new moodle_url('/local/oksigeniaayvurenda/index.php', ['library' => $library->slug]))->out(false),
            'address' => '/' . $library->slug,
            'kindlabel' => $str('librarykind' . $library->kind),
            'copies' => $str('librariescopies', (int) $library->copies),
            'kinds' => $kinds($library->kind),
            'parents' => $options((int) $library->parentid, (int) $library->id),
            'groups' => $groupoptions($lgroups),
            'group' => implode(', ', $lgroups),
            'hasgroups' => (bool) $groups || (bool) $lgroups,
            'canremove' => (int) $library->copies === 0,
            // Who keeps this library only, at its desk.
            'keepernames' => implode(', ', array_column(\local_oksigeniaayvurenda\local\keepers::of((int) $library->id), 'name')),
            'keepersurl' => (new moodle_url('/local/oksigeniaayvurenda/keepers.php', ['library' => $library->slug]))->out(false),
            'look' => (function () use ($library) {
                $look = libraries::look($library);
                $scenes = [];
                $strings = get_string_manager();
                foreach (libraries::scenes() as $name => $url) {
                    // The art package says what each picture shows; without words, its number.
                    $label = $strings->string_exists($name, 'local_oksigeniaayvurendaarte')
                        ? get_string($name, 'local_oksigeniaayvurendaarte') : $name;
                    $scenes[] = ['value' => $name, 'url' => $url, 'label' => $label, 'selected' => $name === $look['scene']];
                }
                return $look + ['colours' => array_map(
                    fn($c) => ['value' => $c, 'selected' => $c === $look['colour']],
                    range(0, libraries::COLOURS - 1)
                ), 'scenes' => $scenes, 'hasscenes' => (bool) $scenes,
                    'noscene' => $look['scene'] === ''];
            })(),
        ];
    }
    $entities[] = [
        'id' => (int) $entity->id,
        'name' => format_string($entity->name),
        'rawname' => $entity->name,
        'yearstart' => $entity->yearstart,
        'classifications' => $classifications((string) ($settings['classification'] ?? 'udc')),
        'controlletter' => !empty($settings['controlletter']),
        'demo' => !empty($settings['demo']),
        'libraries' => $libs,
        'kinds' => $kinds('classroom'),
        'parents' => $options(0),
        'groups' => $groupoptions([]),
        'hasgroups' => (bool) $groups,
        'cardlooks' => array_map(function ($stage) use ($settings, $str) {
            $guess = \local_oksigeniaayvurenda\core\card_look::guess('student', $stage, '');
            $chosen = $settings['cardlooks'][$stage] ?? '';
            return ['stage' => $stage, 'options' => array_merge(
                [['value' => '', 'label' => $str('cardlookauto', $str('cardlookopt' . $guess)), 'selected' => $chosen === '']],
                array_map(
                    fn($l) => ['value' => $l, 'label' => $str('cardlookopt' . $l), 'selected' => $chosen === $l],
                    \local_oksigeniaayvurenda\core\card_look::LOOKS
                )
            )];
        }, \local_oksigeniaayvurenda\local\holds::stages((int) $entity->id, time())),
        'reviewsigns' => array_map(fn($s) => ['value' => $s, 'label' => $str('reviewsign' . $s),
            'selected' => $s === ($settings['reviewsign'] ?? 'name')], \local_oksigeniaayvurenda\local\reviews::SIGNS),
        'challengestages' => array_map(fn($stage) => ['stage' => $stage, 'on' => !in_array(
            $stage,
            \local_oksigeniaayvurenda\local\challenges::stages_off((int) $entity->id),
            true
        )], \local_oksigeniaayvurenda\local\holds::stages((int) $entity->id, time())),
        'plansummary' => plans::summary((int) $entity->id),
        'staffpart' => $setsstaff ? (function () use ($entity, $str, $librarian) {
            $context = access::context((int) $entity->id);
            $own = $context->contextlevel === CONTEXT_COURSECAT;
            return ['staffentity' => (int) $entity->id, 'staffparthint' => $str($own ? 'librariesstaffhint' : 'librariesstaffnone'),
                'staffassign' => $own ? (new moodle_url('/admin/roles/assign.php', ['contextid' => $context->id,
                    'roleid' => $librarian]))->out(false) : ''];
        })() : null,
        'planform' => $setsplans ? (function () use ($entity, $str) {
            $plan = plans::of((int) $entity->id);
            return ['readersopen' => !access::closed((int) $entity->id),
                'plannamenow' => $plan['name'], 'plancopiesnow' => $plan['copies'], 'planreadersnow' => $plan['readers'],
                'planfeatures' => array_map(fn($f) => ['feature' => $f, 'featurelabel' => $str('plan' . $f),
                    'featureon' => $plan[$f]], plans::FEATURES)];
        })() : null,
    ];
}
$data = [
    'heading' => $str('librariestitle'),
    'intro' => $str('librariesintro'),
    'notice' => $notice,
    'action' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
    'entities' => $entities,
    'none' => !$entities,
    'sitewide' => $sitewide,
    'newclassifications' => $classifications('udc'),
];
foreach (
    ['librariesfirst', 'librariesfirsthint', 'librariesentityname', 'librarieslibraryname', 'librariesyearstart',
        'librariesyearstarthint', 'librariesclassification', 'librariescontrolletter', 'librariescreate', 'librariesadd',
        'librariesname', 'librarieskind', 'librariesparent', 'librariesnoparent', 'librariesslug', 'librariesslughint',
        'librariesgroup', 'librariessave', 'librariesremove', 'librariesanother', 'librariesdemo',
        'librariesedit', 'librariesentity', 'librariescardlooks', 'librariescardlookshint', 'librarieslook',
        'librarieslookhint', 'librariescolour', 'librariesmotto', 'librariesmottohint', 'librariesimage',
        'librariesimagehint', 'librariesnoimage', 'librariesfeatured', 'librariesscenes', 'librariesnoscene',
        'librariesenter', 'librariesseat', 'librariesclose', 'librariesdata', 'librariescentre', 'librarieschallenges',
        'librarieschallengeshint', 'librariesreviewsign', 'librariesplan', 'librariesplanhint', 'planname', 'plancopies',
        'planreaders', 'planlimithint', 'librariesstaff', 'librariesstaffassign', 'librariesstaffseparate',
        'librariesreadersopen', 'librariesreadersopenhint', 'librarieskeptby', 'librarieskeepers', 'librarieskeepershint',
        'librarieskeepersgo'] as $key
) {
    $data[$key] = $str($key);
}
shell::output(
    $str('librariestitle'),
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/libraries', $data),
    null,
    '',
    ['search' => false]
);
