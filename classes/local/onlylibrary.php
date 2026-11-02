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
 * A site that is only a library: whoever comes finds the library, and no learning platform around it. Whoever runs the site
 * goes on reaching everything.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class onlylibrary {
    /** @var string[] The pages of the platform that have no place in such a site: they lead to the library. */
    public const AWAY = ['site-index', 'my-index', 'my-courses', 'course-index', 'course-index-category', 'course-search'];

    /** @var int How many open libraries the entry shows. */
    private const OPEN = 12;

    /**
     * Whether this site is only a library.
     *
     * @return bool
     */
    public static function on(): bool {
        return (bool) get_config('local_oksigeniaayvurenda', 'libraryonly');
    }

    /**
     * Whether the person may see the platform behind: who runs the site.
     *
     * @return bool
     */
    public static function runs_the_site(): bool {
        return isloggedin() && has_capability('moodle/site:config', \context_system::instance());
    }

    /**
     * The address of the entry.
     *
     * @return \moodle_url
     */
    public static function entry(): \moodle_url {
        return new \moodle_url('/local/oksigeniaayvurenda/entrar.php');
    }

    /**
     * Where a page of the platform leads instead, if it does.
     *
     * @param string $pagetype
     * @return \moodle_url|null
     */
    public static function instead_of(string $pagetype): ?\moodle_url {
        if (!self::on() || !in_array($pagetype, self::AWAY, true) || self::runs_the_site()) {
            return null;
        }
        return isloggedin() && !isguestuser() ? new \moodle_url('/local/oksigeniaayvurenda/index.php') : self::entry();
    }

    /**
     * The way out at the end of the header, or null when it is the usual one.
     *
     * @return array|null url and label.
     */
    public static function way_out(): ?array {
        if (!self::on() || self::runs_the_site()) {
            return null;
        }
        if (isloggedin() && !isguestuser()) {
            return ['url' => (new \moodle_url('/login/logout.php', ['sesskey' => sesskey()]))->out(false),
                'label' => get_string('logout')];
        }
        return ['url' => self::entry()->out(false), 'label' => get_string('entrargo', 'local_oksigeniaayvurenda')];
    }

    /**
     * The library a person was on their way to, if it is open to everyone: only then may its name be said to anyone.
     *
     * @param string $slug The one asked for, if any.
     * @param string $wantsurl Where the session was going.
     * @return \stdClass|null
     */
    public static function heading_for(string $slug, string $wantsurl): ?\stdClass {
        if ($slug === '' && preg_match('/[?&]library=([a-z0-9-]+)/', $wantsurl, $found)) {
            $slug = $found[1];
        }
        if ($slug === '' || str_starts_with($slug, practice::SLUG)) {
            return null;
        }
        $library = catalogue::library($slug);
        return $library && window::settings($library)['open'] ? $library : null;
    }

    /**
     * The libraries that ask to be found: their window open and their «let search engines find it» on.
     *
     * @return array[] name, motto, colour, url.
     */
    public static function open_libraries(): array {
        $open = [];
        foreach (catalogue::libraries() as $library) {
            $window = window::settings($library);
            if (!$window['open'] || !$window['index']) {
                continue;
            }
            $look = libraries::look($library);
            $open[] = ['name' => format_string($library->name, true, ['escape' => false]), 'motto' => $look['motto'],
                'colour' => $look['colour'],
                'url' => (new \moodle_url('/local/oksigeniaayvurenda/public.php', ['library' => $library->slug]))->out(false)];
            if (count($open) >= self::OPEN) {
                break;
            }
        }
        return $open;
    }
}
