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

namespace local_oksigeniaayvurenda\core\readers;

/**
 * Tests for the answers of a OneRoster REST API.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\core\readers\oneroster_rest
 */
#[\PHPUnit\Framework\Attributes\CoversClass(oneroster_rest::class)]
final class oneroster_rest_test extends \basic_testcase {
    /**
     * The things the made-up API gives (the same people as the made-up export).
     *
     * @param string $version v1p1 or v1p2.
     * @return array Collection => its things.
     */
    private function things(string $version): array {
        $dir = __DIR__ . '/../../fixtures/oneroster/api/';
        $things = [];
        foreach (
            ['users' => 'users-' . $version, 'orgs' => 'orgs', 'classes' => 'classes',
            'enrollments' => 'enrollments'] as $c => $f
        ) {
            $things[$c] = oneroster_rest::page(file_get_contents($dir . $f . '.json'), $c);
        }
        return $things;
    }

    /**
     * What the API gives in 1.2 (roles in each user) and in 1.1 (one role and its orgs) are the same readers, tutors
     * and schools as the files of the export.
     */
    public function test_same_as_the_files(): void {
        $now = strtotime('2026-10-26 12:00:00 UTC');
        $files = [];
        foreach (glob(__DIR__ . '/../../fixtures/oneroster/*.csv') as $path) {
            $files[basename($path)] = file_get_contents($path);
        }
        foreach (['sch1', ''] as $school) {
            $expected = oneroster::readers($files, $school, true, $now);
            foreach (['v1p2', 'v1p1'] as $version) {
                $found = oneroster::readers(oneroster_rest::files($this->things($version)), $school, true, $now);
                foreach (['readers', 'gone', 'tutors', 'schools'] as $part) {
                    $this->assertEquals($expected[$part], $found[$part], $version . ' ' . $part . ' ' . $school);
                }
                $this->assertSame('delta', $found['mode']);
            }
        }
    }

    /**
     * The preferred names of 1.2 come by either name; a text where a reference should be is nothing.
     */
    public function test_names_and_odd_things(): void {
        $files = oneroster_rest::files(['users' => [
            ['sourcedId' => 'x1', 'givenName' => 'Alejandro', 'familyName' => 'Mar', 'preferredFirstName' => 'Álex',
                'enabledUser' => 'true', 'roles' => [['roleType' => 'primary', 'role' => 'student', 'org' => 'sch1']]],
            ['sourcedId' => '', 'givenName' => 'Nadie'],
            ['givenName' => 'Tampoco'],
        ]]);
        $this->assertCount(1, $files['users.csv']);
        $this->assertSame('Álex', $files['users.csv'][0]['preferredGivenName']);
        $this->assertSame('true', $files['users.csv'][0]['enabledUser']);
        $this->assertSame([['userSourcedId' => 'x1', 'role' => 'student', 'orgSourcedId' => '', 'roleType' => 'primary',
            'status' => '', 'endDate' => '']], $files['roles.csv']);
        $this->assertNull(oneroster_rest::page('{"errors": []}', 'users'));
        $this->assertNull(oneroster_rest::page('<html>', 'users'));
        $this->assertSame([], oneroster_rest::page('{"users": []}', 'users'));
    }

    /**
     * Where each collection is: after the server's address, or after the service's when it was given whole.
     */
    public function test_url(): void {
        $this->assertSame(
            'https://roster.example.org/ims/oneroster/rostering/v1p2/users',
            oneroster_rest::url('https://roster.example.org/', '1.2', 'users')
        );
        $this->assertSame(
            'https://roster.example.org/sis/ims/oneroster/v1p1/enrollments',
            oneroster_rest::url('https://roster.example.org/sis', '1.1', 'enrollments')
        );
        $this->assertSame(
            'https://roster.example.org/api/ims/oneroster/v1p1/classes',
            oneroster_rest::url('https://roster.example.org/api/ims/oneroster/v1p1/', '1.2', 'classes')
        );
        $this->assertStringEndsWith('/v1p1/scope/roster-core.readonly', oneroster_rest::scope('1.1'));
        $this->assertStringEndsWith('/v1p2/scope/roster-core.readonly', oneroster_rest::scope('1.2'));
    }

    /**
     * The OAuth 1.0a signature, the same as another implementation gives (oauthlib, with these very values): the host in
     * lower case, the port that is not the usual one, the query among the parameters and the secret with its symbols.
     */
    public function test_oauth1(): void {
        $this->assertSame(
            'Authorization: OAuth oauth_consumer_key="clave%20del%20centro", oauth_nonce="abc123", '
                . 'oauth_signature_method="HMAC-SHA256", oauth_timestamp="1762100000", oauth_version="1.0", '
                . 'oauth_signature="QAKhAMbaqhq3kcCODDOyGyukoqPb9OxpAttJePVSD6w%3D"',
            oneroster_rest::oauth1(
                'GET',
                'https://Roster.Example.org:8443/ims/oneroster/v1p1/users?limit=100&offset=200&filter=status%3D%27active%27',
                'clave del centro',
                'secreto&raro/1',
                'abc123',
                1762100000
            )
        );
    }
}
