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
 * What the library shows in the Moodle app.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\output;

use local_oksigeniaayvurenda\core\barcode\code39;
use local_oksigeniaayvurenda\local\mine;

/**
 * The «Library» of the app's menu: for each centre where someone is a reader, their books out and until when, what is
 * set aside for them, their card's code to show at the desk (in letters, as a barcode and as a QR), and the ways into
 * the library and Ayvu rande. The pages of the library open inside the app with the session (core-link in the app).
 */
class mobile {
    /**
     * The page of the menu entry.
     *
     * @param array $args What the app sends: userid among others.
     * @return array
     */
    public static function mobile_library_view(array $args): array {
        global $OUTPUT, $USER;
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        $libraries = [];
        foreach (mine::summary((int) $USER->id) as $library) {
            $code = $library['cardcode'];
            $libraries[] = $library + [
                'hasloans' => (bool) $library['loans'],
                'hasholds' => (bool) $library['holds'],
                'loans' => array_map(fn($loan) => $loan + ['when' => $str(
                    $loan['late'] ? 'mobilelate' : 'mobiledue',
                    $loan['due']
                )], $library['loans']),
                'holds' => array_map(fn($hold) => $hold + ['when' => $hold['ready'] ? $str('mobileready', $hold['until'])
                    : $str('mobilewaiting')], $library['holds']),
                // Not «barcode» nor «qr»: other templates use those names for drawings, not addresses.
                'barcodeimg' => $code !== '' && code39::can($code)
                    ? 'data:image/svg+xml;base64,' . base64_encode(code39::svg($code, 60, false)) : '',
                'qrimg' => $code !== ''
                    ? 'data:image/png;base64,' . base64_encode((new \core_qrcode($code))->getBarcodePngData(4, 4)) : '',
            ];
        }
        $data = [
            'libraries' => $libraries,
            'haslibraries' => (bool) $libraries,
            'notreader' => $str('mobilenotreader'),
            'loansheading' => $str('mobileloans'),
            'holdsheading' => $str('mobileholds'),
            'cardheading' => $str('mobilecard'),
            'cardhint' => $str('mobilecardhint'),
            'none' => $str('mobilenone'),
            'golibrary' => $str('mobilegolibrary'),
            'gospring' => $str('mobilegospring'),
        ];
        return [
            'templates' => [['id' => 'main', 'html' => $OUTPUT->render_from_template(
                'local_oksigeniaayvurenda/mobile_library',
                $data
            )]],
            'javascript' => '',
            'otherdata' => '',
        ];
    }
}
