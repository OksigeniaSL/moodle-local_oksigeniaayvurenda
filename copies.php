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
 * The daily copies of a library, to download.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\copies;
use local_oksigeniaayvurenda\local\gdrive;
use local_oksigeniaayvurenda\local\offsite;
use local_oksigeniaayvurenda\local\plans;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
require_login(null, false);
$library = catalogue::library($ref);
$context = access::context($library);
shell::require('local/oksigeniaayvurenda:manage', $library);
shell::no_practice($library);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/copies.php', ['library' => $ref]));

if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$entityid = (int) $library->entityid;
$manage = has_capability('local/oksigeniaayvurenda:manage', $context);
$notice = '';
// Shown once, on the page that made them: the secret key and the address of the library's computer.
$secret = '';
$pullurl = '';
// Back from Google, with a code for the permission just given: Moodle's page checked the key, and so does this.
$code = optional_param('oauth2code', '', PARAM_RAW);
if ($code !== '' && $manage && confirm_sesskey() && optional_param('drivefor', 0, PARAM_INT) === $entityid) {
    $id = offsite::add_drive($entityid, $code);
    $good = $id !== null && offsite::test($entityid, $id) === null;
    // Back to a clean address: the code does not stay in the bar or in the history.
    $answer = $good ? 'ok' : (gdrive::$why === 'api' ? 'api' : 'no');
    redirect(new moodle_url('/local/oksigeniaayvurenda/copies.php', ['library' => $ref, 'drive' => $answer]));
}
$drive = optional_param('drive', '', PARAM_ALPHA);
if ($drive !== '') {
    $notice = $str(['ok' => 'offsitedriveadded', 'api' => 'offsitedriveapi'][$drive] ?? 'offsitedrivefailed');
}
if (data_submitted() && confirm_sesskey() && $manage) {
    $do = optional_param('do', 'make', PARAM_ALPHA);
    // The copy off the server goes with the plan; the daily copy, always.
    if (!in_array($do, ['make', 'makefiles', 'clearoffsite'], true) && !plans::allows((int) $library->entityid, 'offsite')) {
        $do = '';
    }
    if ($do === 'make') {
        $file = copies::make($entityid);
        $out = offsite::send($entityid, $file);
        $notice = $str('copymade') . ($out['sent'] ? ' ' . $str(
            $out['sent'] === 1 ? 'offsitesentnowone' : 'offsitesentnow',
            $out['sent']
        ) : '')
            . ($out['failed'] ? ' ' . $str('offsitefailednow', implode(', ', $out['failed'])) : '');
    } else if ($do === 'clearoffsite') {
        offsite::clear($entityid);
        $notice = $str('offsiteplaceremoved');
    } else if ($do === 'makefiles') {
        \local_oksigeniaayvurenda\local\filecopy::queue($entityid);
        $notice = $str('filecopyqueued');
    } else if ($do === 'newkey') {
        $secret = offsite::new_key($entityid);
        $notice = $str('offsitekeymade');
    } else if ($do === 'usekey') {
        $notice = $str(offsite::use_key($entityid, optional_param('recipient', '', PARAM_RAW_TRIMMED)) ? 'offsitekeyused'
            : 'offsitekeybad');
    } else if ($do === 'adds3') {
        $id = offsite::add_s3(
            $entityid,
            optional_param('endpoint', '', PARAM_RAW_TRIMMED),
            optional_param('bucket', '', PARAM_RAW_TRIMMED),
            optional_param('region', '', PARAM_RAW_TRIMMED),
            optional_param('accesskey', '', PARAM_RAW_TRIMMED),
            optional_param('secret', '', PARAM_RAW)
        );
        $error = $id === null ? $str('offsiteplacebad') : offsite::test($entityid, $id);
        $notice = $error === null ? $str('offsiteplaceadded') : ($id === null ? $error : $str('offsiteplacefails', $error));
    } else if ($do === 'addplace') {
        $id = offsite::add_webdav(
            $entityid,
            optional_param('url', '', PARAM_RAW_TRIMMED),
            optional_param('user', '', PARAM_RAW_TRIMMED),
            optional_param('password', '', PARAM_RAW)
        );
        $error = $id === null ? $str('offsiteplacebad') : offsite::test($entityid, $id);
        $notice = $error === null ? $str('offsiteplaceadded') : ($id === null ? $error : $str('offsiteplacefails', $error));
    } else if ($do === 'drive' && gdrive::configured()) {
        // To Google, which brings its answer back here through Moodle's page, with the session's key.
        redirect(gdrive::auth_url(new moodle_url('/local/oksigeniaayvurenda/copies.php', ['library' => $ref,
            'sesskey' => sesskey(), 'drivefor' => $entityid])));
    } else if ($do === 'testplace') {
        $error = offsite::test($entityid, optional_param('place', '', PARAM_ALPHANUM));
        $notice = $error === null ? $str('offsiteplaceworks') : $str('offsiteplacefails', $error);
    } else if ($do === 'removeplace') {
        offsite::remove($entityid, optional_param('place', '', PARAM_ALPHANUM));
        $notice = $str('offsiteplaceremoved');
    } else if ($do === 'keep') {
        offsite::set_keep(
            $entityid,
            optional_param('daily', 7, PARAM_INT),
            optional_param('weekly', 4, PARAM_INT),
            optional_param('monthly', 12, PARAM_INT)
        );
        $notice = $str('offsitekeepsaved');
    } else if ($do === 'newpull') {
        $pullurl = offsite::new_pull($entityid);
        $notice = $str('offsitepullmade');
    } else if ($do === 'stoppull') {
        offsite::stop_pull($entityid);
        $notice = $str('offsitepullstopped');
    }
}
$files = [];
foreach (copies::list((int) $library->entityid) as $file) {
    $files[] = [
        'url' => moodle_url::make_pluginfile_url(
            context_system::instance()->id,
            'local_oksigeniaayvurenda',
            'copies',
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename(),
            true
        )->out(false),
        'label' => userdate($file->get_timecreated(), get_string('strftimedatetime', 'langconfig')),
        'size' => display_size($file->get_filesize()),
    ];
}
$data = [
    'heading' => $str('copiesheading'),
    'intro' => $str('copiesintro', copies::KEEP),
    'notice' => $notice,
    'files' => $files,
    'hasfiles' => (bool) $files,
    'empty' => $str('copiesempty'),
    'download' => $str('copydownload'),
    'make' => $manage ? ['action' => $PAGE->url->out(false), 'sesskey' => sesskey(), 'label' => $str('copymake')] : null,
    'filecopy' => $manage ? copies_files($entityid, $str) : null,
    'offsite' => $manage && plans::allows($entityid, 'offsite') ? copies_offsite($entityid, $secret, $pullurl, $str) : null,
    'offsitenote' => $manage && !plans::allows($entityid, 'offsite') ? $str('offsitenotinplan') : '',
    'offsiteclear' => $manage && !plans::allows($entityid, 'offsite') && offsite::has_any($entityid) ? [
        'action' => $PAGE->url->out(false), 'sesskey' => sesskey(), 'label' => $str('offsiteclearall')] : null,
];

