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
 * The library's external functions: what the Moodle app (and apps made from it) asks for, and what its own screens ask
 * without loading again.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_oksigeniaayvurenda_ask_hold' => [
        'classname' => \local_oksigeniaayvurenda\external\ask_hold::class,
        'description' => 'A reader asks for a book that is out, to be told when it is back for them.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'local_oksigeniaayvurenda_cancel_hold' => [
        'classname' => \local_oksigeniaayvurenda\external\cancel_hold::class,
        'description' => 'A reader lets go of a book they asked for.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'local_oksigeniaayvurenda_renew_loan' => [
        'classname' => \local_oksigeniaayvurenda\external\renew_loan::class,
        'description' => 'A reader gives one of their loans more time, where the library lets them.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'local_oksigeniaayvurenda_stocktake_read' => [
        'classname' => \local_oksigeniaayvurenda\external\stocktake_read::class,
        'description' => 'Notes the codes read in a stocktake.',
        'type' => 'write',
        'capabilities' => 'local/oksigeniaayvurenda:catalogue',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_oksigeniaayvurenda_stocktake_return' => [
        'classname' => \local_oksigeniaayvurenda\external\stocktake_return::class,
        'description' => 'Returns a copy found in a stocktake that was thought lent.',
        'type' => 'write',
        'capabilities' => 'local/oksigeniaayvurenda:catalogue, local/oksigeniaayvurenda:desk',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_oksigeniaayvurenda_lot_suggest' => [
        'classname' => \local_oksigeniaayvurenda\external\lot_suggest::class,
        'description' => 'The free copies whose book has the words typed, for a box being filled.',
        'type' => 'read',
        'capabilities' => 'local/oksigeniaayvurenda:desk',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_oksigeniaayvurenda_digital_place' => [
        'classname' => \local_oksigeniaayvurenda\external\digital_place::class,
        'description' => 'Keeps where someone is in a digital book or recording, and their bookmarks.',
        'type' => 'write',
        'capabilities' => 'local/oksigeniaayvurenda:view',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_oksigeniaayvurenda_howto_ask' => [
        'classname' => \local_oksigeniaayvurenda\external\howto_ask::class,
        'description' => 'The tasks and settings that answer what the staff ask.',
        'type' => 'read',
        'capabilities' => 'local/oksigeniaayvurenda:desk, local/oksigeniaayvurenda:catalogue, local/oksigeniaayvurenda:manage',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_oksigeniaayvurenda_hunt_found' => [
        'classname' => \local_oksigeniaayvurenda\external\hunt_found::class,
        'description' => 'Keeps the hidden book a reader found in the visit.',
        'type' => 'write',
        'capabilities' => 'local/oksigeniaayvurenda:view',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_oksigeniaayvurenda_relay_push' => [
        'classname' => \local_oksigeniaayvurenda\external\relay_push::class,
        'description' => 'Leaves a code read with a phone for its user\'s other screen.',
        'type' => 'write',
        'capabilities' => 'local/oksigeniaayvurenda:desk, local/oksigeniaayvurenda:catalogue',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_oksigeniaayvurenda_relay_pull' => [
        'classname' => \local_oksigeniaayvurenda\external\relay_pull::class,
        'description' => 'Takes the codes a phone left for this person\'s screen.',
        'type' => 'write',
        'capabilities' => 'local/oksigeniaayvurenda:desk, local/oksigeniaayvurenda:catalogue',
        'ajax' => true,
        'loginrequired' => true,
    ],
];
