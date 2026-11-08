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

namespace local_oksigeniaayvurenda\local;

/**
 * The library in the organisation's channel of Telegram, through Oksigenia Marandu when the site has it: a book the
 * library recommends, from its record, and the big numbers of the year as they pass round milestones (Enrique: small
 * ones look bad). Never anything of a reader. Each post goes to whoever reviews the channel first, unless the site says
 * otherwise. Only on a site that is one organisation: a site shared by centres has no channel of a centre.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class channel {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var string The plugin, for Marandu. */
    private const COMPONENT = 'local_oksigeniaayvurenda';

    /** @var int[] The round numbers of books lent in a year that are worth telling. */
    public const MILESTONES = [1000, 2000, 5000, 10000, 20000, 50000, 100000, 200000, 500000];

    /**
     * Whether there is a channel to post in.
     *
     * @return bool
     */
    public static function ready(): bool {
        if (get_config('local_oksigeniaayvurenda', 'entitycategories')) {
            return false;
        }
        return class_exists('\local_oksigeniamarandu\api') && method_exists('\local_oksigeniamarandu\api', 'channel_ready')
            && \local_oksigeniamarandu\api::channel_ready();
    }

    /**
     * What a recommended book says in the channel: its title, author and age, a short summary, its card with its cover,
     * and the way to its record.
     *
     * @param \stdClass $library
     * @param int $recordid
     * @return array|null Null for a record that is not of this centre.
     */
    public static function book(\stdClass $library, int $recordid): ?array {
        $found = catalogue::record($recordid, $library);
        if (!$found) {
            return null;
        }
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        $title = (string) $found['record']->title;
        $author = (string) ($found['card']['authors'] ?? '');
        $age = self::age($recordid);
        $summary = trim((string) $found['record']->summary);
        if (\core_text::strlen($summary) > 280) {
            $summary = rtrim(\core_text::substr($summary, 0, 277)) . '…';
        }
        $url = (new \moodle_url(window::catalogue_open($library) ? '/local/oksigeniaayvurenda/public.php'
            : '/local/oksigeniaayvurenda/record.php', window::catalogue_open($library)
            ? ['library' => $library->slug, 'record' => $recordid] : ['library' => $library->slug, 'id' => $recordid]))->out(false);
        $cover = covers::url($recordid);
        return array_filter([
            'title' => $title,
            'lines' => array_values(array_filter([$author, $age])),
            'text' => $summary,
            'url' => $url,
            'label' => $str('channelbooklink'),
            'card' => ['type' => 'book', 'zones' => array_filter([
                'kicker' => $str('channelbookkicker'),
                'title' => $title,
                'author' => $author,
                'detail' => $age,
                'cover' => $cover ?: null,
            ], fn($v) => $v !== null && $v !== '')],
        ], fn($v) => $v !== '' && $v !== []);
    }

    /**
     * Sends a book the library recommends to the channel, once per book.
     *
     * @param \stdClass $library
     * @param int $recordid
     * @return int Marandu's post, or 0 if it does not go.
     */
    public static function post_book(\stdClass $library, int $recordid): int {
        $content = self::ready() ? self::book($library, $recordid) : null;
        if (!$content) {
            return 0;
        }
        return (int) \local_oksigeniamarandu\api::channel_post(
            self::COMPONENT,
            'book',
            $content,
            null,
            false,
            'book-' . $recordid
        );
    }

    /**
     * The highest round number a count has passed.
     *
     * @param int $count
     * @return int 0 below the first.
     */
    public static function milestone(int $count): int {
        $reached = 0;
        foreach (self::MILESTONES as $milestone) {
            if ($count >= $milestone) {
                $reached = $milestone;
            }
        }
        return $reached;
    }

    /**
     * The big number of a centre's year: the books its reading libraries lent since the year began, and the card that
     * tells it once it passes a round number.
     *
     * @param int $entityid
     * @param int $now
     * @return array count, milestone, key and content (null below the first milestone).
     */
    public static function number(int $entityid, int $now): array {
        global $DB;
        $year = years::around($entityid, $now);
        $from = (int) $year->timestart;
        $count = $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {" . self::T . "loan} l
               JOIN {" . self::T . "library} b ON b.id = l.libraryid
              WHERE b.entityid = :entityid AND l.timeloaned >= :from AND l.timeloaned <= :now
                    AND " . bank::reading('l.libraryid'),
            ['entityid' => $entityid, 'from' => $from, 'now' => $now]
        );
        $milestone = self::milestone($count);
        $out = ['count' => $count, 'milestone' => $milestone, 'key' => '', 'content' => null];
        if (!$milestone) {
            return $out;
        }
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        $number = screens::number($milestone);
        $phrase = skins::say($entityid, 'channelnumberphrase');
        $detail = $str('channelnumberdetail', (object) ['from' => zones::date($from, 'strftimedate', $entityid),
            'to' => zones::date($now, 'strftimedate', $entityid)]);
        $out['key'] = 'big_number-' . $entityid . '-' . zones::date($from, '%Y', $entityid) . '-' . $milestone;
        $out['content'] = [
            'title' => $str('channelnumbertitle', (object) ['number' => $number, 'phrase' => $phrase]),
            'text' => $detail,
            'card' => ['type' => 'big_number', 'zones' => ['kicker' => $str('channelnumberkicker'), 'number' => $number,
                'phrase' => $phrase, 'detail' => $detail]],
        ];
        return $out;
    }

    /**
     * Each morning of a centre, its big number to the channel when it passed a new round one this year; each milestone
     * once (its key), and only for centres that are open and real.
     *
     * @param int $now
     * @param int[] $centres The centres whose morning it is.
     * @return int How many went.
     */
    public static function numbers(int $now, array $centres): int {
        if (!$centres || !self::ready()) {
            return 0;
        }
        $sent = 0;
        foreach ($centres as $entityid) {
            $entityid = (int) $entityid;
            if (practice::is($entityid) || access::closed($entityid)) {
                continue;
            }
            $number = self::number($entityid, $now);
            if (
                $number['content'] && \local_oksigeniamarandu\api::channel_post(
                    self::COMPONENT,
                    'big_number',
                    $number['content'],
                    null,
                    false,
                    $number['key']
                )
            ) {
                $sent++;
            }
        }
        return $sent;
    }

    /**
     * The ages a book is to be read at, when the library said so for reading it at home: «3 to 5 years».
     *
     * @param int $recordid
     * @return string
     */
    private static function age(int $recordid): string {
        global $DB;
        $ages = [];
        foreach (
            $DB->get_fieldset_select(
                self::T . 'digital',
                'homeages',
                'recordid = ? AND homeages IS NOT NULL',
                [$recordid]
            ) as $list
        ) {
            $ages = array_merge($ages, array_map('intval', explode(',', (string) $list)));
        }
        $ages = array_values(array_unique($ages));
        sort($ages);
        return implode(', ', array_map(fn($age) => get_string('talesage' . $age, 'local_oksigeniaayvurenda'), $ages));
    }
}
