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
 * The whole library in figures, as its sheet shows them.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * What glance::of() counts, put as the sheet of the reports draws it: a shelf of a hundred spines, the columns of the trend
 * and the bars, each with the address of its detail.
 */
final class overall {
    /** @var int The columns up to which each one has its figure written and is touched. */
    private const WRITTEN = 14;

    /**
     * The context of the template report_overall, and what there is (holdings) for the sheet above its periods.
     *
     * @param \stdClass $library
     * @param array $glance From glance::of().
     * @param int $from
     * @param int $to
     * @param int $now
     * @param string[] $span The parameters that say the period, for the sheets that have one.
     * @return array
     */
    public static function context(\stdClass $library, array $glance, int $from, int $to, int $now, array $span): array {
        $str = skins::speaker($library);
        $day = fn(int $time) => zones::date($time, '%Y-%m-%d', $library);
        $page = fn(string $file, array $more = []) => (new \moodle_url(
            '/local/oksigeniaayvurenda/' . $file,
            ['library' => $library->slug] + $more
        ))->out(false);
        $report = fn(string $which, array $more = []) => $page('reports.php', ['report' => $which] + $more);
        $classes = !skins::no_classes((int) $library->entityid);
        $n = fn(int $number) => screens::number($number);
        $collection = $glance['collection'];
        $use = $glance['use'];
        $before = $glance['before'];
        $same = $before ? ['now' => $str('overallnow'), 'before' => $before['name']] : null;
        return [
            'holdings' => [
                'lead' => $str($collection['copies'] === 1 ? 'overallcopiesone' : 'overallcopies', $n($collection['copies'])),
                'rest' => $str($collection['titles'] === 1 ? 'overalltitlesone' : 'overalltitles', $n($collection['titles'])),
                'url' => $report('holdings'),
                'kinds' => count($collection['kinds']) < 2 ? [] : array_map(
                    fn(string $kind, int $copies) => ['label' => $str('overallkind' . $kind), 'value' => $n($copies)],
                    array_keys($collection['kinds']),
                    $collection['kinds']
                ),
            ],
            // A term that has not come yet has nothing used to draw: only what went out the year before.
            'shelf' => $collection['copies'] && $from <= $now ? self::shelf($str, $collection, $use, [
                'used' => $use['used'] ? $report('top', $span) : '',
                'other' => '',
                'never' => $collection['never'] ? $report('never', ['unlent' => 0, 'older' => 0]) : '',
            ]) : null,
            'trend' => self::trend($library, $str, $glance, $from > $now, fn(int $a, int $b) => $report(
                'overall',
                ['desde' => $day($a), 'hasta' => $day($b - 1)]
            )),
            'use' => array_slice(self::lines($str, $glance, $report, $page, $span, $classes), 0, $from > $now ? 1 : null),
            'top' => $glance['top'] ? ['heading' => $str('reporttoptitle'),
                'all' => ['url' => $report('top', $span), 'label' => $str('overallall')],
                'books' => self::books($str, $glance['top'], $page)] : null,
            'stages' => $glance['stages'] ? ['heading' => $str('memoirstages'),
                'rows' => self::stages($str, $glance['stages'], $report('loans', $span))] : null,
            'corners' => $glance['corners'] ? ['heading' => $str('memoircorners'), 'keys' => $same,
                'rows' => self::corners($str, $glance['corners'], (bool) $before, $page)] : null,
            'csv' => $report('overall', $span + ['format' => 'csv']),
            'reportcsv' => $str('reportcsv'),
        ];
    }

    /**
     * A percentage, with a decimal when it has one.
     *
     * @param float $share
     * @return string
     */
    private static function share(float $share): string {
        return format_float($share, fmod($share, 1.0) > 0 ? 1 : 0) . "\u{00A0}%";
    }

    /**
     * The name of a year, that no line breaks in two.
     *
     * @param string $name Like 2025-2026.
     * @return string
     */
    private static function unbroken(string $name): string {
        return str_replace('-', "\u{2060}-\u{2060}", $name);
    }

