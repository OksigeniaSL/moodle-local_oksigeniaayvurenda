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
 * Ayvurenda: reader cards in print: of whom, in which design, and how they are kept until they are taken away.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

use local_oksigeniaayvurenda\core\card_look;
use local_oksigeniaayvurenda\core\group_order;

/**
 * The cards of everyone, a stage, a class or one reader, for a printer or on sheets of ten. The design comes from the art
 * package; without it, there is the plain sheet of print.php. A few are made while the person waits; many, by a task.
 * What is made holds photos and names: it stays seven days and goes.
 */
final class cardprint {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var string The area of what is made, by entity. */
    public const AREA = 'cardprint';

    /** @var int Up to this many, made while the person waits. */
    public const NOW = 120;

    /** @var int How many cards a file holds: more go in a zip of several. */
    public const PERFILE = 400;

    /** @var int How many days what is made stays. */
    public const DAYS = 7;

    /** @var string[] How they can be printed. */
    public const FORMATS = ['press', 'plastic', 'sheet', 'plain'];

    /**
     * @var array[] Cut sheets of ten that can be chosen by name. Their makers publish the size of the card but not its margins,
     * so these start centred on the page, and the test sheet says how far off they are.
     */
    public const MODELS = [
        'avery-c32011' => ['name' => 'Avery C32011', 'width' => 85.0, 'height' => 54.0],
        'avery-c32016' => ['name' => 'Avery C32016', 'width' => 85.0, 'height' => 54.0],
        'apli-10408' => ['name' => 'Apli 10408', 'width' => 86.0, 'height' => 54.0],
        'other' => ['name' => '', 'width' => 85.0, 'height' => 54.0],
    ];

    /** @var string[] The designs of a printed card. */
    public const DESIGNS = ['flor', 'cielo', 'sobria'];

    /** @var string What a centre chooses for all its readers. */
    public const EVERYONE = '*';

    /** @var string What a centre chooses for teachers, staff and families, who have no stage. */
    public const ADULTS = 'adults';

    /** @var string Where a centre's logo is kept, with the centre as its item. */
    public const LOGO = 'cardlogo';

    /** @var array[] The sheets of ten: the plain one, cut by hand, and those that come cut, whose margins the centre sets. */
    public const SHEETS = [
        'plain' => ['columns' => 2, 'rows' => 5, 'width' => 85.6, 'height' => 54.0, 'left' => 19.4, 'top' => 13.5, 'gap' => 0.0,
            'cuts' => true],
        'sheet' => ['columns' => 2, 'rows' => 5, 'width' => 85.0, 'height' => 54.0, 'left' => 20.0, 'top' => 13.5, 'gap' => 0.0,
            'cuts' => false],
    ];

    /**
     * The folder of the design in the art package.
     *
     * @return string
     */
    public static function folder(): string {
        global $CFG;
        return $CFG->dirroot . '/local/oksigeniaayvurendaarte/pix/carne/imprenta';
    }

    /**
     * Whether the design is there.
     *
     * @return bool
     */
    public static function ready(): bool {
        global $CFG;
        return is_readable(self::folder() . '/carne.json')
            && is_readable($CFG->dirroot . '/local/oksigeniaayvurendaarte/fuentes/Literata-SemiBold.ttf');
    }

    /**
     * What a centre says on the back and how its sheets fall.
     *
     * @param int $entityid
     * @return array where, notes, model, left, top, gap, pwidth, pheight, backs (whether a card has its back) and designs
     *     (what the centre chose, by stage).
     */
    public static function settings(int $entityid): array {
        global $DB;
        $settings = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        return ($settings['cardprint'] ?? []) + ['where' => '', 'notes' => '', 'model' => 'other',
            'left' => self::SHEETS['sheet']['left'],
            'top' => self::SHEETS['sheet']['top'], 'gap' => self::SHEETS['sheet']['gap'], 'pwidth' => 85.6, 'pheight' => 54.0,
            'backs' => 1, 'designs' => []];
    }

