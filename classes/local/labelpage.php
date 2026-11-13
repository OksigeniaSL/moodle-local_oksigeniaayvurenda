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
 * The page of labels as one sample sheet: what, of which copies and on which sheet, and the first sheet as it will print.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

use local_oksigeniaayvurenda\core\barcode\code39;
use local_oksigeniaayvurenda\core\label_sheets;

/**
 * The labels were chosen with two radio buttons, three options, six date lists from 2016, a list of 26 sheets and a
 * «Show», and the page painted every label (2,000 of them, 96,696 px). Now, on the left, what is printed (two cards with
 * a sample label each), of which copies (chips, the days things came in as small sheets, a list of codes on its paper)
 * and the sheet (each drawn small, the favourites first); on the right, the first sheet as it will come out, where the
 * first free label is touched. A sentence says how many sheets; all of them go to a page of their own to print. Every
 * choice is a link: it is a view.
 */
final class labelpage {
    /** @var int Days with something added that the chips offer, besides today. */
    public const DAYS = 5;

    /**
     * What the page shows.
     *
     * @param \stdClass $library
     * @param array $state kind, which, since ('Y-m-d' or ''), codes, sheet, start, adjust.
     * @param array[] $items The copies chosen (labels::items), at most labels::LIMIT.
     * @param bool $toomany Whether there were more than fit.
     * @param string $saved What was just saved: fav or nudge.
     * @return array
     */
    public static function context(\stdClass $library, array $state, array $items, bool $toomany, string $saved = ''): array {
        $entityid = (int) $library->entityid;
        $str = skins::speaker($library);
        $kind = $state['kind'];
        $sheet = $state['sheet'];
        $here = ['library' => $library->slug, 'kind' => $kind, 'sheet' => $sheet, 'which' => $state['which'],
            'since' => $state['since'], 'codes' => $state['codes']];
        $url = fn(array $change, string $anchor = '') => (new \moodle_url(
            '/local/oksigeniaayvurenda/labels.php',
            array_filter($change + $here, fn($v) => $v !== '' && $v !== null),
            $anchor !== '' ? $anchor : null
        ))->out(false);
        $own = labels::own($library);
        $format = label_sheets::placed($sheet);
        $model = label_sheets::MODELS[$sheet];
        $pages = labels::sheets($items, $sheet, $state['start']);
        $count = count($items);
        $sheets = $count ? count($pages) : 0;
        $name = $model['brand'] . ' ' . $model['ref'];
        $isfav = in_array($sheet, $own['favs'][$kind], true);
        [$nudgex, $nudgey] = $own['nudge'][$sheet] ?? [0.0, 0.0];
        $first = labels::items($library, 'today', 0, []);
        $sample = $items[0] ?? $first[0] ?? null;
        $pose = $saved !== '' ? 'celebra' : 'lee';
        $crier = characters::whole($entityid) ? characters::figure('herald', $pose, characters::step($entityid)) : null;
        $b = fn(string $text) => '<b>' . s($text) . '</b>';
        $plural = fn(int $n, string $one, string $many) => $n === 1 ? $str($one) : $str($many, screens::number($n));
        return [
            'title' => $str('labelsheading'),
            'intro' => $str('labelsintro'),
            'crier' => $crier ? ['figure' => $crier, 'puppet' => characters::puppet('herald', $pose, characters::step($entityid))]
                : null,
            'savedword' => $str('hiwsaved'),
            'kindtitle' => $str('labelskind'),
            'kinds' => [
                ['url' => $url(['kind' => 'labels', 'start' => null]), 'current' => $kind === 'labels', 'iscode' => true,
                    'name' => $str('labelskindcode'), 'sub' => $str('labelskindcodesub'),
                    'library' => format_string($library->name), 'code' => $sample['barcode'] ?? 'AY-01240-K',
                    'svg' => code39::svg(code39::can($sample['barcode'] ?? '') ? $sample['barcode'] : 'AY01240K', 40, false),
                    'booktitle' => $sample['title'] ?? $str('labelssampletitle')],
                ['url' => $url(['kind' => 'spines', 'start' => null]), 'current' => $kind === 'spines', 'iscode' => false,
                    'name' => $str('labelskindspine'), 'sub' => $str('labelskindspinesub'),
                    'lines' => self::lines(($sample['callnumber'] ?? []) ?: ['82-3', 'ROW', 'abu'])],
            ],
            'whichtitle' => $str('labelswhich'),
            'whiches' => [
                // Nothing today: said quietly, not a chip that leads to an empty sheet.
                ['url' => $first || $state['which'] === 'today'
                    ? $url(['which' => 'today', 'since' => null, 'codes' => null, 'start' => null]) : null,
                    'current' => $state['which'] === 'today', 'label' => $first || $state['which'] === 'today'
                        ? $str('labelswhichtodayn', screens::number(count($first))) : $str('labelswhichtodaynone')],
                ['url' => $url(['which' => 'since', 'codes' => null, 'start' => null], 'ayv-et-dias'),
                    'current' => $state['which'] === 'since', 'label' => $state['which'] === 'since' && $state['since'] !== ''
                        ? $str('labelswhichsincen', (object) ['day' => self::day($library, $state['since']),
                            'count' => screens::number($count)]) : $str('labelswhichsinceany')],
                ['url' => $url(['which' => 'codes', 'since' => null, 'start' => null], 'ayv-et-lista'),
                    'current' => $state['which'] === 'codes', 'label' => $str('labelswhichcodes')],
            ],
            'days' => $state['which'] === 'since' ? [
                'lead' => $str('labelsdayslead'),
                'list' => array_map(fn($day) => ['url' => $url(['which' => 'since', 'since' => $day['date'], 'start' => null]),
                    'label' => $day['label'], 'current' => $day['date'] === $state['since']], self::days($library)),
                'otherurl' => (new \moodle_url('/local/oksigeniaayvurenda/year.php', ['library' => $library->slug,
                    'elegir' => 'labels', 'kind' => $kind, 'sheet' => $sheet], 'ayv-cs-sheet'))->out(false),
                'otherlabel' => $str(skins::words($entityid, 'labelsotherday')),
            ] : null,
            'codes' => $state['which'] === 'codes' ? [
                'action' => (new \moodle_url('/local/oksigeniaayvurenda/labels.php'))->out(false),
                'hidden' => [['name' => 'library', 'value' => $library->slug], ['name' => 'kind', 'value' => $kind],
                    ['name' => 'sheet', 'value' => $sheet], ['name' => 'which', 'value' => 'codes']],
                'label' => $str('labelscodes'), 'value' => $state['codes'], 'show' => $str('labelsshow'),
            ] : null,
            'sheettitle' => $str('labelssheet'),
            'sheets' => self::sheets($library, $kind, $sheet, $own, $url, $str),
            'otherslabel' => $str('labelsothersheets'),
            'othersnote' => $str('labelsothersnote'),
            'firstsheet' => $str('labelsfirstsheet'),
            'says' => $count ? $str('labelssays', (object) [
                'count' => $b($plural(
                    $count,
                    $kind === 'spines' ? 'labelsonespine' : 'labelsonecode',
                    $kind === 'spines' ? 'labelsnspines' : 'labelsncodes'
                )),
                'sheets' => $b($plural($sheets, 'labelsonesheet', 'labelsnsheets')),
                'sheet' => s($name), 'per' => $format['per'],
                'start' => $state['start'] > 1 ? $str('labelssaysstart', $state['start']) : '',
            ]) : '',
            'haslabels' => (bool) $count,
            'empty' => $str('labelsempty'),
            'toomany' => $toomany ? $str('labelstoomany', screens::number(labels::LIMIT)) : '',
            'paper' => [
                'cols' => $format['cols'], 'rows' => $format['rows'],
                'letter' => $format['paper'] === 'letter',
                'cells' => self::cells($pages[0]['labels'] ?? [], $format['per'], $state['start'], $kind, $url, $str),
            ],
            'note' => $str('labelsnote'),
            'printurl' => $url(['print' => 1, 'start' => $state['start'] > 1 ? $state['start'] : null]),
            'printlabel' => $sheets === 1 ? $str('labelsprintone') : $str('labelsprintn', screens::number($sheets)),
            'favaction' => $url([]),
            'sesskey' => sesskey(),
            'fav' => ['pressed' => $isfav, 'label' => $str($isfav ? 'labelsfavyes' : 'labelsfavno'),
                'justsaved' => $saved === 'fav'],
            'adjusturl' => $url(['adjust' => 1, 'start' => $state['start'] > 1 ? $state['start'] : null], 'ayv-et-ajuste'),
            'adjustlabel' => $str('labelsnudge'),
            'adjust' => $state['adjust'] || $saved === 'nudge' ? [
                'title' => $str('labelsnudgetitle'),
                'hint' => $str('labelsadjusthint'),
                'now' => $nudgex || $nudgey ? $str('labelsnudged', implode($str('labelsand'), array_filter([
                    $nudgex ? $str($nudgex > 0 ? 'labelsnudgeright' : 'labelsnudgeleft', format_float(abs($nudgex), 1, true, true))
                        : '',
                    $nudgey ? $str($nudgey > 0 ? 'labelsnudgedown' : 'labelsnudgeup', format_float(abs($nudgey), 1, true, true))
                        : '',
                ]))) : $str('labelsnudgednone'),
                'justsaved' => $saved === 'nudge',
                'moves' => array_map(
                    fn($move) => ['value' => $move, 'label' => $str('labelsmove' . $move),
                    'arrow' => ['left' => '←', 'right' => '→', 'up' => '↑', 'down' => '↓'][$move]],
                    ['left', 'right', 'up', 'down']
                ),
                'reset' => $nudgex || $nudgey ? $str('labelsmovereset') : '',
                'testhint' => $str('labelstesthint'),
                'testurl' => $url(['print' => 1, 'test' => 1, 'start' => null]),
                'testlabel' => $str('labelstest'),
                'close' => $url(['adjust' => null]),
                'closelabel' => $str('offsiteclose'),
            ] : null,
        ];
    }

