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
 * The entities of a site and their libraries: creating, renaming and removing them.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * An entity (a school, an institute, a university, an academy) has one catalogue and several libraries: the central one,
 * one per classroom, per department or per faculty, each hanging from another if it wants. A classroom library knows its
 * group, whose readers are its own. A library is only removed while it has no copies.
 */
final class libraries {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var string[] The kinds of library. */
    public const KINDS = ['central', 'classroom', 'department', 'faculty'];

    /** @var string[] How a catalogue classifies. */
    public const CLASSIFICATIONS = ['udc', 'dewey', 'other'];

    /**
     * The entities of the site, with their libraries (the practice library left out).
     *
     * @return array[] entity (the row) and libraries (rows with their copies counted).
     */
    public static function all(): array {
        global $DB;
        $entities = [];
        foreach ($DB->get_records(self::T . 'entity', null, 'name, id') as $entity) {
            $settings = json_decode((string) $entity->settings, true) ?: [];
            if (!empty($settings['practice'])) {
                continue;
            }
            $libraries = $DB->get_records_sql(
                'SELECT l.*, (SELECT COUNT(1) FROM {' . self::T . 'item} i WHERE i.libraryid = l.id) AS copies
                   FROM {' . self::T . 'library} l
                  WHERE l.entityid = :entityid
               ORDER BY l.parentid, l.name, l.id',
                ['entityid' => $entity->id]
            );
            $entities[] = ['entity' => $entity, 'libraries' => array_values(array_filter(
                $libraries,
                fn($l) => $l->slug !== practice::SLUG
            ))];
        }
        return $entities;
    }

