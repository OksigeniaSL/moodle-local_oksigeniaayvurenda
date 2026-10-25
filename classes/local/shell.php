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
    /**
     * Lets in only who may do what a page does. Someone not logged in, or in as a guest, goes to log in and comes back;
     * someone logged in without the capability gets a page of the library saying so, with the way back, instead of
     * an error.
     *
     * @param string $capability
     * @param \stdClass|null $library
     */
    public static function require(string $capability, ?\stdClass $library = null): void {
        if (has_capability($capability, \context_system::instance())) {
            return;
        }
        self::login_first();
        $name = get_string(str_replace('local/', '', $capability), 'local_oksigeniaayvurenda');
        self::refuse(get_string('staffonlyhint', 'local_oksigeniaayvurenda', $name), $library);
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
     * A page of the library that says why someone may not go on, with the way back, and ends there.
     *
     * @param string $why
     * @param \stdClass|null $library
     */
    public static function refuse(string $why, ?\stdClass $library = null): void {
        $home = (new \moodle_url('/local/oksigeniaayvurenda/index.php', $library ? ['library' => $library->slug] : []))->out(false);
        http_response_code(403);
        self::output(
            get_string('staffonly', 'local_oksigeniaayvurenda'),
            '<h1 class="ayv-results-title">' .
            s(get_string('staffonly', 'local_oksigeniaayvurenda')) . '</h1><p>' . s($why) . '</p><p><a class="ayv-button" href="' .
            s($home) . '">' . s(get_string('staffonlyback', 'local_oksigeniaayvurenda')) . '</a></p>',
            $library,
            '',
            ['search' => false]
        );
        die;
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
     *     the page), feed (the address of an OPDS catalogue to announce).
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
        $str = fn(string $key, $a = null) => s(get_string($key, 'local_oksigeniaayvurenda', $a));
        $home = $library ? $base . 'index.php?library=' . rawurlencode($library->slug) : $base . 'index.php';
        $search = '';
        if ($library && ($options['search'] ?? true)) {
            $search = '<form class="ayv-search" role="search" action="' . $base . 'search.php" method="get">
    <input type="hidden" name="library" value="' . s($library->slug) . '">
    <label class="ayv-sr" for="ayv-q">' . $str('searchlabel') . '</label>
    <input id="ayv-q" name="q" type="search" value="' . s($query) . '" placeholder="' . $str('searchplaceholder') . '"
        autocomplete="off" enterkeyhint="search" maxlength="200">
    <a class="ayv-search-clear" href="' . $base . 'index.php?library=' . s($library->slug) . '" aria-label="' .
                $str('searchclear') . '"' . ($query === '' ? ' hidden' : '') . '>×</a>
    <button type="submit">' . $str('searchbutton') . '</button>
</form>';
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
        foreach ($options['scripts'] ?? [] as $script) {
            $scripts .= '<script src="' . $app . $script . '.js?v=' . $version . '"></script>' . "\n";
        }
        $nav = '';
        $links = $options['nav'] ?? [];
        if (isset($links['url'])) {
            $links = [$links];
        }
        // First the library's catalogue, where everyone goes back most (marked when you are on it); then the page's own
        // links and the global ones (the libraries; the desk for the staff), without repeating one.
        $system = \context_system::instance();
        $catalogue = '';
        $seen = [];
        if ($library) {
            global $OUTPUT, $PAGE;
            $here = $PAGE->has_set_url() && str_ends_with($PAGE->url->get_path(), '/local/oksigeniaayvurenda/index.php');
            $seen[$home] = true;
            $catalogue = '<a class="ayv-nav ayv-nav-home" href="' . s($home) . '"' . ($here ? ' aria-current="page"' : '') . '>'
                . $OUTPUT->render_from_template('local_oksigeniaayvurenda/icon_book', []) . ' '
                . s(get_string('deskcatalogue', 'local_oksigeniaayvurenda')) . '</a>';
        }
        // A tutor's class: the challenge of the term, which the tutor chooses.
        global $USER;
        if ($library && challenges::tutored((int) $library->entityid, (int) ($USER->id ?? 0))) {
            $links[] = ['url' => $base . 'class.php?library=' . rawurlencode($library->slug),
                'label' => get_string('classnav', 'local_oksigeniaayvurenda')];
        }
        // The libraries: to choose one; for whoever manages them, their page, whose cards also go in (one link, not two).
        $manage = has_capability('local/oksigeniaayvurenda:manage', $system);
        if (count(catalogue::libraries()) > 1 || $manage) {
            $links[] = ['url' => $base . ($manage ? 'libraries.php' : 'index.php'),
                'label' => get_string('navlibraries', 'local_oksigeniaayvurenda')];
        }
        if ($library && has_capability('local/oksigeniaayvurenda:desk', $system)) {
            $links[] = ['url' => $base . 'desk.php?library=' . rawurlencode($library->slug),
                'label' => get_string('desktitle', 'local_oksigeniaayvurenda')];
        }
        $nav = $catalogue;
        foreach ($links as $link) {
            if (isset($seen[$link['url']])) {
                continue;
            }
            $seen[$link['url']] = true;
            $nav .= '<a class="ayv-nav" href="' . s($link['url']) . '">' . s($link['label']) . '</a>';
        }
        // The way back to Moodle, always at the end and in sight.
        $mine = isloggedin() && !isguestuser();
        $nav .= '<a class="ayv-nav ayv-nav-exit" href="' . s($CFG->wwwroot . ($mine ? '/my/' : '/')) . '">'
            . ($mine ? s(get_string('myhome')) : $str('backto', format_string($SITE->shortname))) . '</a>';
        $nav = '<nav class="ayv-navs">' . $nav . '</nav>';
        $name = $library ? format_string($library->name) : get_string('pluginname', 'local_oksigeniaayvurenda');
        @header('Content-Type: text/html; charset=utf-8');
        @header('X-Frame-Options: SAMEORIGIN');
        echo '<!DOCTYPE html>
<html lang="' . s($lang) . '"' . self::colour_mode() . '>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0f3a33">
<meta name="robots" content="' . (!empty($options['index']) ? 'index, follow' : 'noindex') . '">' . (!empty($options['feed'])
        ? '
<link rel="alternate" type="' . opds::TYPE . '" href="' . s($options['feed']) . '">' : '') . '
<title>' . s($title) . ' · ' . s($name) . '</title>
<link rel="icon" href="' . $app . 'favicon.ico" sizes="16x16 32x32 48x48">
<link rel="icon" href="' . $app . 'emblema-pequeno.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="' . $app . 'apple-touch-icon.png">
<link rel="preload" href="' . $app . 'fonts/andika-400.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="' . $app . 'ayvurenda.css?v=' . $version . '">
<link rel="stylesheet" href="' . $app . 'ayvurenda-d.css?v=' . $version . '">
<link rel="stylesheet" href="' . $app . 'marcapaginas.css?v=' . $version . '">
<script>(function() {
    var root = document.documentElement, query = window.matchMedia(\'(prefers-color-scheme: dark)\');
    var resolve = function() {
        if (root.getAttribute(\'data-colourmode\') === \'auto\') {
            root.setAttribute(\'data-bs-theme\', query.matches ? \'dark\' : \'light\');
        }
    };
    resolve();
    query.addEventListener(\'change\', resolve);
})();</script>
</head>
<body class="ayv">
<a class="ayv-skip" href="#ayv-main">' . $str('skip') . '</a>
<header class="ayv-top">
<a class="ayv-brand" href="' . s($home) . '"><img class="ayv-brand-light" src="' . $app . 'emblema.svg" alt="" width="44"
    height="44"><img class="ayv-brand-dark" src="' . $app . 'emblema-oscuro.svg" alt="" width="44" height="44">
    <span class="ayv-brand-name">' . s($name) . '</span></a>
' . $search . $nav . '
</header>
<main id="ayv-main" class="ayv-main" tabindex="-1">
' . $main . '
</main>
' . self::howto_box($library, $base) . '
<footer class="ayv-foot"><button type="button" class="ayv-foot-credits" data-ayv-credits aria-haspopup="dialog">
Oksigenia Ayvurenda</button>
<a class="ayv-exit" href="' . $CFG->wwwroot . '/">' . $str('backto', format_string($SITE->shortname)) . '</a></footer>
' . self::credits() . '
<script>window.AYV = ' . $config . ';</script>
<script src="' . $app . 'ayvurenda.js?v=' . $version . '"></script>
<script src="' . $app . 'marcapaginas.js?v=' . $version . '"></script>
<script>ayvBookmark({root: document.getElementById(\'ayv-main\'), minWidth: 1000});</script>
' . $scripts . self::access() . '
</body>
</html>';
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
        $context = \context_system::instance();
        $staff = has_capability('local/oksigeniaayvurenda:desk', $context)
            || has_capability('local/oksigeniaayvurenda:catalogue', $context);
        $help = optional_param('help', 0, PARAM_BOOL);
        $here = $PAGE->has_set_url() && str_ends_with($PAGE->url->get_path(), '/howto.php');
        if (!$library || !$staff || $help || $here) {
            return '';
        }
        $str = fn(string $key) => s(get_string($key, 'local_oksigeniaayvurenda'));
        return '<form class="ayv-howto-box" role="search" aria-label="' . $str('howtobox') . '" method="get" action="' . $base .
            'howto.php">
<input type="hidden" name="library" value="' . s($library->slug) . '">
<label for="ayv-howto-q">' . $str('howtobox') . '</label>
<input id="ayv-howto-q" type="search" name="q" placeholder="' . $str('howtoplaceholder') . '" maxlength="200">
<button type="submit" class="ayv-button ayv-button-quiet">' . $str('howtosearch') . '</button>
</form>';
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
            return (string) $panel::get_footer_html();
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
