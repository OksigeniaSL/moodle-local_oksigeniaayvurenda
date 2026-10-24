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
 * «Do you want to give this book a voice?»: recordings of readers reading a book aloud.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

use local_oksigeniaayvurenda\core\digital_rules;

/**
 * A voice is personal data. Whoever records hears it, and so do their tutors this year and the library staff; nobody
 * else until a tutor or the library approves it, and then, for a minor, only with the family's permission noted by the
 * centre; an adult decides by themselves. Whoever recorded it removes it whenever they want, and the centre keeps voices
 * only for the days it sets. Voices never leave the site: they are not in the daily copy nor sent anywhere.
 */
final class voices {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var int Days a voice is kept when the centre sets nothing: two school years. */
    public const KEEPDAYS = 730;

    /**
     * Keeps a recording of a reader, waiting for approval.
     *
     * @param \stdClass $library
     * @param int $recordid
     * @param int $readerid 0 for the staff who record without being readers.
     * @param int $userid
     * @param string $path
     * @param string $title What it is: a chapter, some pages.
     * @param string $extension Of the recording as the browser made it: webm, ogg or m4a.
     * @param int|null $now
     * @return int
     */
    public static function record(
        \stdClass $library,
        int $recordid,
        int $readerid,
        int $userid,
        string $path,
        string $title,
        string $extension = 'webm',
        ?int $now = null
    ): int {
        global $DB;
        $extension = in_array($extension, ['webm', 'ogg', 'm4a'], true) ? $extension : 'webm';
        $id = digital::add_file(
            $library,
            $recordid,
            $path,
            'voz-' . $recordid . '-' . ($now ?? time()) . '.' . $extension,
            ['title' => $title, 'licence' => 'rights', 'audience' => []],
            $userid,
            $now
        );
        $DB->update_record(self::T . 'digital', (object) ['id' => $id, 'kind' => 'voice', 'format' => 'audio',
            'status' => 'pending', 'readerid' => $readerid ?: null, 'audience' => '']);
        return $id;
    }

    /**
     * Whether someone hears a voice: its recorder, their tutors and the library staff always; others, once approved,
     * if it is for them, and for a minor only with the family's permission.
     *
     * @param \stdClass $item
     * @param array $viewer As digital::viewer() gives it.
     * @return bool
     */
    public static function can_hear(\stdClass $item, array $viewer): bool {
        $userid = (int) ($viewer['userid'] ?? 0);
        if ($viewer['who'] === 'desk' || ($userid && (int) $item->createdby === $userid) || self::tutors($item, $userid)) {
            return true;
        }
        if ($item->status !== 'published' || !self::may_share($item)) {
            return false;
        }
        return digital_rules::can_see(
            digital::audience($item),
            json_decode((string) $item->stages, true) ?: [],
            $viewer['who'],
            $viewer['stage']
        );
    }

    /**
     * Whether someone may approve a voice, or note a family's permission: the library staff, or a tutor of the reader.
     *
     * @param \stdClass $item
     * @param int $userid
     * @return bool
     */
    public static function can_approve(\stdClass $item, int $userid): bool {
        return has_capability('local/oksigeniaayvurenda:desk', \context_system::instance(), $userid)
            || self::tutors($item, $userid);
    }

    /**
     * Approves a voice: who may hear it now, and the licence of what was read.
     *
     * @param int $id
     * @param \stdClass $library
     * @param array $data licence, audience.
     * @param int|null $now
     */
    public static function approve(int $id, \stdClass $library, array $data, ?int $now = null): void {
        global $DB;
        $item = digital::get($id, $library);
        $licence = in_array($data['licence'] ?? '', digital_rules::LICENCES, true) ? $data['licence'] : 'rights';
        // Nobody beyond the recorder and their teachers unless it may be shared, and then only whom was chosen.
        $audience = self::may_share($item) ? digital_rules::audience((array) ($data['audience'] ?? []), $licence) : [];
        digital::update($id, $library, ['title' => $item->title, 'licence' => $licence, 'download' => 0], $now);
        $DB->update_record(self::T . 'digital', (object) ['id' => $id, 'status' => 'published',
            'audience' => implode(',', $audience)]);
    }

    /**
     * Whether a voice may be heard beyond its recorder and their teachers: an adult's always; a minor's, with the
     * family's permission noted and not taken back.
     *
     * @param \stdClass $item
     * @return bool
     */
    public static function may_share(\stdClass $item): bool {
        global $DB;
        if (!$item->readerid) {
            return true;
        }
        $type = $DB->get_field(self::T . 'reader', 'readertype', ['id' => $item->readerid]);
        return $type !== 'student' || self::has_permission((int) $item->readerid);
    }

