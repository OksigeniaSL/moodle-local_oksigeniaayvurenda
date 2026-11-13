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
 * What the screens of the catalogue show.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * Builds the data of each screen of the catalogue for its template: the words come ready, so that the templates are
 * plain Mustache and work outside Moodle too.
 */
final class screens {
    /** @var string[] MARC language codes and their two-letter code, to name them in the reader's language. */
    private const LANGUAGES = ['spa' => 'es', 'eng' => 'en', 'fre' => 'fr', 'ger' => 'de', 'ita' => 'it', 'por' => 'pt',
        'cat' => 'ca', 'glg' => 'gl', 'baq' => 'eu', 'grn' => 'gn', 'lat' => 'la', 'ara' => 'ar', 'chi' => 'zh'];

    /**
     * The home of a library: its corners and what came in last.
     *
     * @param \stdClass $library
     * @return array
     */
    public static function home(\stdClass $library): array {
        global $USER;
        $corners = [];
        // A reader of the flower is not told how many books each corner has: a number of seven hundred says nothing at six.
        $young = characters::young((int) $library->entityid);
        foreach (catalogue::corners($library) as $corner) {
            $corners[] = $corner + [
                'url' => self::url('search.php', $library, ['corner' => $corner['code']]),
                'countlabel' => $young ? '' : self::str(
                    $corner['count'] === 1 ? 'onebook' : 'books',
                    self::number((int) $corner['count'])
                ),
                'art' => !empty($corner['genre']) ? ($corner['kind'] ? self::art('genero-' . $corner['kind']) : null)
                    : self::art('rincon-' . preg_replace('/[^a-z0-9]/', '', \core_text::strtolower((string) $corner['code']))),
            ];
        }
        // A genre has no drawing yet: where the others have theirs, its initial in its place, so the tiles stay even.
        if (array_filter(array_column($corners, 'art'))) {
            foreach ($corners as &$corner) {
                if (!empty($corner['genre']) && !$corner['art']) {
                    $corner['mark'] = \core_text::strtoupper(\core_text::substr($corner['label'], 0, 1));
                }
            }
            unset($corner);
        }
        $recent = self::cards(catalogue::recent($library), $library);
        $hero = self::hero($library);
        $desk = null;
        $toread = null;
        $wishes = null;
        if (access::can('local/oksigeniaayvurenda:desk', $library)) {
            $desk = ['url' => self::url('desk.php', $library), 'label' => self::str('deskopen')];
            // The reviews waiting for the library, only when there are some.
            $waiting = reviews::waiting((int) $library->entityid);
            $toread = $waiting ? ['url' => self::url('reviews.php', $library), 'label' => self::str('reviewswaiting', $waiting)]
                : null;
            // The books classes chose as their reward, until the library gets them.
            $asked = wishes::waiting((int) $library->entityid);
            $wishes = $asked ? ['url' => self::url('wishes.php', $library), 'label' => self::str('wisheswaiting', $asked)] : null;
        }
        $addbooks = null;
        if (access::can('local/oksigeniaayvurenda:catalogue', $library)) {
            $addbooks = ['url' => self::url('add.php', $library), 'label' => self::str('addopen'),
                'freeurl' => self::url('free.php', $library), 'freelabel' => self::str('homefreeworks')];
        }
        $shelf = null;
        if ($library->kind === 'classroom' && shelf::can_use($library, (int) $USER->id)) {
            $shelf = ['url' => self::url('shelf.php', $library), 'label' => self::str('shelfopen')];
        }
        // Someone with voices to approve (the library staff, the tutors of those who recorded them) sees it here.
        $pending = count(voices::pending($library, (int) $USER->id));
        $voices = $pending ? ['url' => self::url('voices.php', $library), 'label' => self::str('homevoices', $pending)] : null;
        // The public window: for everyone once it is open, and for whoever manages the library to open it.
        $manage = access::can('local/oksigeniaayvurenda:manage', $library);
        $window = window::settings($library)['open'] || $manage
            ? ['url' => self::url('public.php', $library), 'label' => self::str('homewindow')] : null;
        // Ayvu rande, the voices of the library: for whoever belongs to the centre.
        $spring = digital::viewer((int) $library->entityid, (int) $USER->id)['who'] !== 'nobody'
            ? ['url' => self::url('ayvurande.php', $library), 'label' => self::str('homeayvurande')] : null;
        return [
            'spring' => $spring,
            'window' => $window,
            'voices' => $voices,
            'shelf' => $shelf,
            'desk' => $desk,
            'toread' => $toread,
            'wishes' => $wishes,
            // Not «add»: Mustache would find the method of its helpers by that name when there is none.
            'addbooks' => $addbooks,
            'mycard' => self::mycard($library),
            'athome' => self::at_home($library),
            'hero' => $hero,
            'classblock' => $hero['classreads'] ? ['cards' => $hero['classreads']] : null,
            'eldersblock' => $hero['young'] ? self::elders($library) : null,
            'classheading' => self::said($library, 'homeclass'),
            'shelfprev' => self::str('shelfprev'),
            'shelfnext' => self::str('shelfnext'),
            'classhint' => self::said($library, 'homeclasshint'),
            'heading' => self::str('homeheading'),
            'cornersheading' => self::str('corners'),
            'cornercols' => $corners ? (int) ceil(count($corners) / ceil(count($corners) / 6)) : 1,
            'cornersedit' => $manage ? ['url' => self::url('corners.php', $library), 'label' => self::str('cornersedit')] : null,
            'corners' => $corners,
            'hascorners' => (bool) $corners,
            // The library one visits, when the art package paints it.
            'visitcard' => visit::card($library, false),
            'recentheading' => self::str('recent'),
            'cards' => $recent,
            'hasrecent' => (bool) $recent,
            'herald' => $recent ? self::herald_new($library, $hero) : null,
            'weekbook' => self::week_book($library),
            'board' => self::board($library, $recent) + ['moments' => self::moments($library)],
            'empty' => !$corners && !$recent ? self::str('emptycatalogue') : '',
        ];
    }

    /**
     * Results of a search or of a corner.
     *
     * @param \stdClass $library
     * @param string $query
     * @param string $corner
     * @param int $page
     * @param bool $onlyfree
     * @param int $subject A topic to narrow by, if any.
     * @param bool $asis The words as typed: when nothing is found, no search for the closest ones.
     * @param string $square A square of a cartilla that is no corner (new or rescued): its books.
     * @return array
     */
    public static function results(
        \stdClass $library,
        string $query,
        string $corner,
        int $page,
        bool $onlyfree = false,
        int $subject = 0,
        bool $asis = false,
        string $square = ''
    ): array {
        $typed = '';
        $facets = null;
        if ($square !== '' && $query === '') {
            // The books that fill a square of a cartilla that is no corner: the new ones, or those waiting to be rescued.
            $found = catalogue::square($library, $square, $page, $onlyfree);
            $found['suggestions'] = [];
            $heading = self::str('cartillabooks' . $square);
            $next = ['square' => $square, 'page' => $page + 1];
            $here = ['square' => $square];
        } else if ($corner !== '' && $query === '') {
            $found = catalogue::corner($library, $corner, $page, $onlyfree);
            $label = $corner;
            foreach (catalogue::corners($library) as $known) {
                if ($known['code'] === $corner) {
                    $label = $known['label'];
                }
            }
            $heading = $label;
            $found['suggestions'] = [];
            $next = ['corner' => $corner, 'page' => $page + 1];
            $here = ['corner' => $corner];
        } else {
            $found = catalogue::search($library, $query, $page, $onlyfree, $corner, $subject);
            // A slip of the keys finds the closest words of the catalogue at once, as a child expects; the words as
            // typed stay one link away.
            if ($found['total'] === 0 && !$asis && $found['suggestions']) {
                $closest = catalogue::search($library, $found['suggestions'][0], $page, $onlyfree, $corner, $subject);
                if ($closest['total'] > 0) {
                    $typed = $query;
                    $query = $found['suggestions'][0];
                    // The other close words stay on offer.
                    $found = ['suggestions' => array_slice($found['suggestions'], 1)] + $closest;
                }
            }
            $narrow = array_filter(['corner' => $corner, 'subject' => $subject]);
            $here = ['q' => $query] + $narrow;
            $a = (object) ['count' => self::number((int) $found['total']), 'query' => $query];
            $heading = $found['total'] === 0 ? self::str('noresults', $query) :
                self::str($found['total'] === 1 ? 'oneresult' : 'results', $a);
            $next = ['q' => $query, 'page' => $page + 1] + $narrow;
            $facets = self::facets($library, $query, $found['facets'], $corner, $subject, $onlyfree);
        }
        if ($onlyfree) {
            $next['free'] = 1;
        }
        $suggestions = [];
        foreach ($found['suggestions'] as $suggestion) {
            $suggestions[] = ['text' => $suggestion, 'url' => self::url('search.php', $library, ['q' => $suggestion])];
        }
        $more = ($page + 1) * catalogue::PERPAGE < $found['reachable'];
        return [
            'heading' => $heading,
            'cards' => self::cards($found['cards'], $library),
            'hascards' => (bool) $found['cards'],
            'suggestions' => $suggestions,
            'hassuggestions' => (bool) $suggestions,
            'didyoumean' => self::str('didyoumean'),
            'searched' => $query,
            'corrected' => $typed === '' ? null : [
                'correctedtext' => self::str('searchcorrected', $typed),
                'asislabel' => self::str('searchasis', $typed),
                'asisurl' => self::url('search.php', $library, ['q' => $typed, 'asis' => 1]),
            ],
            // The memory speaks when it found nothing; otherwise it keeps quiet.
            // Sure there is nothing only when there is nothing close either.
            'memory' => $corner === '' && $query !== '' ? self::nothing_found(
                $library,
                $query,
                (int) $found['total'],
                (bool) $suggestions
            ) : null,
            'askforit' => self::ask_for_it($library, $query, (int) $found['total']),
            'tryother' => $found['total'] === 0 && !$suggestions && ($corner !== '' || $query === '' ||
                !characters::of((int) $library->entityid)['memory']['enabled']) ? self::str('tryother') : '',
            'more' => $more ? ['url' => self::url('search.php', $library, $next), 'label' => self::str('more')] : null,
            'facets' => $facets,
            'filter' => $found['total'] > 0 || $onlyfree ? [
                'url' => self::url('search.php', $library, $here + ($onlyfree ? [] : ['free' => 1])),
                'label' => self::str('onlyfree'),
                'pressed' => $onlyfree ? 'true' : 'false',
            ] : null,
        ];
    }

