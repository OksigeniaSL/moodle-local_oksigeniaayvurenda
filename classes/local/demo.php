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
 * Made-up libraries to develop, test and show with.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

use local_oksigeniaayvurenda\core\control_letter;
use local_oksigeniaayvurenda\core\school_year;
use local_oksigeniaayvurenda\core\text;

/**
 * A made-up entity with its central library, a catalogue, copies, readers in groups and loan rules. Every name and
 * title is invented: nothing in it comes from a real school. The same seed always gives the same library.
 */
final class demo {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var string[] Nouns for titles, with their gender. */
    private const NOUNS = ['secreto' => 'm', 'faro' => 'm', 'bosque' => 'm', 'dragón' => 'm', 'isla' => 'f',
        'reloj' => 'm', 'viaje' => 'm', 'jardín' => 'm', 'mapa' => 'm', 'tormenta' => 'f', 'castillo' => 'm', 'río' => 'm',
        'estrella' => 'f', 'ladrona' => 'f', 'puente' => 'm', 'volcán' => 'm', 'cometa' => 'm', 'abuela' => 'f',
        'pirata' => 'm', 'nube' => 'f', 'tesoro' => 'm', 'invierno' => 'm', 'colibrí' => 'm', 'semilla' => 'f',
        'montaña' => 'f', 'laberinto' => 'm', 'barca' => 'f', 'loba' => 'f', 'ciudad' => 'f', 'espejo' => 'm'];
    /** @var string[] Adjectives for titles, in the masculine. */
    private const ADJECTIVES = ['perdido', 'dormido', 'invisible', 'pequeño', 'valiente', 'olvidado', 'mágico',
        'secreto', 'antiguo', 'nuevo', 'salvaje', 'escondido'];
    /** @var string[] Made-up first names. */
    private const FIRSTNAMES = ['Ana', 'Leo', 'Marta', 'Hugo', 'Lucía', 'Pablo', 'Irene', 'Mateo', 'Sara', 'Daniel',
        'Nora', 'Adrián', 'Julia', 'Bruno', 'Elena', 'Iker', 'Carla', 'Óscar', 'Noa', 'Teo', 'Vega', 'Marco', 'Alba',
        'Gael', 'Lola', 'Izan', 'Olivia', 'Dylan', 'Aitana', 'Unai'];
    /** @var string[] Made-up surnames. */
    private const SURNAMES = ['Arenas', 'Bosque', 'Calma', 'Duna', 'Estrada', 'Faro', 'Galán', 'Hierro', 'Isla', 'Jara',
        'Lago', 'Montes', 'Nieve', 'Olmo', 'Prado', 'Quintana', 'Río', 'Sauce', 'Torres', 'Umbría', 'Valle', 'Zarza',
        'Brisa', 'Cardo', 'Encina', 'Fresno', 'Granado', 'Laurel', 'Mirto', 'Rocío'];
    /** @var string[][] Classification codes (UDC) with their label, and the parent code. */
    private const CLASSES = [
        ['0', 'Generalidades', null], ['03', 'Enciclopedias', '0'], ['5', 'Ciencias', null], ['51', 'Matemáticas', '5'],
        ['52', 'Astronomía', '5'], ['57', 'Biología', '5'], ['59', 'Zoología', '57'], ['7', 'Artes', null],
        ['78', 'Música', '7'], ['8', 'Lengua y literatura', null], ['82', 'Literatura', '8'],
        ['821', 'Literaturas nacionales', '82'], ['821.134.2-3', 'Novela', '821'], ['821.134.2-1', 'Poesía', '821'],
        ['9', 'Geografía e historia', null], ['94', 'Historia', '9'],
    ];
    /** @var string[] Subjects. */
    private const SUBJECTS = ['Aventuras', 'Misterio', 'Animales', 'Amistad', 'Ciencia', 'Humor', 'Fantasía', 'Historia',
        'Naturaleza', 'Poesía', 'Deportes', 'Familia', 'Viajes', 'Espacio', 'Dinosaurios'];
    /** @var string[] Publishers. */
    private const PUBLISHERS = ['Editorial Colibrí', 'Ediciones del Faro', 'La Semilla', 'Libros del Valle',
        'Ediciones Nube', 'Editorial Brújula'];
    /** @var string[] Shelves. */
    private const LOCATIONS = ['Estantería infantil', 'Estantería juvenil', 'Cómic', 'Consulta', 'Novedades',
        'Rincón de poesía', 'Ciencias', 'Historia'];
    /** @var array[] Stages and the number of years in each. */
    private const STAGES = [['Infantil', 3], ['Primaria', 6], ['Secundaria', 4], ['Bachillerato', 2]];

