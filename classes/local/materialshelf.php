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
 * The material a library lends (laptops, tablets, calculators, cameras, games…) as tiles on a shelf.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * Material was a form: name, units in a number field, place and kind in lists and «Add». Now each thing is a tile with
 * its line drawing standing on a plank, its name, where it lives and its units with − and +, each kept at once; below,
 * how many are lent. To add another, a dashed tile per kind opens with its name already written.
 */
final class materialshelf {
    /** @var string[] The kinds, each with its drawing (Jujol's line icons, 13 November 2026). */
    public const KINDS = ['portatil', 'tableta', 'calculadora', 'camara', 'juego', 'auriculares', 'otra'];

    /** @var string[] The drawing of each kind: the inside of a 24 × 24 SVG drawn with a line. */
    private const DRAWINGS = [
        'portatil' => '<rect x="4" y="5" width="16" height="11" rx="1.2"/><path d="M2 19h20l-1.6-3H3.6z"/><path d="M10.5 17.5h3"/>',
        'tableta' => '<rect x="5" y="2.5" width="14" height="19" rx="2"/><path d="M11 18.5h2"/>',
        'calculadora' => '<rect x="5" y="2.5" width="14" height="19" rx="1.6"/><rect x="7.5" y="5" width="9" height="4" rx=".6"/>'
            . '<path d="M8.5 12h.01M12 12h.01M15.5 12h.01M8.5 15h.01M12 15h.01M15.5 15h.01M8.5 18h.01M12 18h.01M15.5 18h.01"/>',
        'camara' => '<path d="M3 8.5A1.5 1.5 0 0 1 4.5 7h3l1.5-2.5h6L16.5 7h3A1.5 1.5 0 0 1 21 8.5v9a1.5 1.5 0 0 1-1.5 '
            . '1.5h-15A1.5 1.5 0 0 1 3 17.5z"/><circle cx="12" cy="13" r="3.6"/>',
        'juego' => '<rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8.5 8.5h.01M15.5 8.5h.01M12 12h.01M8.5 15.5h.01'
            . 'M15.5 15.5h.01"/>',
        'auriculares' => '<path d="M4 15v-3a8 8 0 0 1 16 0v3"/><rect x="3" y="14" width="4" height="6" rx="1.4"/>'
            . '<rect x="17" y="14" width="4" height="6" rx="1.4"/>',
        'otra' => '<path d="M3.5 8 12 4l8.5 4v8.5L12 20.5l-8.5-4z"/><path d="M3.5 8 12 12l8.5-4M12 12v8.5"/>',
    ];

    /** @var string[] What a name says of its kind, for the material that came before kinds, in a few languages. */
    private const GUESS = [
        'portatil' => '/port[aá]til|laptop|chromebook|notebook|ordenador|computer|computador|ordinateur/iu',
        'tableta' => '/tablet|tableta|ipad|tablette/iu',
        'calculadora' => '/calculadora|calculator|calculatrice/iu',
        'camara' => '/c[aá]mara|camera|appareil photo/iu',
        'juego' => '/juego|game|jogo|jeu|puzzle|rompecabezas|ajedrez|chess/iu',
        'auriculares' => '/auricular|cascos|headphone|headset|fones|casque/iu',
    ];

    /**
     * The kind of a thing: the one it was given, or what its name says, or another thing.
     *
     * @param string $title
     * @param array $extra The record's extra (with kind, brand, model).
     * @return string One of KINDS.
     */
    public static function kind(string $title, array $extra): string {
        $kind = (string) ($extra['kind'] ?? '');
        if (in_array($kind, self::KINDS, true)) {
            return $kind;
        }
        foreach (self::GUESS as $guess => $pattern) {
            if (preg_match($pattern, $title)) {
                return $guess;
            }
        }
        return 'otra';
    }

    /**
     * The line drawing of a kind, as an SVG to put in the page.
     *
     * @param string $kind
     * @return string
     */
    public static function drawing(string $kind): string {
        return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.6" '
            . 'stroke-linecap="round" stroke-linejoin="round">' . (self::DRAWINGS[$kind] ?? self::DRAWINGS['otra']) . '</svg>';
    }

    /**
     * The material of a library: each thing with its kind, where its units live, how many there are, how many are lent
     * and how many are in their place (which «−» can take away).
     *
     * @param \stdClass $library
     * @return array[] id, title, kind, extra, units, lent, inplace, where (location id), codes.
     */
    public static function things(\stdClass $library): array {
        global $DB;
        $records = $DB->get_records(
            'local_oksigeniaayvurenda_record',
            ['entityid' => $library->entityid, 'doctype' => 'device'],
            'title',
            'id, title, extra'
        );
        if (!$records) {
            return [];
        }
        [$in, $params] = $DB->get_in_or_equal(array_keys($records), SQL_PARAMS_NAMED);
        $items = $DB->get_records_select(
            'local_oksigeniaayvurenda_item',
            "recordid $in AND libraryid = :libraryid AND status <> 'withdrawn' AND status <> 'lost'",
            $params + ['libraryid' => $library->id],
            'id',
            'id, recordid, barcode, status, locationid'
        );
        $by = [];
        foreach ($items as $item) {
            $by[(int) $item->recordid][] = $item;
        }
        $things = [];
        foreach ($records as $record) {
            $mine = $by[(int) $record->id] ?? [];
            $extra = json_decode((string) $record->extra, true) ?: [];
            $places = array_count_values(array_map(fn($i) => (int) $i->locationid, $mine));
            arsort($places);
            $things[] = [
                'id' => (int) $record->id,
                'title' => (string) $record->title,
                'kind' => self::kind((string) $record->title, $extra),
                'extra' => $extra,
                'units' => count($mine),
                'lent' => count(array_filter($mine, fn($i) => $i->status === 'onloan')),
                'inplace' => count(array_filter($mine, fn($i) => $i->status === 'available')),
                'where' => (int) (array_key_first($places) ?? 0),
                'codes' => array_map(fn($i) => (string) $i->barcode, $mine),
            ];
        }
        return $things;
    }

    /**
     * One unit less: the last one added that is in its place, taken out of the collection with a record of it.
     *
     * @param \stdClass $library
     * @param int $recordid
     * @param int $userid
     * @return bool Whether one was taken (none is when all are lent).
     */
    public static function less(\stdClass $library, int $recordid, int $userid): bool {
        global $DB;
        $last = $DB->get_records_select(
            'local_oksigeniaayvurenda_item',
            "recordid = :recordid AND libraryid = :libraryid AND status = 'available'",
            ['recordid' => $recordid, 'libraryid' => $library->id],
            'id DESC',
            'id',
            0,
            1
        );
        if (!$last) {
            return false;
        }
        return weeding::withdraw(
            $library,
            [(int) reset($last)->id],
            get_string('materiallessreason', 'local_oksigeniaayvurenda'),
            [],
            $userid
        ) > 0;
    }
}