    /**
     * Where a reader asks the library for a book it does not have, after a search that found nothing, if they may.
     *
     * @param \stdClass $library
     * @param string $query
     * @param int $total
     * @return array|null url and label.
     */
    private static function ask_for_it(\stdClass $library, string $query, int $total): ?array {
        global $DB, $USER;
        if ($total > 0 || $query === '' || !isloggedin() || isguestuser()) {
            return null;
        }
        $reader = $DB->get_record('local_oksigeniaayvurenda_reader', ['entityid' => $library->entityid, 'userid' => $USER->id,
            'status' => 'active'], '*', IGNORE_MULTIPLE) ?: null;
        return purchases::may_ask($library, $reader, time()) ? ['url' => self::url('suggest.php', $library, ['q' => $query]),
            'label' => self::str('suggestask')] : null;
    }

    /**
     * Ways to narrow a search: the corners its results are in and the topics they share, each with how many; the one
     * chosen, pressed, takes the narrowing off.
     *
     * @param \stdClass $library
     * @param string $query
     * @param array $facets As catalogue::search() gives them.
     * @param string $corner
     * @param int $subject
     * @param bool $onlyfree
     * @return array|null
     */
    private static function facets(
        \stdClass $library,
        string $query,
        array $facets,
        string $corner,
        int $subject,
        bool $onlyfree
    ): ?array {
        $labels = array_column(catalogue::corners($library, true), 'label', 'code');
        $free = $onlyfree ? ['free' => 1] : [];
        $corners = [];
        foreach ($facets['corners'] as $code => $count) {
            $on = (string) $code === $corner;
            $corners[] = ['label' => $labels[$code] ?? (string) $code, 'count' => $count, 'pressed' => $on ? 'true' : 'false',
                'url' => self::url('search.php', $library, ['q' => $query] + ($on ? [] : ['corner' => $code]) +
                    ($subject ? ['subject' => $subject] : []) + $free)];
        }
        $subjects = [];
        foreach ($facets['subjects'] as $id => $topic) {
            $on = (int) $id === $subject;
            $subjects[] = ['label' => $topic['label'], 'count' => $topic['count'], 'pressed' => $on ? 'true' : 'false',
                'url' => self::url('search.php', $library, ['q' => $query] + ($corner !== '' ? ['corner' => $corner] : []) +
                    ($on ? [] : ['subject' => $id]) + $free)];
        }
        // A single corner with nothing chosen narrows nothing.
        if (count($corners) < 2 && $corner === '') {
            $corners = [];
        }
        if (!$corners && !$subjects) {
            return null;
        }
        // On a phone, folded under «Refine · n»: the books come first, unless a filter is on.
        return ['corners' => $corners, 'hascorners' => (bool) $corners, 'subjects' => $subjects,
            'hassubjects' => (bool) $subjects, 'cornerslabel' => self::str('facetcorners'),
            'subjectslabel' => self::str('facetsubjects'), 'refinelabel' => self::str('facetsrefine'),
            'chips' => count($corners) + count($subjects), 'chosen' => $corner !== '' || $subject > 0];
    }

    /**
     * A record and its copies.
     *
     * @param array $found What catalogue::record() gives.
     * @param \stdClass $library
     * @return array
     */
    public static function record(array $found, \stdClass $library): array {
        $record = $found['record'];
        $by = $found['authorities'];
        $authors = [];
        foreach ($by['author'] ?? [] as $author) {
            $role = $author['role'] && $author['role'] !== 'author' &&
                get_string_manager()->string_exists('role' . $author['role'], 'local_oksigeniaayvurenda')
                ? self::str('role' . $author['role']) : '';
            $authors[] = ['name' => $author['label'], 'role' => $role];
        }
        $facts = [];
        $add = function (string $label, ?string $value) use (&$facts) {
            if ($value !== null && trim($value) !== '') {
                $facts[] = ['label' => $label, 'value' => trim($value)];
            }
        };
        $add(self::str('publisher'), implode(', ', array_column($by['publisher'] ?? [], 'label')));
        $add(self::str('year'), $record->pubyear);
        $add(self::str('language'), self::language($record->lang));
        $add(self::str('extent'), $record->extent);
        $add(self::str('collection'), implode(', ', array_column($by['collection'] ?? [], 'label')));
        $add(self::str('audience'), implode(', ', array_column($by['audience'] ?? [], 'label')));
        $add(self::str('isbn'), $record->isbn);
        $subjects = [];
        foreach ($by['subject'] ?? [] as $subject) {
            $subjects[] = ['label' => $subject['label'], 'url' => self::url('search.php', $library, ['q' => $subject['label']])];
        }
        $copies = [];
        foreach ($found['copies'] as $copy) {
            $copies[] = $copy + [
                'statelabel' => self::str('state' . $copy['state']),
                'where' => $copy['here'] ? self::str('here') : $copy['library'],
            ];
        }
        // A reader does not need sixty lines of a textbook: past a dozen in one library, one line says how many are free.
        if (!access::can('local/oksigeniaayvurenda:desk', $library)) {
            $copies = self::summed_copies($copies);
        }
        $card = self::cards([$found['card']], $library)[0];
        // To cite the book in a reference manager: for whoever is past the flower, and never for material.
        global $DB, $USER;
        $reader = isloggedin() && !isguestuser() ? $DB->get_record('local_oksigeniaayvurenda_reader', ['entityid' =>
            $library->entityid, 'userid' => $USER->id, 'status' => 'active'], '*', IGNORE_MULTIPLE) : null;
        $young = $reader && cards::look_of($reader) === 'flower' && !access::can('local/oksigeniaayvurenda:desk', $library);
        $cite = !$young && $record->doctype !== 'device' ? ['citeurl' => self::url('cite.php', $library, ['id' => $record->id]),
            'citelabel' => self::str('citeris')] : null;
        // Where the institution finds its full text; and to pass it to a class, for whoever teaches.
        $firstauthor = (string) ($authors[0]['name'] ?? '');
        $fulltext = $young || $record->doctype === 'device' ? null : \local_oksigeniaayvurenda\core\links::openurl(
            links::settings($library)['resolver'],
            (string) $record->isbn,
            (string) $record->title,
            $firstauthor
        );
        $teaches = !$young && $record->doctype !== 'device' && (access::can('local/oksigeniaayvurenda:desk', $library)
            || ($reader && $reader->readertype === 'teacher'));
        // Where the catalogue is open, the page anyone can see: nobody in the class needs an account to follow it.
        $address = window::catalogue_open($library) ? self::url('public.php', $library, ['record' => $record->id])
            : self::url('record.php', $library, ['id' => $record->id]);
        return [
            'card' => $card,
            'cite' => $cite,
            'lend' => self::lend_link($record, $library),
            'fulltext' => $fulltext ? ['url' => $fulltext, 'label' => self::str('recordfulltext')] : null,
            // COinS: what Zotero and Mendeley find on the page to take the book with a click.
            'coins' => $young || $record->doctype === 'device' ? '' : \local_oksigeniaayvurenda\core\links::kev(
                (string) $record->isbn,
                (string) $record->title,
                $firstauthor,
                (string) $record->pubyear
            ),
            'passon' => $teaches ? ['heading' => self::str('recordshare'),
                'classroom' => \local_oksigeniaayvurenda\core\links::classroom($address),
                'classroomlabel' => self::str('recordshareclassroom'),
                'teams' => \local_oksigeniaayvurenda\core\links::teams($address, (string) $record->title),
                'teamslabel' => self::str('recordshareteams'),
                'moodle' => self::moodle_courses($library, (int) $record->id)] : null,
            'title' => $record->title,
            'subtitle' => $record->subtitle,
            'authors' => $authors,
            'facts' => $facts,
            'hasfacts' => (bool) $facts,
            'oldline' => self::old_line($library, (string) $record->pubyear),
            'subjects' => $subjects,
            'hassubjects' => (bool) $subjects,
            'subjectsheading' => self::str('subjects'),
            'summary' => $record->summary,
            'summaryheading' => self::str('summary'),
            'copies' => $copies,
            'hascopies' => (bool) $copies,
            'copiesheading' => self::str('copies'),
            'callnumberlabel' => self::str('callnumber'),
            'back' => ['url' => self::url('index.php', $library), 'label' => self::str('backtolibrary')],
        ];
    }

