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
 * Reader cards, to see and to print.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

use local_oksigeniaayvurenda\core\barcode\code39;
use local_oksigeniaayvurenda\core\flower;

/**
 * What a reader's card shows: the name, the group, the library, the flower and the code, as a barcode for the scanner
 * and as a QR code for the camera. The printed card always keeps the same code; the one on the screen changes with
 * the flower.
 */
final class cards {
    /**
     * The look of a reader's card this school year: the flower, the sky (lively or sober) or the classic one, as their
     * stage, the centre and their own choice say.
     *
     * @param \stdClass $reader
     * @param int|null $now
     * @return string One of card_look::LOOKS.
     */
    public static function look_of(\stdClass $reader, ?int $now = null): string {
        global $DB;
        $year = years::around((int) $reader->entityid, $now ?? time());
        $member = $DB->get_record(
            'local_oksigeniaayvurenda_member',
            ['readerid' => $reader->id, 'yearid' => $year->id],
            'id, groupname, stage',
            IGNORE_MULTIPLE
        );
        $settings = json_decode((string) $DB->get_field(
            'local_oksigeniaayvurenda_entity',
            'settings',
            ['id' => $reader->entityid]
        ), true) ?: [];
        $stage = $member ? (string) $member->stage : '';
        $own = !empty($reader->userid)
            ? get_user_preferences('local_oksigeniaayvurenda_cardlook', null, (int) $reader->userid) : null;
        $group = $member ? (string) $member->groupname : '';
        [$guess, $centre] = self::base($reader, $stage, $group, $settings);
        return \local_oksigeniaayvurenda\core\card_look::resolve($guess, $centre, $own ?: null);
    }

    /** @var string[] The age bands a centre can give a reader. */
    public const BANDS = ['flower', 'sky', 'skysober'];

    /**
     * Where a reader's look starts: their age band when the centre said it (above the stage's choice), else what their
     * stage and group say and the centre's choice for the stage.
     *
     * @param \stdClass $reader With readertype, entityid and, if known, band.
     * @param string $stage
     * @param string $group
     * @param array $settings The centre's settings.
     * @return array [the guess, the centre's choice or null].
     */
    public static function base(\stdClass $reader, string $stage, string $group, array $settings): array {
        $band = (string) ($reader->band ?? '');
        if ($reader->readertype === 'student' && in_array($band, self::BANDS, true)) {
            return [$band, null];
        }
        return [\local_oksigeniaayvurenda\core\card_look::guess(
            $reader->readertype,
            $stage,
            $group,
            skins::unknown_look((int) $reader->entityid)
        ), $settings['cardlooks'][$stage] ?? null];
    }

    /**
     * A card.
     *
     * @param \stdClass $reader With id, entityid, cardcode and the name to show.
     * @param string $name
     * @param string $group
     * @param string $library
     * @param int|null $stage The flower's stage, or null for a printed card (which shows the emblem).
     * @return array
     */
    public static function card(\stdClass $reader, string $name, string $group, string $library, ?int $stage = null): array {
        $code = (string) $reader->cardcode;
        $qr = '';
        if (function_exists('imagecreate')) {
            $qr = 'data:image/png;base64,' . base64_encode((new \core_qrcode($code))->getBarcodePngData(3, 3));
        }
        return [
            'name' => $name,
            'group' => $group,
            'library' => $library,
            'code' => $code,
            'barcode' => code39::can($code) ? code39::svg($code, 40, true) : '',
            'qr' => $qr,
            'flower' => self::flower_url($stage),
            'stage' => $stage === null ? '' : get_string('stage' . flower::STAGES[$stage], 'local_oksigeniaayvurenda'),
        ];
    }

