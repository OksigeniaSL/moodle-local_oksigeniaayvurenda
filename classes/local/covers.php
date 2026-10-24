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
}
