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
 * The library in the Moodle app (and in apps made from it): an entry of the main menu with what a reader has.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$addons = [
    'local_oksigeniaayvurenda' => [
        'handlers' => [
            'library' => [
                'delegate' => 'CoreMainMenuDelegate',
                'method' => 'mobile_library_view',
                'init' => 'mobile_init',
                'displaydata' => [
                    'title' => 'mobilelibrary',
                    // Not «fas-book-open»: Aty's «My courses» has it, right beside this one.
                    'icon' => 'fas-book-open-reader',
                ],
                'priority' => 500,
                // The official app does not apply the site's mobile CSS, but it does load a handler's sheet.
                'styles' => [
                    'url' => $CFG->wwwroot . '/local/oksigeniaayvurenda/mobilecss.php?v=' .
                        crc32((string) file_get_contents($CFG->dirroot . '/local/oksigeniaayvurenda/app/mobile.css')),
                    'version' => crc32((string) file_get_contents($CFG->dirroot . '/local/oksigeniaayvurenda/app/mobile.css')),
                ],
            ],
        ],
        'lang' => [
            ['mobilelibrary', 'local_oksigeniaayvurenda'],
        ],
    ],
];
