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
        global $CFG;
        $svg = (string) file_get_contents($CFG->dirroot . '/local/oksigeniaayvurenda/app/flor/' . flower::STAGES[$stage] . '.svg');
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
        $new = preg_replace('/\baria-label="[^"]*"/', 'aria-label="' . s($label) . '"', $new);
        $new = '<svg width="' . $size . '" height="' . $size . '" focusable="false"' . substr($new, 4);
        $svg = $new . substr($svg, strlen($open));
        return preg_replace('/<title>[^<]*<\/title>/', '<title>' . s($label) . '</title>', $svg, 1);
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
