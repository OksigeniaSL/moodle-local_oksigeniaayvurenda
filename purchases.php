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
 * What a library buys: what is asked for, what to order, what is on its way and what has arrived.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\holds;
use local_oksigeniaayvurenda\local\purchases;
use local_oksigeniaayvurenda\local\screens;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\skins;
use local_oksigeniaayvurenda\local\zones;

require(__DIR__ . '/../../config.php');

$ref = \local_oksigeniaayvurenda\local\shell::library_ref();
require_login(null, false);
$library = catalogue::library($ref);
$context = access::context($library);
shell::require('local/oksigeniaayvurenda:catalogue', $library);
$inhelp = optional_param('help', 0, PARAM_BOOL);
$tab = optional_param('tab', 'asked', PARAM_ALPHA);
$tab = in_array($tab, ['asked', 'approved', 'ordered', 'done'], true) ? $tab : 'asked';
$PAGE->set_context(context_system::instance());
$params = ['library' => $ref, 'tab' => $tab] + ($inhelp ? ['help' => 1] : []);
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/purchases.php', $params));
if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$str = skins::speaker($library);
$entityid = (int) $library->entityid;
$manage = has_capability('local/oksigeniaayvurenda:manage', $context);
$now = time();
$notice = '';
$error = '';
if (data_submitted() && confirm_sesskey()) {
    $do = required_param('do', PARAM_ALPHA);
    $id = optional_param('id', 0, PARAM_INT);
    $fields = ['copies' => optional_param('copies', 1, PARAM_INT), 'price' => optional_param('price', '', PARAM_TEXT),
        'vendor' => optional_param('vendor', '', PARAM_TEXT)];
    if ($do === 'add') {
        $added = purchases::add($library, $fields + [
            'title' => optional_param('title', '', PARAM_TEXT),
            'author' => optional_param('author', '', PARAM_TEXT),
            'isbn' => optional_param('isbn', '', PARAM_TEXT),
            'note' => optional_param('note', '', PARAM_TEXT),
            'source' => optional_param('source', '', PARAM_ALPHA),
            'recordid' => optional_param('recordid', 0, PARAM_INT),
        ], (int) $USER->id, $now);
        if ($added) {
            $notice = $str('purchaseadded');
        } else {
            $error = $str('purchasenotitle');
        }
    } else if ($do === 'approve') {
        $notice = purchases::approve($library, $id, $fields, (int) $USER->id, $now) ? $str('purchaseapproved') : '';
    } else if ($do === 'borrow') {
        $partner = optional_param('partner', '', PARAM_TEXT);
        $notice = \local_oksigeniaayvurenda\local\ill::from_purchase($library, $id, $partner, (int) $USER->id, $now)
            ? $str('purchaseborrowed') : '';
    } else if ($do === 'decline') {
        $notice = purchases::decline($library, $id, (int) $USER->id, $now) ? $str('purchasedeclined') : '';
    } else if ($do === 'order') {
        $order = purchases::order($library, optional_param('vendor', '', PARAM_TEXT), (int) $USER->id, $now);
        if ($order) {
            redirect(new moodle_url('/local/oksigeniaayvurenda/purchaseorder.php', ['library' => $ref, 'id' => $order]));
        }
    } else if ($do === 'received') {
        $notice = purchases::received($library, $id, optional_param('code', '', PARAM_TEXT), $now) ? $str('purchasereceived') : '';
    } else if ($do === 'budget') {
        $amount = optional_param('amount', '', PARAM_TEXT);
        purchases::save_budget($library, $amount, optional_param('currency', 'EUR', PARAM_ALPHA), $now);
        $notice = $str('purchasebudgetsaved');
    } else if ($manage && $do === 'settings') {
        purchases::save_settings($library, [
            'suggest' => optional_param('suggest', 0, PARAM_BOOL),
            'stages' => optional_param('allstages', 0, PARAM_BOOL) ? [] : optional_param_array('stages', [], PARAM_TEXT),
        ]);
        $notice = $str('purchasesaved');
    }
}
$budget = purchases::budget($library, $now);
$money = fn(int $cents) => purchases::money($cents, $budget['currency']);
$card = function (array $row) use ($str, $money) {
    $names = $row['names'];
    $asked = '';
    if ($names) {
        $asked = count($names) === 1 ? $str('purchaseaskedone', $names[0])
            : $str('purchaseaskedmore', (object) ['name' => $names[0], 'more' => count($names) - 1]);
    }
    $price = $row['price'] === null ? null : (int) $row['price'];
    return [
        'id' => (int) $row['id'],
        'title' => $row['title'],
        'author' => (string) $row['author'],
        'isbn' => (string) $row['isbn'],
        'note' => (string) $row['note'],
        'asked' => $asked,
        'copies' => (int) $row['copies'],
        'pricetext' => $price !== null ? $money($price * (int) $row['copies']) : '',
        'pricevalue' => $price !== null ? number_format($price / 100, 2, '.', '') : '',
        'vendor' => (string) $row['vendor'],
        'copiestext' => $str('purchasecopies', (int) $row['copies']),
    ];
};
$counts = purchases::counts($library);
$tabs = [];
foreach (
    ['asked' => (int) $counts['asked'], 'approved' => (int) $counts['approved'], 'ordered' => (int) $counts['ordered'],
    'done' => 0] as $key => $count
) {
    $tabs[] = ['url' => (new moodle_url('/local/oksigeniaayvurenda/purchases.php', ['library' => $ref, 'tab' => $key]
        + ($inhelp ? ['help' => 1] : [])))->out(false),
        'label' => $str('purchasetab' . $key) . ($count ? ' (' . screens::number($count) . ')' : ''),
        'current' => $tab === $key, 'key' => $key];
}
$data = ['heading' => $str('purchasestitle'), 'intro' => $str('purchasesintro'), 'notice' => $notice, 'error' => $error,
    'action' => $PAGE->url->out(false), 'sesskey' => sesskey(), 'tabs' => $tabs, 'manage' => $manage,
    'isasked' => $tab === 'asked', 'isapproved' => $tab === 'approved', 'isordered' => $tab === 'ordered',
    'isdone' => $tab === 'done'];
