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

namespace local_oksigeniaayvurenda;

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\bank;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\practice;
use local_oksigeniaayvurenda\local\window;

/**
 * What other plugins of the site may ask the library, without reading its tables. These signatures stay stable; what
 * they give only grows.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class api {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var array[] The six drawn covers of the library, as background and ink, for whoever has no picture. */
    private const DRAWN = [
        ['background' => '#17574c', 'ink' => '#fbf6ec'],
        ['background' => '#e2654c', 'ink' => '#141c1b'],
        ['background' => '#f0b43c', 'ink' => '#1e2b2a'],
        ['background' => '#2c7a6a', 'ink' => '#ffffff'],
        ['background' => '#0f3a33', 'ink' => '#f0b43c'],
        ['background' => '#f2eadb', 'ink' => '#17574c'],
    ];

    /**
     * The books that have just come to the libraries, newest first: for the screens of a centre, a newsletter, a block.
     *
     * Only reading libraries (never a bank of textbooks nor the practice library of the guided tours), never a withdrawn
     * or lost copy nor a device, and never a centre that is still closed to its readers. A book with several new copies
     * comes once, with the library of the newest.
     *
     * Each book: recordid; title and subtitle (plain text, to be escaped when shown); authors (up to two, as people say
     * them: «Ana Montes»); cover (the stored_file of its picture, or null); colour (background and ink of its drawn cover,
     * as hexadecimal colours, for when there is no picture); arrived (when its newest copy came); library and libraryid
     * (its name, plain text); entityid; url (its record: the public one if anyone may see the catalogue, else the one
     * that asks to sign in).
     *
     * @param int $since Only what came from this time on.
     * @param int $limit How many, from 1 to 50.
     * @param int|null $entityid Only the libraries of this centre; null, those of every centre on the site.
     * @return array[]
     */
    public static function new_books(int $since, int $limit = 12, ?int $entityid = null): array {
        global $DB;
        $open = [];
        $entities = $DB->get_fieldset_select(
            self::T . 'entity',
            'id',
            $entityid ? 'id = :id' : '1 = 1',
            ['id' => $entityid]
        );
        foreach ($entities as $id) {
            // Never the practice library of the guided tours, nor a centre still closed to its readers.
            if (!access::closed((int) $id) && !practice::is((int) $id)) {
                $open[] = (int) $id;
            }
        }
        if (!$open) {
            return [];
        }
        [$in, $params] = $DB->get_in_or_equal($open, SQL_PARAMS_NAMED);
        $params += ['since' => $since, 'kind' => bank::KIND, 'device' => 'device'];
        $from = "FROM {" . self::T . "item} i
                 JOIN {" . self::T . "library} b ON b.id = i.libraryid
                 JOIN {" . self::T . "record} r ON r.id = i.recordid
                WHERE b.entityid $in AND b.kind <> :kind AND i.timecreated >= :since AND i.status <> 'withdrawn'
                      AND i.status <> 'lost' AND r.doctype <> :device";
        $records = $DB->get_records_sql(
            "SELECT r.id, r.entityid, r.title, r.subtitle, MAX(i.timecreated) AS came
               $from
           GROUP BY r.id, r.entityid, r.title, r.subtitle
           ORDER BY MAX(i.timecreated) DESC, r.id DESC",
            $params,
            0,
            max(1, min(50, $limit))
        );
        if (!$records) {
            return [];
        }
        $ids = array_keys($records);
        // The library of each book's newest copy.
        [$rin, $rparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'r');
        $where = [];
        foreach (
            $DB->get_records_sql(
                "SELECT i.id, i.recordid, i.timecreated, b.id AS libraryid, b.slug, b.name, b.entityid
                   $from AND i.recordid $rin
               ORDER BY i.timecreated DESC, i.id DESC",
                $params + $rparams
            ) as $item
        ) {
            $where[$item->recordid] ??= $item;
        }
        $authors = [];
        $classes = [];
        foreach (
            $DB->get_records_sql(
                "SELECT ra.id, ra.recordid, a.kind, a.code, a.label
                   FROM {" . self::T . "recauth} ra
                   JOIN {" . self::T . "authority} a ON a.id = ra.authorityid
                  WHERE ra.recordid $rin AND (a.kind = 'author' OR a.kind = 'class')
               ORDER BY ra.sortorder, ra.id",
                $rparams
            ) as $link
        ) {
            if ($link->kind === 'author' && count($authors[$link->recordid] ?? []) < 2) {
                $authors[$link->recordid][] = catalogue::display_name((string) $link->label);
            } else if ($link->kind === 'class' && !isset($classes[$link->recordid])) {
                $classes[$link->recordid] = catalogue::top_class((string) $link->code);
            }
        }
        $fs = get_file_storage();
        $context = \context_system::instance();
        $public = [];
        $books = [];
        foreach ($records as $record) {
            $library = $where[$record->id];
            $public[$library->libraryid] ??= window::catalogue_open($DB->get_record(
                self::T . 'library',
                ['id' => $library->libraryid]
            ) ?: null);
            $cover = $fs->get_file($context->id, 'local_oksigeniaayvurenda', 'cover', (int) $record->id, '/', 'cover.jpg');
            $class = $classes[$record->id] ?? null;
            $url = $public[$library->libraryid]
                ? new \moodle_url('/local/oksigeniaayvurenda/public.php', ['library' => $library->slug, 'record' => $record->id])
                : new \moodle_url('/local/oksigeniaayvurenda/record.php', ['library' => $library->slug, 'id' => $record->id]);
            $books[] = [
                'recordid' => (int) $record->id,
                'title' => (string) $record->title,
                'subtitle' => (string) ($record->subtitle ?? ''),
                'authors' => $authors[$record->id] ?? [],
                'cover' => $cover ?: null,
                'colour' => self::DRAWN[($class !== null ? (int) $class : (int) $record->id) % count(self::DRAWN)],
                'arrived' => (int) $record->came,
                'library' => (string) $library->name,
                'libraryid' => (int) $library->libraryid,
                'entityid' => (int) $library->entityid,
                'url' => $url->out(false),
            ];
        }
        return $books;
    }

    /**
     * The libraries of the site that another plugin may offer to people outside (admissions linking a family to the
     * tales to read at home): never a practice library nor a textbook bank. Whether its door is open says whether it can
     * be linked; a closed centre's door is never open.
     *
     * @return array[] Each with slug, name, entityid, open (its door is open to anyone) and tales (it offers tales to read
     *     at home).
     */
    public static function libraries(): array {
        $out = [];
        foreach (catalogue::libraries() as $library) {
            if ($library->kind === bank::KIND || practice::is((int) $library->entityid)) {
                continue;
            }
            $open = (bool) window::settings($library)['open'];
            $out[] = [
                'slug' => (string) $library->slug,
                'name' => format_string($library->name, true, ['escape' => false]),
                'entityid' => (int) $library->entityid,
                'open' => $open,
                'tales' => $open && (bool) window::tale_ages($library),
            ];
        }
        return $out;
    }

    /**
     * The address of a library's tales to read at home, for a child of an age, to link to from outside the library.
     *
     * @param string $slug The library, as libraries() gives it.
     * @param int|null $age The child's age in years; null to let the family choose.
     * @return \moodle_url|null Null when there is no such library or its door is not open.
     */
    public static function tales_url(string $slug, ?int $age = null): ?\moodle_url {
        $library = catalogue::library($slug);
        if (!$library || $library->kind === bank::KIND || !window::settings($library)['open']) {
            return null;
        }
        return new \moodle_url('/local/oksigeniaayvurenda/public.php', ['library' => $library->slug, 'tales' => 1]
            + ($age !== null && $age >= 0 ? ['age' => $age] : []));
    }

    /**
     * What is coming at the libraries: the activities of their agenda from a moment on, the soonest first; for the
     * screens of a centre, a newsletter, a block. Never a practice library nor a centre still closed to its readers.
     *
     * Each activity: id; title, place, audience and text (plain text, to be escaped when shown; '' when not said);
     * timestart and timeend (null if it does not say until when); when (when it is, in words, in the centre's zone and the
     * current language: «sábado, 14 de noviembre de 2026, 11:00»); seats (0 when not counted); library and libraryid;
     * entityid; url (the agenda of its library, which asks to sign in).
     *
     * @param int $from Only what has not ended by this time.
     * @param int $until Only what starts up to this time.
     * @param int|null $entityid Only the libraries of this centre; null, those of every centre on the site.
     * @param int $limit How many, from 1 to 50.
     * @return array[]
     */
    public static function activities(int $from, int $until, ?int $entityid = null, int $limit = 20): array {
        global $DB;
        $limit = max(1, min(50, $limit));
        $entities = $DB->get_fieldset_select(self::T . 'entity', 'id', $entityid ? 'id = :id' : '1 = 1', ['id' => $entityid]);
        $found = [];
        foreach ($entities as $id) {
            if (access::closed((int) $id) || practice::is((int) $id)) {
                continue;
            }
            foreach (local\agenda::between((int) $id, $from, $until) as $activity) {
                $found[] = $activity;
            }
        }
        usort($found, fn($a, $b) => [(int) $a->timestart, (int) $a->id] <=> [(int) $b->timestart, (int) $b->id]);
        $activities = [];
        foreach (array_slice($found, 0, $limit) as $activity) {
            $centre = (int) $DB->get_field(self::T . 'library', 'entityid', ['id' => $activity->libraryid]);
            $activities[] = [
                'id' => (int) $activity->id,
                'title' => (string) $activity->title,
                'place' => (string) $activity->place,
                'audience' => (string) $activity->audience,
                'text' => (string) $activity->text,
                'timestart' => (int) $activity->timestart,
                'timeend' => $activity->timeend !== null ? (int) $activity->timeend : null,
                'when' => local\agenda::when($activity, $centre),
                'seats' => (int) $activity->seats,
                'library' => (string) $activity->libraryname,
                'libraryid' => (int) $activity->libraryid,
                'entityid' => $centre,
                'url' => (new \moodle_url('/local/oksigeniaayvurenda/agenda.php', ['library' => $activity->libraryslug]))
                    ->out(false),
            ];
        }
        return $activities;
    }
}
