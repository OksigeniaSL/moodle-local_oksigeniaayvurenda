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

namespace local_oksigeniaayvurenda\local;

use local_oksigeniaayvurenda\core\text;

/**
 * How do I…? A guide of the staff's tasks, each with its steps, the words someone would look for it with, its screen and
 * its guided help, searched with no help from outside: words without accents or capitals, and the start of each word,
 * so «préstamo» finds «prestar».
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class howto {
    /** @var array[] Each task: its screen, the permission it needs and its guided help (or none). */
    public const TASKS = [
        'lend' => ['desk.php', 'desk', 'desk'],
        'giveback' => ['desk.php', 'desk', 'desk'],
        'renew' => ['desk.php', 'desk', ''],
        'offline' => ['desk.php', 'desk', ''],
        'room' => ['desk.php', 'desk', ''],
        'nolabel' => ['desk.php', 'desk', ''],
        'isbn' => ['desk.php', 'desk', ''],
        'add' => ['add.php', 'catalogue', 'add'],
        'classify' => ['add.php', 'catalogue', ''],
        'catalogued' => ['catalogued.php', 'catalogue', ''],
        'subjects' => ['subjects.php', 'catalogue', ''],
        'duplicates' => ['duplicates.php', 'catalogue', ''],
        'material' => ['material.php', 'catalogue', 'material'],
        'free' => ['free.php', 'catalogue', 'free'],
        'labels' => ['labels.php', 'catalogue', 'labels'],
        'labelsheets' => ['labels.php', 'catalogue', ''],
        'cards' => ['cardprint.php', 'desk', 'cardprint'],
        'readers' => ['readers.php', 'desk', 'readers'],
        'profile' => ['readers.php', 'desk', ''],
        'applink' => ['readers.php', 'desk', ''],
        'newcard' => ['readers.php', 'desk', ''],
        'late' => ['late.php', 'desk', 'late'],
        'holds' => ['holds.php', 'desk', 'holds'],
        'reviews' => ['reviews.php', 'desk', 'reviews'],
        'wishes' => ['wishes.php', 'desk', 'wishes'],
        'myclass' => ['class.php', 'view', 'myclass'],
        'stacks' => ['stacks.php', 'view', 'stacks'],
        'posters' => ['posters.php', 'catalogue', ''],
        'reports' => ['reports.php', 'desk', 'reports'],
        'memoir' => ['memoir.php', 'desk', ''],
        'readings' => ['readings.php', 'desk', ''],
        'copies' => ['copies.php', 'manage', ''],
        'offsite' => ['copies.php', 'manage', ''],
        'stocktake' => ['stocktake.php', 'catalogue', ''],
        'weed' => ['weed.php', 'catalogue', 'weed'],
        'lots' => ['lots.php', 'desk', ''],
        'selfloan' => ['selfloan.php', 'desk', 'selfloan'],
        'sip' => ['selfloan.php', 'manage', ''],
        'homelinks' => ['libraries.php', 'manage', ''],
        'shareclass' => ['search.php', 'desk', ''],
        'formats' => ['formats.php', 'desk', 'formats'],
        'erase' => ['readers.php', 'manage', ''],
        'purchases' => ['purchases.php', 'catalogue', 'purchases'],
        'ill' => ['ill.php', 'desk', 'ill'],
        'bank' => ['bank.php', 'desk', 'bank'],
        'activity' => ['activity.php', 'manage', ''],
        'bankround' => ['libraries.php', 'desk', 'bankround'],
        'import' => ['import.php', 'manage', 'import'],
        'year' => ['year.php', 'manage', ''],
        'returnby' => ['year.php', 'manage', ''],
        'tutors' => ['tutors.php', 'manage', 'tutors'],
        'twins' => ['twins.php', 'manage', 'twins'],
        'platforms' => ['lti.php', 'manage', ''],
        'roster' => ['onerosterapi.php', 'manage', ''],
        'rules' => ['rules.php', 'manage', 'rules'],
        'sanctions' => ['rules.php', 'manage', 'sanctions'],
        'noticeafter' => ['rules.php', 'manage', ''],
        'libraries' => ['libraries.php', 'manage', ''],
        'keepers' => ['keepers.php', 'manage', 'keepers'],
        'characters' => ['characters.php', 'manage', ''],
        'window' => ['public.php', 'manage', ''],
        'record' => ['search.php', 'catalogue', 'record'],
        'closed' => ['libraries.php', 'manage', ''],
        'scanner' => ['phone.php', 'desk', ''],
        'settings' => ['libraries.php', 'manage', ''],
        'voices' => ['voices.php', 'desk', 'voices'],
        'match' => ['match.php', 'manage', ''],
        'readerslist' => ['readerslist.php', 'manage', 'readerslist'],
        'helpme' => ['howto.php', 'desk', ''],
        'lost' => ['desk.php', 'desk', ''],
        'whohas' => ['late.php', 'desk', ''],
        'dashboard' => ['libraries.php', 'manage', ''],
        'readerhold' => ['search.php', 'view', ''],
        'readerrenew' => ['card.php', 'view', ''],
        'readercard' => ['card.php', 'view', ''],
        'readerreview' => ['search.php', 'view', ''],
        'readervoice' => ['search.php', 'view', ''],
        'readerformat' => ['search.php', 'view', ''],
        'password' => ['readers.php', 'desk', ''],
    ];

    /** @var array[] Each setting someone may look for: where it is changed (a page; «centre» for the centre's settings, «site»
     *  for Moodle's) and the permission it needs. A search answers with it and its value now. */
    public const SETTINGS = [
        'loandays' => ['rules.php', 'manage'], 'maxloans' => ['rules.php', 'manage'], 'renewcount' => ['rules.php', 'manage'],
        'renewfrom' => ['rules.php', 'manage'], 'classification' => ['centre', 'manage'], 'controlletter' => ['centre', 'manage'],
        'numbering' => ['centre', 'manage'], 'history' => ['centre', 'manage'], 'reviewsign' => ['centre', 'manage'],
        'cardlook' => ['centre', 'manage'], 'photos' => ['centre', 'manage'], 'entitykind' => ['centre', 'manage'],
        'timezone' => ['centre', 'manage'], 'sources' => ['centre', 'manage'], 'features' => ['centre', 'manage'],
        'challenges' => ['centre', 'manage'], 'corners' => ['corners.php', 'manage'], 'drive' => ['copies.php', 'manage'],
        'z3950' => ['site', 'manage'],
    ];

    /** @var string[] The tasks that need classes, or courses, to mean anything. */
    private const ONLY_WITH = ['myclass' => 'classes', 'wishes' => 'classes', 'tutors' => 'classes', 'bank' => 'classes',
        'bankround' => 'classes', 'readings' => 'courses', 'roster' => 'courses', 'shareclass' => 'courses'];

    /** @var array[] Each guided help: the permission it needs, the screen it plays on and how many steps it says. */
    public const PIECES = [
        'desk' => ['capability' => 'desk', 'page' => 'desk.php', 'steps' => 10],
        'add' => ['capability' => 'catalogue', 'page' => 'add.php', 'steps' => 6],
        'card' => ['capability' => 'desk', 'page' => 'card.php', 'steps' => 7],
        'cardprint' => ['capability' => 'desk', 'page' => 'cardprint.php', 'steps' => 6],
        'record' => ['capability' => 'catalogue', 'page' => 'record.php', 'steps' => 6],
        'labels' => ['capability' => 'catalogue', 'page' => 'labels.php', 'steps' => 6],
        'reports' => ['capability' => 'desk', 'page' => 'reports.php', 'steps' => 5],
        'reviews' => ['capability' => 'desk', 'page' => 'reviews.php', 'steps' => 6],
        'wishes' => ['capability' => 'desk', 'page' => 'wishes.php', 'steps' => 4],
        'myclass' => ['capability' => 'view', 'page' => 'class.php', 'steps' => 7],
        'stacks' => ['capability' => 'view', 'page' => 'stacks.php', 'steps' => 7],
        'free' => ['capability' => 'catalogue', 'page' => 'free.php', 'steps' => 5],
        'keepers' => ['capability' => 'manage', 'page' => 'keepers.php', 'steps' => 4],
        'bankround' => ['capability' => 'desk', 'page' => 'bankround.php', 'steps' => 8],
        'bank' => ['capability' => 'desk', 'page' => 'bank.php', 'steps' => 6],
        'import' => ['capability' => 'manage', 'page' => 'import.php', 'steps' => 9],
        'weed' => ['capability' => 'catalogue', 'page' => 'weed.php', 'steps' => 8],
        'rules' => ['capability' => 'manage', 'page' => 'rules.php', 'steps' => 6],
        'late' => ['capability' => 'desk', 'page' => 'late.php', 'steps' => 6],
        'holds' => ['capability' => 'desk', 'page' => 'holds.php', 'steps' => 5],
        'voices' => ['capability' => 'desk', 'page' => 'voices.php', 'steps' => 8],
        'readers' => ['capability' => 'manage', 'page' => 'readers.php', 'steps' => 6],
        'readerslist' => ['capability' => 'manage', 'page' => 'readerslist.php', 'steps' => 6],
        'material' => ['capability' => 'catalogue', 'page' => 'material.php', 'steps' => 5],
        'twins' => ['capability' => 'manage', 'page' => 'twins.php', 'steps' => 6],
        'tutors' => ['capability' => 'manage', 'page' => 'tutors.php', 'steps' => 5],
        'selfloan' => ['capability' => 'manage', 'page' => 'selfloan.php', 'steps' => 6],
        'purchases' => ['capability' => 'catalogue', 'page' => 'purchases.php', 'steps' => 8],
        'ill' => ['capability' => 'desk', 'page' => 'ill.php', 'steps' => 5],
        'sanctions' => ['capability' => 'manage', 'page' => 'rules.php', 'steps' => 6],
        'formats' => ['capability' => 'desk', 'page' => 'formats.php', 'steps' => 5],
    ];

    /** @var string[] Words that say nothing about the task. */
    private const QUIET = ['como', 'hago', 'hacer', 'puedo', 'para', 'una', 'uno', 'unos', 'unas', 'los', 'las', 'del',
        'que', 'con', 'por', 'how', 'can', 'the', 'and', 'for', 'with', 'what', 'sobre', 'esta', 'este', 'esto', 'quiero',
        'necesito', 'donde', 'cuando', 'mis', 'mas', 'muy', 'pero', 'about', 'want', 'need', 'where', 'when', 'this', 'that',
        'from', 'into', 'our', 'your', 'les', 'des', 'une', 'pour', 'avec', 'dans', 'sur', 'comment', 'faire', 'est', 'mon',
        'mes', 'nos', 'qui', 'quoi', 'veux', 'aux', 'par', 'pas', 'uma', 'umas', 'uns', 'dos', 'das', 'nas', 'nos', 'num',
        'numa', 'com', 'meu', 'minha', 'meus', 'quero', 'preciso', 'onde', 'fazer', 'posso', 'pra', 'pro'];

    /**
     * The tasks that answer a question, best first; with no question, all of them.
     *
     * @param string $query
     * @param \stdClass $library
     * @param int $userid
     * @return array[] key, title, steps, url, help (address or null).
     */
    public static function find(string $query, \stdClass $library, int $userid): array {
        $all = preg_split('/[^\p{L}\p{N}]+/u', text::normalise($query)) ?: [];
        $words = array_values(array_filter($all, fn($w) => \core_text::strlen($w) >= 3 && !in_array($w, self::QUIET, true)));
        if (!$words && trim($query) !== '') {
            // Only short words: an acronym in capitals (QR, IA) is still a question; «what do I» alone finds nothing.
            preg_match_all('/(?<![\p{L}\p{N}])\p{Lu}{2}(?![\p{L}\p{N}])/u', $query, $caps);
            $words = array_values(array_map(fn($w) => text::normalise($w), $caps[0]));
            if (!$words) {
                return [];
            }
        }
        // A code, with or without a word before it («card 004587»), looks someone or something up: not a task.
        if (preg_match('/^(?:[\p{L}]+\s+)?[0-9][0-9A-Za-z]{2,}$/u', trim($query))) {
            return [];
        }
        $found = [];
        $noclasses = skins::no_classes((int) $library->entityid);
        // What a reader does is answered to readers; the staff have their own way of doing it.
        $staff = access::can('local/oksigeniaayvurenda:desk', $library, $userid)
            || access::can('local/oksigeniaayvurenda:catalogue', $library, $userid);
        $nocourses = skins::no_courses((int) $library->entityid);
        foreach (self::TASKS as $key => [$page, $capability, $piece]) {
            if (!access::can('local/oksigeniaayvurenda:' . $capability, $library, $userid)) {
                continue;
            }
            $needs = self::ONLY_WITH[$key] ?? '';
            if (($needs === 'classes' && $noclasses) || ($needs === 'courses' && $nocourses)) {
                continue;
            }
            if ($staff && str_starts_with($key, 'reader') && $key !== 'readers' && $key !== 'readerslist') {
                continue;
            }
            $task = self::task($key, $library);
            $score = $words ? self::score($words, $task) : 1;
            if ($score > 0) {
                $found[] = $task + ['score' => $score, 'cover' => $words ? self::$cover : 1];
            }
        }
        // What someone may want to change, with its value now: only when they ask for something.
        if ($words) {
            foreach (self::SETTINGS as $key => [, $capability]) {
                if (
                    !access::can('local/oksigeniaayvurenda:' . $capability, $library, $userid)
                        || ($key === 'challenges' && $noclasses)
                ) {
                    continue;
                }
                $setting = self::setting($key, $library);
                $score = self::score($words, $setting);
                if ($score > 0) {
                    $found[] = $setting + ['score' => $score, 'cover' => self::$cover];
                }
            }
        }
        usort($found, fn($a, $b) => $b['score'] <=> $a['score']);
        if (!$words) {
            return $found;
        }
        // When not even the best answer covers two fifths of what was asked, the question is not about a task (a book's title,
        // a name, Moodle's own things), and nothing is better than a wrong answer.
        if ($found && 5 * $found[0]['cover'] < 2) {
            return [];
        }
        // Only the answers that come near the best one: the weak ones would only be in the way.
        $best = $found ? $found[0]['score'] : 0;
        return array_slice(array_values(array_filter($found, fn($t) => 2 * $t['score'] >= $best)), 0, 3);
    }

    /** @var string[] Tasks that need readers first, and tasks that need books first: the answer says so. */
    private const NEED_READERS = ['cards', 'newcard', 'profile', 'late', 'applink', 'match', 'tutors'];

    /** @var string[] Tasks that need books first. */
    private const NEED_BOOKS = ['labels', 'labelsheets', 'lend', 'stacks', 'stocktake', 'weed', 'posters', 'lots'];

    /**
     * «What do you want to do?», as «More» shows it while someone types: each answer with what it is (a task or a
     * setting), a line of what to do (or why it has to wait), where to go, its guided help and, for a setting, its value
     * now; then looking it up in the catalogue, for a title or a name, and the whole guide.
     *
     * @param string $query
     * @param \stdClass $library
     * @param int $userid
     * @return array answers, title, showlabel, none, catalogue (url, label) and howto (url, label).
     */
    public static function answers(string $query, \stdClass $library, int $userid): array {
        global $DB;
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        $entityid = (int) $library->entityid;
        $readers = null;
        $books = null;
        $answers = [];
        foreach (self::find($query, $library, $userid) as $found) {
            $setting = ($found['kind'] ?? 'task') === 'setting';
            $note = '';
            if (!$setting && in_array($found['key'], self::NEED_READERS, true)) {
                $readers = $readers ?? $DB->record_exists_select(
                    'local_oksigeniaayvurenda_reader',
                    "entityid = ? AND status = 'active' AND readertype <> 'library'",
                    [$entityid]
                );
                $note = $readers ? '' : $str('howtoneedreaders');
            }
            if (!$setting && in_array($found['key'], self::NEED_BOOKS, true)) {
                $books = $books ?? $DB->record_exists('local_oksigeniaayvurenda_item', ['entityid' => $entityid]);
                $note = $books ? '' : $str('howtoneedbooks');
            }
            $answers[] = [
                'key' => $found['key'],
                'setting' => $setting,
                'kind' => $str($setting ? 'howtoasksetting' : 'howtoasktask'),
                'text' => $found['title'],
                'value' => $setting && ($found['value'] ?? null) !== null ? $str('howtovaluenow', $found['value']) : '',
                'sub' => $note !== '' ? $note : (string) ($found['steps'][0] ?? ''),
                'wait' => $note !== '',
                'url' => $found['url'],
                'go' => $str($setting ? 'howtoaskchange' : 'howtoaskgo'),
                'help' => $found['help'],
            ];
        }
        $url = fn(string $page) => (new \moodle_url('/local/oksigeniaayvurenda/' . $page, ['library' => $library->slug,
            'q' => $query]))->out(false);
        global $OUTPUT;
        return [
            'answers' => $answers,
            'icons' => ['task' => $OUTPUT->render_from_template('local_oksigeniaayvurenda/icon_list', []),
                'setting' => $OUTPUT->render_from_template('local_oksigeniaayvurenda/icon_settings', []),
                'play' => $OUTPUT->render_from_template('local_oksigeniaayvurenda/icon_play', [])],
            'title' => $str('howtoasktitle'),
            'showlabel' => $str('openshow'),
            'none' => $answers ? '' : $str('howtoasknone'),
            'catalogue' => ['url' => $url('search.php'), 'label' => $str('howtoaskcatalogue', $query)],
            'howto' => ['url' => $url('howto.php'), 'label' => $str('howtoaskall')],
        ];
    }

    /**
     * Whether the site's own AI (Moodle's, set up by the site's administrator) can answer in words.
     *
     * @return bool
     */
    public static function ai_ready(): bool {
        if (!class_exists(\core_ai\manager::class) || !class_exists(\core_ai\aiactions\generate_text::class)) {
            return false;
        }
        try {
            return \core\di::get(\core_ai\manager::class)->is_action_available(\core_ai\aiactions\generate_text::class);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * The guide, as text: the tasks someone may do, with their steps. It is all the site's AI is given, besides the
     * question: nothing about readers or loans.
     *
     * @param \stdClass $library
     * @param int $userid
     * @param string $question What is asked: the tasks it finds go whole, the rest by their title.
     * @return string
     */
    public static function guide(\stdClass $library, int $userid, string $question = ''): string {
        // The tasks the search found, whole; the others, by their title only: a shorter prompt answers better, costs a
        // tenth and keeps a tenth in the site's record of what was asked (ia-bibliotecarios 3).
        $near = $question !== '' ? array_column(self::find($question, $library, $userid), 'key') : [];
        $text = '';
        foreach (self::find('', $library, $userid) as $task) {
            $text .= '## [' . $task['key'] . '] ' . $task['title'] . "\n";
            if (!$near || in_array($task['key'], $near, true)) {
                foreach ($task['steps'] as $i => $step) {
                    $text .= ($i + 1) . '. ' . $step . "\n";
                }
            }
            $text .= "\n";
        }
        return $text;
    }

    /**
     * Whether someone may ask the site's AI: the staff of the library, for whom the guide is written; never a reader,
     * who would be accepting the site's AI policy and sending what they write to its provider (ia-bibliotecarios 1).
     *
     * @param \stdClass $library
     * @return bool
     */
    public static function may_ask(\stdClass $library): bool {
        foreach (['desk', 'catalogue', 'manage'] as $what) {
            if (access::can('local/oksigeniaayvurenda:' . $what, $library)) {
                return true;
            }
        }
        return false;
    }

    /**
     * A question answered in words by the site's AI, with the guide as its only source.
     *
     * @param string $question
     * @param \stdClass $library
     * @param int $userid
     * @return array|null text, and tasks: the keys of those it used; null when it could not answer.
     */
    public static function ask(string $question, \stdClass $library, int $userid): ?array {
        $question = \core_text::substr(trim($question), 0, 300);
        if ($question === '' || !self::may_ask($library) || !self::ai_ready()) {
            return null;
        }
        // The question goes between marks, as a question and never as instructions (ia-bibliotecarios 5).
        $prompt = get_string('howtoaiprompt', 'local_oksigeniaayvurenda', (object) [
            'guide' => self::guide($library, $userid, $question),
            'question' => str_replace(['<pregunta>', '</pregunta>', '<question>', '</question>'], '', $question),
        ]);
        try {
            // In the library's context, which on a shared site is its centre's: the site counts what each centre asks.
            $action = new \core_ai\aiactions\generate_text(access::context($library)->id, $userid, $prompt);
            $response = \core\di::get(\core_ai\manager::class)->process_action($action);
        } catch (\Throwable $e) {
            return null;
        }
        if (!$response->get_success()) {
            return null;
        }
        return self::reply((string) ($response->get_response_data()['generatedcontent'] ?? ''));
    }

    /**
     * What the site's AI said, apart from the line that names the tasks it used.
     *
     * @param string $said
     * @return array|null text and tasks; null when it said nothing.
     */
    public static function reply(string $said): ?array {
        $tasks = [];
        // What some models think aloud before answering is not part of the answer.
        $said = (string) preg_replace('#<think>.*?</think>#s', '', $said);
        $text = preg_replace_callback('/^[\s*_#]*(?:TAREAS|TASKS)[\s*_]*:(.*)$/mi', function ($line) use (&$tasks) {
            preg_match_all('/[a-z]+/', $line[1], $keys);
            $tasks = array_values(array_intersect($keys[0], array_keys(self::TASKS)));
            return '';
        }, $said);
        $text = trim((string) $text);
        return $text !== '' ? ['text' => $text, 'tasks' => array_slice($tasks, 0, 3)] : null;
    }

    /**
     * The tasks the site's AI named that this person may do, as find() gives them.
     *
     * @param string[] $keys
     * @param \stdClass $library
     * @param int $userid
     * @return array[]
     */
    public static function named(array $keys, \stdClass $library, int $userid): array {
        $all = array_column(self::find('', $library, $userid), null, 'key');
        return array_values(array_intersect_key($all, array_flip($keys)));
    }

    /**
     * Whether a guided help can be played in a library: not one about classes where there are none, nor the card's flower
     * where the skin has no flower.
     *
     * @param string $piece
     * @param \stdClass $library
     * @return bool
     */
    public static function playable(string $piece, \stdClass $library): bool {
        if (!isset(self::PIECES[$piece])) {
            return false;
        }
        $entityid = (int) $library->entityid;
        // The help of the card is about its flower and its sky, which not every skin has.
        if ($piece === 'card' && !in_array(skins::of($entityid), ['school', 'secondary'], true)) {
            return false;
        }
        foreach (self::TASKS as $key => [, , $theirs]) {
            $needs = $theirs === $piece ? (self::ONLY_WITH[$key] ?? '') : '';
            if (($needs === 'classes' && skins::no_classes($entityid)) || ($needs === 'courses' && skins::no_courses($entityid))) {
                return false;
            }
        }
        return true;
    }

    /**
     * One task, in the reader's language.
     *
     * @param string $key
     * @param \stdClass $library
     * @return array key, title, steps, words, url, help.
     */
    public static function task(string $key, \stdClass $library): array {
        [$page, , $piece] = self::TASKS[$key];
        $str = fn(string $k) => get_string($k, 'local_oksigeniaayvurenda');
        return [
            'key' => $key,
            'kind' => 'task',
            'title' => skins::say((int) $library->entityid, 'howto' . $key),
            'steps' => array_map('trim', explode('|', skins::say((int) $library->entityid, 'howto' . $key . 'steps'))),
            'words' => $str('howto' . $key . 'words'),
            'url' => (new \moodle_url('/local/oksigeniaayvurenda/' . $page, ['library' => $library->slug]))->out(false),
            // The help of the card is about its flower and its sky, which not every skin has.
            'help' => $piece !== '' && self::playable($piece, $library)
                ? (new \moodle_url('/local/oksigeniaayvurenda/help.php', ['library' => $library->slug,
                'piece' => $piece]))->out(false) : null,
        ];
    }

    /**
     * One setting, as a search answers it: what it is, where it is changed and what it is now.
     *
     * @param string $key
     * @param \stdClass $library
     * @return array key, kind, title, steps (where it is), words, url, help (none), value (or null).
     */
    public static function setting(string $key, \stdClass $library): array {
        [$page] = self::SETTINGS[$key];
        $entityid = (int) $library->entityid;
        $url = match ($page) {
            'centre' => (new \moodle_url('/local/oksigeniaayvurenda/libraries.php', ['library' => $library->slug]))->out(false)
                . '#ayv-entity-' . $entityid,
            'site' => (new \moodle_url('/admin/settings.php', ['section' => 'local_oksigeniaayvurenda']))->out(false),
            default => (new \moodle_url('/local/oksigeniaayvurenda/' . $page, ['library' => $library->slug]))->out(false),
        };
        return [
            'key' => $key,
            'kind' => 'setting',
            'title' => skins::say($entityid, 'howtoset' . $key),
            'steps' => [skins::say($entityid, 'howtoset' . $key . 'where')],
            'words' => get_string('howtoset' . $key . 'words', 'local_oksigeniaayvurenda'),
            'url' => $url,
            'help' => null,
            'value' => self::value($key, $library),
        ];
    }

    /**
     * What a setting is now, in a few words, or null where it says nothing short.
     *
     * @param string $key
     * @param \stdClass $library
     * @return string|null
     */
    public static function value(string $key, \stdClass $library): ?string {
        global $DB;
        $str = fn(string $k, $a = null) => get_string($k, 'local_oksigeniaayvurenda', $a);
        $entityid = (int) $library->entityid;
        if (in_array($key, ['loandays', 'maxloans', 'renewcount'], true)) {
            // The rule for any reader and any copy, the first one there is, or what a library lends without rules.
            $rules = rules::of((int) $library->id);
            $rule = current(array_filter($rules, fn($r) => $r['readertype'] === '*' && $r['itemtype'] === '*'))
                ?: (reset($rules) ?: ['loandays' => 14, 'maxloans' => 0, 'renewals' => 1]);
            return match ($key) {
                'loandays' => $str('howtovaluedays', (int) $rule['loandays']),
                'maxloans' => (int) $rule['maxloans'] ? (string) (int) $rule['maxloans'] : $str('howtovaluenolimit'),
                default => (string) (int) $rule['renewals'],
            };
        }
        if ($key === 'renewfrom') {
            return $str(rules::renew_from($entityid) === \local_oksigeniaayvurenda\core\loan_rules::FROM_DUE
                ? 'howtovaluefromdue' : 'howtovaluefromtoday');
        }
        $settings = json_decode((string) $DB->get_field('local_oksigeniaayvurenda_entity', 'settings', ['id' => $entityid]), true)
            ?: [];
        return match ($key) {
            'classification' => $str('classification' . ($settings['classification'] ?? 'udc')),
            'controlletter' => $str(!empty($settings['controlletter']) ? 'howtovalueon' : 'howtovalueoff'),
            'photos' => $str(!empty($settings['photos']) ? 'howtovalueon' : 'howtovalueoff'),
            'timezone' => !empty($settings['timezone']) ? (string) $settings['timezone'] : null,
            default => null,
        };
    }

    /** @var string[] Words nearly every task has: they decide nothing unless the question has no other. */
    private const COMMON = ['libro', 'libros', 'lector', 'lectores', 'mostrador', 'biblioteca', 'bibliotecas', 'book', 'books',
        'reader', 'readers', 'desk', 'library', 'libraries', 'livro', 'livros', 'leitor', 'leitores', 'balcao', 'livre',
        'livres', 'lecteur', 'lecteurs', 'bibliotheque', 'bibliotheques', 'alumno', 'alumnos', 'aluno', 'alunos', 'eleve',
        'eleves', 'student', 'students', 'pupil', 'pupils', 'estudiante', 'estudiantes', 'ejemplar', 'ejemplares', 'copy',
        'copies', 'exemplar', 'exemplares', 'exemplaire', 'exemplaires', 'item', 'items'];

    /**
     * How well a task answers some words. Each word of the question counts once, by the best place it is found in the
     * task (its own words, then its title, then its steps): the task that covers most of the question wins, not the
     * one with most words; all of the question in its title is a bonus; and the words every task has count only when
     * the question has nothing else.
     *
     * @param string[] $words Normalised.
     * @param array $task
     * @return int
     */
    private static function score(array $words, array $task): int {
        $telling = array_values(array_filter($words, fn($w) => !in_array($w, self::COMMON, true)));
        $common = $telling ? array_values(array_diff($words, $telling)) : [];
        $words = $telling ?: $words;
        $fields = [3 => $task['words'], 2 => $task['title'], 1 => implode(' ', $task['steps'])];
        $split = array_map(fn($field) => preg_split('/[^\p{L}\p{N}]+/u', text::normalise($field)) ?: [], $fields);
        // Where a word is found best: 3 in the task's own words, 2 in its title, 1 in its steps, 0 nowhere. A word typed
        // with one letter wrong, missing or doubled («resevas», «histroial») still finds the task's words and title.
        $best = function (string $word) use ($split): int {
            foreach ($split as $weight => $theirs) {
                if (self::in($word, $theirs) || ($weight > 1 && self::near($word, $theirs))) {
                    return $weight;
                }
            }
            return 0;
        };
        $matched = 0;
        $own = false;
        $strong = 0;
        $weights = 0;
        $intitle = 0;
        foreach ($words as $word) {
            $weight = $best($word);
            if ($weight) {
                $own = $own || $weight === 3;
                $strong += $weight > 1 ? 1 : 0;
                $matched++;
                $weights += $weight;
                $intitle += self::in($word, $split[2]) ? 1 : 0;
                // The very word, not only its beginning, breaks a tie: «labels» is the labels', not «no label».
                $weights += in_array($word, $split[2], true) ? 2 : (in_array($word, $split[3], true) ? 1 : 0);
            }
        }
        // Words that only the steps share are chance: an answer needs one of the task's own words or of its title, and
        // half of what the question says at least (a book's title shares a word with some task by chance).
        if (!$matched || !$strong) {
            return 0;
        }
        // The words every task has only break a tie: «repeated reader» is about readers more than about topics.
        foreach ($common as $word) {
            $weights += $best($word);
        }
        // A whole question of two words or more in the title is that task; one word in a title says less. And a
        // question that holds the whole title («lend a book») is asking for that task.
        $titlewords = array_filter($split[2], fn($w) => \core_text::strlen($w) >= 3 && !in_array($w, self::QUIET, true));
        $asked = array_merge($words, $common);
        $whole = $titlewords && !array_filter($titlewords, fn($w) => !self::in($w, $asked) && !array_filter(
            $asked,
            fn($a) => str_starts_with($a, \core_text::substr($w, 0, 5))
        ));
        self::$cover = $matched / max(1, count($words));
        return 10 * $matched + $weights + (count($words) > 1 && $intitle === count($words) ? 5 : 0) + ($whole ? 5 : 0);
    }

    /** @var float How much of the question the last task scored answers, from 0 to 1. */
    private static float $cover = 0;

    /**
     * Whether a word of five letters or more is one of some words but for one letter wrong, missing, doubled or two
     * swapped: the same first letter and at most one change.
     *
     * @param string $word
     * @param string[] $theirs
     * @return bool
     */
    private static function near(string $word, array $theirs): bool {
        $length = strlen($word);
        if ($length < 5) {
            return false;
        }
        foreach ($theirs as $their) {
            if (
                $their !== '' && $their[0] === $word[0] && abs(strlen($their) - $length) <= 1
                    && (levenshtein($word, $their) <= 1 || self::swapped($word, $their))
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether two words differ only in two letters side by side swapped («histroial»).
     *
     * @param string $a
     * @param string $b
     * @return bool
     */
    private static function swapped(string $a, string $b): bool {
        if (strlen($a) !== strlen($b)) {
            return false;
        }
        for ($i = 0; $i < strlen($a) - 1; $i++) {
            if ($a[$i] !== $b[$i]) {
                return $a[$i] === $b[$i + 1] && $a[$i + 1] === $b[$i] && substr($a, $i + 2) === substr($b, $i + 2);
            }
        }
        return false;
    }

    /**
     * Whether a word, or its first five letters, starts one of some words.
     *
     * @param string $word
     * @param string[] $theirs
     * @return bool
     */
    private static function in(string $word, array $theirs): bool {
        $stem = \core_text::substr($word, 0, 5);
        foreach ($theirs as $their) {
            if ($their !== '' && (str_starts_with($their, $word) || str_starts_with($their, $stem))) {
                return true;
            }
        }
        return false;
    }
}