    /**
     * Asking for a book in another format: for a reader past the flower, or one the library knows needs it; what they
     * already asked, and a word after asking.
     *
     * @param array $found As catalogue::record() gives it.
     * @param \stdClass $library
     * @param string $notice What just happened, from formatask.php: asked or no.
     * @return array|null
     */
    public static function formatask(array $found, \stdClass $library, string $notice = ''): ?array {
        global $DB, $USER;
        $record = $found['record'];
        if (!isloggedin() || isguestuser() || $record->doctype === 'device' || practice::is((int) $library->entityid)) {
            return null;
        }
        $reader = $DB->get_record('local_oksigeniaayvurenda_reader', ['entityid' => $library->entityid, 'userid' => $USER->id,
            'status' => 'active'], '*', IGNORE_MULTIPLE);
        // A child with the flower asks through the library, unless it already knows they need it.
        if (!$reader || (cards::look_of($reader) === 'flower' && empty($reader->accessneeds))) {
            return null;
        }
        $open = formats::open_of((int) $reader->id, (int) $record->id);
        $label = fn(string $format) => self::str('format' . $format . 'label');
        $line = $open ? self::str($notice === 'asked' ? 'formataskedfresh' : 'formataskedbefore', \core_text::strtolower(
            $label((string) $open->format)
        )) : ($notice === 'no' ? self::str('formataskno') : '');
        return [
            'open' => (bool) $open,
            'line' => $line,
            'button' => self::str('formataskbutton'),
            'heading' => self::str('formataskheading'),
            'intro' => self::str('formataskintro'),
            'go' => self::str('formataskgo'),
            'close' => self::str('formatsclose'),
            'action' => (new \moodle_url('/local/oksigeniaayvurenda/formatask.php'))->out(false),
            'sesskey' => sesskey(),
            'library' => $library->slug,
            'recordid' => (int) $record->id,
            'choices' => array_map(
                fn($format, $n) => ['value' => $format, 'label' => $label($format), 'first' => $n === 0],
                formats::FORMATS,
                array_keys(formats::FORMATS)
            ),
        ];
    }

    /**
     * The reservation box of a record: for a reader, what they reserved or the button to reserve it, if their stage
     * may; for the staff at the desk, reserving it for a reader. Nothing when a copy is free here.
     *
     * @param array $found As catalogue::record() gives it.
     * @param \stdClass $library
     * @param string $notice A key of what just happened, from hold.php.
     * @return array|null
     */
    public static function hold(array $found, \stdClass $library, string $notice = ''): ?array {
        global $DB, $USER;
        $now = time();
        $recordid = (int) $found['record']->id;
        $state = $found['card']['state'];
        $box = ['action' => (new \moodle_url('/local/oksigeniaayvurenda/hold.php'))->out(false), 'sesskey' => sesskey(),
            'library' => $library->slug, 'recordid' => $recordid, 'notice' => '', 'mine' => '', 'ahead' => '',
            'holdid' => 0, 'canask' => false, 'staff' => false];
        $notices = ['holdadded', 'holdfree', 'holdhas', 'holdmax', 'holdnocopies', 'holdnotallowed', 'holdcancelled',
            'holdlaterdone', 'holdresumed',
            'holdfreefor', 'holdhasfor', 'holdmaxfor', 'holdnocopiesfor', 'holdnoreader', 'holdmanyreaders'];
        if (in_array($notice, $notices, true)) {
            $box['notice'] = self::str($notice);
        } else if (str_starts_with($notice, 'holdaddedfor')) {
            $box['notice'] = self::str('holdaddedfor', (string) optional_param('who', '', PARAM_TEXT));
        }
        $reader = isloggedin() && !isguestuser() ? $DB->get_record(
            'local_oksigeniaayvurenda_reader',
            ['entityid' => $library->entityid, 'userid' => $USER->id, 'status' => 'active'],
            'id',
            IGNORE_MULTIPLE
        ) : null;
        // A book they have out themselves: until when, and nothing to ask for.
        $loan = $reader ? $DB->get_record_sql(
            "SELECT l.id, l.timedue
               FROM {local_oksigeniaayvurenda_loan} l
               JOIN {local_oksigeniaayvurenda_item} i ON i.id = l.itemid
              WHERE i.recordid = :recordid AND l.readerid = :readerid AND l.timereturned IS NULL",
            ['recordid' => $recordid, 'readerid' => $reader->id],
            IGNORE_MULTIPLE
        ) : null;
        if ($loan) {
            $short = get_string('strftimedateshort', 'langconfig');
            $box['mine'] = self::str('holdyouhaveit', zones::date((int) $loan->timedue, $short, $library));
            $box['loanid'] = (int) $loan->id;
            $box['minekind'] = 'loan';
            $box['mineuntil'] = (int) $loan->timedue;
        } else if ($reader) {
            // Theirs here, or in another library of the centre: one reservation is enough (int-aty-pixel 1).
            $mine = holds::open((int) $library->id, $recordid, (int) $reader->id)
                ?? holds::open_anywhere($recordid, (int) $reader->id);
            if ($mine) {
                $box['holdid'] = (int) $mine->id;
                $box['elsewhere'] = (int) $mine->libraryid !== (int) $library->id ? self::str(
                    'holdelsewhere',
                    format_string((string) $DB->get_field(
                        'local_oksigeniaayvurenda_library',
                        'name',
                        ['id' => $mine->libraryid]
                    ), true, ['escape' => false])
                ) : '';
                $box['minekind'] = $mine->status === 'ready' ? 'ready' : 'waiting';
                $box['mineuntil'] = (int) $mine->timeexpires;
                $paused = (int) ($mine->timesuspended ?? 0) > $now;
                if ($mine->status === 'ready') {
                    $box['mine'] = self::str('holdmineready', zones::date((int) $mine->timeexpires, 'strftimedateshort', $library));
                } else if ($paused) {
                    // Not yet: put off, keeping its place in the queue (MER-03).
                    $box['mine'] = self::str(
                        'holdminepaused',
                        zones::date((int) $mine->timesuspended, 'strftimedateshort', $library)
                    );
                } else {
                    $box['mine'] = self::str('holdminewaiting');
                    $ahead = holds::ahead($mine);
                    $box['ahead'] = $ahead ? self::str('holdmineahead', $ahead) : '';
                }
                $box['paused'] = $paused;
                $box['later'] = $paused ? [] : array_map(fn($days) => ['days' => $days,
                    'label' => self::str('holdlater' . $days)], [7, 14, 30]);
                $box['haslater'] = !$paused;
            } else if (in_array($state, ['out', 'held'], true) && holds::online($library, (int) $reader->id, $now)) {
                $box['canask'] = true;
            }
        }
        $isdesk = access::can('local/oksigeniaayvurenda:desk', $library);
        if (in_array($state, ['out', 'held'], true) && $isdesk) {
            $box['staff'] = true;
            $box['holdsurl'] = self::url('holds.php', $library);
        }
        if (!$box['notice'] && !$box['mine'] && !$box['canask'] && !$box['staff']) {
            return null;
        }
        // The box is named by what it holds: «You have it» is not a part called «Tell me when it comes back».
        $box['boxlabel'] = ($box['minekind'] ?? '') === 'loan' ? self::str('holdboxmine') : self::str('holdask');
        foreach (
            ['holdask', 'holdaskhint', 'holdcancelmine', 'holdfor', 'holdforhint', 'holdforgo', 'holdslink', 'holdlater',
                'holdlaterhint', 'holdresume'] as $key
        ) {
            $box[$key] = self::str($key);
        }
        return $box;
    }

    /**
     * The courses of this Moodle where whoever looks can put the book in the readings: those they can change, whose
     * library is of the same centre.
     *
     * @param \stdClass $library
     * @param int $recordid
     * @return array|null courses (id, name, listed), the address and the words of the sheet; null when there is none.
     */
    private static function moodle_courses(\stdClass $library, int $recordid): ?array {
        global $DB;
        if (!isloggedin() || isguestuser() || onlylibrary::on()) {
            return null;
        }
        $courses = [];
        foreach (enrol_get_my_courses(['category'], 'fullname ASC', 60) as $course) {
            if ((int) $course->id === SITEID || !has_capability('moodle/course:update', \context_course::instance($course->id))) {
                continue;
            }
            $list = readings::of_course((int) $course->id);
            $its = $list ? $DB->get_record('local_oksigeniaayvurenda_library', ['id' => $list->libraryid])
                : readings::library_for($course);
            if (!$its || (int) $its->entityid !== (int) $library->entityid) {
                continue;
            }
            $courses[] = ['id' => (int) $course->id, 'name' => format_string($course->fullname, true, ['escape' => false]),
                'listed' => $list && $DB->record_exists('local_oksigeniaayvurenda_readingitem', ['listid' => $list->id,
                    'recordid' => $recordid]),
                'url' => (new \moodle_url('/local/oksigeniaayvurenda/readings.php', ['course' => $course->id]))->out(false)];
        }
        if (!$courses) {
            return null;
        }
        return ['courses' => $courses, 'action' => (new \moodle_url('/local/oksigeniaayvurenda/readings.php'))->out(false),
            'sesskey' => sesskey(), 'recordid' => $recordid, 'label' => self::str('recordsharemoodle'),
            'title' => self::str('recordsharemoodletitle'), 'hint' => self::str('recordsharemoodlehint'),
            'required' => self::str('readingsrequired'), 'addlabel' => self::str('recordsharemoodleadd'),
            'listedlabel' => self::str('recordsharemoodlelisted'), 'close' => self::str('offsiteclose')];
    }

    /**
     * For whoever works at the desk of this library, the way to lend a book from its page: the desk opens with it.
     *
     * @param \stdClass $record
     * @param \stdClass $library
     * @return array|null
     */
    private static function lend_link(\stdClass $record, \stdClass $library): ?array {
        global $USER;
        if (!access::can('local/oksigeniaayvurenda:desk', $library) && !access::keeps($library)) {
            return null;
        }
        $desk = new desk($library, (int) $USER->id);
        return $desk->shelf_book((int) $record->id) ? ['url' => self::url('desk.php', $library, ['book' => $record->id]),
            'label' => self::str('recordlend')] : null;
    }