    /**
     * The shelf: of a hundred spines, those used in the period, those lent at another time and those never lent.
     *
     * @param \Closure $str
     * @param array $collection
     * @param array $use
     * @param string[] $urls used, other and never.
     * @return array
     */
    private static function shelf(\Closure $str, array $collection, array $use, array $urls): array {
        $other = (int) ($use['other'] ?? max(0, $collection['copies'] - $use['used'] - $collection['never']));
        $used = min(100.0, 100 * $use['used'] / $collection['copies']);
        $whole = (int) floor($used);
        // The part of a spine, never so thin that it is not seen.
        $fill = $used - $whole > 0 ? (int) max(12, round(100 * ($used - $whole))) : 0;
        $lent = min(100, $whole + ($fill ? 1 : 0) + (int) round(100 * $other / $collection['copies']));
        $spines = [];
        for ($i = 0; $i < 100; $i++) {
            $spines[] = ['used' => $i < $whole, 'part' => $i === $whole && $fill > 0, 'fill' => $fill,
                'other' => $i >= $whole && $i < $lent && !($i === $whole && $fill > 0 && !$other)];
        }
        $n = fn(int $number) => screens::number($number);
        return ['lead' => self::share($use['usedshare']), 'rest' => $str('overallused'), 'note' => $str('overallspine'),
            'spines' => $spines, 'keys' => array_values(array_filter([
                ['key' => 'used', 'label' => $str('overallkeyused'), 'value' => $n($use['used']), 'url' => $urls['used']],
                $other ? ['key' => 'other', 'label' => $str('overallkeyother'), 'value' => $n($other),
                    'url' => $urls['other']] : null,
                ['key' => 'never', 'label' => $str('overallkeynever'), 'url' => $urls['never'], 'value' =>
                    $n($collection['never']) . ($collection['never'] ? ' · ' . self::share($collection['nevershare']) : '')],
            ]))];
    }

    /**
     * The trend: the loans of the period piece by piece, and the same of the year before.
     *
     * @param \stdClass $library
     * @param \Closure $str
     * @param array $glance
     * @param bool $tocome Whether the whole period is still to come.
     * @param \Closure $url Makes the address of a stretch: fn(int $from, int $to): string.
     * @return array
     */
    private static function trend(\stdClass $library, \Closure $str, array $glance, bool $tocome, \Closure $url): array {
        $n = fn(int $number) => screens::number($number);
        $points = $glance['series']['points'];
        $unit = $glance['series']['unit'];
        $before = $glance['before'];
        $loans = fn(int $count) => $str($count === 1 ? 'reportloansone' : 'reportloanscount', $n($count));
        $max = max(array_merge([1], array_column($points, 'loans'), array_column($points, 'before')));
        $most = max(array_merge([0], array_column($points, 'loans')));
        $written = count($points) <= self::WRITTEN;
        $at = fn(int $time, string $format) => \core_text::strtolower(trim(zones::date($time, $format, $library)));
        $columns = [];
        foreach ($points as $i => $point) {
            $long = self::piece($library, $unit, $point);
            $short = match ($unit) {
                'day' => $written ? $at($point['start'], '%a') . ' ' . (int) $at($point['start'], '%d')
                    : (string) (int) $at($point['start'], '%d'),
                'month' => $at($point['start'], '%b'),
                default => $point['name'],
            };
            if ($unit === 'day' && !$written && (int) $short % 5 !== 1) {
                $short = '';
            }
            $past = $point['loans'] !== null;
            $parts = array_filter([$past ? $loans($point['loans']) : '', $point['before'] !== null && $before
                ? $str('overallbefore', (object) ['year' => $before['name'], 'loans' => $n($point['before'])]) : '']);
            $next = $points[$i + 1]['start'] ?? null;
            $columns[] = ['label' => $short, 'past' => $past, 'value' => $past ? $n($point['loans']) : '',
                'spoken' => $long . ($parts ? ': ' . implode('; ', $parts) : ''),
                'now' => $past ? round(100 * $point['loans'] / $max, 1) : 0,
                'before' => round(100 * (int) $point['before'] / $max, 1), 'hasbefore' => $point['before'] !== null,
                'mark' => $past && $point['loans'] > 0 && ($written || $point['loans'] === $most),
                'url' => $past && $written && count($points) > 1
                    ? $url($point['start'], $next ?? self::after($library, $unit, $point['start'])) : ''];
        }
        $total = $glance['use']['loans'];
        $rest = '';
        if ($before && !$tocome && ($total || $before['loans'])) {
            $change = $total - $before['loans'];
            $rest = $str($change > 0 ? 'overallmore' : ($change < 0 ? 'overallless' : 'overallequal'), (object) [
                'change' => $n(abs($change)), 'year' => self::unbroken($before['name'])]);
        }
        return ['lead' => $tocome ? $str('overalltocome') : ($total ? $loans($total) : $str('overallnoloans')),
            'rest' => $rest, 'axis' => $unit, 'count' => count($columns), 'columns' => $columns,
            'keys' => $unit === 'month' && $before ? ['now' => $str('overallnow'), 'before' => $before['name']] : null];
    }

