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

    /** @var int Days a family has to use the link they were sent. */
    public const INVITEDAYS = 14;

    /** @var string[] Whom a family may let hear their voice, beyond the teacher who invited them and the library. */
    public const FAMILYAUDIENCES = ['class', 'students', 'families', 'public'];

    /** @var int Longest signature. */
    public const SIGNATURE = 60;

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
     * @param string $signature How its author wants it signed, if at all.
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
        ?int $now = null,
        string $signature = ''
    ): int {
        global $DB;
        plans::require((int) $library->entityid, 'voices');
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
        // The signature waits for approval like the voice: nobody sees it before someone has read it.
        $DB->update_record(self::T . 'digital', (object) ['id' => $id, 'kind' => 'voice', 'format' => 'audio',
            'status' => 'pending', 'readerid' => $readerid ?: null, 'audience' => '',
            'signature' => self::signature($signature), 'deletetoken' => bin2hex(random_bytes(24))]);
        if ($userid) {
            self::tell_received(digital::get($id, $library), $userid);
        }
        return $id;
    }

    /**
     * The text to read from while recording: the record's first EPUB, or else its first PDF, that someone may open.
     *
     * @param int $recordid
     * @param \stdClass $library
     * @param array $viewer As digital::viewer() gives it.
     * @return \stdClass|null
     */
    public static function text_of(int $recordid, \stdClass $library, array $viewer): ?\stdClass {
        $found = null;
        foreach (digital::of_record($recordid, $library, $viewer) as $item) {
            if ($item->kind === 'file' && $item->status === 'published' && in_array($item->format, ['epub', 'pdf'], true)) {
                if ($item->format === 'epub') {
                    return $item;
                }
                $found = $found ?? $item;
            }
        }
        return $found;
    }

    /**
     * The link that deletes a voice without logging in or asking anyone.
     *
     * @param \stdClass $item
     * @return string
     */
    public static function forget_url(\stdClass $item): string {
        return (new \moodle_url('/local/oksigeniaayvurenda/forget.php', ['t' => $item->deletetoken]))->out(false);
    }

    /**
     * The voice of a delete link.
     *
     * @param string $token
     * @return \stdClass|null
     */
    public static function by_token(string $token): ?\stdClass {
        global $DB;
        if (!preg_match('/^[0-9a-f]{48}$/', $token)) {
            return null;
        }
        return $DB->get_record(self::T . 'digital', ['deletetoken' => $token, 'kind' => 'voice']) ?: null;
    }

    /**
     * Tells whoever recorded a voice that it was received, with the link that deletes it.
     *
     * @param \stdClass $item
     * @param int $userid
     * @return bool
     */
    public static function tell_received(\stdClass $item, int $userid): bool {
        global $DB;
        $user = \core_user::get_user($userid);
        if (!$user || $user->deleted) {
            return false;
        }
        $title = (string) $DB->get_field(self::T . 'record', 'title', ['id' => $item->recordid]);
        // The book's page, in a library of its centre.
        $slug = (string) $DB->get_field_select(self::T . 'library', 'slug', 'entityid = ?', [$item->entityid], IGNORE_MULTIPLE);
        return notices::send(
            'voicereceived',
            $user,
            get_string('voicereceivedsubject', 'local_oksigeniaayvurenda', $title),
            get_string('voicereceivedbody', 'local_oksigeniaayvurenda', ['title' => $title, 'link' => self::forget_url($item)]),
            $slug !== '' ? (new \moodle_url('/local/oksigeniaayvurenda/record.php', ['library' => $slug, 'id' => $item->recordid]))
                ->out(false) : '',
            $title
        );
    }

    /**
     * Sends a family, once, the link that deletes what they recorded; the address is not kept.
     *
     * @param \stdClass $item
     * @param string $email
     * @return bool
     */
    public static function email_family(\stdClass $item, string $email): bool {
        global $DB;
        if (!validate_email($email)) {
            return false;
        }
        $title = (string) $DB->get_field(self::T . 'record', 'title', ['id' => $item->recordid]);
        $to = \core_user::get_noreply_user();
        $to->email = $email;
        $to->firstname = '';
        $to->lastname = '';
        $to->mailformat = 0;
        return (bool) email_to_user(
            $to,
            \core_user::get_noreply_user(),
            get_string('voicereceivedsubject', 'local_oksigeniaayvurenda', $title),
            get_string('voicereceivedbody', 'local_oksigeniaayvurenda', ['title' => $title, 'link' => self::forget_url($item)])
        );
    }

    /**
     * How many days the centre keeps voices; 0 is until whoever recorded them deletes them.
     *
     * @param int $entityid
     * @return int
     */
    public static function keepdays(int $entityid): int {
        global $DB;
        $settings = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        return max(0, (int) ($settings['voicekeepdays'] ?? self::KEEPDAYS));
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
        if (
            $viewer['who'] === 'desk' || ($userid && (int) $item->createdby === $userid) || self::tutors($item, $userid)
                || self::inviter($item, $userid)
        ) {
            return true;
        }
        if ($item->status !== 'published' || !self::may_share($item)) {
            return false;
        }
        // The class is an audience of voices only, which digital::audience() leaves out.
        if (in_array('class', explode(',', (string) $item->audience), true) && self::in_class($item, $viewer)) {
            return true;
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
        return access::can('local/oksigeniaayvurenda:desk', (int) $item->entityid, $userid)
            || self::tutors($item, $userid) || self::inviter($item, $userid);
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
        $wanted = (array) ($data['audience'] ?? []);
        $share = self::may_share($item);
        $audience = $share ? digital_rules::audience($wanted, $licence) : [];
        // One class: the group this school year of the student it is about.
        $class = $share && in_array('class', $wanted, true) ? self::class_of($item) : null;
        if ($class) {
            array_unshift($audience, 'class');
        }
        $invite = $DB->get_record(self::T . 'voiceinvite', ['digitalid' => $id]);
        if ($invite) {
            $audience = array_values(array_intersect($audience, explode(',', (string) $invite->audience)));
        }
        $class = in_array('class', $audience, true) ? $class : null;
        digital::update($id, $library, ['title' => $item->title, 'licence' => $licence, 'download' => 0], $now);
        $DB->update_record(self::T . 'digital', (object) ['id' => $id, 'status' => 'published',
            'audience' => implode(',', $audience), 'classyearid' => $class ? $class['yearid'] : null,
            'classgroup' => $class ? $class['group'] : null,
            'signature' => array_key_exists('signature', $data) ? self::signature((string) $data['signature'])
                : $item->signature]);
    }

    /**
     * A signature as it is kept: trimmed and short, or nothing.
     *
     * @param string $signature
     * @return string|null
     */
    public static function signature(string $signature): ?string {
        $signature = trim(preg_replace('/\s+/u', ' ', $signature));
        return $signature === '' ? null : \core_text::substr($signature, 0, self::SIGNATURE);
    }

    /**
     * The student a voice is about: whoever recorded it, if a student, or the one whose family was invited.
     *
     * @param \stdClass $item
     * @return int Reader id, or 0.
     */
    public static function child(\stdClass $item): int {
        global $DB;
        if ($item->readerid && $DB->get_field(self::T . 'reader', 'readertype', ['id' => $item->readerid]) === 'student') {
            return (int) $item->readerid;
        }
        $invite = self::invite_of((int) $item->id);
        return $invite && $invite->readerid ? (int) $invite->readerid : 0;
    }

    /**
     * The group this school year of a student.
     *
     * @param int $readerid
     * @param int $entityid
     * @return array|null yearid, group.
     */
    public static function group_of(int $readerid, int $entityid): ?array {
        global $DB;
        if (!$readerid) {
            return null;
        }
        $year = years::around($entityid, time());
        $group = $DB->get_field(
            self::T . 'member',
            'groupname',
            ['readerid' => $readerid, 'yearid' => $year->id],
            IGNORE_MULTIPLE
        );
        return $group ? ['yearid' => (int) $year->id, 'group' => (string) $group] : null;
    }

    /**
     * The class a voice could be kept to: the group of the student it is about.
     *
     * @param \stdClass $item
     * @return array|null yearid, group.
     */
    public static function class_of(\stdClass $item): ?array {
        return self::group_of(self::child($item), (int) $item->entityid);
    }

    /**
     * What a centre lets families choose with their link, and «their class» only when the link is for a student with
     * a group.
     *
     * @param \stdClass $invite
     * @return string[]
     */
    public static function family_options(\stdClass $invite): array {
        global $DB;
        $settings = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $invite->entityid]), true) ?: [];
        $options = array_values(array_intersect(self::FAMILYAUDIENCES, (array) ($settings['voicefamilyoptions'] ??
            self::FAMILYAUDIENCES)));
        if (!self::group_of((int) $invite->readerid, (int) $invite->entityid)) {
            $options = array_values(array_diff($options, ['class']));
        }
        return $options;
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
     * Ayvu rande, the spring of words: the approved voices of a library that someone may hear, newest first, by record.
     *
     * @param \stdClass $library
     * @param array $viewer As digital::viewer() gives it.
     * @param int $page
     * @return array records (id, title, cover, voices), and more.
     */
    public static function spring(\stdClass $library, array $viewer, int $page = 0): array {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT d.*, r.title AS booktitle
               FROM {" . self::T . "digital} d
               JOIN {" . self::T . "record} r ON r.id = d.recordid
              WHERE d.entityid = :entityid AND d.kind = 'voice' AND d.status = 'published'
           ORDER BY d.timecreated DESC, d.id DESC",
            ['entityid' => $library->entityid],
            0,
            1000
        );
        $records = [];
        foreach ($rows as $row) {
            if (!self::can_hear($row, $viewer)) {
                continue;
            }
            $id = (int) $row->recordid;
            $records[$id] = $records[$id] ?? ['id' => $id, 'title' => $row->booktitle, 'voices' => []];
            $records[$id]['voices'][] = $row;
        }
        $per = 20;
        $more = count($records) > ($page + 1) * $per;
        $records = array_slice($records, $page * $per, $per, true);
        $covers = covers::urls(array_keys($records));
        foreach ($records as $id => $record) {
            $records[$id]['cover'] = $covers[$id] ?? null;
        }
        return ['records' => array_values($records), 'more' => $more];
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
            $days = max(0, (int) ($settings['voicekeepdays'] ?? self::KEEPDAYS));
            // Without a term, voices stay until whoever recorded them deletes them; unused links still go.
            if (!$days) {
                $DB->delete_records_select(self::T . 'voiceinvite', 'entityid = :entityid AND digitalid IS NULL
                    AND timeused IS NULL AND timeexpires < :now', ['entityid' => $entity->id, 'now' => $now]);
                continue;
            }
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
            // Links: unused ones once they expire; used ones when their voice is gone and their days are over.
            $DB->delete_records_select(
                self::T . 'voiceinvite',
                'entityid = :entityid AND digitalid IS NULL
                AND ((timeused IS NULL AND timeexpires < :now) OR timecreated < :limit)',
                ['entityid' => $entity->id, 'now' => $now, 'limit' => $now - $days * DAYSECS]
            );
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
        // A family's link stays used: it records once.
        $DB->set_field(self::T . 'voiceinvite', 'digitalid', null, ['digitalid' => $item->id]);
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
     * Makes a one-time link for a family to give a book a voice without an account.
     *
     * @param \stdClass $library
     * @param int $recordid
     * @param int $userid The teacher who sends it, who will approve the voice.
     * @param string $name Who is invited, as the teacher writes it.
     * @param int|null $now
     * @param int $readerid The student whose family it is, if the teacher says.
     * @return string The token of the link.
     */
    public static function invite(
        \stdClass $library,
        int $recordid,
        int $userid,
        string $name,
        ?int $now = null,
        int $readerid = 0
    ): string {
        global $DB;
        $now = $now ?? time();
        $token = bin2hex(random_bytes(24));
        $DB->insert_record(self::T . 'voiceinvite', ['entityid' => $library->entityid, 'recordid' => $recordid,
            'token' => $token, 'invitedby' => $userid ?: null, 'readerid' => $readerid ?: null,
            'name' => \core_text::substr(trim($name), 0, 255),
            'timecreated' => $now, 'timeexpires' => $now + self::INVITEDAYS * DAYSECS]);
        return $token;
    }

    /**
     * The link of a token, while it can be used to record or to see and delete what was recorded with it.
     *
     * @param string $token
     * @param int|null $now
     * @return \stdClass|null
     */
    public static function invitation(string $token, ?int $now = null): ?\stdClass {
        global $DB;
        if (!preg_match('/^[0-9a-f]{48}$/', $token)) {
            return null;
        }
        $invite = $DB->get_record(self::T . 'voiceinvite', ['token' => $token]);
        if (!$invite || (!$invite->timeused && $invite->timeexpires < ($now ?? time()))) {
            return null;
        }
        return $invite;
    }

    /**
     * Keeps what a family recorded with their link, with their permission and whom they let hear it; the link is used.
     *
     * @param \stdClass $invite
     * @param string $path
     * @param string $title
     * @param string $extension
     * @param array $audience Of what family_options() gives.
     * @param int|null $now
     * @param string $signature How the family wants it signed, if at all.
     * @param string $email Where to send, once, the link that deletes it; not kept.
     * @return int
     */
    public static function record_invited(
        \stdClass $invite,
        string $path,
        string $title,
        string $extension,
        array $audience,
        ?int $now = null,
        string $signature = '',
        string $email = ''
    ): int {
        global $DB;
        if ($invite->timeused) {
            throw new \moodle_exception('voiceinviteused', 'local_oksigeniaayvurenda');
        }
        $now = $now ?? time();
        $library = (object) ['entityid' => $invite->entityid];
        $id = self::record($library, (int) $invite->recordid, 0, 0, $path, $title, $extension, $now, $signature);
        $DB->set_field(self::T . 'digital', 'createdby', null, ['id' => $id]);
        $DB->update_record(self::T . 'voiceinvite', (object) ['id' => $invite->id, 'digitalid' => $id, 'timeused' => $now,
            'audience' => implode(',', array_values(array_intersect(self::family_options($invite), $audience)))]);
        if ($email !== '') {
            self::email_family(digital::get($id, $library), $email);
        }
        return $id;
    }

    /**
     * The links someone sent for a record, newest first.
     *
     * @param int $recordid
     * @param int $userid
     * @return \stdClass[]
     */
    public static function invites(int $recordid, int $userid): array {
        global $DB;
        return array_values($DB->get_records(
            self::T . 'voiceinvite',
            ['recordid' => $recordid, 'invitedby' => $userid],
            'timecreated DESC, id DESC'
        ));
    }

    /**
     * The link a voice was recorded with, if a family recorded it.
     *
     * @param int $digitalid
     * @return \stdClass|null
     */
    public static function invite_of(int $digitalid): ?\stdClass {
        global $DB;
        return $DB->get_record(self::T . 'voiceinvite', ['digitalid' => $digitalid]) ?: null;
    }

    /**
     * Whether someone is in the class a voice is kept to, as a student of its group or as its tutor.
     *
     * @param \stdClass $item
     * @param array $viewer
     * @return bool
     */
    private static function in_class(\stdClass $item, array $viewer): bool {
        global $DB;
        if (!$item->classyearid || (string) $item->classgroup === '') {
            return false;
        }
        $where = ['yearid' => $item->classyearid, 'groupname' => $item->classgroup];
        return (!empty($viewer['readerid']) && $DB->record_exists(self::T . 'member', $where + ['readerid' => $viewer['readerid']]))
            || (!empty($viewer['userid']) && $DB->record_exists(self::T . 'tutor', $where + ['userid' => $viewer['userid']]));
    }

    /**
     * Whether someone sent the link a family recorded a voice with.
     *
     * @param \stdClass $item
     * @param int $userid
     * @return bool
     */
    private static function inviter(\stdClass $item, int $userid): bool {
        global $DB;
        return $userid && !$item->readerid && $DB->record_exists(self::T . 'voiceinvite', ['digitalid' => $item->id,
            'invitedby' => $userid]);
    }

    /**
     * Whether someone tutors this year the group of the reader who recorded a voice.
     *
     * @param \stdClass $item
     * @param int $userid
     * @return bool
     */
    public static function tutors(\stdClass $item, int $userid): bool {
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
