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
 * Capabilities.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Checked in the category of each centre when it has one (several centres on one site), else on the whole site.
$capabilities = [
    // Look up the catalogue: readers, families, teachers.
    'local/oksigeniaayvurenda:view' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => [
            'user' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],
    // Lend and take back at the desk.
    'local/oksigeniaayvurenda:desk' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],
    // Catalogue: add and change records and copies, import.
    'local/oksigeniaayvurenda:catalogue' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],
    // Set the plan of each centre, for libraries hosted as a service: for the site's administrators, never the staff.
    'local/oksigeniaayvurenda:plans' => [
        'riskbitmask' => RISK_CONFIG,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [],
    ],
    // Set up libraries, rules, readers and options.
    'local/oksigeniaayvurenda:manage' => [
        'riskbitmask' => RISK_CONFIG | RISK_PERSONAL,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],
];
