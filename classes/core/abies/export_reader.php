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
 * Reader of Abies Web exports.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\core\abies;

/**
 * Reads the XML file that Abies Web exports (`ficheroExportacionDatos.xml`) section by section, without loading it
 * whole: each row comes as its element name, its attributes and the texts of its children (a list per child name,
 * because records repeat fields such as authors and subjects).
 *
 * Part of the core: plain PHP, with no call to Moodle.
 */
final class export_reader {
    /** @var string[] Element names of the records, in the Documentos section. */
    public const DOCUMENT_TYPES = ['Libro', 'Audio', 'Video', 'RecursoElectronico', 'PublicacionPeriodica', 'OtroMaterial',
        'MaterialCartografico', 'Juego'];

    /** @var string Path of the XML file. */
    private string $path;

    /**
     * Opens an export.
     *
     * @param string $path The XML file, already out of its zip.
     */
    public function __construct(string $path) {
        if (!is_readable($path)) {
            throw new \InvalidArgumentException('The Abies export cannot be read: ' . $path);
        }
        $this->path = $path;
        $reader = $this->open();
        $isabies = false;
        while ($reader->read()) {
            if ($reader->nodeType === \XMLReader::ELEMENT) {
                $isabies = $reader->name === 'Fichero';
                break;
            }
        }
        $reader->close();
        if (!$isabies) {
            throw new \InvalidArgumentException('This is not an Abies Web export (its root is not Fichero)');
        }
    }

    /**
     * The records (titles), of every document type.
     *
     * @return \Generator of rows with name, id and fields.
     */
    public function documents(): \Generator {
        foreach ($this->rows('Documentos', self::DOCUMENT_TYPES) as $row) {
            yield $row + ['id' => $row['attributes']['id_documento'] ?? ''];
        }
    }

    /**
     * The authority tables (Catalogo_Auxiliar_*), with the short table name: Autor, CDU, Descriptor, Editorial…
     *
     * @return \Generator of rows with table, id and fields.
     */
    public function authorities(): \Generator {
        foreach ($this->rows('Documentos') as $row) {
            if (strpos($row['name'], 'Catalogo_Auxiliar_') === 0) {
                $table = substr($row['name'], strlen('Catalogo_Auxiliar_'));
                yield $row + ['table' => $table, 'id' => $row['attributes']['id_documento'] ?? ''];
            }
        }
    }

    /**
     * The copies.
     *
     * @return \Generator of attribute arrays.
     */
    public function items(): \Generator {
        foreach ($this->rows('Ejemplares', ['Ejemplar']) as $row) {
            yield $row['attributes'];
        }
    }

    /**
     * The readers.
     *
     * @return \Generator of attribute arrays.
     */
    public function readers(): \Generator {
        foreach ($this->rows('Lectores', ['Lector']) as $row) {
            yield $row['attributes'];
        }
    }

    /**
     * The loans, out and returned.
     *
     * @return \Generator of attribute arrays.
     */
    public function loans(): \Generator {
        foreach ($this->rows('Prestamos', ['Prestamo']) as $row) {
            yield $row['attributes'];
        }
    }

    /**
     * The groups of readers (Abies calls them courses), by id.
     *
     * @return array
     */
    public function groups(): array {
        $groups = [];
        foreach ($this->rows('Cursos', ['Curso']) as $row) {
            $groups[$row['attributes']['IdCurso'] ?? ''] = $row['attributes']['Curso'] ?? '';
        }
        return $groups;
    }

    /**
     * The copy types, with their name, by id.
     *
     * @return array
     */
    public function item_types(): array {
        return $this->types('TiposEjemplar', 'TipoEjemplar', 'IdTipoEjemplar', 'TipoEjemplar');
    }

    /**
     * The reader types, by id: their name and their renewal and card settings.
     *
     * @return array
     */
    public function reader_types(): array {
        $types = [];
        foreach ($this->rows('TiposLectores', ['TipoLector']) as $row) {
            $id = $row['attributes']['IdTipoLector'] ?? '';
            $types[$id] = $row['attributes'] + ['name' => $row['children']['TipoLectorI18n'][0]['Descripcion'] ?? $id];
        }
        return $types;
    }

    /**
     * The loan rules: days and number of loans for each reader type and copy type.
     *
     * @return array[]
     */
    public function rules(): array {
        $rules = [];
        foreach ($this->rows('TiposEjemplarTiposLector', ['TipoEjemplarTipoLector']) as $row) {
            $rules[] = $row['attributes'];
        }
        return $rules;
    }

    /**
     * Rows of a section: the elements just under it.
     *
     * @param string $section
     * @param string[]|null $names Only these element names; null for all.
     * @return \Generator of [name, attributes, fields, children]: fields are the texts of the children, a list per
     *     name; children are the attributes of children elements, a list per name.
     */
    private function rows(string $section, ?array $names = null): \Generator {
        $reader = $this->open();
        $insection = false;
        // After next() the cursor already sits on the following node, so read() must not move it again.
        $moved = false;
        while ($moved || $reader->read()) {
            $moved = false;
            if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->depth === 1 && $insection) {
                break;
            }
            if ($reader->nodeType !== \XMLReader::ELEMENT) {
                continue;
            }
            if ($reader->depth === 1) {
                $insection = $reader->name === $section;
                if ($insection && $reader->isEmptyElement) {
                    break;
                }
                if (!$insection) {
                    $moved = $reader->next();
                }
                continue;
            }
            if (!$insection || $reader->depth !== 2) {
                continue;
            }
            $name = $reader->name;
            if ($names === null || in_array($name, $names, true)) {
                $node = $reader->expand();
                if ($node instanceof \DOMElement) {
                    yield ['name' => $name] + self::element($node);
                }
            }
            $moved = $reader->next();
        }
        $reader->close();
    }

    /**
     * Attributes, child texts and child attributes of an element.
     *
     * @param \DOMElement $node
     * @return array
     */
    private static function element(\DOMElement $node): array {
        $attributes = [];
        foreach ($node->attributes as $attribute) {
            $attributes[$attribute->name] = $attribute->value;
        }
        $fields = [];
        $children = [];
        foreach ($node->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            $text = trim($child->textContent);
            if ($text !== '') {
                $fields[$child->nodeName][] = $text;
            }
            if ($child->hasAttributes()) {
                $childattributes = [];
                foreach ($child->attributes as $attribute) {
                    $childattributes[$attribute->name] = $attribute->value;
                }
                $children[$child->nodeName][] = $childattributes;
            }
        }
        return ['attributes' => $attributes, 'fields' => $fields, 'children' => $children];
    }

    /**
     * Types with an internationalised name.
     *
     * @param string $section
     * @param string $element
     * @param string $idattribute
     * @param string $nameattribute
     * @return array Name by id.
     */
    private function types(string $section, string $element, string $idattribute, string $nameattribute): array {
        $types = [];
        foreach ($this->rows($section, [$element]) as $row) {
            $id = $row['attributes'][$idattribute] ?? '';
            $types[$id] = $row['children'][$element . 'I18n'][0][$nameattribute] ?? $id;
        }
        return $types;
    }

    /**
     * A fresh reader on the file. External entities are never loaded.
     *
     * @return \XMLReader
     */
    private function open(): \XMLReader {
        $reader = new \XMLReader();
        if (!$reader->open($this->path, null, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE)) {
            throw new \InvalidArgumentException('The Abies export cannot be opened: ' . $this->path);
        }
        return $reader;
    }
}
