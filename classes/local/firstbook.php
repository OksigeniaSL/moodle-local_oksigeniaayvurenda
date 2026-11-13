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
 * «Su primer libro» (the first «subidón» of the review of 12 November): the family of a young reader hears of their first book.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * The day a young reader (the card of the flower) takes home their very first book of the centre, their family hears of it
 * the next morning, in the centre's own: a postcard with its cover, the child's name and the day, to keep and send on,
 * and the way to give that book their own voice for them. The one who has to be moved is the mother or the father; the
 * child will say «my book» by its title. Where the site keeps its families (Oksigenia Family), and only if the centre
 * writes to families.
 */
final class firstbook {
    /** @var string Prefix of the tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * Whether a centre sends it: as it chose, or, if it never did, as it writes to families about late books.
     *
     * @param int $entityid
     * @return bool
     */
    public static function on(int $entityid): bool {
        global $DB;
        $entity = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        return array_key_exists('firstbook', $entity) ? !empty($entity['firstbook'])
            : !empty(sanctions::settings($entityid)['noticefamilies']);
    }

    /**
     * Saves whether a centre sends it.
     *
     * @param int $entityid
     * @param bool $on
     */
    public static function save(int $entityid, bool $on): void {
        global $DB;
        $entity = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        $entity['firstbook'] = $on;
        $DB->set_field(self::T . 'entity', 'settings', json_encode($entity), ['id' => $entityid]);
    }

