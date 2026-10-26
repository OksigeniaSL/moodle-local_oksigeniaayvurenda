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
        $own = $reader->userid ? get_user_preferences('local_oksigeniaayvurenda_cardlook', null, (int) $reader->userid) : null;
        $group = $member ? (string) $member->groupname : '';
        return \local_oksigeniaayvurenda\core\card_look::resolve(
            \local_oksigeniaayvurenda\core\card_look::guess($reader->readertype, $stage, $group),
            $settings['cardlooks'][$stage] ?? null,
            $own ?: null
        );
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
        return self::drawing('flor/' . flower::STAGES[$stage] . '.svg', $levels, $label, $size, $size);
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
        return self::drawing('cielo/' . self::SKY[$stage] . '.svg', $levels, $label, $size, $size, $sober);
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