    /**
     * The digital forms of a record that whoever looks may open: read, listen, watch; and, for the catalogue staff, the
     * way to add or change them.
     *
     * @param array $found As catalogue::record() gives it.
     * @param \stdClass $library
     * @return array|null
     */
    public static function digital(array $found, \stdClass $library): ?array {
        global $USER;
        $recordid = (int) $found['record']->id;
        $viewer = digital::viewer((int) $library->entityid, (int) $USER->id);
        $items = [];
        foreach (digital::of_record($recordid, $library, $viewer) as $item) {
            $label = $item->title !== '' && $item->title !== null ? $item->title : self::str('format' . $item->format);
            // A voice's signature, once someone has read and approved it.
            if ($item->kind === 'voice' && $item->status === 'published' && (string) $item->signature !== '') {
                $label .= ' · ' . $item->signature;
            }
            $items[] = [
                'label' => $label . ($item->status === 'pending' ? ' ' . self::str('voicependingshort') : ''),
                'action' => self::str('digitalaction' . $item->format),
                // A book to read: «Read the book», with what it is in small beside.
                'book' => $item->kind === 'file' && in_array($item->format, ['epub', 'pdf'], true),
                'url' => self::url('read.php', $library, ['id' => $item->id]),
            ];
        }
        $manage = access::can('local/oksigeniaayvurenda:catalogue', $library)
            ? ['url' => self::url('digital.php', $library, ['record' => $recordid]), 'label' => self::str('digitalmanage')] : null;
        // Readers of the centre and the library staff may give the book a voice, when the centre's plan brings voices.
        $known = $viewer['who'] !== 'nobody';
        // A textbook only a bank has is not a book to give a voice to.
        $textbook = in_array($recordid, bank::only((int) $library->entityid), true);
        $voice = $known && !$textbook && plans::allows((int) $library->entityid, 'voices') ? ['url' => self::url(
            'voice.php',
            $library,
            ['record' => $recordid]
        ), 'question' => self::str('digitalvoiceask'), 'label' => self::str('digitalvoicego')] : null;
        if (!$items && !$manage && !$voice) {
            return null;
        }
        return ['heading' => self::str('digitalheading'), 'items' => $items, 'manage' => $manage, 'voice' => $voice,
            'readbook' => self::str('digitalreadbook'), 'spring' => $known ? ['url' => self::url('ayvurande.php', $library),
            'label' => self::str('digitalayvurande')] : null];
    }

    /**
     * What a pupil sees of a bank of textbooks: the books lent to them for the year, how they were given and until when.
     * Not a library to visit.
     *
     * @param \stdClass $library
     * @param int|null $now
     * @return array
     */
    public static function bank_mine(\stdClass $library, ?int $now = null): array {
        global $DB, $USER;
        $now = $now ?? time();
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        $short = get_string('strftimedate', 'langconfig');
        $reader = $DB->get_records('local_oksigeniaayvurenda_reader', ['entityid' => $library->entityid, 'userid' => $USER->id,
            'status' => 'active'], 'id', 'id', 0, 1);
        $books = [];
        if ($reader) {
            $rows = $DB->get_records_sql(
                "SELECT l.id, l.timedue, l.conditionout, i.barcode, r.title
                   FROM {local_oksigeniaayvurenda_loan} l
                   JOIN {local_oksigeniaayvurenda_item} i ON i.id = l.itemid
                   JOIN {local_oksigeniaayvurenda_record} r ON r.id = i.recordid
                  WHERE l.libraryid = :libraryid AND l.readerid = :readerid AND l.timereturned IS NULL
               ORDER BY r.title, l.id",
                ['libraryid' => $library->id, 'readerid' => (int) reset($reader)->id]
            );
            foreach ($rows as $row) {
                $books[] = ['title' => format_string($row->title, true, ['escape' => false]), 'code' => $row->barcode,
                    'condition' => desk::condition_label((string) ($row->conditionout ?: 'good'), true),
                    'due' => zones::date((int) $row->timedue, $short, $library)];
            }
        }
        $due = $books ? zones::date((int) bankround::due($library, $now), $short, $library) : '';
        return [
            'heading' => $str('bankminetitle'),
            'name' => format_string($library->name, true, ['escape' => false]),
            'intro' => $books ? $str('bankmineintro', $due) : $str('bankmineempty'),
            'books' => $books,
            'hasbooks' => (bool) $books,
            'conditionlabel' => $str('bankminecondition'),
            'duelabel' => $str('bankminedue'),
        ];
    }

    /**
     * The choice of a library, when there are several.
     *
     * @return array
     */
    public static function choose(): array {
        global $USER;
        // A bank of textbooks is not a library to choose to visit, except for whoever works at it.
        $all = array_values(array_filter(catalogue::visible(), fn($library) => !bank::is($library)
            || access::can('local/oksigeniaayvurenda:desk', $library) || access::keeps($library)));
        $featured = libraries::featured($all, isloggedin() && !isguestuser() ? (int) $USER->id : 0);
        $card = function (\stdClass $library) {
            $look = libraries::look($library);
            return ['name' => format_string($library->name, true, ['escape' => false]), 'url' => self::url('index.php', $library),
                'kindlabel' => self::str('librarykind' . $library->kind), 'motto' => format_string(
                    $look['motto'],
                    true,
                    ['escape' => false]
                ),
                'colour' => $look['colour'], 'image' => $look['image']];
        };
        $libraries = [];
        foreach ($all as $library) {
            if (!$featured || (int) $library->id !== (int) $featured->id) {
                $libraries[] = $card($library);
            }
        }
        $manage = has_capability('local/oksigeniaayvurenda:manage', \context_system::instance());
        // No library yet: whoever may set one up names it here and is in, without another page on the way.
        $first = null;
        if ($manage && libraries::none()) {
            $first = ['action' => (new \moodle_url('/local/oksigeniaayvurenda/libraries.php'))->out(false),
                'sesskey' => sesskey(), 'none' => true, 'sample' => libraries::sample_offer()];
            foreach (['librariesfirst', 'librariesfirsthint', 'librariesfirstname', 'librariescreate'] as $key) {
                $first[$key] = self::str($key);
            }
        }
        $top = $featured ? $card($featured) : null;
        if ($top) {
            if (libraries::look($featured)['featured']) {
                $top['kindlabel'] = self::str('choosemain');
            }
            // Without a picture of its own, the scene of the art package, if the site has it.
            $top['image'] = $top['image'] ?? self::art('portada');
            $top['enter'] = self::str('chooseenter');
        }
        return [
            'heading' => self::str($all ? 'chooselibrary' : 'nolibrary'),
            'featured' => $top,
            'libraries' => $libraries,
            'hasothers' => (bool) $libraries,
            'othersheading' => self::str('chooseothers'),
            'manage' => $manage && !$first ? ['url' => (new \moodle_url('/local/oksigeniaayvurenda/libraries.php'))->out(false),
                'label' => self::str($libraries ? 'librariesnav' : 'librariesstart')] : null,
            'first' => $first,
        ];
    }

    /**
     * A picture of the art package, if the site has it: the plugin carries none of its own (it goes apart, with its
     * own licence), and without it every screen keeps its drawn look.
     *
     * @param string $name
     * @param string $type webp, svg for the drawings used as masks, glb for the models of the island in 3D, or json for
     *     the puppets' data (a name may then carry one more part, «marionetas/memoria.atlas»).
     * @return string|null Its address.
     */
    public static function art(string $name, string $type = 'webp'): ?string {
        $dir = \core_component::get_component_directory('local_oksigeniaayvurendaarte');
        if (
            !$dir || !preg_match('#^[a-z0-9-]+(/[a-z0-9-]+(\.[a-z]+)?)*$#', $name)
                || !in_array($type, ['webp', 'svg', 'glb', 'json', 'jpg', 'png'], true)
                || !is_readable($dir . '/pix/' . $name . '.' . $type)
        ) {
            return null;
        }
        return (new \moodle_url('/local/oksigeniaayvurendaarte/pix/' . $name . '.' . $type))->out(false) . self::artversion();
    }

    /**
     * The picture of the home page for a centre's skin: the school's reading room only for a school; another skin, its
     * own (a «portada-<skin>» when the art package brings one, else the header of its notices), never children reading
     * in a university or a company.
     *
     * @param int $entityid
     * @return string|null
     */
    public static function skin_art(int $entityid): ?string {
        $skin = skins::of($entityid);
        // A reader of the sky in a school sees the secondary school's picture, not the infants' on their cushions.
        if ($skin === 'school' && characters::step($entityid) === 'cielo' && ($older = self::art('portada-instituto'))) {
            return $older;
        }
        if ($skin === 'school') {
            return self::art('portada');
        }
        $name = mail::PICTURES[$skin] ?? '';
        return $name === '' ? null : (self::art('portada-' . $name) ?? self::art('correo/' . $name, 'jpg'));
    }

    /**
     * The size of a file as the page's language writes it: «5,5 MB» in Spanish, «5.5 MB» in English (Moodle's
     * display_size writes the point everywhere).
     *
     * @param int $bytes
     * @return string
     */
    public static function size(int $bytes): string {
        foreach ([[1073741824, 'GB'], [1048576, 'MB'], [1024, 'KB']] as [$unit, $name]) {
            if ($bytes >= $unit) {
                return format_float($bytes / $unit, $bytes >= 10 * $unit ? 0 : 1, true, true) . ' ' . $name;
            }
        }
        return $bytes . ' B';
    }

