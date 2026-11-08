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
 * The page around the library's screens.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * The library's screens are a page of their own, full screen and with the library's look, not Moodle's: a header with
 * the emblem, the name of the library and the search, the screen, and a way back to the site.
 */
final class shell {
    /** @var string|null The nonce of this page's own scripts. */
    private static ?string $nonce = null;

    /**
     * Lets in only who may do what a page does. Someone not logged in, or in as a guest, goes to log in and comes back;
     * someone logged in without the capability gets a page of the library saying so, with the way back, instead of
     * an error.
     *
     * @param string $capability
     * @param \stdClass|null $library The library of the page: the capability is checked in its centre; with none, in any.
     */
    public static function require(string $capability, ?\stdClass $library = null): void {
        if (access::can($capability, $library)) {
            return;
        }
        self::login_first();
        // A library getting ready: whoever is not its staff hears only that it opens soon.
        if ($library && $capability === 'local/oksigeniaayvurenda:view' && access::closed((int) $library->entityid)) {
            http_response_code(403);
            self::refuse_page(
                get_string('closedsoon', 'local_oksigeniaayvurenda'),
                get_string('closedsoonhint', 'local_oksigeniaayvurenda'),
                null,
                '',
                '',
                onlylibrary::way_out() ? [onlylibrary::way_out()['url'], onlylibrary::way_out()['label']]
                    : [(new \moodle_url('/my/'))->out(false), get_string('myhome')]
            );
        }
        $name = get_string(str_replace('local/', '', $capability), 'local_oksigeniaayvurenda');
        // Whoever keeps this library hears what they can do here, not to ask someone for a permission.
        if ($library && access::keeps($library)) {
            self::refuse(get_string('keeperonlyhint', 'local_oksigeniaayvurenda'), $library);
        }
        // A library of a centre that is not theirs is not even named.
        $theirs = $library && access::can('local/oksigeniaayvurenda:view', $library) ? $library : null;
        self::refuse(get_string('staffonlyhint', 'local_oksigeniaayvurenda', $name), $theirs);
    }

    /**
     * Someone not logged in, or in as a guest, goes to log in and comes back here.
     */
    public static function login_first(): void {
        global $SESSION;
        if (!isloggedin() || isguestuser()) {
            $SESSION->wantsurl = qualified_me();
            redirect(get_login_url());
        }
    }

    /**
     * Refuses a page that has no business in a practice library.
     *
     * @param \stdClass|null $library
     */
    public static function no_practice(?\stdClass $library): void {
        if ($library && practice::is((int) $library->entityid)) {
            self::refuse(get_string('practicenothere', 'local_oksigeniaayvurenda'), $library);
        }
    }

    /**
     * A page that says no, with why.
     *
     * @param string $why
     * @param \stdClass|null $library
     * @param string|null $title
     */
    public static function refuse(string $why, ?\stdClass $library = null, ?string $title = null): void {
        http_response_code(403);
        self::refuse_page($title ?? get_string('staffonly', 'local_oksigeniaayvurenda'), $why, $library);
    }

    /**
     * The page that says why someone does not go on, with the way back.
     *
     * @param string $title
     * @param string $why
     * @param \stdClass|null $library
     * @param string $figure Gaudí's drawing of the memory in a pose, or ''.
     * @param string $puppet Its puppet for that pose (JSON), or ''.
     * @param array|null $back Another way back than the library's home: its address and its words.
     * @param string $line What the memory says, under the title.
     */
    private static function refuse_page(
        string $title,
        string $why,
        ?\stdClass $library,
        string $figure = '',
        string $puppet = '',
        ?array $back = null,
        string $line = ''
    ): void {
        global $PAGE;
        // It can come before the page set its own context: every page of the library is on the site's.
        $PAGE->set_context(\context_system::instance());
        $home = $back[0] ??
            (new \moodle_url('/local/oksigeniaayvurenda/index.php', $library ? ['library' => $library->slug] : []))->out(false);
        self::output(
            $title,
            ($figure !== '' ? '<div class="ayv-posed"><span class="ayv-figure"><img class="ayv-posed-figure" src="' . s($figure) .
            '" alt="" width="512" height="512"' . ($puppet !== '' ? ' data-puppet="' . s($puppet) . '"' : '') .
            '></span><div>' : '') .
            '<h1 class="ayv-results-title">' . s($title) . '</h1>' . ($line !== '' ? '<p class="ayv-posed-line">' . s($line) .
            '</p>' : '') . '<p>' . s($why) . '</p><p><a class="ayv-button" href="' .
            s($home) . '">' . s($back[1] ?? get_string('staffonlyback', 'local_oksigeniaayvurenda')) . '</a></p>' .
            ($figure !== '' ? '</div></div>' : ''),
            $library,
            '',
            ['search' => false]
        );
        die;
    }

