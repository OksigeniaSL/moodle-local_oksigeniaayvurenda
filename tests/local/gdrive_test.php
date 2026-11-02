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
namespace local_oksigeniaayvurenda\local;

/**
 * The copies in a centre's Google Drive, against a Drive in memory that behaves as Google says it does.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(gdrive::class)]
final class gdrive_test extends \advanced_testcase {
    /** @var array The files in the Drive: id => name, parent, content. */
    private array $files = [];

    /** @var array Every request: method and address. */
    private array $asked = [];

    /** @var string[] Refresh tokens Google takes. */
    private array $good = ['r-1'];

    /** @var string[] Refresh tokens given back. */
    private array $revoked = [];

    /** @var bool Whether uploads fail. */
    private bool $broken = false;

    /**
     * Google, in memory.
     */
    protected function setUp(): void {
        parent::setUp();
        gdrive::$network = function (string $method, string $url, array $headers, $body): array {
            $this->asked[] = [$method, $url, $headers, $body];
            $path = (string) parse_url($url, PHP_URL_PATH);
            if (str_ends_with($path, '/token')) {
                parse_str((string) $body, $form);
                if ($form['grant_type'] === 'authorization_code') {
                    return $form['code'] === 'code-good' ? [200, json_encode(['refresh_token' => 'r-1', 'access_token' => 'a-1'])]
                        : [400, '{"error":"invalid_grant"}'];
                }
                return in_array($form['refresh_token'], $this->good, true) ? [200, json_encode(['access_token' => 'a-1'])]
                    : [400, '{"error":"invalid_grant"}'];
            }
            if (str_ends_with($path, '/revoke')) {
                parse_str((string) $body, $form);
                $this->revoked[] = $form['token'];
                return [200, ''];
            }
            if (!in_array('Authorization: Bearer a-1', $headers, true) && $method !== 'PUT') {
                return [401, ''];
            }
            if (str_contains($path, '/upload/')) {
                $meta = json_decode((string) $body, true);
                return $this->broken ? [500, ''] : [200, '',
                    ['location' => 'https://up.example/s/' . rawurlencode($meta['name']) . '/'
                    . $meta['parents'][0]]];
            }
            if ($method === 'PUT') {
                [, , $name, $folder] = explode('/', $path);
                $id = 'f' . (count($this->files) + 1);
                $this->files[$id] = ['name' => rawurldecode($name), 'parent' => $folder,
                    'content' => file_get_contents($body['file'])];
                return [200, json_encode(['id' => $id])];
            }
            if ($method === 'POST') {
                $meta = json_decode((string) $body, true);
                $id = 'folder' . (count($this->files) + 1);
                $this->files[$id] = ['name' => $meta['name'], 'parent' => '', 'content' => ''];
                return [200, json_encode(['id' => $id])];
            }
            if ($method === 'GET') {
                $list = [];
                foreach ($this->files as $id => $file) {
                    if ($file['parent'] !== '') {
                        $list[] = ['id' => $id, 'name' => $file['name']];
                    }
                }
                return [200, json_encode(['files' => $list])];
            }
            unset($this->files[substr($path, strrpos($path, '/') + 1)]);
            return [204, ''];
        };
    }

    /**
     * Back to the network.
     */
    protected function tearDown(): void {
        gdrive::$network = null;
        parent::tearDown();
    }

    /**
     * A centre with its key, on a site with the client.
     *
     * @return int
     */
    private function centre(): int {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('driveclientid', 'id-1', 'local_oksigeniaayvurenda');
        set_config('driveclientsecret', 'sec-1', 'local_oksigeniaayvurenda');
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $entityid = (int) $gen->create_library()->entityid;
        offsite::new_key($entityid);
        return $entityid;
    }

    /**
     * What is asked of Google: the narrowest permission, offline, and back through Moodle's page.
     */
    public function test_asking(): void {
        $this->centre();
        $this->assertTrue(gdrive::configured());
        $url = gdrive::auth_url(new \moodle_url('/local/oksigeniaayvurenda/copies.php', ['library' => 'x', 'sesskey' => 'k']));
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame('https://www.googleapis.com/auth/drive.file', $query['scope']);
        $this->assertSame('offline', $query['access_type']);
        $this->assertSame('consent', $query['prompt']);
        $this->assertSame('id-1', $query['client_id']);
        $this->assertStringEndsWith('/admin/oauth2callback.php', $query['redirect_uri']);
        $this->assertStringContainsString('sesskey=k', $query['state']);
        unset_config('driveclientsecret', 'local_oksigeniaayvurenda');
        $this->assertFalse(gdrive::configured());
    }

    /**
     * Connected: a folder of its own, the token kept in the secrets and not in the settings; each night's copy goes there,
     * old ones go away, and only its own are looked at.
     */
    public function test_connect_send_and_keep(): void {
        global $DB;
        $entityid = $this->centre();
        $this->assertNull(offsite::add_drive($entityid, 'code-bad'));
        $place = offsite::add_drive($entityid, 'code-good');
        $this->assertNotNull($place);
        $settings = offsite::settings($entityid);
        $this->assertSame('gdrive', $settings['places'][0]['kind']);
        $this->assertStringStartsWith('Ayvurenda', current($this->files)['name']);
        $stored = (string) $DB->get_field('local_oksigeniaayvurenda_entity', 'settings', ['id' => $entityid]);
        $this->assertStringNotContainsString('r-1', $stored);
        $this->assertNull(offsite::test($entityid, $place));
        $this->assertCount(1, $this->files, 'the trial file went and left');

        offsite::set_keep($entityid, 2, 0, 0);
        $now = time();
        $folder = $settings['places'][0]['folder'];
        for ($i = 5; $i > 0; $i--) {
            $this->files['old' . $i] = ['name' => offsite::name($entityid, $now - $i * DAYSECS), 'parent' => $folder,
                'content' => 'x'];
        }
        $copy = get_file_storage()->create_file_from_string(['contextid' => \context_system::instance()->id,
            'component' => 'local_oksigeniaayvurenda', 'filearea' => 'copytest', 'itemid' => 0, 'filepath' => '/',
            'filename' => 'copia.zip'], 'contenido de la copia');
        $this->assertSame(['sent' => 1, 'failed' => []], offsite::send($entityid, $copy, $now));
        $names = array_column(array_filter($this->files, fn($f) => $f['parent'] !== ''), 'name');
        $this->assertContains(offsite::name($entityid, $now), $names);
        $this->assertLessThan(6, count($names), 'old ones went');
        $sent = array_values(array_filter($this->files, fn($f) => $f['name'] === offsite::name($entityid, $now)))[0];
        $this->assertStringStartsWith('age-encryption.org/v1', $sent['content'], 'encrypted before it leaves');
        $this->assertStringNotContainsString('contenido de la copia', $sent['content']);
    }

    /**
     * Permission taken away at Google: the night says so, and on the second one whoever manages it is told. Taken away here:
     * it goes back to Google, and what is in the folder stays.
     */
    public function test_permission_gone(): void {
        $entityid = $this->centre();
        $place = offsite::add_drive($entityid, 'code-good');
        $this->good = [];
        \cache::make('local_oksigeniaayvurenda', 'access')->purge();
        $this->assertSame(
            get_string('offsitedenied', 'local_oksigeniaayvurenda', 401),
            offsite::test($entityid, $place)
        );
        $this->good = ['r-1'];
        \cache::make('local_oksigeniaayvurenda', 'access')->purge();
        $before = $this->files;
        offsite::remove($entityid, $place);
        $this->assertSame(['r-1'], $this->revoked);
        $this->assertSame($before, $this->files);
        $this->assertSame([], offsite::settings($entityid)['places']);
    }

    /**
     * Only with the plan, and only so many places.
     */
    public function test_plan(): void {
        $entityid = $this->centre();
        plans::save($entityid, plans::preset('free'));
        $this->assertNull(offsite::add_drive($entityid, 'code-good'));
        $tokens = array_filter($this->asked, fn($a) => str_ends_with((string) parse_url($a[1], PHP_URL_PATH), '/token'));
        $this->assertSame([], $tokens);
    }
}
