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
 * «Leyó para los pequeños» (a «subidón» of the review of 12 November): an older reader reads a book aloud for the little ones.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * A reader past the flower records a book in Ayvu rande «for the little ones». Once the library or their tutor approves it,
 * the little ones find it on their home page; and, if whoever read it wants and the centre turned it on, their family gets
 * the next morning a link to hear it without an account, to send on: a father who hears his son of fourteen reading to
 * the six-year-olds. The link is sealed and opens only that voice, while it is published.
 */
final class forlittle {
    /** @var string Prefix of the tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var int Voices on the little ones' shelf. */
    public const SHELF = 8;

    /** @var int Days after its approval a voice is still news to its family: a centre that turns it on later sends no backlog. */
    public const NEWS = 14;

    /**
     * Whether a centre sends the family the way to hear it: only once it turned it on.
     *
     * @param int $entityid
     * @return bool
     */
    public static function on(int $entityid): bool {
        global $DB;
        $entity = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        return !empty($entity['readforlittle']);
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
        $entity['readforlittle'] = $on;
        $DB->set_field(self::T . 'entity', 'settings', json_encode($entity), ['id' => $entityid]);
    }

    /**
     * Whether a reader may read for the little ones: anyone past the flower.
     *
     * @param \stdClass|null $reader
     * @param int $now
     * @return bool
     */
    public static function can_read(?\stdClass $reader, int $now): bool {
        return $reader && cards::look_of($reader, $now) !== 'flower';
    }

    /**
     * Whether a reader's family can be asked to hear it: the centre sends it, and the reader's account has a family.
     *
     * @param \stdClass|null $reader
     * @return bool
     */
    public static function can_tell(?\stdClass $reader): bool {
        if (
            !$reader || empty($reader->userid) || !class_exists('\\local_oksigeniafamily\\api')
            || !self::on((int) $reader->entityid)
        ) {
            return false;
        }
        return (bool) \local_oksigeniafamily\api::adults_of_child((int) $reader->userid);
    }

    /**
     * Marks a voice just recorded: for the little ones, and whether its reader's family hears it. Only what the reader
     * may choose is kept.
     *
     * @param int $digitalid
     * @param \stdClass|null $reader
     * @param bool $little
     * @param bool $family
     * @param int $now
     */
    public static function mark(int $digitalid, ?\stdClass $reader, bool $little, bool $family, int $now): void {
        global $DB;
        $little = $little && self::can_read($reader, $now);
        $DB->update_record(self::T . 'digital', (object) ['id' => $digitalid, 'forlittle' => (int) $little,
            'tellfamily' => (int) ($little && $family && self::can_tell($reader))]);
    }

