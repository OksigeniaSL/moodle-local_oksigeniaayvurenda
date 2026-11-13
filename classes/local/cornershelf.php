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
 * The corners of the room as plates on a shelf: moved, hidden and named with a touch each.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * The corners were a row of fields each, with ▲ and ▼, «Hide it» and one «Save» at the end, and the genres another form.
 * Now each corner is its plate standing on a shelf in the order of the room, as the hall of the visit shows them, with
 * «◀» and «▶», «Hide» (it turns its back) and its name on its back. The genres the catalogue suggests are dashed plates
 * added with one touch, and where the genres take their books from is a light per corner. Each gesture sends only what it
 * changes and comes back to the plate touched.
 */
final class cornershelf {
    /**
     * Does what one gesture asked, and says where to come back to.
     *
     * @param \stdClass $library
     * @return string The anchor of the plate or the part changed.
     */
    public static function apply(\stdClass $library): string {
        $list = catalogue::corners($library, true);
        $codes = array_map(fn($corner) => (string) $corner['code'], $list);
        $own = [];
        foreach ($list as $corner) {
            // A corner keeps a name of its own only when it is not the classification's; a genre's name is the genre's.
            $own[(string) $corner['code']] = ['label' => !empty($corner['genre']) || $corner['label'] === $corner['default']
                ? '' : $corner['label'], 'hidden' => !empty($corner['hidden'])];
        }
        $anchor = '';
        $changed = false;
        $move = optional_param('move', '', PARAM_RAW_TRIMMED);
        if (preg_match('/^([A-Za-z0-9]+):(-1|1)$/', $move, $m) && ($at = array_search($m[1], $codes, true)) !== false) {
            $to = $at + (int) $m[2];
            if ($to >= 0 && $to < count($codes)) {
                [$codes[$at], $codes[$to]] = [$codes[$to], $codes[$at]];
                $changed = true;
            }
            $anchor = 'r' . $m[1];
        }
        foreach (['hide' => true, 'show' => false] as $param => $hidden) {
            $code = optional_param($param, '', PARAM_ALPHANUM);
            if ($code !== '' && isset($own[$code])) {
                $own[$code]['hidden'] = $hidden;
                $changed = true;
                $anchor = 'r' . $code;
            }
        }
        $rename = optional_param('rename', '', PARAM_ALPHANUM);
        $genres = bank::is($library) ? [] : genres::of($library);
        $genreschanged = false;
        if ($rename !== '' && isset($own[$rename])) {
            $label = \core_text::substr(trim(optional_param('label', '', PARAM_TEXT)), 0, 80);
            $anchor = 'r' . $rename;
            if (genres::is_code($rename)) {
                // A genre's name is the genre's: renamed here, it is renamed there; blank, it keeps the one it had.
                foreach ($genres as &$genre) {
                    if ($genre['code'] === $rename && $label !== '') {
                        $genre['label'] = $label;
                        $genreschanged = true;
                    }
                }
                unset($genre);
            } else {
                $own[$rename]['label'] = $label;
                $changed = true;
            }
        }
        if ($changed) {
            $corners = [];
            foreach ($codes as $i => $code) {
                $corners[$code] = $own[$code] + ['order' => 10 * ($i + 1)];
            }
            catalogue::save_corners($library, $corners);
        }
        if (bank::is($library)) {
            return $anchor;
        }
        $genreanchor = self::genres($library, $genres, $genreschanged);
        return $anchor !== '' ? $anchor : $genreanchor;
    }

