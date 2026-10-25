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
 * The digital collection: files and links of a record, who sees them, and where each reader is in them.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

use local_oksigeniaayvurenda\core\digital_rules;

/**
 * A record can have digital forms: an EPUB to read in the browser, a PDF, an audiobook, a video, or a link to where it
 * is. Each one says its licence and who it is for; the licence wins (a work whose rights are someone else's never goes
 * public nor is downloaded). Each reader finds the book, or the recording, where they left it, with their bookmarks.
 * The files stay in the site, in the plugin's file area.
 */
final class digital {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var string The file area of the digital forms. */
    public const AREA = 'digital';

    /**
     * Who someone is for the digital collection of an entity: the library staff, a student (with their stage), a family,
     * a teacher or staff member, or nobody in particular.
     *
     * @param int $entityid
     * @param int $userid
     * @param int|null $now
     * @return array who (desk, students, families, staff or nobody), stage, userid and readerid.
     */
    public static function viewer(int $entityid, int $userid, ?int $now = null): array {
        global $DB;
        $reader = $userid ? $DB->get_record(self::T . 'reader', ['entityid' => $entityid, 'userid' => $userid,
            'status' => 'active'], 'id, readertype', IGNORE_MULTIPLE) : null;
        $ids = ['userid' => $userid, 'readerid' => $reader ? (int) $reader->id : 0];
        if ($userid && access::can('local/oksigeniaayvurenda:desk', $entityid, $userid)) {
            return ['who' => 'desk', 'stage' => null] + $ids;
        }
        if (!$reader) {
            return ['who' => 'nobody', 'stage' => null] + $ids;
        }
        $who = ['student' => 'students', 'family' => 'families'][$reader->readertype] ?? 'staff';
        $stage = null;
        if ($who === 'students') {
            $year = years::around($entityid, $now ?? time());
            $stage = $DB->get_field(
                self::T . 'member',
                'stage',
                ['readerid' => $reader->id, 'yearid' => $year->id],
                IGNORE_MULTIPLE
            ) ?: null;
        }
        return ['who' => $who, 'stage' => $stage] + $ids;
    }

    /**
     * Adds a file to a record.
     *
     * @param \stdClass $library
     * @param int $recordid
     * @param string $path Where the file is now.
     * @param string $filename Its name.
     * @param array $data title, licence, audience, stages, download.
     * @param int $userid
     * @param int|null $now
     * @return int
     */
    public static function add_file(
        \stdClass $library,
        int $recordid,
        string $path,
        string $filename,
        array $data,
        int $userid,
        ?int $now = null
    ): int {
        $filename = clean_filename($filename) ?: 'file';
        $id = self::insert($library, $recordid, 'file', digital_rules::format($filename), null, $data, $userid, $now);
        get_file_storage()->create_file_from_pathname(['contextid' => \context_system::instance()->id,
            'component' => 'local_oksigeniaayvurenda', 'filearea' => self::AREA, 'itemid' => $id, 'filepath' => '/',
            'filename' => $filename], $path);
        return $id;
    }

    /**
     * Adds a link to where the work is (an open library, a publisher, a repository).
     *
     * @param \stdClass $library
     * @param int $recordid
     * @param string $url
     * @param array $data
     * @param int $userid
     * @param int|null $now
     * @return int
     */
    public static function add_link(
        \stdClass $library,
        int $recordid,
        string $url,
        array $data,
        int $userid,
        ?int $now = null
    ): int {
        return self::insert($library, $recordid, 'link', 'link', clean_param(trim($url), PARAM_URL), $data, $userid, $now);
    }

    /**
     * Changes the title, licence, audience, stages and download of an item.
     *
     * @param int $id
     * @param \stdClass $library
     * @param array $data
     * @param int|null $now
     */
    public static function update(int $id, \stdClass $library, array $data, ?int $now = null): void {
        global $DB;
        $item = self::get($id, $library);
        $DB->update_record(self::T . 'digital', (object) (['id' => $item->id, 'timemodified' => $now ?? time()] +
            self::fields($data)));
    }

    /**
     * Removes an item, its file and where readers were in it.
     *
     * @param int $id
     * @param \stdClass $library
     */
    public static function delete(int $id, \stdClass $library): void {
        global $DB;
        $item = self::get($id, $library);
        get_file_storage()->delete_area_files(
            \context_system::instance()->id,
            'local_oksigeniaayvurenda',
            self::AREA,
            $item->id
        );
        $DB->delete_records(self::T . 'digitalread', ['digitalid' => $item->id]);
        $DB->delete_records(self::T . 'digital', ['id' => $item->id]);
    }

    /**
     * An item of the library's entity.
     *
     * @param int $id
     * @param \stdClass $library
     * @return \stdClass
     */
    public static function get(int $id, \stdClass $library): \stdClass {
        global $DB;
        return $DB->get_record(self::T . 'digital', ['id' => $id, 'entityid' => $library->entityid], '*', MUST_EXIST);
    }

    /**
     * Whether someone sees an item: published, and for them.
     *
     * @param \stdClass $item
     * @param array $viewer As viewer() gives it.
     * @return bool
     */
    public static function visible(\stdClass $item, array $viewer): bool {
        if ($item->kind === 'voice') {
            return voices::can_hear($item, $viewer);
        }
        if ($item->status !== 'published' && $viewer['who'] !== 'desk') {
            return false;
        }
        return digital_rules::can_see(
            self::audience($item),
            json_decode((string) $item->stages, true) ?: [],
            $viewer['who'],
            $viewer['stage']
        );
    }

