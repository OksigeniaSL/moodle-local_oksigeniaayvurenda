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
 * The platforms and deployments the LTI 1.3 library asks for.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local\lti;

use local_oksigeniaayvurenda\local\ltitool;
use Packback\Lti1p3\Interfaces\IDatabase;
use Packback\Lti1p3\Interfaces\ILtiDeployment;
use Packback\Lti1p3\Interfaces\ILtiRegistration;
use Packback\Lti1p3\LtiDeployment;
use Packback\Lti1p3\LtiRegistration;

/**
 * From the platforms each library registered, with this site's own key.
 */
final class store implements IDatabase {
    #[\Override]
    public function findRegistrationByIssuer(string $iss, ?string $clientid = null): ?ILtiRegistration {
        $platform = ltitool::platform_by_issuer($iss, $clientid);
        if (!$platform) {
            return null;
        }
        [$kid, $privatekey] = ltitool::keys();
        return LtiRegistration::new([
            'issuer' => $platform->issuer,
            'clientId' => $platform->clientid,
            'authLoginUrl' => $platform->authloginurl,
            'authTokenUrl' => (string) $platform->authtokenurl,
            'keySetUrl' => $platform->keyseturl,
            'kid' => $kid,
            'toolPrivateKey' => $privatekey,
        ]);
    }

    #[\Override]
    public function findDeployment(string $iss, string $deploymentid, ?string $clientid = null): ?ILtiDeployment {
        $platform = ltitool::platform_by_issuer($iss, $clientid);
        if (!$platform || !ltitool::accepts($platform, $deploymentid)) {
            return null;
        }
        return LtiDeployment::new($deploymentid);
    }
}