    /**
     * The gestures of the genres: one added from what the catalogue suggests (or all of them), one made by hand, one
     * changed or taken away, or a light of where they take their books from.
     *
     * @param \stdClass $library
     * @param array[] $genres The library's genres, maybe already renamed.
     * @param bool $changed Whether they were renamed and are to be kept anyway.
     * @return string The anchor of what changed, or ''.
     */
    private static function genres(\stdClass $library, array $genres, bool $changed): string {
        $anchor = '';
        $commas = fn(string $text): array => array_values(array_filter(array_map('trim', explode(',', $text)), 'strlen'));
        $propose = optional_param('propose', '', PARAM_ALPHA);
        $all = optional_param('proposeall', 0, PARAM_BOOL);
        if ($propose !== '' || $all) {
            foreach (genres::proposals($library) as $proposal) {
                if ($all || $proposal['key'] === $propose) {
                    $genres[] = ['label' => $proposal['label'], 'kind' => $proposal['key'], 'topics' => $proposal['topics'],
                        'prefixes' => []];
                    $changed = true;
                }
            }
            $anchor = 'ayv-ri-generos';
        }
        $edit = optional_param('genre', '', PARAM_ALPHANUM);
        if ($edit !== '') {
            $genre = ['label' => optional_param('label', '', PARAM_TEXT),
                'topics' => $commas(optional_param('topics', '', PARAM_TEXT)),
                'prefixes' => $commas(optional_param('prefixes', '', PARAM_TEXT))];
            $at = array_search($edit, array_column($genres, 'code'), true);
            if ($at !== false) {
                $genres[$at] = ['code' => $edit, 'kind' => $genres[$at]['kind']] + $genre;
            } else if ($edit === 'new' && trim($genre['label']) !== '') {
                $genres[] = $genre;
            }
            $changed = true;
            $anchor = 'ayv-ri-generos';
        }
        $drop = optional_param('genredrop', '', PARAM_ALPHANUM);
        if ($drop !== '') {
            $genres = array_values(array_filter($genres, fn($genre) => $genre['code'] !== $drop));
            $changed = true;
            $anchor = 'ayv-ri-generos';
        }
        $from = null;
        if (preg_match('/^([A-Za-z0-9]+):([01])$/', optional_param('from', '', PARAM_RAW_TRIMMED), $m)) {
            $from = genres::from($library);
            $from = $m[2] === '1' ? array_values(array_unique(array_merge($from, [$m[1]])))
                : array_values(array_diff($from, [$m[1]]));
            // The last light stays on: with none, the genres would take their books from nowhere.
            $from = $from ?: null;
            $changed = true;
            $anchor = 'ayv-ri-de';
        }
        if ($changed) {
            genres::save($library, $genres, $from);
        }
        return $anchor;
    }

    /**
     * What the page shows: the plates on their shelf, the genres and the lights.
     *
     * @param \stdClass $library
     * @param string $action
     * @param string $saved The anchor of what was just changed, which says so.
     * @return array
     */
    public static function context(\stdClass $library, string $action, string $saved = ''): array {
        $entityid = (int) $library->entityid;
        $str = skins::speaker($library);
        $books = fn(int $count) => $str($count === 1 ? 'onebook' : 'books', screens::number($count));
        $list = array_values(catalogue::corners($library, true));
        $plates = [];
        foreach ($list as $i => $corner) {
            $code = (string) $corner['code'];
            $isgenre = !empty($corner['genre']);
            $art = $isgenre ? ($corner['kind'] !== '' ? screens::art('genero-' . $corner['kind']) : null)
                : screens::art('rincon-' . preg_replace('/[^a-z0-9]/', '', \core_text::strtolower($code)));
            $plates[] = [
                'code' => $code,
                'label' => $corner['label'],
                'own' => $isgenre || $corner['label'] !== $corner['default'] ? $corner['label'] : '',
                'default' => $corner['default'],
                'says' => ($isgenre ? $str('genrekind') : $code) . ' · ' . $books((int) $corner['count']),
                'art' => $art ?: null,
                'mark' => $isgenre ? \core_text::strtoupper(\core_text::substr($corner['label'], 0, 1)) : $code,
                'colour' => $corner['colour'],
                'hidden' => !empty($corner['hidden']),
                'first' => $i === 0,
                'last' => $i === count($list) - 1,
                'leftlabel' => $str('cornersleft', $corner['label']),
                'rightlabel' => $str('cornersright', $corner['label']),
                'nameof' => $str('cornersnameof', $corner['label']),
                'blank' => $isgenre ? $str('cornersgenrename') : $str('cornersblank'),
                'justsaved' => $saved === 'r' . $code,
            ];
        }
        $pose = $saved !== '' ? 'celebra' : 'lee';
        $crier = characters::whole($entityid) ? characters::figure('herald', $pose, characters::step($entityid)) : null;
        return [
            'title' => $str('cornersheading'),
            'intro' => $str('cornersintro'),
            'action' => $action,
            'sesskey' => sesskey(),
            'crier' => $crier ? ['figure' => $crier, 'puppet' => characters::puppet('herald', $pose, characters::step($entityid))]
                : null,
            'savedword' => $str('hiwsaved'),
            'shelf' => $str('cornersshelf'),
            'shelfsub' => $str('cornersshelfsub'),
            'hide' => $str('cornershide'),
            'show' => $str('cornersshow'),
            'rename' => $str('cornersrename'),
            'save' => $str('cornerssave'),
            'plates' => $plates,
            'hasplates' => (bool) $plates,
            'none' => $str('cornersnone'),
            'importurl' => (new \moodle_url('/local/oksigeniaayvurenda/import.php', ['library' => $library->slug]))->out(false),
            'importlabel' => $str('cornersimport'),
            'hallurl' => (new \moodle_url('/local/oksigeniaayvurenda/visit.php', ['library' => $library->slug]))->out(false),
            'halllabel' => $str('cornershall'),
            'genres' => $plates && !bank::is($library) ? self::genres_context($library, $list, $str, $books, $saved) : null,
        ];
    }

