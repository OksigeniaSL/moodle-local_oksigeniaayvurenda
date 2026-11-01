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
 * The signature of S3 requests, against AWS's published example.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\core\s3
 */
#[\PHPUnit\Framework\Attributes\CoversClass(s3::class)]
final class s3_test extends \basic_testcase {
    /**
     * The GET object example of the AWS documentation gives its signature.
     */
    public function test_aws_example(): void {
        $headers = s3::headers(
            'GET',
            'https://examplebucket.s3.amazonaws.com/test.txt',
            'us-east-1',
            'AKIAIOSFODNN7EXAMPLE',
            'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
            hash('sha256', ''),
            gmmktime(0, 0, 0, 5, 24, 2013),
            ['Range' => 'bytes=0-9']
        );
        $this->assertSame('AWS4-HMAC-SHA256 Credential=AKIAIOSFODNN7EXAMPLE/20130524/us-east-1/s3/aws4_request, '
            . 'SignedHeaders=host;range;x-amz-content-sha256;x-amz-date, '
            . 'Signature=f0e8bdb87c964420e857bd35b5d6ed310bd44f0170aba48dd91039c6036bdb41', $headers['authorization']);
    }

    /**
     * The list of a bucket signs its query.
     */
    public function test_a_query(): void {
        $headers = s3::headers(
            'GET',
            'https://examplebucket.s3.amazonaws.com/?max-keys=2&prefix=J',
            'us-east-1',
            'AKIAIOSFODNN7EXAMPLE',
            'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
            hash('sha256', ''),
            gmmktime(0, 0, 0, 5, 24, 2013)
        );
        $this->assertStringEndsWith(
            'Signature=34b48302e7b5fa45bde8084f4b7868a86f0a534bc59db6670ed5711ef69dc6f7',
            $headers['authorization']
        );
    }
}
