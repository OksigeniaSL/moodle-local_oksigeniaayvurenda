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

namespace local_oksigeniaayvurenda\local\sources;

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\mail;
use local_oksigeniaayvurenda\local\notices;

/**
 * Whether a source can be believed when it leaves readers out.
 *
 * A cohort that was deleted, an empty answer with a good status or a file for another school look just like a whole
 * library leaving. So many at once are held back, nobody leaves, and whoever manages the centre is told.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class leavers {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var int Fewer readers than this from a source: a small list, not worth stopping for. */
    private const SMALL = 5;

    /** @var string[] Each source, by the name of its card in «Where the readers come from». */
    private const CARDS = ['moodle' => 'cohorts', 'cifra' => 'cifra', 'oneroster' => 'oneroster'];

    /**
     * Whether those readers may leave. By hand, a person is looking and half can go; never all of them.
     *
     * @param int $entityid
     * @param string $system
     * @param int $had Active readers from this source.
     * @param int $leaving Those of them it no longer brings.
     * @param bool $byhand
     * @param bool $blind The source cannot say who is in it, however few leave.
     * @param int|null $now
     * @return bool
     */
    public static function allow(
        int $entityid,
        string $system,
        int $had,
        int $leaving,
        bool $byhand = false,
        bool $blind = false,
        ?int $now = null
    ): bool {
        if (!$leaving || (!$blind && self::believable($had, $leaving, $byhand))) {
            self::clear($entityid, $system);
            return true;
        }
        self::hold($entityid, $system, $had, $leaving, $now ?? time());
        return false;
    }

    /**
     * Whether so many leaving at once is believable.
     *
     * @param int $had
     * @param int $leaving
     * @param bool $byhand
     * @return bool
     */
    public static function believable(int $had, int $leaving, bool $byhand = false): bool {
        return $had < self::SMALL || ($leaving < $had && ($byhand || $leaving * 2 <= $had));
    }

    /**
     * What is being held back, to say it on the page of the sources.
     *
     * @param int $entityid
     * @return string[] A sentence for each source.
     */
    public static function held(int $entityid): array {
        $said = [];
        foreach (self::settings($entityid)['held'] ?? [] as $system => $row) {
            $said[] = get_string('readersheldbody', 'local_oksigeniaayvurenda', self::words($system, $row));
        }
        return $said;
    }

    /**
     * What the sentence says.
     *
     * @param string $system
     * @param array $row
     * @return \stdClass
     */
    private static function words(string $system, array $row): \stdClass {
        $card = self::CARDS[$system] ?? 'cohorts';
        return (object) ['leaving' => (int) $row['leaving'], 'had' => (int) $row['had'],
            'source' => get_string('sourcecard' . $card, 'local_oksigeniaayvurenda')];
    }

    /**
     * The settings of a centre.
     *
     * @param int $entityid
     * @return array
     */
    private static function settings(int $entityid): array {
        global $DB;
        return json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
    }

    /**
     * Saves the settings of a centre.
     *
     * @param int $entityid
     * @param array $settings
     */
    private static function save(int $entityid, array $settings): void {
        global $DB;
        $DB->set_field(self::T . 'entity', 'settings', json_encode($settings), ['id' => $entityid]);
    }

    /**
     * The source can be believed again.
     *
     * @param int $entityid
     * @param string $system
     */
    private static function clear(int $entityid, string $system): void {
        $settings = self::settings($entityid);
        if (isset($settings['held'][$system])) {
            unset($settings['held'][$system]);
            self::save($entityid, $settings);
        }
    }

    /**
     * Notes it down and, once a week at most, tells whoever manages the centre.
     *
     * @param int $entityid
     * @param string $system
     * @param int $had
     * @param int $leaving
     * @param int $now
     */
    private static function hold(int $entityid, string $system, int $had, int $leaving, int $now): void {
        $settings = self::settings($entityid);
        $told = (int) ($settings['held'][$system]['told'] ?? 0);
        $row = ['had' => $had, 'leaving' => $leaving, 'time' => $now, 'told' => $told];
        if ($now - $told >= 7 * DAYSECS) {
            $row['told'] = $now;
            self::tell($entityid, self::words($system, $row));
        }
        $settings['held'][$system] = $row;
        self::save($entityid, $settings);
    }

    /**
     * Tells whoever manages the centre.
     *
     * @param int $entityid
     * @param \stdClass $words
     */
    private static function tell(int $entityid, \stdClass $words): void {
        global $DB;
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        $libraries = $DB->get_records(self::T . 'library', ['entityid' => $entityid, 'parentid' => null], 'id', 'id, slug');
        $library = $libraries ? reset($libraries) : null;
        $url = $library ? (new \moodle_url('/local/oksigeniaayvurenda/sources.php', ['library' => $library->slug]))->out(false)
            : '';
        foreach (get_users_by_capability(access::context($entityid), 'local/oksigeniaayvurenda:manage', 'u.*') as $user) {
            notices::send(
                'readersheld',
                $user,
                $str('readersheldsubject'),
                $str('readersheldbody', $words),
                $url,
                $str('readerssources'),
                '',
                [
                    'library' => mail::main_library($entityid),
                    'entityid' => $entityid,
                    'heading' => $str('readersheldsubject'),
                    'lead' => [$str('readersheldbody', $words)],
                    'button' => $url !== '' ? ['url' => $url, 'text' => $str('readersheldbutton')] : null,
                    'why' => $str('mailwhystaff'),
                    'prefs' => true,
                ]
            );
        }
    }
}
