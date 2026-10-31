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
 * The library's news, for whoever signs up.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * The library's news, for whoever signs up: once a week at most, a Moodle message with the books that came in since the
 * last one, only when something did. Each reader chooses it on their card; nobody gets it without asking.
 */
final class newsletter {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var string The user preference that says they signed up. */
    public const PREF = 'local_oksigeniaayvurenda_newbooks';

    /** @var string The user preference with when the last one went. */
    public const LAST = 'local_oksigeniaayvurenda_newbookslast';

    /** @var int How many titles a message lists. */
    private const LISTED = 8;

    /**
     * Whether a user signed up.
     *
     * @param int $userid
     * @return bool
     */
    public static function subscribed(int $userid): bool {
        return (bool) get_user_preferences(self::PREF, false, $userid);
    }

    /**
     * Signs a user up, or off.
     *
     * @param int $userid
     * @param bool $on
     */
    public static function subscribe(int $userid, bool $on): void {
        set_user_preference(self::PREF, $on ? 1 : null, $userid);
        if (!$on) {
            unset_user_preference(self::LAST, $userid);
        }
    }

    /**
     * Sends the news to whoever signed up and has not had them for a week, from the libraries of their centres (not the
     * textbook banks, nor a centre open only to its staff).
     *
     * @param int|null $now
     * @return array subscribers, sent.
     */
    public static function run(?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $stats = ['subscribers' => 0, 'sent' => 0];
        $userids = $DB->get_fieldset_select(
            'user_preferences',
            'userid',
            'name = :name AND value = :value',
            ['name' => self::PREF, 'value' => '1']
        );
        foreach ($userids as $userid) {
            $stats['subscribers']++;
            $last = (int) get_user_preferences(self::LAST, 0, $userid);
            if ($last > $now - WEEKSECS + HOURSECS) {
                continue;
            }
            $user = \core_user::get_user((int) $userid);
            if (!$user || $user->deleted || $user->suspended) {
                continue;
            }
            $sent = false;
            $since = max($last, $now - WEEKSECS);
            foreach ($DB->get_records(self::T . 'reader', ['userid' => $userid, 'status' => 'active']) as $reader) {
                $entityid = (int) $reader->entityid;
                if (access::closed($entityid)) {
                    continue;
                }
                $news = self::news($entityid, $since);
                if ($news['total'] && self::send($user, $entityid, $news)) {
                    $sent = true;
                }
            }
            if ($sent) {
                set_user_preference(self::LAST, $now, $userid);
                $stats['sent']++;
            }
        }
        return $stats;
    }

    /**
     * What came into a centre's libraries (not its textbook banks) since a moment, the newest first.
     *
     * @param int $entityid
     * @param int $since
     * @return array total, titles (up to LISTED), library (the centre's main one: slug and name), or no library.
     */
    public static function news(int $entityid, int $since): array {
        global $DB;
        $params = ['entityid' => $entityid, 'since' => $since, 'kind' => bank::KIND];
        $from = "FROM {" . self::T . "item} i
                 JOIN {" . self::T . "library} b ON b.id = i.libraryid
                 JOIN {" . self::T . "record} r ON r.id = i.recordid
                WHERE b.entityid = :entityid AND b.kind <> :kind AND i.timecreated >= :since AND i.status <> 'withdrawn'";
        $total = (int) $DB->count_records_sql("SELECT COUNT(DISTINCT r.id) $from", $params);
        $titles = $total ? array_values(array_map(fn($r) => $r->title, $DB->get_records_sql(
            "SELECT r.id, r.title, MAX(i.timecreated) AS came $from GROUP BY r.id, r.title ORDER BY MAX(i.timecreated) DESC, r.id",
            $params,
            0,
            self::LISTED
        ))) : [];
        $library = $DB->get_records_select(
            self::T . 'library',
            'entityid = :entityid AND kind <> :kind',
            $params,
            "kind = 'central' DESC, id",
            'id, slug, name',
            0,
            1
        );
        return ['total' => $total, 'titles' => $titles, 'library' => $library ? reset($library) : null];
    }

    /**
     * One message, in the tone of the centre's skin, opening the library's home.
     *
     * @param \stdClass $user
     * @param int $entityid
     * @param array $news As news() gives it.
     * @return bool
     */
    private static function send(\stdClass $user, int $entityid, array $news): bool {
        if (!$news['library']) {
            return false;
        }
        $words = fn(string $key) => skins::words($entityid, $key);
        $str = fn(string $key, $a = null) => get_string($words($key), 'local_oksigeniaayvurenda', $a);
        $lines = array_map(fn($t) => get_string('newbooksline', 'local_oksigeniaayvurenda', $t), $news['titles']);
        if ($news['total'] > count($news['titles'])) {
            $lines[] = get_string('newbooksmore', 'local_oksigeniaayvurenda', $news['total'] - count($news['titles']));
        }
        $a = (object) ['name' => $user->firstname, 'library' => format_string($news['library']->name),
            'books' => implode("\n", $lines)];
        $body = $str('newbooksbody', $a);
        $herald = characters::of($entityid)['herald'];
        if ($herald['enabled'] && $herald['signs']) {
            $body .= "\n\n" . characters::say($entityid, 'herald', 'heraldsign')['text'];
        }
        $url = (new \moodle_url('/local/oksigeniaayvurenda/index.php', ['library' => $news['library']->slug]))->out(false);
        return notices::send(
            'newbooks',
            $user,
            $news['total'] === 1 ? $str('newbookssubjectone') : $str('newbookssubject', $news['total']),
            $body,
            $url,
            format_string($news['library']->name)
        );
    }
}
