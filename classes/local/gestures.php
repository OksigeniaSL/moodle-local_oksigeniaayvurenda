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
 * The gestures of a setting in sight: a number with «−» and «+», a lamp, a chip; and the card that holds them.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * What the template settingcard draws: a card with its sentence and its gestures, each a submit button of the card's
 * empty form that sends only what it changes (no JavaScript needed). Used by the rules, the reservations, the voices,
 * the purchases and self-service.
 */
final class gestures {
    /**
     * A setting card: its sentence on top and its groups of gestures under it.
     *
     * @param string $id The card's id and anchor; its form is id-f.
     * @param string $set What the form sends as «set».
     * @param string $title
     * @param string $says
     * @param bool $justsaved
     * @param array[] $groups
     * @param array $more notes, dates, presets, currency, link, help, text, bar, action: what some cards carry.
     * @return array
     */
    public static function card(
        string $id,
        string $set,
        string $title,
        string $says,
        bool $justsaved,
        array $groups,
        array $more = []
    ): array {
        return ['id' => $id, 'set' => $set, 'title' => $title, 'says' => $says, 'justsaved' => $justsaved, 'groups' => $groups]
            + $more + ['notes' => [], 'dates' => null, 'presets' => null, 'currency' => null, 'link' => null, 'help' => null,
            'text' => null, 'bar' => null];
    }

    /**
     * A group of gestures under a label: counters, lights or chips.
     *
     * @param string $label
     * @param array[] $steps
     * @param array[] $lights
     * @param array[] $chips
     * @return array
     */
    public static function group(string $label, array $steps, array $lights, array $chips): array {
        // Chips take a row of their own: one choice among several reads better on its own line than beside counters.
        return ['label' => $label, 'steps' => $steps, 'lights' => $lights, 'chips' => $chips, 'haslights' => (bool) $lights,
            'haschips' => (bool) $chips, 'whole' => (bool) $chips];
    }

    /**
     * A number with its «−» and «+».
     *
     * @param string $form
     * @param string $field
     * @param string $label
     * @param int $value
     * @param int $min
     * @param int $max
     * @param int $by How much each sign moves it.
     * @param string $shown The number as it is read.
     * @param string $unit What it counts, under it.
     * @param callable $str
     * @return array
     */
    public static function step(
        string $form,
        string $field,
        string $label,
        int $value,
        int $min,
        int $max,
        int $by,
        string $shown,
        string $unit,
        callable $str
    ): array {
        return ['form' => $form, 'field' => $field, 'label' => $label, 'shown' => $shown, 'unit' => $unit,
            'less' => max($min, $value - $by), 'more' => min($max, $value + $by), 'atmin' => $value <= $min,
            'atmax' => $value >= $max, 'lesslabel' => $str('hiwless') . ': ' . $label,
            'morelabel' => $str('hiwmore') . ': ' . $label, 'still' => false];
    }

    /**
     * A figure that is not counted with «−» and «+» (no limit), in the place of one.
     *
     * @param string $label
     * @param string $shown
     * @return array
     */
    public static function still(string $label, string $shown): array {
        return ['form' => '', 'field' => '', 'label' => $label, 'shown' => $shown, 'unit' => '', 'less' => 0, 'more' => 0,
            'atmin' => true, 'atmax' => true, 'lesslabel' => '', 'morelabel' => '', 'still' => true];
    }

    /**
     * A lamp: sends what it turns to.
     *
     * @param string $form
     * @param string $name
     * @param int $value
     * @param bool $on
     * @param string $label
     * @return array
     */
    public static function light(string $form, string $name, int $value, bool $on, string $label): array {
        return ['form' => $form, 'name' => $name, 'value' => $value, 'pressed' => $on, 'label' => $label];
    }

    /**
     * A lamp that turns a yes or no: sends the other one.
     *
     * @param string $form
     * @param string $name
     * @param bool $on
     * @param string $label
     * @return array
     */
    public static function lamp(string $form, string $name, bool $on, string $label): array {
        return self::light($form, $name, $on ? 0 : 1, $on, $label);
    }

    /**
     * A chip: sends its value.
     *
     * @param string $form
     * @param string $name
     * @param string $value
     * @param string $label
     * @param bool $on
     * @return array
     */
    public static function chip(string $form, string $name, string $value, string $label, bool $on): array {
        return ['form' => $form, 'name' => $name, 'value' => $value, 'label' => $label, 'pressed' => $on];
    }

    /**
     * The stages as chips, «All the stages» first: none chosen is all of them.
     *
     * @param string $form
     * @param string[] $chosen
     * @param string[] $stages
     * @param callable $str
     * @return array[]
     */
    public static function stages(string $form, array $chosen, array $stages, callable $str): array {
        return array_merge(
            [self::chip($form, 'stage', 'all', $str('holdsallstages'), !$chosen)],
            array_map(
                fn($i, $name) => self::chip($form, 'stage', (string) $i, $name, in_array($name, $chosen, true)),
                array_keys($stages),
                $stages
            )
        );
    }

    /**
     * The stages after a chip of them is touched: «all», or one that comes in or goes out; with every stage in, or none
     * left, all of them again. Null when no stage chip was sent.
     *
     * @param string[] $chosen
     * @param string[] $stages
     * @return string[]|null
     */
    public static function stage_touched(array $chosen, array $stages): ?array {
        $sent = optional_param('stage', '', PARAM_ALPHANUM);
        if ($sent === 'all') {
            return [];
        }
        if ($sent === '' || !isset($stages[(int) $sent])) {
            return null;
        }
        $name = $stages[(int) $sent];
        $chosen = in_array($name, $chosen, true) ? array_values(array_diff($chosen, [$name])) : array_merge($chosen, [$name]);
        return count(array_intersect($stages, $chosen)) === count($stages) ? [] : $chosen;
    }

    /**
     * A number sent, or null.
     *
     * @param string $name
     * @return int|null
     */
    public static function number(string $name): ?int {
        $value = optional_param($name, null, PARAM_INT);
        return $value === null ? null : (int) $value;
    }
}
