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
 * The diploma of a full bingo card (a «subidón» of the review of 12 November): to keep, print and send on.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * When a reader fills the nine squares of a bingo card, the library gives them a diploma with its name and its logo: Gaudí's
 * background, the reader's first name, the card's name, the day and the nine drawings, each with its seal. Drawn each time
 * from the card, nothing kept, and only with the art package. The reader downloads it from their card; the staff print it
 * for a reader without an account; and, once the centre turns it on, the family of a young reader (the card of the flower)
 * gets it the next morning, in the centre's own.
 */
final class diploma {
    /** @var string Prefix of the tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var string Where the diploma's layers are in the art package. */
    private const DIR = '/pix/diploma/';

    /** @var int Width of the diploma on a page (the card, the mail). */
    public const SMALL = 640;

    /**
     * Whether a centre sends it to the families: only once it turned it on, as the postcard of the first book.
     *
     * @param int $entityid
     * @return bool
     */
    public static function on(int $entityid): bool {
        global $DB;
        $entity = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        return !empty($entity['diploma']);
    }

    /**
     * Saves whether a centre sends it to the families.
     *
     * @param int $entityid
     * @param bool $on
     */
    public static function save(int $entityid, bool $on): void {
        global $DB;
        $entity = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        $entity['diploma'] = $on;
        $DB->set_field(self::T . 'entity', 'settings', json_encode($entity), ['id' => $entityid]);
    }

    /**
     * Whether diplomas can be drawn here: the art package brings Gaudí's, and GD writes with fonts.
     *
     * @return bool
     */
    public static function drawable(): bool {
        $art = \core_component::get_component_directory('local_oksigeniaayvurendaarte');
        return $art && function_exists('imagettftext') && is_readable($art . self::DIR . 'huecos.json');
    }

    /**
     * A reader's full card of a challenge, as the diploma tells it.
     *
     * @param int $joinedid The reader in the challenge (questreader).
     * @param int $now
     * @return array|null quest, library, reader, card (as cartilla::card gives it) and day (when it filled); null if
     *     the card is not full or something is missing.
     */
    public static function of(int $joinedid, int $now): ?array {
        global $DB;
        $joined = $DB->get_record(self::T . 'questreader', ['id' => $joinedid]);
        $quest = $joined ? $DB->get_record(self::T . 'quest', ['id' => $joined->questid]) : null;
        if (!$quest || !cartilla::complete($quest)) {
            return null;
        }
        $library = $DB->get_record(self::T . 'library', ['id' => $quest->libraryid]);
        $reader = $DB->get_record(self::T . 'reader', ['id' => $joined->readerid, 'status' => 'active']);
        if (!$library || !$reader) {
            return null;
        }
        $card = cartilla::card($quest, $library, (int) $reader->id, $now, self::str());
        if (!$card['full']) {
            return null;
        }
        // The day it filled: that of the last book its squares took.
        $day = max(array_column($card['squares'], 'time'));
        return ['joined' => $joined, 'quest' => $quest, 'library' => $library, 'reader' => $reader, 'card' => $card,
            'day' => $day];
    }

    /**
     * The reader in a challenge, if they play it.
     *
     * @param int $questid
     * @param int $readerid
     * @return int Its id (questreader), or 0.
     */
    public static function joined(int $questid, int $readerid): int {
        global $DB;
        return (int) $DB->get_field(self::T . 'questreader', 'id', ['questid' => $questid, 'readerid' => $readerid]);
    }

    /**
     * The diplomas of a reader's full cards, for the staff to print for a reader without an account (or hand over at the
     * desk): those of the challenges still on their card.
     *
     * @param \stdClass $reader
     * @param int $now
     * @param callable $str
     * @return array[] name, img, url, alt and save.
     */
    public static function of_reader(\stdClass $reader, int $now, callable $str): array {
        global $DB;
        if (!self::drawable()) {
            return [];
        }
        $list = [];
        foreach (quests::open($reader, $now) as $quest) {
            if (empty($quest->joinedid) || !cartilla::complete($quest)) {
                continue;
            }
            $library = $DB->get_record(self::T . 'library', ['id' => $quest->libraryid]);
            if ($library && cartilla::card($quest, $library, (int) $reader->id, $now, $str)['full']) {
                $list[] = ['name' => format_string($quest->name, true, ['escape' => false])]
                    + cartilla::diploma_links($quest, $library, (int) $reader->id, $str, true);
            }
        }
        return $list;
    }

