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
 * From Abies to Ayvurenda.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\core\abies;

use local_oksigeniaayvurenda\core\isbn;

/**
 * Turns rows of an Abies export into Ayvurenda's terms: document types, languages, author roles, copy and reader types,
 * statuses and the fields of a record.
 *
 * Part of the core: plain PHP, with no call to Moodle.
 */
final class mapper {
    /** @var string[] Record element of Abies and Ayvurenda document type. */
    private const DOCTYPES = ['Libro' => 'book', 'Audio' => 'audio', 'Video' => 'video', 'RecursoElectronico' => 'electronic',
        'PublicacionPeriodica' => 'periodical', 'OtroMaterial' => 'other', 'MaterialCartografico' => 'map', 'Juego' => 'game'];

    /** @var string[] Authority table of Abies and Ayvurenda kind. */
    public const AUTHORITY_KINDS = ['Autor' => 'author', 'CDU' => 'class', 'Descriptor' => 'subject',
        'Editorial' => 'publisher', 'Ubicacion' => 'location', 'Aplicacion' => 'audience'];

    /** @var string[] Language as written in catalogues, normalised, and its MARC code (ISO 639-2/B). */
    private const LANGUAGES = ['e' => 'spa', 'es' => 'spa', 'esp' => 'spa', 'spa' => 'spa', 'espanol' => 'spa',
        'castellano' => 'spa', 'en' => 'eng', 'eng' => 'eng', 'ingles' => 'eng', 'english' => 'eng', 'fr' => 'fre',
        'fre' => 'fre', 'fra' => 'fre', 'frances' => 'fre', 'de' => 'ger', 'ger' => 'ger', 'deu' => 'ger', 'aleman' => 'ger',
        'it' => 'ita', 'ita' => 'ita', 'italiano' => 'ita', 'pt' => 'por', 'por' => 'por', 'portugues' => 'por',
        'ca' => 'cat', 'cat' => 'cat', 'catalan' => 'cat', 'gl' => 'glg', 'glg' => 'glg', 'gallego' => 'glg',
        'eu' => 'baq', 'baq' => 'baq', 'eus' => 'baq', 'euskera' => 'baq', 'gn' => 'grn', 'grn' => 'grn', 'guarani' => 'grn', 'val' => 'cat', 'valenciano' => 'cat',
        'la' => 'lat', 'lat' => 'lat', 'latin' => 'lat', 'sk' => 'slo', 'slo' => 'slo', 'slk' => 'slo', 'zh' => 'chi',
        'chi' => 'chi', 'ar' => 'ara', 'ara' => 'ara', 'ro' => 'rum', 'rum' => 'rum', 'ron' => 'rum'];

    /** @var string[] Author function as Abies abbreviates it, and Ayvurenda role. */
    private const ROLES = ['aut' => 'author', 'il' => 'illustrator', 'trad' => 'translator', 'ed lit' => 'editor',
        'ed mus' => 'editor', 'red' => 'editor', 'adapt' => 'adapter', 'pr' => 'prefacer', 'int y not' => 'prefacer',
        'col' => 'contributor', 'otr' => 'contributor', 'otra' => 'contributor', 'dir' => 'director', 'coord' => 'editor',
        'comp' => 'compiler', 'rec' => 'compiler', 'sel' => 'compiler', 'fot' => 'photographer', 'int' => 'performer',
        'duo' => 'performer', 'arr' => 'arranger', 'anot' => 'annotator', 'com' => 'commentator'];

    /** @var string[] Names of copy types in Abies and their Ayvurenda code. */
    private const ITEM_TYPES = ['normal' => 'normal', 'no prestable' => 'reference', 'restringido' => 'restricted',
        'video' => 'av', 'audiovisual' => 'av'];

    /** @var string[] Names of reader types in Abies and their Ayvurenda code. */
    private const READER_TYPES = ['alumno' => 'student', 'alumnado' => 'student', 'profesor' => 'teacher',
        'profesorado' => 'teacher', 'no docente' => 'staff', 'personal' => 'staff', 'aula' => 'classroom',
        'familia' => 'family'];

    /**
     * The document type of a record element.
     *
     * @param string $element
     * @return string
     */
    public static function doctype(string $element): string {
        return self::DOCTYPES[$element] ?? 'other';
    }

    /**
     * The MARC language code of a language as written, or null when it cannot be told.
     *
     * @param string|null $raw
     * @return string|null
     */
    public static function language(?string $raw): ?string {
        $key = self::key($raw ?? '');
        if (isset(self::LANGUAGES[$key])) {
            return self::LANGUAGES[$key];
        }
        // A code of three small letters is taken as the MARC code it looks like (slv, egy, ang…).
        $raw = trim($raw ?? '');
        return preg_match('/^[a-z]{3}$/', $raw) ? $raw : null;
    }