    /**
     * The genres for the page: those the library has, those its catalogue suggests and the lights of where they take
     * their books from.
     *
     * @param \stdClass $library
     * @param array[] $corners The library's corners, hidden ones too.
     * @param callable $str
     * @param callable $books
     * @param string $saved
     * @return array
     */
    private static function genres_context(
        \stdClass $library,
        array $corners,
        callable $str,
        callable $books,
        string $saved
    ): array {
        $counts = array_column($corners, 'count', 'code');
        $mine = [];
        foreach (genres::of($library) as $genre) {
            $mine[] = ['code' => $genre['code'], 'label' => $genre['label'],
                'says' => $books((int) ($counts[$genre['code']] ?? 0)),
                'topics' => implode(', ', $genre['topics']), 'prefixes' => implode(', ', $genre['prefixes']),
                'dropsure' => $genre['label'] . ': ' . $str('genresdropsure')];
        }
        $proposals = [];
        foreach (count($mine) < genres::MAX ? genres::proposals($library) : [] as $proposal) {
            $proposals[] = ['key' => $proposal['key'], 'label' => $proposal['label'], 'says' => $books((int) $proposal['count'])];
        }
        $from = array_flip(genres::from($library));
        $lights = [];
        foreach ($corners as $corner) {
            if (empty($corner['genre'])) {
                $on = isset($from[(string) $corner['code']]);
                $lights[] = ['value' => $corner['code'] . ':' . ($on ? '0' : '1'), 'pressed' => $on,
                    'label' => $corner['code'] . ' · ' . $corner['label']];
            }
        }
        $topics = genres::story_topics($library);
        return [
            'heading' => $str('genresheading'),
            'intro' => $str('genresintro') . ' ' . ($mine ? '' : $str('genresnone') . ' ')
                . ($proposals ? $str('genresproposalshint') : ''),
            'mine' => $mine,
            'proposals' => $proposals,
            'canadd' => count($mine) < genres::MAX,
            'many' => count($proposals) > 1,
            'lights' => $lights,
            'haslights' => (bool) $lights,
            'justsaved' => in_array($saved, ['ayv-ri-generos', 'ayv-ri-de'], true),
            'change' => $str('genresedit'),
            'drop' => $str('genresdrop'),
            'other' => $str('genresother'),
            'othersub' => $str('genresothersub'),
            'name' => $str('genresname'),
            'topicslabel' => $str('genrestopicslabel'),
            'topicshint' => $topics ? $str('genrestopicshint', implode(', ', $topics)) : $str('genrestopicshintnone'),
            'prefixeslabel' => $str('genresprefixeslabel'),
            'addit' => $str('genresaddit'),
            'save' => $str('genressave'),
            'addall' => $str('genresproposeall'),
            'fromlead' => $str('genresfromlead'),
            'fromhint' => $str('genresfromhint'),
        ];
    }
}