    /**
     * The bingo cards filled in the day before now: by readers who took a book home in it, full now and not a day ago.
     *
     * @param int $now
     * @param int[]|null $centres Only these centres.
     * @return array[] joinedid, entityid and day, as of() gives them with the rest.
     */
    public static function due(int $now, ?array $centres = null): array {
        global $DB;
        $since = $now - DAYSECS;
        $rows = $DB->get_records_sql(
            "SELECT qr.id, qr.questid, b.entityid
               FROM {" . self::T . "questreader} qr
               JOIN {" . self::T . "quest} q ON q.id = qr.questid
               JOIN {" . self::T . "library} b ON b.id = q.libraryid
               JOIN {" . self::T . "reader} r ON r.id = qr.readerid
              WHERE q.timestart < :now AND q.timeend >= :since AND r.status = 'active'
                    AND EXISTS (SELECT 1
                                  FROM {" . self::T . "loan} l
                                 WHERE l.readerid = qr.readerid AND l.timeloaned >= :since2 AND l.timeloaned < :now2)
           ORDER BY qr.id",
            ['now' => $now, 'since' => $since, 'since2' => $since, 'now2' => $now]
        );
        $due = [];
        foreach ($rows as $row) {
            if ($centres !== null && !in_array((int) $row->entityid, $centres, true)) {
                continue;
            }
            $full = self::of((int) $row->id, $now);
            if (!$full || $full['day'] < $since) {
                continue;
            }
            // Full a day ago already: it is no news.
            $before = cartilla::card($full['quest'], $full['library'], (int) $full['reader']->id, $since, self::str());
            if (!$before['full']) {
                $due[] = $full + ['joinedid' => (int) $row->id, 'entityid' => (int) $row->entityid];
            }
        }
        return $due;
    }

    /**
     * Notes the day each card filled in the day before now (also for whoever has not looked at their card since), and
     * sends its diploma to the family of each young reader, in their centres' morning.
     *
     * @param int $now
     * @param int[]|null $centres Only these centres (those whose morning it is).
     * @return int Adults told.
     */
    public static function run(int $now, ?array $centres = null): int {
        global $DB;
        $told = 0;
        foreach (self::due($now, $centres) as $full) {
            if (empty($full['joined']->timedone)) {
                $DB->set_field(self::T . 'questreader', 'timedone', $full['day'], ['id' => $full['joinedid']]);
            }
            $entityid = $full['entityid'];
            if (
                !class_exists('\\local_oksigeniafamily\\api') || !self::drawable() || !self::on($entityid)
                    || practice::is($entityid) || access::closed($entityid)
            ) {
                continue;
            }
            if (empty($full['reader']->userid) || cards::look_of($full['reader'], $now) !== 'flower') {
                continue;
            }
            // One family whose message fails does not leave the others untold.
            try {
                $told += self::tell($full);
            } catch (\Throwable $e) {
                debugging('Ayvurenda: the diploma of reader ' . $full['reader']->id . ' was not sent: ' . $e->getMessage());
            }
        }
        return $told;
    }

