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
 * Moving books from one place to another together: a whole place, a stretch of shelf by the letters of the spines, a
 * corner, or one by one with the reader; each move can be undone.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\placesview;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = shell::library_ref();
require_login(null, false);
$library = catalogue::library($ref);
$str = \local_oksigeniaayvurenda\local\skins::speaker($library);
if (!$library) {
    shell::missing($str('librarynotfound'));
}
shell::require('local/oksigeniaayvurenda:catalogue', $library);
$PAGE->set_context(context_system::instance());
$state = placesview::state();
$PAGE->set_url(placesview::url($library, $state));

if (data_submitted() && confirm_sesskey()) {
    $code = optional_param('code', '', PARAM_RAW_TRIMMED);
    if ($code !== '' && !empty($state['reading'])) {
        placesview::read($library, $state, \core_text::substr($code, 0, 200));
    } else {
        $state = placesview::apply_moves($library, $state);
    }
    redirect(placesview::url($library, $state));
}
$data = placesview::moves($library, $state);
$tools = $data['tools'] ?? null;
unset($data['tools']);
shell::output($data['heading'], $OUTPUT->render_from_template('local_oksigeniaayvurenda/moves', $data), $library, '', [
    'search' => false,
    'nav' => [['url' => (new moodle_url('/local/oksigeniaayvurenda/places.php', ['library' => $library->slug]))->out(false),
        'label' => $str('placesheading')]],
] + ($tools ? ['scripts' => ['camera'], 'config' => $tools['config'], 'strings' => $tools['strings']] : []));
