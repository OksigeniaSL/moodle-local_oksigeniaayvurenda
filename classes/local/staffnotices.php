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
 * What waits for the library's staff (reviews to read, books classes asked for), in one Moodle notice a day at most, and
 * only when something new arrived since the last one.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class staffnotices {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var string The preference with when each one was last told. */
    public const PREFERENCE = 'local_oksigeniaayvurenda_stafftold';

    /**
     * Tells the staff of each centre what waits for them.
     *
     * @param int|null $now
     * @return array centres and sent.
     */
    public static function run(?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $stats = ['centres' => 0, 'sent' => 0];
        $staff = get_users_by_capability(\context_system::instance(), 'local/oksigeniaayvurenda:desk', 'u.id');
        if (!$staff) {
            return $stats;
        }
        foreach ($DB->get_records(self::T . 'entity', null, 'id') as $entity) {
            $reviews = reviews::waiting((int) $entity->id);
            $wishes = wishes::waiting((int) $entity->id);
            if (!$reviews && !$wishes) {
                continue;
            }
            $stats['centres']++;
            $library = $DB->get_records(
                self::T . 'library',
                ['entityid' => $entity->id],
                "kind = 'central' DESC, id",
                'id, slug',
                0,
                1
            );
            $library = reset($library);
            // Its staff: who works at the desk and is one of its readers; if nobody is, whoever works at a desk.
            [$in, $params] = $DB->get_in_or_equal(array_keys($staff), SQL_PARAMS_NAMED);
            $theirs = $DB->get_fieldset_select(
                self::T . 'reader',
                'DISTINCT userid',
                "entityid = :entityid AND userid $in",
                $params + ['entityid' => $entity->id]
            );
            foreach ($theirs ?: array_keys($staff) as $userid) {
                $told = (int) get_user_preferences(self::PREFERENCE, 0, (int) $userid);
                if (!self::anything_new((int) $entity->id, $told)) {
                    continue;
                }
                $user = \core_user::get_user((int) $userid);
                if (!$user || $user->deleted || $user->suspended) {
                    continue;
                }
                [$subject, $body] = self::words($entity, $library, $reviews, $wishes);
                if (notices::send('stafftodo', $user, $subject, $body)) {
                    set_user_preference(self::PREFERENCE, $now, (int) $userid);
                    $stats['sent']++;
                }
            }
        }
        return $stats;
    }

    /**
     * Whether a review or a book asked for arrived since someone was last told.
     *
     * @param int $entityid
     * @param int $since
     * @return bool
     */
    private static function anything_new(int $entityid, int $since): bool {
        global $DB;
        $params = ['entityid' => $entityid, 'since' => $since];
        return $DB->record_exists_select(
            self::T . 'review',
            "entityid = :entityid AND status = 'pending' AND timemodified > :since",
            $params
        ) || $DB->record_exists_select(self::T . 'wish', "entityid = :entityid AND status = 'asked'
            AND timemodified > :since", $params);
    }

    /**
     * The words of the notice: what waits, and where.
     *
     * @param \stdClass $entity
     * @param \stdClass|false $library
     * @param int $reviews
     * @param int $wishes
     * @return string[] subject and body.
     */
    private static function words(\stdClass $entity, $library, int $reviews, int $wishes): array {
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        $parts = [];
        $lines = [];
        $url = fn(string $page) => $library ? (new \moodle_url('/local/oksigeniaayvurenda/' . $page, ['library' => $library->slug]))
            ->out(false) : '';
        if ($reviews) {
            $parts[] = $reviews === 1 ? $str('stafftodoreviewsone') : $str('stafftodoreviewsmany', $reviews);
            $lines[] = end($parts) . ': ' . $url('reviews.php');
        }
        if ($wishes) {
            $parts[] = $wishes === 1 ? $str('stafftodowishesone') : $str('stafftodowishesmany', $wishes);
            $lines[] = end($parts) . ': ' . $url('wishes.php');
        }
        $what = implode($str('stafftodoand'), $parts);
        return [$str('stafftodosubject', (object) ['centre' => format_string($entity->name), 'what' => $what]),
            $str('stafftodobody', format_string($entity->name)) . "\n\n" . implode("\n", $lines)];
    }
}
