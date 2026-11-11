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
 * groups (one or several: a shelf two classes share, a cycle, a department), whose readers are its own. A library is
 * only removed while it has no copies.
 */
final class libraries {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var string[] The kinds of library. */
    public const KINDS = ['central', 'classroom', 'department', 'faculty', 'bank'];

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
                fn($l) => !practice::is((int) $l->entityid)
            ))];
        }
        return $entities;
    }

    /**
     * Whether the site has no library of its own yet: the practice libraries of the guided help do not count.
     *
     * @return bool
     */
    public static function none(): bool {
        global $DB;
        return !$DB->record_exists_select(self::T . 'library', 'NOT ' . $DB->sql_like('slug', ':practice'), [
            'practice' => practice::SLUG . '%',
        ]);
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
            // A new centre starts with the site's plan.
            'settings' => json_encode(self::entity_settings([], $data) + ['plan' => plans::defaults()]),
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        // On a site shared by several centres, each one gets its category, where the roles of its staff are given.
        if (get_config('local_oksigeniaayvurenda', 'entitycategories')) {
            access::category((int) $entityid);
        }
        \local_oksigeniaayvurenda\event\centre_created::fire((int) $entityid);
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
        // The catalogues, in the order of the region and only when they are not its own order.
        if (isset($data['sources'])) {
            $data['sources'] = lookup::chosen($entityid, (array) $data['sources']);
        }
        $after = self::entity_settings($settings, $data);
        $DB->update_record(self::T . 'entity', (object) [
            'id' => $entityid,
            'name' => trim((string) ($data['name'] ?? '')) !== '' ? \core_text::substr(trim($data['name']), 0, 255) : $entity->name,
            'yearstart' => self::yearstart((string) ($data['yearstart'] ?? $entity->yearstart)),
            'settings' => json_encode($after),
            'timemodified' => $now ?? time(),
        ]);
        skins::forget($entityid);
        zones::forget($entityid);
        $from = $settings['skin'] ?? skins::SKINS[0];
        $to = $after['skin'] ?? skins::SKINS[0];
        if ($from !== $to) {
            \local_oksigeniaayvurenda\event\skin_changed::fire($entityid, null, ['from' => $from, 'to' => $to]);
        }
    }

    /**
     * Creates a library in an entity.
     *
     * @param int $entityid
     * @param array $data name, kind, parentid, slug (made from the name when empty), groups (for a classroom; a single
     *     group also comes as group).
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
        $settings = self::with_groups([], $kind, $data);
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
     * Renames a library or changes its kind, the library it hangs from or its groups.
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
                'entityid' => $library->entityid])) || ($parentid && self::descends($parentid, (int) $library->id))
        ) {
            $parentid = 0;
        }
        $settings = self::with_groups($settings, $kind, $data);
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

    /** @var int The colours a library's card can take (the six of the covers). */
    public const COLOURS = 6;

    /**
     * How a library's card looks: its colour, its line, whether it is the site's main seat, and its picture.
     *
     * @param \stdClass $library
     * @return array colour, motto, featured, image (address or null).
     */
    public static function look(\stdClass $library): array {
        $settings = json_decode((string) $library->settings, true) ?: [];
        $look = (array) ($settings['look'] ?? []);
        $scene = (string) ($look['scene'] ?? '');
        return [
            'colour' => isset($look['colour']) ? max(0, min(self::COLOURS - 1, (int) $look['colour'])) : ((int) $library->id) % 5,
            'motto' => (string) ($look['motto'] ?? ''),
            'featured' => !empty($look['featured']),
            'scene' => $scene,
            // Its own picture first; else the header of Ayvurenda it chose, if the site has the art package.
            'image' => images::url('libraryimage', (int) $library->id, 'image') ?? ($scene !== '' ? screens::art($scene) : null),
            'own' => images::url('libraryimage', (int) $library->id, 'image') !== null,
        ];
    }

    /**
     * The headers of Ayvurenda a library can choose, from the art package (none without it).
     *
     * @return string[] name => address, in order.
     */
    public static function scenes(): array {
        $dir = \core_component::get_component_directory('local_oksigeniaayvurendaarte');
        $scenes = [];
        foreach ($dir ? (glob($dir . '/pix/cabecera-*.webp') ?: []) : [] as $file) {
            $name = basename($file, '.webp');
            if (preg_match('/^cabecera-\d{1,2}$/', $name)) {
                $scenes[$name] = screens::art($name);
            }
        }
        uksort($scenes, 'strnatcmp');
        return $scenes;
    }

    /**
     * Changes how a library's card looks; marked as the main seat, it is the only one of the site.
     *
     * @param int $libraryid
     * @param array $data colour, motto, featured.
     */
    public static function save_look(int $libraryid, array $data): void {
        global $DB;
        $library = $DB->get_record(self::T . 'library', ['id' => $libraryid], '*', MUST_EXIST);
        $settings = json_decode((string) $library->settings, true) ?: [];
        $settings['look'] = [
            'colour' => max(0, min(self::COLOURS - 1, (int) ($data['colour'] ?? 0))),
            'motto' => \core_text::substr(trim((string) ($data['motto'] ?? '')), 0, 120),
            'featured' => !empty($data['featured']),
            'scene' => preg_match('/^cabecera-\d{1,2}$/', (string) ($data['scene'] ?? '')) ? (string) $data['scene'] : '',
        ];
        $DB->set_field(self::T . 'library', 'settings', json_encode($settings), ['id' => $libraryid]);
        if ($settings['look']['featured']) {
            foreach ($DB->get_records_select(self::T . 'library', 'id <> :id', ['id' => $libraryid]) as $other) {
                $theirs = json_decode((string) $other->settings, true) ?: [];
                if (!empty($theirs['look']['featured'])) {
                    $theirs['look']['featured'] = false;
                    $DB->set_field(self::T . 'library', 'settings', json_encode($theirs), ['id' => $other->id]);
                }
            }
        }
    }

    /**
     * Whether a library hangs, however far down, from another: going up from it, the other comes.
     *
     * @param int $libraryid
     * @param int $ancestorid
     * @return bool
     */
    public static function descends(int $libraryid, int $ancestorid): bool {
        global $DB;
        $seen = [];
        $id = $libraryid;
        while ($id && !isset($seen[$id])) {
            if ($id === $ancestorid) {
                return true;
            }
            $seen[$id] = true;
            $id = (int) $DB->get_field(self::T . 'library', 'parentid', ['id' => $id]);
        }
        return false;
    }

    /**
     * The library to show first, big: the main seat the administrator marked (the school's, the faculty's…); else the
     * central one of the person's own entity; else the first central one.
     *
     * @param \stdClass[] $libraries
     * @param int $userid
     * @return \stdClass|null
     */
    public static function featured(array $libraries, int $userid): ?\stdClass {
        global $DB;
        if (!$libraries) {
            return null;
        }
        $mine = $userid ? array_map('intval', $DB->get_fieldset_select(
            self::T . 'reader',
            'entityid',
            "userid = :userid AND status = 'active'",
            ['userid' => $userid]
        )) : [];
        foreach ($libraries as $library) {
            if (self::look($library)['featured']) {
                return $library;
            }
        }
        $pick = function (array $among) {
            foreach ($among as $library) {
                if ($library->kind === 'central') {
                    return $library;
                }
            }
            return null;
        };
        if ($mine) {
            $own = $pick(array_filter($libraries, fn($l) => in_array((int) $l->entityid, $mine, true)));
            if ($own) {
                return $own;
            }
        }
        return $pick($libraries) ?? reset($libraries);
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
        $purchases = $DB->get_fieldset_select(self::T . 'purchase', 'id', 'libraryid = ?', [$libraryid]);
        if ($purchases) {
            [$in, $params] = $DB->get_in_or_equal($purchases);
            $DB->delete_records_select(self::T . 'purchasewho', "purchaseid $in", $params);
        }
        foreach (
            ['rule', 'hold', 'stocktake', 'withdrawal', 'keeper', 'selfpost', 'purchase', 'purchaseorder',
            'budget', 'ill', 'sanction', 'formatask'] as $table
        ) {
            $DB->delete_records(self::T . $table, ['libraryid' => $libraryid]);
        }
        $DB->delete_records(self::T . 'library', ['id' => $libraryid]);
        \local_oksigeniaayvurenda\event\library_deleted::fire((int) $library->entityid, $libraryid);
        return true;
    }

    /**
     * The settings of a library with the groups a form gives: a classroom has one or several; the others, none.
     *
     * @param array $settings
     * @param string $kind
     * @param array $data groups (a list) or group (one).
     * @return array
     */
    private static function with_groups(array $settings, string $kind, array $data): array {
        unset($settings['group'], $settings['groups']);
        $groups = array_key_exists('groups', $data) ? (array) $data['groups'] : [$data['group'] ?? ''];
        $groups = array_values(array_unique(array_filter(
            array_map(fn($g) => trim((string) $g), $groups),
            fn($g) => $g !== ''
        )));
        sort($groups, SORT_NATURAL | SORT_FLAG_CASE);
        if ($kind === 'classroom' && $groups) {
            $settings['groups'] = $groups;
        }
        return $settings;
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
        // Those are the practice libraries' own.
        if (str_starts_with($base, practice::SLUG)) {
            $base = 'biblioteca-' . substr($base, 0, 44);
        }
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
        foreach (['copyfrom', 'cardfrom'] as $key) {
            if (array_key_exists($key, $data)) {
                $from = cataloguer::first_code((string) $data[$key]);
                if ($from === null) {
                    unset($settings[$key]);
                } else {
                    $settings[$key] = $from;
                }
            }
        }
        if (array_key_exists('photos', $data)) {
            $settings['photos'] = !empty($data['photos']);
        }
        if (isset($data['sources'])) {
            $list = array_values(array_filter((array) $data['sources'], fn($s) => isset(lookup::PRESETS[$s])));
            if ($list) {
                $settings['sources'] = $list;
            } else {
                unset($settings['sources']);
            }
        }
        foreach (['linkproxy', 'linkresolver'] as $name) {
            if (array_key_exists($name, $data)) {
                $address = trim((string) $data[$name]);
                $settings[$name] = \local_oksigeniaayvurenda\core\links::usable($address) ? $address : '';
            }
        }
        if (array_key_exists('timezone', $data)) {
            $settings['timezone'] = zones::known((string) $data['timezone']) ? (string) $data['timezone'] : '';
        }
        // The centre's week: the day it starts (0, as Moodle says for the site or its language) and the days the
        // libraries close every week (Saturday and Sunday unless it says).
        if (array_key_exists('weekstart', $data)) {
            $start = (int) $data['weekstart'];
            if ($start >= 1 && $start <= 7) {
                $settings['weekstart'] = $start;
            } else {
                unset($settings['weekstart']);
            }
        }
        if (array_key_exists('restdays', $data)) {
            $rest = array_values(array_unique(array_filter(
                array_map('intval', (array) $data['restdays']),
                fn($day) => $day >= 1 && $day <= 7
            )));
            sort($rest);
            if ($rest === zones::REST) {
                unset($settings['restdays']);
            } else {
                $settings['restdays'] = $rest;
            }
        }
        $before = $settings['skin'] ?? skins::SKINS[0];
        if (isset($data['skin'])) {
            $settings['skin'] = in_array($data['skin'], skins::SKINS, true) ? $data['skin'] : skins::SKINS[0];
        }
        // How many school years a loan keeps its reader (forgetting); two, unless the centre says otherwise.
        if (isset($data['historyyears']) && in_array((int) $data['historyyears'], forgetting::CHOICES, true)) {
            if ((int) $data['historyyears'] === forgetting::KEEP) {
                unset($settings['historyyears']);
            } else {
                $settings['historyyears'] = (int) $data['historyyears'];
            }
        }
        // How a review is signed: only what differs from the skin's own is kept, so a new skin brings its own.
        if (isset($data['reviewsign'])) {
            $sign = in_array($data['reviewsign'], reviews::SIGNS, true) ? $data['reviewsign'] : '';
            if ($sign === '' || $sign === reviews::SKINSIGN[$settings['skin'] ?? skins::SKINS[0]]) {
                unset($settings['reviewsign']);
            } else {
                $settings['reviewsign'] = $sign;
            }
        }
        // What is on: only what differs from the skin is kept; a new skin starts with its own.
        if (($settings['skin'] ?? skins::SKINS[0]) !== $before) {
            unset($settings['play']);
        } else if (isset($data['play'])) {
            $settings['play'] = skins::changes($settings['skin'] ?? skins::SKINS[0], (array) $data['play']);
            if (!$settings['play']) {
                unset($settings['play']);
            }
        }
        // The stages that play no class challenge; all play when none is listed.
        if (array_key_exists('challengesoff', $data)) {
            $off = array_values(array_unique(array_filter(array_map(
                fn($s) => trim((string) $s),
                (array) $data['challengesoff']
            ), fn($s) => $s !== '')));
            $settings['challengesoff'] = $off;
            if (!$off) {
                unset($settings['challengesoff']);
            }
        }
        // The card each stage sees; what is left automatic is not kept.
        if (array_key_exists('cardlooks', $data)) {
            $looks = [];
            foreach ((array) $data['cardlooks'] as $stage => $look) {
                if (trim((string) $stage) !== '' && in_array($look, \local_oksigeniaayvurenda\core\card_look::LOOKS, true)) {
                    $looks[trim((string) $stage)] = $look;
                }
            }
            $settings['cardlooks'] = $looks;
            if (!$looks) {
                unset($settings['cardlooks']);
            }
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
