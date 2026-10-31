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

use local_oksigeniaayvurenda\core\age;

/**
 * Tests for the copies out of the server.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\offsite
 */
#[\PHPUnit\Framework\Attributes\CoversClass(offsite::class)]
final class offsite_test extends \advanced_testcase {
    /** @var array The files of the pretend WebDAV folder, by name. */
    private array $folder = [];

    /** @var bool Whether the pretend folder exists yet. */
    private bool $made = false;

    /** @var int What every PUT answers, when not 0. */
    private int $broken = 0;

    /**
     * A WebDAV folder in memory, in place of the network.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->folder = [];
        $this->made = false;
        $this->broken = 0;
        offsite::$network = function (string $method, string $url, string $payload): array {
            $name = rawurldecode((string) substr($url, strlen('https://nube.ejemplo.org/dav/Copias/')));
            if ($method === 'MKCOL') {
                $this->made = true;
                return [201, ''];
            }
            if ($method === 'PUT') {
                if ($this->broken) {
                    return [$this->broken, ''];
                }
                if (!$this->made) {
                    return [409, ''];
                }
                $this->folder[$name] = file_get_contents($payload);
                return [201, ''];
            }
            if ($method === 'DELETE') {
                unset($this->folder[$name]);
                return [204, ''];
            }
            $hrefs = implode('', array_map(
                fn($n) => '<d:response><d:href>/dav/Copias/' . rawurlencode($n) . '</d:href></d:response>',
                array_keys($this->folder)
            ));
            return [207, '<?xml version="1.0"?><d:multistatus xmlns:d="DAV:"><d:response><d:href>/dav/Copias/</d:href>'
                . '</d:response>' . $hrefs . '</d:multistatus>'];
        };
    }

    /**
     * Back to the network.
     */
    protected function tearDown(): void {
        offsite::$network = null;
        parent::tearDown();
    }

    /**
     * Each copy goes encrypted and opens with the centre's key; the folder is made if missing; only the kept stay.
     */
    public function test_send(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $entityid = (int) $library->entityid;
        $gen->create_item(['libraryid' => $library->id]);
        $copy = copies::make($entityid);
        // Without a key or a place, nothing leaves.
        $this->assertSame(['sent' => 0, 'failed' => []], offsite::send($entityid, $copy));
        $this->assertNull(offsite::add_webdav($entityid, 'http://nube.ejemplo.org/dav/Copias/', 'b', 'x'));
        $this->assertNull(offsite::add_webdav($entityid, 'javascript:alert(1)', 'b', 'x'));
        $place = offsite::add_webdav($entityid, 'https://nube.ejemplo.org/dav/Copias', 'biblioteca', 'clave-de-prueba');
        $this->assertNotNull($place);
        $this->assertNull(offsite::test($entityid, $place));
        $this->assertSame([], $this->folder, 'the test file was taken away');
        $this->assertFalse(offsite::use_key($entityid, 'age1nonsense'));
        $secret = offsite::new_key($entityid);
        // Old copies there: every day of the last hundred.
        $now = make_timestamp(2026, 10, 31, 3, 0);
        for ($i = 1; $i < 100; $i++) {
            $this->folder[offsite::name($entityid, $now - $i * DAYSECS)] = 'old';
        }
        $this->folder['otra-cosa.txt'] = 'not ours';
        $this->assertSame(['sent' => 1, 'failed' => []], offsite::send($entityid, $copy, $now));
        $name = offsite::name($entityid, $now);
        $dir = make_request_directory();
        file_put_contents($dir . '/c.age', $this->folder[$name]);
        age::decrypt($dir . '/c.age', $dir . '/c.zip', $secret);
        $this->assertSame($copy->get_content(), file_get_contents($dir . '/c.zip'));
        // Seven days, the newest of four weeks and of twelve months; and what is not a copy of the centre stays.
        $this->assertArrayHasKey('otra-cosa.txt', $this->folder);
        $this->assertLessThanOrEqual(7 + 4 + 12 + 1, count($this->folder));
        $this->assertGreaterThan(7 + 1, count($this->folder));
        $this->assertSame(1, offsite::settings($entityid)['last']['sent']);
    }

    /**
     * Two nights that fail, and whoever manages the centre is told, once.
     */
    public function test_failures_are_told(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $entityid = (int) $library->entityid;
        $manager = $this->getDataGenerator()->create_user();
        role_assign($DB->get_field('role', 'id', ['shortname' => 'manager']), $manager->id, \context_system::instance());
        offsite::new_key($entityid);
        offsite::add_webdav($entityid, 'https://nube.ejemplo.org/dav/Copias/', 'biblioteca', 'clave-de-prueba');
        $copy = copies::make($entityid);
        $this->broken = 507;
        $sink = $this->redirectMessages();
        $this->assertSame(['nube.ejemplo.org'], offsite::send($entityid, $copy)['failed']);
        $this->assertSame(0, $sink->count());
        offsite::send($entityid, $copy);
        $told = array_filter($sink->get_messages(), fn($m) => $m->eventtype === 'copyfailed');
        $this->assertCount(1, $told);
        $this->assertSame((int) $manager->id, (int) reset($told)->useridto);
        offsite::send($entityid, $copy);
        $this->assertCount(1, array_filter($sink->get_messages(), fn($m) => $m->eventtype === 'copyfailed'));
        $placeid = offsite::settings($entityid)['places'][0]['id'];
        $this->assertStringContainsString('507', (string) offsite::test($entityid, $placeid));
    }

    /**
     * The link of the library's computer opens with its own key only, and stops when asked.
     */
    public function test_pull(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $entityid = (int) $gen->create_library()->entityid;
        $url = new \moodle_url(offsite::new_pull($entityid));
        $key = $url->get_param('key');
        // Without a key to encrypt, nothing leaves.
        $this->assertFalse(offsite::pull_allowed($entityid, $key));
        $pair = age::keypair();
        $this->assertTrue(offsite::use_key($entityid, $pair['public']));
        $this->assertTrue(offsite::pull_allowed($entityid, $key));
        $this->assertFalse(offsite::pull_allowed($entityid, 'otra'));
        $this->assertFalse(offsite::pull_allowed($entityid + 1, $key));
        offsite::stop_pull($entityid);
        $this->assertFalse(offsite::pull_allowed($entityid, $key));
    }
}
