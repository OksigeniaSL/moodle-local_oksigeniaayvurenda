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

use local_oksigeniaayvurenda\local\libraries;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

require_login(null, false);
$context = context_system::instance();
require_capability('local/oksigeniaayvurenda:manage', $context);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/libraries.php'));
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$notice = '';

if (data_submitted() && confirm_sesskey()) {
    $do = required_param('do', PARAM_ALPHA);
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
        ]);
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
    } else if ($do === 'delete') {
        $notice = $str(libraries::delete_library(required_param('library', PARAM_INT)) ? 'librariesdeleted'
            : 'librariesnotempty');
    }
}

$kinds = fn(string $chosen) => array_map(fn($k) => ['value' => $k, 'label' => $str('librarykind' . $k),
    'selected' => $k === $chosen], libraries::KINDS);
$classifications = fn(string $chosen) => array_map(fn($c) => ['value' => $c, 'label' => $str('classification' . $c),
    'selected' => $c === $chosen], libraries::CLASSIFICATIONS);
$entities = [];
foreach (libraries::all() as $one) {
    $entity = $one['entity'];
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
        $libs[] = [
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
    'newclassifications' => $classifications('udc'),
];
foreach (
    ['librariesfirst', 'librariesfirsthint', 'librariesentityname', 'librarieslibraryname', 'librariesyearstart',
        'librariesyearstarthint', 'librariesclassification', 'librariescontrolletter', 'librariescreate', 'librariesadd',
        'librariesname', 'librarieskind', 'librariesparent', 'librariesnoparent', 'librariesslug', 'librariesslughint',
        'librariesgroup', 'librariessave', 'librariesremove', 'librariesanother', 'librariesdemo',
        'librariesedit', 'librariesentity'] as $key
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
