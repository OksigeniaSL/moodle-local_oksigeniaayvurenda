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
 * Ayvurenda: a PDF of reader cards, drawn from the design in the art package.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

use local_oksigeniaayvurenda\core\barcode\code39;

/**
 * Draws cards: for a printer, each face on its own page with its bleed and its trim box; on sheets, ten to a page, with the
 * backs on the next page in mirrored columns. Everything the scanner reads is pure black, one ink.
 */
final class cardpdf {
    /** @var float Width of the card, in mm. */
    private const W = 85.6;

    /** @var float Height of the card, in mm. */
    private const H = 54.0;

    /** @var float The bleed around it, in mm. */
    private const BLEED = 3.0;

    /** @var float[] A logo on the front: the right edge and top of its white plate, its size at most, and the air around. */
    private const PLATE = ['right' => 82.1, 'top' => 2.6, 'width' => 13.0, 'height' => 9.4, 'pad' => .7, 'gap' => 1.0];

    /** @var int[] Pure black, as CMYK. */
    private const BLACK = [0, 0, 0, 100];

    /** @var \pdf The document. */
    private \pdf $pdf;

    /** @var string[] The fonts, by the names the design gives them. */
    private array $fonts = [];

    /** @var array[] Cards waiting to fill a sheet. */
    private array $waiting = [];

    /**
     * Starts a document.
     *
     * @param array $design As carne.json has it.
     * @param string $folder Where its files are.
     * @param string $fonts Where the fonts are.
     * @param array|null $sheet Null for a printer; for sheets: columns, rows, width, height, left, top, gap, cuts, and page
     *     when each card is a page of the size of the plastic.
     * @param array $texts Of the back, by variant and box.
     * @param bool $backs Whether the cards have a back: a printer of one side only wants the fronts.
     * @param array $logo The centre's logo, if it has one: file and ratio.
     */
    public function __construct(
        /** @var array As carne.json has it. */
        private array $design,
        /** @var string Where its files are. */
        private string $folder,
        string $fonts,
        /** @var array|null The sheet, or null for a printer. */
        private ?array $sheet,
        /** @var array Of the back, by variant and box. */
        private array $texts,
        /** @var bool Whether the cards have a back. */
        private bool $backs = true,
        /** @var array The centre's logo: file and ratio. */
        private array $logo = []
    ) {
        global $CFG;
        require_once($CFG->libdir . '/pdflib.php');
        if (!empty($sheet['page'])) {
            $this->pdf = new \pdf('L', 'mm', [$sheet['width'], $sheet['height']]);
        } else if ($sheet) {
            $this->pdf = new \pdf('P', 'mm', 'A4');
        } else {
            $this->pdf = new \pdf('L', 'mm', [self::W + 2 * self::BLEED, self::H + 2 * self::BLEED]);
        }
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        $this->pdf->SetMargins(0, 0, 0);
        $this->pdf->SetAutoPageBreak(false);
        $this->pdf->setCellPaddings(0, 0, 0, 0);
        $this->pdf->setFontSubsetting(true);
        $cache = make_localcache_directory('local_oksigeniaayvurenda_fonts') . '/';
        foreach (['Literata-SemiBold', 'Andika-Regular', 'Andika-Bold'] as $font) {
            $name = \TCPDF_FONTS::addTTFfont($fonts . '/' . $font . '.ttf', 'TrueTypeUnicode', '', 32, $cache);
            $this->pdf->AddFont($name, '', $cache . $name . '.php');
            $this->fonts[$font] = $name;
        }
    }

    /**
     * Adds a card.
     *
     * @param array $person variant, name, group, stage, library, code, photo (a file, or ''), creature (number of a bichito).
     */
    public function card(array $person): void {
        if ($this->sheet) {
            $this->waiting[] = $person;
            if (count($this->waiting) === $this->sheet['columns'] * $this->sheet['rows']) {
                $this->flush();
            }
            return;
        }
        foreach ($this->backs ? ['front', 'back'] : ['front'] as $face) {
            $this->press_page();
            $this->$face($person, self::BLEED, self::BLEED);
        }
    }

    /**
     * A page for a printer: the sheet it is cut from is the trim box.
     */
    private function press_page(): void {
        $w = self::W + 2 * self::BLEED;
        $h = self::H + 2 * self::BLEED;
        $box = fn($inset) => ['llx' => $inset, 'lly' => $inset, 'urx' => $w - $inset, 'ury' => $h - $inset];
        $this->pdf->AddPage('L', ['MediaBox' => $box(0), 'BleedBox' => $box(0), 'TrimBox' => $box(self::BLEED),
            'ArtBox' => $box(self::BLEED), 'CropBox' => $box(0), 'Rotate' => 0]);
    }

