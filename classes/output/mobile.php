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
use local_oksigeniaayvurenda\local\mail;
use local_oksigeniaayvurenda\local\mine;
use local_oksigeniaayvurenda\local\renewals;
use local_oksigeniaayvurenda\local\screens;
use local_oksigeniaayvurenda\local\skins;
use local_oksigeniaayvurenda\local\zones;

/**
 * The «Library» of the app's menu: for each centre where someone is a reader, their books out and until when, what is
 * set aside for them, their card's code to show at the desk (in letters, as a barcode and as a QR), and the ways into
 * the library and Ayvu rande. The pages of the library open inside the app with the session (core-link in the app).
 */
class mobile {
    /**
     * Why a page of a library does not open: the person reads at another one here, or at none. No names: on a shared site, that
     * other library says nothing of this one.
     *
     * @return string
     */
    private static function not_here(): string {
        global $USER;
        return get_string(mine::summary((int) $USER->id) ? 'mobilenotyours' : 'mobilenotreader', 'local_oksigeniaayvurenda');
    }

    /**
     * What the app runs when it starts: the links of the notices open inside it.
     *
     * @param array $args What the app sends.
     * @return array
     */
    public static function mobile_init(array $args): array {
        global $CFG;
        return [
            'templates' => [],
            'javascript' => (string) file_get_contents($CFG->dirroot . '/local/oksigeniaayvurenda/app/mobile-links.js'),
            'otherdata' => '',
            // No «Library» in the app's menu for whoever has no library to go to yet (one still closed to its readers).
            'disabled' => !access::sees_any(),
        ];
    }

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
            $otherdata['m' . $index] = (string) ($library['remembers']['query'] ?? '');
            // What is worked out here replaces what the summary gave (its lists of loans and holds), so it goes first.
            $libraries[] = [
                'memoryline' => $library['remembers']['text'] ?? '',
                'memoryportrait' => $library['remembers']['portrait'] ?? '',
                'memoryrecord' => $library['remembers']['recordid'] ?? 0,
                'memorykey' => 'm' . $index,
                'memorybook' => !empty($library['remembers']['recordid']),
                'hasloans' => (bool) $library['loans'],
                'hasholds' => (bool) $library['holds'],
                'loans' => array_map(
                    fn($loan) => $loan + ['when' => $str(
                        $loan['late'] ? 'mobilelate' : 'mobiledue',
                        $loan['due']
                    ), 'renewtoast' => $loan['renewuntil'] !== '' ? $str('mobilerenewedto', $loan['renewuntil']) : ''],
                    $library['loans']
                ),
                'holds' => array_map(fn($hold) => $hold + ['when' => $hold['ready'] ? $str('mobileready', $hold['until'])
                    : $str('mobilewaiting')], $library['holds']),
                'qkey' => 'q' . $index,
            ] + self::codes($code) + $library;
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
            'renew' => $str('mobilerenew'),
            'renewed' => $str('mobilerenewed'),
            'searchplaceholder' => $str('searchplaceholder'),
            'searchbutton' => $str('searchbutton'),
        ];
        return [
            'templates' => [['id' => 'main', 'html' => $OUTPUT->render_from_template(
                'local_oksigeniaayvurenda/mobile_library',
                self::inert($data)
            )]],
            'javascript' => '',
            'otherdata' => $otherdata,
        ];
    }

    /**
     * The reader's card alone, to show at the desk: their name, the library, the QR and the barcode, big.
     *
     * @param array $args What the app sends.
     * @return array
     */
    public static function mobile_card_view(array $args): array {
        global $DB, $OUTPUT, $USER;
        $str = fn(string $key) => get_string($key, 'local_oksigeniaayvurenda');
        $cards = [];
        foreach (mine::summary((int) $USER->id) as $library) {
            if ($library['cardcode'] !== '') {
                // Not «library» nor «name»: the page's own would stand in for what is missing in a list.
                // A young reader's card in the app shows its flower too, as on the web (jue-carne D13).
                $reader = $DB->get_record('local_oksigeniaayvurenda_reader', ['id' => $library['readerid'] ?? 0]);
                $flower = $reader && \local_oksigeniaayvurenda\local\cards::look_of($reader) === 'flower'
                    ? \local_oksigeniaayvurenda\local\cards::flower_url(
                        \local_oksigeniaayvurenda\local\garden::album($reader)['flower']['stage']
                    ) : '';
                $cards[] = ['where' => $library['name'], 'cardcode' => $library['cardcode'], 'fullname' => fullname($USER),
                    'flowerimg' => $flower] + self::codes($library['cardcode'], 8);
            }
        }
        $data = ['cards' => $cards, 'hascards' => (bool) $cards, 'notreader' => $str('mobilenotreader'),
            'hint' => $str('mobilecardhint')];
        return [
            'templates' => [['id' => 'main', 'html' => $OUTPUT->render_from_template(
                'local_oksigeniaayvurenda/mobile_card',
                self::inert($data)
            )]],
            'javascript' => '',
            'otherdata' => [],
        ];
    }

    /**
     * A card's code as pictures embedded in the page. Not «barcode» nor «qr»: other templates use those names for drawings,
     * not addresses.
     *
     * @param string $code
     * @param int $qrscale The size of each square of the QR.
     * @return array barcodeimg and qrimg, empty when they cannot be drawn.
     */
    private static function codes(string $code, int $qrscale = 4): array {
        return [
            'barcodeimg' => $code !== '' && code39::can($code)
                ? 'data:image/svg+xml;base64,' . base64_encode(code39::svg($code, 60, false)) : '',
            'qrimg' => $code !== ''
                ? 'data:image/png;base64,' . base64_encode((new \core_qrcode($code))->getBarcodePngData($qrscale, $qrscale)) : '',
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
            'name' => $library ? format_string($library->name, true, ['escape' => false]) : '',
            'cards' => array_map(
                fn($c) => ['id' => $c['id'], 'title' => $c['title'], 'authors' => $c['authors'],
                'statelabel' => $c['statelabel'], 'free' => $c['state'] === 'free', 'location' => $c['location'] ?? ''],
                $cards
            ),
            'hascards' => (bool) $cards,
            'searched' => $query !== '',
            'cannot' => !$library,
            'notreader' => $library ? '' : self::not_here(),
            'nothing' => $str('mobilenothing', s($query)),
            'searchplaceholder' => $str('searchplaceholder'),
            'searchbutton' => $str('searchbutton'),
        ];
        return [
            'templates' => [['id' => 'main', 'html' => $OUTPUT->render_from_template(
                'local_oksigeniaayvurenda/mobile_search',
                self::inert($data)
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
        global $DB, $OUTPUT, $USER;
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        $library = self::library($args);
        $found = $library ? catalogue::record((int) ($args['id'] ?? 0), $library) : null;
        $data = ['cannot' => !$found, 'notreader' => $found ? '' : self::not_here()];
        if ($found) {
            $card = screens::cards([$found['card']], $library)[0];
            $box = screens::hold($found, $library) ?? [];
            // Their own loan of it: more time, if they may still have it.
            $until = null;
            if (!empty($box['loanid'])) {
                $loan = $DB->get_record('local_oksigeniaayvurenda_loan', ['id' => $box['loanid']]);
                $where = $DB->get_record('local_oksigeniaayvurenda_library', ['id' => $loan->libraryid]);
                $until = renewals::until($where, (int) $loan->readerid, (int) $loan->id, (int) $USER->id);
            }
            $copies = array_values(array_filter($found['copies'], fn($c) => $c['here']));
            $mineis = (string) ($box['minekind'] ?? '');
            $words = fn(string $key) => get_string(skins::words((int) $library->entityid, $key), 'local_oksigeniaayvurenda');
            $date = !empty($box['mineuntil'])
                ? zones::date((int) $box['mineuntil'], 'strftimedateshort', $library) : '';
            $late = $mineis === 'loan' && (int) $box['mineuntil'] < time();
            // One copy goes in the slip; of several, the list says where each one is.
            $one = !$mineis && count($copies) === 1 && $card['state'] !== 'out' ? $copies[0] : null;
            $drawn = mail::book((int) $found['record']->id, $card['title']);
            $data += [
                'libraryname' => format_string($library->name, true, ['escape' => false]),
                'library' => $library->slug,
                'recordid' => (int) $found['record']->id,
                'title' => $card['title'],
                'subtitle' => (string) ($card['subtitle'] ?? ''),
                'authors' => $card['authors'],
                'cover' => self::cover((int) $found['record']->id),
                'coverbg' => $drawn['coverbg'],
                'coverink' => $drawn['coverink'],
                'statelabel' => [
                    'ready' => $words('holdreadyheading'),
                    'loan' => $str('mobilestateloan'),
                    'waiting' => $str('mobilestatewaiting'),
                ][$mineis] ?? $card['statelabel'],
                'stateclass' => $mineis ? 'mine' : ($card['state'] === 'free' ? 'free' : 'out'),
                'stamplabel' => $mineis === 'ready' ? $words('holdreadyuntil')
                    : ($late ? $str('mobileloanlate') : $str('mobileloanuntil')),
                'stampvalue' => in_array($mineis, ['ready', 'loan'], true) ? $date : '',
                'stamplate' => $late,
                'sliptext' => $mineis === 'ready' ? $words('holdreadylead')
                    : ($mineis === 'waiting' ? $str('holdminewaiting') : ''),
                'where' => $one ? (string) $one['location'] : '',
                'tejuelo' => $one && $one['callnumber'] ? $str('mobilecallnumber', $one['callnumber']) : '',
                'copies' => array_map(fn($c) => ['state' => $str('state' . $c['state']), 'location' => (string) $c['location'],
                    'tejuelo' => $c['callnumber'] ? $str('mobilecallnumber', $c['callnumber']) : ''], $copies),
                'hascopies' => !$mineis && count($copies) > 1,
                'copiesheading' => $str('copies'),
                'mine' => $mineis !== '',
                'holdid' => (int) ($box['holdid'] ?? 0),
                'holdkeepwaiting' => $str('holdkeepwaiting'),
                'holdletgo' => $str('holdletgo'),
                'holdcancelask' => $str('holdcancelask'),
                'holdcancelled' => $str('holdcancelled'),
                'holdcancelmine' => $str('holdcancelmine'),
                'ahead' => $box['ahead'] ?? '',
                'canask' => !empty($box['canask']),
                'loanid' => (int) ($box['loanid'] ?? 0),
                'canrenew' => $until !== null,
                'renew' => $str('mobilerenew'),
                'renewtoast' => $until
                    ? $str('mobilerenewedto', zones::date($until, 'strftimedateshort', $library)) : '',
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
                self::inert($data)
            )]],
            'javascript' => '',
            // Whether the reader is being asked if they let go of the book (a question in the page, not a dialog).
            'otherdata' => ['letgo' => 0],
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
     * The page's data with no braces nor at signs in its texts. The app compiles these templates as Angular ones, and
     * Mustache escapes neither: a title brought from Open Library, Wikisource or an imported catalogue with «{{ … }}» or
     * «@let» in it would run in the app with the reader's session, and an «@if» would not let the page compile. Each one
     * becomes its full-width look-alike, which reads the same; in an address, an at sign means nothing and stays.
     *
     * @param mixed $data
     * @param string $key The name it goes by: an address only counts as one where one is expected, not because of how it starts.
     * @return mixed
     */
    private static function inert($data, string $key = '') {
        if (is_array($data)) {
            $inert = [];
            foreach ($data as $name => $value) {
                $inert[$name] = self::inert($value, is_string($name) ? $name : $key);
            }
            return $inert;
        }
        if (!is_string($data)) {
            return $data;
        }
        $address = preg_match('/(url|img|cover|portrait|picture)$/i', $key);
        if ($address && str_starts_with($data, 'data:')) {
            return $data;
        }
        // Entities first: the app reads them before it looks for expressions, and Mustache leaves the numeric ones as they are.
        do {
            $before = $data;
            $data = html_entity_decode($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            // What still looks like an entity is none, and would stop the app from compiling the whole page.
            if ($data === $before) {
                $data = preg_replace('/&#(\d+|x[0-9a-f]+);/i', '', $data);
            }
        } while ($data !== $before);
        $map = ['{' => '｛', '}' => '｝'];
        return strtr($data, $address && preg_match('#^https?://#i', $data) ? $map : $map + ['@' => '＠']);
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
