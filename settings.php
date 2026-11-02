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
        // Hosts of the site's own network a connector may reach (a bridge inside the server); none by default.
        $settings->add(new admin_setting_configtext(
            'local_oksigeniaayvurenda/internalhosts',
            $str('internalhosts'),
            $str('internalhosts_desc'),
            '',
            PARAM_TEXT,
            50
        ));
        // Hosts of the site's own network that copies may go to (a NAS); none by default, and none on a shared site.
        $settings->add(new admin_setting_configtext(
            'local_oksigeniaayvurenda/offsitehosts',
            $str('offsitehosts'),
            $str('offsitehosts_desc'),
            '',
            PARAM_TEXT,
            50
        ));
        // The site's client at Google, for centres to keep their copies in their own Drive; none, and it is not offered.
        $settings->add(new admin_setting_configtext(
            'local_oksigeniaayvurenda/driveclientid',
            $str('driveclientid'),
            $str('driveclientid_desc'),
            '',
            PARAM_RAW_TRIMMED,
            60
        ));
        $settings->add(new admin_setting_configpasswordunmask(
            'local_oksigeniaayvurenda/driveclientsecret',
            $str('driveclientsecret'),
            '',
            ''
        ));
        // A line on every page of the library, for whoever runs the site (an open demo that starts again each night).
        $settings->add(new admin_setting_configtextarea(
            'local_oksigeniaayvurenda/sitenotice',
            $str('sitenotice'),
            $str('sitenotice_desc'),
            '',
            PARAM_TEXT,
            60,
            3
        ));
        // The class island in 3D, in «My class»: on unless the site's computers cannot carry it.
        $settings->add(new admin_setting_configcheckbox(
            'local_oksigeniaayvurenda/island3d',
            $str('island3d'),
            $str('island3d_desc'),
            1
        ));
        // A site that is only a library: its entry and its home are the library's.
        $settings->add(new admin_setting_configcheckbox(
            'local_oksigeniaayvurenda/libraryonly',
            $str('libraryonly'),
            $str('libraryonly_desc'),
            0
        ));
        $settings->add(new admin_setting_configtext(
            'local_oksigeniaayvurenda/libraryonlylegal',
            $str('libraryonlylegal'),
            $str('libraryonlylegal_desc'),
            '',
            PARAM_URL
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
        $features = ['planlti' => 1, 'planconnectors' => 1, 'planvoices' => 1, 'planoffsite' => 1, 'planapp' => 0,
            'planmadewith' => 0];
        foreach ($features as $feature => $default) {
            $settings->add(new admin_setting_configcheckbox(
                'local_oksigeniaayvurenda/' . $feature,
                $str($feature),
                $str($feature . '_desc'),
                $default
            ));
        }
    }
}