    /**
     * The sheet waiting: a page of fronts and, behind it, a page of backs, its columns mirrored so that they fall behind.
     */
    private function flush(): void {
        if (!$this->waiting) {
            return;
        }
        $sheet = $this->sheet;
        foreach ($this->backs ? ['front', 'back'] : ['front'] as $face) {
            if (empty($sheet['page'])) {
                $this->pdf->AddPage('P', 'A4');
            } else {
                $this->pdf->AddPage('L', [$sheet['width'], $sheet['height']]);
            }
            foreach ($this->waiting as $index => $person) {
                $column = $index % $sheet['columns'];
                $row = (int) floor($index / $sheet['columns']);
                [$x, $y] = self::position($sheet, $face === 'back' ? $sheet['columns'] - 1 - $column : $column, $row);
                $this->on_sheet($face, $person, $x, $y);
            }
            if (!empty($sheet['cuts'])) {
                $this->cuts();
            }
        }
        $this->waiting = [];
    }

    /**
     * Where a card goes on a sheet.
     *
     * @param array $sheet
     * @param int $column
     * @param int $row
     * @return float[] x and y, in mm.
     */
    public static function position(array $sheet, int $column, int $row): array {
        return [$sheet['left'] + $column * ($sheet['width'] + $sheet['gap']), $sheet['top'] + $row * $sheet['height']];
    }

    /**
     * One face on a sheet: scaled to the size of its place, and cut at the edge of the card.
     *
     * @param string $face front or back.
     * @param array $person
     * @param float $x
     * @param float $y
     */
    private function on_sheet(string $face, array $person, float $x, float $y): void {
        $pdf = $this->pdf;
        $pdf->StartTransform();
        $pdf->Scale(100 * $this->sheet['width'] / self::W, 100 * $this->sheet['height'] / self::H, $x, $y);
        $pdf->Rect($x, $y, self::W, self::H, 'CNZ');
        $this->$face($person, $x, $y);
        $pdf->StopTransform();
    }

    /**
     * The hairlines to cut a plain sheet by, outside the cards.
     */
    private function cuts(): void {
        $sheet = $this->sheet;
        $pdf = $this->pdf;
        $pdf->SetDrawColorArray(self::BLACK);
        $pdf->SetLineWidth(.1);
        $right = $sheet['left'] + $sheet['columns'] * $sheet['width'];
        $bottom = $sheet['top'] + $sheet['rows'] * $sheet['height'];
        for ($i = 0; $i <= $sheet['columns']; $i++) {
            $x = $sheet['left'] + $i * $sheet['width'];
            $pdf->Line($x, $sheet['top'] - 6, $x, $sheet['top'] - 1);
            $pdf->Line($x, $bottom + 1, $x, $bottom + 6);
        }
        for ($i = 0; $i <= $sheet['rows']; $i++) {
            $y = $sheet['top'] + $i * $sheet['height'];
            $pdf->Line($sheet['left'] - 6, $y, $sheet['left'] - 1, $y);
            $pdf->Line($right + 1, $y, $right + 6, $y);
        }
    }

    /**
     * A sheet to lay over a pack of cards against the light: the outline of each place, and nothing else.
     *
     * @param string $words What it says, in the middle of each place.
     */
    public function test_sheet(string $words): void {
        $sheet = $this->sheet;
        $pdf = $this->pdf;
        $pdf->AddPage('P', 'A4');
        $pdf->SetDrawColorArray(self::BLACK);
        $pdf->SetLineWidth(.15);
        for ($row = 0; $row < $sheet['rows']; $row++) {
            for ($column = 0; $column < $sheet['columns']; $column++) {
                [$x, $y] = self::position($sheet, $column, $row);
                $pdf->Rect($x, $y, $sheet['width'], $sheet['height'], 'D');
                $pdf->SetFont($this->fonts['Andika-Regular'], '', 8);
                $pdf->SetTextColorArray(self::BLACK);
                $pdf->MultiCell($sheet['width'], 0, $words, 0, 'C', false, 1, $x, $y + $sheet['height'] / 2 - 2);
            }
        }
    }