    /**
     * The little ones' shelf: the voices the older readers read for them that whoever looks may hear, the newest first,
     * one per book.
     *
     * @param \stdClass $library
     * @param array $viewer As digital::viewer() gives it.
     * @param int $limit
     * @return array[] recordid, title, url (the record), hascover, coverurl, colour, audio (its voice), by (who reads it to
     *     you) and playlabel.
     */
    public static function shelf(\stdClass $library, array $viewer, int $limit = self::SHELF): array {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT d.*, r.title AS recordtitle
               FROM {" . self::T . "digital} d
               JOIN {" . self::T . "record} r ON r.id = d.recordid
              WHERE d.entityid = :entityid AND d.kind = 'voice' AND d.status = 'published' AND d.forlittle = 1
           ORDER BY d.timemodified DESC, d.id DESC",
            ['entityid' => $library->entityid],
            0,
            $limit * 4
        );
        $items = [];
        foreach ($rows as $row) {
            if (count($items) < $limit && !isset($items[$row->recordid]) && voices::can_hear($row, $viewer)) {
                $items[$row->recordid] = $row;
            }
        }
        $covers = covers::urls(array_map('intval', array_keys($items)));
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        return array_values(array_map(function ($row) use ($library, $covers, $str) {
            $title = format_string($row->recordtitle, true, ['escape' => false]);
            // Who reads it to you: as they signed it, or «an older one».
            $by = (string) $row->signature !== '' ? $str('littlereadby', format_string($row->signature, true, ['escape' => false]))
                : $str('littlereadbyanon');
            return [
                'recordid' => (int) $row->recordid,
                'title' => $title,
                'url' => (new \moodle_url('/local/oksigeniaayvurenda/record.php', ['library' => $library->slug,
                    'id' => (int) $row->recordid]))->out(false),
                'hascover' => isset($covers[$row->recordid]),
                'coverurl' => $covers[$row->recordid] ?? '',
                'colour' => (int) $row->recordid % 6,
                'audio' => digital::file_url($row),
                'by' => $by,
                'playlabel' => $str('littleplay', (object) ['title' => $title, 'who' => $by]),
            ];
        }, $items));
    }

    /**
     * The story for today on a little one's home: one of the voices read for them, the next one each day.
     *
     * @param \stdClass $library
     * @param array $viewer
     * @param int $now
     * @return array|null As shelf() gives each one.
     */
    public static function today(\stdClass $library, array $viewer, int $now): ?array {
        $shelf = self::shelf($library, $viewer);
        return $shelf ? $shelf[intdiv($now, DAYSECS) % count($shelf)] : null;
    }

    /**
     * The address a family hears a voice at, sealed: without an account, only that voice.
     *
     * @param int $digitalid
     * @return string
     */
    public static function url(int $digitalid): string {
        return (new \moodle_url('/local/oksigeniaayvurenda/hear.php', ['i' => $digitalid,
            's' => mail::seal('hear', $digitalid, 'h')]))->out(false);
    }

    /**
     * The voice a sealed address opens, if it is still there to hear: published, read for the little ones and meant for
     * the family.
     *
     * @param int $digitalid
     * @param string $seal
     * @return array|null voice, record, library (the centre's main one), reader.
     */
    public static function heard(int $digitalid, string $seal): ?array {
        global $DB;
        if (!hash_equals(mail::seal('hear', $digitalid, 'h'), $seal)) {
            return null;
        }
        $voice = $DB->get_record(self::T . 'digital', ['id' => $digitalid, 'kind' => 'voice', 'status' => 'published',
            'forlittle' => 1, 'tellfamily' => 1]);
        $record = $voice ? $DB->get_record(self::T . 'record', ['id' => $voice->recordid]) : null;
        $reader = $voice && $voice->readerid ? $DB->get_record(self::T . 'reader', ['id' => $voice->readerid]) : null;
        $library = $voice ? mail::main_library((int) $voice->entityid) : null;
        if (!$record || !$reader || !$library || !digital::file($voice)) {
            return null;
        }
        return ['voice' => $voice, 'record' => $record, 'library' => $library, 'reader' => $reader];
    }

    /**
     * The voices whose family is still to hear of them: published lately, read for the little ones, meant for the family.
     *
     * @param int $now
     * @param int[]|null $centres Only these centres.
     * @return \stdClass[]
     */
    public static function due(int $now, ?array $centres = null): array {
        global $DB;
        $rows = $DB->get_records_select(
            self::T . 'digital',
            "kind = 'voice' AND status = 'published' AND forlittle = 1 AND tellfamily = 1 AND timetold IS NULL
                AND readerid IS NOT NULL AND timemodified >= :since",
            ['since' => $now - self::NEWS * DAYSECS],
            'id'
        );
        return array_values(array_filter($rows, fn($row) => $centres === null
            || in_array((int) $row->entityid, $centres, true)));
    }

    /**
     * Sends each family the way to hear what their reader read for the little ones, once, in their centres' morning.
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
        foreach (self::due($now, $centres) as $voice) {
            $entityid = (int) $voice->entityid;
            if (!self::on($entityid) || practice::is($entityid) || access::closed($entityid)) {
                continue;
            }
            // Once: a family that gets nothing now (none left, a message that fails) is not asked again every morning.
            $DB->set_field(self::T . 'digital', 'timetold', $now, ['id' => $voice->id]);
            try {
                $told += self::tell($voice);
            } catch (\Throwable $e) {
                debugging('Ayvurenda: the family of voice ' . $voice->id . ' was not told: ' . $e->getMessage());
            }
        }
        return $told;
    }

    /**
     * Tells a reader's family what they read for the little ones: one message to each adult.
     *
     * @param \stdClass $voice
     * @return int Adults told.
     */
    public static function tell(\stdClass $voice): int {
        global $DB;
        $reader = $DB->get_record(self::T . 'reader', ['id' => $voice->readerid, 'status' => 'active']);
        $child = $reader && $reader->userid ? \core_user::get_user((int) $reader->userid) : null;
        $record = $DB->get_record(self::T . 'record', ['id' => $voice->recordid]);
        $library = mail::main_library((int) $voice->entityid);
        if (!$child || !$record || !$library) {
            return 0;
        }
        $entityid = (int) $voice->entityid;
        $url = self::url((int) $voice->id);
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
            $a = (object) ['child' => $child->firstname, 'title' => format_string($record->title, true, ['escape' => false]),
                'library' => format_string($library->name, true, ['escape' => false])];
            $str = fn(string $key) => get_string($key, 'local_oksigeniaayvurenda', $a);
            $subject = $str('forlittlesubject');
            $hello = get_string('mailhello', 'local_oksigeniaayvurenda', $adult->firstname);
            // Jujol's: the book with who reads it, the button, and why it comes.
            $design = [
                'library' => $library,
                'entityid' => $entityid,
                'hello' => $hello,
                'heading' => $subject,
                'lead' => [$str('forlittlelead'), $str('forlittleafter')],
                'book' => mail::book((int) $record->id, $a->title),
                'place' => ['label' => $str('forlittlereadby'), 'value' => (string) $voice->signature !== ''
                    ? format_string($voice->signature, true, ['escape' => false]) : $a->child],
                'button' => ['url' => $url, 'text' => $str('forlittlelisten')],
                'why' => $str('forlittlewhy'),
            ];
            $text = $hello . "\n\n" . $str('forlittlelead') . "\n\n" . $str('forlittleafter') . "\n" . $url;
            if ($user) {
                $sent = notices::send('forlittle', $user, $subject, $text, $url, $str('forlittlelisten'), $a->library, $design);
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
