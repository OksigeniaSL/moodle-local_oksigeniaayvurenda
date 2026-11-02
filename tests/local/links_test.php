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

use local_oksigeniaayvurenda\core\links as link_rules;

/**
 * Links that open from home, and passing a book to a class.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(link_rules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(links::class)]
final class links_test extends \advanced_testcase {
    /**
     * A DOI, however it comes; and what is not a DOI.
     */
    public function test_doi(): void {
        $this->assertSame('https://doi.org/10.1234/ab.c', link_rules::doi('10.1234/ab.c'));
        $this->assertSame('https://doi.org/10.1234/ab.c', link_rules::doi(' doi:10.1234/ab.c '));
        $this->assertSame('https://doi.org/10.1234/ab.c', link_rules::doi('https://dx.doi.org/10.1234/ab.c'));
        $this->assertNull(link_rules::doi('https://example.org/10.1234/ab.c'));
        $this->assertNull(link_rules::doi('javascript:alert(1)'));
    }

    /**
     * The prefix of a proxy: as it is after «url=», encoded after «qurl=», and never twice.
     */
    public function test_through(): void {
        $url = 'https://journals.example.org/a?b=1&c=2';
        $this->assertSame($url, link_rules::through($url, ''));
        $this->assertSame('https://p.example.org/login?url=' . $url, link_rules::through($url, 'https://p.example.org/login?url='));
        $this->assertSame(
            'https://p.example.org/login?qurl=' . rawurlencode($url),
            link_rules::through($url, 'https://p.example.org/login?qurl=')
        );
        $once = link_rules::through($url, 'https://p.example.org/login?url=');
        $this->assertSame($once, link_rules::through($once, 'https://p.example.org/login?url='));
    }

    /**
     * Only an address with https can be a prefix or a resolver.
     */
    public function test_usable(): void {
        $this->assertTrue(link_rules::usable('https://p.example.org/login?url='));
        $this->assertFalse(link_rules::usable('http://p.example.org/login?url='));
        $this->assertFalse(link_rules::usable('javascript:alert(1)'));
        $this->assertFalse(link_rules::usable('https://p.example.org/"onclick="'));
    }

    /**
     * What is asked of a resolver, and of Classroom and Teams.
     */
    public function test_addresses(): void {
        $this->assertNull(link_rules::openurl('', '9780000000002', 'Faro'));
        $this->assertNull(link_rules::openurl('https://r.example.org/', '', ''));
        $asked = link_rules::openurl('https://r.example.org/?a=1', '9780000000002', 'El faro', 'Ana');
        $this->assertStringStartsWith('https://r.example.org/?a=1&ctx_ver=Z39.88-2004&', $asked);
        $this->assertStringContainsString('rft.isbn=9780000000002', $asked);
        $this->assertStringContainsString('rft.btitle=El%20faro', $asked);
        $this->assertSame(
            'https://classroom.google.com/share?url=https%3A%2F%2Fa.example.org%2Fb%3Fc%3D1',
            link_rules::classroom('https://a.example.org/b?c=1')
        );
        $teams = link_rules::teams('https://a.example.org/b', str_repeat('ñ', 300));
        $this->assertStringStartsWith('https://teams.microsoft.com/share?href=https%3A%2F%2Fa.example.org%2Fb&msgText=', $teams);
        parse_str((string) parse_url($teams, PHP_URL_QUERY), $query);
        $this->assertSame(200, mb_strlen($query['msgText']));
    }

    /**
     * A link marked as subscribed goes through the proxy of its centre; any other, straight.
     */
    public function test_a_link_through_its_centre(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $ours = $gen->create_library();
        $theirs = $gen->create_library();
        libraries::update_entity((int) $ours->entityid, ['name' => 'Uno', 'linkproxy' => 'https://p.example.org/login?url=',
            'linkresolver' => 'http://no.example.org/']);
        $this->assertSame('https://p.example.org/login?url=', links::settings($ours)['proxy']);
        $this->assertSame('', links::settings($ours)['resolver']);
        $this->assertSame('', links::settings($theirs)['proxy']);
        $record = $gen->create_record(['entityid' => $ours->entityid, 'title' => 'Faro']);
        $fields = ['title' => 'Revista', 'licence' => 'rights', 'audience' => ['students']];
        $paid = digital::add_link($ours, (int) $record->id, 'doi:10.1234/ab', $fields + ['subscribed' => 1], 2);
        $free = digital::add_link($ours, (int) $record->id, 'https://a.example.org/b', $fields, 2);
        $item = fn($id) => $DB->get_record('local_oksigeniaayvurenda_digital', ['id' => $id]);
        $this->assertSame('https://doi.org/10.1234/ab', $item($paid)->url);
        $this->assertSame('https://p.example.org/login?url=https://doi.org/10.1234/ab', links::out($ours, $item($paid)));
        $this->assertSame('https://a.example.org/b', links::out($ours, $item($free)));
        // The other centre has no proxy: the same link, straight.
        $this->assertSame('https://doi.org/10.1234/ab', links::out($theirs, $item($paid)));
    }
}