    /**
     * The front of a card.
     *
     * @param array $person
     * @param float $x Left of the cut card.
     * @param float $y Top of the cut card.
     */
    private function front(array $person, float $x, float $y): void {
        $variant = $person['variant'];
        $boxes = $this->design['variants'][$variant]['front'];
        $this->ground($variant, 'front', $x, $y);
        $this->face($person, $boxes, $x, $y);
        // What is beside the logo makes room for it; what is lower down, as the sober design's label, stays.
        $taken = $this->logo ? $this->logo_plate($x, $y) : 0.0;
        foreach (['rotulo', 'biblioteca'] as $key) {
            if ($boxes[$key]['y'] < self::PLATE['top'] + self::PLATE['height']) {
                $boxes[$key]['w'] -= $taken;
            }
        }
        $this->text($boxes['rotulo'], $this->texts[$variant]['rotulo'] ?? '', $x, $y);
        $this->text($boxes['biblioteca'], $person['library'], $x, $y);
        $this->text($boxes['nombre'], $person['name'], $x, $y);
        $stage = $boxes['etapa'];
        if ($person['group'] !== '') {
            $this->text($boxes['grupo'], $person['group'], $x, $y);
        } else {
            $stage['y'] = $boxes['grupo']['y'];
        }
        $this->text($stage, $person['stage'], $x, $y);
        $this->bars($boxes['barras'], (string) $person['code'], $x, $y);
        $this->text($boxes['barras_texto'], (string) $person['code'], $x, $y, true);
        $this->qr($boxes['qr'], (string) $person['code'], $x, $y);
    }

    /**
     * The back of a card: the same for everyone of a kind.
     *
     * @param array $person
     * @param float $x Left of the cut card.
     * @param float $y Top of the cut card.
     */
    private function back(array $person, float $x, float $y): void {
        $variant = $person['variant'];
        $this->ground($variant, 'back', $x, $y);
        foreach ($this->design['variants'][$variant]['back'] as $key => $box) {
            $this->text($box, $this->texts[$variant][$key] ?? '', $x, $y);
        }
    }

    /**
     * The centre's logo, on its white plate at the top right, as wide as the logo asks up to its largest.
     *
     * @param float $x Left of the cut card.
     * @param float $y Top of the cut card.
     * @return float The width it takes from what is beside it.
     */
    private function logo_plate(float $x, float $y): float {
        $plate = self::PLATE;
        $inside = $plate['height'] - 2 * $plate['pad'];
        $width = min($inside * $this->logo['ratio'], $plate['width'] - 2 * $plate['pad']);
        $height = $width / $this->logo['ratio'];
        $wide = max($plate['height'], $width + 2 * $plate['pad']);
        $left = $x + $plate['right'] - $wide;
        $top = $y + $plate['top'];
        $this->pdf->RoundedRect($left, $top, $wide, $plate['height'], 1.2, '1111', 'F', [], [0, 0, 0, 0]);
        $this->pdf->Image(
            $this->logo['file'],
            $left + ($wide - $width) / 2,
            $top + ($plate['height'] - $height) / 2,
            $width,
            $height,
            'PNG'
        );
        return $wide + $plate['gap'];
    }

    /**
     * The ground of a face, with its bleed.
     *
     * @param string $variant
     * @param string $face
     * @param float $x
     * @param float $y
     */
    private function ground(string $variant, string $face, float $x, float $y): void {
        $file = $this->folder . '/' . $this->design['grounds'][$variant][$face];
        $this->pdf->Image($file, $x - self::BLEED, $y - self::BLEED, self::W + 2 * self::BLEED, self::H + 2 * self::BLEED, 'PNG');
    }

    /**
     * The photo, or their bichito, in the shape of the design.
     *
     * @param array $person
     * @param array $boxes
     * @param float $ox
     * @param float $oy
     */
    private function face(array $person, array $boxes, float $ox, float $oy): void {
        $pdf = $this->pdf;
        $place = $boxes['foto'];
        $file = $person['photo'];
        $type = 'JPEG';
        if ($file === '') {
            $tone = basename($boxes['sin_foto']['folder']);
            $file = sprintf('%s/bichos/%s/bicho-%02d.png', $this->folder, $tone, $person['creature']);
            $type = 'PNG';
        }
        if ($place['form'] === 'circle') {
            $r = $place['d'] / 2;
            $x = $ox + $place['cx'];
            $y = $oy + $place['cy'];
            $pdf->StartTransform();
            $pdf->Circle($x, $y, $r, 0, 360, 'CNZ');
            $pdf->Image($file, $x - $r, $y - $r, $place['d'], $place['d'], $type);
            $pdf->StopTransform();
            return;
        }
        $x = $ox + $place['x'];
        $y = $oy + $place['y'];
        $pdf->Image($file, $x, $y, $place['size'], $place['size'], $type);
        $pdf->SetDrawColorArray(self::rgb($place['keyline']['colour']));
        $pdf->SetLineWidth($place['keyline']['pt'] * 25.4 / 72);
        $pdf->Rect($x, $y, $place['size'], $place['size'], 'D');
    }