    /**
     * The digital forms of a record that someone sees, or all of them for the library staff.
     *
     * @param int $recordid
     * @param \stdClass $library
     * @param array $viewer
     * @return \stdClass[]
     */
    public static function of_record(int $recordid, \stdClass $library, array $viewer): array {
        global $DB;
        $items = $DB->get_records(
            self::T . 'digital',
            ['recordid' => $recordid, 'entityid' => $library->entityid],
            'timecreated, id'
        );
        return array_values(array_filter($items, fn($i) => self::visible($i, $viewer)));
    }

    /**
     * The audience an item has, after its licence.
     *
     * @param \stdClass $item
     * @return string[]
     */
    public static function audience(\stdClass $item): array {
        return digital_rules::audience(explode(',', (string) $item->audience), (string) $item->licence);
    }

    /**
     * The stored file of an item, if it has one.
     *
     * @param \stdClass $item
     * @return \stored_file|null
     */
    public static function file(\stdClass $item): ?\stored_file {
        $files = get_file_storage()->get_area_files(
            \context_system::instance()->id,
            'local_oksigeniaayvurenda',
            self::AREA,
            $item->id,
            'id',
            false
        );
        return $files ? reset($files) : null;
    }

    /**
     * Where to fetch an item's file from, to read it or to download it.
     *
     * @param \stdClass $item
     * @param bool $download
     * @return string
     */
    public static function file_url(\stdClass $item, bool $download = false): string {
        $file = self::file($item);
        if (!$file) {
            return '';
        }
        return \moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            'local_oksigeniaayvurenda',
            self::AREA,
            $item->id,
            $file->get_filepath(),
            $file->get_filename(),
            $download
        )->out(false);
    }

    /**
     * Where someone is in an item, and their bookmarks.
     *
     * @param int $id
     * @param int $userid
     * @return array position and bookmarks (place and label).
     */
    public static function place(int $id, int $userid): array {
        global $DB;
        $row = $DB->get_record(self::T . 'digitalread', ['digitalid' => $id, 'userid' => $userid]);
        return ['position' => $row ? (string) $row->position : '',
            'bookmarks' => $row ? (json_decode((string) $row->bookmarks, true) ?: []) : []];
    }

    /**
     * Keeps where someone is, or their bookmarks.
     *
     * @param int $id
     * @param int $userid
     * @param string|null $position
     * @param array|null $bookmarks Places and short labels, twenty at most.
     * @param int|null $now
     */
    public static function keep_place(int $id, int $userid, ?string $position, ?array $bookmarks, ?int $now = null): void {
        global $DB;
        $now = $now ?? time();
        $row = $DB->get_record(self::T . 'digitalread', ['digitalid' => $id, 'userid' => $userid]);
        $data = ['timemodified' => $now];
        if ($position !== null) {
            $data['position'] = \core_text::substr($position, 0, 255);
        }
        if ($bookmarks !== null) {
            $clean = [];
            foreach (array_slice($bookmarks, 0, 20) as $mark) {
                $clean[] = ['place' => \core_text::substr((string) ($mark['place'] ?? ''), 0, 255),
                    'label' => \core_text::substr(clean_param((string) ($mark['label'] ?? ''), PARAM_TEXT), 0, 100)];
            }
            $data['bookmarks'] = json_encode($clean);
        }
        if ($row) {
            $DB->update_record(self::T . 'digitalread', (object) (['id' => $row->id] + $data));
        } else {
            $DB->insert_record(self::T . 'digitalread', ['digitalid' => $id, 'userid' => $userid] + $data);
        }
    }

    /**
     * Inserts an item.
     *
     * @param \stdClass $library
     * @param int $recordid
     * @param string $kind
     * @param string $format
     * @param string|null $url
     * @param array $data
     * @param int $userid
     * @param int|null $now
     * @return int
     */
    private static function insert(
        \stdClass $library,
        int $recordid,
        string $kind,
        string $format,
        ?string $url,
        array $data,
        int $userid,
        ?int $now
    ): int {
        global $DB;
        $now = $now ?? time();
        $DB->get_record(self::T . 'record', ['id' => $recordid, 'entityid' => $library->entityid], 'id', MUST_EXIST);
        return (int) $DB->insert_record(self::T . 'digital', ['entityid' => $library->entityid, 'recordid' => $recordid,
            'kind' => $kind, 'format' => $format, 'url' => $url, 'status' => 'published', 'createdby' => $userid ?: null,
            'timecreated' => $now, 'timemodified' => $now] + self::fields($data));
    }

    /**
     * The fields a form sets, cleaned: an unknown licence counts as someone else's rights.
     *
     * @param array $data
     * @return array
     */
    private static function fields(array $data): array {
        $licence = in_array($data['licence'] ?? '', digital_rules::LICENCES, true) ? $data['licence'] : 'rights';
        $audience = digital_rules::audience((array) ($data['audience'] ?? ['staff']), $licence) ?: ['staff'];
        $stages = array_values(array_filter(
            array_map(fn($s) => trim((string) $s), (array) ($data['stages'] ?? [])),
            fn($s) => $s !== ''
        ));
        return [
            'title' => \core_text::substr(trim((string) ($data['title'] ?? '')), 0, 255),
            'licence' => $licence,
            'audience' => implode(',', $audience),
            'stages' => $stages ? json_encode($stages) : null,
            'download' => (int) digital_rules::can_download($licence, !empty($data['download'])),
        ];
    }
}
