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
 * «See it full»: a sample library, made with one touch, to look around before setting up one's own.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * Whoever installs to see if it works wants to see it working, not empty. A centre of its own, apart, with some hundreds
 * of made-up books, readers in groups and a term of loans, in Spanish or in English as the site speaks, that says on
 * every page that it is made up. It is not the site's library: «start here» stays until there is one, its steps to open
 * never show, its readers have no accounts and it stays closed, so nobody but the staff ever sees it. It goes with one
 * touch, after saying so. Only on a site with one centre and for whoever runs it: on a shared site, a centre does not
 * make another.
 */
final class sample {
    /** @var string The flag in the settings of the centre that is the sample. */
    private const FLAG = 'sample';

    /**
     * Whether this person may make the sample library here.
     *
     * @return bool
     */
    public static function allowed(): bool {
        return !get_config('local_oksigeniaayvurenda', 'entitycategories')
            && has_capability('local/oksigeniaayvurenda:manage', \context_system::instance());
    }

    /**
     * The sample centre's library, if there is one.
     *
     * @return \stdClass|null
     */
    public static function library(): ?\stdClass {
        global $DB;
        foreach (
            $DB->get_records_select('local_oksigeniaayvurenda_entity', $DB->sql_like('settings', ':flag'), [
            'flag' => '%"' . self::FLAG . '":true%',
            ]) as $entity
        ) {
            $library = $DB->get_record(
                'local_oksigeniaayvurenda_library',
                ['entityid' => $entity->id, 'kind' => 'central'],
                '*',
                IGNORE_MULTIPLE
            );
            if ($library) {
                return $library;
            }
        }
        return null;
    }

    /**
     * Whether a centre is the sample.
     *
     * @param int $entityid
     * @return bool
     */
    public static function is(int $entityid): bool {
        global $DB;
        $settings = json_decode((string) $DB->get_field('local_oksigeniaayvurenda_entity', 'settings', ['id' => $entityid]), true);
        return !empty($settings[self::FLAG]);
    }

    /**
     * Makes the sample library, or gives the one there is.
     *
     * @return \stdClass Its library.
     */
    public static function make(): \stdClass {
        global $DB;
        $library = self::library();
        if ($library) {
            return $library;
        }
        $lang = str_starts_with(current_language(), 'es') ? 'es' : 'en';
        $entityid = demo::create(get_string('samplename', 'local_oksigeniaayvurenda'), 600, 150, 20261111, $lang);
        $settings = json_decode((string) $DB->get_field('local_oksigeniaayvurenda_entity', 'settings', ['id' => $entityid]), true)
            ?: [];
        $settings[self::FLAG] = true;
        $DB->set_field('local_oksigeniaayvurenda_entity', 'settings', json_encode($settings), ['id' => $entityid]);
        access::set_closed($entityid, true);
        $library = $DB->get_record(
            'local_oksigeniaayvurenda_library',
            ['entityid' => $entityid, 'kind' => 'central'],
            '*',
            MUST_EXIST
        );
        demo::activity((int) $library->id, 120);
        return $library;
    }

    /**
     * Takes the sample library away, whole.
     */
    public static function remove(): void {
        $library = self::library();
        if ($library) {
            removal::remove((int) $library->entityid);
        }
    }

    /**
     * The line on every page of the sample library: what it is, and for whoever may, the way to take it away.
     *
     * @param \stdClass $library
     * @return string HTML, or nothing.
     */
    public static function line(\stdClass $library): string {
        if (!self::is((int) $library->entityid)) {
            return '';
        }
        $gone = self::allowed() ? ' <a href="' . s((new \moodle_url('/local/oksigeniaayvurenda/libraries.php'))->out(false)
            . '#ayv-sample') . '">' . s(get_string('sampleremove', 'local_oksigeniaayvurenda')) . '</a>' : '';
        return '<p class="ayv-sitenotice ayv-sample-said" role="note">'
            . s(get_string('samplesaid', 'local_oksigeniaayvurenda')) . $gone . '</p>';
    }
}
