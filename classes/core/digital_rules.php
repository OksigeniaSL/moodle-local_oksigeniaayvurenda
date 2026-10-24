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
 * Who sees a digital book, and who may download it.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\core;

/**
 * What decides is whose the rights are. Works in the public domain or with an open licence can go anywhere, also to the
 * public window; the centre's own works too, if it wants; a work whose rights are someone else's stays inside, read in
 * the browser and never downloaded. Then each item says who it is for: students (all, or some stages), families, staff,
 * the public. The library staff see everything. Part of the core: plain PHP.
 */
final class digital_rules {
    /** @var string[] Licences, the open ones first. */
    public const LICENCES = ['pd', 'cc-by', 'cc-by-sa', 'cc-by-nc', 'cc-by-nc-sa', 'cc-by-nd', 'cc-by-nc-nd', 'own', 'rights'];

    /** @var string[] Who an item can be for. */
    public const AUDIENCES = ['students', 'families', 'staff', 'public'];

    /** @var string[] Extensions of each format. */
    private const FORMATS = ['epub' => ['epub'], 'pdf' => ['pdf'], 'audio' => ['mp3', 'm4a', 'm4b', 'ogg', 'oga', 'opus', 'wav',
        'flac', 'aac'], 'video' => ['mp4', 'webm', 'ogv', 'mov']];

    /**
     * The format of a file by its name.
     *
     * @param string $filename
     * @return string epub, pdf, audio, video or other.
     */
    public static function format(string $filename): string {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        foreach (self::FORMATS as $format => $extensions) {
            if (in_array($extension, $extensions, true)) {
                return $format;
            }
        }
        return 'other';
    }

    /**
     * Whether a licence lets the work be open to the public.
     *
     * @param string $licence
     * @return bool
     */
    public static function can_be_public(string $licence): bool {
        return in_array($licence, self::LICENCES, true) && $licence !== 'rights';
    }

    /**
     * Whether an item may be downloaded: the library said so, and the rights allow it.
     *
     * @param string $licence
     * @param bool $allowed
     * @return bool
     */
    public static function can_download(string $licence, bool $allowed): bool {
        return $allowed && self::can_be_public($licence);
    }

    /**
     * The audiences that are left once the licence has its say: never the public for a work with someone else's rights.
     *
     * @param string[] $audience
     * @param string $licence
     * @return string[]
     */
    public static function audience(array $audience, string $licence): array {
        $audience = array_values(array_intersect(self::AUDIENCES, $audience));
        return self::can_be_public($licence) ? $audience : array_values(array_diff($audience, ['public']));
    }

    /**
     * Whether someone sees an item.
     *
     * @param string[] $audience Of the item, after the licence.
     * @param string[] $stages Of the item: the students' stages it is for; all when empty.
     * @param string $who students, families, staff, desk (the library staff) or nobody (no reader here).
     * @param string|null $stage The stage of a student this year.
     * @return bool
     */
    public static function can_see(array $audience, array $stages, string $who, ?string $stage): bool {
        if ($who === 'desk' || in_array('public', $audience, true)) {
            return true;
        }
        if (!in_array($who, $audience, true)) {
            return false;
        }
        if ($who !== 'students' || !$stages) {
            return true;
        }
        $mine = text::normalise((string) $stage);
        foreach ($stages as $chosen) {
            if (text::normalise((string) $chosen) === $mine) {
                return true;
            }
        }
        return false;
    }
}
