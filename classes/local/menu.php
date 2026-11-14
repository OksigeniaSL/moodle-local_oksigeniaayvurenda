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
 * The header of the library's pages.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * The same few doors on every page, so nobody has to look for where they are: for the staff, the desk, the catalogue,
 * the readers, the reports and «More» (a sheet with the rest, in groups); for a reader, the catalogue, the shelves and
 * their card. The page you are in is always marked. What only one page needs (going back to a course, its guided
 * help) goes in a small row under the header.
 */
final class menu {
    /** @var array<string, string[]> The pages of each door, to mark where you are. */
    private const SECTIONS = [
        'desk' => ['desk.php', 'phone.php', 'bank.php', 'roll.php', 'contract.php'],
        'catalogue' => ['index.php', 'search.php', 'record.php', 'digital.php', 'read.php', 'ayvurande.php', 'voice.php',
            'public.php'],
        'stacks' => ['stacks.php', 'visit.php', 'shelf.php'],
        'card' => ['card.php'],
        'class' => ['class.php'],
        'agenda' => ['agenda.php'],
        'readers' => ['readers.php', 'reader.php', 'history.php', 'cardprint.php', 'print.php', 'readerslist.php', 'oneroster.php',
            'pair.php', 'match.php', 'twins.php', 'tutors.php', 'sources.php', 'connector.php'],
        'reports' => ['reports.php', 'memoir.php', 'readings.php', 'late.php', 'activity.php', 'sanctions.php'],
    ];

    /** @var string[] The pages that need classes, or courses, to mean anything. */
    private const ONLY_WITH = ['tutors.php' => 'classes', 'wishes.php' => 'classes', 'readings.php' => 'courses',
        'sanctions.php' => 'sanctions'];

    /**
     * The groups of «More», with what each person may open: what waits first, what is used every day, what once a year,
     * and the five steps that set a library up together, as «To open your library» names them («How do I…?» went up to
     * the head of the panel). The order never changes with what there is.
     *
     * @return array[] key => [title, [[page, label, capability]]]; «centre», «team» and «open» are not pages.
     */
    private static function groups(): array {
        $d = 'local/oksigeniaayvurenda:desk';
        $c = 'local/oksigeniaayvurenda:catalogue';
        $m = 'local/oksigeniaayvurenda:manage';
        return [
            'waiting' => ['menuattend', [['reviews.php', 'menureviews', $d], ['wishes.php', 'menuwishes', $d],
                ['voices.php', 'menuvoices', $d], ['readings.php', 'menureadings', $d]]],
            'lending' => ['menulending', [['holds.php', 'menuholds', $d], ['late.php', 'menulate', $d],
                ['sanctions.php', 'menusanctions', $d], ['selfloan.php', 'menuselfloan', $d], ['ill.php', 'menuill', $d],
                ['lots.php', 'menulots', $d], ['formats.php', 'menuformats', $d]]],
            'readers' => ['menupeople', [['cardprint.php', 'menucards', $d], ['twins.php', 'menutwins', $m],
                ['tutors.php', 'menututors', $m]]],
            'catalogue' => ['menucataloguing', [['add.php', 'menuadd', $c], ['catalogued.php', 'menucataloged', $c],
                ['subjects.php', 'menusubjects', $c], ['duplicates.php', 'menuduplicates', $c], ['free.php', 'menufree', $c],
                ['material.php', 'menumaterial', $c], ['purchases.php', 'menupurchases', $c]]],
            'shelves' => ['menushelves', [['stacks.php', 'menustacks', $d], ['labels.php', 'menulabels', $c],
                ['posters.php', 'menuposters', $c]]],
            'reading' => ['menureading', [['quests.php', 'menuquests', $m], ['agenda.php', 'agendatitle', $d]]],
            'year' => ['menuyeargroup', [['stocktake.php', 'menustocktake', $c], ['weed.php', 'menuweed', $c],
                ['memoir.php', 'menumemoir', $d], ['year.php', 'menuyear', $m], ['activity.php', 'menuactivity', $m]]],
            'setup' => ['menusetup', [['import.php', 'menuimport', $c], ['sources.php', 'menusources', $m],
                ['rules.php', 'menurules', $m], ['team', 'menuteam', $m], ['open.php', 'menuopen', $m]]],
            'settings' => ['menusettings', [['howitworks.php', 'hiwtitle', $m], ['functions.php', 'functionsheading', $d],
                ['libraries.php', 'navlibraries', $m],
                ['corners.php', 'menucorners', $m], ['characters.php', 'menucharacters', $m], ['copies.php', 'menucopies', $m],
                ['lti.php', 'menulti', $m]]],
        ];
    }

