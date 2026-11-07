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
     * Whether a user signed up for a centre's news (or for every centre's, as before 0.195).
     *
     * @param int $userid
     * @param int $entityid The centre; 0 only for the old sign-up for all.
     * @return bool
     */
    public static function subscribed(int $userid, int $entityid = 0): bool {
        // Before 0.195 one asked for every centre at once.
        if (get_user_preferences(self::PREF, false, $userid)) {
            return true;
        }
        return $entityid > 0 && (bool) get_user_preferences(self::PREF . '_' . $entityid, false, $userid);
    }

    /**
     * Signs a user up for a centre's news, or off: someone with a card in two centres chooses in each (D14).
     *
     * @param int $userid
     * @param bool $on
     * @param int $entityid The centre; 0 for every centre, as before 0.195.
     */
    public static function subscribe(int $userid, bool $on, int $entityid = 0): void {
        global $DB;
        if ($entityid > 0) {
            // Signed up before for every centre: each of them keeps it, and only this one changes.
            if (get_user_preferences(self::PREF, false, $userid)) {
                $centres = $DB->get_fieldset_select(
                    self::T . 'reader',
                    'entityid',
                    'userid = :userid AND status = :status',
                    ['userid' => $userid, 'status' => 'active']
                );
                foreach (array_unique(array_map('intval', $centres)) as $centre) {
                    set_user_preference(self::PREF . '_' . $centre, 1, $userid);
                }
                unset_user_preference(self::PREF, $userid);
            }
            set_user_preference(self::PREF . '_' . $entityid, $on ? 1 : null, $userid);
        } else {
            set_user_preference(self::PREF, $on ? 1 : null, $userid);
        }
        $any = $DB->record_exists_select(
            'user_preferences',
            'userid = :userid AND value = :value AND (name = :name OR ' . $DB->sql_like('name', ':centre') . ')',
            ['userid' => $userid, 'value' => '1', 'name' => self::PREF, 'centre' => $DB->sql_like_escape(self::PREF . '_') . '%']
        );
        if (!$any) {
            unset_user_preference(self::LAST, $userid);
        }
    }

    /**
     * Sends the news to whoever signed up and has not had them for a week, from the libraries of their centres (not the
     * textbook banks, nor a centre open only to its staff).
     *
     * @param int|null $now
     * @param int[]|null $centres Only these centres (those whose morning it is); all without it.
     * @return array subscribers, sent.
     */
    public static function run(?int $now = null, ?array $centres = null): array {
        global $DB;
        $now = $now ?? time();
        $stats = ['subscribers' => 0, 'sent' => 0];
        $userids = array_unique(array_map('intval', $DB->get_fieldset_select(
            'user_preferences',
            'userid',
            'value = :value AND (name = :name OR ' . $DB->sql_like('name', ':centre') . ')',
            ['value' => '1', 'name' => self::PREF, 'centre' => $DB->sql_like_escape(self::PREF . '_') . '%']
        )));
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
            // Since the last letter, however long ago (a week without news is not lost); the first time, a week.
            $since = $last ? $last + 1 : $now - WEEKSECS;
            foreach ($DB->get_records(self::T . 'reader', ['userid' => $userid, 'status' => 'active']) as $reader) {
                $entityid = (int) $reader->entityid;
                if (
                    access::closed($entityid) || ($centres !== null && !in_array($entityid, $centres, true))
                        || !self::subscribed((int) $userid, $entityid)
                ) {
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
     * What came into a centre's libraries (not its textbook banks) since a moment, the newest first. A catalogue brought in whole
     * from another program did not just come: what an import brings counts only if it says it was acquired in that time.
     *
     * @param int $entityid
     * @param int $since
     * @return array total, titles (record => title, up to LISTED), library (the centre's main one: slug and name), or no library.
     */
    public static function news(int $entityid, int $since): array {
        global $DB;
        $params = ['entityid' => $entityid, 'since' => $since, 'kind' => bank::KIND, 'imported' => $since, 'acquired' => $since];
        $from = "FROM {" . self::T . "item} i
                 JOIN {" . self::T . "library} b ON b.id = i.libraryid
                 JOIN {" . self::T . "record} r ON r.id = i.recordid
            LEFT JOIN {" . self::T . "source} s ON s.tablename = 'record' AND s.localid = r.id AND s.timecreated >= :imported
                WHERE b.entityid = :entityid AND b.kind <> :kind AND i.timecreated >= :since AND i.status <> 'withdrawn'
                      AND (s.id IS NULL OR i.timeacquired >= :acquired)";
        $total = (int) $DB->count_records_sql("SELECT COUNT(DISTINCT r.id) $from", $params);
        $titles = $total ? array_map(fn($r) => $r->title, $DB->get_records_sql(
            "SELECT r.id, r.title, MAX(i.timecreated) AS came $from GROUP BY r.id, r.title ORDER BY MAX(i.timecreated) DESC, r.id",
            $params,
            0,
            self::LISTED
        )) : [];
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
        global $DB;
        // In their language, wherever the task runs; in the tone of the centre's skin.
        $language = force_current_language((string) $user->lang);
        $words = fn(string $key) => skins::words($entityid, $key);
        $str = fn(string $key, $a = null) => get_string($words($key), 'local_oksigeniaayvurenda', $a);
        $lines = array_map(fn($t) => get_string('newbooksline', 'local_oksigeniaayvurenda', $t), $news['titles']);
        $more = '';
        if ($news['total'] > count($news['titles'])) {
            $left = $news['total'] - count($news['titles']);
            $more = get_string('newbooksmore', 'local_oksigeniaayvurenda', screens::number($left));
            $lines[] = $more;
        }
        $a = (object) ['name' => $user->firstname, 'library' => format_string($news['library']->name, true, ['escape' => false]),
            'books' => implode("\n", $lines)];
        $body = $str('newbooksbody', $a);
        $herald = characters::of($entityid)['herald'];
        if ($herald['enabled'] && $herald['signs']) {
            $body .= "\n\n" . characters::say($entityid, 'herald', 'heraldsign')['text'];
        }
        $url = (new \moodle_url('/local/oksigeniaayvurenda/index.php', ['library' => $news['library']->slug]))->out(false);
        $subject = $news['total'] === 1 ? $str('newbookssubjectone') : $str('newbookssubject', screens::number($news['total']));
        $shelf = [];
        foreach ($news['titles'] as $recordid => $title) {
            $shelf[] = mail::book((int) $recordid, $title);
        }
        $sent = notices::send(
            'newbooks',
            $user,
            $subject,
            $body,
            $url,
            format_string($news['library']->name, true, ['escape' => false]),
            '',
            [
                'library' => $DB->get_record(self::T . 'library', ['id' => $news['library']->id]),
                'hello' => get_string('mailhello', 'local_oksigeniaayvurenda', $user->firstname),
                'heading' => $subject,
                'lead' => [$str('newbookslead')],
                'shelf' => $shelf,
                'more' => $more,
                'button' => ['url' => $url, 'text' => get_string('newbooksbutton', 'local_oksigeniaayvurenda')],
                'after' => [$str('newbooksafter')],
                'sign' => mail::sign($entityid),
                'why' => get_string('mailwhyreader', 'local_oksigeniaayvurenda'),
                'prefs' => true,
            ]
        );
        force_current_language($language);
        return $sent;
    }
}
