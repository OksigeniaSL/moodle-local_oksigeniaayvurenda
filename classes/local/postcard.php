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
 * The postcard «Mi primer libro» (Gaudí, 13 November): what a young reader's family keeps and sends on.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * Drawn from a loan each time it is asked for, nothing kept: Gaudí's background (by day or at dusk, so that two siblings do
 * not have the same one), the cover of the book held by its tapes, the child's first name on the label of the seed, the
 * day and the library. A JPEG: some servers' GD has no WebP, and every phone shows a JPEG.
 */
final class postcard {
    /** @var string Prefix of the tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var string Where the postcard's layers are in the art package. */
    private const DIR = '/pix/postal/';

    /**
     * The postcard of a loan, if the art package has the postcard.
     *
     * @param int $loanid
     * @return string|null The JPEG.
     */
    public static function make(int $loanid): ?string {
        global $DB;
        $loan = $DB->get_record_sql(
            "SELECT l.id, l.readerid, l.timeloaned, l.libraryid, i.recordid, rec.title, rec.extra, r.userid, r.firstname,
                    r.entityid
               FROM {" . self::T . "loan} l
               JOIN {" . self::T . "item} i ON i.id = l.itemid
               JOIN {" . self::T . "record} rec ON rec.id = i.recordid
               JOIN {" . self::T . "reader} r ON r.id = l.readerid
              WHERE l.id = :id AND r.status = 'active'",
            ['id' => $loanid]
        );
        if (!$loan) {
            return null;
        }
        $user = $loan->userid ? \core_user::get_user((int) $loan->userid) : null;
        $file = get_file_storage()->get_file(
            \context_system::instance()->id,
            'local_oksigeniaayvurenda',
            'cover',
            (int) $loan->recordid,
            '/',
            'cover.jpg'
        );
        $extra = json_decode((string) $loan->extra, true);
        return self::compose([
            'cover' => $file ? $file->get_content() : null,
            // The covers of the example library leave a band for the title, which the pages write over (Gaudí).
            'band' => ($extra['cover']['source'] ?? '') === 'demo',
            'title' => format_string($loan->title, true, ['escape' => false]),
            'name' => trim((string) ($user->firstname ?? $loan->firstname)),
            'day' => zones::date((int) $loan->timeloaned, 'strftimedate', (int) $loan->entityid),
            'library' => format_string(
                (string) $DB->get_field(self::T . 'library', 'name', ['id' => $loan->libraryid]),
                true,
                ['escape' => false]
            ),
            'night' => (int) $loan->readerid % 2 === 1,
        ]);
    }

    /**
     * The postcard with example data, small, for «How your library works» to show what families get (Jujol): a cover of
     * the art package, «Leo», today, and the library's own name.
     *
     * @param \stdClass $library
     * @param int $width
     * @return string|null A data address of the JPEG.
     */
    public static function sample(\stdClass $library, int $width = 480): ?string {
        $art = \core_component::get_component_directory('local_oksigeniaayvurendaarte');
        $cover = $art && is_readable($art . '/pix/portadas/libro-abuela.jpg')
            ? file_get_contents($art . '/pix/portadas/libro-abuela.jpg') : null;
        $jpeg = self::compose([
            'cover' => $cover ?: null,
            'band' => true,
            'title' => get_string('hiwfirstbooksamplebook', 'local_oksigeniaayvurenda'),
            'name' => get_string('hiwfirstbooksamplename', 'local_oksigeniaayvurenda'),
            'day' => zones::date(time(), 'strftimedate', (int) $library->entityid),
            'library' => format_string($library->name, true, ['escape' => false]),
            'night' => false,
        ], $width);
        return $jpeg === null ? null : 'data:image/jpeg;base64,' . base64_encode($jpeg);
    }

