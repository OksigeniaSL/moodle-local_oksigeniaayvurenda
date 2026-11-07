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
 * Who did what in a centre, from Moodle's logs: whoever keeps a centre in a shared site has no way into the site's logs.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class activity {
    /** @var int Lines of a page. */
    public const PAGE = 50;

    /** @var array<string, string[]> The actions by what they touch; opening a record has its own, so it does not hide the rest. */
    public const KINDS = [
        'people' => ['reader_card_replaced', 'reader_changed', 'cards_made',
            'reader_merged', 'reader_paired', 'reader_matched', 'readers_synced', 'connector_changed', 'connector_tried',
            'tutor_changed', 'keeper_added', 'keeper_removed', 'applink_sent', 'entry_given', 'voice_published', 'voice_removed',
            'voice_consent_changed', 'sanction_settings_changed', 'sanction_lifted', 'fine_paid', 'fine_waived', 'charge_added',
            'sanction_reopened', 'reader_needs_changed', 'reader_erased'],
        'copies' => ['offsite_key_created', 'offsite_place_added', 'offsite_place_removed', 'offsite_pull_created',
            'offsite_pull_stopped', 'copy_downloaded', 'copy_recovered', 'report_exported'],
        'books' => ['copies_withdrawn', 'copy_restored', 'stocktake_applied', 'catalogue_imported', 'catalogue_corrected',
            'addition_undone',
            'digital_added', 'digital_removed', 'textbook_lost', 'book_lost', 'format_asked', 'format_answered'],
        'centre' => ['plan_changed', 'skin_changed', 'centre_closed', 'centre_opened', 'centre_created', 'centre_removed',
            'library_deleted', 'window_changed', 'rules_changed', 'hold_settings_changed', 'voice_settings_changed',
            'purchase_ordered', 'budget_changed', 'self_post_created', 'self_post_removed', 'self_post_undone',
            'self_settings_changed',
            'lti_platform_saved', 'lti_platform_deleted'],
        'views' => ['reader_viewed'],
    ];

    /**
     * The log of the site, if it keeps one that can be read.
     *
     * @return \core\log\sql_internal_table_reader|null
     */
    private static function log(): ?\core\log\sql_internal_table_reader {
        $readers = get_log_manager()->get_readers('\core\log\sql_internal_table_reader');
        return $readers ? reset($readers) : null;
    }

    /**
     * Whether the site keeps a log this can read.
     *
     * @return bool
     */
    public static function available(): bool {
        return self::log() !== null;
    }

    /**
     * A page of what was done in a centre, the newest first.
     *
     * @param int $entityid
     * @param string $kind A key of KINDS, or empty for everything except opening records.
     * @param int $from
     * @return array The events of the page (at most PAGE) and whether there are more: events, more.
     */
    public static function events(int $entityid, string $kind, int $from): array {
        global $DB;
        $log = self::log();
        if (!$log) {
            return ['events' => [], 'more' => false];
        }
        $names = isset(self::KINDS[$kind]) ? self::KINDS[$kind] : array_merge(...array_values(array_diff_key(
            self::KINDS,
            ['views' => true]
        )));
        [$in, $params] = $DB->get_in_or_equal(
            array_map(fn($name) => '\\local_oksigeniaayvurenda\\event\\' . $name, $names),
            SQL_PARAMS_NAMED
        );
        $found = $log->get_events_select(
            'contextid = :contextid AND eventname ' . $in,
            $params + ['contextid' => access::context($entityid)->id],
            'timecreated DESC, id DESC',
            $from,
            self::PAGE + 1
        );
        return ['events' => array_slice($found, 0, self::PAGE), 'more' => count($found) > self::PAGE];
    }

    /**
     * What a page says of each event: its day, hour, name, whom it is about, who did it. The names are looked up
     * here, on a screen for whoever keeps the centre: the logs themselves carry only ids.
     *
     * @param array $events
     * @param int $entityid
     * @return array[] One per day: day, lines (time, name, about, detail, who).
     */
    public static function days(array $events, int $entityid): array {
        global $DB;
        $userids = [];
        $readerids = [];
        $libraryids = [];
        $recordids = [];
        foreach ($events as $event) {
            $extra = $event->get_logextra();
            $userids[] = (int) $event->userid;
            $userids[] = (int) ($extra['realuserid'] ?? 0);
            $userids[] = (int) $event->relateduserid;
            $table = substr((string) $event->objecttable, strlen('local_oksigeniaayvurenda_'));
            if ($table === 'reader') {
                $readerids[] = (int) $event->objectid;
            } else if ($table === 'library') {
                $libraryids[] = (int) $event->objectid;
            } else if ($table === 'record') {
                $recordids[] = (int) $event->objectid;
            }
        }
        $users = self::users($userids);
        $readers = $readerids ? $DB->get_records_list(
            'local_oksigeniaayvurenda_reader',
            'id',
            array_unique($readerids),
            '',
            'id, entityid, userid, firstname, lastname'
        ) : [];
        $users += self::users(array_map(fn($reader) => (int) $reader->userid, $readers));
        $libraries = $libraryids
            ? $DB->get_records_list('local_oksigeniaayvurenda_library', 'id', array_unique($libraryids), '', 'id, name') : [];
        $records = $recordids
            ? $DB->get_records_list('local_oksigeniaayvurenda_record', 'id', array_unique($recordids), '', 'id, title') : [];
        $days = [];
        foreach ($events as $event) {
            $table = substr((string) $event->objecttable, strlen('local_oksigeniaayvurenda_'));
            $id = (int) $event->objectid;
            $about = '';
            if ($table === 'reader' && isset($readers[$id])) {
                $about = self::reader_name($readers[$id], $users);
            } else if ($table === 'library' && isset($libraries[$id])) {
                $about = format_string($libraries[$id]->name, true, ['escape' => false]);
            } else if ($table === 'record' && isset($records[$id])) {
                $about = format_string($records[$id]->title, true, ['escape' => false]);
            }
            if ($event->relateduserid && isset($users[(int) $event->relateduserid]) && $table !== 'reader') {
                $related = fullname($users[(int) $event->relateduserid]);
                $about = $about !== '' ? $about . ' · ' . $related : $related;
            }
            $day = userdate($event->timecreated, get_string('strftimedaydate', 'langconfig'));
            $days[$day] = $days[$day] ?? ['day' => $day, 'lines' => []];
            $days[$day]['lines'][] = [
                'time' => userdate($event->timecreated, get_string('strftimetime', 'langconfig')),
                'name' => $event->get_name(),
                'about' => $about,
                'detail' => self::detail($event),
                'who' => self::who($event, $users),
            ];
        }
        return array_values($days);
    }

    /**
     * Accounts by their ids, with what their names need.
     *
     * @param int[] $ids
     * @return array Id => user.
     */
    private static function users(array $ids): array {
        global $DB;
        $ids = array_unique(array_filter($ids));
        return $ids ? $DB->get_records_list(
            'user',
            'id',
            $ids,
            '',
            'id, ' . implode(', ', \core_user\fields::get_name_fields())
        ) : [];
    }

    /**
     * The name of a reader.
     *
     * @param \stdClass $reader
     * @param array $users
     * @return string
     */
    private static function reader_name(\stdClass $reader, array $users): string {
        return isset($users[(int) $reader->userid]) ? fullname($users[(int) $reader->userid])
            : trim($reader->firstname . ' ' . $reader->lastname);
    }

    /**
     * Who did it: a person, someone entered as a person, or nobody with a session (the night, a link).
     *
     * @param \core\event\base $event
     * @param array $users
     * @return string
     */
    private static function who(\core\event\base $event, array $users): string {
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        $user = $users[(int) $event->userid] ?? null;
        if (!$user) {
            return $str('activitynobody');
        }
        $real = $users[(int) ($event->get_logextra()['realuserid'] ?? 0)] ?? null;
        return $real ? $str('activityas', (object) ['real' => fullname($real), 'name' => fullname($user)]) : fullname($user);
    }

    /**
     * The numbers or the way of an action, in words.
     *
     * @param \core\event\base $event
     * @return string
     */
    private static function detail(\core\event\base $event): string {
        $key = 'event' . str_replace('_', '', substr(strrchr(get_class($event), '\\'), 1)) . 'detail';
        $other = (array) $event->other;
        $manager = get_string_manager();
        foreach (['source', 'how', 'what', 'mode', 'kind'] as $code) {
            $ofcode = $key . (string) ($other[$code] ?? '');
            if (isset($other[$code]) && $manager->string_exists($ofcode, 'local_oksigeniaayvurenda')) {
                return get_string($ofcode, 'local_oksigeniaayvurenda', (object) $other);
            }
        }
        if (isset($other['report']) && $manager->string_exists('report' . $other['report'], 'local_oksigeniaayvurenda')) {
            return get_string('report' . $other['report'], 'local_oksigeniaayvurenda');
        }
        if (isset($other['from'], $other['to'])) {
            return get_string($key, 'local_oksigeniaayvurenda', (object) [
                'from' => get_string('skin' . $other['from'], 'local_oksigeniaayvurenda'),
                'to' => get_string('skin' . $other['to'], 'local_oksigeniaayvurenda'),
            ]);
        }
        return $manager->string_exists($key, 'local_oksigeniaayvurenda')
            ? get_string($key, 'local_oksigeniaayvurenda', (object) $other) : '';
    }
}