    /**
     * Whether a reader's family gave permission to share their voice.
     *
     * @param int $readerid
     * @return bool
     */
    public static function has_permission(int $readerid): bool {
        global $DB;
        return $DB->record_exists(self::T . 'voiceconsent', ['readerid' => $readerid, 'timerevoked' => null]);
    }

    /**
     * Notes a family's permission, or takes it back; taking it back makes the reader's voices private again.
     *
     * @param int $readerid
     * @param bool $given
     * @param int $userid Who notes it.
     * @param int|null $now
     */
    public static function permission(int $readerid, bool $given, int $userid, ?int $now = null): void {
        global $DB;
        $now = $now ?? time();
        if ($given && !self::has_permission($readerid)) {
            $DB->insert_record(self::T . 'voiceconsent', ['readerid' => $readerid, 'notedby' => $userid ?: null,
                'timecreated' => $now]);
        } else if (!$given) {
            $DB->set_field(self::T . 'voiceconsent', 'timerevoked', $now, ['readerid' => $readerid, 'timerevoked' => null]);
            $DB->set_field(self::T . 'digital', 'audience', '', ['readerid' => $readerid, 'kind' => 'voice']);
        }
    }

    /**
     * Voices waiting for someone to approve them, or those of a record that someone hears.
     *
     * @param \stdClass $library
     * @param int $userid
     * @return \stdClass[] With the record's title.
     */
    public static function pending(\stdClass $library, int $userid): array {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT d.*, r.title AS booktitle
               FROM {" . self::T . "digital} d
               JOIN {" . self::T . "record} r ON r.id = d.recordid
              WHERE d.entityid = :entityid AND d.kind = 'voice' AND d.status = 'pending'
           ORDER BY d.timecreated, d.id",
            ['entityid' => $library->entityid]
        );
        return array_values(array_filter($rows, fn($row) => self::can_approve($row, $userid)));
    }

    /**
     * The voices someone recorded of a record.
     *
     * @param int $recordid
     * @param \stdClass $library
     * @param int $userid
     * @return \stdClass[]
     */
    public static function mine(int $recordid, \stdClass $library, int $userid): array {
        global $DB;
        return array_values($DB->get_records(self::T . 'digital', ['recordid' => $recordid, 'entityid' => $library->entityid,
            'kind' => 'voice', 'createdby' => $userid], 'timecreated, id'));
    }

    /**
     * Removes the voices older than the centre keeps them.
     *
     * @param int|null $now
     * @return int How many.
     */
    public static function expire(?int $now = null): int {
        global $DB;
        $now = $now ?? time();
        $count = 0;
        foreach ($DB->get_records(self::T . 'entity', null, 'id', 'id, settings') as $entity) {
            $settings = json_decode((string) $entity->settings, true) ?: [];
            $days = (int) ($settings['voicekeepdays'] ?? self::KEEPDAYS);
            $old = $DB->get_records_select(
                self::T . 'digital',
                "entityid = :entityid AND kind = 'voice' AND timecreated < :limit",
                ['entityid' => $entity->id, 'limit' => $now - $days * DAYSECS],
                '',
                'id, entityid'
            );
            foreach ($old as $item) {
                self::remove($item);
                $count++;
            }
        }
        return $count;
    }

    /**
     * Removes a voice and its file.
     *
     * @param \stdClass $item
     */
    public static function remove(\stdClass $item): void {
        global $DB;
        get_file_storage()->delete_area_files(
            \context_system::instance()->id,
            'local_oksigeniaayvurenda',
            digital::AREA,
            $item->id
        );
        $DB->delete_records(self::T . 'digitalread', ['digitalid' => $item->id]);
        $DB->delete_records(self::T . 'digital', ['id' => $item->id]);
    }

    /**
     * Whether someone tutors this year the group of the reader who recorded a voice.
     *
     * @param \stdClass $item
     * @param int $userid
     * @return bool
     */
    private static function tutors(\stdClass $item, int $userid): bool {
        global $DB;
        if (!$userid || !$item->readerid) {
            return false;
        }
        $year = years::around((int) $item->entityid, time());
        return $DB->record_exists_sql(
            'SELECT 1
               FROM {' . self::T . 'member} m
               JOIN {' . self::T . 'tutor} t ON t.yearid = m.yearid AND t.groupname = m.groupname
              WHERE m.readerid = :readerid AND m.yearid = :yearid AND t.userid = :userid',
            ['readerid' => $item->readerid, 'yearid' => $year->id, 'userid' => $userid]
        );
    }
}
