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
 * An order of a library, as a sheet to send or to print.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\purchases;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\skins;
use local_oksigeniaayvurenda\local\zones;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
$id = required_param('id', PARAM_INT);
require_login(null, false);
$library = catalogue::library($ref);
$context = access::context($library);
shell::require('local/oksigeniaayvurenda:catalogue', $library);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/purchaseorder.php', ['library' => $ref, 'id' => $id]));
$str = skins::speaker($library);
$found = $library ? purchases::order_of($library, $id) : null;
if (!$found) {
    shell::missing($str('purchaseordernothere'), $library);
}
$budget = purchases::budget($library);
$money = fn(int $cents) => purchases::money($cents, $budget['currency']);
$total = 0;
$copies = 0;
$rows = [];
foreach ($found['rows'] as $row) {
    $line = $row->price === null ? null : (int) $row->price * (int) $row->copies;
    $total += (int) $line;
    $copies += (int) $row->copies;
    $rows[] = ['title' => $row->title, 'author' => (string) $row->author, 'isbn' => (string) $row->isbn,
        'copies' => (int) $row->copies,
        'price' => $row->price === null ? '' : $money((int) $row->price), 'line' => $line === null ? '' : $money($line)];
}
$data = [
    'heading' => $str(
        $found['order']->vendor ? 'purchaseorderheading' : 'purchaseorderheadingplain',
        (object) ['vendor' => (string) $found['order']->vendor,
            'date' => zones::date((int) $found['order']->timecreated, 'strftimedate', $library)]
    ),
    'from' => format_string($library->name, true, ['escape' => false]),
    'rows' => $rows,
    'totalcopies' => $copies,
    'total' => $total ? $money($total) : '',
    'back' => (new moodle_url('/local/oksigeniaayvurenda/purchases.php', ['library' => $ref, 'tab' => 'ordered']))->out(false),
];
foreach (
    ['purchasesheetfrom', 'purchasefieldtitle', 'purchasefieldisbn', 'purchasefieldcopies', 'purchasefieldprice',
        'purchasesheettotal',
        'purchasesheetback', 'printnow'] as $key
) {
    $data[$key] = $str($key);
}
shell::output($data['heading'], $OUTPUT->render_from_template('local_oksigeniaayvurenda/purchase_order', $data), $library, '', [
    'search' => false,
]);
