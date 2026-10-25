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
 * The site's settings: the plan a new centre starts with, for libraries hosted as a service.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_oksigeniaayvurenda', new lang_string('pluginname', 'local_oksigeniaayvurenda'));
    $ADMIN->add('localplugins', $settings);
    if ($ADMIN->fulltree) {
        $str = fn(string $key) => new lang_string($key, 'local_oksigeniaayvurenda');
        // The Z39.50 bridge: catalogues that only speak Z39.50 (REBECA), asked as SRU through a service of Oksigenia.
        $settings->add(new admin_setting_configtext(
            'local_oksigeniaayvurenda/z3950bridge',
            $str('z3950bridge'),
            $str('z3950bridge_desc'),
            '',
            PARAM_URL,
            50
        ));
        // Several centres on one site: each with its own category, where the roles of its staff are given.
        $settings->add(new admin_setting_heading(
            'local_oksigeniaayvurenda/centresheading',
            $str('centresheading'),
            $str('centresheading_desc')
        ));
        $settings->add(new admin_setting_configcheckbox(
            'local_oksigeniaayvurenda/entitycategories',
            $str('entitycategories'),
            $str('entitycategories_desc'),
            0
        ));
        $settings->add(new admin_setting_heading(
            'local_oksigeniaayvurenda/planheading',
            $str('planheading'),
            $str('planheading_desc')
        ));
        $settings->add(new admin_setting_configtext(
            'local_oksigeniaayvurenda/planname',
            $str('planname'),
            $str('planname_desc'),
            '',
            PARAM_TEXT,
            30
        ));
        foreach (['plancopies', 'planreaders'] as $limit) {
            $settings->add(new admin_setting_configtext(
                'local_oksigeniaayvurenda/' . $limit,
                $str($limit),
                $str($limit . '_desc'),
                0,
                PARAM_INT,
                8
            ));
        }
        foreach (['planlti' => 1, 'planconnectors' => 1, 'planvoices' => 1, 'planmadewith' => 0] as $feature => $default) {
            $settings->add(new admin_setting_configcheckbox(
                'local_oksigeniaayvurenda/' . $feature,
                $str($feature),
                $str($feature . '_desc'),
                $default
            ));
        }
    }
}
