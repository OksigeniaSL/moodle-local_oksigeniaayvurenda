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
 * The pictures a centre puts of its own: each library's, and its characters' portraits.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * Kept in Moodle's files of the site, one per name: a new one takes the place of the one before. Only PNG, JPEG, WebP
 * or GIF that really are pictures, of 4 MB at most.
 */
final class images {
    /** @var string[] The kinds of picture taken, and their ending. */
    private const TYPES = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];

    /** @var int The largest picture taken, in bytes. */
    public const MAX = 4 * 1024 * 1024;

    /**
     * Keeps a picture.
     *
     * @param string $area libraryimage or portrait.
     * @param int $itemid The library, or the entity of the characters.
     * @param string $name Its name, without the ending (image, memory, herald).
     * @param string $path Where the picture is now.
     * @return bool Whether it was a picture and was kept.
     */
    public static function store(string $area, int $itemid, string $name, string $path): bool {
        if (!is_readable($path) || filesize($path) > self::MAX || filesize($path) === 0) {
            return false;
        }
        $info = @getimagesize($path);
        $ending = $info ? (self::TYPES[$info[2]] ?? null) : null;
        if (!$ending) {
            return false;
        }
        self::remove($area, $itemid, $name);
        get_file_storage()->create_file_from_pathname([
            'contextid' => \context_system::instance()->id,
            'component' => 'local_oksigeniaayvurenda',
            'filearea' => $area,
            'itemid' => $itemid,
            'filepath' => '/',
            'filename' => $name . '.' . $ending,
        ], $path);
        return true;
    }

    /**
     * Keeps the data of a puppet (its atlas, rig or gestures), if it is JSON.
     *
     * @param string $area portrait.
     * @param int $itemid The entity of the characters.
     * @param string $name Its name, without the ending (herald-marioneta.atlas).
     * @param string $path Where the file is now.
     * @return bool Whether it was JSON and was kept.
     */
    public static function store_json(string $area, int $itemid, string $name, string $path): bool {
        if (!is_readable($path) || filesize($path) > self::MAX || filesize($path) === 0) {
            return false;
        }
        if (!is_array(json_decode((string) file_get_contents($path), true))) {
            return false;
        }
        self::remove($area, $itemid, $name);
        get_file_storage()->create_file_from_pathname([
            'contextid' => \context_system::instance()->id,
            'component' => 'local_oksigeniaayvurenda',
            'filearea' => $area,
            'itemid' => $itemid,
            'filepath' => '/',
            'filename' => $name . '.json',
        ], $path);
        return true;
    }

    /**
     * Keeps the picture a form sent, if it sent one.
     *
     * @param string $field The form's field.
     * @param string $area
     * @param int $itemid
     * @param string $name
     * @return bool|null Null when nothing was sent; else whether it was kept.
     */
    public static function store_upload(string $field, string $area, int $itemid, string $name): ?bool {
        $upload = $_FILES[$field] ?? null;
        if (!$upload || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
            return false;
        }
        return self::store($area, $itemid, $name, $upload['tmp_name']);
    }

    /**
     * The address of a picture, if there is one.
     *
     * @param string $area
     * @param int $itemid
     * @param string $name
     * @return string|null
     */
    public static function url(string $area, int $itemid, string $name): ?string {
        $file = self::file($area, $itemid, $name);
        if (!$file) {
            return null;
        }
        return \moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            'local_oksigeniaayvurenda',
            $area,
            $itemid,
            '/',
            $file->get_filename()
        )->out(false) . '?v=' . $file->get_timemodified();
    }

    /**
     * Takes a picture away.
     *
     * @param string $area
     * @param int $itemid
     * @param string $name
     */
    public static function remove(string $area, int $itemid, string $name): void {
        while ($file = self::file($area, $itemid, $name)) {
            $file->delete();
        }
    }

    /**
     * The stored file of a picture.
     *
     * @param string $area
     * @param int $itemid
     * @param string $name
     * @return \stored_file|null
     */
    private static function file(string $area, int $itemid, string $name): ?\stored_file {
        $files = get_file_storage()->get_area_files(
            \context_system::instance()->id,
            'local_oksigeniaayvurenda',
            $area,
            $itemid,
            'filename',
            false
        );
        foreach ($files as $file) {
            if (pathinfo($file->get_filename(), PATHINFO_FILENAME) === $name) {
                return $file;
            }
        }
        return null;
    }
}