    /**
     * The sheets to choose from: the favourites and the one chosen at the top, the rest under «Other sheets».
     *
     * @param \stdClass $library
     * @param string $kind
     * @param string $sheet
     * @param array $own labels::own()
     * @param callable $url
     * @param callable $str
     * @return array top, others, hasothers.
     */
    private static function sheets(
        \stdClass $library,
        string $kind,
        string $sheet,
        array $own,
        callable $url,
        callable $str
    ): array {
        $mm = fn(float $value) => format_float($value, 2, true, true);
        $card = function (string $key) use ($sheet, $url, $str, $mm): array {
            $model = label_sheets::MODELS[$key];
            $per = $model['cols'] * $model['rows'];
            $sub = $str('labelssheetsub', (object) ['size' => $mm($model['w']) . ' × ' . $mm($model['h']), 'per' => $per]);
            return ['url' => $url(['sheet' => $key, 'start' => null]), 'current' => $key === $sheet,
                'name' => $model['brand'] . ' ' . $model['ref'],
                'sub' => $sub . ($model['also'] !== '' ? ' · ' . $str('labelsalso', $model['also']) : '')
                    . ($model['paper'] === 'letter' ? ' · ' . $str('labelsletter') : ''),
                'cols' => $model['cols'], 'rows' => $model['rows'], 'cells' => array_fill(0, $per, ['x' => 1])];
        };
        $top = array_values(array_unique(array_merge($own['favs'][$kind], [$sheet])));
        $others = array_values(array_diff(array_keys(label_sheets::MODELS), $top));
        return ['top' => array_map($card, $top), 'others' => array_map($card, $others), 'hasothers' => (bool) $others];
    }