/**
 * What the page says of the copy of the files, for whoever manages the centre.
 *
 * @param int $entityid
 * @param callable $str
 * @return array
 */
function copies_files(int $entityid, callable $str): array {
    global $PAGE;
    $latest = \local_oksigeniaayvurenda\local\filecopy::latest($entityid);
    $last = offsite::settings($entityid)['lastfiles'];
    return [
        'action' => $PAGE->url->out(false),
        'sesskey' => sesskey(),
        'waiting' => \local_oksigeniaayvurenda\local\filecopy::waiting($entityid),
        'url' => $latest ? moodle_url::make_pluginfile_url(
            context_system::instance()->id,
            'local_oksigeniaayvurenda',
            \local_oksigeniaayvurenda\local\filecopy::AREA,
            $entityid,
            '/',
            $latest->get_filename(),
            true
        )->out(false) : '',
        'label' => $latest ? userdate($latest->get_timecreated(), get_string('strftimedatetime', 'langconfig')) : '',
        'size' => $latest ? display_size($latest->get_filesize()) : '',
        'failed' => !empty($last['failed']) ? $str('filecopyfailed', implode(', ', $last['failed'])) : '',
    ];
}

/**
 * What the page says of the copies out of the server, for whoever manages the centre.
 *
 * @param int $entityid
 * @param string $secret A secret key just made, to show once.
 * @param string $pullurl A link just made, to show once.
 * @param callable $str
 * @return array
 */