    /**
     * A page of the library that says something is not here (an address of another centre, or of something gone),
     * with the way back, and ends there.
     *
     * @param string $why
     * @param \stdClass|null $library
     * @param array|null $back Another way back than the library's home: its address and its words.
     * @param string $key The memory's line: notheresaid, or one of its own (a shared book no longer here).
     */
    public static function missing(
        string $why,
        ?\stdClass $library = null,
        ?array $back = null,
        string $key = 'notheresaid'
    ): void {
        http_response_code(404);
        $memory = $library ? (characters::of((int) $library->entityid)['memory'] ?? null) : null;
        $alive = $memory && $memory['enabled'] && empty($memory['own']);
        $step = $alive ? characters::step((int) $library->entityid) : '';
        $figure = $alive ? characters::figure('memory', 'senala', $step) : null;
        self::refuse_page(
            get_string('notherehead', 'local_oksigeniaayvurenda'),
            $why,
            $library,
            (string) $figure,
            $alive ? characters::puppet('memory', 'senala', $step) : '',
            $back,
            // The memory's line in the words of whoever looks: a gap is said one way to a child, another to a grown-up.
            $alive ? (string) (characters::say((int) $library->entityid, 'memory', $key, [], 'senala')['text'] ?? '') : ''
        );
    }

    /**
     * The site's line for every page of the library, if whoever runs the site wrote one (setting «sitenotice»).
     *
     * @return string HTML, or nothing.
     */
    public static function site_notice(): string {
        $notice = trim((string) get_config('local_oksigeniaayvurenda', 'sitenotice'));
        if ($notice === '') {
            return '';
        }
        return '<p class="ayv-sitenotice" role="note">' . s($notice) . '</p>';
    }

