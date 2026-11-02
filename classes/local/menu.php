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
        'readers' => ['readers.php', 'reader.php', 'readerslist.php', 'oneroster.php', 'pair.php', 'match.php',
            'twins.php', 'tutors.php', 'sources.php', 'connector.php'],
        'reports' => ['reports.php', 'memoir.php', 'readings.php', 'late.php', 'activity.php'],
    ];

    /** @var string[] The pages that need classes, or courses, to mean anything. */
    private const ONLY_WITH = ['tutors.php' => 'classes', 'wishes.php' => 'classes', 'readings.php' => 'courses'];

    /**
     * The groups of «More», with what each person may open.
     *
     * @return array[] title => [[page, label, capability]].
     */
    private static function groups(): array {
        $d = 'local/oksigeniaayvurenda:desk';
        $c = 'local/oksigeniaayvurenda:catalogue';
        $m = 'local/oksigeniaayvurenda:manage';
        return [
            'menulending' => [['holds.php', 'menuholds', $d], ['late.php', 'menulate', $d], ['lots.php', 'menulots', $d],
                ['copies.php', 'menucopies', $d]],
            'menucataloguing' => [['add.php', 'menuadd', $c], ['material.php', 'menumaterial', $c],
                ['import.php', 'menuimport', $c], ['free.php', 'menufree', $c], ['labels.php', 'menulabels', $c],
                ['stocktake.php', 'menustocktake', $c], ['weed.php', 'menuweed', $c], ['stacks.php', 'stacksnav', $d],
                ['posters.php', 'menuposters', $c]],
            'menureview' => [['reviews.php', 'menureviews', $d], ['wishes.php', 'menuwishes', $d],
                ['voices.php', 'menuvoices', $d], ['memoir.php', 'menumemoir', $d], ['readings.php', 'menureadings', $d],
                ['activity.php', 'menuactivity', $m]],
            'menusettings' => [['libraries.php', 'navlibraries', $m], ['rules.php', 'menurules', $m],
                ['year.php', 'menuyear', $m], ['tutors.php', 'menututors', $m], ['twins.php', 'menutwins', $m],
                ['sources.php', 'menusources', $m], ['corners.php', 'menucorners', $m],
                ['characters.php', 'menucharacters', $m], ['lti.php', 'menulti', $m]],
            'menuhelp' => [['howto.php', 'menuhowto', $d]],
        ];
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
            // Whoever keeps a library of this centre has its desk.
            foreach (keepers::libraries($userid) as $kept) {
                if ((int) $kept->entityid === (int) $library->entityid) {
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
        foreach (self::groups() as $title => $links) {
            $shown = [];
            foreach ($links as [$page, $label, $capability]) {
                $needs = self::ONLY_WITH[$page] ?? '';
                $entityid = (int) $library->entityid;
                if (
                    ($needs === 'classes' && skins::no_classes($entityid)) ||
                    ($needs === 'courses' && skins::no_courses($entityid))
                ) {
                    continue;
                }
                if (access::can($capability, $library, $userid)) {
                    $shown[] = ['url' => $url($page), 'label' => skins::say($entityid, $label), 'current' => $here === $page];
                    $inmore = $inmore || ($here === $page && !self::in_doors($here));
                }
            }
            if ($shown) {
                $groups[] = ['title' => $str($title), 'links' => $shown];
            }
        }
        if (challenges::tutored((int) $library->entityid, $userid)) {
            array_unshift($groups, ['title' => $str('classnav'), 'links' => [['url' => $url('class.php'),
                'label' => $str('classnav'), 'current' => $here === 'class.php']]]);
        }
        $doors[] = ['key' => 'more', 'url' => '', 'label' => $str('menumore'), 'current' => $inmore, 'groups' => $groups];
        return $doors;
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