    /**
     * A card on paper. Paper does not grow: everyone's shows the first day, the seed or the new moon, so that a class's
     * cards on a table are not a ranking, and nothing goes out of date; what grows stays on the screen.
     *
     * @param \stdClass $reader With id, entityid, userid, readertype and cardcode.
     * @param string $name
     * @param string $group
     * @param string $library
     * @param string $skin The centre's.
     * @return array name, group, library, code, skin, look, art, label, namesize, qr, barcode, barwidth.
     */
    public static function printed(\stdClass $reader, string $name, string $group, string $library, string $skin): array {
        global $CFG;
        $code = (string) $reader->cardcode;
        // The school, the secondary school and the libraries of every age draw; each reader's look says what (a grown-up
        // reader of a public library has the sober card).
        $look = in_array($skin, ['school', 'secondary', 'public', 'mobile'], true) ? self::look_of($reader) : 'none';
        $art = match ($look) {
            'flower' => screens::art('carne/impreso/semilla') ?? self::flower_url(null),
            'sky' => screens::art('carne/impreso/luna-nueva'),
            default => null,
        };
        // The QR: core_qrcode forces the lowest correction, and this needs no GD.
        require_once($CFG->libdir . '/tcpdf/tcpdf_barcodes_2d.php');
        $matrix = (new \TCPDF2DBarcode($code, 'QRCODE,Q'))->getBarcodeArray();
        $path = '';
        foreach ($matrix['bcode'] as $row => $cells) {
            foreach ($cells as $col => $on) {
                $path .= $on ? 'M' . $col . ' ' . $row . 'h1v1h-1z' : '';
            }
        }
        $n = (int) $matrix['num_cols'];
        $qr = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $n . ' ' . $n . '" shape-rendering="crispEdges" '
            . 'aria-hidden="true"><path fill="#000" d="' . $path . '"/></svg>';
        // The bars: a narrow bar of 0.45 mm, or what fits in 76.6 mm; under 0.25 mm, none.
        $barcode = '';
        $barwidth = '';
        if (code39::can($code)) {
            $modules = array_sum(code39::widths($code)) + 20;
            $x = min(.45, 76.6 / $modules);
            if ($x >= .25) {
                $barcode = code39::svg($code, 40, false);
                $barwidth = (string) round($modules * $x, 2);
            }
        }
        return [
            'name' => $name,
            'group' => $group,
            'library' => $library,
            'code' => strtoupper($code),
            'skin' => $skin,
            'look' => $art ? ($look === 'flower' ? 'art-flower' : 'art-sky') : 'plain',
            'art' => $art ?? '',
            'label' => get_string('printcardlabel', 'local_oksigeniaayvurenda'),
            'namesize' => \core_text::strlen($name) > 34 ? 'verylong' : (\core_text::strlen($name) > 18 ? 'long' : ''),
            'qr' => $qr,
            'barcode' => $barcode,
            'barwidth' => $barwidth,
        ];
    }

    /**
     * The flower drawn in the page, with the marks of each path lit up to its level (Gaudí's drawing: drops for back,
     * sparkles for explore, speech bubbles for tell, roots for stay; what is missing shows dotted, nothing withers).
     *
     * @param int $stage
     * @param array $levels Level of each path, 0 to 3.
     * @param string $label What it says to a screen reader.
     * @param int $size Pixels.
     * @return string SVG.
     */
    public static function flower_svg(int $stage, array $levels, string $label, int $size): string {
        return self::layered('flor', $stage, $levels, $label, $size)
            ?? self::drawing('flor/' . flower::STAGES[$stage] . '.svg', $levels, $label, $size, $size);
    }

    /** @var string[] The drawings of the sky, by stage: the moon as it grows. */
    public const SKY = ['new', 'crescent', 'half', 'full'];

    /**
     * The sky drawn in the page, for twelve and over: a constellation for each path, lit up to its level, and the moon
     * as the stage (Gaudí's drawing). Sober from fifteen and for adults.
     *
     * @param int $stage
     * @param array $levels
     * @param string $label
     * @param int $size
     * @param bool $sober
     * @return string
     */
    public static function sky_svg(int $stage, array $levels, string $label, int $size, bool $sober): string {
        return self::layered('cielo', $stage, $levels, $label, $size, $sober)
            ?? self::drawing('cielo/' . self::SKY[$stage] . '.svg', $levels, $label, $size, $size, $sober);
    }

    /** @var int Up to this size, the sky takes the marks drawn thicker (pix/carne/cielo/pequeno/). */
    private const SMALL = 140;

