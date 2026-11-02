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

namespace local_oksigeniaayvurenda\core;

/**
 * Files encrypted in the open format of age (https://age-encryption.org/v1), for a public key of X25519: whoever has
 * the matching secret key opens them with this class, with the age program or with any tool that speaks the format,
 * also without Ayvurenda. The server that encrypts only needs the public key, so it cannot read what it keeps.
 *
 * Plain PHP with sodium, no Moodle.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class age {
    /** @var string First line of every file. */
    private const VERSION = 'age-encryption.org/v1';

    /** @var int Bytes of plain text in each chunk of the payload. */
    private const CHUNK = 65536;

    /** @var string The characters of Bech32. */
    private const CHARSET = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';

    /**
     * A new pair of keys.
     *
     * @return array public (age1…) and secret (AGE-SECRET-KEY-1…).
     */
    public static function keypair(): array {
        $secret = random_bytes(SODIUM_CRYPTO_SCALARMULT_SCALARBYTES);
        return [
            'public' => self::bech32('age', sodium_crypto_scalarmult_base($secret)),
            'secret' => strtoupper(self::bech32('age-secret-key-', $secret)),
        ];
    }

    /**
     * The public key of a secret one.
     *
     * @param string $identity AGE-SECRET-KEY-1…
     * @return string age1…
     */
    public static function public_of(string $identity): string {
        return self::bech32('age', sodium_crypto_scalarmult_base(self::unbech32('age-secret-key-', $identity)));
    }

    /**
     * Whether some text is a public key of age.
     *
     * @param string $recipient
     * @return bool
     */
    public static function is_recipient(string $recipient): bool {
        try {
            $key = self::unbech32('age', $recipient);
            if (strlen($key) !== 32) {
                return false;
            }
            // A key of low order makes the sodium library refuse to encrypt, every night: it is no key.
            sodium_crypto_scalarmult(random_bytes(32), $key);
            return true;
        } catch (\InvalidArgumentException | \SodiumException $e) {
            return false;
        }
    }

    /**
     * Encrypts a file for a public key.
     *
     * @param string $in Path of the plain file.
     * @param string $out Path of the encrypted one.
     * @param string $recipient age1…
     */
    public static function encrypt(string $in, string $out, string $recipient): void {
        $public = self::unbech32('age', $recipient);
        $filekey = random_bytes(16);
        $ephemeral = random_bytes(SODIUM_CRYPTO_SCALARMULT_SCALARBYTES);
        $share = sodium_crypto_scalarmult_base($ephemeral);
        $shared = sodium_crypto_scalarmult($ephemeral, $public);
        $wrap = hash_hkdf('sha256', $shared, 32, 'age-encryption.org/v1/X25519', $share . $public);
        $body = sodium_crypto_aead_chacha20poly1305_ietf_encrypt($filekey, '', str_repeat("\0", 12), $wrap);
        $header = self::VERSION . "\n-> X25519 " . self::b64($share) . "\n" . self::wrap(self::b64($body)) . "---";
        $mac = hash_hmac('sha256', $header, hash_hkdf('sha256', $filekey, 32, 'header', ''), true);
        $nonce = random_bytes(16);
        $key = hash_hkdf('sha256', $filekey, 32, 'payload', $nonce);
        $from = fopen($in, 'rb');
        $to = fopen($out, 'wb');
        if (!$from || !$to) {
            throw new \InvalidArgumentException('The files cannot be opened.');
        }
        fwrite($to, $header . ' ' . self::b64($mac) . "\n" . $nonce);
        $counter = 0;
        $chunk = self::read($from, self::CHUNK);
        while (true) {
            $next = self::read($from, self::CHUNK);
            $last = $next === '';
            fwrite($to, sodium_crypto_aead_chacha20poly1305_ietf_encrypt($chunk, '', self::nonce($counter, $last), $key));
            if ($last) {
                break;
            }
            $chunk = $next;
            $counter++;
        }
        fclose($from);
        fclose($to);
        sodium_memzero($filekey);
        sodium_memzero($ephemeral);
    }

    /**
     * Opens a file encrypted for the public key of a secret one.
     *
     * @param string $in Path of the encrypted file.
     * @param string $out Path of the plain one.
     * @param string $identity AGE-SECRET-KEY-1…
     * @throws \InvalidArgumentException When it is not for this key, or it was changed.
     */
    public static function decrypt(string $in, string $out, string $identity): void {
        $secret = self::unbech32('age-secret-key-', $identity);
        $public = sodium_crypto_scalarmult_base($secret);
        $from = fopen($in, 'rb');
        if (!$from) {
            throw new \InvalidArgumentException('The file cannot be opened.');
        }
        $lines = [];
        $filekey = null;
        $stanza = null;
        while (($line = fgets($from, 4096)) !== false) {
            $line = rtrim($line, "\n");
            if (str_starts_with($line, '---')) {
                break;
            }
            $lines[] = $line;
            if (str_starts_with($line, '-> ')) {
                $stanza = ['args' => explode(' ', substr($line, 3)), 'body' => ''];
            } else if ($stanza !== null) {
                $stanza['body'] .= $line;
                if (strlen($line) < 64) {
                    // The body ends with a line shorter than a full one: try it.
                    if ($filekey === null && $stanza['args'][0] === 'X25519' && count($stanza['args']) === 2) {
                        $share = self::unb64($stanza['args'][1]);
                        $shared = sodium_crypto_scalarmult($secret, $share);
                        $wrap = hash_hkdf('sha256', $shared, 32, 'age-encryption.org/v1/X25519', $share . $public);
                        $key = sodium_crypto_aead_chacha20poly1305_ietf_decrypt(
                            self::unb64($stanza['body']),
                            '',
                            str_repeat("\0", 12),
                            $wrap
                        );
                        $filekey = $key === false ? null : $key;
                    }
                    $stanza = null;
                }
            }
        }
        if (($lines[0] ?? '') !== self::VERSION || !isset($line) || $line === false || $filekey === null) {
            fclose($from);
            throw new \InvalidArgumentException('This file is not for this key.');
        }
        $header = implode("\n", $lines) . "\n---";
        $mac = hash_hmac('sha256', $header, hash_hkdf('sha256', $filekey, 32, 'header', ''), true);
        if (!hash_equals($mac, self::unb64(substr($line, 4)))) {
            fclose($from);
            throw new \InvalidArgumentException('The header was changed.');
        }
        $nonce = self::read($from, 16);
        $key = hash_hkdf('sha256', $filekey, 32, 'payload', $nonce);
        $to = fopen($out, 'wb');
        $counter = 0;
        $chunk = self::read($from, self::CHUNK + 16);
        while (true) {
            $next = self::read($from, self::CHUNK + 16);
            $last = $next === '';
            $plain = sodium_crypto_aead_chacha20poly1305_ietf_decrypt($chunk, '', self::nonce($counter, $last), $key);
            if ($plain === false) {
                fclose($from);
                fclose($to);
                throw new \InvalidArgumentException('The file was changed or cut.');
            }
            fwrite($to, $plain);
            if ($last) {
                break;
            }
            $chunk = $next;
            $counter++;
        }
        fclose($from);
        fclose($to);
    }

    /**
     * Up to some bytes of a file, however the stream gives them.
     *
     * @param resource $handle
     * @param int $length
     * @return string
     */
    private static function read($handle, int $length): string {
        $data = '';
        while (strlen($data) < $length && !feof($handle)) {
            $part = fread($handle, $length - strlen($data));
            if ($part === false || $part === '') {
                break;
            }
            $data .= $part;
        }
        return $data;
    }

    /**
     * The nonce of a chunk: its number in eleven bytes, and whether it is the last one.
     *
     * @param int $counter
     * @param bool $last
     * @return string
     */
    private static function nonce(int $counter, bool $last): string {
        return "\0\0\0" . pack('J', $counter) . ($last ? "\x01" : "\x00");
    }

    /**
     * Base64 without padding, as the format writes it.
     *
     * @param string $data
     * @return string
     */
    private static function b64(string $data): string {
        return rtrim(base64_encode($data), '=');
    }

    /**
     * Back from base64 without padding.
     *
     * @param string $text
     * @return string
     */
    private static function unb64(string $text): string {
        $data = base64_decode($text, true);
        if ($data === false || self::b64($data) !== $text) {
            throw new \InvalidArgumentException('Not the base64 of the format.');
        }
        return $data;
    }

    /**
     * A stanza body in lines of 64 characters; the last one always shorter (empty if it would be full).
     *
     * @param string $text
     * @return string
     */
    private static function wrap(string $text): string {
        $lines = str_split($text, 64);
        if ($text === '' || strlen(end($lines)) === 64) {
            $lines[] = '';
        }
        return implode("\n", $lines) . "\n";
    }

    /**
     * Bech32 of some bytes (as age writes its keys, without the length limit of BIP 173).
     *
     * @param string $hrp
     * @param string $data
     * @return string
     */
    private static function bech32(string $hrp, string $data): string {
        $values = self::convert(array_values(unpack('C*', $data)), 8, 5, true);
        $polymod = self::polymod(array_merge(self::expand($hrp), $values, [0, 0, 0, 0, 0, 0])) ^ 1;
        for ($i = 0; $i < 6; $i++) {
            $values[] = ($polymod >> (5 * (5 - $i))) & 31;
        }
        return $hrp . '1' . implode('', array_map(fn($v) => self::CHARSET[$v], $values));
    }

    /**
     * The bytes of a Bech32 text with the given prefix.
     *
     * @param string $hrp
     * @param string $text
     * @return string
     * @throws \InvalidArgumentException When it is not one.
     */
    private static function unbech32(string $hrp, string $text): string {
        $text = trim($text);
        if ($text !== strtolower($text) && $text !== strtoupper($text)) {
            throw new \InvalidArgumentException('Mixed case.');
        }
        $text = strtolower($text);
        $at = strrpos($text, '1');
        if ($at === false || substr($text, 0, $at) !== $hrp || strlen($text) - $at < 7) {
            throw new \InvalidArgumentException('Not a key of this kind.');
        }
        $values = [];
        foreach (str_split(substr($text, $at + 1)) as $char) {
            $value = strpos(self::CHARSET, $char);
            if ($value === false) {
                throw new \InvalidArgumentException('Not Bech32.');
            }
            $values[] = $value;
        }
        if (self::polymod(array_merge(self::expand($hrp), $values)) !== 1) {
            throw new \InvalidArgumentException('Wrong checksum.');
        }
        $bytes = self::convert(array_slice($values, 0, -6), 5, 8, false);
        return pack('C*', ...$bytes);
    }

    /**
     * The prefix, spread for the checksum.
     *
     * @param string $hrp
     * @return int[]
     */
    private static function expand(string $hrp): array {
        $chars = array_map('ord', str_split($hrp));
        return array_merge(array_map(fn($c) => $c >> 5, $chars), [0], array_map(fn($c) => $c & 31, $chars));
    }

    /**
     * The checksum of Bech32.
     *
     * @param int[] $values
     * @return int
     */
    private static function polymod(array $values): int {
        $generator = [0x3b6a57b2, 0x26508e6d, 0x1ea119fa, 0x3d4233dd, 0x2a1462b3];
        $check = 1;
        foreach ($values as $value) {
            $top = $check >> 25;
            $check = (($check & 0x1ffffff) << 5) ^ $value;
            for ($i = 0; $i < 5; $i++) {
                if (($top >> $i) & 1) {
                    $check ^= $generator[$i];
                }
            }
        }
        return $check;
    }

    /**
     * Groups of bits from one width to another.
     *
     * @param int[] $values
     * @param int $from
     * @param int $to
     * @param bool $pad
     * @return int[]
     * @throws \InvalidArgumentException When the padding is wrong.
     */
    private static function convert(array $values, int $from, int $to, bool $pad): array {
        $acc = 0;
        $bits = 0;
        $out = [];
        $max = (1 << $to) - 1;
        $keep = (1 << ($from + $to - 1)) - 1;
        foreach ($values as $value) {
            $acc = (($acc << $from) | $value) & $keep;
            $bits += $from;
            while ($bits >= $to) {
                $bits -= $to;
                $out[] = ($acc >> $bits) & $max;
            }
        }
        if ($pad && $bits) {
            $out[] = ($acc << ($to - $bits)) & $max;
        } else if (!$pad && ($bits >= $from || (($acc << ($to - $bits)) & $max))) {
            throw new \InvalidArgumentException('Wrong padding.');
        }
        return $out;
    }
}
