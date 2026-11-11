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
 * «Your first day here»: what someone who comes to the desk of a library already open does every day.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * The same piece as «To open your library», for a person instead of a centre: lending, taking a book back and printing
 * a card, each marked when this person has done it here, so a librarian of years never sees it and whoever comes new sees
 * it until the third thing is done, from the desk or from anywhere. It is folded to a line and stays as each one left it.
 */
final class firstday {
    /** @var array[] Of each step: its page, its guided help, its colour and its height on the shelf. */
    private const LOOK = [
        'lend' => ['desk.php', 'desk', '#17574c', 'cream', 28],
        'giveback' => ['desk.php', 'desk', '#e2614a', 'ink', 27],
        'cards' => ['cardprint.php', 'cardprint', '#f2b632', 'ink', 26],
    ];

    /** @var string The preference that keeps it folded, for whoever folded it. */
    public const FOLDED = 'local_oksigeniaayvurenda_firstdayfolded';

    /**
     * What this person has done of each step in the centre of a library.
     *
     * @param \stdClass $library
     * @param int $userid
     * @return bool[] Of each step, whether it is done.
     */
    public static function done(\stdClass $library, int $userid): array {
        global $DB;
        $entityid = (int) $library->entityid;
        $libraries = array_map('intval', $DB->get_fieldset_select(
            'local_oksigeniaayvurenda_library',
            'id',
            'entityid = ?',
            [$entityid]
        ));
        [$in, $params] = $libraries ? $DB->get_in_or_equal($libraries) : ['= ?', [0]];
        return [
            'lend' => $DB->record_exists_select(
                'local_oksigeniaayvurenda_loan',
                'libraryid ' . $in . ' AND staffid = ?',
                array_merge($params, [$userid])
            ),
            'giveback' => $DB->record_exists_select(
                'local_oksigeniaayvurenda_loan',
                'libraryid ' . $in . ' AND returnstaffid = ?',
                array_merge($params, [$userid])
            ),
            'cards' => activity::last($entityid, ['cards_made'], $userid) !== null,
        ];
    }

    /**
     * What the template open_steps takes, or null when it does not show: the library is not open yet (its five steps
     * show instead), the person does not work at its desk, or everything is done.
     *
     * @param \stdClass $library
     * @param int $userid
     * @return array|null
     */
    public static function context(\stdClass $library, int $userid): ?array {
        $entityid = (int) $library->entityid;
        if (access::closed($entityid) || practice::is($entityid) || sample::is($entityid)) {
            return null;
        }
        if (!access::can('local/oksigeniaayvurenda:desk', $library, $userid) && !access::keeps($library, $userid)) {
            return null;
        }
        $done = self::done($library, $userid);
        if (!in_array(false, $done, true)) {
            return null;
        }
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        $now = array_search(false, $done, true);
        $rows = [];
        $n = 0;
        foreach (self::LOOK as $key => [$page, $piece, $bg, $ink, $cm]) {
            $n++;
            // At the desk, straight to the box where the card and the book are passed.
            $url = (new \moodle_url('/local/oksigeniaayvurenda/' . $page, ['library' => $library->slug]))->out(false)
                . ($page === 'desk.php' ? '#ayv-code' : '');
            $help = howto::playable($piece, $library) ? (new \moodle_url(
                '/local/oksigeniaayvurenda/help.php',
                ['library' => $library->slug, 'piece' => $piece]
            ))->out(false) : '';
            $rows[] = ['n' => $n, 'name' => $str('firstday' . $key), 'spine' => $str('firstdayspine' . $key),
                'short' => $str('firstdayshort' . $key), 'what' => $str('firstdaywhat' . $key), 'url' => $url,
                'showurl' => $help, 'bg' => $bg, 'ink' => $ink, 'cm' => $cm, 'done' => $done[$key],
                'now' => $key === $now, 'todo' => !$done[$key] && $key !== $now, 'blocked' => false, 'notyours' => false,
                'can' => true, 'meta' => '', 'reason' => '', 'who' => '', 'confirm' => null];
        }
        $name = $str('firstday' . $now);
        return [
            'id' => 'ayv-firstday', 'big' => false,
            'folded' => (bool) get_user_preferences(self::FOLDED, false, $userid),
            'eyebrow' => '', 'title' => $str('firstdaytitle'), 'lead' => $str('firstdaylead'),
            'count' => $str('opencount', (object) ['done' => count(array_filter($done)), 'total' => count($done)]),
            'nowlabel' => $str('opennow'),
            'nowname' => \core_text::strtolower(\core_text::substr($name, 0, 1)) . \core_text::substr($name, 1),
            'nowurl' => $rows[array_search($now, array_keys(self::LOOK), true)]['url'],
            'dolabel' => $str('opendo'), 'showlabel' => $str('openshow'), 'foldlabel' => $str('openfold'),
            'unfoldlabel' => $str('openunfold'), 'donelabel' => $str('opendone'), 'nowmark' => $str('opennowmark'),
            'steps' => $rows,
            'foldurl' => (new \moodle_url('/local/oksigeniaayvurenda/open.php', ['which' => 'firstday']))->out(false),
            'sesskey' => sesskey(),
            'crier' => null,
        ];
    }
}