    /**
     * Draws a postcard: Gaudí's background, the cover in its hole under the tapes, and the texts in their boxes.
     *
     * @param array $data cover (its bytes, or null), band (the title written on the cover), title, name, day, library,
     *     night.
     * @param int $width Smaller than the drawing's 1200, for a page.
     * @return string|null The JPEG; null without the art package's postcard or without fonts in GD.
     */
    private static function compose(array $data, int $width = 0): ?string {
        $art = \core_component::get_component_directory('local_oksigeniaayvurendaarte');
        if (!$art || !function_exists('imagettftext') || !is_readable($art . self::DIR . 'huecos.json')) {
            return null;
        }
        $holes = json_decode((string) file_get_contents($art . self::DIR . 'huecos.json'), true);
        $base = @imagecreatefromjpeg($art . self::DIR . ($data['night'] ? 'fondo-noche.jpg' : 'fondo-dia.jpg'));
        if (!$base) {
            return null;
        }
        imagealphablending($base, true);
        // The cover, filling its hole from the centre (no bands), under the tapes.
        $hole = $holes['huecos']['portada'];
        $cover = $data['cover'] !== null ? @imagecreatefromstring($data['cover']) : false;
        if ($cover) {
            [$w, $h] = [imagesx($cover), imagesy($cover)];
            $scale = max($hole['w'] / $w, $hole['h'] / $h);
            [$sw, $sh] = [(int) round($hole['w'] / $scale), (int) round($hole['h'] / $scale)];
            imagecopyresampled(
                $base,
                $cover,
                $hole['x'],
                $hole['y'],
                (int) (($w - $sw) / 2),
                (int) (($h - $sh) / 2),
                $hole['w'],
                $hole['h'],
                $sw,
                $sh
            );
            if ($data['band']) {
                self::text(
                    $base,
                    $art,
                    ['x' => $hole['x'] + 14, 'y' => $hole['y'] + 22, 'w' => 272, 'h' => 70,
                    'fuente' => 'Literata-SemiBold.ttf', 'cuerpo' => 34, 'minimo' => 20, 'alinear' => 'centro'],
                    $data['title'],
                    '#17574c'
                );
            }
        } else {
            // Without a cover, its title on the paper of the house, as the shelves draw a book without one.
            $paper = imagecolorallocate($base, 0xf2, 0xea, 0xdb);
            imagefilledrectangle($base, $hole['x'], $hole['y'], $hole['x'] + $hole['w'], $hole['y'] + $hole['h'], $paper);
            self::text(
                $base,
                $art,
                ['x' => $hole['x'] + 20, 'y' => $hole['y'] + 160, 'w' => $hole['w'] - 40, 'h' => 120,
                'fuente' => 'Literata-SemiBold.ttf', 'cuerpo' => 34, 'minimo' => 20, 'alinear' => 'centro'],
                $data['title'],
                '#1e2b2a'
            );
        }
        $tapes = @imagecreatefrompng($art . self::DIR . 'encima.png');
        if ($tapes) {
            imagecopy($base, $tapes, 0, 0, 0, 0, imagesx($tapes), imagesy($tapes));
        }
        $colours = $holes['colores'][$data['night'] ? 'noche' : 'dia'];
        $first = preg_split('/\s+/u', $data['name'])[0] ?? $data['name'];
        self::text(
            $base,
            $art,
            $holes['huecos']['titulo'],
            get_string('postcardtitle', 'local_oksigeniaayvurenda'),
            $colours['titulo']
        );
        self::text($base, $art, $holes['huecos']['nombre'], $first, $colours['nombre']);
        self::text($base, $art, $holes['huecos']['fecha'], $data['day'], $colours['fecha']);
        self::text($base, $art, $holes['huecos']['biblioteca'], $data['library'], $colours['biblioteca']);
        if ($width > 0 && $width < imagesx($base)) {
            $base = imagescale($base, $width, -1, IMG_BICUBIC);
        }
        ob_start();
        imagejpeg($base, null, $width > 0 ? 82 : 88);
        return (string) ob_get_clean();
    }

    /**
     * A text in its box: centred up and down by its ink, to the left or in the middle; smaller down to its minimum if it
     * does not fit, and cut to the width if not even then.
     *
     * @param \GdImage $image
     * @param string $art The art package's folder.
     * @param array $box x, y, w, h, fuente, cuerpo, minimo, alinear.
     * @param string $text
     * @param string $colour #rrggbb
     */
    private static function text($image, string $art, array $box, string $text, string $colour): void {
        $font = $art . '/fuentes/' . $box['fuente'];
        if (!is_readable($font) || $text === '') {
            return;
        }
        $rgb = sscanf($colour, '#%02x%02x%02x');
        $ink = imagecolorallocate($image, $rgb[0], $rgb[1], $rgb[2]);
        // The boxes say pixels; GD draws points, at 96 per inch: three points are four pixels.
        $size = (float) $box['cuerpo'] * 0.75;
        $least = (float) ($box['minimo'] ?? $box['cuerpo']) * 0.75;
        $measure = fn(string $t, float $s) => imagettfbbox($s, 0, $font, $t);
        $bbox = $measure($text, $size);
        while ($bbox[2] - $bbox[0] > $box['w'] && $size > $least) {
            $size -= 1.5;
            $bbox = $measure($text, $size);
        }
        while ($bbox[2] - $bbox[0] > $box['w'] && \core_text::strlen($text) > 1) {
            $text = \core_text::substr($text, 0, -1);
            $bbox = $measure($text, $size);
        }
        $width = $bbox[2] - $bbox[0];
        $height = $bbox[1] - $bbox[7];
        $x = ($box['alinear'] ?? 'izquierda') === 'centro' ? $box['x'] + ($box['w'] - $width) / 2 : $box['x'];
        $y = $box['y'] + ($box['h'] - $height) / 2 - $bbox[7];
        imagettftext($image, $size, 0, (int) round($x - $bbox[0]), (int) round($y), $ink, $font, $text);
    }
}