    /**
     * A whole number as the page's language writes it, with its thousands separator (17.378 in Spanish, 17,378 in
     * English); plain digits without the intl extension.
     *
     * @param int $number
     * @return string
     */
    public static function number(int $number): string {
        static $formatters = [];
        $lang = current_language();
        if (!class_exists('NumberFormatter')) {
            return (string) $number;
        }
        $formatters[$lang] ??= new \NumberFormatter(str_replace('_', '-', $lang), \NumberFormatter::DECIMAL);
        return (string) ($formatters[$lang]->format($number) ?: $number);
    }

    /**
     * What the addresses of the art package carry so that a new version of it is fetched at once: a cache on the way
     * (a proxy in front of the site) may keep a file for hours under the same address.
     *
     * @return string Like ?v=2026102616; empty without the package.
     */
    public static function artversion(): string {
        $version = (int) get_config('local_oksigeniaayvurendaarte', 'version');
        return $version ? '?v=' . $version : '';
    }

    /**
     * Spines with what they say: their state in words, what the gap of one that is out says, and all of it for a
     * screen reader (title, author, call number, state).
     *
     * @param array[] $spines From stacks.
     * @return array[]
     */
    public static function spines(array $spines): array {
        foreach ($spines as &$spine) {
            $spine['whys'] = array_map(fn($why) => ['text' => self::str(
                $why['kind'] === 'class' ? ($why['word'] === ''
                ? 'whyclassonly' : ($why['corner'] !== '' ? 'whyclasscorner' : 'whyclass')) : 'why' . $why['kind'],
                (object) $why
            )], $spine['why'] ?? []);
            $spine['whyjoined'] = implode('|', array_column($spine['whys'], 'text'));
            $spine['statetext'] = self::str('state' . $spine['state']);
            $spine['awaytext'] = $spine['out'] ? self::str($spine['held'] ? 'stateheld' : 'stacksaway') : '';
            $spine['label'] = implode(', ', array_filter([$spine['title'], $spine['authors'], $spine['callnumber'],
                $spine['statetext']], fn($part) => (string) $part !== ''));
        }
        return $spines;
    }

    /**
     * How to find a record on the shelf of this library: its label, big, what each line says and the place it stands
     * in. Nothing when the library has no copy of it.
     *
     * @param \stdClass $library
     * @param int $recordid
     * @return array|null heading, lines, placecolour, placeline, whys, order.
     */
    public static function where(\stdClass $library, int $recordid): ?array {
        $spine = stacks::spines($library, [(object) ['recordid' => $recordid, 'k' => '']])[0] ?? null;
        if (!$spine || !$spine['lines']) {
            return null;
        }
        $spine = self::spines([$spine])[0];
        return [
            'heading' => self::str('whyheading'),
            'lines' => $spine['lines'],
            'placecolour' => $spine['placecolour'],
            'placeline' => $spine['place'] !== '' ? self::str('whyplace', $spine['place']) : '',
            'whys' => $spine['whys'],
            'order' => self::str('whyorder'),
        ];
    }

    /**
     * Spines for someone without an account, in a library that shows its catalogue: each leads to the record in the
     * window, not to the library's own page.
     *
     * @param array[] $spines From stacks.
     * @param \stdClass $library
     * @return array[]
     */
    public static function public_spines(array $spines, \stdClass $library): array {
        foreach ($spines as &$spine) {
            $spine['url'] = (new \moodle_url('/local/oksigeniaayvurenda/public.php', ['library' => $library->slug,
                'record' => $spine['id']]))->out(false);
        }
        return $spines;
    }

    /**
     * The class's garden as Gaudí's island: its base, a piece for each challenge met this school year (three, one a
     * term), the lighthouse when one was of everyone, and the boat when the book the class asked for arrived. Layers of
     * the same size, one on top of another; null without the art package, and then the garden stays a list.
     *
     * @param int $met Challenges met this school year.
     * @param bool $everyone Whether one of them was of everyone in the class.
     * @param bool $boat Whether a book the class asked for arrived.
     * @return string[]|null Addresses of the layers, from the bottom.
     */
    public static function island(int $met, bool $everyone, bool $boat): ?array {
        $base = self::art('isla/base');
        if (!$base) {
            return null;
        }
        $names = array_map(fn($n) => 'isla/reto-' . $n, range(1, min(3, $met)));
        if ($met < 1) {
            $names = [];
        }
        if ($everyone) {
            $names[] = 'isla/faro';
        }
        if ($boat) {
            $names[] = 'isla/barco';
        }
        return array_merge([$base], array_values(array_filter(array_map([self::class, 'art'], $names))));
    }

    /** @var string[] The pencils of the drawing pad: the house's colours, by name. */
    private const DRAWCOLOURS = ['ink' => '#1e2b2a', 'green' => '#17574c', 'coral' => '#e2654c', 'sun' => '#f0b43c',
        'blue' => '#3d7cc9', 'brown' => '#8a5a3b'];

    /**
     * What readers tell about a book: the published reviews, signed, and for a reader who took it home, their own.
     *
     * @param array $found The record, as catalogue::record() gives it.
     * @param \stdClass $library
     * @param string $done What the reader just did: sent, removed or nothing.
     * @return array
     */
    public static function reviews(array $found, \stdClass $library, string $done = ''): array {
        global $DB, $USER;
        $recordid = (int) $found['record']->id;
        $reader = isloggedin() && !isguestuser() ? $DB->get_record('local_oksigeniaayvurenda_reader', [
            'entityid' => $library->entityid, 'userid' => $USER->id, 'status' => 'active']) : null;
        $mine = $reader ? reviews::mine($recordid, (int) $reader->id) : null;
        $canwrite = $reader && reviews::can_write($recordid, (int) $reader->id);
        // The older readers (not the flower) may recommend it to the young.
        $older = $canwrite && cards::look_of($reader) !== 'flower';
        $list = array_map(
            fn($r) => $r + ['drawingalt' => self::str('reviewdrawingof', $r['who']),
                'voicelabel' => self::str('reviewvoiceof', $r['who'])],
            reviews::published($recordid, (int) $library->entityid, (int) ($USER->id ?? 0))
        );
        // A textbook only a bank has is not reviewed.
        $textbook = in_array($recordid, bank::only((int) $library->entityid), true);
        return [
            'show' => !$textbook && ($list || $canwrite),
            'heading' => self::str('reviewsheading'),
            'none' => self::str('reviewsnone'),
            'list' => $list,
            'haslist' => (bool) $list,
            'done' => in_array($done, ['sent', 'removed'], true) ? self::str('reviewdone' . $done) : '',
            'write' => $canwrite ? [
                'action' => self::url('record.php', $library, ['id' => $recordid]),
                'sesskey' => sesskey(),
                'label' => self::str('reviewask'),
                'hint' => self::str('reviewhint', reviews::MAX),
                'max' => reviews::MAX,
                'text' => $mine ? $mine->text : '',
                'send' => self::str($mine ? 'reviewresend' : 'reviewsend'),
                'hasmine' => (bool) $mine,
                'state' => $mine ? self::str('reviewstate' . $mine->status) : '',
                // To its author alone, and only from a few readers on: how many took the book since it went up (P4).
                'scent' => $mine && ($taken = reviews::followers($mine)) >= anonymity::FEW ? self::str('reviewscent', $taken) : '',
                'remove' => self::str('reviewremove'),
                'canrecommend' => $older,
                'recommend' => self::str('reviewforlittle'),
                'recommended' => $mine && $mine->forlittle,
                // The drawing pad: for whoever would rather draw than write.
                'drawopen' => self::str('reviewdrawopen'),
                'mydrawing' => $mine ? reviews::drawing((int) $mine->id) : null,
                'mydrawingalt' => self::str('reviewdrawingmine'),
                'drawcanvas' => self::str('drawcanvas'),
                'drawtools' => self::str('drawtools'),
                'drawcolours' => array_map(fn($c, $name) => ['value' => $c, 'label' => self::str('drawcolour' . $name),
                    'pressed' => $name === 'ink' ? 'true' : 'false'], self::DRAWCOLOURS, array_keys(self::DRAWCOLOURS)),
                'drawthin' => self::str('drawthin'),
                'drawthick' => self::str('drawthick'),
                'draweraser' => self::str('draweraser'),
                'drawundo' => self::str('drawundo'),
                'drawclear' => self::str('drawclear'),
                // The recorder: for whoever would rather say it, when the centre's plan brings voices.
                'canspeak' => plans::allows((int) $library->entityid, 'voices'),
                'young' => characters::young((int) $library->entityid),
                'speakopen' => self::str('reviewspeakopen'),
                'speakrecord' => self::str('reviewspeakrecord'),
                'speakstop' => self::str('reviewspeakstop'),
                'speakagain' => self::str('reviewspeakagain'),
                'speakhint' => self::said($library, 'reviewspeakhint'),
                'speaknomic' => self::str('reviewspeaknomic'),
                'myvoice' => $mine ? reviews::voice((int) $mine->id) : null,
                'myvoicelabel' => self::str('reviewvoicemine'),
            ] : null,
        ];
    }

    /**
     * A voice clip of the art package (voz/<path>.mp3), when the site has it.
     *
     * @param string $path Like ui/es/homequestion.
     * @return string|null Its address.
     */
    public static function spoken(string $path): ?string {
        $dir = \core_component::get_component_directory('local_oksigeniaayvurendaarte');
        if (!$dir || !preg_match('#^[a-z0-9-]+(/[a-z0-9-]+)*$#', $path) || !is_readable($dir . '/voz/' . $path . '.mp3')) {
            return null;
        }
        return (new \moodle_url('/local/oksigeniaayvurendaarte/voz/' . $path . '.mp3'))->out(false) . self::artversion();
    }

