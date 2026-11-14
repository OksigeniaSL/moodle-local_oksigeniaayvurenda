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
 * The reader's booklet, for the family: what a reader lived in the library, to keep and print. Two editions of it.
 *
 * - The year's («La flor de junio» in the review of 12 November): when the school year's classes end, that year's album
 *   as a booklet, the flower (or the sky) as it was left, the books in their order, the collector cards won that year, the
 *   drawings and the voices, the class's island and a line of the library.
 * - The whole life's («Tu vida de lector aquí»): when a reader leaves the centre, every school year they had here.
 *
 * The family gets a sealed link the morning after (never at night), if the centre turned it on (off at first); the
 * staff print it from the reader's page and the tutor for the whole class from «My class», for the families who read no
 * e-mail. A reader leaves when their group is one the centre says leaves that year, or when its source drops them
 * during the year.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class booklet {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var string[] The editions: the school year's and the whole life's. */
    public const KINDS = ['year', 'life'];

    /** @var int Days after the end of the classes in which the year's booklets are still made, if the cron missed it. */
    private const LATE = 7;

    /** @var int The longest line of the library, in characters: what fits its slip (Jujol). */
    public const LINE = 140;

    /** @var int Days a reader of twelve or over has to say their family may get it; then it is theirs alone. */
    private const YES = 30;

    /** @var int Days after the end of the classes the card tells a reader of twelve or over that their booklet is ready. */
    public const ONCARD = 60;

    /**
     * A centre's settings.
     *
     * @param int $entityid
     * @return array
     */
    private static function settings(int $entityid): array {
        global $DB;
        return json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
    }

    /**
     * Changes some of a centre's settings.
     *
     * @param int $entityid
     * @param array $changes
     */
    private static function keep(int $entityid, array $changes): void {
        global $DB;
        $settings = array_merge(self::settings($entityid), $changes);
        $DB->set_field(self::T . 'entity', 'settings', json_encode($settings), ['id' => $entityid]);
    }

    /**
     * Whether a centre sends the booklet to the families (off at first: it is the centre's way to speak to them).
     *
     * @param int $entityid
     * @return bool
     */
    public static function on(int $entityid): bool {
        return !empty(self::settings($entityid)['booklet']);
    }

    /**
     * Saves whether a centre sends it.
     *
     * @param int $entityid
     * @param bool $on
     */
    public static function save(int $entityid, bool $on): void {
        self::keep($entityid, ['booklet' => $on]);
    }

    /**
     * The library's line in the booklets of a school year: one for the whole centre.
     *
     * @param int $entityid
     * @param int $yearid
     * @return string
     */
    public static function line(int $entityid, int $yearid): string {
        return (string) (self::settings($entityid)['bookletlines'][$yearid] ?? '');
    }

    /**
     * Saves the library's line of a school year.
     *
     * @param int $entityid
     * @param int $yearid
     * @param string $line
     */
    public static function save_line(int $entityid, int $yearid, string $line): void {
        $lines = self::settings($entityid)['bookletlines'] ?? [];
        $line = \core_text::substr(trim(preg_replace('/\s+/u', ' ', $line)), 0, self::LINE);
        if ($line === '') {
            unset($lines[$yearid]);
        } else {
            $lines[$yearid] = $line;
        }
        self::keep($entityid, ['bookletlines' => $lines]);
    }

    /**
     * The library's farewell in the whole life's booklets: one for the whole centre; without it, the year's line.
     *
     * @param int $entityid
     * @return string
     */
    public static function farewell(int $entityid): string {
        return (string) (self::settings($entityid)['bookletfarewell'] ?? '');
    }

    /**
     * Saves the library's farewell.
     *
     * @param int $entityid
     * @param string $line
     */
    public static function save_farewell(int $entityid, string $line): void {
        self::keep($entityid, ['bookletfarewell' => \core_text::substr(trim(preg_replace('/\s+/u', ' ', $line)), 0, self::LINE)]);
    }

    /**
     * Whether a booklet is its reader's to give: from twelve (the sky), their family gets it only if they say so.
     *
     * @param \stdClass $reader
     * @param int $yearid
     * @return bool
     */
    public static function theirs(\stdClass $reader, int $yearid): bool {
        global $DB;
        $year = $DB->get_record(self::T . 'year', ['id' => $yearid]);
        return $year && cards::look_of($reader, max((int) $year->timestart, self::classes_end($year) - 1)) === 'sky';
    }

    /**
     * A reader of twelve or over says whether their family gets their booklet.
     *
     * @param int $id
     * @param bool $on
     */
    public static function wish(int $id, bool $on): void {
        global $DB;
        $DB->set_field(self::T . 'booklet', 'tellfamily', $on ? 1 : 0, ['id' => $id]);
    }

    /**
     * The booklet a reader's card tells of: the last one made for them, for some weeks after it was due.
     *
     * @param \stdClass $reader
     * @param int $now
     * @return \stdClass|null
     */
    public static function on_card(\stdClass $reader, int $now): ?\stdClass {
        global $DB;
        $rows = $DB->get_records_select(
            self::T . 'booklet',
            'readerid = :readerid AND timedue <= :now AND timedue > :since',
            ['readerid' => $reader->id, 'now' => $now, 'since' => $now - self::ONCARD * DAYSECS],
            'timedue DESC, id DESC',
            '*',
            0,
            1
        );
        return $rows ? reset($rows) : null;
    }

    /**
     * The groups that leave the centre when a school year ends: their readers get the whole life's booklet.
     *
     * @param int $entityid
     * @param int $yearid
     * @return string[]
     */
    public static function leaving(int $entityid, int $yearid): array {
        return array_values(array_map('strval', (array) (self::settings($entityid)['bookletleaving'][$yearid] ?? [])));
    }

    /**
     * Saves the groups that leave when a school year ends.
     *
     * @param int $entityid
     * @param int $yearid
     * @param string[] $groups
     */
    public static function save_leaving(int $entityid, int $yearid, array $groups): void {
        $leaving = self::settings($entityid)['bookletleaving'] ?? [];
        $groups = array_values(array_unique(array_filter(array_map(fn($g) => trim((string) $g), $groups), 'strlen')));
        if ($groups) {
            $leaving[$yearid] = $groups;
        } else {
            unset($leaving[$yearid]);
        }
        self::keep($entityid, ['bookletleaving' => $leaving]);
    }

    /**
     * A reader's group in a school year.
     *
     * @param int $readerid
     * @param int $yearid
     * @return string
     */
    public static function group_of(int $readerid, int $yearid): string {
        global $DB;
        return (string) $DB->get_field_select(
            self::T . 'member',
            'groupname',
            'readerid = :readerid AND yearid = :yearid',
            ['readerid' => $readerid, 'yearid' => $yearid],
            IGNORE_MULTIPLE
        );
    }

    /**
     * When a school year's classes end: the last moment of their last day.
     *
     * @param \stdClass $year
     * @return int
     */
    public static function classes_end(\stdClass $year): int {
        return (int) challenges::ends($year)[2];
    }

    /**
     * What a reader lived in one school year, for the booklet.
     *
     * @param \stdClass $reader
     * @param \stdClass $year
     * @return array year (its name), look (flower or sky), stage and levels, books (id, title, cover, premiere), cards (the
     *     corners first won that year: code and the moment), drawings (review id, book title and the drawing's address),
     *     voices (id, book title and forlittle), group, island (met, everyone, boat), planted (term and kind), line.
     */
    public static function year(\stdClass $reader, \stdClass $year): array {
        global $DB;
        $entityid = (int) $reader->entityid;
        $end = self::classes_end($year);
        $within = fn(int $time) => $time >= (int) $year->timestart && $time < max($end, (int) $year->timeend);
        $flower = self::flower_of($reader, $year);
        // The corners whose first book ever went home that year.
        $cards = array_filter(garden::corners_won($reader), $within);
        $drawings = [];
        foreach (
            $DB->get_records_sql(
                "SELECT v.id, v.timecreated, r.title
                   FROM {" . self::T . "review} v
                   JOIN {" . self::T . "record} r ON r.id = v.recordid
                  WHERE v.readerid = :readerid AND v.status = 'published'
               ORDER BY v.timecreated, v.id",
                ['readerid' => $reader->id]
            ) as $review
        ) {
            // Sealed, as the e-mails carry pictures: the family's page has no session.
            if ($within((int) $review->timecreated) && mail::file_of(reviews::AREA, (int) $review->id, 'drawing')) {
                $drawings[] = ['id' => (int) $review->id, 'title' => $review->title,
                    'url' => mail::image(reviews::AREA, (int) $review->id, 'drawing')];
            }
        }
        $voices = [];
        foreach (
            $DB->get_records_sql(
                "SELECT d.id, d.timecreated, d.forlittle, r.title
                   FROM {" . self::T . "digital} d
                   JOIN {" . self::T . "record} r ON r.id = d.recordid
                  WHERE d.readerid = :readerid AND d.kind = 'voice' AND d.status = 'published'
               ORDER BY d.timecreated, d.id",
                ['readerid' => $reader->id]
            ) as $voice
        ) {
            if ($within((int) $voice->timecreated)) {
                $voices[] = ['id' => (int) $voice->id, 'title' => $voice->title, 'forlittle' => !empty($voice->forlittle)];
            }
        }
        // The class's island as it was left that year: what the group met together, and the boat of a wish come true.
        $group = self::group_of((int) $reader->id, (int) $year->id);
        $then = max((int) $year->timestart, min($end, (int) $year->timeend) - 1);
        $planted = $group === '' ? [] : challenges::garden($entityid, $group, $then);
        $boat = false;
        foreach ($group === '' ? [] : wishes::rewards($entityid, $group, $then) as $reward) {
            $boat = $boat || ($reward['wish'] && $reward['wish']->status === 'got');
        }
        return [
            'year' => $year->name,
            'yearid' => (int) $year->id,
            'look' => $flower['look'],
            'stage' => $flower['stage'],
            'levels' => $flower['levels'],
            'books' => garden::books($reader, (int) $year->id),
            'cards' => $cards,
            'drawings' => $drawings,
            'voices' => $voices,
            'group' => $group,
            'island' => ['met' => count($planted), 'everyone' => in_array('everyone', array_column($planted, 'kind'), true),
                'boat' => $boat],
            'planted' => $planted,
            'line' => self::line($entityid, (int) $year->id),
        ];
    }

    /**
     * A reader's flower (or sky) in a school year: from their loans, or kept when the loans no longer say who took them.
     *
     * @param \stdClass $reader
     * @param \stdClass $year
     * @return array look, stage and levels.
     */
    private static function flower_of(\stdClass $reader, \stdClass $year): array {
        global $DB;
        $look = cards::look_of($reader, max((int) $year->timestart, self::classes_end($year) - 1)) === 'sky' ? 'sky' : 'flower';
        $kept = $DB->get_field(self::T . 'keepsake', 'flower', ['readerid' => $reader->id, 'yearid' => $year->id]);
        $flower = $kept ? json_decode((string) $kept, true) : null;
        if (!is_array($flower) || !isset($flower['levels'], $flower['stage'])) {
            $flower = garden::flower($reader, $year);
        }
        return ['look' => $look, 'stage' => (int) $flower['stage'], 'levels' => $flower['levels']];
    }

    /**
     * Every school year a reader had here, the oldest first, with their first book ever and what adds up.
     *
     * @param \stdClass $reader
     * @return array years (as year() gives each), first (their first book: id, title, cover and the moment) or null, books
     *     (how many in all) and cards (every corner won).
     */
    public static function life(\stdClass $reader): array {
        global $DB;
        $years = $DB->get_records_sql(
            'SELECT y.*
               FROM {' . self::T . 'year} y
              WHERE y.entityid = :entityid
                    AND (EXISTS (SELECT 1 FROM {' . self::T . 'loan} l WHERE l.yearid = y.id AND l.readerid = :r1)
                         OR EXISTS (SELECT 1 FROM {' . self::T . 'keepsake} k WHERE k.yearid = y.id AND k.readerid = :r2))
           ORDER BY y.timestart',
            ['entityid' => $reader->entityid, 'r1' => $reader->id, 'r2' => $reader->id]
        );
        $all = array_values(array_map(fn($year) => self::year($reader, $year), $years));
        $first = $DB->get_record_sql(
            "SELECT l.id, l.timeloaned, l.yearid, i.recordid, r.title
               FROM {" . self::T . "loan} l
               JOIN {" . self::T . "item} i ON i.id = l.itemid
               JOIN {" . self::T . "record} r ON r.id = i.recordid
              WHERE l.readerid = :readerid AND " . bank::reading('l.libraryid') . "
           ORDER BY l.timeloaned, l.id",
            ['readerid' => $reader->id],
            IGNORE_MULTIPLE
        );
        return [
            'years' => $all,
            'first' => $first ? ['id' => (int) $first->recordid, 'title' => $first->title,
                'cover' => covers::urls([(int) $first->recordid])[(int) $first->recordid] ?? null,
                'time' => (int) $first->timeloaned, 'yearid' => (int) $first->yearid] : null,
            'books' => array_sum(array_map(fn($y) => count($y['books']), $all)),
            'cards' => garden::corners_won($reader),
        ];
    }

    /**
     * A reader leaves the centre during the year (their source dropped them): their whole life's booklet, the next morning,
     * if the centre sends booklets and they took a book home here.
     *
     * @param \stdClass $reader
     * @param int $now
     * @return bool Whether one was made.
     */
    public static function left(\stdClass $reader, int $now): bool {
        global $DB;
        // The whole record: a source may have only some of its fields.
        $reader = $DB->get_record(self::T . 'reader', ['id' => $reader->id]);
        if (!$reader) {
            return false;
        }
        $entityid = (int) $reader->entityid;
        if (!self::on($entityid) || $reader->readertype !== 'student' || practice::is($entityid)) {
            return false;
        }
        if (!$DB->record_exists_select(self::T . 'loan', 'readerid = ? AND ' . bank::reading('libraryid'), [$reader->id])) {
            return false;
        }
        // Once: whoever left with their group at the end of the year has it already, when the new year drops them.
        if ($DB->record_exists(self::T . 'booklet', ['readerid' => $reader->id, 'kind' => 'life'])) {
            return false;
        }
        return self::make($reader, (int) years::around($entityid, $now)->id, 'life', $now);
    }

    /**
     * Makes a booklet to send, once for each reader, school year and edition.
     *
     * @param \stdClass $reader
     * @param int $yearid
     * @param string $kind year or life.
     * @param int $due When it may go.
     * @return bool Whether it was new.
     */
    private static function make(\stdClass $reader, int $yearid, string $kind, int $due): bool {
        global $DB;
        if ($DB->record_exists(self::T . 'booklet', ['readerid' => $reader->id, 'yearid' => $yearid, 'kind' => $kind])) {
            return false;
        }
        $DB->insert_record(self::T . 'booklet', ['entityid' => $reader->entityid, 'readerid' => $reader->id,
            'yearid' => $yearid, 'kind' => $kind, 'timedue' => $due, 'timesent' => null, 'timecreated' => $due]);
        return true;
    }

    /**
     * When a centre's school year has just ended its classes, a booklet for each of its young readers who took a book
     * home that year: the whole life's for those whose group leaves the centre, the year's for the rest.
     *
     * @param int $now
     * @param int[]|null $centres Only these centres.
     * @return int Booklets made.
     */
    public static function plan(int $now, ?array $centres = null): int {
        global $DB;
        $made = 0;
        foreach ($DB->get_records(self::T . 'entity', null, 'id', 'id, settings') as $entity) {
            $entityid = (int) $entity->id;
            if (($centres !== null && !in_array($entityid, $centres, true)) || !self::on($entityid) || practice::is($entityid)) {
                continue;
            }
            $year = years::around($entityid, $now - DAYSECS);
            $end = self::classes_end($year);
            if ($end > $now || $end <= $now - self::LATE * DAYSECS) {
                continue;
            }
            $leaving = array_flip(self::leaving($entityid, (int) $year->id));
            $readers = $DB->get_records_sql(
                "SELECT r.*
                   FROM {" . self::T . "reader} r
                  WHERE r.entityid = :entityid AND r.status = 'active' AND r.readertype = 'student'
                        AND EXISTS (SELECT 1 FROM {" . self::T . "loan} l
                                     WHERE l.readerid = r.id AND l.yearid = :yearid AND " . bank::reading('l.libraryid') . ")
               ORDER BY r.id",
                ['entityid' => $entityid, 'yearid' => $year->id]
            );
            foreach ($readers as $reader) {
                $kind = isset($leaving[self::group_of((int) $reader->id, (int) $year->id)]) ? 'life' : 'year';
                $made += (int) self::make($reader, (int) $year->id, $kind, $end);
            }
        }
        return $made;
    }

    /**
     * The booklets due and not sent yet.
     *
     * @param int $now
     * @param int[]|null $centres Only these centres.
     * @return \stdClass[]
     */
    public static function due(int $now, ?array $centres = null): array {
        global $DB;
        $rows = $DB->get_records_select(
            self::T . 'booklet',
            'timesent IS NULL AND timedue <= :now',
            ['now' => $now],
            'timedue, id'
        );
        return array_values(array_filter($rows, fn($row) => $centres === null || in_array((int) $row->entityid, $centres, true)));
    }

    /**
     * Makes the year's booklets that are due and tells the families, in their centres' morning.
     *
     * @param int $now
     * @param int[]|null $centres Only these centres (those whose morning it is).
     * @return int Adults told.
     */
    public static function run(int $now, ?array $centres = null): int {
        global $DB;
        self::plan($now, $centres);
        $told = 0;
        foreach (self::due($now, $centres) as $row) {
            $reader = $DB->get_record(self::T . 'reader', ['id' => $row->readerid]);
            // From twelve it is theirs: it waits for their yes, a month at most, and then it is theirs alone.
            if ($reader && empty($row->tellfamily) && self::theirs($reader, (int) $row->yearid)) {
                if ((int) $row->timedue < $now - self::YES * DAYSECS) {
                    $DB->set_field(self::T . 'booklet', 'timesent', $now, ['id' => $row->id]);
                }
                continue;
            }
            // One family whose message fails does not leave the others untold.
            try {
                $told += $reader && self::on((int) $row->entityid) && !access::closed((int) $row->entityid)
                    ? self::tell($row, $reader, $now) : 0;
            } catch (\Throwable $e) {
                debugging('Ayvurenda: the booklet ' . $row->id . ' was not told: ' . $e->getMessage());
            }
            // Told or not (no family, no address), it is not tried again: the staff print it.
            $DB->set_field(self::T . 'booklet', 'timesent', $now, ['id' => $row->id]);
        }
        return $told;
    }

    /**
     * What makes a booklet's link its own.
     *
     * @param int $id
     * @return string
     */
    public static function seal(int $id): string {
        return mail::seal('booklet', $id, 'b');
    }

    /**
     * The family's link to a booklet, without an account.
     *
     * @param int $id
     * @return string
     */
    public static function url(int $id): string {
        return (new \moodle_url('/local/oksigeniaayvurenda/booklet.php', ['b' => $id, 's' => self::seal($id)]))->out(false);
    }

    /**
     * The booklet a sealed link opens, while its reader is here: null with a wrong seal or once they were forgotten.
     *
     * @param int $id
     * @param string $seal
     * @return array|null booklet (its row) and reader.
     */
    public static function opened(int $id, string $seal): ?array {
        global $DB;
        if ($seal === '' || !hash_equals(self::seal($id), $seal)) {
            return null;
        }
        $row = $DB->get_record(self::T . 'booklet', ['id' => $id]);
        $reader = $row ? $DB->get_record(self::T . 'reader', ['id' => $row->readerid]) : null;
        if (!$reader || $reader->status === 'deleted') {
            return null;
        }
        return ['booklet' => $row, 'reader' => $reader];
    }

    /**
     * The cover of a booklet, for its e-mail and for the chats that show its link (Gaudí).
     *
     * @param \stdClass $row The booklet.
     * @param \stdClass $reader
     * @param \stdClass $library
     * @param \stdClass $year
     * @return string|null The JPEG.
     */
    public static function cover_of(\stdClass $row, \stdClass $reader, \stdClass $library, \stdClass $year): ?string {
        $name = bookletview::name($reader);
        $librarian = format_string($library->name, true, ['escape' => false]);
        if ($row->kind === 'life') {
            $life = bookletview::life($reader, $library);
            if (!$life) {
                return null;
            }
            $last = end($life['medals']);
            $groups = array_values(array_unique($life['groups']));
            $line = count($groups) > 1 ? get_string('bookletcoverlife', 'local_oksigeniaayvurenda', (object) ['from' => $groups[0],
                'to' => end($groups), 'years' => $life['span']]) : ($groups ? $groups[0] . ' · ' : '') . $life['span'];
            return bookletview::cover('vida', $last[0] === 'sky', $last, $life['medals'], $name, $line, $librarian);
        }
        $page = self::year($reader, $year);
        return bookletview::cover(
            'curso',
            $page['look'] === 'sky',
            [$page['look'], (int) $page['stage'], $page['levels']],
            [],
            $name,
            ($page['group'] !== '' ? $page['group'] . ' · ' : '') . $year->name,
            $librarian
        );
    }

    /**
     * The address of a booklet's cover, sealed as its link.
     *
     * @param int $id
     * @return string
     */
    public static function cover_url(int $id): string {
        return (new \moodle_url('/local/oksigeniaayvurenda/booklet.php', ['b' => $id, 's' => self::seal($id), 'cover' => 1]))
            ->out(false);
    }

    /**
     * Tells a reader's family of their booklet: one message to each adult of the family. Memoria signs it to the family
     * of a young reader; the library alone, from twelve.
     *
     * @param \stdClass $row The booklet.
     * @param \stdClass $reader
     * @param int $now
     * @return int Adults told.
     */
    public static function tell(\stdClass $row, \stdClass $reader, int $now): int {
        global $DB;
        $child = $reader->userid ? \core_user::get_user((int) $reader->userid) : null;
        $library = mail::main_library((int) $reader->entityid);
        $year = $DB->get_record(self::T . 'year', ['id' => $row->yearid]);
        if (!$child || !$library || !$year || !class_exists('\\local_oksigeniafamily\\api')) {
            return 0;
        }
        $entityid = (int) $reader->entityid;
        $url = self::url((int) $row->id);
        $life = $row->kind === 'life';
        $theirs = self::theirs($reader, (int) $row->yearid);
        $name = bookletview::name($reader);
        $page = self::year($reader, $year);
        $whole = $life ? self::life($reader) : null;
        $first = $whole ? $whole['first'] : null;
        $count = $whole ? count($whole['years']) : 0;
        $memory = characters::of($entityid)['memory'];
        $sign = $theirs ? ['portrait' => '', 'initial' => '', 'text' => get_string(
            'mailsignlibrary',
            'local_oksigeniaayvurenda',
            format_string($library->name, true, ['escape' => false])
        )] : ['portrait' => (string) screens::art(
            'correo/memoria',
            'png'
        ), 'initial' => '', 'text' => get_string('bookletsignmemory', 'local_oksigeniaayvurenda', $memory['name'])];
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
            $a = (object) ['child' => $name, 'library' => format_string($library->name, true, ['escape' => false]),
                'books' => count($page['books']), 'years' => $count, 'first' => $first ? $first['title'] : ''];
            $str = fn(string $key) => get_string($key, 'local_oksigeniaayvurenda', $a);
            if ($life) {
                $subject = $str('bookletlifesubject');
                $lead = $str($count > 1 && $first ? 'bookletlifelead' : 'bookletlifeleadone');
            } else {
                $subject = $str('bookletyearsubject');
                $lead = $str(!$page['books'] ? 'bookletyearleadnone' : ($page['look'] === 'sky' ? 'bookletyearleadsky'
                    : 'bookletyearlead'));
            }
            $why = $theirs ? get_string('bookletwhytheirs', 'local_oksigeniaayvurenda', $name)
                : $str($life ? 'bookletwhylife' : 'bookletwhyyear');
            $hello = get_string('mailhello', 'local_oksigeniaayvurenda', $adult->firstname);
            $design = [
                'library' => $library,
                'entityid' => $entityid,
                'young' => !$theirs,
                'preheader' => $lead,
                'hello' => $hello,
                'heading' => $subject,
                'lead' => [$lead, $str('bookletleadhow')],
                'postcard' => ['url' => self::cover_url((int) $row->id), 'alt' => $subject],
                'button' => ['url' => $url, 'text' => $str('bookletopen')],
                'sign' => $sign,
                'why' => $why,
            ];
            $text = $hello . "\n\n" . $lead . "\n\n" . $str('bookletleadhow') . "\n" . $url;
            if ($user) {
                $sent = notices::send('booklet', $user, $subject, $text, $url, $str('bookletopen'), $a->library, $design);
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