    /**
     * Sends a young reader's diploma to their family: one message to each adult.
     *
     * @param array $full As due() gives it.
     * @return int Adults told.
     */
    public static function tell(array $full): int {
        $reader = $full['reader'];
        $library = $full['library'];
        $child = \core_user::get_user((int) $reader->userid);
        if (!$child) {
            return 0;
        }
        $entityid = (int) $library->entityid;
        $image = mail::diploma((int) $full['joinedid']);
        $told = 0;
        $seen = [];
        foreach (\local_oksigeniafamily\api::adults_of_child((int) $child->id) as $adult) {
            $user = $adult->userid ? \core_user::get_user((int) $adult->userid) : null;
            if ($user && (!empty($user->deleted) || !empty($user->suspended))) {
                continue;
            }
            $email = trim((string) $adult->email);
            if (!$user && !validate_email($email)) {
                continue;
            }
            $key = $user ? 'u' . $user->id : 'e' . \core_text::strtolower($email);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $language = force_current_language((string) ($user->lang ?? ''));
            $a = (object) ['child' => $child->firstname,
                'cartilla' => format_string($full['quest']->name, true, ['escape' => false]),
                'library' => format_string($library->name, true, ['escape' => false]),
                'day' => zones::date((int) $full['day'], 'strftimedateshort', $entityid)];
            $str = fn(string $key) => get_string($key, 'local_oksigeniaayvurenda', $a);
            $subject = $str('diplomasubject');
            $hello = get_string('mailhello', 'local_oksigeniaayvurenda', $adult->firstname);
            $design = [
                'library' => $library,
                'entityid' => $entityid,
                'hello' => $hello,
                'heading' => $subject,
                'lead' => [$str('diplomalead')],
                'postcard' => ['url' => $image, 'alt' => $str('diplomaalt')],
                'button' => ['url' => $image, 'text' => $str('diplomasave')],
                'after' => [$str('diplomaafter')],
                'why' => get_string('mailwhyfamily', 'local_oksigeniaayvurenda'),
            ];
            $text = $hello . "\n\n" . $str('diplomalead') . "\n\n" . $str('diplomaafter') . "\n" . $image;
            if ($user) {
                $sent = notices::send('diploma', $user, $subject, $text, $image, $str('diplomasave'), $a->library, $design);
            } else {
                $to = clone \core_user::get_noreply_user();
                $to->email = $email;
                $to->firstname = (string) $adult->firstname;
                $to->lastname = (string) $adult->lastname;
                $to->mailformat = 1;
                $sent = (bool) email_to_user($to, mail::from($a->library), $subject, $text, mail::html($design));
            }
            force_current_language($language);
            $told += $sent ? 1 : 0;
        }
        return $told;
    }

    /**
     * The diploma of a reader's full card.
     *
     * @param int $joinedid The reader in the challenge.
     * @param int $width 0 for the whole drawing, to print.
     * @param int|null $now
     * @return string|null The JPEG; null if the card is not full or there is no diploma to draw.
     */
    public static function make(int $joinedid, int $width = 0, ?int $now = null): ?string {
        if (!self::drawable()) {
            return null;
        }
        $full = self::of($joinedid, $now ?? time());
        if (!$full) {
            return null;
        }
        $reader = $full['reader'];
        $user = $reader->userid ? \core_user::get_user((int) $reader->userid) : null;
        $library = $full['library'];
        $entityid = (int) $library->entityid;
        $names = cartilla::art_names($library);
        return self::compose([
            'name' => trim((string) ($user->firstname ?? $reader->firstname)),
            'cartilla' => format_string($full['quest']->name, true, ['escape' => false]),
            'day' => zones::date((int) $full['day'], 'strftimedate', $entityid),
            'library' => format_string($library->name, true, ['escape' => false]),
            'logo' => self::logo($entityid),
            'squares' => array_map(
                fn($square) => ['art' => $names[$square['spec']] ?? '', 'label' => $square['label']],
                $full['card']['squares']
            ),
        ], $width);
    }

    /**
     * The diploma with example data, small, for «How your library works» to show what readers and families get: «Leo»,
     * a card of the library's own squares, today, and the library's name and logo.
     *
     * @param \stdClass $library
     * @param int $width
     * @return string|null A data address of the JPEG.
     */
    public static function sample(\stdClass $library, int $width = 480): ?string {
        if (!self::drawable()) {
            return null;
        }
        $names = cartilla::art_names($library);
        $labels = cartilla::labels($library, self::str());
        $specs = array_slice(array_values(array_unique(array_merge(
            array_slice(array_keys($names), 0, 6),
            cartilla::SPECIAL
        ))), 0, 9);
        $jpeg = self::compose([
            'name' => get_string('hiwfirstbooksamplename', 'local_oksigeniaayvurenda'),
            'cartilla' => get_string('hiwdiplomasamplecard', 'local_oksigeniaayvurenda'),
            'day' => zones::date(time(), 'strftimedate', (int) $library->entityid),
            'library' => format_string($library->name, true, ['escape' => false]),
            'logo' => self::logo((int) $library->entityid),
            'squares' => array_map(fn($spec) => ['art' => $names[$spec] ?? '', 'label' => $labels[$spec] ?? ''], $specs),
        ], $width);
        return $jpeg === null ? null : 'data:image/jpeg;base64,' . base64_encode($jpeg);
    }

