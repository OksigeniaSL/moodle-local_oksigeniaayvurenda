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
 * The plan of each centre, for libraries hosted as a service: its name, how many copies and readers it holds, and what it
 * brings (LTI, connectors, voices, «made with Ayvurenda» at the foot). Only the site's administrator sets it, in the
 * centre's settings; a new centre starts with the site's defaults. With no plan, nothing is limited. Past a limit nothing
 * is removed: no more copies or readers come in, and an import that would not fit is refused whole.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class plans {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var string[] What a plan may bring or not. */
    public const FEATURES = ['lti', 'connectors', 'voices', 'offsite', 'app', 'madewith'];

    /** @var array[] The plans of the hosted service (docs: price tiers), to set with one choice: limits and what they bring. */
    public const PRESETS = [
        'free' => ['copies' => 1000, 'readers' => 150, 'lti' => false, 'connectors' => false, 'voices' => false,
            'offsite' => false, 'app' => false, 'madewith' => true],
        'small' => ['copies' => 5000, 'readers' => 600, 'lti' => false, 'connectors' => false, 'voices' => true,
            'offsite' => true, 'app' => true, 'madewith' => false],
        'medium' => ['copies' => 25000, 'readers' => 2500, 'lti' => true, 'connectors' => true, 'voices' => true,
            'offsite' => true, 'app' => true, 'madewith' => false],
    ];

    /**
     * The site's defaults, which a new centre takes: with nothing set, no limits and everything in.
     *
     * @return array name, copies, readers (0 for no limit) and the features.
     */
    public static function defaults(): array {
        $config = get_config('local_oksigeniaayvurenda');
        return [
            'name' => trim((string) ($config->planname ?? '')),
            'copies' => max(0, (int) ($config->plancopies ?? 0)),
            'readers' => max(0, (int) ($config->planreaders ?? 0)),
            'lti' => (bool) ($config->planlti ?? true),
            'connectors' => (bool) ($config->planconnectors ?? true),
            'voices' => (bool) ($config->planvoices ?? true),
            'offsite' => (bool) ($config->planoffsite ?? true),
            'app' => (bool) ($config->planapp ?? false),
            'madewith' => (bool) ($config->planmadewith ?? false),
        ];
    }

    /**
     * The plan of a centre: its own, or the site's defaults.
     *
     * @param int $entityid
     * @return array
     */
    public static function of(int $entityid): array {
        global $DB;
        $settings = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        return isset($settings['plan']) && is_array($settings['plan']) ? self::clean($settings['plan']) : self::defaults();
    }

    /**
     * Sets the plan of a centre (the caller checks the site's capability).
     *
     * @param int $entityid
     * @param array $plan
     */
    public static function save(int $entityid, array $plan): void {
        global $DB;
        $settings = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        $before = self::clean($settings['plan'] ?? self::defaults());
        $settings['plan'] = self::clean($plan);
        $DB->set_field(self::T . 'entity', 'settings', json_encode($settings), ['id' => $entityid]);
        if ($before != $settings['plan']) {
            \local_oksigeniaayvurenda\event\plan_changed::fire($entityid, null, ['copies' => $settings['plan']['copies'],
                'readers' => $settings['plan']['readers']]);
        }
    }

    /**
     * One of the plans of the service, with its name.
     *
     * @param string $key free, small or medium.
     * @return array|null
     */
    public static function preset(string $key): ?array {
        if (!isset(self::PRESETS[$key])) {
            return null;
        }
        return ['name' => get_string('planpreset' . $key, 'local_oksigeniaayvurenda')] + self::PRESETS[$key];
    }

    /**
     * A new centre takes the site's defaults as its own plan.
     *
     * @param int $entityid
     */
    public static function seed(int $entityid): void {
        self::save($entityid, self::defaults());
    }

    /**
     * What a centre holds now: copies (not withdrawn) and active readers.
     *
     * @param int $entityid
     * @return int[] copies and readers.
     */
    public static function used(int $entityid): array {
        global $DB;
        return [
            'copies' => $DB->count_records_select(
                self::T . 'item',
                "entityid = :entityid AND status <> 'withdrawn'",
                ['entityid' => $entityid]
            ),
            'readers' => $DB->count_records(self::T . 'reader', ['entityid' => $entityid, 'status' => 'active']),
        ];
    }

    /**
     * How many more copies or readers fit in a centre's plan.
     *
     * @param int $entityid
     * @param string $what copies or readers.
     * @return int|null Null with no limit.
     */
    public static function left(int $entityid, string $what): ?int {
        $limit = self::of($entityid)[$what] ?? 0;
        return $limit ? max(0, $limit - self::used($entityid)[$what]) : null;
    }

    /**
     * Stops when more copies or readers would not fit, saying what fits.
     *
     * @param int $entityid
     * @param string $what copies or readers.
     * @param int $more
     * @throws plan_full
     */
    public static function check(int $entityid, string $what, int $more = 1): void {
        $left = self::left($entityid, $what);
        if ($left !== null && $more > $left) {
            throw self::full(self::of($entityid), $what, self::used($entityid)[$what], $more);
        }
    }

    /**
     * Does some work that brings copies or readers, and undoes all of it when the centre ends up past its plan with more
     * than it had: an import that does not fit is refused whole.
     *
     * @param int $entityid
     * @param callable $work
     * @return mixed What the work returns.
     * @throws plan_full When it did not fit.
     */
    public static function within(int $entityid, callable $work) {
        global $DB;
        $plan = self::of($entityid);
        if (!$plan['copies'] && !$plan['readers']) {
            return $work();
        }
        $before = self::used($entityid);
        $transaction = $DB->start_delegated_transaction();
        try {
            $result = $work();
        } catch (\Throwable $e) {
            // Whatever broke, nothing of it stays and the transaction does not stay open for the rest of the page.
            $transaction->rollback($e);
        }
        $after = self::used($entityid);
        foreach (['copies', 'readers'] as $what) {
            if ($plan[$what] && $after[$what] > $plan[$what] && $after[$what] > $before[$what]) {
                $transaction->rollback(self::full($plan, $what, $before[$what], $after[$what] - $before[$what]));
            }
        }
        $transaction->allow_commit();
        return $result;
    }

    /**
     * What is said when more copies or readers do not fit.
     *
     * @param array $plan
     * @param string $what copies or readers.
     * @param int $used What the centre had.
     * @param int $more What came.
     * @return plan_full
     */
    private static function full(array $plan, string $what, int $used, int $more): plan_full {
        $left = max(0, $plan[$what] - $used);
        $a = (object) ['plan' => self::named($plan), 'limit' => self::number($plan[$what]), 'used' => self::number($used),
            'left' => self::number($left), 'more' => self::number($more)];
        return new plan_full(($left ? 'planshort' : 'planfull') . $what, 'local_oksigeniaayvurenda', '', $a);
    }

    /**
     * «The Free plan», or «The centre's plan» when it has no name, to start a sentence.
     *
     * @param array $plan
     * @return string
     */
    private static function named(array $plan): string {
        return $plan['name'] !== '' ? get_string('plannamed', 'local_oksigeniaayvurenda', $plan['name'])
            : get_string('planunnamed', 'local_oksigeniaayvurenda');
    }

    /**
     * Says that a centre's plan does not bring something.
     *
     * @param int $entityid
     * @param string $feature One of FEATURES.
     * @return string
     */
    public static function without(int $entityid, string $feature): string {
        return get_string('planwithout', 'local_oksigeniaayvurenda', self::without_a($entityid, $feature));
    }

    /**
     * Stops what a centre's plan does not bring.
     *
     * @param int $entityid
     * @param string $feature One of FEATURES.
     * @throws \moodle_exception
     */
    public static function require(int $entityid, string $feature): void {
        if (!self::allows($entityid, $feature)) {
            throw new \moodle_exception('planwithout', 'local_oksigeniaayvurenda', '', self::without_a($entityid, $feature));
        }
    }

    /**
     * What a sync says of the readers it left out because they did not fit; nothing when none.
     *
     * @param array $stats What the sync gave, with overlimit.
     * @return string
     */
    public static function left_out(array $stats): string {
        return empty($stats['overlimit']) ? '' : ' ' . get_string(
            'planleftout',
            'local_oksigeniaayvurenda',
            self::number((int) $stats['overlimit'])
        );
    }

    /**
     * The plan and the feature, for the strings.
     *
     * @param int $entityid
     * @param string $feature
     * @return \stdClass
     */
    private static function without_a(int $entityid, string $feature): \stdClass {
        return (object) ['plan' => self::named(self::of($entityid)),
            'feature' => get_string('planfeature' . $feature, 'local_oksigeniaayvurenda')];
    }

    /**
     * Whether a centre's plan brings something.
     *
     * @param int $entityid
     * @param string $feature One of FEATURES.
     * @return bool
     */
    public static function allows(int $entityid, string $feature): bool {
        return (bool) (self::of($entityid)[$feature] ?? true);
    }

    /**
     * Whether someone may enter Oksigenia Aty, the app of the hosted service, by the plans of the centres they belong to: on
     * a shared site, one whose plan brings it is enough. Someone of no centre, or a site that is not shared, is not for the
     * library to say. The official app of Moodle does not ask.
     *
     * @param int $userid
     * @return bool
     */
    public static function app_allowed(int $userid): bool {
        global $DB;
        if (!access::apart()) {
            return true;
        }
        $centres = array_merge(
            $DB->get_fieldset_select(self::T . 'reader', 'DISTINCT entityid', "userid = ? AND status = 'active'", [$userid]),
            access::entities('local/oksigeniaayvurenda:desk', $userid)
        );
        // A grown-up of a family who is neither reader nor staff goes by the centres of their children
        // (int-aty-pixel 3): the app is in the plan of the library they come to.
        if (!$centres && class_exists('\\local_oksigeniafamily\\api')) {
            foreach (\local_oksigeniafamily\api::children_of_user($userid) as $child) {
                $centres = array_merge($centres, $DB->get_fieldset_select(
                    self::T . 'reader',
                    'DISTINCT entityid',
                    "userid = ? AND status = 'active'",
                    [$child]
                ));
            }
        }
        foreach ($centres as $entityid) {
            if (self::allows((int) $entityid, 'app')) {
                return true;
            }
        }
        return !$centres;
    }

    /**
     * What the staff see of their plan: «Plan Free: 812 of 1,000 copies · 140 of 150 readers»; nothing with no plan.
     *
     * @param int $entityid
     * @return string|null
     */
    public static function summary(int $entityid): ?string {
        $plan = self::of($entityid);
        if ($plan['name'] === '' && !$plan['copies'] && !$plan['readers']) {
            return null;
        }
        $used = self::used($entityid);
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        $parts = [];
        foreach (['copies', 'readers'] as $what) {
            $parts[] = $plan[$what] ? $str('planused' . $what, (object) ['used' => self::number($used[$what]),
                'limit' => self::number($plan[$what])]) : $str('planfree' . $what, self::number($used[$what]));
        }
        return $plan['name'] !== '' ? $str('plansummary', (object) ['plan' => $plan['name'], 'parts' => implode(' · ', $parts)])
            : $str('plansummaryunnamed', implode(' · ', $parts));
    }

    /**
     * A plan with only what it may have.
     *
     * @param array $plan
     * @return array
     */
    private static function clean(array $plan): array {
        $clean = ['name' => \core_text::substr(trim((string) ($plan['name'] ?? '')), 0, 60),
            'copies' => max(0, (int) ($plan['copies'] ?? 0)), 'readers' => max(0, (int) ($plan['readers'] ?? 0))];
        foreach (self::FEATURES as $feature) {
            $clean[$feature] = (bool) ($plan[$feature] ?? !in_array($feature, ['madewith', 'app'], true));
        }
        return $clean;
    }

    /**
     * A number with the thousands as the language writes them.
     *
     * @param int $number
     * @return string
     */
    private static function number(int $number): string {
        return number_format($number, 0, '', get_string('thousandssep', 'langconfig'));
    }
}