// What has been used before, to choose from.
$vendors = $DB->get_fieldset_sql(
    'SELECT DISTINCT vendor FROM {local_oksigeniaayvurenda_purchase} WHERE libraryid = ? AND vendor IS NOT NULL ORDER BY vendor',
    [$library->id]
);
$data['vendors'] = array_map(fn($v) => ['name' => $v], $vendors);
$data['vendorlist'] = 'ayv-purchase-vendors';
if ($tab === 'asked') {
    $data['cards'] = array_map($card, purchases::list($library, 'asked'));
    $data['hascards'] = (bool) $data['cards'];
    $data['ideas'] = array_map(fn($i) => $i + [
        'why' => $i['source'] === 'course'
            ? $str('purchaseideacourse', (object) ['students' => screens::number($i['students']),
                'copies' => screens::number($i['copies'])])
            : $str('purchaseideaqueue', (object) ['waiting' => screens::number($i['waiting']),
                'copies' => screens::number($i['copies'])]),
    ], purchases::ideas($library));
    $data['hasideas'] = (bool) $data['ideas'];
} else if ($tab === 'approved') {
    $groups = [];
    foreach (purchases::list($library, 'approved') as $row) {
        $vendor = (string) $row['vendor'];
        $groups[$vendor]['vendor'] = $vendor;
        $groups[$vendor]['vendorname'] = $vendor !== '' ? $vendor : $str('purchasenovendor');
        $groups[$vendor]['orderlabel'] = $vendor !== '' ? $str('purchaseorderto', $vendor) : $str('purchaseorderplain');
        $groups[$vendor]['rows'][] = $card($row);
    }
    ksort($groups);
    $data['groups'] = array_values($groups);
    $data['hasgroups'] = (bool) $groups;
} else if ($tab === 'ordered') {
    $orders = [];
    foreach (purchases::list($library, 'ordered') as $row) {
        $id = (int) $row['orderid'];
        if (!isset($orders[$id])) {
            $order = $DB->get_record('local_oksigeniaayvurenda_purchaseorder', ['id' => $id]);
            $orders[$id] = [
                'heading' => $str(
                    $order && $order->vendor ? 'purchaseorderheading' : 'purchaseorderheadingplain',
                    (object) ['vendor' => (string) ($order->vendor ?? ''),
                        'date' => zones::date((int) ($order->timecreated ?? $now), 'strftimedateshort', $library)]
                ),
                'sheet' => (new moodle_url('/local/oksigeniaayvurenda/purchaseorder.php', ['library' => $ref,
                    'id' => $id]))->out(false),
                'rows' => [],
            ];
        }
        $orders[$id]['rows'][] = $card($row);
    }
    krsort($orders);
    $data['orders'] = array_values($orders);
    $data['hasorders'] = (bool) $orders;
} else {
    $data['received'] = array_map($card, purchases::list($library, 'received', 100));
    $data['declined'] = array_map($card, purchases::list($library, 'declined', 50));
    $data['hasreceived'] = (bool) $data['received'];
    $data['hasdeclined'] = (bool) $data['declined'];
}
// The budget and whether readers may ask.
$data['budgetline'] = $budget['amount'] !== null
    ? $str('purchasebudgetline', (object) ['spent' => $money($budget['spent']), 'amount' => $money($budget['amount']),
        'year' => $budget['year']])
    : $str('purchasebudgetnone', (object) ['spent' => $money($budget['spent']), 'year' => $budget['year']]);