function copies_offsite(int $entityid, string $secret, string $pullurl, callable $str): array {
    global $PAGE;
    $settings = offsite::settings($entityid);
    $day = fn(int $time) => userdate($time, get_string('strftimedate', 'langconfig'));
    $last = $settings['last'];
    if ($settings['recipient'] === '' || !$settings['places']) {
        $state = ['tone' => 'off', 'text' => $str($settings['recipient'] === '' ? 'offsitestatenokey' : 'offsitestatenoplace')];
    } else if (!$last) {
        $state = ['tone' => 'wait', 'text' => $str('offsitestatewait')];
    } else if (!empty($last['failed'])) {
        $state = ['tone' => 'bad', 'text' => $str('offsitestatefailed', (object) ['when' => userdate(
            $last['time'],
            get_string('strftimedatetime', 'langconfig')
        ), 'places' => implode(', ', $last['failed'])])];
    } else {
        $state = ['tone' => 'ok', 'text' => $str(
            $last['sent'] === 1 ? 'offsitestateokone' : 'offsitestateok',
            (object) ['when' => userdate($last['time'], get_string('strftimedatetime', 'langconfig')), 'count' => $last['sent']]
        )];
    }
    $keep = $settings['keep'];
    $scripts = [];
    if ($pullurl !== '') {
        $folder = 'Copias-Ayvurenda';
        $scripts = [
            ['id' => 'ayv-pull-linux', 'label' => $str('offsitescriptlinux'), 'hint' => $str('offsitescriptlinuxhint'),
                'code' => 'mkdir -p "$HOME/' . $folder . '" && curl -fsS -o "$HOME/' . $folder . '/ayvurenda-$(date +%F).zip.age" "'
                . $pullurl . '" && ls -1t "$HOME/' . $folder . '"/*.zip.age | tail -n +31 | xargs -r rm --'],
            ['id' => 'ayv-pull-windows', 'label' => $str('offsitescriptwindows'), 'hint' => $str('offsitescriptwindowshint'),
                'code' => '$d = "$env:USERPROFILE\\' . $folder . '"; New-Item -ItemType Directory -Force $d | Out-Null; '
                . 'Invoke-WebRequest -UseBasicParsing -Uri "' . $pullurl . '" -OutFile '
                . '"$d\ayvurenda-$(Get-Date -Format yyyy-MM-dd).zip.age"; '
                . 'Get-ChildItem $d -Filter *.zip.age | Sort-Object LastWriteTime -Descending | Select-Object -Skip 30 | '
                . 'Remove-Item'],
        ];
    }
    return [
        'action' => $PAGE->url->out(false),
        'sesskey' => sesskey(),
        'state' => $state,
        'haskey' => $settings['recipient'] !== '',
        'keyline' => $settings['recipient'] !== '' ? $str('offsitekeysince', $day($settings['keyed'])) : $str('offsitenokey'),
        'recipient' => $settings['recipient'],
        'secret' => $secret,
        'secretfile' => $secret !== '' ? 'data:text/plain;charset=utf-8;base64,'
            . base64_encode("# " . $str('offsitesecretfilehead')
            . "\n" . $secret . "\n") : '',
        'places' => array_map(
            fn($p) => ['id' => $p['id'], 'label' => $p['label'], 'url' => $p['url'], 'user' => $p['user']],
            $settings['places']
        ),
        'hasplaces' => (bool) $settings['places'],
        'canadd' => count($settings['places']) < offsite::PLACES,
        'candrive' => gdrive::configured() && count($settings['places']) < offsite::PLACES,
        'keepline' => $str('offsitekeepline', (object) $keep),
        'keepdaily' => $keep['daily'],
        'keepweekly' => $keep['weekly'],
        'keepmonthly' => $keep['monthly'],
        'haspull' => $settings['pull'],
        'pullurl' => $pullurl,
        'scripts' => $scripts,
        'hasscripts' => (bool) $scripts,
    ];
}
$deskurl = (new moodle_url('/local/oksigeniaayvurenda/desk.php', ['library' => $library->slug]))->out(false);
shell::output(
    $data['heading'],
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/copies', $data),
    $library,
    '',
    ['search' => false, 'nav' => [['url' => $deskurl, 'label' => $str('desktitle')]]]
);
