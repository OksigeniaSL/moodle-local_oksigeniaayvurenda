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

/**
 * What a notice looks like by e-mail: the library's picture and colour, what it is about, the book as a slip with its date, one
 * large button and who signs. A mail program opens it without a session, so its pictures are the art package's, or come through
 * mailimage.php with a seal only the e-mail knows.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mail {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var string The plugin. */
    private const COMPONENT = 'local_oksigeniaayvurenda';

    /** @var array By the number a library's card keeps: band, ink on it, letters on paper, button, ink on it. */
    private const ACCENTS = [
        ['#17574c', '#ffffff', '#17574c', '#17574c', '#ffffff'],
        ['#e2654c', '#141c1b', '#a8402a', '#a8402a', '#ffffff'],
        ['#f0b43c', '#1e2b2a', '#17574c', '#17574c', '#ffffff'],
        ['#2c7a6a', '#ffffff', '#1f6658', '#1f6658', '#ffffff'],
        ['#0f3a33', '#f0b43c', '#0f3a33', '#0f3a33', '#ffffff'],
        ['#f3e6cc', '#17574c', '#17574c', '#17574c', '#ffffff'],
    ];

    /** @var array The cover of a book without a picture: its paper and its ink. */
    private const COVERS = [
        ['#17574c', '#fbf6ec'], ['#e2654c', '#141c1b'], ['#f0b43c', '#1e2b2a'], ['#2c7a6a', '#ffffff'], ['#0f3a33', '#f0b43c'],
        ['#efe2c8', '#17574c'],
    ];

    /** @var string[] The picture at the top of each skin, in the art package. */
    private const PICTURES = ['school' => 'cabecera-2', 'secondary' => 'instituto', 'university' => 'universidad',
        'public' => 'publica', 'company' => 'empresa', 'mobile' => 'publica'];

    /** @var string The picture at the top for a young reader of a school. */
    private const PICTURE_YOUNG = 'cabecera-5';

    /** @var string[] The skins where the herald signs for anyone; in the rest, for a young reader or if the centre asks. */
    private const HERALD_SIGNS = ['school', 'secondary'];

    /** @var string[] Where a picture of a centre may be. */
    public const AREAS = ['cover', 'libraryimage', 'portrait'];

    /** @var string[] What every mail program shows: WebP is not among them. */
    private const TYPES = ['jpg', 'png', 'gif'];

    /**
     * A notice ready to send.
     *
     * @param array $content library (its row) or entityid, preheader, hello, heading, lead (paragraphs), book (from book()),
     *     stamp and place (label, value), books (rows from book() with line, urgent), shelf (from book()), more, people (name,
     *     line), tasks (text, url), button (url, text), after (paragraphs), quiet (hint, url, text), sign (from sign()), why,
     *     prefs,
     *     young (for a reader with the flower), userid (who it is for, so its button can open their app).
     * @return string The whole page.
     */
    public static function html(array $content): string {
        global $OUTPUT;
        $library = $content['library'] ?? null;
        $entityid = (int) ($content['entityid'] ?? ($library->entityid ?? 0));
        $accent = self::accent($library);
        $picture = self::picture($library, $entityid, !empty($content['young']));
        $lead = array_values((array) ($content['lead'] ?? []));
        $books = array_values((array) ($content['books'] ?? []));
        $shelf = array_values((array) ($content['shelf'] ?? []));
        $people = array_values((array) ($content['people'] ?? []));
        $tasks = array_values((array) ($content['tasks'] ?? []));
        $look = self::look($library);
        $name = format_string($library ? $library->name : get_site()->fullname, true, ['escape' => false]);
        $sign = $content['sign'] ?? null;
        $context = [
            'lang' => str_replace('_', '-', current_language()),
            'preheader' => (string) ($content['preheader'] ?? ($lead[0] ?? $content['heading'] ?? '')),
            'artpicture' => $picture['art'] ?? '',
            'ownpicture' => $picture['own'] ?? '',
            'libraryname' => $name,
            'motto' => trim((string) ($look['motto'] ?? '')),
            'accentbg' => $accent[0],
            'accentink' => $accent[1],
            'accenttext' => $accent[2],
            'buttonbg' => $accent[3],
            'buttonink' => $accent[4],
            'bandcorners' => empty($picture) ? '22px 22px 0 0' : '0',
            'hello' => (string) ($content['hello'] ?? ''),
            'heading' => (string) ($content['heading'] ?? ''),
            'lead' => $lead,
            'book' => $content['book'] ?? null,
            'stamp' => $content['stamp'] ?? null,
            'place' => $content['place'] ?? null,
            'hasbooks' => (bool) $books,
            'books' => $books,
            'hasshelf' => (bool) $shelf,
            'shelfrows' => self::rows($shelf),
            'more' => (string) ($content['more'] ?? ''),
            'haspeople' => (bool) $people,
            'people' => $people,
            'hastasks' => (bool) $tasks,
            'tasks' => $tasks,
            'button' => !empty($content['button']) ? ['url' => self::leading((string) $content['button']['url'], $content)]
                + $content['button'] : null,
            'after' => array_values((array) ($content['after'] ?? [])),
            'quiet' => $content['quiet'] ?? null,
            'sign' => $sign ? true : false,
            'signpicture' => !empty($sign['portrait']) || !empty($sign['initial']),
            'signportrait' => $sign['portrait'] ?? '',
            'signinitial' => $sign['initial'] ?? '',
            'signtext' => $sign['text'] ?? '',
            'linkurl' => self::link($content, $library),
            'why' => (string) ($content['why'] ?? ''),
            'prefsurl' => !empty($content['prefs'])
                ? (new \moodle_url('/message/notificationpreferences.php'))->out(false) : '',
            'prefstext' => get_string('mailprefs', self::COMPONENT),
            'madewith' => $entityid && plans::allows($entityid, 'madewith') ? get_string('planmadewithfoot', self::COMPONENT) : '',
        ];
        return $OUTPUT->render_from_template(self::COMPONENT . '/email_notice', $context);
    }

    /**
     * A notice that has only words: a heading and its paragraphs.
     *
     * @param string $subject
     * @param string $body
     * @return array For html().
     */
    public static function plain(string $subject, string $body): array {
        return ['heading' => $subject, 'lead' => array_values(array_filter(array_map('trim', preg_split('/\n\s*\n/', $body))))];
    }

    /**
     * A book for a notice, with its cover if it has one; if not, a cover of its colour with its title.
     *
     * @param int $recordid
     * @param string $title
     * @param string $authors
     * @return array title, authors, cover, coverbg, coverink.
     */
    public static function book(int $recordid, string $title, string $authors = ''): array {
        $colours = self::COVERS[$recordid % count(self::COVERS)];
        return [
            'title' => $title,
            'authors' => $authors,
            'cover' => self::file_of('cover', $recordid, 'cover') ? self::image('cover', $recordid, 'cover') : '',
            'coverbg' => $colours[0],
            'coverink' => $colours[1],
            'line' => '',
            'urgent' => false,
        ];
    }

    /**
     * Who signs a notice: the herald, with their portrait of the centre or the art package's; where the herald does not speak to
     * this reader, the library, by its name and without a picture.
     *
     * @param int $entityid
     * @param \stdClass|null $reader Who it is for: a young reader sees the flower's herald, and the rest the sky's.
     * @param bool $always Whether to sign even if the centre does not ask the herald to sign what it sends.
     * @param \stdClass|null $library The library that signs when the herald does not.
     * @return array|null portrait, initial, text.
     */
    public static function sign(
        int $entityid,
        ?\stdClass $reader = null,
        bool $always = false,
        ?\stdClass $library = null
    ): ?array {
        $herald = characters::of($entityid)['herald'];
        if (!$always && !$herald['signs']) {
            return null;
        }
        $speaks = $herald['enabled']
            && ($herald['signs'] || self::young($reader) || in_array(skins::of($entityid), self::HERALD_SIGNS, true));
        if (!$speaks) {
            return !$library ? null : ['portrait' => '', 'initial' => '', 'text' => get_string(
                'mailsignlibrary',
                self::COMPONENT,
                format_string($library->name, true, ['escape' => false])
            )];
        }
        $young = $reader ? self::young($reader) : skins::lively($entityid);
        $own = self::file_of('portrait', $entityid, 'herald');
        $portrait = $own ? self::image('portrait', $entityid, 'herald')
            : ($herald['own'] ? null : screens::art('correo/pregonero' . ($young ? '' : '-cielo'), 'png'));
        $words = characters::say($entityid, 'herald', 'heraldsign');
        return ['portrait' => (string) $portrait, 'initial' => $herald['initial'], 'text' => $words ? $words['text'] : ''];
    }

    /**
     * Whether a notice is for a reader with the flower.
     *
     * @param \stdClass|null $reader
     * @return bool
     */
    public static function young(?\stdClass $reader): bool {
        return $reader && cards::look_of($reader) === 'flower';
    }

    /**
     * The address of a picture for a mail program, which has no session: sealed, so that only the e-mail knows it.
     *
     * @param string $area cover, libraryimage or portrait.
     * @param int $itemid The record, the library or the centre.
     * @param string $name Its name, without the ending.
     * @return string
     */
    public static function image(string $area, int $itemid, string $name): string {
        $file = self::file_of($area, $itemid, $name);
        return (new \moodle_url('/local/oksigeniaayvurenda/mailimage.php', [
            'a' => $area,
            'i' => $itemid,
            'n' => $name,
            's' => self::seal($area, $itemid, $name),
            't' => $file ? $file->get_timemodified() : 0,
        ]))->out(false);
    }

    /**
     * What makes the address of a picture its own.
     *
     * @param string $area
     * @param int $itemid
     * @param string $name
     * @return string
     */
    public static function seal(string $area, int $itemid, string $name): string {
        $secret = (string) get_config(self::COMPONENT, 'mailsecret');
        if ($secret === '') {
            $secret = random_string(64);
            set_config('mailsecret', $secret, self::COMPONENT);
        }
        return substr(hash_hmac('sha256', $area . '/' . $itemid . '/' . $name, $secret), 0, 32);
    }

    /**
     * The file of a picture a mail program can show: never WebP, which some do not.
     *
     * @param string $area
     * @param int $itemid
     * @param string $name
     * @return \stored_file|null
     */
    public static function file_of(string $area, int $itemid, string $name): ?\stored_file {
        if (!in_array($area, self::AREAS, true)) {
            return null;
        }
        $fs = get_file_storage();
        $context = \context_system::instance();
        foreach (self::TYPES as $type) {
            $file = $fs->get_file($context->id, self::COMPONENT, $area, $itemid, '/', $name . '.' . $type);
            if ($file) {
                return $file;
            }
        }
        return null;
    }

    /**
     * The main library of a centre, for a notice that is about no library in particular.
     *
     * @param int $entityid
     * @return \stdClass|null
     */
    public static function main_library(int $entityid): ?\stdClass {
        global $DB;
        $rows = $DB->get_records_select(
            self::T . 'library',
            'entityid = :entityid AND kind <> :kind',
            ['entityid' => $entityid, 'kind' => bank::KIND],
            "kind = 'central' DESC, id",
            '*',
            0,
            1
        );
        return $rows ? reset($rows) : null;
    }

    /**
     * Who a notice comes from: the library, on a site that is only a library; else, the site's no-reply.
     *
     * @param string $name
     * @return \stdClass
     */
    public static function from(string $name = ''): \stdClass {
        // A copy: the one Moodle hands out is the same for the whole request, and the next message is someone else's.
        $from = clone \core_user::get_noreply_user();
        if ($name !== '' && onlylibrary::on()) {
            $from->firstname = $name;
            $from->lastname = '';
        }
        return $from;
    }

    /**
     * What a library chose for its card.
     *
     * @param \stdClass|null $library
     * @return array
     */
    private static function look(?\stdClass $library): array {
        return $library ? (array) ((json_decode((string) ($library->settings ?? ''), true) ?: [])['look'] ?? []) : [];
    }

    /**
     * The colours of a library, if it chose them: the number a card shows when nobody chose is not the library's, and the house's
     * green stands in.
     *
     * @param \stdClass|null $library
     * @return string[]
     */
    private static function accent(?\stdClass $library): array {
        $look = self::look($library);
        return self::ACCENTS[isset($look['colour']) ? max(0, min(count(self::ACCENTS) - 1, (int) $look['colour'])) : 0];
    }

    /**
     * The picture at the top: the library's own, or the header it chose, or that of its centre's skin.
     *
     * @param \stdClass|null $library
     * @param int $entityid
     * @param bool $young
     * @return array own (a picture of any shape) or art (cut to fit), or nothing.
     */
    private static function picture(?\stdClass $library, int $entityid, bool $young): array {
        if ($library) {
            if (self::file_of('libraryimage', (int) $library->id, 'image')) {
                return ['own' => self::image('libraryimage', (int) $library->id, 'image')];
            }
            $scene = (string) (self::look($library)['scene'] ?? '');
            $art = $scene !== '' ? screens::art('correo/' . $scene, 'jpg') : null;
            if ($art) {
                return ['art' => $art];
            }
        }
        $skin = skins::of($entityid);
        $art = screens::art('correo/' . ($skin === 'school' && $young ? self::PICTURE_YOUNG
            : (self::PICTURES[$skin] ?? self::PICTURES['school'])), 'jpg');
        return $art ? ['art' => $art] : [];
    }

    /**
     * Where the pictures of a notice lead: where its button does, or to the library.
     *
     * @param array $content
     * @param \stdClass|null $library
     * @return string
     */
    private static function link(array $content, ?\stdClass $library): string {
        global $CFG;
        if (!empty($content['button']['url'])) {
            return self::leading((string) $content['button']['url'], $content);
        }
        return !empty($library->slug)
            ? (new \moodle_url('/local/oksigeniaayvurenda/index.php', ['library' => $library->slug]))->out(false) : $CFG->wwwroot;
    }

    /**
     * Where the button of a notice leads: the page in the language of the notice and, for whoever has Aty on their phone and a
     * page the app knows how to open, by way of the app. Aty is optional, and may be an older one.
     *
     * @param string $url
     * @param array $content
     * @return string
     */
    private static function leading(string $url, array $content): string {
        $url = self::in_language($url);
        $api = '\\local_oksigeniaaty\\api';
        if (
            empty($content['userid']) || !preg_match('#/local/oksigeniaayvurenda/(record|index)\.php#', $url)
                || !class_exists($api) || !method_exists($api, 'app_link')
        ) {
            return $url;
        }
        return $api::app_link((int) $content['userid'], $url);
    }

    /**
     * An address of the site in the language of the notice: whoever is not signed in lands on an entry that speaks like the
     * e-mail, not like their browser.
     *
     * @param string $url
     * @return string
     */
    private static function in_language(string $url): string {
        global $CFG;
        return str_starts_with($url, $CFG->wwwroot . '/')
            ? (new \moodle_url($url, ['lang' => current_language()]))->out(false) : $url;
    }

    /**
     * Books in rows of four, keeping their width when a row is short.
     *
     * @param array $books
     * @return array Rows, each with its cells.
     */
    private static function rows(array $books): array {
        $rows = [];
        foreach (array_chunk($books, 4) as $chunk) {
            $cells = array_map(fn($book) => $book + ['isbook' => true], $chunk);
            while (count($cells) < 4) {
                $cells[] = ['isbook' => false];
            }
            $rows[] = ['cells' => $cells];
        }
        return $rows;
    }
}
