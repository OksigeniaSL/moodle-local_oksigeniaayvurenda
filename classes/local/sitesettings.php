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
 * The site's settings of the library, given their values when it is installed or upgraded.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * None of the site's settings is needed to start a library: they are for hosting several centres or for services from
 * outside. Moodle shows every setting without a value on «New settings» after an upgrade, so whoever installs it met
 * some twenty technical fields (and two of LDAP, one for each of the library's roles) before seeing the library at all.
 * Each gets its default as soon as it exists, and Moodle stops asking; the settings page is still there for whoever
 * looks for it. Only what has no value is touched: what someone chose stays.
 */
final class sitesettings {
    /**
     * Puts the settings of the library on its page of the site's administration. The same page is made to give them
     * their values while the plugin is being installed, when Moodle's administration does not list it yet.
     *
     * @param \admin_settingpage $settings
     */
    public static function fill(\admin_settingpage $settings): void {
        $str = fn(string $key) => new \lang_string($key, 'local_oksigeniaayvurenda');
        // First of all: none of this is needed to start, and where the library is set up instead.
        $settings->add(new \admin_setting_heading(
            'local_oksigeniaayvurenda/start',
            $str('settingsstart'),
            new \lang_string(
                'settingsstart_desc',
                'local_oksigeniaayvurenda',
                (new \moodle_url('/local/oksigeniaayvurenda/index.php'))->out(false)
            )
        ));
        // The Z39.50 bridge: catalogues that only speak Z39.50 (REBECA), asked as SRU through a service of Oksigenia.
        $settings->add(new \admin_setting_configtext(
            'local_oksigeniaayvurenda/z3950bridge',
            $str('z3950bridge'),
            $str('z3950bridge_desc'),
            '',
            PARAM_URL,
            50
        ));
        // Hosts of the site's own network a connector may reach (a bridge inside the server); none by default.
        $settings->add(new \admin_setting_configtext(
            'local_oksigeniaayvurenda/internalhosts',
            $str('internalhosts'),
            $str('internalhosts_desc'),
            '',
            PARAM_TEXT,
            50
        ));
        // Hosts of the site's own network that copies may go to (a NAS); none by default, and none on a shared site.
        $settings->add(new \admin_setting_configtext(
            'local_oksigeniaayvurenda/offsitehosts',
            $str('offsitehosts'),
            $str('offsitehosts_desc'),
            '',
            PARAM_TEXT,
            50
        ));
        // The site's client at Google, for centres to keep their copies in their own Drive; none, and it is not offered.
        $settings->add(new \admin_setting_configtext(
            'local_oksigeniaayvurenda/driveclientid',
            $str('driveclientid'),
            $str('driveclientid_desc'),
            '',
            PARAM_RAW_TRIMMED,
            60
        ));
        $settings->add(new \admin_setting_configpasswordunmask(
            'local_oksigeniaayvurenda/driveclientsecret',
            $str('driveclientsecret'),
            '',
            ''
        ));
        // A line on every page of the library, for whoever runs the site (an open demo that starts again each night).
        $settings->add(new \admin_setting_configtextarea(
            'local_oksigeniaayvurenda/sitenotice',
            $str('sitenotice'),
            $str('sitenotice_desc'),
            '',
            PARAM_TEXT,
            60,
            3
        ));
        // The class island in 3D, in «My class»: on unless the site's computers cannot carry it.
        $settings->add(new \admin_setting_configcheckbox(
            'local_oksigeniaayvurenda/island3d',
            $str('island3d'),
            $str('island3d_desc'),
            1
        ));
        // A site that is only a library: its entry and its home are the library's.
        $settings->add(new \admin_setting_configcheckbox(
            'local_oksigeniaayvurenda/libraryonly',
            $str('libraryonly'),
            $str('libraryonly_desc'),
            0
        ));
        $settings->add(new \admin_setting_configtext(
            'local_oksigeniaayvurenda/libraryonlylegal',
            $str('libraryonlylegal'),
            $str('libraryonlylegal_desc'),
            '',
            PARAM_URL
        ));
        // Several centres on one site: each with its own category, where the roles of its staff are given.
        $settings->add(new \admin_setting_heading(
            'local_oksigeniaayvurenda/centresheading',
            $str('centresheading'),
            $str('centresheading_desc')
        ));
        $centres = new \admin_setting_configcheckbox(
            'local_oksigeniaayvurenda/entitycategories',
            $str('entitycategories'),
            $str('entitycategories_desc'),
            0
        );
        // Where the library manager's role may be given follows it: on the site with one centre, in categories with many.
        $centres->set_updatedcallback(fn() => roles::ensure_manager());
        $settings->add($centres);
        $settings->add(new \admin_setting_heading(
            'local_oksigeniaayvurenda/planheading',
            $str('planheading'),
            $str('planheading_desc')
        ));
        $settings->add(new \admin_setting_configtext(
            'local_oksigeniaayvurenda/planname',
            $str('planname'),
            $str('planname_desc'),
            '',
            PARAM_TEXT,
            30
        ));
        foreach (['plancopies', 'planreaders'] as $limit) {
            $settings->add(new \admin_setting_configtext(
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
            $settings->add(new \admin_setting_configcheckbox(
                'local_oksigeniaayvurenda/' . $feature,
                $str($feature),
                $str($feature . '_desc'),
                $default
            ));
        }
    }

    /**
     * Gives every setting of the library that has no value its default, and the LDAP context of its roles an empty one.
     *
     * @return string[] The names of the settings given a value now.
     */
    public static function settle(): array {
        global $CFG;
        require_once($CFG->libdir . '/adminlib.php');
        $done = [];
        // Not the page of Moodle's administration: while the plugin is installed, it is not there yet.
        $page = new \admin_settingpage('local_oksigeniaayvurenda', 'local_oksigeniaayvurenda');
        self::fill($page);
        foreach ($page->settings as $setting) {
            $default = $setting->get_defaultsetting();
            if ($setting->get_setting() !== null || $default === null) {
                continue;
            }
            $setting->write_setting($default);
            $done[] = $setting->get_full_name();
        }
        // LDAP offers a context for every role of the site, and ours would sit on «New settings» empty all the same.
        if (\core_component::get_component_directory('auth_ldap')) {
            foreach ([roles::LIBRARIAN, roles::MANAGER] as $shortname) {
                if (get_config('auth_ldap', $shortname . 'context') === false) {
                    set_config($shortname . 'context', '', 'auth_ldap');
                    $done[] = 'auth_ldap/' . $shortname . 'context';
                }
            }
        }
        return $done;
    }
}
