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
use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\mine;
use local_oksigeniaayvurenda\local\screens;

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
        $otherdata = [];
        foreach (mine::summary((int) $USER->id) as $index => $library) {
            $code = $library['cardcode'];
            // Each library's search box keeps its words in the page's data, under its own name.
            $otherdata['q' . $index] = '';
            // What is worked out here replaces what the summary gave (its lists of loans and holds), so it goes first.
            $libraries[] = [
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
                'qkey' => 'q' . $index,
            ] + $library;
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
            'searchheading' => $str('mobilesearch'),
            'searchplaceholder' => $str('searchplaceholder'),
            'searchbutton' => $str('searchbutton'),
        ];
        return [
            'templates' => [['id' => 'main', 'html' => $OUTPUT->render_from_template(
                'local_oksigeniaayvurenda/mobile_library',
                $data
            )]],
            'javascript' => '',
            'otherdata' => $otherdata,
        ];
    }

    /**
     * A search of a library's catalogue, in the app: each book with whether it is free here and where.
     *
     * @param array $args library (its short name) and q (the words).
     * @return array
     */
    public static function mobile_search_view(array $args): array {
        global $OUTPUT;
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        $library = self::library($args);
        $query = trim(\core_text::substr(clean_param((string) ($args['q'] ?? ''), PARAM_TEXT), 0, 200));
        $cards = [];
        if ($library && $query !== '') {
            $cards = screens::cards(array_slice(catalogue::search($library, $query)['cards'], 0, 30), $library);
        }
        $data = [
            'library' => $library ? $library->slug : '',
            'name' => $library ? format_string($library->name) : '',
            'cards' => array_map(
                fn($c) => ['id' => $c['id'], 'title' => $c['title'], 'authors' => $c['authors'],
                'statelabel' => $c['statelabel'], 'free' => $c['state'] === 'free', 'location' => $c['location'] ?? ''],
                $cards
            ),
            'hascards' => (bool) $cards,
            'searched' => $query !== '',
            'cannot' => !$library,
            'notreader' => $str('mobilenotreader'),
            'nothing' => $str('mobilenothing', s($query)),
            'searchplaceholder' => $str('searchplaceholder'),
            'searchbutton' => $str('searchbutton'),
        ];
        return [
            'templates' => [['id' => 'main', 'html' => $OUTPUT->render_from_template(
                'local_oksigeniaayvurenda/mobile_search',
                $data
            )]],
            'javascript' => '',
            'otherdata' => ['q' => $query],
        ];
    }

    /**
     * A book of a library, in the app: its cover, whether it is free and where, what the reader asked for and the way
     * to ask for it when it is out (if they may ask online), and the way to its page in the library.
     *
     * @param array $args library (its short name) and id (the book).
     * @return array
     */
    public static function mobile_record_view(array $args): array {
        global $OUTPUT;
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        $library = self::library($args);
        $found = $library ? catalogue::record((int) ($args['id'] ?? 0), $library) : null;
        $data = ['cannot' => !$found, 'notreader' => $str('mobilenotreader')];
        if ($found) {
            $card = screens::cards([$found['card']], $library)[0];
            $box = screens::hold($found, $library) ?? [];
            $copies = array_values(array_filter($found['copies'], fn($c) => $c['here']));
            $data += [
                'library' => $library->slug,
                'recordid' => (int) $found['record']->id,
                'title' => $card['title'],
                'subtitle' => (string) ($card['subtitle'] ?? ''),
                'authors' => $card['authors'],
                'cover' => self::cover((int) $found['record']->id),
                'statelabel' => $card['statelabel'],
                'free' => $card['state'] === 'free',
                'copies' => array_map(fn($c) => ['state' => $str('state' . $c['state']), 'location' => (string) $c['location'],
                    'callnumber' => $c['callnumber']], $copies),
                'hascopies' => (bool) $copies,
                'copiesheading' => $str('copies'),
                'mine' => $box['mine'] ?? '',
                'ahead' => $box['ahead'] ?? '',
                'canask' => !empty($box['canask']),
                'holdask' => $str('holdask'),
                'holdaskhint' => $str('holdaskhint'),
                'holdadded' => $str('holdadded'),
                'url' => $card['url'],
                'openweb' => $str('mobileopenweb'),
            ];
        }
        return [
            'templates' => [['id' => 'main', 'html' => $OUTPUT->render_from_template(
                'local_oksigeniaayvurenda/mobile_record',
                $data
            )]],
            'javascript' => '',
            'otherdata' => '',
        ];
    }

    /**
     * A book's cover inside the page, so the app shows it without asking the site for it with its key.
     *
     * @param int $recordid
     * @return string A data address, or '' when it has none (or it is too big to carry).
     */
    private static function cover(int $recordid): string {
        $file = get_file_storage()->get_file(
            \context_system::instance()->id,
            'local_oksigeniaayvurenda',
            'cover',
            $recordid,
            '/',
            'cover.jpg'
        );
        if (!$file || $file->get_filesize() > 200 * 1024) {
            return '';
        }
        return 'data:image/jpeg;base64,' . base64_encode($file->get_content());
    }

    /**
     * The library the app asks about, if the person may see it.
     *
     * @param array $args
     * @return \stdClass|null
     */
    private static function library(array $args): ?\stdClass {
        $library = catalogue::library(clean_param((string) ($args['library'] ?? ''), PARAM_ALPHANUMEXT));
        return $library && access::can('local/oksigeniaayvurenda:view', $library) ? $library : null;
    }
}