    /**
     * The whole name of a piece of the trend: a day, a month or a year.
     *
     * @param \stdClass $library
     * @param string $unit
     * @param array $point
     * @return string
     */
    private static function piece(\stdClass $library, string $unit, array $point): string {
        $name = match ($unit) {
            'day' => zones::date($point['start'], '%A, %d %B', $library),
            'month' => zones::date($point['start'], '%B %Y', $library),
            default => $point['name'],
        };
        return \core_text::strtotitle(\core_text::substr($name, 0, 1)) . \core_text::substr($name, 1);
    }

    /**
     * The sheet to download: every figure in its line, with the same of the year before where it is known.
     *
     * @param \stdClass $library
     * @param array $glance From glance::of().
     * @param int $from
     * @param int $to
     * @return array columns and rows.
     */
    public static function sheet(\stdClass $library, array $glance, int $from, int $to): array {
        $str = skins::speaker($library);
        $day = fn(int $time) => zones::date($time, '%Y-%m-%d', $library);
        $share = fn(?float $number) => $number === null ? '' : format_float($number, 1);
        $collection = $glance['collection'];
        $use = $glance['use'];
        $readers = $glance['readers'];
        $past = $glance['before']['readers'] ?? null;
        $holdings = $str('overallcsvholdings');
        $used = $str('overallcsvuse');
        $rows = [[$str('overallcsvperiod'), $day($from) . ' – ' . $day($to - 1), '', ''],
            [$holdings, $str('overallcsvcopies'), $collection['copies'], ''],
            [$holdings, $str('overallcsvtitles'), $collection['titles'], '']];
        foreach ($collection['kinds'] as $kind => $copies) {
            $rows[] = [$holdings, $str('overallkind' . $kind), $copies, ''];
        }
        array_push(
            $rows,
            [$holdings, $str('overallcsvnever'), $collection['never'], ''],
            [$holdings, $str('overallcsvneverpct'), $share($collection['nevershare']), ''],
            [$used, $str('reportloans'), $use['loans'], $glance['before']['loans'] ?? ''],
            [$used, $str('overallcsvused'), $use['used'], ''],
            [$used, $str('overallcsvusedpct'), $share($use['usedshare']), ''],
            [$used, $str('overallout'), $use['out'], ''],
            [$used, $str('overallcsvoutpct'), $share($use['outshare']), ''],
            [$used, $str('overallreaders'), $readers['came'], $past['came'] ?? ''],
            [$used, $str('overallcsvreadersof'), $readers['of'] ?? '', $past['of'] ?? ''],
            [$used, $str('overallcsvreaderspct'), $share($readers['share']), $share($past['share'] ?? null)],
            [$used, $str('overallperreader'), $share($use['perreader']), ''],
            [$used, $str('overallperhundred'), $share($use['perhundred']), ''],
            [$used, $str('overallroom'), $use['room'], ''],
            [$used, $str('overallnew'), $collection['acquired'], '']
        );
        foreach ($glance['series']['points'] as $point) {
            if ($point['loans'] !== null) {
                $rows[] = [$str('overallcsvtrend'), self::piece($library, $glance['series']['unit'], $point), $point['loans'],
                    $point['before'] ?? ''];
            }
        }
        foreach ($glance['top'] as $book) {
            $rows[] = [$str('reporttoptitle'), $book['label'], $book['value'], ''];
        }
        foreach ($glance['stages'] as $stage) {
            $rows[] = [$str('memoirstages'), $stage['stage'] !== '' ? $stage['stage'] : $str('overallrest'), $stage['loans'], ''];
        }
        foreach ($glance['corners'] as $corner) {
            $rows[] = [$str('memoircorners'), $corner['corner'], $corner['loans'],
                $corner['change'] === null ? '' : $corner['before']];
        }
        return ['columns' => [$str('overallcsvpart'), $str('overallcsvwhat'), $str('overallcsvvalue'), $str('overallcsvbefore')],
            'rows' => $rows];
    }

    /**
     * The end of the last piece of a trend.
     *
     * @param \stdClass $library
     * @param string $unit
     * @param int $start
     * @return int
     */
    private static function after(\stdClass $library, string $unit, int $start): int {
        if ($unit === 'year') {
            return (int) years::around((int) $library->entityid, $start)->timeend;
        }
        return (new \DateTimeImmutable('@' . $start))->setTimezone(zones::of($library))
            ->modify($unit === 'day' ? '+1 day' : 'first day of next month')->setTime(0, 0)->getTimestamp();
    }