    /**
     * The role of an author from the Abies function: aut., il., trad.…
     *
     * @param string|null $raw
     * @return string
     */
    public static function role(?string $raw): string {
        return self::ROLES[self::key($raw ?? '')] ?? 'contributor';
    }

    /**
     * The Ayvurenda code of a copy type, from its name.
     *
     * @param string $name
     * @return string
     */
    public static function item_type(string $name): string {
        return self::ITEM_TYPES[self::key($name)] ?? self::slug($name);
    }

    /**
     * The Ayvurenda code of a reader type, from its name.
     *
     * @param string $name
     * @return string
     */
    public static function reader_type(string $name): string {
        return self::READER_TYPES[self::key($name)] ?? self::slug($name);
    }

    /**
     * The status of a copy: 0 available, 1 on loan; anything else is not known and comes in as available.
     *
     * @param string|null $estado
     * @return string
     */
    public static function item_status(?string $estado): string {
        return $estado === '1' ? 'onloan' : 'available';
    }

    /**
     * Whether Abies gives a copy status Ayvurenda knows.
     *
     * @param string|null $estado
     * @return bool
     */
    public static function known_status(?string $estado): bool {
        return $estado === '0' || $estado === '1';
    }

    /**
     * The record fields of a document row, and the texts that become authorities.
     *
     * @param array $doc A row of export_reader::documents().
     * @return array{record: array, authorities: array, extra: array, rawlanguage: ?string}
     */
    public static function record(array $doc): array {
        $f = $doc['fields'];
        $first = fn(string $name): ?string => isset($f[$name][0]) ? self::clean($f[$name][0]) : null;
        $isbn = null;
        foreach (array_merge($f['ISBN'] ?? [], $f['ISBN2'] ?? []) as $raw) {
            $isbn = isbn::normalise($raw);
            if ($isbn !== null) {
                break;
            }
        }
        $year = $first('FechaPublicacion') ?? $first('FechaEdicion');
        if ($year !== null && preg_match('/\d{4}/', $year, $matches)) {
            $year = $matches[0];
        }
        $extra = [];
        foreach (
            ['DepositoLegal' => 'legaldeposit', 'NumeroSerie' => 'seriesnumber', 'Dimensiones' => 'dimensions',
                'CaracteristicasFisicas' => 'physical', 'MaterialComplementario' => 'accompanying', 'Pais' => 'country',
                'Unidades' => 'units', 'OtraInformacion' => 'otherinfo'] as $field => $key
        ) {
            if ($first($field) !== null) {
                $extra[$key] = $first($field);
            }
        }
        if (isset($f['ISBN']) || isset($f['ISBN2'])) {
            $extra['isbnraw'] = array_values(array_unique(array_merge($f['ISBN'] ?? [], $f['ISBN2'] ?? [])));
        }
        $unique = fn(string $name): array => array_values(array_unique(array_map([self::class, 'clean'], $f[$name] ?? [])));
        return [
            'record' => [
                'doctype' => self::doctype($doc['name']),
                'title' => $first('Titulo') ?? '',
                'subtitle' => $first('RestoTitulo'),
                'responsibility' => $first('RestoPortada'),
                'isbn' => $isbn,
                'edition' => $first('Edicion'),
                'pubplace' => $first('LugarPublicacion'),
                'pubyear' => $year,
                'lang' => self::language($first('Lengua')),
                'extent' => $first('Extension'),
                'summary' => $first('Sinopsis'),
                'notes' => $first('Notas'),
            ],
            'authorities' => [
                'author' => $unique('Autor'),
                'publisher' => $unique('Editorial'),
                'class' => $unique('CDU'),
                'subject' => array_values(array_unique(array_merge($unique('OtroDescriptor'), $unique('Descriptor')))),
                'collection' => $unique('Coleccion'),
                'audience' => $unique('AplicacionPedagogica'),
            ],
            'extra' => $extra,
            'rawlanguage' => $first('Lengua'),
        ];
    }

    /**
     * Text with single spaces and no blanks around.
     *
     * @param string $text
     * @return string
     */
    public static function clean(string $text): string {
        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * A lookup key: lower case, no accents, no dots, single spaces.
     *
     * @param string $text
     * @return string
     */
    private static function key(string $text): string {
        return trim(preg_replace('/\s+/', ' ', str_replace('.', ' ', \local_oksigeniaayvurenda\core\text::normalise($text))));
    }

    /**
     * A code made from a name: lower case letters, digits and hyphens.
     *
     * @param string $name
     * @return string
     */
    private static function slug(string $name): string {
        $slug = trim(preg_replace('/[^a-z0-9]+/', '-', \local_oksigeniaayvurenda\core\text::normalise($name)), '-');
        return $slug === '' ? 'other' : substr($slug, 0, 30);
    }
}
