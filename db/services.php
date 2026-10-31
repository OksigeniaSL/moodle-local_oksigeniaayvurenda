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
 * The library's web services: what the Moodle app (and apps made from it) asks for.
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
];