    /**
     * Creates the made-up entity.
     *
     * @param string $name
     * @param int $records Titles in the catalogue; copies come to about a fifth more.
     * @param int $readers
     * @param int $seed
     * @return int The entity id.
     */
    public static function create(string $name, int $records, int $readers, int $seed = 20261023): int {
        global $DB;
        mt_srand($seed);
        $now = time();
        $transaction = $DB->start_delegated_transaction();

        $entityid = $DB->insert_record(self::T . 'entity', [
            'name' => $name,
            'yearstart' => '09-01',
            'settings' => json_encode(['classification' => 'udc', 'demo' => true, 'controlletter' => true]),
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $libraryid = $DB->insert_record(self::T . 'library', [
            'entityid' => $entityid,
            'name' => $name,
            'slug' => 'demo-' . $entityid,
            'kind' => 'central',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $year = school_year::around($now, '09-01', \core_date::get_server_timezone_object());
        $yearid = $DB->insert_record(self::T . 'year', ['entityid' => $entityid] + $year);
        self::rules($libraryid, $now);

        $classes = self::classes($entityid, $now);
        $locations = self::authorities($entityid, 'location', self::LOCATIONS, $now);
        $subjects = self::authorities($entityid, 'subject', self::SUBJECTS, $now);
        $publishers = self::authorities($entityid, 'publisher', self::PUBLISHERS, $now);
        $authornames = [];
        for ($i = 0; $i < max(1, intdiv($records, 2)); $i++) {
            $authornames[] = self::pick(self::SURNAMES) . ' ' . self::pick(self::SURNAMES) . ', ' . self::pick(self::FIRSTNAMES);
        }
        $authors = self::authorities($entityid, 'author', array_values(array_unique($authornames)), $now);

        $barcode = 100000;
        for ($i = 0; $i < $records; $i++) {
            $title = self::title();
            $author = array_rand($authors);
            $subject = array_rand($subjects);
            $class = array_rand($classes);
            $recordid = $DB->insert_record(self::T . 'record', [
                'entityid' => $entityid,
                'doctype' => 'book',
                'title' => $title,
                'pubyear' => (string) mt_rand(1985, 2026),
                'lang' => 'spa',
                'extent' => mt_rand(24, 320) . ' p.',
                'searchtext' => text::normalise($title . ' ' . $authors[$author] . ' ' . $subjects[$subject]),
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
            $DB->insert_records(self::T . 'recauth', [
                ['recordid' => $recordid, 'authorityid' => $author, 'role' => 'author', 'sortorder' => 0],
                ['recordid' => $recordid, 'authorityid' => $subject, 'role' => null, 'sortorder' => 1],
                ['recordid' => $recordid, 'authorityid' => array_rand($publishers), 'role' => null, 'sortorder' => 2],
                ['recordid' => $recordid, 'authorityid' => $class, 'role' => null, 'sortorder' => 3],
            ]);
            $copies = mt_rand(1, 100) <= 85 ? 1 : mt_rand(2, 4);
            $call = [$classes[$class], strtoupper(mb_substr($authors[$author], 0, 3)),
                mb_strtolower(mb_substr(preg_replace('/^(El|La|Un|Una) /', '', $title), 0, 3))];
            $items = [];
            for ($c = 0; $c < $copies; $c++) {
                $items[] = [
                    'entityid' => $entityid,
                    'libraryid' => $libraryid,
                    'recordid' => $recordid,
                    'barcode' => control_letter::append(sprintf('%06d', $barcode)),
                    'regnumber' => (string) $barcode,
                    'callnumber1' => $call[0],
                    'callnumber2' => $call[1],
                    'callnumber3' => $call[2],
                    'shelfkey' => \local_oksigeniaayvurenda\core\shelf_order::key($call[0], $call[1], $call[2], $title, $recordid),
                    'locationid' => array_rand($locations),
                    'itemtype' => mt_rand(1, 100) <= 3 ? 'reference' : 'normal',
                    'status' => 'available',
                    'timeacquired' => $now - mt_rand(0, 20 * 365) * DAYSECS,
                    'timecreated' => $now,
                    'timemodified' => $now,
                ];
                $barcode++;
            }
            $DB->insert_records(self::T . 'item', $items);
        }

        self::readers($entityid, $yearid, $readers, $now);
        $transaction->allow_commit();
        return $entityid;
    }

    /**
     * Made-up Moodle accounts in cohorts, one per group and one for the staff, chosen as the entity's sources of
     * readers, and the readers brought from them. Usernames start with «ayvdemo» and every name is invented.
     *
     * @param int $entityid
     * @param int $count Students; teachers come to about a tenth more.
     * @param int $seed
     * @return array What the sync of the readers did.
     */
    public static function accounts(int $entityid, int $count, int $seed = 20261025): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/lib.php');
        require_once($CFG->dirroot . '/cohort/lib.php');
        mt_srand($seed);
        $system = \context_system::instance();
        $cohorts = [];
        $config = [];
        $groups = [];
        foreach (self::STAGES as [$stage, $years]) {
            for ($y = 1; $y <= $years; $y++) {
                $groups[] = [$stage, $y . 'º ' . $stage . ' A'];
            }
        }
        foreach ($groups as [$stage, $name]) {
            $id = $DB->get_field('cohort', 'id', ['name' => $name, 'contextid' => $system->id]) ?: cohort_add_cohort(
                (object) ['name' => $name, 'contextid' => $system->id, 'idnumber' => 'ayvdemo-' . md5($name)]
            );
            $cohorts[] = $id;
            $config[$id] = ['type' => 'student', 'stage' => $stage, 'group' => true];
        }
        $staff = $DB->get_field('cohort', 'id', ['name' => 'Claustro', 'contextid' => $system->id]) ?: cohort_add_cohort(
            (object) ['name' => 'Claustro', 'contextid' => $system->id, 'idnumber' => 'ayvdemo-claustro']
        );
        $config[$staff] = ['type' => 'teacher', 'stage' => '', 'group' => false];
        $total = $count + intdiv($count, 10);
        for ($i = 0; $i < $total; $i++) {
            $username = 'ayvdemo' . $entityid . '_' . $i;
            $userid = $DB->get_field('user', 'id', ['username' => $username]);
            if (!$userid) {
                $user = (object) [
                    'username' => $username,
                    'firstname' => self::pick(self::FIRSTNAMES),
                    'lastname' => self::pick(self::SURNAMES) . ' ' . self::pick(self::SURNAMES),
                    'email' => $username . '@example.invalid',
                    'auth' => 'nologin',
                    'confirmed' => 1,
                    'mnethostid' => $CFG->mnet_localhost_id,
                ];
                // Moodle 5.3 moved the creation of users; older versions keep the function.
                $userid = method_exists('\core\user', 'create_user') ? \core\user::create_user($user, false, false)
                    : user_create_user($user, false, false);
            }
            cohort_add_member($i < $count ? $cohorts[$i % count($cohorts)] : $staff, $userid);
        }
        \local_oksigeniaayvurenda\local\sources\cohorts::save($entityid, $config);
        return \local_oksigeniaayvurenda\local\sources\cohorts::sync($entityid);
    }

    /**
     * A term of made-up activity in a library: loans of the last weeks, most of them returned, some out and some late,
     * by readers who have a group this year; and a tutor for each group among the teachers with an account.
     *
     * @param int $libraryid
     * @param int $count Loans.
     * @param int $seed
     * @param int|null $now
     * @return array Loans out, late and returned, and tutors set.
     */
    public static function activity(int $libraryid, int $count, int $seed = 20261026, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        mt_srand($seed);
        $library = $DB->get_record(self::T . 'library', ['id' => $libraryid], '*', MUST_EXIST);
        $year = years::around((int) $library->entityid, $now);
        $members = $DB->get_records_sql(
            "SELECT m.readerid, m.groupname
               FROM {" . self::T . "member} m
               JOIN {" . self::T . "reader} r ON r.id = m.readerid
              WHERE m.yearid = :yearid AND r.status = 'active' AND m.groupname IS NOT NULL",
            ['yearid' => $year->id]
        );
        $members = array_values($members);
        $items = $DB->get_fieldset_select(
            self::T . 'item',
            'id',
            "libraryid = :libraryid AND status = 'available' AND itemtype = 'normal'",
            ['libraryid' => $libraryid]
        );
        $stats = ['out' => 0, 'late' => 0, 'returned' => 0, 'tutors' => 0];
        if (!$members || !$items) {
            return $stats;
        }
        shuffle($items);
        for ($i = 0; $i < $count && $items; $i++) {
            $member = $members[mt_rand(0, count($members) - 1)];
            $itemid = (int) array_pop($items);
            $loaned = $now - mt_rand(1, 40) * DAYSECS - mt_rand(0, 6) * HOURSECS;
            $due = $loaned + 14 * DAYSECS;
            $returned = $due < $now - 3 * DAYSECS && mt_rand(1, 100) <= 85 ? $loaned + mt_rand(3, 16) * DAYSECS : null;
            $returned = $returned !== null ? min($returned, $now - HOURSECS) : null;
            $DB->insert_record(self::T . 'loan', [
                'libraryid' => $libraryid,
                'itemid' => $itemid,
                'readerid' => $member->readerid,
                'yearid' => $year->id,
                'groupname' => $member->groupname,
                'timeloaned' => $loaned,
                'timedue' => $due,
                'timereturned' => $returned,
                'renewals' => 0,
                'timecreated' => $loaned,
                'timemodified' => $returned ?? $loaned,
            ]);
            if ($returned === null) {
                $DB->set_field(self::T . 'item', 'status', 'onloan', ['id' => $itemid]);
                $stats[$due < $now ? 'late' : 'out']++;
            } else {
                $stats['returned']++;
            }
        }
        $teachers = $DB->get_fieldset_select(
            self::T . 'reader',
            'userid',
            "entityid = :entityid AND userid IS NOT NULL AND readertype <> 'student' AND status = 'active'",
            ['entityid' => $library->entityid]
        );
        $groups = array_unique(array_map(fn($m) => $m->groupname, $members));
        sort($groups);
        foreach ($groups as $index => $group) {
            if (!isset($teachers[$index])) {
                break;
            }
            tutors::set((int) $year->id, $group, (int) $teachers[$index]);
            $stats['tutors']++;
        }
        return $stats;
    }

    /**
     * Loan rules as a school usually has them.
     *
     * @param int $libraryid
     * @param int $now
     */
    private static function rules(int $libraryid, int $now): void {
        global $DB;
        $rules = [
            ['student', 'normal', 7, 1, 6, 7],
            ['teacher', '*', 30, 0, 1, 15],
            ['staff', 'normal', 15, 0, 1, 15],
            ['*', 'reference', 0, 0, 0, 0],
        ];
        foreach ($rules as [$readertype, $itemtype, $days, $max, $renewals, $renewdays]) {
            $DB->insert_record(self::T . 'rule', [
                'libraryid' => $libraryid,
                'readertype' => $readertype,
                'itemtype' => $itemtype,
                'loandays' => $days,
                'maxloans' => $max,
                'renewals' => $renewals,
                'renewdays' => $renewdays,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }
    }

    /**
     * The classification codes, with their hierarchy.
     *
     * @param int $entityid
     * @param int $now
     * @return array Code of each authority id.
     */
    private static function classes(int $entityid, int $now): array {
        global $DB;
        $ids = [];
        foreach (self::CLASSES as [$code, $label, $parent]) {
            $ids[$code] = $DB->insert_record(self::T . 'authority', [
                'entityid' => $entityid,
                'kind' => 'class',
                'code' => $code,
                'label' => $label,
                'parentid' => $parent === null ? null : $ids[$parent],
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }
        return array_flip($ids);
    }

    /**
     * Authorities of one kind.
     *
     * @param int $entityid
     * @param string $kind
     * @param string[] $labels
     * @param int $now
     * @return array Label of each authority id.
     */
    private static function authorities(int $entityid, string $kind, array $labels, int $now): array {
        global $DB;
        $ids = [];
        foreach ($labels as $label) {
            $id = $DB->insert_record(self::T . 'authority', [
                'entityid' => $entityid,
                'kind' => $kind,
                'label' => $label,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
            $ids[$id] = $label;
        }
        return $ids;
    }

    /**
     * Readers: students spread over the groups of each stage, teachers and other staff, with their group this year.
     *
     * @param int $entityid
     * @param int $yearid
     * @param int $count
     * @param int $now
     */
    private static function readers(int $entityid, int $yearid, int $count, int $now): void {
        global $DB;
        $groups = [];
        foreach (self::STAGES as [$stage, $years]) {
            for ($y = 1; $y <= $years; $y++) {
                foreach (['A', 'B'] as $letter) {
                    $groups[] = [$stage, $y . 'º ' . $stage . ' ' . $letter];
                }
            }
        }
        for ($i = 0; $i < $count; $i++) {
            $roll = mt_rand(1, 100);
            $type = $roll <= 92 ? 'student' : ($roll <= 98 ? 'teacher' : 'staff');
            $readerid = $DB->insert_record(self::T . 'reader', [
                'entityid' => $entityid,
                'cardcode' => control_letter::append(sprintf('%05d', 10000 + $i)),
                'readertype' => $type,
                'firstname' => self::pick(self::FIRSTNAMES),
                'lastname' => self::pick(self::SURNAMES) . ' ' . self::pick(self::SURNAMES),
                'status' => 'active',
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
            if ($type === 'student') {
                [$stage, $group] = $groups[$i % count($groups)];
                $DB->insert_record(self::T . 'member', [
                    'readerid' => $readerid,
                    'yearid' => $yearid,
                    'groupname' => $group,
                    'stage' => $stage,
                    'source' => 'demo',
                    'timecreated' => $now,
                ]);
            }
        }
    }

    /** @var string[][] Covers of the art package for the books of some classes, before the noun of their title. */
    private const CLASS_COVERS = ['52' => ['astronomia', 'planetas'], '51' => ['matematicas'], '59' => ['zoologia', 'erizo'],
        '57' => ['erizo'], '78' => ['musica'], '7' => ['colores'], '94' => ['historia', 'mapa'], '9' => ['tren', 'mapa'],
        '03' => ['cartas'], '0' => ['cartas']];

    /**
     * Gives the made-up books of a library the made-up covers of the art package (Gaudí's, pix/portadas/): by the class
     * of a book that is not a story, else by the noun of its title. One book in seven keeps its drawn cover, as in any
     * library; books that already have one keep it.
     *
     * @param int $libraryid
     * @return int How many covers were given; -1 if the centre has records brought from a catalogue, which are not
     *     made-up and never get a made-up cover.
     */
    public static function covers(int $libraryid): int {
        global $DB;
        $entityid = (int) $DB->get_field(self::T . 'library', 'entityid', ['id' => $libraryid], MUST_EXIST);
        if ($DB->record_exists(self::T . 'source', ['entityid' => $entityid, 'tablename' => 'record'])) {
            return -1;
        }
        $dir = \core_component::get_component_directory('local_oksigeniaayvurendaarte');
        if (!$dir || !is_dir($dir . '/pix/portadas')) {
            return 0;
        }
        $rows = $DB->get_recordset_sql(
            "SELECT r.id, r.title, r.extra,
                    (SELECT MIN(a.code)
                       FROM {" . self::T . "recauth} ra
                       JOIN {" . self::T . "authority} a ON a.id = ra.authorityid AND a.kind = 'class'
                      WHERE ra.recordid = r.id) AS class
               FROM {" . self::T . "record} r
              WHERE r.entityid = :entityid
           ORDER BY r.id",
            ['entityid' => $entityid]
        );
        $prepared = [];
        $given = 0;
        foreach ($rows as $row) {
            if ($row->id % 7 === 0 || str_contains((string) $row->extra, '"cover"')) {
                continue;
            }
            $keys = self::CLASS_COVERS[(string) $row->class] ?? [];
            $words = explode(' ', \local_oksigeniaayvurenda\core\text::normalise((string) $row->title));
            $key = $keys ? $keys[$row->id % count($keys)] : ($words[1] ?? '');
            if (!array_key_exists($key, $prepared)) {
                $file = $dir . '/pix/portadas/libro-' . $key . '.webp';
                $prepared[$key] = preg_match('/^[a-z]+$/', $key) && is_readable($file)
                    ? covers::prepare((string) file_get_contents($file)) : null;
            }
            if ($prepared[$key] !== null && covers::store((int) $row->id, $prepared[$key], 'demo')) {
                $given++;
            }
        }
        $rows->close();
        return $given;
    }

    /**
     * A made-up title.
     *
     * @return string
     */
    private static function title(): string {
        $noun = self::pick(array_keys(self::NOUNS));
        $other = self::pick(array_keys(self::NOUNS));
        $female = self::NOUNS[$noun] === 'f';
        $of = self::NOUNS[$other] === 'f' ? 'de la' : 'del';
        switch (mt_rand(1, 3)) {
            case 1:
                $adjective = self::pick(self::ADJECTIVES);
                if ($female && substr($adjective, -1) === 'o') {
                    $adjective = substr($adjective, 0, -1) . 'a';
                }
                return ($female ? 'La ' : 'El ') . $noun . ' ' . $adjective;
            case 2:
                return ($female ? 'La ' : 'El ') . $noun . ' ' . $of . ' ' . $other;
            default:
                return ($female ? 'Una ' : 'Un ') . $noun . ' en ' . ($of === 'del' ? 'el' : 'la') . ' ' . $other;
        }
    }

    /**
     * A random entry of a list.
     *
     * @param string[] $list
     * @return string
     */
    private static function pick(array $list): string {
        return $list[mt_rand(0, count($list) - 1)];
    }

    /**
     * Puts made-up works for everyone in a library's public window and opens it: an EPUB written on the spot, of the
     * centre's own, on titles of its catalogue that have nothing digital yet.
     *
     * @param int $libraryid
     * @param int $count
     * @param int $seed
     * @return array works, window.
     */
    public static function window(int $libraryid, int $count, int $seed = 20261027): array {
        global $DB;
        mt_srand($seed);
        $library = $DB->get_record(self::T . 'library', ['id' => $libraryid], '*', MUST_EXIST);
        $records = $DB->get_records_sql(
            'SELECT r.id, r.title
               FROM {' . self::T . 'record} r
              WHERE r.entityid = :entityid AND NOT EXISTS (SELECT 1 FROM {' . self::T . 'digital} d WHERE d.recordid = r.id)
           ORDER BY r.id',
            ['entityid' => $library->entityid],
            0,
            $count
        );
        $dir = make_request_directory();
        $works = 0;
        foreach ($records as $record) {
            $path = $dir . '/' . $record->id . '.epub';
            self::epub($path, $record->title);
            digital::add_file($library, (int) $record->id, $path, clean_filename($record->title) . '.epub', ['title' => '',
                'licence' => 'own', 'audience' => ['students', 'families', 'public'], 'download' => 1], 0);
            $works++;
        }
        window::save($library, ['open' => 1, 'voices' => 1, 'index' => 0, 'text' => 'Una biblioteca inventada para ' .
            'enseñar Ayvurenda. Todo lo que hay aquí, títulos, autores y textos, es inventado.']);
        return ['works' => $works, 'window' => 'open'];
    }

    /**
     * Writes a made-up EPUB 3 with three short chapters.
     *
     * @param string $path
     * @param string $title
     */
    private static function epub(string $path, string $title): void {
        $esc = fn(string $text) => htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $who = self::pick(self::FIRSTNAMES);
        $chapters = [];
        foreach (['Uno', 'Dos', 'Tres'] as $n => $name) {
            $paragraphs = [];
            if ($n === 0) {
                $paragraphs[] = 'Este libro es inventado, para enseñar la biblioteca.';
            }
            for ($i = 0; $i < 12; $i++) {
                $paragraphs[] = $who . ' ' . self::pick(['encontró', 'buscó', 'perdió', 'dibujó', 'escuchó', 'soñó']) . ' ' .
                    self::pick(['un mapa', 'una llave', 'una canción', 'un faro', 'una puerta', 'un río']) . ' ' .
                    self::pick(['al final del pasillo', 'bajo la lluvia', 'detrás de la biblioteca', 'en lo alto del árbol',
                        'junto al mar', 'en el último cajón']) . '. ' .
                    self::pick(['Nadie lo había visto antes.', 'Al día siguiente ya no estaba.', 'Se lo contó a su abuela.',
                        'Decidió guardarlo en secreto.', 'Lo apuntó en su cuaderno.', 'Pensó que era una señal.']);
            }
            $chapters[] = [$name, $paragraphs];
        }
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('mimetype', 'application/epub+zip');
        $zip->setCompressionName('mimetype', \ZipArchive::CM_STORE);
        $zip->addFromString('META-INF/container.xml', '<?xml version="1.0"?><container version="1.0" ' .
            'xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="OEBPS/content.opf" ' .
            'media-type="application/oebps-package+xml"/></rootfiles></container>');
        $manifest = '<item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>';
        $spine = '';
        $toc = '';
        foreach ($chapters as $n => [$name, $paragraphs]) {
            $file = 'c' . ($n + 1) . '.xhtml';
            $manifest .= '<item id="c' . ($n + 1) . '" href="' . $file . '" media-type="application/xhtml+xml"/>';
            $spine .= '<itemref idref="c' . ($n + 1) . '"/>';
            $toc .= '<li><a href="' . $file . '">' . $name . '</a></li>';
            $zip->addFromString('OEBPS/' . $file, '<?xml version="1.0" encoding="UTF-8"?><html ' .
                'xmlns="http://www.w3.org/1999/xhtml" xml:lang="es"><head><title>' . $name . '</title></head><body><h1>' .
                $name . '</h1>' . implode('', array_map(fn($p) => '<p>' . $esc($p) . '</p>', $paragraphs)) . '</body></html>');
        }
        $zip->addFromString('OEBPS/nav.xhtml', '<?xml version="1.0" encoding="UTF-8"?><html ' .
            'xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops" xml:lang="es"><head><title>' .
            $esc($title) . '</title></head><body><nav epub:type="toc"><ol>' . $toc . '</ol></nav></body></html>');
        $zip->addFromString('OEBPS/content.opf', '<?xml version="1.0" encoding="UTF-8"?><package ' .
            'xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="id"><metadata ' .
            'xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:identifier id="id">urn:uuid:' . \core\uuid::generate() .
            '</dc:identifier><dc:title>' . $esc($title) . '</dc:title><dc:language>es</dc:language><meta ' .
            'property="dcterms:modified">' . gmdate('Y-m-d\\TH:i:s\\Z') . '</meta></metadata><manifest>' . $manifest .
            '</manifest><spine>' . $spine . '</spine></package>');
        $zip->close();
    }
}
