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

namespace local_oksigeniaayvurenda\local\sources;

/**
 * Tests for the readers from a OneRoster REST API.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\sources\oneroster_api
 */
#[\PHPUnit\Framework\Attributes\CoversClass(oneroster_api::class)]
final class oneroster_api_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    #[\Override]
    protected function tearDown(): void {
        oneroster_api::$fetcher = null;
        parent::tearDown();
    }

    /**
     * A made-up server: each collection from the fixtures, a few at a time whatever is asked (as some servers do), and
     * what it was asked.
     *
     * @param string $version v1p1 or v1p2.
     * @param array $asked Where each request goes.
     * @param array $drop sourcedIds it no longer has.
     * @param int $size Things per page.
     * @return callable
     */
    private function server(string $version, array &$asked, array &$drop, int $size = 2): callable {
        $dir = __DIR__ . '/../../fixtures/oneroster/api/';
        return function (string $method, string $url, array $headers, string $body) use ($version, $dir, &$asked, &$drop, $size) {
            $asked[] = [$method, $url, $headers, $body];
            if ($method === 'POST') {
                return json_encode(['access_token' => 'made-up-token', 'token_type' => 'bearer', 'expires_in' => 3600]);
            }
            if (!preg_match('~/(users|orgs|classes|enrollments)\?limit=100&offset=(\d+)$~', $url, $m)) {
                return null;
            }
            $file = $dir . ($m[1] === 'users' ? 'users-' . $version : $m[1]) . '.json';
            $all = array_values(array_filter(
                json_decode(file_get_contents($file), true)[$m[1]],
                fn($thing) => !in_array($thing['sourcedId'], $drop, true)
            ));
            return json_encode([$m[1] => array_slice($all, (int) $m[2], $size)]);
        };
    }

    /**
     * With OAuth 2: a token by client credentials and HTTP Basic, every page of each collection, the students with
     * their homeroom, the teacher with an account as its tutor, the secret out of the entity; the night only after a
     * first time by hand; who is no longer there leaves.
     */
    public function test_sync_with_oauth2(): void {
        global $DB;
        $this->resetAfterTest();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $entityid = (int) $library->entityid;
        $teacher = $this->getDataGenerator()->create_user(['email' => 'hugo.lago@example.invalid']);
        oneroster_api::save($entityid, ['enabled' => 1, 'url' => 'https://roster.example.org/', 'version' => '1.2',
            'auth' => 'oauth2', 'clientid' => 'ayvurenda', 'secret' => 'made-up-secret', 'school' => 'sch1']);
        $this->assertStringNotContainsString('made-up-secret', $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]));
        $this->assertTrue(oneroster_api::connected($entityid));
        $asked = [];
        $drop = [];
        oneroster_api::$fetcher = $this->server('v1p2', $asked, $drop);

        $tried = oneroster_api::test($entityid);
        $this->assertSame(['readers' => 3, 'families' => 1, 'groups' => 1, 'schools' => 2], $tried);
        $this->assertSame(
            ['sch1' => 'Colegio Inventado Alfa', 'sch2' => 'Colegio Inventado Beta'],
            oneroster_api::settings($entityid)['schools']
        );
        $this->assertSame(0, $DB->count_records(self::T . 'reader', ['entityid' => $entityid]));
        [$method, $url, $headers, $body] = $asked[0];
        $this->assertSame(['POST', 'https://roster.example.org/token'], [$method, $url]);
        $this->assertContains('Authorization: Basic ' . base64_encode('ayvurenda:made-up-secret'), $headers);
        parse_str($body, $form);
        $this->assertSame(['grant_type' => 'client_credentials',
            'scope' => 'https://purl.imsglobal.org/spec/or/v1p2/scope/roster-core.readonly'], $form);
        // Six users, two at a time: four pages, the last one empty.
        $users = array_values(array_filter($asked, fn($a) => str_contains($a[1], '/users?')));
        $this->assertSame([
            'https://roster.example.org/ims/oneroster/rostering/v1p2/users?limit=100&offset=0',
            'https://roster.example.org/ims/oneroster/rostering/v1p2/users?limit=100&offset=2',
            'https://roster.example.org/ims/oneroster/rostering/v1p2/users?limit=100&offset=4',
            'https://roster.example.org/ims/oneroster/rostering/v1p2/users?limit=100&offset=6',
        ], array_column($users, 1));
        $this->assertContains('Authorization: Bearer made-up-token', $users[0][2]);

        $now = time();
        $this->assertFalse(oneroster_api::brought($entityid));
        $stats = oneroster_api::sync($entityid, $now, true);
        $this->assertTrue(oneroster_api::brought($entityid));
        $this->assertSame(['created' => 3, 'updated' => 0, 'linked' => 1, 'total' => 3, 'overlimit' => 0, 'left' => 0,
            'tutors' => 1], $stats);
        $leo = $DB->get_record(self::T . 'reader', ['entityid' => $entityid, 'firstname' => 'Leo'], '*', MUST_EXIST);
        $this->assertSame('3º A', $DB->get_field(self::T . 'member', 'groupname', ['readerid' => $leo->id]));
        $this->assertSame((int) $teacher->id, (int) $DB->get_field(self::T . 'tutor', 'userid', ['groupname' => '3º A']));

        // Ana is no longer on the server: the next night she leaves, and nobody comes twice.
        $drop = ['u1'];
        $again = oneroster_api::sync($entityid, $now + DAYSECS);
        $this->assertSame(1, $again['left']);
        $this->assertSame(0, $again['created']);
        $this->assertSame('left', $DB->get_field(self::T . 'reader', 'status', ['entityid' => $entityid, 'firstname' => 'Ana']));
        $this->assertSame(3, $DB->count_records(self::T . 'reader', ['entityid' => $entityid]));
    }

    /**
     * With the OAuth 1.0a signature of 1.1: no token, each request signed; a server that ignores where to start does
     * not go round forever; a server that answers something else says so.
     */
    public function test_sync_with_oauth1(): void {
        $this->resetAfterTest();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $entityid = (int) $library->entityid;
        oneroster_api::save($entityid, ['url' => 'https://sis.example.org/api/ims/oneroster/v1p1', 'version' => '1.1',
            'auth' => 'oauth1', 'clientid' => 'consumer', 'secret' => 'made-up-secret']);
        $asked = [];
        $drop = [];
        oneroster_api::$fetcher = $this->server('v1p1', $asked, $drop, 100);
        $stats = oneroster_api::sync($entityid);
        $this->assertSame(4, $stats['created']);
        $this->assertNotContains('POST', array_column($asked, 0));
        $this->assertStringStartsWith('https://sis.example.org/api/ims/oneroster/v1p1/users?limit=100&offset=0', $asked[0][1]);
        $this->assertMatchesRegularExpression(
            '/^Authorization: OAuth oauth_consumer_key="consumer", oauth_nonce="\w{24}", oauth_signature_method="HMAC-SHA256", /',
            $asked[0][2][1]
        );

        $count = 0;
        oneroster_api::$fetcher = function (string $method, string $url) use (&$count) {
            $count++;
            $name = preg_match('~/(\w+)\?~', $url, $m) ? $m[1] : '';
            return json_encode([$name => [['sourcedId' => 'always-the-same', 'title' => 'x']]]);
        };
        oneroster_api::files($entityid);
        $this->assertSame(8, $count);

        oneroster_api::$fetcher = fn() => json_encode(['imsx_codeMajor' => 'failure']);
        $this->expectException(\moodle_exception::class);
        oneroster_api::files($entityid);
    }

    /**
     * The secret is forgotten when an address or the way of entering changes, and kept when only the school does; a
     * token refused is said so; the night needs the plan's connectors.
     */
    public function test_secret_and_refusals(): void {
        $this->resetAfterTest();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $entityid = (int) $library->entityid;
        $base = ['url' => 'https://roster.example.org', 'auth' => 'oauth2', 'clientid' => 'c'];
        oneroster_api::save($entityid, $base + ['secret' => 's', 'enabled' => 1]);
        oneroster_api::save($entityid, ['school' => 'sch1', 'families' => 1]);
        $this->assertTrue(oneroster_api::settings($entityid)['hassecret']);
        $this->assertTrue(oneroster_api::enabled($entityid));
        oneroster_api::save($entityid, ['tokenurl' => 'https://elsewhere.example.net/token']);
        $this->assertFalse(oneroster_api::settings($entityid)['hassecret']);
        oneroster_api::save($entityid, ['secret' => 's']);
        oneroster_api::save($entityid, ['auth' => 'oauth1']);
        $this->assertFalse(oneroster_api::settings($entityid)['hassecret']);
        oneroster_api::save($entityid, ['secret' => 's', 'auth' => 'oauth2']);
        $this->assertTrue(oneroster_api::settings($entityid)['hassecret']);
        oneroster_api::save($entityid, ['url' => 'https://other.example.net']);
        $this->assertFalse(oneroster_api::connected($entityid));

        oneroster_api::save($entityid, ['secret' => 's']);
        oneroster_api::$fetcher = fn(string $method) => $method === 'POST' ? json_encode(['error' => 'invalid_client']) : null;
        try {
            oneroster_api::files($entityid);
            $this->fail('A refused token goes on');
        } catch (\moodle_exception $e) {
            $this->assertSame('onerosternotoken', $e->errorcode);
        }
        \local_oksigeniaayvurenda\local\plans::save($entityid, ['connectors' => 0]);
        $this->assertFalse(oneroster_api::enabled($entityid));
    }
}