    /**
     * «Listen» for a young reader: the clips of some lines, in the reader's language, played in turn.
     *
     * @param string[] $paths With {lang} for the language, like ui/{lang}/homequestion.
     * @return array|null urls and label; null when none of them has a voice.
     */
    public static function listen(array $paths): ?array {
        $lang = substr(current_language(), 0, 2);
        $urls = array_values(array_filter(array_map(fn($p) => self::spoken(str_replace('{lang}', $lang, $p)), $paths)));
        return $urls ? ['urls' => implode(' ', $urls), 'label' => self::str('listen')] : null;
    }

    /**
     * The home of a young reader (the card of the flower), from Jujol's mock-up: one screen, the herald greeting with its
     * voice and three big doors, each with its drawing, its word and its voice apart (hearing it does not open it): a
     * story for today (the memory's book), into the library (the visit) and their flower (their card). No field to
     * type in; «For the grown-ups» opens the whole home.
     *
     * @param \stdClass $library
     * @param array $hero What self::hero() said, with young set.
     * @return array greeting, hello, herald, doors, elders.
     */
    public static function little(\stdClass $library, array $hero): array {
        global $DB, $USER;
        $entityid = (int) $library->entityid;
        $step = characters::step($entityid);
        $voice = 'herald-' . ($hero['heraldsex'] === 'f' ? 'f' : 'm') . '/{lang}/';
        $name = format_string($USER->firstname, true, ['escape' => false]);
        $reader = $DB->get_record('local_oksigeniaayvurenda_reader', ['entityid' => $entityid, 'userid' => $USER->id,
            'status' => 'active']);
        $stage = $reader ? (int) garden::album($reader)['flower']['stage'] : 0;
        $book = !empty($hero['remembers']['cover']) ? $hero['remembers'] : null;
        $doors = [[
            'kind' => 'cuento',
            'url' => $book ? $book['url'] : self::url('search.php', $library, ['corner' => '8']),
            'image' => $book ? $book['cover'] : self::art('rincon-8'),
            'cover' => true,
            'pointing' => characters::figure('memory', 'senala', $step),
            'word' => self::str('littlestory'),
            'listen' => self::listen([$voice . 'infantstory']),
        ]];
        $visit = visit::card($library, false);
        if ($visit) {
            $doors[] = ['kind' => 'sala', 'url' => $visit['url'], 'image' => self::art('visita/vestibulo-fondo'),
                'cover' => false, 'pointing' => null, 'word' => self::str('littlevisit'),
                'listen' => self::listen([$voice . 'infantvisit'])];
        }
        $doors[] = ['kind' => 'flor', 'url' => self::url('card.php', $library),
            'image' => self::art('carne/flor/' . ['semilla', 'brote', 'capullo', 'flor'][min(3, max(0, $stage))]),
            'cover' => false, 'pointing' => null, 'word' => self::str('littleflower'),
            'listen' => self::listen([$voice . 'heraldstage' . $stage])];
        foreach ($doors as &$door) {
            $door['listenlabel'] = self::str('littlelisten', $door['word']);
        }
        unset($door);
        $hello = self::str('littlehello', $name);
        return [
            'greeting' => $hello,
            'herald' => characters::figure('herald', 'saluda', $step),
            'hello' => self::listen([$voice . 'infanthello']),
            'hellolabel' => self::str('littlelisten', $hello),
            'doors' => $doors,
            'elders' => ['url' => self::url('index.php', $library, ['todo' => 1]), 'label' => self::str('littleelders')],
        ];
    }

    /**
     * The top of the home: the greeting, the search with its ideas and, for a reader, their card as it grows.
     *
     * @param \stdClass $library
     * @return array greeting, question, search (action, slug, placeholder, label), ideas, you, art.
     */
    private static function hero(\stdClass $library): array {
        global $DB, $USER;
        $reader = null;
        if (isloggedin() && !isguestuser()) {
            $reader = $DB->get_record('local_oksigeniaayvurenda_reader', ['entityid' => $library->entityid,
                'userid' => $USER->id, 'status' => 'active']) ?: null;
        }
        $year = years::around((int) $library->entityid, time());
        $memory = characters::of((int) $library->entityid)['memory'] ?? null;
        $asks = $memory && $memory['enabled'];
        $ideas = [];
        foreach (catalogue::ideas($library, (int) $year->timestart, $reader ? (int) $reader->id : 0) as $idea) {
            $ideas[] = ['label' => $idea['last'] ? self::said($library, 'homelikelast') : $idea['label'],
                'url' => self::url('search.php', $library, ['subject' => $idea['id'], 'q' => $idea['label']])];
        }
        $hero = [
            'greeting' => $reader ? self::str('homehello', format_string(
                $USER->firstname,
                true,
                ['escape' => false]
            )) : format_string($library->name, true, ['escape' => false]),
            'question' => self::said($library, 'homequestion'),
            'action' => self::url('search.php', $library),
            'slug' => $library->slug,
            'placeholder' => $asks ? self::said($library, 'homeaskmemory', $memory['name']) : self::str('searchplaceholder'),
            'memoryinitial' => $asks ? $memory['initial'] : '',
            'memoryportrait' => $asks ? $memory['portrait'] : null,
            'searchlabel' => self::str('searchbutton'),
            'ideas' => $ideas,
            'hasideas' => (bool) $ideas,
            'art' => self::skin_art((int) $library->entityid),
            'emblem' => (new \moodle_url('/local/oksigeniaayvurenda/app/emblema.svg'))->out(false),
            'you' => null,
            'challenge' => null,
            'classreads' => [],
            // The young (the card with the flower) can hear what they may not read yet.
            'young' => false,
            'heraldsex' => 'm',
            'listenquestion' => null,
            'wishherald' => null,
            'remembers' => null,
        ];
        if (!$reader) {
            return $hero;
        }
        $album = garden::album($reader);
        $member = $DB->get_record(
            'local_oksigeniaayvurenda_member',
            ['readerid' => $reader->id, 'yearid' => $album['year']->id],
            'id, groupname, stage',
            IGNORE_MULTIPLE
        );
        $settings = json_decode((string) $DB->get_field(
            'local_oksigeniaayvurenda_entity',
            'settings',
            ['id' => $library->entityid]
        ), true) ?: [];
        $stagename = $member ? (string) $member->stage : '';
        $group = $member ? (string) $member->groupname : '';
        [$guess, $centre] = cards::base($reader, $stagename, $group, $settings);
        $look = \local_oksigeniaayvurenda\core\card_look::resolve(
            $guess,
            $centre,
            get_user_preferences('local_oksigeniaayvurenda_cardlook') ?: null
        );
        $stage = $album['flower']['stage'];
        $levels = $album['flower']['levels'];
        if ($look === 'flower') {
            $name = get_string('stage' . \local_oksigeniaayvurenda\core\flower::STAGES[$stage], 'local_oksigeniaayvurenda');
            $hero['young'] = true;
            $hero['heraldsex'] = (characters::of((int) $library->entityid)['herald']['sex'] ?? 'm') === 'f' ? 'f' : 'm';
            $hero['listenquestion'] = self::listen(['ui/{lang}/homequestion']);
            $hero['you'] = ['title' => self::str('homeflower', $name), 'text' => self::str('heraldstage' . $stage),
                'drawing' => cards::flower_svg($stage, $levels, $name, 72),
                'listen' => self::listen(['herald-' . $hero['heraldsex'] . '/{lang}/heraldstage' . $stage])];
        } else if ($look === 'sky' || $look === 'skysober') {
            $name = self::str('stagesky' . $stage);
            $hero['you'] = ['title' => self::str('homesky', $name), 'text' => self::str('homeskytext'),
                'drawing' => cards::sky_svg($stage, $levels, $name, 72, $look === 'skysober')];
        } else {
            $hero['you'] = ['title' => self::str('homeclassic'),
                'text' => skins::say((int) $library->entityid, 'homeclassictext'), 'drawing' => ''];
        }
        $hero['you'] += ['url' => self::url('card.php', $library), 'label' => self::str('mycard'), 'listen' => null];
        // What the memory has to tell this reader today: a book, or what they were looking for. A new reader who has
        // taken nothing yet is welcomed instead (jue-humor H40-H43).
        $remembers = memory::remembers($library, $reader);
        $loans = $DB->record_exists('local_oksigeniaayvurenda_loan', ['readerid' => $reader->id]);
        $welcome = !$loans && (int) $reader->timecreated > time() - 14 * DAYSECS ? characters::say(
            (int) $library->entityid,
            'memory',
            'memoryfirstday',
            ['reader' => format_string($reader->firstname ?: fullname($USER), true, ['escape' => false])],
            'saluda'
        ) : null;
        if ($welcome) {
            $hero['remembers'] = $welcome + ['url' => self::url('search.php', $library), 'label' => self::str('memorysearch'),
                'cover' => null, 'listen' => null];
            $remembers = null;
        }
        if ($remembers) {
            $book = $remembers['recordid'];
            $hero['remembers'] = $remembers['say'] + [
                'url' => $book ? self::url('record.php', $library, ['id' => $book])
                    : self::url('search.php', $library, ['q' => $remembers['query']]),
                'label' => self::str($book ? 'memorygo' : 'memorysearchagain'),
                'cover' => $book ? covers::url($book) : null,
                'listen' => null,
            ];
        }
        if ($member && (string) $member->groupname !== '') {
            $challenge = challenges::of((int) $library->entityid, (string) $member->groupname);
            $hero['challenge'] = $challenge ? challenges::view($challenge, (int) $library->entityid) : null;
            if ($challenge && $hero['young']) {
                $hero['challenge']['listen'] = self::listen(
                    ['ui/{lang}/challengeheading', 'ui/{lang}/challenge' . $challenge->kind]
                );
                // On the board the flower's card holds the challenge: one «Listen» reads both, one after the other.
                if (!empty($hero['you']['listen'])) {
                    $hero['you']['listen'] = self::listen(['herald-' . $hero['heraldsex'] . '/{lang}/heraldstage'
                        . $album['flower']['stage'], 'ui/{lang}/challengeheading', 'ui/{lang}/challenge' . $challenge->kind]);
                }
            }
            // The book the class chose as its reward is here: the herald tells.
            $arrived = wishes::arrived((int) $library->entityid, (string) $member->groupname);
            $hero['wishherald'] = $arrived !== null
                ? characters::say((int) $library->entityid, 'herald', 'heraldwish', ['title' => $arrived], 'celebra') : null;
            // What the class reads: a school's; a group of a library without classes (a club) is not a class.
            $hero['classreads'] = skins::no_classes((int) $library->entityid) ? [] : self::cards(catalogue::class_reads(
                $library,
                (string) $member->groupname,
                (int) $album['year']->id
            ), $library);
        }
        return $hero;
    }

