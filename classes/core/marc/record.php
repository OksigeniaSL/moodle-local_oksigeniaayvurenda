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
 * A MARC record.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\core\marc;

/**
 * A MARC record as plain data: the leader, the control fields and the data fields with their indicators and
 * subfields, in their order. Plain PHP, without Moodle.
 */
final class record {
    /** @var string The leader, 24 characters. */
    public string $leader;

    /** @var array<int, array{tag: string, value?: string, ind1?: string, ind2?: string, subfields?: array}> Fields. */
    public array $fields = [];

    /**
     * A record with its leader.
     *
     * @param string $leader
     */
    public function __construct(string $leader = '00000nam a2200000 a 4500') {
        $this->leader = str_pad(substr($leader, 0, 24), 24);
    }

    /**
     * Adds a control field (001 to 009).
     *
     * @param string $tag
     * @param string $value
     * @return self
     */
    public function control(string $tag, string $value): self {
        $this->fields[] = ['tag' => $tag, 'value' => $value];
        return $this;
    }

    /**
     * Adds a data field.
     *
     * @param string $tag
     * @param string $ind1
     * @param string $ind2
     * @param array $subfields List of [code, value].
     * @return self
     */
    public function data(string $tag, string $ind1, string $ind2, array $subfields): self {
        $this->fields[] = ['tag' => $tag, 'ind1' => $ind1 === '' ? ' ' : $ind1, 'ind2' => $ind2 === '' ? ' ' : $ind2,
            'subfields' => array_values($subfields)];
        return $this;
    }

    /**
     * The value of a control field.
     *
     * @param string $tag
     * @return string|null
     */
    public function control_value(string $tag): ?string {
        foreach ($this->fields as $field) {
            if ($field['tag'] === $tag && isset($field['value'])) {
                return $field['value'];
            }
        }
        return null;
    }

    /**
     * The data fields with one of these tags, in their order.
     *
     * @param string ...$tags
     * @return array[]
     */
    public function data_fields(string ...$tags): array {
        return array_values(array_filter($this->fields, fn($f) => isset($f['subfields']) && in_array($f['tag'], $tags, true)));
    }

    /**
     * Every value of some subfields in fields with one of these tags.
     *
     * @param string[] $tags
     * @param string $codes Subfield codes, e.g. 'ab'.
     * @return string[]
     */
    public function values(array $tags, string $codes): array {
        $values = [];
        foreach ($this->data_fields(...$tags) as $field) {
            foreach ($field['subfields'] as [$code, $value]) {
                if (str_contains($codes, (string) $code)) {
                    $values[] = $value;
                }
            }
        }
        return $values;
    }

    /**
     * The first value of a subfield in the first field with one of these tags that has it.
     *
     * @param string[] $tags
     * @param string $code
     * @return string|null
     */
    public function first(array $tags, string $code): ?string {
        foreach ($this->data_fields(...$tags) as $field) {
            foreach ($field['subfields'] as [$c, $value]) {
                if ((string) $c === $code) {
                    return $value;
                }
            }
        }
        return null;
    }
}
