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
     * Sends a whole page.
     *
     * @param string $title
     * @param string $main The HTML of the screen.
     * @param \stdClass|null $library
     * @param string $query What is in the search box.
     * @param array $options search (false to leave the search out), scripts (more scripts of app/), config (more data
     *     for the scripts), nav (links in the header: a list of url and label).
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
    <button type="submit">' . $str('searchbutton') . '</button>
</form>';
        }
        $config = json_encode(($options['config'] ?? []) + [
            'library' => $library ? $library->slug : '',
            'searchurl' => $base . 'search.php',
            'homeurl' => $home,
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
        foreach ($links as $link) {
            $nav .= '<a class="ayv-nav" href="' . s($link['url']) . '">' . s($link['label']) . '</a>';
        }
        if ($nav !== '') {
            $nav = '<nav class="ayv-navs">' . $nav . '</nav>';
        }
        $name = $library ? format_string($library->name) : get_string('pluginname', 'local_oksigeniaayvurenda');
        @header('Content-Type: text/html; charset=utf-8');
        @header('X-Frame-Options: SAMEORIGIN');
        echo '<!DOCTYPE html>
<html lang="' . s($lang) . '"' . self::colour_mode() . '>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0f3a33">
<meta name="robots" content="noindex">
<title>' . s($title) . ' · ' . s($name) . '</title>
<link rel="icon" href="' . $app . 'favicon.ico" sizes="16x16 32x32 48x48">
<link rel="icon" href="' . $app . 'emblema-pequeno.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="' . $app . 'apple-touch-icon.png">
<link rel="preload" href="' . $app . 'fonts/andika-400.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="' . $app . 'ayvurenda.css?v=' . $version . '">
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
<footer class="ayv-foot"><span>Oksigenia Ayvurenda</span>
<a class="ayv-exit" href="' . $CFG->wwwroot . '/">' . $str('backto', format_string($SITE->shortname)) . '</a></footer>
<script>window.AYV = ' . $config . ';</script>
<script src="' . $app . 'ayvurenda.js?v=' . $version . '"></script>
' . $scripts . self::access() . '
</body>
</html>';
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