    /**
     * What the older readers recommend to the young, for the home of a young reader.
     *
     * @param \stdClass $library
     * @return array|null heading, hint and items (url, title, quote, who); null when there is none.
     */
    private static function elders(\stdClass $library): ?array {
        $items = array_map(
            fn($r) => ['url' => self::url('record.php', $library, ['id' => $r['recordid']]),
            'title' => format_string($r['title'], true, ['escape' => false]), 'quote' => $r['text'], 'who' => $r['who']],
            reviews::for_little((int) $library->entityid)
        );
        return $items ? ['heading' => self::str('homeelders'), 'hint' => self::str('homeeldershint'), 'items' => $items] : null;
    }

    /**
     * The words of the home's board: whose each card is, and the first covers of what just arrived.
     *
     * @param \stdClass $library
     * @param array $recent The cards of what just arrived.
     * @return array
     */
    private static function board(\stdClass $library, array $recent): array {
        $cast = characters::of((int) $library->entityid);
        $covers = array_values(array_filter($recent, fn($card) => !empty($card['cover'])));
        return [
            'heraldtitle' => self::str('boardherald', $cast['herald']['name'] ?? ''),
            'memorytitle' => self::str('boardmemory', $cast['memory']['name'] ?? ''),
            'allnew' => self::str('boardallnew'),
            'challengedone' => self::str('boardchallengedone'),
            'covers' => array_map(
                fn($card) => ['url' => $card['url'], 'cover' => $card['cover'], 'title' => $card['title']],
                array_slice($covers, 0, 6)
            ),
            'agenda' => self::week_agenda($library),
        ];
    }

    /**
     * The moments to celebrate this week, on the home's board: the herald cheers the first; whoever runs or keeps the
     * library sees the next two below it (Enrique, 8 November: high roles see life too, not only figures).
     *
     * @param \stdClass $library
     * @param int|null $now
     * @return array|null heading, what the herald says (text, figure…), cover, url, label and more.
     */
    public static function moments(\stdClass $library, ?int $now = null): ?array {
        global $USER;
        $entityid = (int) $library->entityid;
        $staff = access::is_staff($entityid, (int) $USER->id) || access::keeps($library, (int) $USER->id);
        $moments = moments::of($library, $now ?? time(), $staff);
        if (!$moments) {
            return null;
        }
        $first = array_shift($moments);
        $said = characters::say($entityid, 'herald', $first['key'], $first['a'], 'celebra')
            ?? ['text' => $first['text'], 'figure' => null, 'portrait' => null, 'initial' => '', 'role' => 'herald'];
        $more = array_map(fn($moment) => ['text' => $moment['text'], 'url' => $moment['url']], array_slice($moments, 0, 2));
        $skin = skins::of($entityid);
        return $said + [
            'heading' => self::str('momentsheading'),
            // The paper pennants are for the young of a school; older readers and grown-ups see the moment soberly.
            'sober' => !in_array($skin, ['school', 'secondary'], true) || characters::step($entityid) === 'cielo',
            // Explicit, so that Mustache does not look for them outside.
            'cover' => $first['recordid'] ? (covers::url($first['recordid']) ?? false) : false,
            'url' => $first['url'],
            'label' => self::str($first['recordid'] ? 'memorygo' : 'momentsee'),
            'more' => $more ? ['items' => $more] : false,
        ];
    }

    /**
     * This week in the library, for the home's board: the next three activities of the agenda in seven days.
     *
     * @param \stdClass $library
     * @return array|null title, items (when, title), url and label; null with nothing on.
     */
    private static function week_agenda(\stdClass $library): ?array {
        $now = time();
        $items = [];
        foreach (array_slice(agenda::between((int) $library->entityid, $now, $now + WEEKSECS), 0, 3) as $activity) {
            $items[] = ['when' => agenda::when($activity, $library),
                'title' => format_string($activity->title, true, ['escape' => false])];
        }
        return $items ? ['title' => self::str('agendaweek'), 'items' => $items,
            'url' => self::url('agenda.php', $library), 'label' => self::str('agendaall')] : null;
    }

    /**
     * The herald announcing new books, with its voice for a young reader.
     *
     * @param \stdClass $library
     * @param array $hero The top of the home, which knows the reader.
     * @return array|null
     */
    private static function herald_new(\stdClass $library, array $hero): ?array {
        // Whole, waving: on the home's board it is the one figure of the left column.
        if (
            self::swap_day((int) $library->entityid) && ($swap = characters::say(
                (int) $library->entityid,
                'memory',
                'memorynewswap',
                [],
                'senala'
            ))
        ) {
            return $swap;
        }
        $said = characters::say((int) $library->entityid, 'herald', 'heraldnew', [], 'saluda');
        if ($said && $hero['young']) {
            $said['listen'] = self::listen(['herald-' . $hero['heraldsex'] . '/{lang}/heraldnew']);
        }
        return $said;
    }

    /**
     * The book of the week, told by the herald: the one most readers took from this library in the last seven days,
     * when two or more did (a count, never who).
     *
     * @param \stdClass $library
     * @param int|null $now
     * @return array|null What the herald says, with the book's url, cover and label.
     */
    public static function week_book(\stdClass $library, ?int $now = null): ?array {
        global $DB;
        if (bank::is($library)) {
            return null;
        }
        // The one a librarian picked this week, with their line and their name; else the one most taken.
        $pick = weekpick::of($library, $now ?? time());
        if ($pick) {
            $said = characters::say(
                (int) $library->entityid,
                'herald',
                $pick['phrase'] !== '' ? 'heraldpick' : 'heraldpicknophrase',
                ['title' => $pick['title'], 'phrase' => $pick['phrase'], 'name' => $pick['name'] !== '' ? $pick['name']
                : format_string($library->name, true, ['escape' => false])],
                'celebra'
            );
            return $said ? $said + ['url' => self::url('record.php', $library, ['id' => $pick['recordid']]),
                'cover' => covers::url($pick['recordid']), 'label' => self::str('memorygo')] : null;
        }
        $rows = $DB->get_records_sql(
            "SELECT i.recordid, COUNT(DISTINCT l.readerid) AS readers
               FROM {local_oksigeniaayvurenda_loan} l
               JOIN {local_oksigeniaayvurenda_item} i ON i.id = l.itemid
              WHERE l.libraryid = :libraryid AND l.timeloaned >= :since
           GROUP BY i.recordid
             HAVING COUNT(DISTINCT l.readerid) >= :few
           ORDER BY COUNT(DISTINCT l.readerid) DESC, i.recordid",
            ['libraryid' => $library->id, 'since' => ($now ?? time()) - WEEKSECS, 'few' => anonymity::FEW],
            0,
            1
        );
        $row = $rows ? reset($rows) : null;
        if (!$row) {
            return null;
        }
        $title = (string) $DB->get_field('local_oksigeniaayvurenda_record', 'title', ['id' => $row->recordid]);
        // How many, only from SAY on: «several» says it without the number that tells a small group who.
        $said = characters::say(
            (int) $library->entityid,
            'herald',
            (int) $row->readers < anonymity::SAY ? 'heraldweekseveral' : 'heraldweek',
            ['title' => $title, 'count' => (int) $row->readers],
            'celebra'
        );
        return $said ? $said + ['url' => self::url('record.php', $library, ['id' => $row->recordid]),
            'cover' => covers::url((int) $row->recordid), 'label' => self::str('memorygo')] : null;
    }

