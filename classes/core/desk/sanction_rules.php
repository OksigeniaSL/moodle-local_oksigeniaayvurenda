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
 * What a late book costs, if the centre wants it to cost anything.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\core\desk;

use local_oksigeniaayvurenda\core\text;

/**
 * Off unless a centre turns it on: a book that comes back late is only said at the desk. On, it costs days without
 * lending or an amount of money, for each day late past the days of grace, up to a ceiling. Part of the core: plain
 * PHP, with no call to Moodle.
 */
final class sanction_rules {
    /** @var string A late book costs nothing. */
    public const NONE = 'none';

    /** @var string A late book costs days without lending. */
    public const DAYS = 'days';

    /** @var string A late book costs money. */
    public const FINE = 'fine';

    /** @var array The settings of a centre that has said nothing. Money is in hundredths. */
    public const DEFAULTS = ['mode' => self::NONE, 'grace' => 0, 'perday' => 1, 'maxdays' => 30, 'cents' => 10,
        'maxcents' => 500, 'block' => 500, 'currency' => 'EUR', 'stages' => [], 'adults' => true];

    /**
     * The settings, within reason, with the defaults where something is missing.
     *
     * @param array $settings
     * @return array mode, grace, perday, maxdays, cents, maxcents, block, currency, stages, adults.
     */
    public static function settings(array $settings): array {
        $settings += self::DEFAULTS;
        $between = fn($value, int $low, int $high) => max($low, min($high, (int) $value));
        $currency = strtoupper((string) $settings['currency']);
        return [
            'mode' => in_array($settings['mode'], [self::DAYS, self::FINE], true) ? $settings['mode'] : self::NONE,
            'grace' => $between($settings['grace'], 0, 30),
            'perday' => $between($settings['perday'], 1, 30),
            'maxdays' => $between($settings['maxdays'], 1, 365),
            'cents' => $between($settings['cents'], 1, 100000),
            'maxcents' => $between($settings['maxcents'], 1, 10000000),
            'block' => $between($settings['block'], 1, 10000000),
            'currency' => preg_match('/^[A-Z]{3}$/', $currency) ? $currency : self::DEFAULTS['currency'],
            'stages' => array_values(array_filter(array_map('strval', (array) $settings['stages']), fn($s) => trim($s) !== '')),
            'adults' => !empty($settings['adults']),
        ];
    }

    /**
     * Whether a reader answers for their late books: a library that borrows from this one never does; a reader who is not
     * a student, if the centre says so; a student, in the stages it chose (none chosen, all of them).
     *
     * @param array $settings
     * @param string $readertype
     * @param string|null $stage
     * @return bool
     */
    public static function applies(array $settings, string $readertype, ?string $stage): bool {
        if ($settings['mode'] === self::NONE || $readertype === 'library') {
            return false;
        }
        if ($readertype !== 'student') {
            return !empty($settings['adults']);
        }
        if (!$settings['stages']) {
            return true;
        }
        $mine = text::normalise((string) $stage);
        foreach ($settings['stages'] as $chosen) {
            if (text::normalise($chosen) === $mine) {
                return true;
            }
        }
        return false;
    }

    /**
     * The days without lending of a book so late.
     *
     * @param array $settings
     * @param int $dayslate
     * @return int
     */
    public static function days(array $settings, int $dayslate): int {
        $counted = max(0, $dayslate - (int) $settings['grace']);
        return $counted ? min($counted * (int) $settings['perday'], (int) $settings['maxdays']) : 0;
    }

    /**
     * The fine of a book so late, in hundredths.
     *
     * @param array $settings
     * @param int $dayslate
     * @return int
     */
    public static function cents(array $settings, int $dayslate): int {
        $counted = max(0, $dayslate - (int) $settings['grace']);
        return $counted ? min($counted * (int) $settings['cents'], (int) $settings['maxcents']) : 0;
    }

    /**
     * An amount as a form gives it («0,10», «0.10», «2»), in hundredths.
     *
     * @param string $amount
     * @return int
     */
    public static function to_cents(string $amount): int {
        $amount = str_replace(',', '.', trim($amount));
        return is_numeric($amount) ? max(0, (int) round((float) $amount * 100)) : 0;
    }
}
