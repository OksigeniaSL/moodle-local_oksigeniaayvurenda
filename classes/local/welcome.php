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
 * A notice for whoever is given a place in a library: who gave it, which library, and the way to its desk.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * Someone given the librarian's or the manager's role of a centre, or the desk of one library, used to hear nothing and
 * find the library only if they looked for it. Now a notice of Moodle tells them, by whatever way they hear from it (the
 * site, e-mail, the app, a messenger), with the way to the desk, where «Your first day here» waits.
 */
final class welcome {
    /** @var bool While a centre is signed up: its owner already hears of it by the account's e-mail. */
    public static bool $quiet = false;

    /**
     * A role of the library given: tells the person, once for that role and centre.
     *
     * @param \core\event\role_assigned $event
     */
    public static function role_assigned(\core\event\role_assigned $event): void {
        global $DB;
        $shortname = (string) $DB->get_field('role', 'shortname', ['id' => $event->objectid]);
        if (!in_array($shortname, [roles::LIBRARIAN, roles::MANAGER], true) || !$event->relateduserid) {
            return;
        }
        $entityid = self::entity_of($event->get_context());
        $library = $entityid ? mail::main_library($entityid) : null;
        if ($library) {
            self::send((int) $event->relateduserid, $library, (int) $event->userid);
        }
    }

    /**
     * The desk of one library given to someone.
     *
     * @param \local_oksigeniaayvurenda\event\keeper_added $event
     */
    public static function keeper_added(\local_oksigeniaayvurenda\event\keeper_added $event): void {
        global $DB;
        $library = $DB->get_record('local_oksigeniaayvurenda_library', ['id' => $event->objectid]);
        if ($library && $event->relateduserid) {
            self::send((int) $event->relateduserid, $library, (int) $event->userid);
        }
    }

    /**
     * Tells someone they have a place in a library.
     *
     * @param int $userid
     * @param \stdClass $library
     * @param int $byuserid Who gave it.
     * @return bool Whether it was sent.
     */
    public static function send(int $userid, \stdClass $library, int $byuserid): bool {
        $entityid = (int) $library->entityid;
        if (self::$quiet || $userid === $byuserid || practice::is($entityid) || sample::is($entityid)) {
            return false;
        }
        $user = \core_user::get_user($userid);
        if (!$user || $user->deleted || $user->suspended) {
            return false;
        }
        $by = \core_user::get_user($byuserid);
        $language = force_current_language((string) $user->lang);
        $a = (object) [
            'who' => $by && !isguestuser($by) ? fullname($by) : get_string('welcomesomeone', 'local_oksigeniaayvurenda'),
            'library' => format_string($library->name, true, ['escape' => false]),
        ];
        $url = (new \moodle_url('/local/oksigeniaayvurenda/desk.php', ['library' => $library->slug]))->out(false);
        $subject = get_string('welcomesubject', 'local_oksigeniaayvurenda', $a);
        $body = get_string('welcomebody', 'local_oksigeniaayvurenda', $a);
        $sent = notices::send(
            'staffwelcome',
            $user,
            $subject,
            $body,
            $url,
            get_string('desktitle', 'local_oksigeniaayvurenda'),
            '',
            mail::plain($subject, $body) + [
                'library' => $library,
                'entityid' => $entityid,
                'button' => ['url' => $url, 'text' => get_string('welcomebutton', 'local_oksigeniaayvurenda')],
                'why' => get_string('mailwhystaff', 'local_oksigeniaayvurenda'),
                'prefs' => true,
            ]
        );
        force_current_language($language);
        return $sent;
    }

    /**
     * The centre a context belongs to: its category on a site of several centres; the site, on a site of one.
     *
     * @param \context $context
     * @return int|null
     */
    private static function entity_of(\context $context): ?int {
        global $DB;
        if ($context->contextlevel === CONTEXT_COURSECAT) {
            $id = $DB->get_field('local_oksigeniaayvurenda_entity', 'id', ['categoryid' => $context->instanceid]);
            return $id ? (int) $id : null;
        }
        if ($context->contextlevel !== CONTEXT_SYSTEM || get_config('local_oksigeniaayvurenda', 'entitycategories')) {
            return null;
        }
        // On a site of one centre, its first own centre (not a practice nor the sample one).
        foreach ($DB->get_records('local_oksigeniaayvurenda_entity', null, 'id', 'id') as $entity) {
            if (!practice::is((int) $entity->id) && !sample::is((int) $entity->id)) {
                return (int) $entity->id;
            }
        }
        return null;
    }
}
