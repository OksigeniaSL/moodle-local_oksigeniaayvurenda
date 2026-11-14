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
 * Caches.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$definitions = [
    // The words of each entity's catalogue, for the suggestions of a search that finds nothing.
    'vocabulary' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true,
    ],
    // How many records each corner of a centre has, for a few minutes (and gone when the catalogue changes).
    'corners' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'ttl' => 300,
    ],
    // What the catalogues said of an ISBN, for a day.
    'lookup' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
    ],
    // Who a machine that speaks SIP2 is serving, so that one visit counts once however many books it takes.
    'selfvisit' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true,
        'ttl' => 1800,
    ],
    // LTI 1.3: the nonces of the logins under way, the launches and the access tokens to the platforms' services.
    'lti' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'ttl' => 3600,
    ],
    // Who reads in which centre and which centre is the practice one, for one request (emptied between tests).
    'access' => [
        'mode' => cache_store::MODE_REQUEST,
        'simplekeys' => true,
        'simpledata' => true,
    ],
    // The main colour of each cover, by its content, for the spines on the shelves.
    'spinecolour' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
    ],
    // The people of a management platform while pairing its readers with those already there: asking takes a while.
    'platformpeople' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'ttl' => 3600,
    ],
    // Each person's card in «Your work» on the dashboard, for a few minutes: it is asked on every load of the page.
    'work' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'ttl' => 300,
    ],
    // The curious things of each library's catalogue, once a day: the longest title, the oldest book.
    'curiosities' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'ttl' => 2 * DAYSECS,
    ],
    // The pages asked for this minute from each address at the public doors (the shield against robots).
    'shield' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'ttl' => 120,
    ],
    // The moments to celebrate of each centre this week, for an hour: the home, the block and Aty ask for them.
    'moments' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'ttl' => 2 * HOURSECS,
    ],
];
