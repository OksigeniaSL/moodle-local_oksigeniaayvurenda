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