    /**
     * The first sheet, label by label: the ones before the start crossed, each a link to start there.
     *
     * @param array[] $labels The first sheet's labels (labels::sheets), blanks first.
     * @param int $per Labels on a sheet.
     * @param int $start
     * @param string $kind
     * @param callable $url
     * @param callable $str
     * @return array[]
     */
    private static function cells(array $labels, int $per, int $start, string $kind, callable $url, callable $str): array {
        $cells = [];
        for ($n = 1; $n <= $per; $n++) {
            $label = $labels[$n - 1] ?? ['blank' => true];
            $used = $n < $start;
            $cells[] = ['url' => $url(['start' => $n > 1 ? $n : null], 'ayv-et-hoja'), 'aria' => $str('labelsstartat', $n),
                'used' => $used, 'first' => $n === $start, 'blank' => !empty($label['blank']) && !$used,
                'spine' => $kind === 'spines' && empty($label['blank']) ? ['lines' => self::lines($label['callnumber'])] : null,
                'code' => $kind !== 'spines' && empty($label['blank']) ? ['library' => $label['library'],
                    'svg' => $label['svg'], 'barcode' => $label['barcode'], 'title' => $label['title']] : null];
        }
        return $cells;
    }

    /**
     * The lines of a spine label as the sample draws them: the first, the class, on its yellow band.
     *
     * @param string[] $lines
     * @return array[] line and top.
     */
    private static function lines(array $lines): array {
        return array_map(
            fn($i, $line) => ['line' => $line, 'top' => $i === 0],
            array_keys(array_slice($lines, 0, 3)),
            array_slice($lines, 0, 3)
        );
    }

