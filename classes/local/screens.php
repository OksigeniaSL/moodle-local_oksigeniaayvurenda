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
        foreach (catalogue::corners($library) as $corner) {
            $corners[] = $corner + [
                'url' => self::url('search.php', $library, ['corner' => $corner['code']]),
                'countlabel' => self::str($corner['count'] === 1 ? 'onebook' : 'books', $corner['count']),
                'art' => self::art('rincon-' . preg_replace('/[^a-z0-9]/', '', \core_text::strtolower((string) $corner['code']))),
            ];
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
            'hero' => $hero,
            'classblock' => $hero['classreads'] ? ['cards' => $hero['classreads']] : null,
            'eldersblock' => $hero['young'] ? self::elders($library) : null,
            'classheading' => self::str('homeclass'),
            'shelfprev' => self::str('shelfprev'),
            'shelfnext' => self::str('shelfnext'),
            'classhint' => self::str('homeclasshint'),
            'heading' => self::str('homeheading'),
            'cornersheading' => self::str('corners'),
            'cornercols' => $corners ? (int) ceil(count($corners) / ceil(count($corners) / 6)) : 1,
            'cornersedit' => $manage ? ['url' => self::url('corners.php', $library), 'label' => self::str('cornersedit')] : null,
            'corners' => $corners,
            'hascorners' => (bool) $corners,
            'recentheading' => self::str('recent'),
            'cards' => $recent,
            'hasrecent' => (bool) $recent,
            'herald' => $recent ? self::herald_new($library, $hero) : null,
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
     * @return array
     */
    public static function results(
        \stdClass $library,
        string $query,
        string $corner,
        int $page,
        bool $onlyfree = false,
        int $subject = 0
    ): array {
        $facets = null;
        if ($corner !== '' && $query === '') {
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
            $narrow = array_filter(['corner' => $corner, 'subject' => $subject]);
            $here = ['q' => $query] + $narrow;
            $a = (object) ['count' => $found['total'], 'query' => $query];
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
        $more = ($page + 1) * catalogue::PERPAGE < $found['total'];
        return [
            'heading' => $heading,
            'cards' => self::cards($found['cards'], $library),
            'hascards' => (bool) $found['cards'],
            'suggestions' => $suggestions,
            'hassuggestions' => (bool) $suggestions,
            'didyoumean' => self::str('didyoumean'),
            // The memory speaks when it found nothing; otherwise it keeps quiet.
            'memory' => $corner === '' && $found['total'] === 0 && $query !== ''
                ? characters::say((int) $library->entityid, 'memory', 'memorynothing', ['query' => $query]) : null,
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
        return ['corners' => $corners, 'hascorners' => (bool) $corners, 'subjects' => $subjects,
            'hassubjects' => (bool) $subjects, 'cornerslabel' => self::str('facetcorners'),
            'subjectslabel' => self::str('facetsubjects')];
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
        $card = self::cards([$found['card']], $library)[0];
        // To cite the book in a reference manager: for whoever is past the flower, and never for material.
        global $DB, $USER;
        $reader = isloggedin() && !isguestuser() ? $DB->get_record('local_oksigeniaayvurenda_reader', ['entityid' =>
            $library->entityid, 'userid' => $USER->id, 'status' => 'active'], '*', IGNORE_MULTIPLE) : null;
        $young = $reader && cards::look_of($reader) === 'flower' && !access::can('local/oksigeniaayvurenda:desk', $library);
        $cite = !$young && $record->doctype !== 'device' ? ['citeurl' => self::url('cite.php', $library, ['id' => $record->id]),
            'citelabel' => self::str('citeris')] : null;
        return [
            'card' => $card,
            'cite' => $cite,
            'title' => $record->title,
            'subtitle' => $record->subtitle,
            'authors' => $authors,
            'facts' => $facts,
            'hasfacts' => (bool) $facts,
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
        if ($reader) {
            $mine = holds::open((int) $library->id, $recordid, (int) $reader->id);
            if ($mine) {
                $box['holdid'] = (int) $mine->id;
                if ($mine->status === 'ready') {
                    $box['mine'] = self::str('holdmineready', userdate(
                        (int) $mine->timeexpires,
                        get_string('strftimedateshort', 'langconfig')
                    ));
                } else {
                    $box['mine'] = self::str('holdminewaiting');
                    $ahead = holds::ahead($mine);
                    $box['ahead'] = $ahead ? self::str('holdmineahead', $ahead) : '';
                }
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
        foreach (['holdask', 'holdaskhint', 'holdcancelmine', 'holdfor', 'holdforhint', 'holdforgo', 'holdslink'] as $key) {
            $box[$key] = self::str($key);
        }
        return $box;
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
        $voice = $known && plans::allows((int) $library->entityid, 'voices') ? ['url' => self::url(
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
     * The choice of a library, when there are several.
     *
     * @return array
     */
    public static function choose(): array {
        global $USER;
        $all = catalogue::visible();
        $featured = libraries::featured($all, isloggedin() && !isguestuser() ? (int) $USER->id : 0);
        $card = function (\stdClass $library) {
            $look = libraries::look($library);
            return ['name' => format_string($library->name), 'url' => self::url('index.php', $library),
                'kindlabel' => self::str('librarykind' . $library->kind), 'motto' => format_string($look['motto']),
                'colour' => $look['colour'], 'image' => $look['image']];
        };
        $libraries = [];
        foreach ($all as $library) {
            if (!$featured || (int) $library->id !== (int) $featured->id) {
                $libraries[] = $card($library);
            }
        }
        $manage = has_capability('local/oksigeniaayvurenda:manage', \context_system::instance());
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
            'manage' => $manage ? ['url' => (new \moodle_url('/local/oksigeniaayvurenda/libraries.php'))->out(false),
                'label' => self::str($libraries ? 'librariesnav' : 'librariesstart')] : null,
        ];
    }

    /**
     * A picture of the art package, if the site has it: the plugin carries none of its own (it goes apart, with its
     * own licence), and without it every screen keeps its drawn look.
     *
     * @param string $name
     * @param string $type webp, svg for the drawings used as masks, or glb for the models of the island in 3D.
     * @return string|null Its address.
     */
    public static function art(string $name, string $type = 'webp'): ?string {
        $dir = \core_component::get_component_directory('local_oksigeniaayvurendaarte');
        if (
            !$dir || !preg_match('#^[a-z0-9-]+(/[a-z0-9-]+)?$#', $name) || !in_array($type, ['webp', 'svg', 'glb'], true)
                || !is_readable($dir . '/pix/' . $name . '.' . $type)
        ) {
            return null;
        }
        return (new \moodle_url('/local/oksigeniaayvurendaarte/pix/' . $name . '.' . $type))->out(false);
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
        return [
            'show' => $list || $canwrite,
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
                'speakopen' => self::str('reviewspeakopen'),
                'speakrecord' => self::str('reviewspeakrecord'),
                'speakstop' => self::str('reviewspeakstop'),
                'speakagain' => self::str('reviewspeakagain'),
                'speakhint' => self::str('reviewspeakhint'),
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
        return (new \moodle_url('/local/oksigeniaayvurendaarte/voz/' . $path . '.mp3'))->out(false);
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
            $ideas[] = ['label' => $idea['last'] ? self::str('homelikelast') : $idea['label'],
                'url' => self::url('search.php', $library, ['subject' => $idea['id'], 'q' => $idea['label']])];
        }
        $hero = [
            'greeting' => $reader ? self::str('homehello', format_string($USER->firstname)) : format_string($library->name),
            'question' => self::str('homequestion'),
            'action' => self::url('search.php', $library),
            'slug' => $library->slug,
            'placeholder' => $asks ? self::str('homeaskmemory', $memory['name']) : self::str('searchplaceholder'),
            'memoryinitial' => $asks ? $memory['initial'] : '',
            'memoryportrait' => $asks ? $memory['portrait'] : null,
            'searchlabel' => self::str('searchbutton'),
            'ideas' => $ideas,
            'hasideas' => (bool) $ideas,
            'art' => self::art('portada'),
            'emblem' => (new \moodle_url('/local/oksigeniaayvurenda/app/emblema.svg'))->out(false),
            'you' => null,
            'challenge' => null,
            'classreads' => [],
            // The young (the card with the flower) can hear what they may not read yet.
            'young' => false,
            'heraldsex' => 'm',
            'listenquestion' => null,
            'wishherald' => null,
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
        $look = \local_oksigeniaayvurenda\core\card_look::resolve(
            \local_oksigeniaayvurenda\core\card_look::guess($reader->readertype, $stagename, $group),
            $settings['cardlooks'][$stagename] ?? null,
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
            $hero['you'] = ['title' => self::str('homeclassic'), 'text' => self::str('homeclassictext'), 'drawing' => ''];
        }
        $hero['you'] += ['url' => self::url('card.php', $library), 'label' => self::str('mycard'), 'listen' => null];
        if ($member && (string) $member->groupname !== '') {
            $challenge = challenges::of((int) $library->entityid, (string) $member->groupname);
            $hero['challenge'] = $challenge ? challenges::view($challenge, (int) $library->entityid) : null;
            if ($challenge && $hero['young']) {
                $hero['challenge']['listen'] = self::listen(
                    ['ui/{lang}/challengeheading', 'ui/{lang}/challenge' . $challenge->kind]
                );
            }
            // The book the class chose as its reward is here: the herald tells.
            $arrived = wishes::arrived((int) $library->entityid, (string) $member->groupname);
            $hero['wishherald'] = $arrived !== null
                ? characters::say((int) $library->entityid, 'herald', 'heraldwish', ['title' => $arrived]) : null;
            $hero['classreads'] = self::cards(catalogue::class_reads(
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
            'title' => format_string($r['title']), 'quote' => $r['text'], 'who' => $r['who']],
            reviews::for_little((int) $library->entityid)
        );
        return $items ? ['heading' => self::str('homeelders'), 'hint' => self::str('homeeldershint'), 'items' => $items] : null;
    }

    /**
     * The herald announcing new books, with its voice for a young reader.
     *
     * @param \stdClass $library
     * @param array $hero The top of the home, which knows the reader.
     * @return array|null
     */
    private static function herald_new(\stdClass $library, array $hero): ?array {
        $said = characters::say((int) $library->entityid, 'herald', 'heraldnew');
        if ($said && $hero['young']) {
            $said['listen'] = self::listen(['herald-' . $hero['heraldsex'] . '/{lang}/heraldnew']);
        }
        return $said;
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
}
