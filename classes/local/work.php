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
 * The library's card in «Your work», the dashboard of whoever runs something in the centre (Oksigenia Tembiapo,
 * `block_oksigeniatembiapo`): one card for each centre where someone lends, keeps a library or manages, with its door,
 * a line of numbers and what has to be solved, each alert leading to where it is solved. The contract is in
 * shared/moodle-convenciones.md of hq. Kept a few minutes for each person: it runs on every load of the dashboard.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class work {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var int Days late from which a book is urgent, as «Late» paints it red. */
    public const OLD = 30;

    /**
     * The cards of someone, from the cache if it has them.
     *
     * @param int $userid
     * @param int|null $now
     * @return array[] Each with key, title, url, status, alerts (text, url, level) and actions (label, url).
     */
    public static function cards(int $userid, ?int $now = null): array {
        if ($userid <= 0 || isguestuser($userid)) {
            return [];
        }
        $cache = \cache::make('local_oksigeniaayvurenda', 'work');
        $key = $userid . '_' . current_language();
        if ($now === null && ($kept = $cache->get($key)) !== false) {
            return $kept;
        }
        $cards = self::make($userid, $now ?? time());
        if ($now === null) {
            $cache->set($key, $cards);
        }
        return $cards;
    }

    /**
     * The cards, worked out.
     *
     * @param int $userid
     * @param int $now
     * @return array[]
     */
    private static function make(int $userid, int $now): array {
        $byentity = [];
        foreach (catalogue::libraries() as $library) {
            $lends = access::can('local/oksigeniaayvurenda:desk', $library, $userid);
            $keeps = !$lends && access::keeps($library, $userid);
            $manages = access::can('local/oksigeniaayvurenda:manage', $library, $userid);
            // A library hanging from another is its keeper's, or the centre's managers'.
            if ($library->parentid && !$keeps && !$manages) {
                continue;
            }
            if ($lends || $keeps || $manages) {
                $byentity[(int) $library->entityid][] = ['library' => $library, 'lends' => $lends || $keeps,
                    'manages' => $manages];
            }
        }
        $cards = [];
        foreach ($byentity as $entityid => $libraries) {
            if (practice::is($entityid)) {
                continue;
            }
            $cards[] = self::card($entityid, $libraries, $userid, $now, count($byentity) > 1);
        }
        return $cards;
    }

    /**
     * The card of one centre.
     *
     * @param int $entityid
     * @param array[] $libraries Each with library, lends and manages.
     * @param int $userid
     * @param int $now
     * @param bool $named Whether the centre's name goes in the title, when someone works in several.
     * @return array
     */
    private static function card(int $entityid, array $libraries, int $userid, int $now, bool $named): array {
        global $DB;
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        $url = fn(string $page, \stdClass $library, array $params = []) => (new \moodle_url(
            '/local/oksigeniaayvurenda/' . $page,
            ['library' => $library->slug] + $params
        ))->out(false);
        $count = fn(int $n, string $key) => $str($key . ($n === 1 ? 'one' : ''), screens::number($n));
        $alerts = [];
        $lent = 0;
        $late = 0;
        $today = 0;
        $ready = 0;
        $manages = false;
        $first = null;
        $lendsin = null;
        foreach ($libraries as $one) {
            $library = $one['library'];
            $first ??= $library;
            $manages = $manages || $one['manages'];
            if (!$one['lends']) {
                continue;
            }
            $lendsin ??= $library;
            $start = zones::today($library, $now);
            $lent += $DB->count_records_select(self::T . 'loan', 'libraryid = ? AND timereturned IS NULL', [$library->id]);
            $out = 'libraryid = ? AND timereturned IS NULL AND timedue ';
            $n = $DB->count_records_select(self::T . 'loan', $out . '< ?', [$library->id, $start]);
            $o = $DB->count_records_select(self::T . 'loan', $out . '< ?', [$library->id, $start - self::OLD * DAYSECS]);
            $t = $DB->count_records_select(
                self::T . 'loan',
                $out . '>= ? AND timedue < ?',
                [$library->id, $start, $start + DAYSECS]
            );
            $late += $n;
            $today += $t;
            $ready += $DB->count_records(self::T . 'hold', ['libraryid' => $library->id, 'status' => 'ready']);
            if ($o) {
                $alerts[] = ['text' => $count($o, 'workold'), 'url' => $url('late.php', $library, ['span' => 'old']),
                    'level' => 'urgent'];
            }
            if ($n - $o > 0) {
                $alerts[] = ['text' => $count($n - $o, 'worklate'), 'url' => $url('late.php', $library), 'level' => 'today'];
            }
            if ($t) {
                $alerts[] = ['text' => $count($t, 'worktoday'), 'url' => $url('late.php', $library, ['all' => 1,
                    'span' => 'today']), 'level' => 'today'];
            }
            $stopped = selfloan::stopped($library);
            if ($stopped) {
                $alerts[] = ['text' => $count($stopped, 'workstopped'), 'url' => $url('selfloan.php', $library),
                    'level' => 'urgent'];
            }
            $ill = ill::overdue($library, $now);
            if ($ill) {
                $alerts[] = ['text' => $count($ill, 'workill'), 'url' => $url('ill.php', $library), 'level' => 'today'];
            }
            if ($one['manages']) {
                $waiting = menu::waiting($library, $userid);
                $asked = (int) ($waiting['purchases.php'] ?? 0) + (int) ($waiting['formats.php'] ?? 0);
                if ($asked) {
                    $alerts[] = ['text' => $count($asked, 'workasked'), 'url' => $url(
                        ($waiting['purchases.php'] ?? 0) ? 'purchases.php' : 'formats.php',
                        $library
                    ), 'level' => 'today'];
                }
            }
        }
        $main = mail::main_library($entityid) ?? $first;
        if ($manages) {
            // What waits for the whole centre: what readers and classes ask, reviews and voices to look at, the copies.
            $waiting = menu::waiting($main, $userid);
            $wishes = (int) ($waiting['wishes.php'] ?? 0);
            if ($wishes) {
                $alerts[] = ['text' => $count($wishes, 'workwishes'), 'url' => $url('wishes.php', $main), 'level' => 'today'];
            }
            $look = (int) ($waiting['reviews.php'] ?? 0) + (int) ($waiting['voices.php'] ?? 0);
            if ($look) {
                $alerts[] = ['text' => $count($look, 'worklook'), 'url' => $url(
                    ($waiting['reviews.php'] ?? 0) ? 'reviews.php' : 'voices.php',
                    $main
                ), 'level' => 'today'];
            }
            $copies = offsite::settings($entityid);
            if ($copies['places'] && $copies['failures'] >= 2) {
                $alerts[] = ['text' => $count($copies['failures'], 'workcopies'), 'url' => $url('copies.php', $main),
                    'level' => 'urgent'];
            }
        }
        // The urgent first, as the block shows them.
        usort($alerts, fn($a, $b) => ($a['level'] === 'urgent' ? 0 : 1) <=> ($b['level'] === 'urgent' ? 0 : 1));

        $parts = [];
        if ($lendsin) {
            $parts[] = $lent ? $count($lent, 'worklent') : $str('worknothinglent');
            if ($late) {
                $parts[] = $count($late, 'worklatestatus');
            }
            if ($ready) {
                $parts[] = $count($ready, 'workready');
            }
        }
        $door = $lendsin ? $url('desk.php', $lendsin) : $url('index.php', $main);
        $actions = [];
        if ($lendsin) {
            $actions[] = ['label' => $str('workcatalogue'), 'url' => $url('index.php', $lendsin)];
            $actions[] = ['label' => $str('worklatelink'), 'url' => $url('late.php', $lendsin)];
        } else {
            $actions[] = ['label' => $str('workreports'), 'url' => $url('reports.php', $main)];
        }
        $name = format_string(
            (string) $DB->get_field(self::T . 'entity', 'name', ['id' => $entityid]),
            true,
            ['escape' => false]
        );
        return [
            'key' => 'library-' . $entityid,
            'title' => $named ? $str('worktitlecentre', $name) : $str('worktitle'),
            'url' => $door,
            'status' => $parts ? implode(' · ', $parts) : $str('workmanage'),
            'alerts' => $alerts,
            'actions' => $actions,
        ];
    }
}
