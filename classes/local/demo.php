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
            $items = [];
            for ($c = 0; $c < $copies; $c++) {
                $items[] = [
                    'entityid' => $entityid,
                    'libraryid' => $libraryid,
                    'recordid' => $recordid,
                    'barcode' => control_letter::append(sprintf('%06d', $barcode)),
                    'regnumber' => (string) $barcode,
                    'callnumber1' => $classes[$class],
                    'callnumber2' => strtoupper(mb_substr($authors[$author], 0, 3)),
                    'callnumber3' => mb_strtolower(mb_substr(preg_replace('/^(El|La|Un|Una) /', '', $title), 0, 3)),
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
}
