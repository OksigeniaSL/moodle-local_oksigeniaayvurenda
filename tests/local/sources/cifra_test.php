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
 * Tests for readers from Cifra.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\sources\cifra
 */
#[\PHPUnit\Framework\Attributes\CoversClass(cifra::class)]
final class cifra_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    #[\Override]
    protected function tearDown(): void {
        cifra::$fetcher = null;
        parent::tearDown();
    }

    /**
     * Through a bridge: readers with their group, matched with their account by email; two siblings with the family's
     * email are two readers, and that email does not give either an account; the key out of the entity; whoever is no
     * longer in the list leaves, with the history kept.
     */
    public function test_sync_through_bridge(): void {
        global $DB;
        $this->resetAfterTest();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $entityid = (int) $library->entityid;
        $user = $this->getDataGenerator()->create_user(['email' => 'irene.duna@example.invalid']);
        $this->getDataGenerator()->create_user(['email' => 'bruno.olmo@example.invalid']);
        cifra::save($entityid, ['enabled' => 1, 'url' => 'http://cifra-puente.invalid:8080/', 'mode' => 'key',
            'example' => 1, 'key' => 'made-up-key']);
        $this->assertStringNotContainsString('made-up-key', $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]));
        $this->assertTrue(cifra::settings($entityid)['haskey']);
        $people = json_decode(file_get_contents(__DIR__ . '/../../fixtures/cifra/alumnos.json'), true);
        $asked = [];
        cifra::$fetcher = function (string $method, string $url, array $headers) use (&$people, &$asked) {
            $asked[] = $method . ' ' . $url;
            $this->assertContains('X-Puente-Llave: made-up-key', $headers);
            return json_encode(str_contains($url, 'page=1&') ? $people : []);
        };
        $now = strtotime('2026-10-24 12:00:00 UTC');
        $stats = cifra::sync($entityid, $now);
        $this->assertSame(['GET http://cifra-puente.invalid:8080/ejemplo/v1/alumnos?page=1&per_page=100'], $asked);
        $this->assertSame(['created' => 4, 'updated' => 0, 'linked' => 1, 'total' => 4, 'left' => 0], $stats);
        $this->assertSame('teacher', $DB->get_field(self::T . 'reader', 'readertype', ['userid' => $user->id]));
        $bruno = $DB->get_record(self::T . 'reader', ['entityid' => $entityid, 'firstname' => 'Bruno'], '*', MUST_EXIST);
        $this->assertNull($bruno->userid);
        $this->assertSame('ESO 1º A', $DB->get_field(self::T . 'member', 'groupname', ['readerid' => $bruno->id]));
        $this->assertSame(1, $DB->count_records(self::T . 'reader', ['entityid' => $entityid, 'firstname' => 'Lía',
            'userid' => null]));

        // Noa is no longer in Cifra; a second night repeats nobody and she leaves.
        $people = array_values(array_filter($people, fn($p) => $p['id'] !== 7002));
        $again = cifra::sync($entityid, $now + DAYSECS);
        $this->assertSame(['created' => 0, 'updated' => 3, 'linked' => 1, 'total' => 3, 'left' => 1], $again);
        $this->assertSame('left', $DB->get_field(self::T . 'reader', 'status', ['entityid' => $entityid, 'lastname' => 'Lago']));
        $this->assertSame(4, $DB->count_records(self::T . 'reader', ['entityid' => $entityid]));
    }

    /**
     * Straight to Cifra: a token first, then the list with it.
     */
    public function test_people_with_oauth(): void {
        $this->resetAfterTest();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        cifra::save((int) $library->entityid, ['url' => 'https://cifra.invalid/api_publica', 'mode' => 'oauth',
            'clientid' => 'centre', 'secret' => 'made-up-secret']);
        cifra::$fetcher = function (string $method, string $url, array $headers, string $body) {
            if ($method === 'POST') {
                $this->assertSame('https://cifra.invalid/api_publica/oauth/token', $url);
                $this->assertStringContainsString('client_secret=made-up-secret', $body);
                return json_encode(['access_token' => 'made-up-token']);
            }
            $this->assertContains('Authorization: Bearer made-up-token', $headers);
            $this->assertStringStartsWith('https://cifra.invalid/api_publica/v1/alumnos?', $url);
            return json_encode(['data' => [['id' => 1, 'nombre' => 'Lía', 'apellido1' => 'Faro']]]);
        };
        $this->assertCount(1, cifra::people((int) $library->entityid));
        cifra::$fetcher = fn() => json_encode(['error' => 'invalid_client']);
        $this->expectException(\moodle_exception::class);
        cifra::people((int) $library->entityid);
    }
}
