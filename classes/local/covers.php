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
 * The covers of the catalogue.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

use local_oksigeniaayvurenda\core\sources\googlebooks;

/**
 * Covers fetched by the server when a book is added, made small (300 pixels high) and kept in the site, with where
 * they came from and when: the browser only sees the site's own files, the source is asked once, and a cover can be
 * taken away if anyone asks.
 */
final class covers {
    /** @var int Height of a kept cover, in pixels. */
    public const HEIGHT = 300;

    /**
     * Fetches a cover and keeps it for a record.
     *
     * @param int $recordid
     * @param string $url
     * @param string $source Where it comes from.
     * @return bool Whether there is a cover now.
     */
    public static function fetch(int $recordid, string $url, string $source): bool {
        $data = lookup::fetch($url);
        // Open Library answers a 1×1 picture when it has no cover; anything that small is not one.
        if ($data === null || strlen($data) < 1000) {
            return false;
        }
        return self::store($recordid, $data, $source, $url);
    }

    /**
     * Keeps an image as the cover of a record, made small.
     *
     * @param int $recordid
     * @param string $data
     * @param string $source
     * @param string $url
     * @return bool
     */
    public static function store(int $recordid, string $data, string $source, string $url = ''): bool {
        global $DB;
        // Already made small when it was a draft.
        $small = @getimagesizefromstring($data)[1] ?? 0;
        $small = $small > 0 && $small <= self::HEIGHT && str_starts_with($data, "\xFF\xD8") ? $data : self::shrink($data);
        if ($small === null) {
            return false;
        }
        $fs = get_file_storage();
        $context = \context_system::instance();
        $fs->delete_area_files($context->id, 'local_oksigeniaayvurenda', 'cover', $recordid);
        $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'local_oksigeniaayvurenda',
            'filearea' => 'cover',
            'itemid' => $recordid,
            'filepath' => '/',
            'filename' => 'cover.jpg',
        ], $small);
        $extra = json_decode((string) $DB->get_field('local_oksigeniaayvurenda_record', 'extra', ['id' => $recordid]), true) ?: [];
        $extra['cover'] = ['source' => $source, 'url' => $url, 'time' => time()];
        $DB->set_field('local_oksigeniaayvurenda_record', 'extra', json_encode($extra), ['id' => $recordid]);
        return true;
    }

    /**
     * Fetches the cover of an ISBN for the person adding it, to see it before saving; kept until they save it or
     * another day comes.
     *
     * @param int $userid
     * @param string $isbn
     * @param string|null $url Where the cover is; none in the practice library.
     * @return string|null The address of the draft.
     */
    public static function draft(int $userid, string $isbn, ?string $url): ?string {
        if ($url === null || $url === '') {
            return null;
        }
        $fs = get_file_storage();
        $context = \context_system::instance();
        $filename = $isbn . '.jpg';
        $file = $fs->get_file($context->id, 'local_oksigeniaayvurenda', 'coverdraft', $userid, '/', $filename);
        if (!$file) {
            $data = lookup::fetch($url);
            $small = $data !== null && strlen($data) >= 1000 ? self::shrink($data) : null;
            if ($small === null) {
                return null;
            }
            $file = $fs->create_file_from_string(['contextid' => $context->id, 'component' => 'local_oksigeniaayvurenda',
                'filearea' => 'coverdraft', 'itemid' => $userid, 'filepath' => '/', 'filename' => $filename,
                'source' => $url], $small);
        }
        return \moodle_url::make_pluginfile_url(
            $context->id,
            'local_oksigeniaayvurenda',
            'coverdraft',
            $userid,
            '/',
            $filename
        )->out(false);
    }

    /**
     * Makes the draft cover of an ISBN the cover of a record.
     *
     * @param int $userid
     * @param string $isbn
     * @param int $recordid
     * @param string $source
     * @return bool
     */
    public static function adopt(int $userid, string $isbn, int $recordid, string $source): bool {
        $context = \context_system::instance();
        $file = get_file_storage()->get_file(
            $context->id,
            'local_oksigeniaayvurenda',
            'coverdraft',
            $userid,
            '/',
            $isbn . '.jpg'
        );
        if (!$file) {
            return false;
        }
        $stored = self::store($recordid, $file->get_content(), $source, (string) $file->get_source());
        $file->delete();
        return $stored;
    }

    /**
     * Forgets the drafts of covers older than a day.
     */
    public static function forget_drafts(): void {
        $fs = get_file_storage();
        $context = \context_system::instance();
        foreach ($fs->get_area_files($context->id, 'local_oksigeniaayvurenda', 'coverdraft', false, 'id', false) as $file) {
            if ($file->get_timecreated() < time() - DAYSECS) {
                $file->delete();
            }
        }
    }

    /**
     * Takes a cover away.
     *
     * @param int $recordid
     */
    public static function remove(int $recordid): void {
        global $DB;
        get_file_storage()->delete_area_files(\context_system::instance()->id, 'local_oksigeniaayvurenda', 'cover', $recordid);
        $extra = json_decode((string) $DB->get_field('local_oksigeniaayvurenda_record', 'extra', ['id' => $recordid]), true) ?: [];
        unset($extra['cover']);
        $DB->set_field('local_oksigeniaayvurenda_record', 'extra', json_encode($extra), ['id' => $recordid]);
    }

    /**
     * The address of a record's cover, or null.
     *
     * @param int $recordid
     * @param string|null $extra The record's extra, if at hand: no cover there means no file to look for.
     * @return string|null
     */
    public static function url(int $recordid, ?string $extra = null): ?string {
        if ($extra !== null && !str_contains($extra, '"cover"')) {
            return null;
        }
        $context = \context_system::instance();
        $file = get_file_storage()->get_file($context->id, 'local_oksigeniaayvurenda', 'cover', $recordid, '/', 'cover.jpg');
        if (!$file) {
            return null;
        }
        return \moodle_url::make_pluginfile_url(
            $context->id,
            'local_oksigeniaayvurenda',
            'cover',
            $recordid,
            '/',
            'cover.jpg'
        )->out(false) . '?t=' . $file->get_timemodified();
    }

    /**
     * The addresses of the covers of some records, in one question.
     *
     * @param int[] $recordids
     * @return array Record id => address, for those that have one.
     */
    public static function urls(array $recordids): array {
        global $DB;
        if (!$recordids) {
            return [];
        }
        $context = \context_system::instance();
        [$in, $params] = $DB->get_in_or_equal($recordids, SQL_PARAMS_NAMED);
        $files = $DB->get_records_select(
            'files',
            "contextid = :contextid AND component = 'local_oksigeniaayvurenda'
            AND filearea = 'cover' AND filename = 'cover.jpg' AND itemid $in",
            $params + ['contextid' => $context->id],
            '',
            'itemid, timemodified'
        );
        $urls = [];
        foreach ($files as $file) {
            $urls[(int) $file->itemid] = \moodle_url::make_pluginfile_url(
                $context->id,
                'local_oksigeniaayvurenda',
                'cover',
                $file->itemid,
                '/',
                'cover.jpg'
            )->out(false) . '?t=' . $file->timemodified;
        }
        return $urls;
    }

    /**
     * A book's cover inside the page, as a data address: the app carries no session to ask the site for a file, and an adult
     * who sees a child's books may have no right to the library's own files (int-app-oficial 6).
     *
     * @param int $recordid
     * @param int $most Bytes it may have: a list of them carries smaller ones.
     * @param int $width For a list, as wide as it is drawn there (in pixels, for the sharpest phone): a few kilobytes and
     *     not the whole cover for each book, made once for each cover.
     * @return string A data address, or '' when it has none (or it is too big to carry).
     */
    public static function inline(int $recordid, int $most = 200 * 1024, int $width = 0): string {
        $file = get_file_storage()->get_file(
            \context_system::instance()->id,
            'local_oksigeniaayvurenda',
            'cover',
            $recordid,
            '/',
            'cover.jpg'
        );
        if (!$file) {
            return '';
        }
        if ($width > 0 && function_exists('imagecreatefromstring')) {
            $cache = \cache::make('local_oksigeniaayvurenda', 'coverthumb');
            $key = $file->get_contenthash() . '_' . $width;
            $thumb = $cache->get($key);
            if ($thumb === false) {
                $thumb = (string) self::thumb($file->get_content(), $width);
                $cache->set($key, $thumb);
            }
            if ($thumb !== '') {
                return 'data:image/jpeg;base64,' . base64_encode($thumb);
            }
        }
        if ($file->get_filesize() > $most) {
            return '';
        }
        return 'data:image/jpeg;base64,' . base64_encode($file->get_content());
    }

    /**
     * A cover as wide as a list draws it, as JPEG; one already as narrow goes as it is.
     *
     * @param string $data
     * @param int $width
     * @return string|null
     */
    private static function thumb(string $data, int $width): ?string {
        $size = @getimagesizefromstring($data);
        if ($size === false) {
            return null;
        }
        if ($size[0] <= $width) {
            return $data;
        }
        $image = @imagecreatefromstring($data);
        if (!$image) {
            return null;
        }
        $image = imagescale($image, $width, max(1, (int) round($size[1] * $width / $size[0])));
        ob_start();
        imagejpeg($image, null, 78);
        return (string) ob_get_clean();
    }

    /**
     * An image as covers are kept (made small, as JPEG), to keep the same one for many records without making it small
     * each time.
     *
     * @param string $data
     * @return string|null
     */
    public static function prepare(string $data): ?string {
        // A JPEG already as small as covers are kept goes as it is: no GD needed (some servers' GD reads no WebP).
        $height = @getimagesizefromstring($data)[1] ?? 0;
        if ($height > 0 && $height <= self::HEIGHT && str_starts_with($data, "\xFF\xD8")) {
            return $data;
        }
        return self::shrink($data);
    }

    /**
     * An image made small, as JPEG.
     *
     * @param string $data
     * @return string|null
     */
    private static function shrink(string $data): ?string {
        if (!function_exists('imagecreatefromstring') || @getimagesizefromstring($data) === false) {
            return null;
        }
        $image = @imagecreatefromstring($data);
        if (!$image) {
            return null;
        }
        $width = imagesx($image);
        $height = imagesy($image);
        if ($height > self::HEIGHT) {
            $image = imagescale($image, max(1, (int) round($width * self::HEIGHT / $height)), self::HEIGHT);
        }
        ob_start();
        imagejpeg($image, null, 82);
        return (string) ob_get_clean();
    }

    /** @var int How long a book that was asked for its cover in vain waits to be asked again. */
    private const ASK_AGAIN = 180 * DAYSECS;

    /** @var int Books asked for in each round of the sweep: a round every ten minutes, so some 860 a day at most. */
    public const SWEEP = 6;

    /** @var int How long a source that said it was asked too much is left alone. */
    private const CALM = 2 * HOURSECS;

    /** @var int How far back the loans that put a book first are counted. */
    private const DEMAND = 2 * 365 * DAYSECS;

    /** @var int How many candidates a book without an ISBN shows at most. */
    public const CANDIDATES = 6;

    /**
     * A book whose cover would help (it is lent, comes back, is looked at): if it has an ISBN and no cover, and it was not
     * asked lately, it is asked for once in the background, right away. The rest come with the sweep, a few at a time.
     *
     * @param int $recordid
     * @param int|null $now
     * @return bool Whether it was queued.
     */
    public static function seek(int $recordid, ?int $now = null): bool {
        global $DB;
        $now = $now ?? time();
        $record = $DB->get_record('local_oksigeniaayvurenda_record', ['id' => $recordid], 'id, entityid, isbn, extra');
        if (!$record || trim((string) $record->isbn) === '' || practice::is((int) $record->entityid)) {
            return false;
        }
        $extra = json_decode((string) $record->extra, true) ?: [];
        if ((int) ($extra['coverasked'] ?? 0) > $now - self::ASK_AGAIN || self::url($recordid) !== null) {
            return false;
        }
        $extra['coverasked'] = $now;
        $DB->set_field('local_oksigeniaayvurenda_record', 'extra', json_encode($extra), ['id' => $recordid]);
        $task = new \local_oksigeniaayvurenda\task\find_cover();
        $task->set_custom_data(['recordid' => $recordid]);
        \core\task\manager::queue_adhoc_task($task, true);
        return true;
    }

    /**
     * Asks for the cover of a book by its ISBN, if it still has none: Open Library first, then Google Books, each while it
     * has not asked to be left alone.
     *
     * @param int $recordid
     * @return bool Whether it has a cover now.
     */
    public static function find(int $recordid): bool {
        global $DB;
        $isbn = trim((string) $DB->get_field('local_oksigeniaayvurenda_record', 'isbn', ['id' => $recordid]));
        if ($isbn === '' || self::url($recordid) !== null) {
            return false;
        }
        if (self::calm('openlibrary')) {
            $found = self::fetch($recordid, \local_oksigeniaayvurenda\core\sources\openlibrary::cover_url($isbn), 'openlibrary');
            self::heed('openlibrary');
            if ($found) {
                return true;
            }
        }
        if (!self::calm('googlebooks')) {
            return false;
        }
        $answer = lookup::fetch(googlebooks::isbn_url($isbn, self::key()));
        self::heed('googlebooks');
        foreach ($answer === null ? [] : googlebooks::covers($answer) as $cover) {
            if (self::fetch($recordid, $cover['url'], 'googlebooks')) {
                return true;
            }
        }
        return false;
    }

    /**
     * One round of the sweep: the books with an ISBN and no cover, the most lent of the last two years first, then those
     * never asked, a few each time; each asked once in a while (ASK_AGAIN), and none while the sources ask for calm.
     *
     * @param int $now
     * @param int $max
     * @return int[] asked and found.
     */
    public static function sweep(int $now, int $max = self::SWEEP): array {
        global $DB;
        $asked = 0;
        $found = 0;
        if (!self::calm('openlibrary') && !self::calm('googlebooks')) {
            return [0, 0];
        }
        $nocover = "NOT EXISTS (SELECT 1 FROM {files} f WHERE f.contextid = :ctx AND f.component = 'local_oksigeniaayvurenda'
            AND f.filearea = 'cover' AND f.itemid = r.id AND f.filename = 'cover.jpg')";
        $params = ['ctx' => \context_system::instance()->id, 'empty' => ''];
        // The most lent first, and then the rest that were never asked, the newest first.
        $rounds = [
            "SELECT r.id, r.entityid, r.extra
               FROM {local_oksigeniaayvurenda_record} r
               JOIN (SELECT i.recordid, COUNT(1) AS demand
                       FROM {local_oksigeniaayvurenda_loan} l
                       JOIN {local_oksigeniaayvurenda_item} i ON i.id = l.itemid
                      WHERE l.timeloaned >= :since
                   GROUP BY i.recordid) d ON d.recordid = r.id
              WHERE r.isbn IS NOT NULL AND r.isbn <> :empty AND $nocover
           ORDER BY d.demand DESC, r.id DESC",
            "SELECT r.id, r.entityid, r.extra
               FROM {local_oksigeniaayvurenda_record} r
              WHERE r.isbn IS NOT NULL AND r.isbn <> :empty AND $nocover
                    AND " . $DB->sql_like('COALESCE(r.extra, :none)', ':asked', true, true, true) . "
           ORDER BY r.id DESC",
        ];
        $params += ['since' => $now - self::DEMAND, 'none' => '', 'asked' => '%"coverasked"%'];
        $practice = [];
        foreach ($rounds as $sql) {
            foreach ([0, 200, 400] as $from) {
                $rows = $DB->get_records_sql($sql, $params, $from, 200);
                foreach ($rows as $row) {
                    $practice[$row->entityid] ??= practice::is((int) $row->entityid);
                    $extra = json_decode((string) $row->extra, true) ?: [];
                    if ($practice[$row->entityid] || (int) ($extra['coverasked'] ?? 0) > $now - self::ASK_AGAIN) {
                        continue;
                    }
                    $extra['coverasked'] = $now;
                    $DB->set_field('local_oksigeniaayvurenda_record', 'extra', json_encode($extra), ['id' => $row->id]);
                    $asked++;
                    $found += self::find((int) $row->id) ? 1 : 0;
                    if ($asked >= $max || (!self::calm('openlibrary') && !self::calm('googlebooks'))) {
                        return [$asked, $found];
                    }
                }
                if (count($rows) < 200) {
                    break;
                }
            }
        }
        return [$asked, $found];
    }

    /**
     * The covers that may be a book's when its ISBN gives none or it has none, by its title and its first author, from
     * Open Library and Google Books: never put by themselves (another edition's cover misleads a child), only offered.
     *
     * @param int $recordid
     * @return array[] url, source, title, detail (authors, publisher and year).
     */
    public static function candidates(int $recordid): array {
        global $DB;
        $record = $DB->get_record('local_oksigeniaayvurenda_record', ['id' => $recordid], 'id, entityid, title');
        if (!$record || practice::is((int) $record->entityid) || trim((string) $record->title) === '') {
            return [];
        }
        $author = (string) $DB->get_field_sql(
            "SELECT a.label FROM {local_oksigeniaayvurenda_recauth} ra
               JOIN {local_oksigeniaayvurenda_authority} a ON a.id = ra.authorityid AND a.kind = 'author'
              WHERE ra.recordid = :recordid ORDER BY ra.id",
            ['recordid' => $recordid],
            IGNORE_MULTIPLE
        );
        // An author written «Surname, Name» is asked as «Name Surname».
        if (preg_match('/^([^,]+),\s*(.+)$/u', $author, $m)) {
            $author = trim(preg_replace('/[\d\-–()]+$/u', '', $m[2])) . ' ' . $m[1];
        }
        $found = [];
        $asks = ['openlibrary' => fn() => \local_oksigeniaayvurenda\core\sources\openlibrary::covers((string) lookup::fetch(
            \local_oksigeniaayvurenda\core\sources\openlibrary::cover_search_url($record->title, $author)
        )), 'googlebooks' => fn() => googlebooks::covers((string) lookup::fetch(
            googlebooks::search_url($record->title, $author, 10, self::key())
        ))];
        foreach ($asks as $source => $ask) {
            if (!self::calm($source)) {
                continue;
            }
            foreach ($ask() as $cover) {
                $found[$cover['url']] = ['url' => $cover['url'], 'source' => $source, 'title' => $cover['title'],
                    'publisher' => $cover['publisher'], 'year' => $cover['year'],
                    'detail' => implode(' · ', array_filter([$cover['authors'], $cover['publisher'], $cover['year']]))];
            }
            self::heed($source);
        }
        return array_slice(array_values($found), 0, self::CANDIDATES);
    }

    /**
     * What whoever catalogues asks of a book without a cover, from its record: «seek» asks now by its ISBN and, without it,
     * by title and author, keeping the candidates (with a small picture of each, inside the page) for the person; a number
     * chooses one of them; «none» says none is this one.
     *
     * @param int $recordid
     * @param string $action
     * @return string found, some, none, chosen or dismissed.
     */
    public static function ask_now(int $recordid, string $action): string {
        global $SESSION;
        $kept = $SESSION->ayvcovers[$recordid] ?? [];
        if ($action === 'none') {
            unset($SESSION->ayvcovers[$recordid]);
            return 'dismissed';
        }
        if (ctype_digit($action)) {
            $chosen = $kept[(int) $action] ?? null;
            unset($SESSION->ayvcovers[$recordid]);
            return $chosen && self::choose($recordid, $chosen['url']) ? 'chosen' : 'none';
        }
        if (self::url($recordid) !== null || self::find($recordid)) {
            return 'found';
        }
        $list = [];
        foreach (self::candidates($recordid) as $candidate) {
            $data = lookup::fetch($candidate['url']);
            $small = $data !== null && strlen($data) >= 1000 ? self::thumb($data, 160) : null;
            if ($small !== null) {
                $list[] = $candidate + ['src' => 'data:image/jpeg;base64,' . base64_encode($small)];
            }
        }
        $SESSION->ayvcovers = [$recordid => $list];
        return $list ? 'some' : 'none';
    }

    /**
     * The candidates kept for a person, for a record.
     *
     * @param int $recordid
     * @return array[]
     */
    public static function kept(int $recordid): array {
        global $SESSION;
        return $SESSION->ayvcovers[$recordid] ?? [];
    }

    /**
     * Keeps as a book's cover one of its candidates: only a picture of the two sources is fetched.
     *
     * @param int $recordid
     * @param string $url
     * @return bool
     */
    public static function choose(int $recordid, string $url): bool {
        $source = \local_oksigeniaayvurenda\core\sources\openlibrary::is_picture($url) ? 'openlibrary'
            : (googlebooks::is_picture($url) ? 'googlebooks' : null);
        return $source !== null && self::fetch($recordid, $url, $source);
    }

    /**
     * The key of Google Books the site has, or ''.
     *
     * @return string
     */
    private static function key(): string {
        return trim((string) get_config('local_oksigeniaayvurenda', 'googlebookskey'));
    }

    /**
     * Whether a source may be asked now: not while it asks for calm.
     *
     * @param string $source
     * @return bool
     */
    private static function calm(string $source): bool {
        return (int) get_config('local_oksigeniaayvurenda', 'coverpause_' . $source) <= time();
    }

    /**
     * After asking a source: if it said it was asked too much, it is left alone for a while.
     *
     * @param string $source
     */
    private static function heed(string $source): void {
        if (lookup::limited()) {
            set_config('coverpause_' . $source, time() + self::CALM, 'local_oksigeniaayvurenda');
        }
    }
}