    /**
     * The first loans of the centre's readers with an account made in the day before now: their first book here, of a
     * library and not of a textbook bank.
     *
     * @param int $now
     * @param int[]|null $centres Only these centres.
     * @return \stdClass[] The loan (id, recordid, title, timeloaned, libraryid) with its reader (readerid, userid, entityid).
     */
    public static function due(int $now, ?array $centres = null): array {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT l.id, l.readerid, l.libraryid, l.timeloaned, i.recordid, rec.title, r.userid, r.entityid
               FROM {" . self::T . "loan} l
               JOIN {" . self::T . "reader} r ON r.id = l.readerid
               JOIN {" . self::T . "item} i ON i.id = l.itemid
               JOIN {" . self::T . "record} rec ON rec.id = i.recordid
              WHERE l.timeloaned >= :since AND l.timeloaned < :now AND r.userid IS NOT NULL AND r.status = 'active'
                    AND r.readertype <> :ill AND " . bank::reading('l.libraryid') . "
                    AND NOT EXISTS (SELECT 1
                                      FROM {" . self::T . "loan} o
                                      JOIN {" . self::T . "library} ob ON ob.id = o.libraryid
                                     WHERE o.readerid = l.readerid AND ob.kind <> :bank
                                           AND (o.timeloaned < l.timeloaned OR (o.timeloaned = l.timeloaned AND o.id < l.id)))
           ORDER BY l.timeloaned, l.id",
            ['since' => $now - DAYSECS, 'now' => $now, 'ill' => ill::READERTYPE, 'bank' => bank::KIND]
        );
        return array_values(array_filter($rows, fn($row) => $centres === null || in_array((int) $row->entityid, $centres, true)));
    }

    /**
     * Tells the families of the young readers whose first book went home in the day before now, in their centres'
     * morning.
     *
     * @param int $now
     * @param int[]|null $centres Only these centres (those whose morning it is).
     * @return int Adults told.
     */
    public static function run(int $now, ?array $centres = null): int {
        global $DB;
        if (!class_exists('\\local_oksigeniafamily\\api')) {
            return 0;
        }
        $told = 0;
        foreach (self::due($now, $centres) as $loan) {
            $entityid = (int) $loan->entityid;
            if (!self::on($entityid) || practice::is($entityid) || access::closed($entityid)) {
                continue;
            }
            $reader = $DB->get_record(self::T . 'reader', ['id' => $loan->readerid]);
            if (!$reader || cards::look_of($reader, $now) !== 'flower') {
                continue;
            }
            // One family whose message fails does not leave the others untold.
            try {
                $told += self::tell($reader, $loan, $now);
            } catch (\Throwable $e) {
                debugging('Ayvurenda: the first book of reader ' . $reader->id . ' was not told: ' . $e->getMessage());
            }
        }
        return $told;
    }

    /**
     * Tells a young reader's family of their first book: one message to each adult of the family.
     *
     * @param \stdClass $reader
     * @param \stdClass $loan As due() gives it.
     * @param int $now
     * @return int Adults told.
     */
    public static function tell(\stdClass $reader, \stdClass $loan, int $now): int {
        global $DB;
        $child = \core_user::get_user((int) $reader->userid);
        $library = $DB->get_record(self::T . 'library', ['id' => $loan->libraryid]);
        if (!$child || !$library) {
            return 0;
        }
        $entityid = (int) $reader->entityid;
        // The way to give that book their voice: one link for the whole family, if the centre has voices.
        $token = plans::allows($entityid, 'voices') ? voices::invite(
            $library,
            (int) $loan->recordid,
            0,
            get_string('voiceinvitefamilyof', 'local_oksigeniaayvurenda', $child->firstname),
            $now,
            (int) $reader->id
        ) : '';
        $voice = $token !== '' ? (new \moodle_url('/local/oksigeniaayvurenda/family.php', ['token' => $token]))->out(false)
            : '';
        $told = 0;
        $seen = [];
        foreach (\local_oksigeniafamily\api::adults_of_child((int) $child->id) as $adult) {
            $user = $adult->userid ? \core_user::get_user((int) $adult->userid) : null;
            if ($user && (!empty($user->deleted) || !empty($user->suspended))) {
                continue;
            }
            $email = trim((string) $adult->email);
            if (!$user && !validate_email($email)) {
                continue;
            }
            $key = $user ? 'u' . $user->id : 'e' . \core_text::strtolower($email);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $language = force_current_language((string) ($user->lang ?? ''));
            $a = (object) ['child' => $child->firstname, 'title' => format_string($loan->title, true, ['escape' => false]),
                'library' => format_string($library->name, true, ['escape' => false]),
                'day' => zones::date((int) $loan->timeloaned, 'strftimedateshort', $entityid)];
            $str = fn(string $key) => get_string($key, 'local_oksigeniaayvurenda', $a);
            $subject = $str('firstbooksubject');
            $hello = get_string('mailhello', 'local_oksigeniaayvurenda', $adult->firstname);
            $design = [
                'library' => $library,
                'entityid' => $entityid,
                'hello' => $hello,
                'heading' => $subject,
                'lead' => [$str('firstbooklead')],
                // The postcard to keep and send on, when the art package has it (Gaudí).
                'postcard' => screens::art('postal/huecos', 'json') ? ['url' => mail::postcard((int) $loan->id),
                    'alt' => $str('firstbookpostcardalt')] : null,
                'books' => [['line' => $str('firstbookline')] + mail::book((int) $loan->recordid, $a->title)],
                'button' => $voice !== '' ? ['url' => $voice, 'text' => $str('firstbookvoice')] : null,
                'after' => [$voice !== '' ? $str('firstbookaftervoice') : $str('firstbookafter')],
                'why' => get_string('mailwhyfamily', 'local_oksigeniaayvurenda'),
            ];
            $text = $hello . "\n\n" . $str('firstbooklead') . "\n\n" . ($voice !== '' ? $str('firstbookaftervoice') . "\n"
                . $voice : $str('firstbookafter'));
            if ($user) {
                $sent = notices::send(
                    'firstbook',
                    $user,
                    $subject,
                    $text,
                    $voice,
                    $str('firstbookvoice'),
                    $a->library,
                    $design
                );
            } else {
                $to = clone \core_user::get_noreply_user();
                $to->email = $email;
                $to->firstname = (string) $adult->firstname;
                $to->lastname = (string) $adult->lastname;
                $to->mailformat = 1;
                $sent = (bool) email_to_user($to, mail::from($a->library), $subject, $text, mail::html($design));
            }
            force_current_language($language);
            $told += $sent ? 1 : 0;
        }
        return $told;
    }
}