    /**
     * Sends a whole page.
     *
     * @param string $title
     * @param string $main The HTML of the screen.
     * @param \stdClass|null $library
     * @param string $query What is in the search box.
     * @param array $options search (false to leave the search out), scripts (more scripts of app/), config (more data
     *     for the scripts), nav (links in the header: a list of url and label), index (true to let search engines list
     *     the page), feed (the address of an OPDS catalogue to announce), share (title, description, image and url
     *     for the preview a link shows where it is shared: messaging apps, social networks, a school's website), paper
     *     (letter for a page printed on US Letter).
     */
    public static function output(
        string $title,
        string $main,
        ?\stdClass $library = null,
        string $query = '',
        array $options = []
    ): void {
        global $CFG, $SITE;
        $version = (int) get_config('local_oksigeniaayvurenda', 'version');
        $base = $CFG->wwwroot . '/local/oksigeniaayvurenda/';
        $app = $base . 'app/';
        $lang = str_replace('_', '-', current_language());
        if (!empty($options['bare'])) {
            self::bare($title, $main, $library, $options, $app, $version, $lang);
            return;
        }
        global $OUTPUT;
        $str = fn(string $key, $a = null) => s(get_string($key, 'local_oksigeniaayvurenda', $a));
        $home = $library ? $base . 'index.php?library=' . rawurlencode($library->slug) : $base . 'index.php';
        // Someone without an account goes home to the library's door, when it has its window open.
        $outsider = !isloggedin() || isguestuser();
        if ($library && $outsider && window::settings($library)['open']) {
            $home = $base . 'public.php?library=' . rawurlencode($library->slug);
        }
        $search = '';
        $lens = '';
        // Without an account, the search of a catalogue shown to anyone is the window's; with none shown, there is none.
        $searchpage = $outsider && $library ? (window::catalogue_open($library) ? 'public.php' : '') : 'search.php';
        if ($library && $searchpage !== '' && ($options['search'] ?? true)) {
            $search = '<form class="ayv-search" role="search" action="' . $base . $searchpage . '" method="get">
    <input type="hidden" name="library" value="' . s($library->slug) . '">
    <label class="ayv-sr" for="ayv-q">' . $str('searchlabel') . '</label>
    <input id="ayv-q" name="q" type="search" value="' . s($query) . '" placeholder="' . $str('searchplaceholder') . '"
        autocomplete="off" enterkeyhint="search" maxlength="200">
    <a class="ayv-search-clear" href="' . s($home) . '" aria-label="' .
                $str('searchclear') . '"' . ($query === '' ? ' hidden' : '') . '>×</a>
    <button type="submit">' . $str('searchbutton') . '</button>
    <button type="button" class="ayv-search-close" aria-label="' . $str('searchclose') . '">×</button>
</form>';
        }
        // The magnifier of a phone, always in the same place: where the page has its own search (the home), it goes there.
        if ($library && $searchpage !== '' && (($options['search'] ?? true) || !empty($options['lens']))) {
            $lens = '<button type="button" class="ayv-search-toggle" aria-expanded="false" aria-controls="ayv-q">'
                . $OUTPUT->render_from_template('local_oksigeniaayvurenda/icon_search', [])
                . '<span class="ayv-sr">' . $str('searchlabel') . '</span></button>';
        }
        $config = json_encode(($options['config'] ?? []) + [
            'library' => $library ? $library->slug : '',
            'searchurl' => $base . 'search.php',
            'homeurl' => $home,
            // The words of the file button, in the library's language and not the browser's.
            'filechoose' => get_string('filechoose', 'local_oksigeniaayvurenda'),
            'filenone' => get_string('filenone', 'local_oksigeniaayvurenda'),
            'dateday' => get_string('dateday', 'local_oksigeniaayvurenda'),
            'datemonth' => get_string('datemonth', 'local_oksigeniaayvurenda'),
            'dateyear' => get_string('dateyear', 'local_oksigeniaayvurenda'),
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
        $scripts = '';
        if (str_contains($main, 'data-puppet=')) {
            $options['scripts'] = array_merge($options['scripts'] ?? [], ['puppet', 'characters']);
        }
        foreach ($options['scripts'] ?? [] as $script) {
            $scripts .= '<script src="' . $app . $script . '.js?v=' . $version . '"></script>' . "\n";
        }
        global $OUTPUT, $USER;
        // The same doors on every page (menu::doors), the one you are in marked; then the way back to Moodle.
        // On a page of no library (the list of them), the menu of the one this person works in: it never goes away.
        $menufor = $library ?: menu::home((int) ($USER->id ?? 0));
        $doors = $menufor ? menu::doors($menufor, (int) ($USER->id ?? 0)) : [];
        if (!$menufor && access::can('local/oksigeniaayvurenda:manage', null)) {
            $doors[] = ['key' => 'libraries', 'url' => $base . 'libraries.php', 'label' => $str('navlibraries'),
                'current' => menu::here() === 'libraries.php'];
        }
        if ($library && !access::can('local/oksigeniaayvurenda:desk', $library) && count(catalogue::visible()) > 1) {
            $doors[] = ['key' => 'libraries', 'url' => $base . 'index.php', 'label' => $str('navlibraries'), 'current' => false];
        }
        $nav = '';
        $sheet = '';
        foreach ($doors as $door) {
            $on = $door['current'] ? ' ayv-nav-on' : '';
            if ($door['key'] === 'more') {
                $badge = !empty($door['count']) ? ' <span class="ayv-badge" aria-hidden="true">' . (int) $door['count']
                    . '</span><span class="ayv-sr">' . $str('menuwaiting', (int) $door['count']) . '</span>' : '';
                $nav .= '<button type="button" class="ayv-nav ayv-nav-more' . $on . ($badge ? ' ayv-nav-waiting' : '')
                    . '" data-ayv-open="ayv-more" aria-haspopup="dialog">' . s($door['label']) . $badge . '</button>';
                $parts = '';
                foreach ($door['groups'] as $group) {
                    $items = '';
                    foreach ($group['links'] as $link) {
                        $items .= '<li><a class="ayv-more-link" href="' . s($link['url']) . '"'
                            . ($link['current'] ? ' aria-current="page"' : '') . '>' . s($link['label'])
                            . (!empty($link['count']) ? ' <span class="ayv-badge" aria-hidden="true">' . (int) $link['count']
                            . '</span><span class="ayv-sr">' . $str('menuwaiting', (int) $link['count']) . '</span>' : '')
                            . '</a></li>';
                    }
                    $parts .= '<section class="ayv-more-group"><h4>' . s($group['title']) . '</h4><ul>' . $items
                        . '</ul></section>';
                }
                $sheet = '<dialog class="ayv-modal ayv-more" id="ayv-more" aria-labelledby="ayv-more-title">'
                    . '<div class="ayv-modal-head"><h3 id="ayv-more-title" class="ayv-section-title">' . s($door['label'])
                    . '</h3><form method="dialog">'
                    . '<button class="ayv-modal-close" aria-label="' . $str('librariesclose') . '">'
                    . $OUTPUT->render_from_template('local_oksigeniaayvurenda/icon_x', []) . '</button></form></div>'
                    . '<div class="ayv-more-groups">' . $parts . '</div></dialog>';
                continue;
            }
            $nav .= '<a class="ayv-nav' . $on . '" href="' . s($door['url']) . '"'
                . ($door['current'] ? ' aria-current="page"' : '') . '>' . s($door['label']) . '</a>';
        }
        // The way back to Moodle, always at the end and in sight, but quiet.
        $mine = isloggedin() && !isguestuser();
        $out = onlylibrary::way_out();
        $nav .= '<a class="ayv-nav-out" href="' . s($out ? $out['url'] : $CFG->wwwroot . ($mine ? '/my/' : '/')) . '">'
            . ($out ? s($out['label']) : ($mine ? s(get_string('myhome'))
                : $str('backto', format_string($SITE->shortname, true, ['escape' => false]))))
            . ' <span aria-hidden="true">→</span></a>';
        // On a phone, a reader sees four tabs: the first three doors and «More», a sheet from below with the rest and
        // the way out (Gaudí's mock-up of 7 November 2026). The staff keep their own «More».
        $readermore = !in_array('more', array_column($doors, 'key'), true);
        if ($readermore) {
            $rest = '';
            foreach (array_slice($doors, 3) as $door) {
                $rest .= '<li><a class="ayv-more-link" href="' . s($door['url']) . '"'
                    . ($door['current'] ? ' aria-current="page"' : '') . '>' . s($door['label']) . '</a></li>';
            }
            $rest .= '<li><a class="ayv-more-link" href="' . s($out ? $out['url'] : $CFG->wwwroot . ($mine ? '/my/' : '/'))
                . '">' . ($out ? s($out['label']) : ($mine ? s(get_string('myhome'))
                : $str('backto', format_string($SITE->shortname, true, ['escape' => false])))) . '</a></li>';
            $nav .= '<button type="button" class="ayv-nav ayv-nav-phonemore" data-ayv-open="ayv-phonemore" aria-haspopup="dialog">'
                . $str('menumore') . '</button>';
            $sheet .= '<dialog class="ayv-modal ayv-phonemore" id="ayv-phonemore" aria-label="' . $str('menumore') . '">'
                . '<ul class="ayv-phonemore-list">' . $rest . '</ul></dialog>';
        }
        $nav = '<nav class="ayv-navs' . ($readermore ? ' ayv-navs-reader' : '') . '" aria-label="' . $str('menulabel') . '">'
            . $nav . '</nav>';
        // An administrator who logged in as someone else sees it on every page, as in the rest of Moodle.
        $as = '';
        if (\core\session\manager::is_loggedinas()) {
            global $USER;
            $as = '<p class="ayv-loggedinas">' . s(get_string('loggedinas', 'moodle', fullname($USER))) . ' <a href="'
                . s((new \moodle_url('/login/logout.php', ['sesskey' => sesskey()]))->out(false)) . '">'
                . s(get_string('logout')) . '</a></p>';
        }
        $as .= self::site_notice();
        // A practice library says what it is: everything may be broken here, and it goes the next night (jue-humor H44).
        if ($library && practice::is((int) $library->entityid)) {
            $as .= '<p class="ayv-sitenotice ayv-practice-said" role="note">'
                . s(get_string('practicesaid', 'local_oksigeniaayvurenda')) . '</p>';
        }
        // What only this page needs (back to a course, its guided help), in a small row, without what the header has.
        // Inside a guided help's frame, none: a help within the help, or a way out of the screen it explains.
        $links = optional_param('help', 0, PARAM_BOOL) ? [] : ($options['nav'] ?? []);
        if (isset($links['url'])) {
            $links = [$links];
        }
        $covered = menu::covered($doors);
        $row = '';
        $how = '';
        foreach ($links as $link) {
            $page = basename((string) parse_url($link['url'], PHP_URL_PATH));
            $inside = str_contains($link['url'], '/local/oksigeniaayvurenda/');
            // The guided help of the page is a button that says what it does, first: a plain «Help» went unseen.
            if ($inside && $page === 'help.php') {
                $how = '<a class="ayv-button ayv-howbutton" href="' . s($link['url']) . '">'
                    . $OUTPUT->render_from_template('local_oksigeniaayvurenda/icon_play', []) . '<span>'
                    . s(get_string('helpnav', 'local_oksigeniaayvurenda')) . '</span></a>';
                continue;
            }
            if ($inside && in_array($page, $covered, true)) {
                continue;
            }
            $row .= '<a class="ayv-subnav-link" href="' . s($link['url']) . '">' . s($link['label']) . '</a>';
        }
        $row = $how . $row;
        // The sheet of «More» goes in the page, not in the header (a dialog inside the header is not centred).
        $main = $sheet . ($row !== '' ? '<nav class="ayv-subnav" aria-label="' . $str('menuthispage') . '">' . $row . '</nav>' : '')
            . $main;
        $name = $library ? format_string($library->name, true, ['escape' => false]) : (onlylibrary::on()
            ? format_string($GLOBALS['SITE']->fullname, true, ['escape' => false])
            : get_string('pluginname', 'local_oksigeniaayvurenda'));
        @header('Content-Type: text/html; charset=utf-8');
        self::frame_header();
        self::guard();
        $howto = self::howto_box($library, $base);
        // The skin of the library's centre: what changes in the look hangs from its class.
        $skin = skins::of($library ? (int) $library->entityid : 0);
        // With the system's font there is no font to fetch.
        $sober = in_array($skin, ['university', 'company'], true);
        echo '<!DOCTYPE html>
<html lang="' . s($lang) . '"' . self::colour_mode() . '>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0f3a33">
<meta name="robots" content="' . (!empty($options['index']) ? 'index, follow' : 'noindex') . '">' . (!empty($options['feed'])
        ? '
<link rel="alternate" type="' . opds::TYPE . '" href="' . s($options['feed']) . '">' : '') .
            self::share($options['share'] ?? null) . '
<title>' . s($title) . ' · ' . s($name) . '</title>
<link rel="icon" href="' . $app . 'favicon.ico" sizes="16x16 32x32 48x48">
<link rel="icon" href="' . $app . 'emblema-pequeno.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="' . $app . 'apple-touch-icon.png">
' . ($sober ? '' : '<link rel="preload" href="' . $app . 'fonts/andika-400.woff2" as="font" type="font/woff2"
    crossorigin>') . '
<link rel="stylesheet" href="' . $app . 'ayvurenda.css?v=' . $version . '">
<link rel="stylesheet" href="' . $app . 'ayvurenda-d.css?v=' . $version . '">
<link rel="stylesheet" href="' . $app . 'marcapaginas.css?v=' . $version . '">
<script nonce="' . self::nonce() . '">(function() {
    var root = document.documentElement, query = window.matchMedia(\'(prefers-color-scheme: dark)\');
    var resolve = function() {
        if (root.getAttribute(\'data-colourmode\') === \'auto\') {
            root.setAttribute(\'data-bs-theme\', query.matches ? \'dark\' : \'light\');
        }
    };
    resolve();
    query.addEventListener(\'change\', resolve);
})();</script>' . (($options['paper'] ?? '') === 'letter'
        // A page printed on US Letter says so to the print dialog, which otherwise starts on the A4 of the stylesheet.
        ? "\n<style>@media print { @page { size: letter; margin: 0; } }</style>" : '') . '
</head>
<body class="ayv ayv-skin-' . $skin . ($library && characters::young((int) $library->entityid) ? ' ayv-young' : '')
        . ($howto !== '' ? ' ayv-has-howto' : '') . '">
<a class="ayv-skip" href="#ayv-main">' . $str('skip') . '</a>
' . $as . '<header class="ayv-top' . ($query !== '' ? ' ayv-searching' : '') . '">
<a class="ayv-brand" href="' . s($home) . '"><img class="ayv-brand-light" src="' . $app . 'emblema.svg" alt="" width="44"
    height="44"><img class="ayv-brand-dark" src="' . $app . 'emblema-oscuro.svg" alt="" width="44" height="44">
    <span class="ayv-brand-name">' . s($name) . '</span></a>
' . $search . $lens . $nav . '
</header>
<main id="ayv-main" class="ayv-main" tabindex="-1">
' . $main . '
</main>
' . $howto . '
<footer class="ayv-foot"><button type="button" class="ayv-foot-credits" data-ayv-credits aria-haspopup="dialog">
Oksigenia Ayvurenda</button>
' . self::made_with($library) . (onlylibrary::way_out() ? '' : '<a class="ayv-exit" href="' . $CFG->wwwroot . '/">' .
        $str('backto', format_string($SITE->shortname, true, ['escape' => false])) . '</a>') . '</footer>
' . self::credits() . '
<script nonce="' . self::nonce() . '">window.AYV = ' . $config . ';</script>
<script src="' . $app . 'ayvurenda.js?v=' . $version . '"></script>
<script src="' . $app . 'marcapaginas.js?v=' . $version . '"></script>
<script nonce="' . self::nonce() . '">ayvBookmark({root: document.getElementById(\'ayv-main\'), minWidth: 1000});</script>
' . $scripts . self::access() . '
</body>
</html>';
    }

    /**
     * «Made with Ayvurenda» at the foot, when the centre's plan asks for it.
     *
     * @param \stdClass|null $library
     * @return string
     */
    private static function made_with(?\stdClass $library): string {
        if (!$library || !plans::allows((int) $library->entityid, 'madewith')) {
            return '';
        }
        return '<a class="ayv-foot-made" href="https://ayvurenda.com/">' .
            s(get_string('planmadewithfoot', 'local_oksigeniaayvurenda')) . '</a>' . "\n";
    }

    /**
     * The library's newest books as a shelf for another website (a school's), in a frame: one plank of spines, each
     * opening its record in the window in a new tab. Only for a library that shows its catalogue to anyone.
     *
     * @param \stdClass $library
     */
    public static function embed(\stdClass $library): void {
        global $CFG, $OUTPUT;
        $version = (int) get_config('local_oksigeniaayvurenda', 'version');
        $app = $CFG->wwwroot . '/local/oksigeniaayvurenda/app/';
        $rows = array_map(fn($card) => (object) ['recordid' => $card['id'], 'k' => ''], catalogue::recent($library, 24));
        $spines = screens::spines(screens::public_spines(stacks::spines($library, $rows), $library));
        $door = (new \moodle_url('/local/oksigeniaayvurenda/public.php', ['library' => $library->slug]))->out(false);
        $html = $OUTPUT->render_from_template('local_oksigeniaayvurenda/embed', [
            'name' => format_string($library->name, true, ['escape' => false]),
            'heading' => get_string('embedheading', 'local_oksigeniaayvurenda'),
            'door' => $door,
            'more' => get_string('embedmore', 'local_oksigeniaayvurenda'),
            'spines' => $spines,
            'bird' => screens::art('estanteria/mainumby', 'svg'),
            'bookend' => screens::art('estanteria/sujetalibros', 'svg'),
            'madewith' => plans::allows((int) $library->entityid, 'madewith')
                ? get_string('planmadewithfoot', 'local_oksigeniaayvurenda') : '',
        ]);
        // Another website frames it: no X-Frame-Options, and any page may.
        @header('Content-Type: text/html; charset=utf-8');
        @header('Content-Security-Policy: frame-ancestors *');
        self::guard();
        echo '<!DOCTYPE html>
<html lang="' . s(str_replace('_', '-', current_language())) . '">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<base target="_blank">
<title>' . s(format_string($library->name, true, ['escape' => false])) . '</title>
<link rel="stylesheet" href="' . $app . 'ayvurenda.css?v=' . $version . '">
<script nonce="' . self::nonce() . '">if (window.matchMedia && window.matchMedia(\'(prefers-color-scheme: dark)\').matches) {
    document.documentElement.setAttribute(\'data-bs-theme\', \'dark\');
}</script>
</head>
<body class="ayv ayv-embed">
' . $html . '
<script src="' . $app . 'stacks.js?v=' . $version . '"></script>
</body>
</html>';
    }

    /**
     * The library a page is about, from its address. An address without it, cut or typed by hand, goes to the only
     * library whoever looks has, or to the home that lets them choose: never to Moodle's error page (DIA-11).
     *
     * @return string Its short name.
     */
    public static function library_ref(): string {
        $ref = optional_param('library', '', PARAM_ALPHANUMEXT);
        if ($ref !== '') {
            return $ref;
        }
        $only = isloggedin() && !isguestuser() ? catalogue::library('') : null;
        if ($only) {
            return (string) $only->slug;
        }
        redirect(new \moodle_url('/local/oksigeniaayvurenda/index.php'));
    }

    /**
     * The nonce of this page: only the scripts the library writes into it carry it, so a script that slipped into the
     * page through some text would not run.
     *
     * @return string
     */
    public static function nonce(): string {
        return self::$nonce ??= base64_encode(random_bytes(16));
    }

    /**
     * What a browser is told to refuse on a page of the library (seg-salida 3): scripts that are neither the library's
     * files nor written by it with this page's nonce, plugins and another base; and guessing a type. A page that set its
     * own rules for scripts (the reader of e-books) keeps them.
     */
    private static function guard(): void {
        @header('X-Content-Type-Options: nosniff');
        foreach (headers_list() as $header) {
            if (stripos($header, 'content-security-policy:') === 0 && stripos($header, 'script-src') !== false) {
                return;
            }
        }
        @header("Content-Security-Policy: script-src 'self' 'nonce-" . self::nonce() . "'; object-src 'none'; base-uri 'self'");
    }

    /**
     * Who may show the page inside a frame: the platforms this session came from (LTI), as Moodle does when the site
     * allows embedding, and otherwise only the site itself.
     */
    private static function frame_header(): void {
        global $CFG;
        $framers = ltitool::framers();
        if ($framers) {
            @header("Content-Security-Policy: frame-ancestors 'self' " . implode(' ', $framers));
        } else if (empty($CFG->allowframembedding)) {
            @header('X-Frame-Options: SAMEORIGIN');
        }
    }

    /**
     * A page on its own, without the library's header: the entry of the site.
     *
     * @param string $title
     * @param string $html
     */
    public static function standalone(string $title, string $html): void {
        global $CFG;
        $version = (int) get_config('local_oksigeniaayvurenda', 'version');
        $app = $CFG->wwwroot . '/local/oksigeniaayvurenda/app/';
        @header('Content-Type: text/html; charset=utf-8');
        @header('X-Frame-Options: DENY');
        self::guard();
        echo '<!DOCTYPE html>
<html lang="' . s(str_replace('_', '-', current_language())) . '">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>' . s($title) . '</title>
<link rel="icon" href="' . $app . 'emblema.svg" type="image/svg+xml">
<link rel="stylesheet" href="' . $app . 'ayvurenda.css?v=' . $version . '">
<script nonce="' . self::nonce() . '">if (window.matchMedia && window.matchMedia(\'(prefers-color-scheme: dark)\').matches) {
    document.documentElement.setAttribute(\'data-bs-theme\', \'dark\');
}</script>
</head>
<body class="ayv ayv-entry-page">
' . $html . '
</body>
</html>';
    }

    /**
     * What a link to the page shows where it is shared (Open Graph): its title, a line, a picture and its address.
     *
     * @param array|null $share title, description, image (an address, or null) and url.
     * @return string
     */
    private static function share(?array $share): string {
        if (!$share) {
            return '';
        }
        $description = trim((string) preg_replace('/\s+/u', ' ', (string) ($share['description'] ?? '')));
        $description = \core_text::substr($description, 0, 200);
        $meta = [
            ['property', 'og:type', 'website'],
            ['property', 'og:title', (string) ($share['title'] ?? '')],
            ['property', 'og:description', $description],
            ['property', 'og:url', (string) ($share['url'] ?? '')],
            ['property', 'og:image', (string) ($share['image'] ?? '')],
            ['name', 'twitter:card', !empty($share['image']) ? 'summary_large_image' : 'summary'],
            ['name', 'description', $description],
        ];
        $out = '';
        foreach ($meta as [$attribute, $key, $content]) {
            if ($content !== '') {
                $out .= "\n" . '<meta ' . $attribute . '="' . $key . '" content="' . s($content) . '">';
            }
        }
        return $out;
    }

    /**
     * The credits, in a small window from the footer: the version, who makes it, its licence, what it is made with (as
     * thirdpartylibs.xml says), and the dedication, with the drawing of the memory that was made thinking of her (ours,
     * from the art package, whatever portrait a centre gives its own memory).
     *
     * @return string
     */
    private static function credits(): string {
        $str = fn(string $key, $a = null) => s(get_string($key, 'local_oksigeniaayvurenda', $a));
        $info = \core_plugin_manager::instance()->get_plugin_info('local_oksigeniaayvurenda');
        $with = [];
        $libs = @simplexml_load_file(__DIR__ . '/../../thirdpartylibs.xml');
        foreach ($libs ? $libs->library : [] as $lib) {
            // One line per work: the fonts' weights and each icon are the same one.
            $name = preg_replace('/ (Bold|SemiBold)$| icon «.*»$| \(.*\)$/u', '', (string) $lib->name);
            $licence = ['OFL' => 'SIL OFL', 'Apache' => 'Apache 2.0'][(string) $lib->license] ?? (string) $lib->license;
            $with[$name] = s($name) . ' (' . s($licence) . ')';
        }
        $art = \core_component::get_component_directory('local_oksigeniaayvurendaarte')
            ? '<p>' . $str('creditsart') . '</p>' : '';
        return '<dialog class="ayv-credits" id="ayv-credits" aria-labelledby="ayv-credits-title">
<h2 id="ayv-credits-title" class="ayv-section-title">Oksigenia Ayvurenda</h2>
<p class="ayv-field-hint">' . $str('creditsversion', $info->release ?? '') . '</p>
<p>' . $str('creditsmade') . ' · <a href="https://oksigenia.com" target="_blank" rel="noopener">oksigenia.com</a></p>
<p><a href="https://github.com/OksigeniaSL/moodle-local_oksigeniaayvurenda" target="_blank" rel="noopener">'
            . $str('creditscode') . '</a></p>
' . $art . '<p class="ayv-field-hint">' . $str('creditswith', implode(', ', $with)) . '</p>
' . (($carmita = screens::art('memoria')) ? '<img class="ayv-credits-portrait" src="' . s($carmita) . '" alt="" width="96"
    height="96">' : '') . '
<p class="ayv-credits-dedication">' . $str('creditsdedication') . '</p>
<p class="ayv-credits-name">' . $str('creditsmeaning') . ' · <span lang="gn" translate="no">Ayvu renda</span> (' .
            $str('creditsguarani') . ')</p>
<form method="dialog"><button class="ayv-button">' . $str('creditsclose') . '</button></form>
</dialog>';
    }

    /**
     * «How do I…?» at the foot of the staff's screens: a question that goes to the guide of tasks.
     *
     * @param \stdClass|null $library
     * @param string $base The address of the plugin, ending in a slash.
     * @return string
     */
    private static function howto_box(?\stdClass $library, string $base): string {
        global $PAGE;
        $staff = $library && (access::can('local/oksigeniaayvurenda:desk', $library)
            || access::can('local/oksigeniaayvurenda:catalogue', $library));
        $help = optional_param('help', 0, PARAM_BOOL);
        $here = $PAGE->has_set_url() && str_ends_with($PAGE->url->get_path(), '/howto.php');
        if (!$library || !$staff || $help || $here) {
            return '';
        }
        global $OUTPUT;
        $str = fn(string $key) => s(get_string($key, 'local_oksigeniaayvurenda'));
        global $USER;
        $asks = howto::ai_ready() && \core_ai\manager::get_user_policy_status((int) $USER->id);
        $placeholder = s(skins::say((int) $library->entityid, $asks ? 'howtoaiplaceholder' : 'howtoplaceholder'));
        // With the site's AI, the question goes straight to it.
        $how = $asks ? 'method="post" data-busy="' . $str('howtoaibusy') . '" data-busyhint="' . $str('howtoaibusyhint') . '"'
            : 'method="get"';
        $more = $asks ? '<input type="hidden" name="sesskey" value="' . sesskey() . '">'
            . '<input type="hidden" name="ask" value="1">' : '';
        // Always at hand, down on the right, folded until it is opened; it remembers how it was left.
        return '<details class="ayv-howto-dock" data-ayv-howto>
<summary class="ayv-howto-toggle">' . $OUTPUT->render_from_template('local_oksigeniaayvurenda/icon_help', []) . ' ' .
            $str('howtobox') . '</summary>
<form class="ayv-howto-box" role="search" aria-label="' . $str('howtobox') . '" ' . $how . ' action="' . $base . 'howto.php">
<input type="hidden" name="library" value="' . s($library->slug) . '">' . $more . '
<label for="ayv-howto-q">' . $str($asks ? 'howtoailabel' : 'howtoask') . '</label>
<input id="ayv-howto-q" type="search" name="q" placeholder="' . $placeholder . '" maxlength="200">
<button type="submit" class="ayv-button">' . $str($asks ? 'howtoaigo' : 'howtosearch') . '</button>
</form>
</details>';
    }

    /**
     * The panel of Oksigenia Access, when the site has it.
     *
     * @return string
     */
    private static function access(): string {
        $panel = '\\local_oksigeniaaccess\\local\\hook_callbacks';
        if (!class_exists($panel) || !method_exists($panel, 'get_footer_html')) {
            return '';
        }
        try {
            // Its scripts, trusted as the library's own, carry this page's nonce.
            return (string) preg_replace('/<script\b(?![^>]*\bnonce=)/i', '<script nonce="' . self::nonce() . '"', (string)
                $panel::get_footer_html());
        } catch (\Throwable $e) {
            // Accessibility never stops the library.
            debugging('Ayvurenda could not add the panel of Oksigenia Access: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return '';
        }
    }

    /**
     * The camera and the phone, under a page's reading field: the buttons, the panel with the phone's address as a QR
     * code, and what the scripts need.
     *
     * @param \stdClass $library
     * @return array html, config (relayurl, zxing, phoneurl) and strings for the scripts.
     */
    public static function readers_tools(\stdClass $library): array {
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        $phone = new \moodle_url('/local/oksigeniaayvurenda/phone.php', ['library' => $library->slug]);
        $qr = '';
        if (function_exists('imagecreate')) {
            $qr = '<img class="ayv-phone-qr" alt="" width="148" height="148" src="data:image/png;base64,' .
                base64_encode((new \core_qrcode($phone->out(false)))->getBarcodePngData(4, 4)) . '">';
        }
        $html = '<div class="ayv-tools">
    <button type="button" class="ayv-toggle" id="ayv-camera-open" hidden>' . s($str('camerabutton')) . '</button>
    <button type="button" class="ayv-toggle" id="ayv-phone-pair" aria-expanded="false" aria-controls="ayv-phone-panel">' .
            s($str('phonebutton')) . '</button>
</div>
<div class="ayv-phone-panel" id="ayv-phone-panel" hidden>
    ' . $qr . '
    <div><p>' . s($str('phonepanel')) . '</p>
    <p class="ayv-phone-url">' . s($phone->out(false)) . '</p>
    <p class="ayv-phone-waiting" id="ayv-phone-waiting" role="status">' . s($str('phonewaiting')) . '</p>
    <button type="button" class="ayv-button ayv-button-quiet" id="ayv-phone-stop">' . s($str('phonestop')) . '</button></div>
</div>';
        $strings = [];
        foreach (['cameratitle', 'camerahint', 'cameraclose', 'cameranone', 'camerastarting', 'cameraread'] as $key) {
            $strings[$key] = $str($key);
        }
        return [
            'html' => $html,
            'config' => [
                'relayurl' => (new \moodle_url('/local/oksigeniaayvurenda/relay_ajax.php'))->out(false),
                'zxing' => (new \moodle_url('/local/oksigeniaayvurenda/app/zxing/zxing.min.js'))->out(false),
            ],
            'strings' => $strings,
        ];
    }

    /**
     * A page with nothing around it: no header, no way out and nothing of the site, for a screen left alone in a room.
     *
     * @param string $title
     * @param string $main
     * @param \stdClass|null $library
     * @param array $options config and scripts.
     * @param string $app
     * @param int $version
     * @param string $lang
     */
    private static function bare(
        string $title,
        string $main,
        ?\stdClass $library,
        array $options,
        string $app,
        int $version,
        string $lang
    ): void {
        $scripts = '';
        foreach ($options['scripts'] ?? [] as $script) {
            $scripts .= '<script src="' . $app . $script . '.js?v=' . $version . '"></script>' . "\n";
        }
        $config = json_encode($options['config'] ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            | JSON_UNESCAPED_UNICODE);
        @header('Content-Type: text/html; charset=utf-8');
        @header('X-Frame-Options: SAMEORIGIN');
        @header('Cache-Control: no-store');
        self::guard();
        echo '<!DOCTYPE html>
<html lang="' . s($lang) . '"' . self::colour_mode() . '>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex">
<title>' . s($title) . '</title>
<link rel="icon" href="' . $app . 'favicon.ico" sizes="16x16 32x32 48x48">
<link rel="stylesheet" href="' . $app . 'ayvurenda.css?v=' . $version . '">
<link rel="stylesheet" href="' . $app . 'ayvurenda-d.css?v=' . $version . '">
<script nonce="' . self::nonce() . '">(function() {
    var root = document.documentElement;
    if (root.getAttribute(\'data-colourmode\') === \'auto\') {
        root.setAttribute(\'data-bs-theme\', window.matchMedia(\'(prefers-color-scheme: dark)\').matches ? \'dark\' : \'light\');
    }
})();</script>
</head>
<body class="ayv ayv-skin-' . skins::of($library ? (int) $library->entityid : 0) . '">
<main id="ayv-main" class="ayv-main" tabindex="-1">
' . $main . '
</main>
<script nonce="' . self::nonce() . '">window.AYV = ' . $config . ';</script>
' . $scripts . '</body>
</html>';
    }

    /**
     * The colour mode of the page, as Moodle 5.3 decides it for its own pages: the person's choice, or the site's
     * default; light where the site has no colour modes, as Moodle is then.
     *
     * @return string Attributes for the html element.
     */
    private static function colour_mode(): string {
        $class = '\\theme_boost\\colour_mode';
        if (!class_exists($class) || !$class::is_boost_theme() || !$class::is_enabled()) {
            return '';
        }
        $mode = $class::get_current_mode();
        // Auto is resolved in the browser, before the page is painted.
        return ' data-bs-theme="' . ($mode === 'dark' ? 'dark' : 'light') . '" data-colourmode="' . s($mode) . '"';
    }
}
