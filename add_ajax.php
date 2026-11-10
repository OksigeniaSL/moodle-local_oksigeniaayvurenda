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
 * The questions of the screen that adds books: what the catalogues say of an ISBN, save, undo.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\catalogued;
use local_oksigeniaayvurenda\local\cataloguer;
use local_oksigeniaayvurenda\local\covers;
use local_oksigeniaayvurenda\local\lookup;
use local_oksigeniaayvurenda\local\plan_full;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
$action = required_param('action', PARAM_ALPHA);
require_login(null, false);
require_sesskey();
$library = catalogue::library($ref);
access::require('local/oksigeniaayvurenda:catalogue', $library);
$PAGE->set_context(context_system::instance());
if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$entityid = (int) $library->entityid;
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
core_php_time_limit::raise(60);

/**
 * Names in a sentence: «A, B y C».
 *
 * @param string[] $names
 * @param string $last The word before the last one, with its spaces.
 * @return string
 */
function local_oksigeniaayvurenda_join(array $names, string $last): string {
    $tail = array_pop($names);
    return $names ? implode(', ', $names) . $last . $tail : (string) $tail;
}

/**
 * A book on the screen: what a catalogue said of it, whether the library has it already, its spine and its cover.
 *
 * @param stdClass $library
 * @param string $isbn Empty when it has none.
 * @param array $description
 * @param array $how found, source, tried (names), cover (an address or null).
 * @return array
 */
function local_oksigeniaayvurenda_book(stdClass $library, string $isbn, array $description, array $how): array {
    global $USER;
    $entityid = (int) $library->entityid;
    $existing = $isbn !== '' ? cataloguer::existing($entityid, $isbn) : [];
    $record = $description['record'];
    $cover = null;
    if ($existing) {
        $cover = covers::url($existing[0]['id']);
    } else if ($isbn !== '') {
        $cover = covers::draft((int) $USER->id, $isbn, $how['cover']);
    }
    return [
        'kind' => 'book',
        'isbn' => $isbn,
        'found' => $how['found'],
        'source' => $how['source'],
        'tried' => local_oksigeniaayvurenda_join($how['tried'], get_string('addor', 'local_oksigeniaayvurenda')),
        'title' => $existing ? $existing[0]['title'] : $record['title'],
        'subtitle' => $record['subtitle'] ?? '',
        'authors' => $description['authorities']['author'] ?? [],
        'publisher' => $description['authorities']['publisher'][0] ?? '',
        'year' => $record['pubyear'] ?? '',
        'extent' => $record['extent'] ?? '',
        'existing' => $existing,
        'remark' => \local_oksigeniaayvurenda\local\memory::cataloguing(
            $entityid,
            (string) ($existing ? $existing[0]['title'] : ($record['title'] ?? '')),
            (array) ($description['authorities']['author'] ?? [])
        ),
        'spine' => cataloguer::spine($library, $description, $existing[0]['id'] ?? 0),
        'spinebefore' => (bool) $existing,
        'cover' => $cover,
    ];
}