    /**
     * What the reader looking has at home from this centre: each book with its cover and the day it comes back, late in
     * red, and «Renew» where the library lets them do it themselves. Not the textbooks of a bank, lent for the year.
     *
     * @param \stdClass $library
     * @param int|null $now
     * @return array|null heading and books; null for whoever is not a reader here or has nothing at home.
     */
    public static function at_home(\stdClass $library, ?int $now = null): ?array {
        global $DB, $USER, $PAGE;
        $now = $now ?? time();
        if (!isloggedin() || isguestuser()) {
            return null;
        }
        $reader = $DB->get_record('local_oksigeniaayvurenda_reader', ['entityid' => $library->entityid, 'userid' => $USER->id,
            'status' => 'active'], 'id', IGNORE_MULTIPLE);
        if (!$reader) {
            return null;
        }
        $rows = $DB->get_records_sql(
            "SELECT l.id, l.timedue, l.libraryid, r.id AS recordid, r.title
               FROM {local_oksigeniaayvurenda_loan} l
               JOIN {local_oksigeniaayvurenda_item} i ON i.id = l.itemid
               JOIN {local_oksigeniaayvurenda_record} r ON r.id = i.recordid
              WHERE l.readerid = :readerid AND l.timereturned IS NULL
           ORDER BY l.timedue, l.id",
            ['readerid' => $reader->id]
        );
        $covers = covers::urls(array_map(fn($row) => (int) $row->recordid, $rows));
        $back = $PAGE->has_set_url() ? $PAGE->url->out(false) : self::url('index.php', $library);
        $books = [];
        foreach ($rows as $row) {
            $where = (int) $row->libraryid === (int) $library->id ? $library
                : $DB->get_record('local_oksigeniaayvurenda_library', ['id' => $row->libraryid]);
            if (!$where || bank::is($where)) {
                continue;
            }
            $until = renewals::until($where, (int) $reader->id, (int) $row->id, (int) $USER->id, $now);
            $late = (int) $row->timedue < zones::today((int) $library->entityid, $now);
            $day = zones::date((int) $row->timedue, 'strftimedateshort', $library);
            $books[] = [
                'title' => format_string($row->title, true, ['escape' => false]),
                'url' => self::url('record.php', $where, ['id' => $row->recordid]),
                'hascover' => isset($covers[$row->recordid]),
                'coverurl' => $covers[$row->recordid] ?? '',
                'colour' => (int) $row->recordid % 6,
                'when' => self::str($late ? 'athomelate' : 'athomedue', $day),
                'late' => $late,
                'renew' => $until ? ['loan' => (int) $row->id, 'label' => self::str(
                    'athomerenew',
                    zones::date($until, 'strftimedateshort', $library)
                )] : null,
            ];
        }
        if (!$books) {
            return null;
        }
        return ['heading' => self::str('athomeheading'), 'books' => $books,
            'action' => (new \moodle_url('/local/oksigeniaayvurenda/renew.php'))->out(false), 'sesskey' => sesskey(),
            'returnurl' => $back];
    }

    /**
     * The link to the person's own card, when they are a reader of this entity.
     *
     * @param \stdClass $library
     * @return array|null
     */
    private static function mycard(\stdClass $library): ?array {
        global $DB, $USER;
        if (!isloggedin() || isguestuser()) {
            return null;
        }
        if (
            !$DB->record_exists('local_oksigeniaayvurenda_reader', ['entityid' => $library->entityid, 'userid' => $USER->id,
                'status' => 'active'])
        ) {
            return null;
        }
        return ['url' => self::url('card.php', $library), 'label' => self::str('mycard')];
    }

    /**
     * Cards with their link and the words of their state.
     *
     * @param array $cards
     * @param \stdClass $library
     * @return array
     */
    public static function cards(array $cards, \stdClass $library): array {
        foreach ($cards as &$card) {
            $card['url'] = self::url('record.php', $library, ['id' => $card['id']]);
            $card['statelabel'] = self::str('state' . $card['state']);
            $card['stateclass'] = $card['state'];
            if ($card['state'] !== 'free') {
                $card['location'] = null;
            }
        }
        return $cards;
    }

    /**
     * What a character says on a search: with nothing found, a line of the bag of the reader's age (jue-humor); when
     * the search is the name of a character, that character answers; «nothing» found nothing; «book» found too many.
     *
     * @param \stdClass $library
     * @param string $query
     * @param int $total
     * @param bool $near Whether there are words close to it to suggest.
     * @return array|null What is said, as characters::say gives it.
     */
    private static function nothing_found(\stdClass $library, string $query, int $total, bool $near): ?array {
        global $USER;
        $entityid = (int) $library->entityid;
        $said = \local_oksigeniaayvurenda\core\text::normalise($query);
        $grown = characters::band($entityid) === '';
        // The voices of the youngest, who may not read yet (the rest is read).
        $voice = function (?array $said, string $who, string $clip) use ($entityid, $grown): ?array {
            if ($said && !$grown) {
                $folder = $who === 'memory' ? 'memoria'
                    : ((characters::of($entityid)['herald']['sex'] ?? 'm') === 'f' ? 'herald-f' : 'herald-m');
                $said['listen'] = self::listen(['juegos/' . $folder . '/{lang}/' . $clip]);
            }
            return $said;
        };
        foreach (characters::of($entityid) as $role => $character) {
            if ($character['enabled'] && $said === \local_oksigeniaayvurenda\core\text::normalise($character['name'])) {
                return $voice(
                    characters::say($entityid, $role, $role === 'herald' ? 'heraldself' : 'memoryself', [], 'celebra'),
                    $role,
                    'self'
                );
            }
        }
        if ($total > 500 && in_array($said, ['libro', 'libros', 'book', 'books'], true)) {
            return characters::say($entityid, 'memory', 'memorytoomany', ['total' => self::number($total)], 'piensa');
        }
        if ($total > 0) {
            return null;
        }
        if ($grown && in_array($said, ['nada', 'nothing'], true)) {
            return characters::say($entityid, 'memory', 'memorynothingnada', ['query' => $query], 'piensa');
        }
        if (!$near && !$grown && self::swap_day($entityid)) {
            // The day they swap jobs, the herald does the memory's (jue-humor H46).
            $swap = characters::say($entityid, 'herald', 'heraldnothingswap', ['query' => $query], 'piensa');
            if ($swap) {
                return $swap;
            }
        }
        $key = $near ? 'memorynear' : characters::bag($entityid, 'memorynothing', (string) $USER->id);
        $said = characters::say($entityid, 'memory', $key, ['query' => $query], 'piensa');
        return $key === 'memorynothinginf1' ? $voice($said, 'memory', 'nothinginf1') : $said;
    }

    /**
     * The day the two characters swap jobs: the first of April, and in Spanish the 28th of December too, in the
     * centre's own day. Nothing false is said: only who says it changes.
     *
     * @param int $entityid
     * @return bool
     */
    private static function swap_day(int $entityid): bool {
        $day = zones::date(time(), '%m-%d', $entityid);
        return $day === '04-01' || ($day === '12-28' && substr(current_language(), 0, 2) === 'es');
    }

    /**
     * A line for a very old book: more than fifty years for the young and the sky, a century for grown-ups (jue-humor
     * H34-H36). Nothing for a year that is not a year («c. 1920», «[s.a.]», «19--»).
     *
     * @param \stdClass $library
     * @param string $pubyear
     * @return string
     */
    private static function old_line(\stdClass $library, string $pubyear): string {
        $entityid = (int) $library->entityid;
        if (!preg_match('/^\s*\[?(\d{4})\]?\.?\s*$/', $pubyear, $m)) {
            return '';
        }
        $year = (int) $m[1];
        $age = (int) date('Y') - $year;
        $young = characters::band($entityid) !== '';
        $sky = characters::step($entityid) === 'cielo' && in_array(skins::of($entityid), ['school', 'secondary'], true);
        if ($year < 1000 || $age > 1000 || $age <= ($young || $sky ? 50 : 100)) {
            return '';
        }
        return skins::say($entityid, $young ? 'recordoldpri' : 'recordold', $year);
    }

    /**
     * The name of a language, in the reader's language.
     *
     * @param string|null $code MARC code.
     * @return string|null
     */
    private static function language(?string $code): ?string {
        if ($code === null || $code === '') {
            return null;
        }
        $two = self::LANGUAGES[$code] ?? null;
        $names = get_string_manager()->get_list_of_languages();
        // Some languages are listed with a second name after a semicolon («Español; Castellano»): the first is enough.
        return $two !== null && isset($names[$two]) ? trim(explode(';', $names[$two])[0]) : $code;
    }

    /**
     * A link inside the library.
     *
     * @param string $script
     * @param \stdClass $library
     * @param array $params
     * @return string
     */
    private static function url(string $script, \stdClass $library, array $params = []): string {
        return (new \moodle_url('/local/oksigeniaayvurenda/' . $script, ['library' => $library->slug] + $params))->out(false);
    }

    /**
     * A string of the plugin.
     *
     * @param string $key
     * @param mixed $a
     * @return string
     */
    private static function str(string $key, $a = null): string {
        return get_string($key, 'local_oksigeniaayvurenda', $a);
    }

    /**
     * Something said to a reader, in the tone of the library's skin.
     *
     * @param \stdClass $library
     * @param string $key
     * @param mixed $a
     * @return string
     */
    private static function said(\stdClass $library, string $key, $a = null): string {
        return get_string(skins::words((int) $library->entityid, $key), 'local_oksigeniaayvurenda', $a);
    }

    /**
     * The copies of a record, with each library that has more than a dozen in one line: how many are free of how many.
     *
     * @param array[] $copies As the record gives them.
     * @return array[]
     */
    private static function summed_copies(array $copies): array {
        $by = [];
        foreach ($copies as $copy) {
            $by[(string) $copy['where']][] = $copy;
        }
        $out = [];
        foreach ($by as $where => $group) {
            if (count($group) <= 12) {
                array_push($out, ...$group);
                continue;
            }
            $free = count(array_filter($group, fn($c) => $c['state'] === 'free'));
            $out[] = ['id' => 0, 'state' => $free ? 'free' : 'out', 'here' => false, 'where' => $where,
                'location' => '', 'fix' => false, 'statelabel' => self::str('copiesfreeof', (object) ['free' => $free,
                'total' => count($group)])];
        }
        return $out;
    }
}
