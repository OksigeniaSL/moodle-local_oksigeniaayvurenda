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
 * Where the LTI 1.3 library keeps what lasts between two requests.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local\lti;

use Packback\Lti1p3\Interfaces\ICache;

/**
 * In Moodle's application cache, not in the session: a platform's form posts the launch from another site, and the
 * browser may not send the session cookie with it. A nonce serves once.
 */
final class cache implements ICache {
    /**
     * The cache.
     *
     * @return \cache
     */
    private function store(): \cache {
        return \cache::make('local_oksigeniaayvurenda', 'lti');
    }

    /**
     * A key the cache takes.
     *
     * @param string $kind
     * @param string $key
     * @return string
     */
    private function key(string $kind, string $key): string {
        return $kind . '_' . sha1($key);
    }

    #[\Override]
    public function getLaunchData(string $key): ?array {
        $data = $this->store()->get($this->key('launch', $key));
        return is_array($data) ? $data : null;
    }

    #[\Override]
    public function cacheLaunchData(string $key, array $jwtbody): void {
        $this->store()->set($this->key('launch', $key), $jwtbody);
    }

    #[\Override]
    public function cacheNonce(string $nonce, string $state): void {
        $this->store()->set($this->key('nonce', $nonce), $state);
    }

    #[\Override]
    public function checkNonceIsValid(string $nonce, string $state): bool {
        $key = $this->key('nonce', $nonce);
        $valid = $this->store()->get($key) === $state;
        $this->store()->delete($key);
        return $valid;
    }

    #[\Override]
    public function cacheAccessToken(string $key, string $accesstoken): void {
        $this->store()->set($this->key('token', $key), $accesstoken);
    }

    #[\Override]
    public function getAccessToken(string $key): ?string {
        $token = $this->store()->get($this->key('token', $key));
        return is_string($token) ? $token : null;
    }

    #[\Override]
    public function clearAccessToken(string $key): void {
        $this->store()->delete($this->key('token', $key));
    }
}