$data['budgetshare'] = $budget['amount'] ? min(100, (int) round($budget['spent'] * 100 / max(1, $budget['amount']))) : 0;
$data['hasbudget'] = $budget['amount'] !== null;
$data['over'] = $budget['amount'] !== null && $budget['spent'] > $budget['amount'];
$data['amountvalue'] = $budget['amount'] !== null ? number_format($budget['amount'] / 100, 2, '.', '') : '';
$currencies = get_string_manager()->get_list_of_currencies();
$data['currencies'] = array_map(
    fn($code, $name) => ['code' => $code, 'name' => $name . ' (' . $code . ')', 'selected' => $code === $budget['currency']],
    array_keys($currencies),
    array_values($currencies)
);
$settings = purchases::settings($library);
$stages = holds::stages($entityid, $now);
$data['suggestline'] = !$settings['suggest'] ? $str('purchasesuggestoff')
    : ($settings['stages'] ? $str('purchasesuggeststages', implode(', ', $settings['stages'])) : $str('purchasesuggestall'));
$data['suggeston'] = $settings['suggest'];
$data['allstages'] = !$settings['stages'];
$data['hasstages'] = (bool) $stages;
$data['stages'] = array_map(fn($s) => ['name' => $s, 'chosen' => in_array($s, $settings['stages'], true)], $stages);
foreach (
    ['purchaseadd', 'purchaseaddintro', 'purchasefieldtitle', 'purchasefieldauthor', 'purchasefieldisbn', 'purchasefieldnote',
        'purchasefieldcopies', 'purchasefieldprice', 'purchasefieldpricehint', 'purchasefieldvendor', 'purchasesave',
        'purchaseclose',
        'purchaseyes', 'purchaseborrow', 'purchaseborrowintro', 'purchaseborrowfrom', 'purchaseno',
        'purchaseideas', 'purchaseideasintro', 'purchaseideaadd', 'purchasenothingasked',
        'purchasenothingapproved', 'purchasenothingordered', 'purchasenothingdone', 'purchasechange', 'purchasearrived',
        'purchasearrivedintro', 'purchasefieldcode', 'purchasesheet', 'purchasedoneheading', 'purchasedeclinedheading',
        'purchasebudgetheading', 'purchasebudgetintro', 'purchasefieldamount', 'purchasefieldcurrency', 'purchasesuggestheading',
        'purchasesuggestlabel', 'purchasestages', 'purchaseallstages'] as $key
) {
    $data[$key] = $str($key);
}
shell::output($data['heading'], $OUTPUT->render_from_template('local_oksigeniaayvurenda/purchases', $data), $library, '', [
    'search' => false,
    'nav' => $inhelp ? [] : [['url' => (new moodle_url(
        '/local/oksigeniaayvurenda/help.php',
        ['library' => $library->slug, 'piece' => 'purchases']
    ))->out(false), 'label' => $str('helpnav')]],
]);
