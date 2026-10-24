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

use Firebase\JWT\JWT;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Packback\Lti1p3\JwksEndpoint;
use Packback\Lti1p3\LtiOidcLogin;

/**
 * Tests for the library as an LTI 1.3 tool, against a made-up platform that signs with its own key.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\ltitool
 */
#[\PHPUnit\Framework\Attributes\CoversClass(ltitool::class)]
final class ltitool_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var string The made-up platform. */
    private const ISS = 'https://platform.invalid';

    #[\Override]
    protected function tearDown(): void {
        ltitool::$client = null;
        lti\cookie::$set = [];
        parent::tearDown();
    }

    /**
     * The made-up platform's key, its key set served to the tool, and a login started by it.
     *
     * @return array private key, state and nonce.
     */
    private function platform(): array {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $private);
        $jwks = JwksEndpoint::new(['pk1' => $private])->getPublicJwks();
        $responses = array_fill(0, 4, new Response(200, ['Content-Type' => 'application/json'], json_encode($jwks)));
        ltitool::$client = new \GuzzleHttp\Client(['handler' => HandlerStack::create(new MockHandler($responses))]);
        $redirect = LtiOidcLogin::new(new lti\store(), new lti\cache(), new lti\cookie())
            ->getRedirectUrl(ltitool::urls()['launch'], ['iss' => self::ISS, 'login_hint' => 'u-7', 'client_id' => 'tool-1',
                'lti_deployment_id' => 'dep-1']);
        parse_str((string) parse_url($redirect, PHP_URL_QUERY), $query);
        $this->assertStringStartsWith('https://platform.invalid/auth', $redirect);
        return [$private, $query['state'], $query['nonce']];
    }

    /**
     * A launch as the platform signs it.
     *
     * @param string $private
     * @param string $nonce
     * @param array $more Claims to add or change.
     * @return string
     */
    private function token(string $private, string $nonce, array $more = []): string {
        $lti = 'https://purl.imsglobal.org/spec/lti/claim/';
        $claims = $more + [
            'iss' => self::ISS, 'aud' => 'tool-1', 'sub' => 'u-7', 'iat' => time(), 'exp' => time() + 300, 'nonce' => $nonce,
            'given_name' => 'Elio', 'family_name' => 'Pino Vela', 'email' => 'elio.pino@example.invalid',
            $lti . 'deployment_id' => 'dep-1', $lti . 'message_type' => 'LtiResourceLinkRequest', $lti . 'version' => '1.3.0',
            $lti . 'roles' => ['http://purl.imsglobal.org/vocab/lis/v2/membership#Learner'],
            $lti . 'resource_link' => ['id' => 'rl-1'], $lti . 'target_link_uri' => ltitool::urls()['launch'],
        ];
        return JWT::encode($claims, $private, 'RS256', 'pk1');
    }

    /**
     * A student comes from the platform: the launch is checked, the account made and bound, the reader made, and the
     * book the link names opens. The nonce serves once; a deployment not written is refused.
     */
    public function test_launch(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $generator->create_library();
        $entityid = (int) $library->entityid;
        $record = $generator->create_record(['entityid' => $entityid, 'title' => 'El faro del norte']);
        ltitool::save_platform($entityid, ['name' => 'Aula virtual', 'issuer' => self::ISS, 'clientid' => 'tool-1',
            'authloginurl' => 'https://platform.invalid/auth', 'keyseturl' => 'https://platform.invalid/jwks',
            'deployments' => "dep-1\n"]);
        $jwks = ltitool::jwks();
        $this->assertSame(get_config('local_oksigeniaayvurenda', 'ltikid'), $jwks['keys'][0]['kid']);
        $this->assertNotEmpty($jwks['keys'][0]['n']);

        [$private, $state, $nonce] = $this->platform();
        $custom = ['https://purl.imsglobal.org/spec/lti/claim/custom' => ['record' => (string) $record->id]];
        $launch = ltitool::launch(['id_token' => $this->token($private, $nonce, $custom), 'state' => $state]);
        $this->assertTrue($launch->isResourceLaunch());
        $data = $launch->getLaunchData();
        // What complete_login() does, without the session PHPUnit has not got.
        $this->setUser(get_auth_plugin('lti')->find_or_create_user_from_launch($data));
        $this->assertSame('lti', $USER->auth);
        $this->assertSame('Elio', $USER->firstname);
        $url = ltitool::arrive($data, (int) $USER->id);
        $this->assertStringContainsString('/record.php', $url->out(false));
        $this->assertSame((string) $record->id, $url->get_param('id'));
        $reader = $DB->get_record(self::T . 'reader', ['entityid' => $entityid, 'userid' => $USER->id], '*', MUST_EXIST);
        $this->assertSame('student', $reader->readertype);
        $this->assertNull($reader->firstname);

        // Again with the same nonce: refused.
        try {
            ltitool::launch(['id_token' => $this->token($private, $nonce), 'state' => $state]);
            $this->fail('A nonce served twice');
        } catch (\Packback\Lti1p3\LtiException $e) {
            $this->assertNotEmpty($e->getMessage());
        }
        // A deployment the library did not write: refused.
        [$private, $state, $nonce] = $this->platform();
        $other = ['https://purl.imsglobal.org/spec/lti/claim/deployment_id' => 'dep-2'];
        try {
            ltitool::launch(['id_token' => $this->token($private, $nonce, $other), 'state' => $state]);
            $this->fail('A deployment not written');
        } catch (\Packback\Lti1p3\LtiException $e) {
            $this->assertNotEmpty($e->getMessage());
        }
        // A second visit lands in the library and keeps the same reader.
        $this->assertSame((int) $reader->id, ltitool::reader($entityid, (int) $USER->id, 'teacher'));
        $home = ltitool::arrive($data, (int) $USER->id);
        $this->assertStringContainsString('/record.php', $home->out(false));
    }

    /**
     * Roles give the reader type; platforms are checked and one platform opens only one library.
     */
    public function test_roles_and_platforms(): void {
        $this->resetAfterTest();
        $roles = 'https://purl.imsglobal.org/spec/lti/claim/roles';
        $lis = 'http://purl.imsglobal.org/vocab/lis/v2/';
        $this->assertSame('teacher', ltitool::reader_type([$roles => [$lis . 'membership#Instructor']]));
        $this->assertSame('family', ltitool::reader_type([$roles => [$lis . 'membership#Mentor']]));
        $this->assertSame('staff', ltitool::reader_type([$roles => [$lis . 'institution/person#Staff']]));
        $this->assertSame('student', ltitool::reader_type([$roles => []]));
        $generator = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $one = (int) $generator->create_library()->entityid;
        $two = (int) $generator->create_library()->entityid;
        $data = ['name' => 'Aula', 'issuer' => self::ISS, 'clientid' => 'c', 'authloginurl' => 'https://platform.invalid/a',
            'keyseturl' => 'https://platform.invalid/k'];
        $id = ltitool::save_platform($one, $data);
        $this->assertTrue(ltitool::accepts(ltitool::platform_by_issuer(self::ISS), 'any'));
        try {
            ltitool::save_platform($two, $data);
            $this->fail('One platform, two libraries');
        } catch (\moodle_exception $e) {
            $this->assertSame('ltitaken', $e->errorcode);
        }
        try {
            ltitool::save_platform($one, ['keyseturl' => 'ftp://x'] + $data, $id);
            $this->fail('Not an address');
        } catch (\moodle_exception $e) {
            $this->assertSame('ltinoturl', $e->errorcode);
        }
        ltitool::delete_platform($two, $id);
        $this->assertCount(1, ltitool::platforms($one));
        ltitool::delete_platform($one, $id);
        $this->assertSame([], ltitool::platforms($one));
    }
}
