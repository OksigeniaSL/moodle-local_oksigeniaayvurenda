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
 * What readers tell about books: one line each, which the library publishes with a touch, signed as the centre chose.
 * Published, they grow the path of telling and hang from the book for the centre's readers (never in the public window).
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class reviews {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var int Characters a review may have. */
    public const MAX = 280;

    /** @var string The file area of the drawings. */
    public const AREA = 'reviewdrawing';

    /** @var int Bytes a drawing may have. */
    public const DRAWINGMAX = 1024 * 1024;

    /** @var string The file area of the voices. */
    public const VOICEAREA = 'reviewvoice';

    /** @var int Bytes a voice may have: half a minute is a tenth of that. */
    public const VOICEMAX = 2 * 1024 * 1024;

    /** @var string[] What a browser records in, by its media type. */
    public const VOICETYPES = ['audio/webm' => 'webm', 'video/webm' => 'webm', 'audio/ogg' => 'ogg', 'audio/mp4' => 'm4a',
        'video/mp4' => 'm4a', 'audio/x-m4a' => 'm4a'];

    /** @var string[] How a review is signed: first name and group, first name and initial, or no name. */
    public const SIGNS = ['name', 'initial', 'none'];

    /**
     * Whether a reader may tell about a book: they took it home some time.
     *
     * @param int $recordid
     * @param int $readerid
     * @return bool
     */
    public static function can_write(int $recordid, int $readerid): bool {
        global $DB;
        return $DB->record_exists_sql('SELECT 1
               FROM {' . self::T . 'loan} l
               JOIN {' . self::T . 'item} i ON i.id = l.itemid
              WHERE l.readerid = :readerid AND i.recordid = :recordid', ['readerid' => $readerid, 'recordid' => $recordid]);
    }

    /**
     * A reader's review of a book, in whatever state.
     *
     * @param int $recordid
     * @param int $readerid
     * @return \stdClass|null
     */
    public static function mine(int $recordid, int $readerid): ?\stdClass {
        global $DB;
        return $DB->get_record(self::T . 'review', ['recordid' => $recordid, 'readerid' => $readerid]) ?: null;
    }

    /**
     * Writes (or rewrites) a reader's review: it waits for the library again.
     *
     * @param int $entityid
     * @param int $recordid
     * @param int $readerid
     * @param string $text
     * @param int|null $now
     * @param bool $forlittle An older reader recommends it to the young.
     * @param bool $drawn It comes with a drawing or a voice, so it may have no words.
     * @return int Its id, or 0 when there was nothing to say.
     */
    public static function write(
        int $entityid,
        int $recordid,
        int $readerid,
        string $text,
        ?int $now = null,
        bool $forlittle = false,
        bool $drawn = false
    ): int {
        global $DB;
        $text = \core_text::substr(trim(preg_replace('/\s+/u', ' ', $text)), 0, self::MAX);
        if ($text === '' && !$drawn) {
            return 0;
        }
        $now = $now ?? time();
        $row = self::mine($recordid, $readerid);
        if ($row) {
            $DB->update_record(self::T . 'review', (object) ['id' => $row->id, 'text' => $text, 'status' => 'pending',
                'forlittle' => (int) $forlittle, 'decidedby' => null, 'timedecided' => null, 'timemodified' => $now]);
            return (int) $row->id;
        }
        return (int) $DB->insert_record(self::T . 'review', (object) ['entityid' => $entityid, 'recordid' => $recordid,
            'readerid' => $readerid, 'text' => $text, 'status' => 'pending', 'forlittle' => (int) $forlittle,
            'timecreated' => $now, 'timemodified' => $now]);
    }

    /**
     * Gives one reader's reviews to another when two cards turn out to be one person. Where both told about the same
     * book, the one that stays keeps theirs.
     *
     * @param int $fromid
     * @param int $toid
     */
    public static function move(int $fromid, int $toid): void {
        global $DB;
        $theirs = $DB->get_fieldset_select(self::T . 'review', 'recordid', 'readerid = :readerid', ['readerid' => $toid]);
        if ($theirs) {
            [$in, $params] = $DB->get_in_or_equal($theirs, SQL_PARAMS_NAMED);
            $params += ['readerid' => $fromid];
            $ids = $DB->get_fieldset_select(self::T . 'review', 'id', "readerid = :readerid AND recordid $in", $params);
            foreach ($ids as $gone) {
                images::remove(self::AREA, (int) $gone, 'drawing');
                self::hush((int) $gone);
            }
            $DB->delete_records_select(
                self::T . 'review',
                "readerid = :readerid AND recordid $in",
                $params
            );
        }
        $DB->set_field(self::T . 'review', 'readerid', $toid, ['readerid' => $fromid]);
    }

    /**
     * Takes a reader's review away, published or not.
     *
     * @param int $recordid
     * @param int $readerid
     */
    public static function remove(int $recordid, int $readerid): void {
        global $DB;
        $row = self::mine($recordid, $readerid);
        if ($row) {
            images::remove(self::AREA, (int) $row->id, 'drawing');
            self::hush((int) $row->id);
            $DB->delete_records(self::T . 'review', ['id' => $row->id]);
        }
    }

    /**
     * Keeps the voice of a review (what the browser recorded), in place of the one it had.
     *
     * @param int $id
     * @param string $path Where the recording is now.
     * @return bool Whether it was a recording of a sensible size and was kept.
     */
    public static function speak(int $id, string $path): bool {
        global $DB;
        if (!plans::allows((int) $DB->get_field(self::T . 'review', 'entityid', ['id' => $id]), 'voices')) {
            return false;
        }
        if (!is_readable($path) || filesize($path) === 0 || filesize($path) > self::VOICEMAX) {
            return false;
        }
        $type = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $ending = self::VOICETYPES[$type] ?? null;
        if (!$ending) {
            return false;
        }
        self::hush($id);
        get_file_storage()->create_file_from_pathname(['contextid' => \context_system::instance()->id,
            'component' => 'local_oksigeniaayvurenda', 'filearea' => self::VOICEAREA, 'itemid' => $id, 'filepath' => '/',
            'filename' => 'voice.' . $ending], $path);
        return true;
    }

    /**
     * Takes the voice of a review away.
     *
     * @param int $id
     */
    public static function hush(int $id): void {
        get_file_storage()->delete_area_files(\context_system::instance()->id, 'local_oksigeniaayvurenda', self::VOICEAREA, $id);
    }

    /**
     * The address of a review's voice, if it has one.
     *
     * @param int $id
     * @return string|null
     */
    public static function voice(int $id): ?string {
        $files = get_file_storage()->get_area_files(
            \context_system::instance()->id,
            'local_oksigeniaayvurenda',
            self::VOICEAREA,
            $id,
            'id',
            false
        );
        $file = reset($files);
        return $file ? \moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            'local_oksigeniaayvurenda',
            self::VOICEAREA,
            $id,
            '/',
            $file->get_filename()
        )->out(false) : null;
    }

    /**
     * Whether someone hears the voice of a review: its author, their tutors and the library staff always; anyone else
     * once it is published, and for a minor only with the family's permission noted by the centre.
     *
     * @param \stdClass $review
     * @param int $userid
     * @return bool
     */
    public static function can_hear(\stdClass $review, int $userid): bool {
        global $DB;
        $author = (int) $DB->get_field(self::T . 'reader', 'userid', ['id' => $review->readerid]);
        if (
            ($userid && $author === $userid) || voices::tutors($review, $userid)
                || access::can('local/oksigeniaayvurenda:desk', (int) $review->entityid, $userid)
        ) {
            return true;
        }
        // Published, for whoever looks up that centre's library, if it may be shared.
        return $review->status === 'published' && access::can('local/oksigeniaayvurenda:view', (int) $review->entityid, $userid)
            && voices::may_share($review);
    }

    /**
     * Takes away the voices of reviews older than the days the centre keeps voices.
     *
     * @param int|null $now
     * @return int How many.
     */
    public static function expire_voices(?int $now = null): int {
        global $DB;
        $now = $now ?? time();
        $gone = 0;
        $files = $DB->get_records_sql('SELECT DISTINCT f.itemid
               FROM {files} f
              WHERE f.component = :component AND f.filearea = :area AND f.filename <> :dot', ['component' =>
            'local_oksigeniaayvurenda', 'area' => self::VOICEAREA, 'dot' => '.']);
        foreach ($files as $file) {
            $review = $DB->get_record(self::T . 'review', ['id' => $file->itemid]);
            if (!$review) {
                self::hush((int) $file->itemid);
                $gone++;
                continue;
            }
            $days = voices::keepdays((int) $review->entityid);
            if ($days && (int) $review->timemodified < $now - $days * DAYSECS) {
                self::hush((int) $review->id);
                $gone++;
            }
        }
        return $gone;
    }

    /**
     * Keeps the drawing of a review (a PNG the drawing pad made), in place of the one it had.
     *
     * @param int $id
     * @param string $png The picture itself.
     * @return bool Whether it was a picture of a sensible size and was kept.
     */
    public static function draw(int $id, string $png): bool {
        $info = $png !== '' && strlen($png) <= self::DRAWINGMAX ? @getimagesizefromstring($png) : false;
        if (!$info || $info[2] !== IMAGETYPE_PNG || $info[0] > 2000 || $info[1] > 2000) {
            return false;
        }
        $path = make_request_directory() . '/drawing.png';
        file_put_contents($path, $png);
        return images::store(self::AREA, $id, 'drawing', $path);
    }

    /**
     * The address of a review's drawing, if it has one.
     *
     * @param int $id
     * @return string|null
     */
    public static function drawing(int $id): ?string {
        return images::url(self::AREA, $id, 'drawing');
    }

    /**
     * The reviews waiting for the library, oldest first.
     *
     * @param int $entityid
     * @return \stdClass[] With title, and the reader's name and group.
     */
    public static function pending(int $entityid): array {
        global $DB;
        $rows = $DB->get_records_sql('SELECT v.*, r.title
               FROM {' . self::T . 'review} v
               JOIN {' . self::T . 'record} r ON r.id = v.recordid
              WHERE v.entityid = :entityid AND v.status = :status
           ORDER BY v.timemodified, v.id', ['entityid' => $entityid, 'status' => 'pending']);
        foreach ($rows as $row) {
            $row->who = self::who((int) $row->readerid, $entityid, 'name');
            $row->drawing = self::drawing((int) $row->id);
            $row->voice = self::voice((int) $row->id);
        }
        return array_values($rows);
    }

    /**
     * How many reviews wait for the library.
     *
     * @param int $entityid
     * @return int
     */
    public static function waiting(int $entityid): int {
        global $DB;
        return $DB->count_records(self::T . 'review', ['entityid' => $entityid, 'status' => 'pending']);
    }

    /**
     * Publishes a review, or not.
     *
     * @param int $id
     * @param int $entityid
     * @param bool $publish
     * @param int $staffid
     * @param int|null $now
     */
    public static function decide(int $id, int $entityid, bool $publish, int $staffid, ?int $now = null): void {
        global $DB;
        $row = $DB->get_record(self::T . 'review', ['id' => $id, 'entityid' => $entityid], '*', MUST_EXIST);
        $DB->update_record(self::T . 'review', (object) ['id' => $row->id, 'status' => $publish ? 'published' : 'declined',
            'decidedby' => $staffid, 'timedecided' => $now ?? time(), 'timemodified' => $now ?? time()]);
    }

    /**
     * The published reviews of a book, newest first, signed as the centre chose.
     *
     * @param int $recordid
     * @param int $entityid
     * @param int $viewerid Who looks: the voice only for whom may hear it.
     * @return array[] text, who, drawing and voice; a review with nothing they may see or hear is left out.
     */
    public static function published(int $recordid, int $entityid, int $viewerid = 0): array {
        global $DB;
        $settings = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        $sign = in_array($settings['reviewsign'] ?? '', self::SIGNS, true) ? $settings['reviewsign'] : 'name';
        $rows = $DB->get_records(self::T . 'review', ['recordid' => $recordid, 'entityid' => $entityid,
            'status' => 'published'], 'timedecided DESC, id DESC', '*', 0, 20);
        $list = [];
        foreach ($rows as $r) {
            $voice = self::voice((int) $r->id);
            $item = ['text' => $r->text, 'who' => self::who((int) $r->readerid, $entityid, $sign),
                'drawing' => self::drawing((int) $r->id), 'voice' => $voice && self::can_hear($r, $viewerid) ? $voice : null];
            if ($item['text'] !== '' || $item['drawing'] || $item['voice']) {
                $list[] = $item;
            }
        }
        return $list;
    }

    /**
     * What the older readers recommend to the young: their published recommendations, newest first, one per book.
     *
     * @param int $entityid
     * @param int $limit
     * @return array[] recordid, title, text and who.
     */
    public static function for_little(int $entityid, int $limit = 6): array {
        global $DB;
        $settings = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        $sign = in_array($settings['reviewsign'] ?? '', self::SIGNS, true) ? $settings['reviewsign'] : 'name';
        $rows = $DB->get_records_sql('SELECT v.id, v.recordid, v.readerid, v.text, r.title
               FROM {' . self::T . 'review} v
               JOIN {' . self::T . 'record} r ON r.id = v.recordid
              WHERE v.entityid = :entityid AND v.status = :status AND v.forlittle = 1
           ORDER BY v.timedecided DESC, v.id DESC', ['entityid' => $entityid, 'status' => 'published'], 0, $limit * 3);
        $items = [];
        foreach ($rows as $row) {
            if (count($items) < $limit && !isset($items[$row->recordid])) {
                $items[$row->recordid] = ['recordid' => (int) $row->recordid, 'title' => (string) $row->title,
                    'text' => $row->text, 'who' => self::who((int) $row->readerid, $entityid, $sign)];
            }
        }
        return array_values($items);
    }

    /**
     * How many of a reader's reviews were published in a school year: they count on the path of telling.
     *
     * @param int $readerid
     * @param \stdClass $year
     * @return int
     */
    public static function told(int $readerid, \stdClass $year): int {
        global $DB;
        return $DB->count_records_select(
            self::T . 'review',
            "readerid = :readerid AND status = 'published' AND timecreated >= :a AND timecreated < :b",
            ['readerid' => $readerid, 'a' => $year->timestart, 'b' => $year->timeend]
        );
    }

    /**
     * How a reader signs: first name and group, first name and initial, or «a reader of» the group.
     *
     * @param int $readerid
     * @param int $entityid
     * @param string $sign One of SIGNS.
     * @return string
     */
    private static function who(int $readerid, int $entityid, string $sign): string {
        global $DB;
        $reader = $DB->get_record(self::T . 'reader', ['id' => $readerid]);
        $first = '';
        $last = '';
        if ($reader && $reader->userid) {
            $user = \core_user::get_user((int) $reader->userid, 'id, firstname, lastname');
            [$first, $last] = $user ? [(string) $user->firstname, (string) $user->lastname] : ['', ''];
        } else if ($reader) {
            [$first, $last] = [(string) $reader->firstname, (string) $reader->lastname];
        }
        $year = years::around($entityid, time());
        $group = (string) $DB->get_field(
            self::T . 'member',
            'groupname',
            ['readerid' => $readerid, 'yearid' => $year->id],
            IGNORE_MULTIPLE
        );
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        $first = trim(explode(' ', trim($first))[0] ?? '');
        if ($sign === 'none' || $first === '') {
            return $group !== '' ? $str('reviewbyagroup', $group) : $str('reviewbyareader');
        }
        if ($sign === 'initial') {
            $initial = \core_text::substr(trim($last), 0, 1);
            return $initial !== '' ? $first . ' ' . \core_text::strtoupper($initial) . '.' : $first;
        }
        return $group !== '' ? $first . ', ' . $group : $first;
    }
}