    /**
     * The lines of the card of use.
     *
     * @param \Closure $str
     * @param array $glance
     * @param \Closure $report
     * @param \Closure $page
     * @param string[] $span
     * @param bool $classes
     * @return array[]
     */
    private static function lines(
        \Closure $str,
        array $glance,
        \Closure $report,
        \Closure $page,
        array $span,
        bool $classes
    ): array {
        $n = fn(int $number) => screens::number($number);
        $one = fn(float $number) => format_float($number, fmod($number, 1.0) > 0 ? 1 : 0);
        $use = $glance['use'];
        $readers = $glance['readers'];
        $past = $glance['before']['readers']['share'] ?? null;
        return [
            ['label' => $str('overallout'), 'value' => $n($use['out']), 'url' => $use['out'] ? $page('late.php', ['all' => 1]) : '',
                'note' => $use['out'] ? $str('overalloutnote', self::share($use['outshare'])) : ''],
            ['label' => $str('overallreaders'), 'value' => !$readers['of'] ? $n($readers['borrowers']) : $str(
                'overallreadersof',
                (object) ['came' => $n($readers['came']), 'of' => $n($readers['of'])]
            ), 'url' => $classes && $readers['borrowers'] ? $report('loans', $span) : '',
                'note' => !$readers['of'] ? '' : ($past === null ? $str('overallreadersshare', self::share($readers['share']))
                    : $str('overallreadersbefore', (object) ['share' => self::share($readers['share']),
                        'past' => self::share($past), 'year' => $glance['before']['name']]))],
            ['label' => $str('overallperreader'), 'value' => $one($use['perreader']), 'url' => '', 'note' => ''],
            ['label' => $str('overallperhundred'), 'url' => '', 'note' => '', 'value' =>
                ($use['loans'] && !$use['perhundred'] ? '< ' . format_float(0.1, 1) : $one($use['perhundred']))],
            ['label' => $str('overallroom'), 'value' => $n($use['room']), 'url' => '', 'note' => ''],
            ['label' => $str('overallnew'), 'value' => $n($glance['collection']['acquired']), 'url' => '', 'note' => ''],
        ];
    }

    /**
     * The most lent, with their covers.
     *
     * @param \Closure $str
     * @param array[] $top
     * @param \Closure $page
     * @return array[]
     */
    private static function books(\Closure $str, array $top, \Closure $page): array {
        $covers = covers::urls(array_column($top, 'id'));
        return array_map(fn($book) => ['label' => $book['label'],
            'value' => $str($book['value'] === 1 ? 'reportloansone' : 'reportloanscount', screens::number($book['value'])),
            'url' => $page('record.php', ['id' => $book['id']]), 'hascover' => isset($covers[$book['id']]),
            'coverurl' => $covers[$book['id']] ?? '', 'colour' => $book['id'] % 6], $top);
    }

    /**
     * The stages, each against the one most books went to.
     *
     * @param \Closure $str
     * @param array[] $stages
     * @param string $url
     * @return array[]
     */
    private static function stages(\Closure $str, array $stages, string $url): array {
        $max = max(array_merge([1], array_column($stages, 'loans')));
        return array_map(fn($row) => ['label' => $row['stage'] !== '' ? $row['stage'] : $str('overallrest'),
            'value' => screens::number($row['loans']), 'share' => self::share($row['share']),
            'width' => round(100 * $row['loans'] / $max, 1), 'url' => $url], $stages);
    }

    /**
     * The corners, each with what went out from it the year before.
     *
     * @param \Closure $str
     * @param array[] $corners
     * @param bool $before
     * @param \Closure $page
     * @return array[]
     */
    private static function corners(\Closure $str, array $corners, bool $before, \Closure $page): array {
        $max = max(array_merge([1], array_column($corners, 'loans'), array_column($corners, 'before')));
        $rows = [];
        foreach ($corners as $row) {
            $art = screens::art('rincon-' . preg_replace('/[^a-z0-9]/', '', \core_text::strtolower((string) $row['code'])));
            $change = $row['change'];
            $rows[] = ['label' => $row['corner'], 'value' => screens::number($row['loans']),
                'width' => $row['loans'] ? round(100 * $row['loans'] / $max, 1) : 0, 'hasbefore' => $before,
                'before' => round(100 * $row['before'] / $max, 1),
                'change' => $change === null ? '' : ($change === 0 ? $str('overallchangenone') : $str(
                    $change > 0 ? 'overallchangemore' : 'overallchangeless',
                    screens::number(abs($change))
                )),
                'hasart' => (bool) $art, 'arturl' => $art ?: '',
                'url' => $page('search.php', ['corner' => $row['code']])];
        }
        return $rows;
    }
}
