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
 * The cookie with which the LTI 1.3 library checks that a launch comes from the login it started.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local\lti;

use Packback\Lti1p3\Interfaces\ICookie;

/**
 * Over HTTPS it goes to other sites too (SameSite=None), since the platform posts the launch from its own.
 */
final class cookie implements ICookie {
    /** @var array Cookies set during this request, for the tests. */
    public static array $set = [];

    #[\Override]
    public function getCookie(string $name): ?string {
        return self::$set[$name] ?? (isset($_COOKIE[$name]) ? (string) $_COOKIE[$name] : null);
    }

    #[\Override]
    public function setCookie(string $name, string $value, int $exp = 3600, array $options = []): void {
        self::$set[$name] = $value;
        if (PHPUNIT_TEST || headers_sent()) {
            return;
        }
        $secure = is_https();
        setcookie($name, $value, ['expires' => time() + $exp, 'path' => '/', 'secure' => $secure, 'httponly' => true,
            'samesite' => $secure ? 'None' : 'Lax']);
    }
}