    /**
     * The library whose doors someone sees on a page that is not of any library: the first one they work in.
     *
     * @param int $userid
     * @return \stdClass|null
     */
    public static function home(int $userid): ?\stdClass {
        foreach (catalogue::visible($userid) as $library) {
            if (access::can('local/oksigeniaayvurenda:desk', $library, $userid)) {
                return $library;
            }
        }
        return null;
    }

    /**
     * The page being shown, by its file.
     *
     * @return string
     */
    public static function here(): string {
        global $PAGE;
        return $PAGE->has_set_url() ? basename($PAGE->url->get_path()) : '';
    }

    /**
     * The doors of the header for someone in a library.
     *
     * @param \stdClass $library
     * @param int $userid
     * @return array[] key, url, label, current; and, for «More», groups (title, links: url, label, current).
     */
    public static function doors(\stdClass $library, int $userid): array {
        $url = fn(string $page) => (new \moodle_url('/local/oksigeniaayvurenda/' . $page, ['library' => $library->slug]))
            ->out(false);
        $here = self::here();
        $str = fn(string $key) => get_string($key, 'local_oksigeniaayvurenda');
        $door = fn(string $key, string $page, string $label) => ['key' => $key, 'url' => $url($page),
            'label' => $str($label), 'current' => in_array($here, self::SECTIONS[$key] ?? [], true)];
        $staff = access::can('local/oksigeniaayvurenda:desk', $library, $userid);
        if (!$staff) {
            $doors = [$door('catalogue', 'index.php', 'deskcatalogue'), $door('stacks', 'stacks.php', 'stacksnav')];
            if ($userid && \core\user::is_real_user($userid) && self::reader($library, $userid)) {
                $doors[] = $door('card', 'card.php', 'menucard');
            }
            if (challenges::tutored((int) $library->entityid, $userid)) {
                $doors[] = $door('class', 'class.php', 'classnav');
            }
            // The agenda, when something is coming.
            if (agenda::between((int) $library->entityid, time(), time() + 60 * DAYSECS)) {
                $doors[] = $door('agenda', 'agenda.php', 'agendatitle');
            }
            // Whoever keeps a library of this centre has its desk.
            foreach (keepers::libraries($userid) as $kept) {
                if ((int) $kept->entityid === (int) $library->entityid) {
                    // And what it has out, and who has it.
                    array_unshift($doors, ['key' => 'reports', 'url' => (new \moodle_url(
                        '/local/oksigeniaayvurenda/late.php',
                        ['library' => $kept->slug, 'all' => 1]
                    ))->out(false), 'label' => $str('latetaball'), 'current' => $here === 'late.php']);
                    array_unshift($doors, ['key' => 'desk', 'url' => (new \moodle_url(
                        '/local/oksigeniaayvurenda/desk.php',
                        ['library' => $kept->slug]
                    ))->out(false), 'label' => $str('desktitle'),
                        'current' => in_array($here, self::SECTIONS['desk'], true)]);
                    break;
                }
            }
            return $doors;
        }
        $doors = [$door('desk', 'desk.php', 'desktitle'), $door('catalogue', 'index.php', 'deskcatalogue'),
            $door('readers', 'readers.php', 'readers'), $door('reports', 'reports.php', 'reportsnav')];
        $groups = [];
        $inmore = false;
        // What waits for someone to look at it, as a number on its link and on «More», as phones do.
        $waiting = self::waiting($library, $userid);
        $total = 0;
        foreach (self::groups() as $key => [$title, $links]) {
            $shown = [];
            foreach ($links as [$page, $label, $capability]) {
                $needs = self::ONLY_WITH[$page] ?? '';
                $entityid = (int) $library->entityid;
                if (
                    ($needs === 'classes' && skins::no_classes($entityid)) ||
                    ($needs === 'courses' && skins::no_courses($entityid)) ||
                    ($needs === 'sanctions' && !sanctions::on($entityid)) ||
                    // Opening is a step while the centre is closed; closing it again is in its settings.
                    ($page === 'open.php' && !access::closed($entityid))
                ) {
                    continue;
                }
                if (access::can($capability, $library, $userid)) {
                    $count = $waiting[$page] ?? 0;
                    $total += $count;
                    // The centre's own settings live in a window of «Libraries»: the link opens it.
                    $link = $page === 'centre' ? $url('libraries.php') . '#ayv-entity-' . $entityid
                        : ($page === 'team' ? opening::team_url($library) : $url($page));
                    $shown[] = ['url' => $link, 'label' => skins::say($entityid, $label), 'current' => $here === $page,
                        'count' => $count];
                    $inmore = $inmore || ($here === $page && !self::in_doors($here));
                }
            }
            if ($shown) {
                $groups[] = ['key' => $key, 'title' => skins::say((int) $library->entityid, $title), 'links' => $shown];
            }
        }
        if (challenges::tutored((int) $library->entityid, $userid)) {
            array_unshift($groups, ['key' => 'class', 'title' => $str('classnav'), 'links' => [['url' => $url('class.php'),
                'label' => $str('classnav'), 'current' => $here === 'class.php']]]);
        }
        // The second factor of their account, where the site is only a library.
        $security = onlylibrary::security_url();
        if ($security) {
            $groups[] = ['key' => 'account', 'title' => $str('menuaccount'), 'links' => [['url' => $security,
                'label' => $str('menusecurity'),
                'current' => false]]];
        }
        $doors[] = ['key' => 'more', 'url' => '', 'label' => $str('menumore'), 'current' => $inmore, 'groups' => $groups,
            'count' => $total, 'howto' => $url('howto.php'), 'library' => $library];
        return $doors;
    }