    /**
     * Changes them.
     *
     * @param int $entityid
     * @param array $changes
     */
    public static function save(int $entityid, array $changes): void {
        global $DB;
        $settings = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        $known = array_flip(['where', 'notes', 'model', 'left', 'top', 'gap', 'pwidth', 'pheight', 'backs', 'designs']);
        $settings['cardprint'] = array_intersect_key($changes, $known) + self::settings($entityid);
        $DB->set_field(self::T . 'entity', 'settings', json_encode($settings), ['id' => $entityid]);
    }

    /**
     * Chooses a model of sheet: its cards, centred on the page, to correct with the test sheet.
     *
     * @param int $entityid
     * @param string $key
     */
    public static function choose_model(int $entityid, string $key): void {
        $model = self::MODELS[$key] ?? null;
        if ($model) {
            self::save($entityid, ['model' => $key, 'left' => round((210 - 2 * $model['width']) / 2, 1),
                'top' => round((297 - 5 * $model['height']) / 2, 1), 'gap' => 0.0]);
        }
    }

    /**
     * The sheet of a format; a printer has none, and plastic is one card to a page.
     *
     * @param string $format
     * @param int $entityid
     * @return array|null
     */
    public static function sheet(string $format, int $entityid): ?array {
        if ($format === 'press') {
            return null;
        }
        if ($format === 'plastic') {
            $own = self::settings($entityid);
            return ['columns' => 1, 'rows' => 1, 'width' => (float) $own['pwidth'], 'height' => (float) $own['pheight'],
                'left' => 0.0, 'top' => 0.0, 'gap' => 0.0, 'cuts' => false, 'page' => true];
        }
        $sheet = self::SHEETS[$format];
        if ($format === 'sheet') {
            $own = self::settings($entityid);
            $model = self::MODELS[$own['model']] ?? self::MODELS['other'];
            $sheet = ['left' => (float) $own['left'], 'top' => (float) $own['top'], 'gap' => (float) $own['gap'],
                'width' => $model['width'], 'height' => $model['height']] + $sheet;
        }
        return $sheet;
    }

    /**
     * The design of a card: only the school and the secondary school draw, and each reader's look says what; whoever
     * chose the plain look, and the adults, have the sober one.
     *
     * @param string $skin
     * @param string $look
     * @return string flor, cielo or sobria.
     */
    public static function variant(string $skin, string $look): string {
        if (!in_array($skin, ['school', 'secondary'], true)) {
            return 'sobria';
        }
        return ['flower' => 'flor', 'sky' => 'cielo', 'skysober' => 'cielo'][$look] ?? 'sobria';
    }

    /**
     * The design a card comes out in: what the centre chose for that stage, else for everyone, else what goes with the
     * reader.
     *
     * @param string $skin
     * @param string $look
     * @param string $stage The reader's stage, or ADULTS.
     * @param array $chosen What the centre chose, by stage.
     * @return string flor, cielo or sobria.
     */
    public static function design(string $skin, string $look, string $stage, array $chosen): string {
        $own = ($chosen[$stage] ?? '') ?: ($chosen[self::EVERYONE] ?? '');
        return in_array($own, self::DESIGNS, true) ? $own : self::variant($skin, $look);
    }

    /**
     * Keeps what a centre chose: only designs, and nothing for what stays as it was.
     *
     * @param int $entityid
     * @param array $chosen By stage, EVERYONE or ADULTS.
     */
    public static function choose_designs(int $entityid, array $chosen): void {
        self::save($entityid, ['designs' => array_filter($chosen, fn($design) => in_array($design, self::DESIGNS, true))]);
    }

