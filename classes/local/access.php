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
 * Who may do what in each centre. A centre can have a course category of its own: then the staff's roles are given
 * there, and Moodle itself keeps the staff of one centre out of every other (a role given at site level still reaches
 * all). Looking up a library is for whoever has the capability there or is one of its active readers, so that on a
 * site shared by several centres, where the authenticated user does not get it at site level, each reader sees only
 * the libraries of their centres.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class access {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var string[] What the staff do: never reached by being a reader. */
    public const STAFF = ['desk', 'catalogue', 'manage'];

    /**
     * The context where the capabilities of a centre are checked: its category, or the site's.
     *
     * @param \stdClass|int|null $where A library, an entity id, or nothing for the site.
     * @return \context
     */
    public static function context($where): \context {
        global $DB;
        $entityid = self::entity_of($where);
        if ($entityid) {
            $categoryid = (int) $DB->get_field(self::T . 'entity', 'categoryid', ['id' => $entityid]);
            if ($categoryid) {
                $context = \context_coursecat::instance($categoryid, IGNORE_MISSING);
                if ($context) {
                    return $context;
                }
            }
        }
        return \context_system::instance();
    }

    /**
     * Whether someone may do something in a centre, or, with no centre, in any of them.
     *
     * @param string $capability Full name, as local/oksigeniaayvurenda:desk.
     * @param \stdClass|int|null $where A library, an entity id, or nothing.
     * @param int|null $userid The current user when not given.
     * @return bool
     */
    public static function can(string $capability, $where, ?int $userid = null): bool {
        global $DB, $USER;
        $userid = $userid ?? (int) ($USER->id ?? 0);
        $entityid = self::entity_of($where);
        // The practice library of the guided help, invented and the same for everyone: for whoever may do it anywhere.
        if ($entityid && practice::is($entityid)) {
            $entityid = 0;
        }
        $view = $capability === 'local/oksigeniaayvurenda:view';
        if (!$entityid) {
            // With centres apart, looking up is only ever in a centre: the site's grant to everyone does not count.
            return (!($view && self::apart()) && has_capability($capability, \context_system::instance(), $userid)) ||
                (bool) self::entities($capability, $userid);
        }
        if (!$view) {
            return has_capability($capability, self::context($entityid), $userid);
        }
        // A centre open only to its staff for now: its readers do not even see the way in.
        if (self::closed($entityid) && !self::is_staff($entityid, $userid)) {
            return false;
        }
        if (self::is_reader($entityid, $userid)) {
            return true;
        }
        $context = self::context($entityid);
        if (!self::apart() || $context->contextlevel !== CONTEXT_COURSECAT) {
            return has_capability($capability, $context, $userid);
        }
        // With centres apart, the authenticated user's grant on the whole site does not open a centre: its staff, the
        // site's managers or a role given in its category do.
        foreach (self::STAFF as $staff) {
            if (has_capability('local/oksigeniaayvurenda:' . $staff, $context, $userid)) {
                return true;
            }
        }
        return $userid && has_capability($capability, $context, $userid) &&
            $DB->record_exists('role_assignments', ['userid' => $userid, 'contextid' => $context->id]);
    }

    /**
     * Whether the site keeps its centres apart, each with its category.
     *
     * @return bool
     */
    public static function apart(): bool {
        return (bool) get_config('local_oksigeniaayvurenda', 'entitycategories');
    }

    /**
     * Stops a request of someone who may not do something in a centre (for the AJAX answers; the pages use shell).
     *
     * @param string $capability
     * @param \stdClass|int|null $where
     * @throws \required_capability_exception
     */
    public static function require(string $capability, $where): void {
        if (!self::can($capability, $where)) {
            throw new \required_capability_exception(self::context($where), $capability, 'nopermissions', '');
        }
    }

    /**
     * The centres where someone may do something.
     *
     * @param string $capability
     * @param int|null $userid
     * @return int[] Entity ids.
     */
    public static function entities(string $capability, ?int $userid = null): array {
        global $DB, $USER;
        $userid = $userid ?? (int) ($USER->id ?? 0);
        $ids = [];
        foreach ($DB->get_fieldset_select(self::T . 'entity', 'id', '1 = 1') as $entityid) {
            if (!practice::is((int) $entityid) && self::can($capability, (int) $entityid, $userid)) {
                $ids[] = (int) $entityid;
            }
        }
        sort($ids);
        return $ids;
    }

    /**
     * Whether a centre is open only to its staff for now, while it gets ready: its readers see no way in and get no
     * notices, and its public door is shut. Only whoever sets the plans opens it, where its staff do not see it.
     *
     * @param int $entityid
     * @return bool
     */
    public static function closed(int $entityid): bool {
        global $DB;
        if (!$entityid) {
            return false;
        }
        $cache = \cache::make('local_oksigeniaayvurenda', 'access');
        $closed = $cache->get('closed_' . $entityid);
        if ($closed === false) {
            $settings = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
            $closed = empty($settings['closed']) ? 0 : 1;
            $cache->set('closed_' . $entityid, $closed);
        }
        return (bool) $closed;
    }

    /**
     * Opens a centre to its readers, or keeps it for its staff only (the caller checks the site's capability).
     *
     * @param int $entityid
     * @param bool $closed
     */
    public static function set_closed(int $entityid, bool $closed): void {
        global $DB;
        $settings = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        $settings['closed'] = $closed;
        $DB->set_field(self::T . 'entity', 'settings', json_encode($settings), ['id' => $entityid]);
        \cache::make('local_oksigeniaayvurenda', 'access')->delete('closed_' . $entityid);
    }

    /**
     * Whether someone is staff of a centre: lends, catalogues or runs it.
     *
     * @param int $entityid
     * @param int $userid
     * @return bool
     */
    public static function is_staff(int $entityid, int $userid): bool {
        if (!$userid) {
            return false;
        }
        $context = self::context($entityid);
        foreach (self::STAFF as $staff) {
            if (has_capability('local/oksigeniaayvurenda:' . $staff, $context, $userid)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether someone works at a library's desk only as its keeper (keepers::is), not as staff of its centre: the desk
     * then serves that library alone.
     *
     * @param \stdClass|null $library
     * @param int|null $userid
     * @return bool
     */
    public static function keeps(?\stdClass $library, ?int $userid = null): bool {
        global $USER;
        $userid = $userid ?? (int) ($USER->id ?? 0);
        return $library && !self::can('local/oksigeniaayvurenda:desk', $library, $userid)
            && keepers::is((int) $library->id, $userid);
    }

    /**
     * Whether a user is an active reader of a centre.
     *
     * @param int $entityid
     * @param int $userid
     * @return bool
     */
    public static function is_reader(int $entityid, int $userid): bool {
        global $DB;
        if (!$userid || isguestuser($userid)) {
            return false;
        }
        $cache = \cache::make('local_oksigeniaayvurenda', 'access');
        $key = 'reader_' . $entityid . '_' . $userid;
        $reader = $cache->get($key);
        if ($reader === false) {
            $reader = $DB->record_exists(self::T . 'reader', ['entityid' => $entityid, 'userid' => $userid,
                'status' => 'active']) ? 1 : 0;
            $cache->set($key, $reader);
        }
        return (bool) $reader;
    }

    /**
     * Gives a centre a course category of its own, hidden, where the roles of its staff are given; the one it has if
     * it has one.
     *
     * @param int $entityid
     * @return int The category id.
     */
    public static function category(int $entityid): int {
        global $DB;
        $entity = $DB->get_record(self::T . 'entity', ['id' => $entityid], 'id, name, categoryid', MUST_EXIST);
        if ($entity->categoryid && $DB->record_exists('course_categories', ['id' => $entity->categoryid])) {
            return (int) $entity->categoryid;
        }
        $idnumber = 'ayvurenda-' . $entityid;
        $categoryid = (int) $DB->get_field('course_categories', 'id', ['idnumber' => $idnumber]);
        if (!$categoryid) {
            $categoryid = (int) \core_course_category::create([
                'name' => get_string('accesscategory', 'local_oksigeniaayvurenda', $entity->name),
                'idnumber' => $idnumber,
                'visible' => 0,
                'description' => get_string('accesscategory_desc', 'local_oksigeniaayvurenda'),
            ])->id;
        }
        $DB->set_field(self::T . 'entity', 'categoryid', $categoryid, ['id' => $entityid]);
        return $categoryid;
    }

    /**
     * The course categories of a centre with its own: that one and those inside it. Its cohorts and courses are the only
     * ones it may bring readers from. Null when it has none: then the whole site's.
     *
     * @param int $entityid
     * @return int[]|null
     */
    public static function categories(int $entityid): ?array {
        $context = self::context($entityid);
        if ($context->contextlevel !== CONTEXT_COURSECAT) {
            return null;
        }
        $category = \core_course_category::get((int) $context->instanceid, IGNORE_MISSING, true);
        return $category ? array_merge([(int) $category->id], array_map('intval', $category->get_all_children_ids())) : [];
    }

    /**
     * Forgets what this request knew of who reads where (after adding or changing readers).
     */
    public static function forget(): void {
        \cache::make('local_oksigeniaayvurenda', 'access')->purge();
    }

    /**
     * The entity id of a library, an entity id, or nothing.
     *
     * @param \stdClass|int|null $where
     * @return int
     */
    private static function entity_of($where): int {
        if ($where instanceof \stdClass) {
            return (int) ($where->entityid ?? 0);
        }
        return (int) $where;
    }
}