    /**
     * What waits for the staff of a library to look at it: reviews and voices to approve, books the classes asked for,
     * readers who asked for another format or to buy a book, stopped self-loan posts and loans from other libraries that
     * are late.
     *
     * @param \stdClass $library
     * @param int $userid
     * @return int[] page => how many.
     */
    public static function waiting(\stdClass $library, int $userid): array {
        $entityid = (int) $library->entityid;
        // A practice library is a rehearsal: what its guided help leaves waits for nobody.
        if (practice::is($entityid)) {
            return [];
        }
        return array_filter([
            'reviews.php' => reviews::waiting($entityid),
            'wishes.php' => wishes::waiting($entityid),
            'voices.php' => count(voices::pending($library, $userid)),
            'formats.php' => formats::waiting($library),
            'selfloan.php' => selfloan::stopped($library),
            'ill.php' => ill::overdue($library),
            'purchases.php' => purchases::counts($library)['asked'] ?? 0,
        ]);
    }

    /**
     * The pages that open a door of their own.
     *
     * @param string $page
     * @return bool
     */
    private static function in_doors(string $page): bool {
        foreach (['desk', 'catalogue', 'readers', 'reports'] as $key) {
            if (in_array($page, self::SECTIONS[$key], true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether someone is an active reader of the library's centre.
     *
     * @param \stdClass $library
     * @param int $userid
     * @return bool
     */
    private static function reader(\stdClass $library, int $userid): bool {
        global $DB;
        return $DB->record_exists('local_oksigeniaayvurenda_reader', ['entityid' => $library->entityid, 'userid' => $userid,
            'status' => 'active']);
    }

    /**
     * Every address the header already has, to leave out of a page's own row.
     *
     * @param array $doors
     * @return string[] Page files.
     */
    public static function covered(array $doors): array {
        $pages = [];
        foreach ($doors as $door) {
            if ($door['url'] !== '') {
                $pages[] = basename(parse_url($door['url'], PHP_URL_PATH));
            }
            foreach ($door['groups'] ?? [] as $group) {
                foreach ($group['links'] as $link) {
                    $pages[] = basename(parse_url($link['url'], PHP_URL_PATH));
                }
            }
        }
        return array_values(array_unique(array_merge($pages, ['libraries.php'])));
    }
}