    /**
     * Creates an entity with its first library, the central one.
     *
     * @param array $data name, library (its name), yearstart (MM-DD), classification, controlletter.
     * @param int|null $now
     * @return \stdClass The library.
     */
    public static function create_entity(array $data, ?int $now = null): \stdClass {
        global $DB;
        $now = $now ?? time();
        $name = trim((string) ($data['name'] ?? ''));
        $entityid = $DB->insert_record(self::T . 'entity', [
            'name' => \core_text::substr($name, 0, 255),
            'yearstart' => self::yearstart((string) ($data['yearstart'] ?? '09-01')),
            'settings' => json_encode(self::entity_settings([], $data)),
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $library = trim((string) ($data['library'] ?? '')) !== '' ? trim($data['library']) : $name;
        return self::create_library((int) $entityid, ['name' => $library, 'kind' => 'central'], $now);
    }

    /**
     * Changes an entity's name, the start of its school year and how it classifies.
     *
     * @param int $entityid
     * @param array $data
     * @param int|null $now
     */
    public static function update_entity(int $entityid, array $data, ?int $now = null): void {
        global $DB;
        $entity = $DB->get_record(self::T . 'entity', ['id' => $entityid], '*', MUST_EXIST);
        $settings = json_decode((string) $entity->settings, true) ?: [];
        $DB->update_record(self::T . 'entity', (object) [
            'id' => $entityid,
            'name' => trim((string) ($data['name'] ?? '')) !== '' ? \core_text::substr(trim($data['name']), 0, 255) : $entity->name,
            'yearstart' => self::yearstart((string) ($data['yearstart'] ?? $entity->yearstart)),
            'settings' => json_encode(self::entity_settings($settings, $data)),
            'timemodified' => $now ?? time(),
        ]);
    }

    /**
     * Creates a library in an entity.
     *
     * @param int $entityid
     * @param array $data name, kind, parentid, slug (made from the name when empty), group (for a classroom).
     * @param int|null $now
     * @return \stdClass
     */
    public static function create_library(int $entityid, array $data, ?int $now = null): \stdClass {
        global $DB;
        $now = $now ?? time();
        $name = \core_text::substr(trim((string) ($data['name'] ?? '')), 0, 255);
        $kind = in_array($data['kind'] ?? '', self::KINDS, true) ? $data['kind'] : 'central';
        $parentid = (int) ($data['parentid'] ?? 0);
        if ($parentid && !$DB->record_exists(self::T . 'library', ['id' => $parentid, 'entityid' => $entityid])) {
            $parentid = 0;
        }
        $settings = [];
        if ($kind === 'classroom' && trim((string) ($data['group'] ?? '')) !== '') {
            $settings['group'] = trim($data['group']);
        }
        $id = $DB->insert_record(self::T . 'library', [
            'entityid' => $entityid,
            'parentid' => $parentid ?: null,
            'name' => $name,
            'slug' => self::slug(trim((string) ($data['slug'] ?? '')) !== '' ? $data['slug'] : $name),
            'kind' => $kind,
            'settings' => $settings ? json_encode($settings) : null,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        return $DB->get_record(self::T . 'library', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Renames a library or changes its kind, the library it hangs from or its group.
     *
     * @param int $libraryid
     * @param array $data
     * @param int|null $now
     */
    public static function update_library(int $libraryid, array $data, ?int $now = null): void {
        global $DB;
        $library = $DB->get_record(self::T . 'library', ['id' => $libraryid], '*', MUST_EXIST);
        $settings = json_decode((string) $library->settings, true) ?: [];
        $kind = in_array($data['kind'] ?? '', self::KINDS, true) ? $data['kind'] : $library->kind;
        $parentid = (int) ($data['parentid'] ?? $library->parentid);
        if (
            $parentid === (int) $library->id || ($parentid && !$DB->record_exists(self::T . 'library', ['id' => $parentid,
                'entityid' => $library->entityid]))
        ) {
            $parentid = 0;
        }
        unset($settings['group']);
        if ($kind === 'classroom' && trim((string) ($data['group'] ?? '')) !== '') {
            $settings['group'] = trim($data['group']);
        }
        $DB->update_record(self::T . 'library', (object) [
            'id' => $library->id,
            'name' => trim((string) ($data['name'] ?? '')) !== '' ? \core_text::substr(trim($data['name']), 0, 255)
                : $library->name,
            'kind' => $kind,
            'parentid' => $parentid ?: null,
            'settings' => $settings ? json_encode($settings) : null,
            'timemodified' => $now ?? time(),
        ]);
    }

    /**
     * Removes a library that has no copies, with its loan rules.
     *
     * @param int $libraryid
     * @return bool Whether it was removed.
     */
    public static function delete_library(int $libraryid): bool {
        global $DB;
        $library = $DB->get_record(self::T . 'library', ['id' => $libraryid]);
        if (
            !$library || $DB->record_exists(self::T . 'item', ['libraryid' => $libraryid]) ||
            $DB->record_exists(self::T . 'loan', ['libraryid' => $libraryid]) ||
            $DB->record_exists_select(self::T . 'lot', 'libraryid = :a OR tolibraryid = :b', ['a' => $libraryid,
                'b' => $libraryid])
        ) {
            return false;
        }
        $DB->set_field(self::T . 'library', 'parentid', $library->parentid, ['parentid' => $libraryid]);
        foreach (
            ['stocktake' => ['stockread', 'stocktakeid'], 'withdrawal' => ['withdrawn', 'withdrawalid']] as $parent => [$child,
                $field]
        ) {
            $ids = $DB->get_fieldset_select(self::T . $parent, 'id', 'libraryid = ?', [$libraryid]);
            if ($ids) {
                [$in, $params] = $DB->get_in_or_equal($ids);
                $DB->delete_records_select(self::T . $child, "$field $in", $params);
            }
        }
        foreach (['rule', 'hold', 'stocktake', 'withdrawal'] as $table) {
            $DB->delete_records(self::T . $table, ['libraryid' => $libraryid]);
        }
        $DB->delete_records(self::T . 'library', ['id' => $libraryid]);
        return true;
    }

    /**
     * The groups of this year's readers of an entity, for a classroom library.
     *
     * @param int $entityid
     * @param int|null $now
     * @return string[]
     */
    public static function groups(int $entityid, ?int $now = null): array {
        global $DB;
        $year = years::around($entityid, $now ?? time());
        $groups = $DB->get_fieldset_select(
            self::T . 'member',
            'DISTINCT groupname',
            'yearid = :yearid AND groupname IS NOT NULL',
            ['yearid' => $year->id]
        );
        $groups = array_values(array_filter(array_map('strval', $groups), fn($g) => trim($g) !== ''));
        sort($groups, SORT_NATURAL | SORT_FLAG_CASE);
        return $groups;
    }

    /**
     * A short name for the address of a library, from its name, free on the site: «Biblioteca de 4.º B» gives
     * «biblioteca-de-4-b».
     *
     * @param string $text
     * @return string
     */
    public static function slug(string $text): string {
        global $DB;
        $base = \core_text::strtolower(\core_text::specialtoascii(trim($text)));
        $base = trim(preg_replace('/[^a-z0-9]+/', '-', $base), '-');
        $base = substr($base !== '' ? $base : 'biblioteca', 0, 56);
        $slug = $base;
        for ($n = 2; $DB->record_exists(self::T . 'library', ['slug' => $slug]) || ctype_digit($slug); $n++) {
            $slug = $base . '-' . $n;
        }
        return $slug;
    }

    /**
     * The settings of an entity, with what a form changes.
     *
     * @param array $settings
     * @param array $data
     * @return array
     */
    private static function entity_settings(array $settings, array $data): array {
        if (isset($data['classification'])) {
            $settings['classification'] = in_array($data['classification'], self::CLASSIFICATIONS, true)
                ? $data['classification'] : 'other';
        }
        if (array_key_exists('controlletter', $data)) {
            $settings['controlletter'] = !empty($data['controlletter']);
        }
        return $settings + ['classification' => 'udc', 'controlletter' => false];
    }

    /**
     * A start of the school year as MM-DD; September the first when it does not read as one.
     *
     * @param string $text
     * @return string
     */
    private static function yearstart(string $text): string {
        return preg_match('/^(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', trim($text)) ? trim($text) : '09-01';
    }
}
