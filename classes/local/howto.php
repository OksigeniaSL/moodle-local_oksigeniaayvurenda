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

use local_oksigeniaayvurenda\core\text;

/**
 * How do I…? A guide of the staff's tasks, each with its steps, the words someone would look for it with, its screen and
 * its guided help, searched with no help from outside: words without accents or capitals, and the start of each word,
 * so «préstamo» finds «prestar».
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class howto {
    /** @var array[] Each task: its screen, the permission it needs and its guided help (or none). */
    public const TASKS = [
        'lend' => ['desk.php', 'desk', 'desk'],
        'giveback' => ['desk.php', 'desk', 'desk'],
        'renew' => ['desk.php', 'desk', ''],
        'room' => ['desk.php', 'desk', ''],
        'add' => ['add.php', 'catalogue', 'add'],
        'free' => ['free.php', 'catalogue', ''],
        'labels' => ['labels.php', 'catalogue', 'labels'],
        'cards' => ['readers.php', 'desk', 'card'],
        'readers' => ['readers.php', 'desk', ''],
        'late' => ['late.php', 'desk', ''],
        'holds' => ['holds.php', 'desk', ''],
        'reviews' => ['reviews.php', 'desk', 'reviews'],
        'wishes' => ['wishes.php', 'desk', 'wishes'],
        'myclass' => ['class.php', 'view', 'myclass'],
        'reports' => ['reports.php', 'desk', 'reports'],
        'copies' => ['copies.php', 'desk', ''],
        'stocktake' => ['stocktake.php', 'catalogue', ''],
        'weed' => ['weed.php', 'catalogue', ''],
        'lots' => ['lots.php', 'desk', ''],
        'import' => ['import.php', 'manage', ''],
        'year' => ['year.php', 'manage', ''],
        'tutors' => ['tutors.php', 'manage', ''],
        'rules' => ['rules.php', 'manage', ''],
        'libraries' => ['libraries.php', 'manage', ''],
        'characters' => ['characters.php', 'manage', ''],
        'window' => ['public.php', 'manage', ''],
    ];

    /** @var string[] Words that say nothing about the task. */
    private const QUIET = ['como', 'hago', 'hacer', 'puedo', 'para', 'una', 'uno', 'unos', 'unas', 'los', 'las', 'del',
        'que', 'con', 'por', 'how', 'can', 'the', 'and', 'for', 'with', 'what'];

    /**
     * The tasks that answer a question, best first; with no question, all of them.
     *
     * @param string $query
     * @param \stdClass $library
     * @param int $userid
     * @return array[] key, title, steps, url, help (address or null).
     */
    public static function find(string $query, \stdClass $library, int $userid): array {
        $context = \context_system::instance();
        $words = array_values(array_filter(
            preg_split('/[^\p{L}\p{N}]+/u', text::normalise($query)) ?: [],
            fn($w) => \core_text::strlen($w) >= 3 && !in_array($w, self::QUIET, true)
        ));
        $found = [];
        foreach (self::TASKS as $key => [$page, $capability, $piece]) {
            if (!has_capability('local/oksigeniaayvurenda:' . $capability, $context, $userid)) {
                continue;
            }
            $task = self::task($key, $library);
            $score = $words ? self::score($words, $task) : 1;
            if ($score > 0) {
                $found[] = $task + ['score' => $score];
            }
        }
        usort($found, fn($a, $b) => $b['score'] <=> $a['score']);
        if (!$words) {
            return $found;
        }
        // Only the answers that come near the best one: the weak ones would only be in the way.
        $best = $found ? $found[0]['score'] : 0;
        return array_slice(array_values(array_filter($found, fn($t) => 2 * $t['score'] >= $best)), 0, 3);
    }

    /**
     * One task, in the reader's language.
     *
     * @param string $key
     * @param \stdClass $library
     * @return array key, title, steps, words, url, help.
     */
    public static function task(string $key, \stdClass $library): array {
        [$page, , $piece] = self::TASKS[$key];
        $str = fn(string $k) => get_string($k, 'local_oksigeniaayvurenda');
        return [
            'key' => $key,
            'title' => $str('howto' . $key),
            'steps' => array_map('trim', explode('|', $str('howto' . $key . 'steps'))),
            'words' => $str('howto' . $key . 'words'),
            'url' => (new \moodle_url('/local/oksigeniaayvurenda/' . $page, ['library' => $library->slug]))->out(false),
            'help' => $piece !== '' ? (new \moodle_url('/local/oksigeniaayvurenda/help.php', ['library' => $library->slug,
                'piece' => $piece]))->out(false) : null,
        ];
    }

    /**
     * How well a task answers some words: its own words count most, then its title, then its steps.
     *
     * @param string[] $words Normalised.
     * @param array $task
     * @return int
     */
    private static function score(array $words, array $task): int {
        $fields = [3 => $task['words'], 2 => $task['title'], 1 => implode(' ', $task['steps'])];
        $score = 0;
        foreach ($words as $word) {
            $stem = \core_text::substr($word, 0, 5);
            foreach ($fields as $weight => $field) {
                foreach (preg_split('/[^\p{L}\p{N}]+/u', text::normalise($field)) ?: [] as $their) {
                    if ($their !== '' && (str_starts_with($their, $word) || str_starts_with($their, $stem))) {
                        $score += $weight;
                        break;
                    }
                }
            }
        }
        return $score;
    }
}