    /**
     * Who gets each design, to show it: the stages of this year, and the adults.
     *
     * @param \stdClass $library
     * @param int $now
     * @return array[] By design, the keys of what goes with it: stage names, ADULTS, or EVERYONE where there are no stages.
     */
    public static function design_map(\stdClass $library, int $now): array {
        global $DB;
        $entityid = (int) $library->entityid;
        $skin = skins::of($entityid);
        $chosen = self::settings($entityid)['designs'];
        $settings = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        $map = array_fill_keys(self::DESIGNS, []);
        $stages = array_map('strval', array_keys(self::stages($library, $now)));
        foreach ($stages as $stage) {
            $look = card_look::resolve(
                card_look::guess('student', $stage, '', skins::unknown_look($entityid)),
                $settings['cardlooks'][$stage] ?? null,
                null
            );
            $map[self::design($skin, $look, $stage, $chosen)][] = $stage;
        }
        $rest = $stages ? self::ADULTS : self::EVERYONE;
        $map[self::design($skin, 'none', $rest, $chosen)][] = $rest;
        return $map;
    }

    /**
     * Keeps the logo a form sent, if a card can use it.
     *
     * @param int $entityid
     * @param string $field
     * @return bool|null Null when nothing was sent; else whether it was kept.
     */
    public static function keep_logo(int $entityid, string $field): ?bool {
        $kept = images::store_upload($field, self::LOGO, $entityid, 'logo');
        if ($kept && !self::logo_image($entityid)) {
            images::remove(self::LOGO, $entityid, 'logo');
            return false;
        }
        return $kept;
    }

    /**
     * A centre's logo as something to draw on: on white, without the white around it.
     *
     * @param int $entityid
     * @return \GdImage|null
     */
    private static function logo_image(int $entityid): ?\GdImage {
        $file = images::file(self::LOGO, $entityid, 'logo');
        if (!$file || !function_exists('imagecreatefromstring')) {
            return null;
        }
        $image = @imagecreatefromstring($file->get_content());
        if (!$image) {
            return null;
        }
        imagepalettetotruecolor($image);
        [$w, $h] = [imagesx($image), imagesy($image)];
        $flat = imagecreatetruecolor($w, $h);
        imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
        imagecopy($flat, $image, 0, 0, 0, 0, $w, $h);
        return imagecropauto($flat, IMG_CROP_WHITE) ?: $flat;
    }