    /**
     * Gaudí's drawing of the card, when the art package brings it: the base of the stage and, on it, each mark in its
     * box, lit up to the level of its path and waiting beyond (the sober sky leaves out the waiting ones). Its layers
     * and boxes come from the package's carne.json.
     *
     * @param string $kind flor or cielo.
     * @param int $stage 0 to 3.
     * @param array $levels Path => level, 0 to 3.
     * @param string $label What it says to a screen reader.
     * @param int $size Pixels, at most (it shrinks with its box).
     * @param bool $sober
     * @return string|null HTML; null without the art package's drawing.
     */
    private static function layered(
        string $kind,
        int $stage,
        array $levels,
        string $label,
        int $size,
        bool $sober = false
    ): ?string {
        $dir = \core_component::get_component_directory('local_oksigeniaayvurendaarte');
        $json = $dir ? $dir . '/pix/carne/' . $kind . '/carne.json' : '';
        $layout = $json !== '' && is_readable($json) ? json_decode((string) file_get_contents($json), true) : null;
        $base = null;
        foreach ((array) ($layout['stages'] ?? []) as $one) {
            if ((int) ($one['stage'] ?? -1) === $stage) {
                $base = screens::art('carne/' . $kind . '/' . pathinfo((string) $one['file'], PATHINFO_FILENAME));
            }
        }
        if (!$base) {
            return null;
        }
        $small = $kind === 'cielo' && $size <= self::SMALL ? 'pequeno/' : '';
        $html = '<span class="ayv-layered ayv-layered-' . $kind . '" role="img" aria-label="' . s($label) . '" style="width: '
            . $size . 'px"><img class="ayv-layered-base" src="' . s($base) . '" alt="" width="' . $size . '" height="' . $size
            . '">';
        foreach ((array) ($layout['order'] ?? []) as $path) {
            $level = max(0, min(3, (int) ($levels[$path] ?? 0)));
            foreach ((array) ($layout['paths'][$path]['marks'] ?? []) as $mark) {
                $lit = (int) ($mark['n'] ?? 9) <= $level;
                if (!$lit && $sober) {
                    continue;
                }
                $file = pathinfo((string) ($lit ? $mark['lit'] : $mark['waiting']), PATHINFO_FILENAME);
                $url = screens::art('carne/' . $kind . '/' . $small . $file) ?? screens::art('carne/' . $kind . '/' . $file);
                [$left, $top, $width] = array_map('floatval', array_slice((array) ($mark['pct'] ?? []), 0, 3) + [0, 0, 0]);
                if ($url && $width > 0) {
                    $html .= '<img class="ayv-layered-mark' . ($lit ? '' : ' ayv-layered-waiting') . '" src="' . s($url)
                        . '" alt="" style="left: ' . $left . '%; top: ' . $top . '%; width: ' . $width . '%">';
                }
            }
        }
        return $html . '</span>';
    }

    /**
     * The three stars of one path in a row, as the sky draws them, for its legend.
     *
     * @param string $path
     * @param int $level
     * @param string $label
     * @param bool $sober
     * @return string
     */
    public static function sky_path_svg(string $path, int $level, string $label, bool $sober): string {
        return self::drawing('cielo/path-' . $path . '.svg', [$path => $level], $label, 62, 22, $sober);
    }

    /**
     * The three marks of one path in a row, as the flower draws them, for the legend of how it grows.
     *
     * @param string $path back, explore, tell or stay.
     * @param int $level 0 to 3.
     * @param string $label What it says to a screen reader.
     * @return string SVG, 22 pixels high.
     */
    public static function path_svg(string $path, int $level, string $label): string {
        return self::drawing('flor/path-' . $path . '.svg', [$path => $level], $label, 62, 22);
    }

    /**
     * One of the flower's drawings, with the marks of the paths given lit up to their level.
     *
     * @param string $file In app.
     * @param array $levels Level of each path, 0 to 3.
     * @param string $label
     * @param int $width
     * @param int $height
     * @param bool $sober The sober style of the sky.
     * @return string
     */
    private static function drawing(
        string $file,
        array $levels,
        string $label,
        int $width,
        int $height,
        bool $sober = false
    ): string {
        global $CFG;
        $svg = (string) file_get_contents($CFG->dirroot . '/local/oksigeniaayvurenda/app/' . $file);
        $attributes = ['back' => 'volver', 'explore' => 'explorar', 'tell' => 'contar', 'stay' => 'quedarse'];
        $open = preg_match('/^<svg\b[^>]*>/', $svg, $matches) ? $matches[0] : '';
        if ($open === '') {
            return '';
        }
        $new = $open;
        foreach ($attributes as $path => $attribute) {
            $level = max(0, min(3, (int) ($levels[$path] ?? 0)));
            $new = preg_replace('/\bdata-' . $attribute . '="\d"/', 'data-' . $attribute . '="' . $level . '"', $new);
        }
        // The label goes in as it is: a «$1» in a name is not a back reference.
        $quoted = str_replace(['\\', '$'], ['\\\\', '\\$'], s($label));
        $new = preg_replace('/\baria-label="[^"]*"/', 'aria-label="' . $quoted . '"', $new);
        if ($sober) {
            $new = preg_replace('/\bclass="([^"]*)"/', 'class="$1 sobrio"', $new, 1);
        }
        $new = '<svg width="' . $width . '" height="' . $height . '" focusable="false"' . substr($new, 4);
        $svg = $new . substr($svg, strlen($open));
        return preg_replace('/<title>[^<]*<\/title>/', '<title>' . $quoted . '</title>', $svg, 1);
    }

    /**
     * The picture of a stage of the flower; the emblem for printed cards.
     *
     * @param int|null $stage
     * @return string
     */
    public static function flower_url(?int $stage): string {
        $file = $stage === null ? 'emblema.svg' : 'flor/' . flower::STAGES[$stage] . '.svg';
        return (new \moodle_url('/local/oksigeniaayvurenda/app/' . $file))->out(false);
    }
}