switch ($action) {
    case 'lookup':
        $found = lookup::isbn($entityid, required_param('isbn', PARAM_TEXT));
        $answer = ['kind' => 'notisbn', 'isbn' => $found['isbn']];
        if ($found['valid']) {
            $answer = local_oksigeniaayvurenda_book(
                $library,
                $found['isbn'],
                $found['description'] ?? ['record' => ['title' => ''], 'authorities' => []],
                ['found' => $found['found'], 'source' => $found['sourcename'], 'tried' => $found['tried'],
                    'cover' => $found['cover']]
            );
        }
        break;
    case 'searchwhere':
        // Which catalogues a search asks: the screen asks them itself, a few at a time, and shows how it goes.
        $where = lookup::word_sources($entityid);
        $answer = ['kind' => 'where', 'sources' => $where, 'enough' => lookup::ENOUGH, 'most' => lookup::FOUND,
            'tried' => $where ? local_oksigeniaayvurenda_join(array_column($where, 'name'), $str('addor'))
                : $str('practicecatalogue')];
        break;
    case 'searchone':
        $answer = ['kind' => 'results', 'items' => array_map(fn($f) => [
            'pick' => $f['pick'],
            'same' => $f['same'],
            'score' => $f['score'],
            'source' => $f['source'],
            'title' => $f['description']['record']['title'] . (!empty($f['description']['record']['subtitle'])
                ? ': ' . $f['description']['record']['subtitle'] : ''),
            'authors' => implode(' · ', $f['description']['authorities']['author'] ?? []),
            'facts' => implode(' · ', array_filter([
                $f['description']['authorities']['publisher'][0] ?? '',
                $f['description']['record']['pubyear'] ?? '',
                $f['description']['record']['isbn'] ?? '',
            ])),
        ], lookup::words_in($entityid, required_param('q', PARAM_TEXT), required_param('source', PARAM_ALPHANUMEXT)))];
        break;
    case 'searchspell':
        // Nothing found: what it may have been meant to say.
        $answer = ['kind' => 'spelled', 'near' => lookup::spelled($entityid, required_param('q', PARAM_TEXT))];
        break;
    case 'pick':
        // One of them, or none (to write it by hand).
        $chosen = lookup::picked(optional_param('pick', '', PARAM_ALPHANUM));
        $description = $chosen['description'] ?? ['record' => ['title' => ''], 'authorities' => []];
        $answer = local_oksigeniaayvurenda_book(
            $library,
            (string) ($description['record']['isbn'] ?? ''),
            $description,
            ['found' => (bool) $chosen, 'source' => $chosen['source'] ?? '', 'tried' => [],
                'cover' => !empty($description['record']['isbn'])
                    ? ($description['extra']['cover'] ?? \local_oksigeniaayvurenda\core\sources\openlibrary::cover_url(
                        (string) $description['record']['isbn']
                    )) : null]
        ) + ['pick' => $chosen ? optional_param('pick', '', PARAM_ALPHANUM) : ''];
        break;
    case 'save':
        $isbn = optional_param('isbn', '', PARAM_ALPHANUMEXT);
        $chosen = lookup::picked(optional_param('pick', '', PARAM_ALPHANUM));
        $target = catalogue::library(optional_param('target', $library->slug, PARAM_ALPHANUMEXT));
        if (!$target || (int) $target->entityid !== $entityid) {
            throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
        }
        $recordid = optional_param('recordid', 0, PARAM_INT);
        if ($chosen) {
            // Chosen from a search by words: as that catalogue described it.
            $description = $chosen['description'];
            $isbn = (string) ($description['record']['isbn'] ?? '');
            $found = ['valid' => $isbn !== '', 'isbn' => $isbn, 'sourcename' => $chosen['source']];
        } else {
            $found = $isbn !== '' ? lookup::isbn($entityid, $isbn) : ['valid' => false, 'isbn' => ''];
            $description = $found['description'] ?? ['record' => ['isbn' => $found['valid'] ? $found['isbn'] : null],
                'authorities' => [], 'extra' => []];
        }
        // What the person typed or corrected wins over what the catalogue said.
        foreach (['title' => 'title', 'subtitle' => 'subtitle', 'year' => 'pubyear'] as $param => $field) {
            $value = trim(optional_param($param, '', PARAM_TEXT));
            if ($value !== '') {
                $description['record'][$field] = $value;
            }
        }
        $pages = optional_param('pages', 0, PARAM_INT);
        if ($pages > 0) {
            $description['record']['extent'] = $pages . ' p.';
        }
        foreach (['author' => 'author', 'publisher' => 'publisher'] as $param => $kind) {
            $value = trim(optional_param($param, '', PARAM_TEXT));
            if ($value !== '' && !in_array($value, $description['authorities'][$kind] ?? [], true)) {
                $description['authorities'][$kind] = array_merge([$value], $description['authorities'][$kind] ?? []);
            }
        }
        if (trim((string) ($description['record']['title'] ?? '')) === '') {
            throw new moodle_exception('addnotitle', 'local_oksigeniaayvurenda');
        }
        // A new record whose title (and author) the centre already has: the same book typed twice, unless the person says
        // it is another. A book with an ISBN, only against records without one: those with it were found by it.
        $noisbn = trim((string) ($description['record']['isbn'] ?? '')) === '';
        if (!$recordid && !optional_param('confirmnew', 0, PARAM_BOOL)) {
            $similar = cataloguer::similar(
                $entityid,
                (string) $description['record']['title'],
                (array) ($description['authorities']['author'] ?? []),
                !$noisbn
            );
            if ($similar) {
                echo json_encode(
                    ['kind' => 'similar', 'records' => $similar, 'message' => $str(
                        $noisbn ? 'addsimilar' : 'addsimilarnoisbn',
                        $similar[0]['title'] . ($similar[0]['author'] !== '' ? ', ' . $similar[0]['author'] : '')
                    )],
                    JSON_UNESCAPED_UNICODE
                );
                die();
            }
        }
        try {
            $added = cataloguer::add($target, $description, [
                'count' => optional_param('count', 1, PARAM_INT),
                'barcodes' => preg_split('/[\s,;]+/', optional_param('barcodes', '', PARAM_TEXT)) ?: [],
                'callnumber1' => optional_param('callnumber1', '', PARAM_TEXT),
                'callnumber2' => optional_param('callnumber2', '', PARAM_TEXT),
                'callnumber3' => optional_param('callnumber3', '', PARAM_TEXT),
                'locationid' => optional_param('locationid', 0, PARAM_INT),
                'itemtype' => optional_param('itemtype', 'normal', PARAM_ALPHANUMEXT),
            ], $recordid);
        } catch (invalid_parameter_exception $e) {
            $taken = preg_replace('/^.*taken:/', '', $e->debuginfo ?? $e->getMessage());
            echo json_encode(['error' => $str('addtaken', $taken)], JSON_UNESCAPED_UNICODE);
            die();
        } catch (plan_full $e) {
            echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
            die();
        }
        if ($found['valid'] && ($added['newrecord'] || covers::url($added['recordid']) === null)) {
            covers::adopt((int) $USER->id, $found['isbn'], $added['recordid'], (string) ($found['sourcename'] ?? 'Open Library'));
        }
        $count = count($added['barcodes']);
        $copies = $count === 1 ? $str('addcopiesone', $added['barcodes'][0])
            : $str('addcopiesmany', (object) ['count' => $count, 'codes' => implode(', ', $added['barcodes'])]);
        $title = $DB->get_field('local_oksigeniaayvurenda_record', 'title', ['id' => $added['recordid']]);
        $answer = $added + [
            'kind' => 'saved',
            'message' => $str('addsaved', (object) ['title' => $title, 'copies' => $copies]),
            'cover' => covers::url($added['recordid']),
            'url' => (new moodle_url('/local/oksigeniaayvurenda/record.php', ['library' => $target->slug,
                'id' => $added['recordid']]))->out(false),
            // The count of «Catalogued today», which the page made when it opened.
            'today' => $str('catalogedtodaylink', \local_oksigeniaayvurenda\local\screens::number(catalogued::today($library))),
        ];
        catalogue::purge($entityid);
        break;
    case 'classes':
        // The class while it is typed: the numbers that fit a word, or those that start with a number.
        $answer = ['kind' => 'classes', 'classes' => array_map(
            fn($class) => $class + ['note' => $class['used']
            ? $str($class['used'] === 1 ? 'classusedone' : 'classused', $class['used'])
            : ($class['count'] ? $str($class['count'] === 1 ? 'classbooksone' : 'classbooks', $class['count']) : '')],
            \local_oksigeniaayvurenda\local\classify::suggest($library, required_param('q', PARAM_TEXT))
        )];
        break;
    case 'subjects':
        // The subjects the centre uses that start like what is typed, the most used first.
        $answer = ['kind' => 'subjects', 'subjects' => array_map(
            fn($subject) => ['label' => $subject['label'],
            'note' => $subject['count'] === 1 ? $str('subjectsbookone') : $str(
                'subjectsbooks',
                \local_oksigeniaayvurenda\local\screens::number($subject['count'])
            )],
            \local_oksigeniaayvurenda\local\subjects::suggest($entityid, required_param('q', PARAM_TEXT))
        )];
        break;
    case 'undo':
        $itemids = array_filter(array_map('intval', explode(',', required_param('itemids', PARAM_SEQUENCE))));
        $done = cataloguer::undo(
            $entityid,
            required_param('recordid', PARAM_INT),
            $itemids,
            optional_param('newrecord', 0, PARAM_BOOL)
        );
        $answer = ['kind' => $done ? 'undone' : 'kept', 'message' => $str($done ? 'addundone' : 'addcantundo'),
            'today' => $str('catalogedtodaylink', \local_oksigeniaayvurenda\local\screens::number(catalogued::today($library)))];
        if ($done) {
            catalogue::purge($entityid);
        }
        break;
    default:
        throw new moodle_exception('invalidparameter', 'debug');
}
echo json_encode($answer, JSON_UNESCAPED_UNICODE);