    /**
     * Some text in its box, in the size that fits, down to the smallest; beyond that, cut.
     *
     * @param array $box
     * @param string $text
     * @param float $ox
     * @param float $oy
     * @param bool $black For the scanner's side: one ink.
     */
    private function text(array $box, string $text, float $ox, float $oy, bool $black = false): void {
        $text = trim($text);
        if ($text === '') {
            return;
        }
        $pdf = $this->pdf;
        $text = !empty($box['caps']) ? \core_text::strtoupper($text) : $text;
        $size = (float) $box['pt'];
        $least = (float) ($box['size_min'] ?? $size);
        $lines = (int) ($box['lines'] ?? 1);
        $pdf->setCellHeightRatio((float) ($box['leading'] ?? 1.15));
        $font = $this->fonts[$box['font']];
        $pdf->SetFont($font, '', $size);
        // A long word (an e-mail) is not split: the size goes down until it fits on its line.
        $widest = fn() => max(array_map(fn($word) => $pdf->GetStringWidth($word), preg_split('/\s+/u', $text)));
        while (($pdf->getNumLines($text, $box['w']) > $lines || $widest() > $box['w']) && $size > $least) {
            $size = max($least, $size - .25);
            $pdf->SetFont($font, '', $size);
        }
        while ($pdf->getNumLines($text, $box['w']) > $lines && \core_text::strlen($text) > 1) {
            $text = rtrim(\core_text::substr(rtrim($text, '…'), 0, -1)) . '…';
        }
        $pdf->setFontSpacing(($box['tracking'] ?? 0) * $size * 25.4 / 72);
        $pdf->SetTextColorArray($black ? self::BLACK : self::rgb($box['colour']));
        $pdf->MultiCell($box['w'], 0, $text, 0, $box['align'] ?? 'L', false, 1, $ox + $box['x'], $oy + $box['y']);
        $pdf->setFontSpacing(0);
    }

    /**
     * The bars, centred in their box: a narrow bar of 0.45 mm, or what fits; under 0.25 mm, none.
     *
     * @param array $box
     * @param string $code
     * @param float $ox
     * @param float $oy
     */
    private function bars(array $box, string $code, float $ox, float $oy): void {
        if (!code39::can($code)) {
            return;
        }
        $widths = code39::widths($code);
        $modules = array_sum($widths);
        $narrow = min(.45, $box['w'] / $modules);
        if ($narrow < .25) {
            return;
        }
        $pdf = $this->pdf;
        $pdf->SetFillColorArray(self::BLACK);
        $x = $ox + $box['x'] + ($box['w'] - $modules * $narrow) / 2;
        foreach ($widths as $index => $width) {
            if ($index % 2 === 0) {
                $pdf->Rect($x, $oy + $box['y'], $width * $narrow, $box['h'], 'F', ['all' => ['width' => 0]]);
            }
            $x += $width * $narrow;
        }
    }

    /**
     * The QR, drawn module by module in pure black.
     *
     * @param array $box
     * @param string $code
     * @param float $ox
     * @param float $oy
     */
    private function qr(array $box, string $code, float $ox, float $oy): void {
        global $CFG;
        require_once($CFG->libdir . '/tcpdf/tcpdf_barcodes_2d.php');
        $matrix = (new \TCPDF2DBarcode($code, 'QRCODE,' . $box['level']))->getBarcodeArray();
        $module = $box['size'] / (int) $matrix['num_cols'];
        $pdf = $this->pdf;
        $pdf->SetFillColorArray(self::BLACK);
        foreach ($matrix['bcode'] as $row => $cells) {
            foreach ($cells as $column => $on) {
                if ($on) {
                    $pdf->Rect(
                        $ox + $box['x'] + $column * $module,
                        $oy + $box['y'] + $row * $module,
                        $module,
                        $module,
                        'F',
                        ['all' => ['width' => 0]]
                    );
                }
            }
        }
    }

    /**
     * A colour of the design.
     *
     * @param string $hex
     * @return int[]
     */
    private static function rgb(string $hex): array {
        return array_map('hexdec', str_split(ltrim($hex, '#'), 2));
    }

    /**
     * The document.
     *
     * @return string The PDF.
     */
    public function output(): string {
        $this->flush();
        return $this->pdf->Output('', 'S');
    }
}