    /**
     * A centre's logo as the card takes it: flat on white, which also prints on any machine, and not larger than it needs.
     *
     * @param int $entityid
     * @param string $dir Where to leave it.
     * @return array file and ratio (width by height), or nothing without a logo.
     */
    public static function logo(int $entityid, string $dir): array {
        $image = self::logo_image($entityid);
        if (!$image) {
            return [];
        }
        [$w, $h] = [imagesx($image), imagesy($image)];
        // A plate of 13 by 9 mm, at 600 ppi at most.
        $scale = min(1, 310 / $w, 220 / $h);
        $small = imagescale($image, max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale)));
        $path = $dir . '/logo.png';
        imagepng($small ?: $image, $path);
        return ['file' => $path, 'ratio' => $w / $h];
    }

    /**
     * The stages of this school year, with how many readers each has.
     *
     * @param \stdClass $library
     * @param int $now
     * @return int[] Stage => readers.
     */
    public static function stages(\stdClass $library, int $now): array {
        global $DB;
        $year = years::around((int) $library->entityid, $now);
        $rows = $DB->get_records_sql_menu(
            "SELECT m.stage, COUNT(DISTINCT m.readerid)
               FROM {" . self::T . "member} m
               JOIN {" . self::T . "reader} r ON r.id = m.readerid
              WHERE m.yearid = :yearid AND r.entityid = :entityid AND r.status = 'active' AND m.stage IS NOT NULL AND m.stage <> ''
           GROUP BY m.stage",
            ['yearid' => $year->id, 'entityid' => $library->entityid]
        );
        uksort($rows, fn($a, $b) => group_order::compare('', '', (string) $a, (string) $b));
        return array_map('intval', $rows);
    }

    /**
     * The readers of what was chosen.
     *
     * @param \stdClass $library
     * @param array $scope who (all, stage, group, type or reader), and its stage, group, type or reader. Everyone is whoever
     *     is at the centre this year: a student without a class, where there are classes, has to be asked for by name.
     * @param int $now
     * @return \stdClass[] In the order of the classes, and by name in each.
     */
    public static function people(\stdClass $library, array $scope, int $now): array {
        global $DB;
        $year = years::around((int) $library->entityid, $now);
        $params = ['yearid' => $year->id, 'entityid' => $library->entityid];
        $where = '';
        if ($scope['who'] === 'stage') {
            $where = ' AND m.stage = :stage';
            $params['stage'] = (string) $scope['stage'];
        } else if ($scope['who'] === 'group') {
            $where = ' AND m.groupname = :groupname';
            $params['groupname'] = (string) $scope['group'];
        } else if ($scope['who'] === 'type') {
            $where = ' AND r.readertype = :readertype';
            $params['readertype'] = (string) $scope['type'];
        } else if ($scope['who'] === 'reader') {
            $where = ' AND r.id = :id';
            $params['id'] = (int) $scope['reader'];
        } else if (!skins::no_classes((int) $library->entityid) && readers::groups($library, $now)) {
            $where = " AND (m.id IS NOT NULL OR r.readertype <> 'student')";
        }
        $rows = $DB->get_records_sql(
            "SELECT r.id, r.entityid, r.userid, r.cardcode, r.readertype, r.firstname, r.lastname, m.groupname, m.stage,
                    u.firstname AS ufirstname, u.lastname AS ulastname, u.picture
               FROM {" . self::T . "reader} r
          LEFT JOIN {" . self::T . "member} m ON m.readerid = r.id AND m.yearid = :yearid
          LEFT JOIN {user} u ON u.id = r.userid
              WHERE r.entityid = :entityid AND r.status = 'active'$where",
            $params
        );
        $name = fn($row) => \core_text::strtolower(trim(
            ($row->ulastname ?? $row->lastname) . ' ' . ($row->ufirstname ?? $row->firstname)
        ));
        $rows = array_values($rows);
        usort($rows, fn($a, $b) => group_order::compare(
            (string) $a->groupname,
            (string) $b->groupname,
            (string) $a->stage,
            (string) $b->stage
        ) ?: strcmp($name($a), $name($b)));
        return $rows;
    }

    /**
     * What a card of a reader says.
     *
     * @param \stdClass $row From people().
     * @param \stdClass $library
     * @param string $centre
     * @param string $skin
     * @param bool $photos Whether the centre shows photos.
     * @param string $dir Where to leave the photo.
     * @param int $now
     * @param array $chosen The designs the centre chose.
     * @return array As cardpdf::card() wants it.
     */
    private static function person(
        \stdClass $row,
        \stdClass $library,
        string $centre,
        string $skin,
        bool $photos,
        string $dir,
        int $now,
        array $chosen
    ): array {
        $first = $row->userid ? (string) $row->ufirstname : (string) $row->firstname;
        $last = $row->userid ? (string) $row->ulastname : (string) $row->lastname;
        $name = trim($first . ' ' . $last) ?: (string) $row->cardcode;
        $student = $row->readertype === 'student';
        $type = get_string_manager()->string_exists('profiletype' . $row->readertype, 'local_oksigeniaayvurenda')
            ? skins::say((int) $library->entityid, 'profiletype' . $row->readertype) : '';
        return [
            'variant' => self::design($skin, cards::look_of($row, $now), $student ? (string) $row->stage : self::ADULTS, $chosen),
            'name' => $name,
            'group' => (string) $row->groupname,
            'stage' => $student ? (string) $row->stage : $type,
            'library' => $centre,
            'code' => \core_text::strtoupper((string) $row->cardcode),
            'photo' => $photos && $row->userid && !empty($row->picture) ? self::photo((int) $row->userid, $dir) : '',
            'creature' => (int) $row->id % 16 + 1,
        ];
    }

    /**
     * The large picture of an account as a JPEG on white, which the PDF takes without its transparency.
     *
     * @param int $userid
     * @param string $dir
     * @return string Its file, or '' without one.
     */
    private static function photo(int $userid, string $dir): string {
        if (!function_exists('imagecreatefromstring')) {
            return '';
        }
        $context = \context_user::instance($userid, IGNORE_MISSING);
        $fs = get_file_storage();
        $file = $context ? ($fs->get_file($context->id, 'user', 'icon', 0, '/', 'f3.png')
            ?: $fs->get_file($context->id, 'user', 'icon', 0, '/', 'f3.jpg')) : null;
        $image = $file ? @imagecreatefromstring($file->get_content()) : false;
        if (!$image) {
            return '';
        }
        [$w, $h] = [imagesx($image), imagesy($image)];
        $flat = imagecreatetruecolor($w, $h);
        imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
        imagecopy($flat, $image, 0, 0, 0, 0, $w, $h);
        $path = $dir . '/foto-' . $userid . '.jpg';
        imagejpeg($flat, $path, 90);
        return $path;
    }

    /**
     * What the cards of a centre say that is not about each reader, by design and box.
     *
     * @param \stdClass $library
     * @return array
     */
    private static function texts(\stdClass $library): array {
        $own = self::settings((int) $library->entityid);
        $str = fn(string $key) => get_string($key, 'local_oksigeniaayvurenda');
        $texts = [];
        foreach (['flor', 'cielo', 'sobria'] as $variant) {
            $texts[$variant] = ['rotulo' => $str('printcardlabel'), 'titulo' => $str('cardbacktitle' . $variant),
                'que_es_rotulo' => $str('cardbackwhat'), 'que_es' => $str('cardbackwhat' . $variant),
                'como_rotulo' => $str('cardbackhow'), 'como' => $str('cardbackhow' . $variant),
                'encontrado_rotulo' => $str('cardbackfound' . ($variant === 'sobria' ? 'sober' : '')),
                'encontrado' => $str('cardbackfound' . $variant), 'direccion_rotulo' => $str('cardbackwhere'),
                'direccion' => (string) $own['where'], 'notas' => (string) $own['notes']];
        }
        return $texts;
    }

    /**
     * A drawing of the cards.
     *
     * @param \stdClass $library
     * @param string $format
     * @param array $logo As logo() gives it.
     * @return cardpdf
     */
    private static function document(\stdClass $library, string $format, array $logo = []): cardpdf {
        global $CFG;
        $design = json_decode((string) file_get_contents(self::folder() . '/carne.json'), true);
        return new cardpdf(
            $design,
            self::folder(),
            $CFG->dirroot . '/local/oksigeniaayvurendaarte/fuentes',
            self::sheet($format, (int) $library->entityid),
            self::texts($library),
            in_array($format, ['sheet', 'plain'], true) || !empty(self::settings((int) $library->entityid)['backs']),
            $logo
        );
    }

    /**
     * The sheet to lay over a pack of cards, with the margins saved.
     *
     * @param \stdClass $library
     * @return string The PDF.
     */
    public static function test_sheet(\stdClass $library): string {
        $document = self::document($library, 'sheet');
        $document->test_sheet(get_string('cardprinttestwords', 'local_oksigeniaayvurenda'));
        return $document->output();
    }

    /**
     * Makes the cards and keeps them: a PDF, or a zip of several if they are many.
     *
     * @param \stdClass $library
     * @param array $scope
     * @param string $format
     * @param string $label What the file is called after.
     * @param int $userid Who asked.
     * @param int|null $now
     * @return \stored_file|null Null if there is nobody.
     */
    public static function make(
        \stdClass $library,
        array $scope,
        string $format,
        string $label,
        int $userid,
        ?int $now = null
    ): ?\stored_file {
        global $DB, $CFG;
        $now = $now ?? time();
        $rows = self::people($library, $scope, $now);
        if (!$rows) {
            return null;
        }
        $entityid = (int) $library->entityid;
        $centre = format_string($DB->get_field(self::T . 'entity', 'name', ['id' => $entityid]), true, ['escape' => false]);
        $skin = skins::of($entityid);
        $photos = profile::photos_on($entityid);
        $dir = make_request_directory();
        $chosen = self::settings($entityid)['designs'];
        $logo = self::logo($entityid, $dir);
        $base = clean_filename(str_replace(' ', '-', get_string('cardprintfile', 'local_oksigeniaayvurenda') . '-' . $label)) . '-'
            . date('Ymd-His', $now);
        $paths = [];
        foreach (array_chunk($rows, self::PERFILE) as $index => $part) {
            $document = self::document($library, $format, $logo);
            foreach ($part as $row) {
                $document->card(self::person($row, $library, $centre, $skin, $photos, $dir, $now, $chosen));
            }
            $name = $base . (count($rows) > self::PERFILE ? '-' . ($index + 1) : '') . '.pdf';
            file_put_contents($dir . '/' . $name, $document->output());
            $paths[$name] = $dir . '/' . $name;
            // Their photos go as soon as they are in the document.
            array_map('unlink', glob($dir . '/foto-*.jpg') ?: []);
        }
        $final = reset($paths);
        $filename = array_key_first($paths);
        if (count($paths) > 1) {
            $filename = $base . '.zip';
            $final = $dir . '/' . $filename;
            (new \zip_packer())->archive_to_pathname($paths, $final);
        }
        $context = \context_system::instance();
        $file = get_file_storage()->create_file_from_pathname(['contextid' => $context->id,
            'component' => 'local_oksigeniaayvurenda', 'filearea' => self::AREA, 'itemid' => $entityid, 'filepath' => '/',
            'filename' => $filename, 'userid' => $userid, 'timecreated' => $now, 'timemodified' => $now], $final);
        \local_oksigeniaayvurenda\event\cards_made::fire($entityid, null, ['count' => count($rows), 'format' => $format]);
        return $file;
    }

    /**
     * Leaves many cards to the task.
     *
     * @param \stdClass $library
     * @param array $scope
     * @param string $format
     * @param string $label
     * @param int $userid
     */
    public static function queue(\stdClass $library, array $scope, string $format, string $label, int $userid): void {
        $task = new \local_oksigeniaayvurenda\task\make_cardprint();
        $task->set_custom_data(['library' => (int) $library->id, 'scope' => $scope, 'format' => $format, 'label' => $label,
            'userid' => $userid]);
        \core\task\manager::queue_adhoc_task($task);
    }

    /**
     * How many are being made for a centre.
     *
     * @param \stdClass $library
     * @return int
     */
    public static function waiting(\stdClass $library): int {
        global $DB;
        $ids = array_map('intval', $DB->get_fieldset_select(self::T . 'library', 'id', 'entityid = ?', [$library->entityid]));
        $tasks = \core\task\manager::get_adhoc_tasks(\local_oksigeniaayvurenda\task\make_cardprint::class);
        return count(array_filter($tasks, fn($task) => in_array((int) $task->get_custom_data()->library, $ids, true)));
    }

    /**
     * What is ready for a centre, the last first.
     *
     * @param int $entityid
     * @return \stored_file[]
     */
    public static function files(int $entityid): array {
        return get_file_storage()->get_area_files(
            \context_system::instance()->id,
            'local_oksigeniaayvurenda',
            self::AREA,
            $entityid,
            'timecreated DESC',
            false
        );
    }

    /**
     * Takes away what was made long ago: it holds photos and names.
     *
     * @param int|null $now
     * @return int How many.
     */
    public static function sweep(?int $now = null): int {
        global $DB;
        $old = ($now ?? time()) - self::DAYS * DAYSECS;
        $rows = $DB->get_records_select('files', "component = 'local_oksigeniaayvurenda' AND filearea = :area AND filename <> '.'
            AND timecreated < :old", ['area' => self::AREA, 'old' => $old], '', 'id');
        $fs = get_file_storage();
        foreach (array_keys($rows) as $id) {
            $fs->get_file_by_id($id)->delete();
        }
        return count($rows);
    }
}