    /**
     * A centre's logo, as it is (with its transparency): the diploma has its own paper.
     *
     * @param int $entityid
     * @return \GdImage|null
     */
    private static function logo(int $entityid): ?\GdImage {
        $file = images::file(cardprint::LOGO, $entityid, 'logo');
        $image = $file ? @imagecreatefromstring($file->get_content()) : false;
        if (!$image) {
            return null;
        }
        imagepalettetotruecolor($image);
        imagealphablending($image, true);
        imagesavealpha($image, true);
        return $image;
    }

    /**
     * Draws a diploma: Gaudí's background, the nine drawings in the grid, the logo, what goes on top, and the texts.
     *
     * @param array $data name, cartilla, day, library, logo (or null), squares (art: the drawing's name, label).
     * @param int $width Smaller than the drawing, for a page or a mail.
     * @return string|null The JPEG.
     */
    private static function compose(array $data, int $width = 0): ?string {
        $art = \core_component::get_component_directory('local_oksigeniaayvurendaarte');
        $layout = json_decode((string) file_get_contents($art . self::DIR . 'huecos.json'), true);
        $holes = (array) ($layout['huecos'] ?? []);
        $colours = (array) ($layout['colores'] ?? []);
        $base = @imagecreatefromjpeg($art . self::DIR . 'fondo.jpg');
        if (!$base) {
            return null;
        }
        imagealphablending($base, true);
        // The nine squares, three by three, each with its drawing (a JPEG copy: some servers' GD has no WebP) and its seal.
        $grid = $holes['rejilla'] ?? null;
        if ($grid) {
            $gap = (int) ($grid['separacion'] ?? 0);
            $step = (min($grid['w'], $grid['h']) - 2 * $gap) / 3;
            $cell = (int) round($step);
            $seal = @imagecreatefrompng($art . self::DIR . 'sello.png');
            // The seal on the corner up and to the right, a little out of the square, as Gaudí lays it.
            $sealside = (int) round($step * (float) ($holes['sello']['lado'] ?? 0.4));
            $out = (int) round($step * (float) ($holes['sello']['fuera'] ?? 0));
            $paper = imagecolorallocate($base, 0xf2, 0xea, 0xdb);
            foreach (array_values($data['squares']) as $i => $square) {
                if ($i > 8) {
                    break;
                }
                $x = (int) round($grid['x'] + ($i % 3) * ($step + $gap));
                $y = (int) round($grid['y'] + intdiv($i, 3) * ($step + $gap));
                $file = $art . self::DIR . 'casillas/' . $square['art'] . '.jpg';
                $drawing = $square['art'] !== '' && is_readable($file) ? @imagecreatefromjpeg($file) : false;
                if ($drawing) {
                    imagecopyresampled($base, $drawing, $x, $y, 0, 0, $cell, $cell, imagesx($drawing), imagesy($drawing));
                } else {
                    // A square without its drawing (a corner the art has none for): its name on the paper of the house.
                    imagefilledrectangle($base, $x, $y, $x + $cell - 1, $y + $cell - 1, $paper);
                    postcard::text(
                        $base,
                        $art,
                        ['x' => $x + 8, 'y' => $y + 8, 'w' => $cell - 16, 'h' => $cell - 16,
                        'fuente' => 'Andika-Regular.ttf', 'cuerpo' => 26, 'minimo' => 14, 'alinear' => 'centro'],
                        $square['label'],
                        '#1e2b2a'
                    );
                }
                if ($seal) {
                    imagecopyresampled(
                        $base,
                        $seal,
                        $x + $cell - $sealside + $out,
                        $y - $out,
                        0,
                        0,
                        $sealside,
                        $sealside,
                        imagesx($seal),
                        imagesy($seal)
                    );
                }
            }
        }
        // The centre's logo, whole and centred in its box; without one, the box stays as Gaudí drew it.
        $box = $holes['logo'] ?? null;
        if ($box && $data['logo']) {
            [$lw, $lh] = [imagesx($data['logo']), imagesy($data['logo'])];
            $scale = min($box['w'] / $lw, $box['h'] / $lh, 1);
            [$dw, $dh] = [(int) round($lw * $scale), (int) round($lh * $scale)];
            imagecopyresampled(
                $base,
                $data['logo'],
                (int) ($box['x'] + ($box['w'] - $dw) / 2),
                (int) ($box['y'] + ($box['h'] - $dh) / 2),
                0,
                0,
                $dw,
                $dh,
                $lw,
                $lh
            );
        }
        $top = @imagecreatefrompng($art . self::DIR . 'encima.png');
        if ($top) {
            imagecopy($base, $top, 0, 0, 0, 0, imagesx($top), imagesy($top));
        }
        // The whole first name, as it is said: a «Gonzalo Andrés» is not a «Gonzalo».
        $texts = ['titulo' => get_string('diplomatitle', 'local_oksigeniaayvurenda'), 'nombre' => $data['name'],
            'cartilla' => $data['cartilla'], 'fecha' => $data['day'], 'biblioteca' => $data['library']];
        foreach ($texts as $hole => $text) {
            if (!isset($holes[$hole])) {
                continue;
            }
            $colour = (string) ($colours[$hole] ?? '#1e2b2a');
            if ((int) ($holes[$hole]['lineas'] ?? 1) >= 2) {
                self::lines($base, $art, $holes[$hole], $text, $colour);
            } else {
                postcard::text($base, $art, $holes[$hole], $text, $colour);
            }
        }
        if ($width > 0 && $width < imagesx($base)) {
            $base = imagescale($base, $width, -1, IMG_BICUBIC);
        }
        ob_start();
        imagejpeg($base, null, $width > 0 ? 84 : 90);
        return (string) ob_get_clean();
    }