    /**
     * The last days something came into the library, and today: the days a «since» is usually asked from.
     *
     * @param \stdClass $library
     * @param int|null $now
     * @return array[] date ('Y-m-d') and label («fri 2 Oct», «today»), oldest first.
     */
    public static function days(\stdClass $library, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $zone = zones::of($library);
        $today = (new \DateTimeImmutable('@' . $now))->setTimezone($zone)->format('Y-m-d');
        // By the hour, in the database: an import brings thousands of copies in one afternoon.
        $hours = $DB->get_fieldset_sql(
            'SELECT DISTINCT FLOOR(timecreated / 3600) FROM {local_oksigeniaayvurenda_item}
              WHERE libraryid = :libraryid AND timecreated >= :since',
            ['libraryid' => $library->id, 'since' => $now - 180 * DAYSECS]
        );
        $days = [];
        foreach ($hours as $hour) {
            $day = (new \DateTimeImmutable('@' . ((int) $hour * 3600)))->setTimezone($zone)->format('Y-m-d');
            if ($day < $today) {
                $days[$day] = true;
            }
        }
        ksort($days);
        $list = [];
        foreach (array_slice(array_keys($days), -self::DAYS) as $day) {
            $at = (new \DateTimeImmutable($day . ' 12:00', $zone))->getTimestamp();
            $list[] = ['date' => $day, 'label' => (userdate($at, get_string(
                'labelsdayformat',
                'local_oksigeniaayvurenda'
            ), zones::name($library)))];
        }
        $list[] = ['date' => $today, 'label' => get_string('labelstoday', 'local_oksigeniaayvurenda')];
        return $list;
    }

    /**
     * A day said as in a sentence: «2 de octubre».
     *
     * @param \stdClass $library
     * @param string $date 'Y-m-d'.
     * @return string
     */
    private static function day(\stdClass $library, string $date): string {
        $at = new \DateTimeImmutable($date . ' 12:00', zones::of($library));
        return get_string('reportdaymonth', 'local_oksigeniaayvurenda', (object) ['day' => $at->format('j'),
            'month' => userdate($at->getTimestamp(), '%B', zones::name($library))]);
    }
}
