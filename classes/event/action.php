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

namespace local_oksigeniaayvurenda\event;

use local_oksigeniaayvurenda\local\access;

/**
 * Something delicate done in a library, for Moodle's logs: who, when and on what. Only ids and codes, never a name or
 * the title of what someone reads; loans and searches are not told at all, because that would be watching what each one reads.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class action extends \core\event\base {
    /** @var string What it does: c, r, u or d. */
    protected const CRUD = 'u';

    /** @var string The table of the plugin its object is in. */
    protected const TABLE = 'entity';

    /**
     * What every action shares.
     */
    protected function init() {
        $this->data['crud'] = static::CRUD;
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_oksigeniaayvurenda_' . static::TABLE;
    }

    /**
     * Its short name, without the namespace.
     *
     * @return string
     */
    private static function short(): string {
        return substr(strrchr(static::class, '\\'), 1);
    }

    /**
     * Its name in the logs.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event' . str_replace('_', '', self::short()), 'local_oksigeniaayvurenda');
    }

    /**
     * What it says in the logs, in English as Moodle asks.
     *
     * @return string
     */
    public function get_description() {
        $other = $this->other ? ' ' . json_encode($this->other) : '';
        return "The user with id '{$this->userid}': " . str_replace('_', ' ', self::short()) . ', on the ' . static::TABLE
            . " with id '{$this->objectid}'" . $other . '.';
    }

    /**
     * Tells it, in the place of the centre.
     *
     * @param int $entityid
     * @param int|null $objectid The row it is about; the centre if none.
     * @param array $other Ids and codes only.
     * @param int $relateduserid The account it is about, if any.
     */
    public static function fire(int $entityid, ?int $objectid = null, array $other = [], int $relateduserid = 0): void {
        $data = ['context' => access::context($entityid), 'objectid' => $objectid ?? $entityid, 'other' => $other];
        if ($relateduserid) {
            $data['relateduserid'] = $relateduserid;
        }
        static::create($data)->trigger();
    }

    /**
     * Nothing readable gets into the logs by mistake.
     *
     * @throws \coding_exception
     */
    protected function validate_data() {
        parent::validate_data();
        foreach ((array) ($this->other ?? []) as $key => $value) {
            if (!is_int($value) && !is_bool($value) && !(is_string($value) && preg_match('/^[a-z0-9_-]{0,40}$/', $value))) {
                throw new \coding_exception('An event of the library carries only ids and codes, not: ' . $key);
            }
        }
    }

    /**
     * Not restored with a course.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return self::NOT_MAPPED;
    }

    /**
     * Not restored with a course.
     *
     * @return bool
     */
    public static function get_other_mapping() {
        return false;
    }
}