    /**
     * A text that may take two lines (the challenge's name): in one, from its size down to its minimum; if it does not
     * fit even then, in two balanced at the space nearest the middle, both at the largest size that fits; centred.
     *
     * @param \GdImage $image
     * @param string $art The art package's folder.
     * @param array $box x, y, w, h, fuente, cuerpo, minimo.
     * @param string $text
     * @param string $colour #rrggbb
     */
    private static function lines($image, string $art, array $box, string $text, string $colour): void {
        $font = $art . '/fuentes/' . $box['fuente'];
        if (!is_readable($font) || $text === '') {
            return;
        }
        // The boxes say pixels; GD draws points: three points are four pixels.
        $width = function (string $line, int $px) use ($font): int {
            $bbox = imagettfbbox($px * 0.75, 0, $font, $line);
            return $bbox[2] - $bbox[0];
        };
        $least = (int) ($box['minimo'] ?? $box['cuerpo']);
        $lines = [$text];
        $size = 0;
        for ($px = (int) $box['cuerpo']; $px >= $least && !$size; $px -= 2) {
            $size = $width($text, $px) <= $box['w'] ? $px : 0;
        }
        if (!$size) {
            $size = $least;
            $cut = null;
            $half = strlen($text) / 2;
            for ($at = strpos($text, ' '); $at !== false; $at = strpos($text, ' ', $at + 1)) {
                $cut = $cut === null || abs($at - $half) < abs($cut - $half) ? $at : $cut;
            }
            if ($cut !== null) {
                $lines = [substr($text, 0, $cut), substr($text, $cut + 1)];
                for ($px = (int) $box['cuerpo']; $px >= $least; $px -= 2) {
                    if (max($width($lines[0], $px), $width($lines[1], $px)) <= $box['w'] && $px * 2.2 <= $box['h']) {
                        $size = $px;
                        break;
                    }
                }
            }
        }
        $rgb = sscanf($colour, '#%02x%02x%02x');
        $ink = imagecolorallocate($image, $rgb[0], $rgb[1], $rgb[2]);
        $lead = $size * 1.2;
        $top = $box['y'] + ($box['h'] - $lead * count($lines)) / 2;
        foreach ($lines as $k => $line) {
            $bbox = imagettfbbox($size * 0.75, 0, $font, $line);
            $x = $box['x'] + ($box['w'] - ($bbox[2] - $bbox[0])) / 2 - $bbox[0];
            // On the line's baseline, so that its middle (a third of the size above it) is the middle of its row.
            $y = $top + $lead * ($k + 0.5) + $size * 0.35;
            imagettftext($image, $size * 0.75, 0, (int) round($x), (int) round($y), $ink, $font, $line);
        }
    }

    /**
     * The strings of the plugin, as the bingo card asks for them.
     *
     * @return callable
     */
    private static function str(): callable {
        return fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
    }
}
