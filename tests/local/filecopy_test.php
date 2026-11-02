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

/**
 * A centre's files in their own copy, leaving the server every week and coming back after its daily copy; voices stay.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(filecopy::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(recover::class)]
final class filecopy_test extends \advanced_testcase {
    /** @var string[] What a place has, name => what was sent. */
    private array $folder = [];

    /**
     * A little PNG.
     *
     * @return string
     */
    private function png(): string {
        $image = imagecreatetruecolor(4, 4);
        ob_start();
        imagepng($image);
        return (string) ob_get_clean();
    }

    /**
     * A file of the plugin.
     *
     * @param string $area
     * @param int $item
     * @param string $name
     * @param string $content
     */
    private function file(string $area, int $item, string $name, string $content): void {
        get_file_storage()->create_file_from_string(['contextid' => \context_system::instance()->id,
            'component' => 'local_oksigeniaayvurenda', 'filearea' => $area, 'itemid' => $item, 'filepath' => '/',
            'filename' => $name], $content);
    }

    /**
     * What a centre has in an area, name => content.
     *
     * @param string $area
     * @param int $item
     * @return string[]
     */
    private function has(string $area, int $item): array {
        $found = [];
        $context = \context_system::instance();
        foreach (get_file_storage()->get_area_files($context->id, 'local_oksigeniaayvurenda', $area, $item, '', false) as $f) {
            $found[$f->get_filename()] = $f->get_content();
        }
        return $found;
    }

    /**
     * A file, a link, a voice, a cover, a drawing, a picture and a character go; everything but the voice comes back, each
     * with the new ids; doing it again changes nothing; and the files of a centre do not enter another.
     */
    public function test_the_files_go_and_come_back(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $dg = $this->getDataGenerator();
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $dg->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library(['slug' => 'central']);
        $entity = (int) $library->entityid;
        $record = $gen->create_record(['entityid' => $entity, 'title' => 'Platero y yo']);
        $gen->create_item(['libraryid' => $library->id, 'recordid' => $record->id, 'barcode' => '100000L']);
        $user = $dg->create_user();
        $reader = $gen->create_reader(['entityid' => $entity, 'userid' => $user->id, 'cardcode' => '10000H',
            'firstname' => 'Ana', 'lastname' => 'Inventada']);
        $dir = make_request_directory();
        file_put_contents($dir . '/libro.pdf', '%PDF-1.4 un libro inventado');
        $book = digital::add_file($library, (int) $record->id, $dir . '/libro.pdf', 'libro.pdf', ['title' => 'Libro',
            'licence' => 'pd'], 2);
        digital::add_link($library, (int) $record->id, 'https://ejemplo.org/platero', ['title' => 'Enlace'], 2);
        file_put_contents($dir . '/voz.webm', 'una voz inventada');
        voices::record($library, (int) $record->id, (int) $reader->id, (int) $user->id, $dir . '/voz.webm', 'Capítulo 1');
        $this->file('cover', (int) $record->id, 'cover.jpg', 'una portada');
        $this->file('libraryimage', (int) $library->id, 'image.png', 'una imagen');
        $this->file('portrait', $entity, 'herald-saluda.webp', 'un personaje');
        $review = reviews::write($entity, (int) $record->id, (int) $reader->id, '', null, false, true);
        $this->assertTrue(reviews::draw($review, $this->png()));
        reviews::speak($review, $dir . '/voz.webm');

        $copy = filecopy::make($entity);
        $this->assertEquals($copy->get_id(), filecopy::latest($entity)->get_id());
        $path = $dir . '/ficheros.zip';
        $copy->copy_content_to($path);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path, \ZipArchive::RDONLY));
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $this->assertContains('cover/' . $record->id . '/cover.jpg', $names);
        $this->assertContains('digital/' . $book . '/libro.pdf', $names);
        $this->assertContains('ficheros.csv', $names);
        $this->assertContains('LEEME.txt', $names);
        $this->assertEmpty(preg_grep('/voz|webm|reviewvoice/', $names));
        $this->assertStringNotContainsString('Capítulo 1', (string) $zip->getFromName('digital.csv'));
        $zip->close();

        $daily = $dir . '/copia.zip';
        copies::make($entity)->copy_content_to($daily);
        $back = recover::restore($daily, (int) get_admin()->id);
        $new = (int) $back->entityid;
        $this->assertNotSame($new, $entity);
        $this->assertTrue(recover::recovered($new));
        $newrecord = (int) $DB->get_field('local_oksigeniaayvurenda_record', 'id', ['entityid' => $new, 'title' => 'Platero y yo']);
        $this->assertSame(1, $DB->count_records('local_oksigeniaayvurenda_digital', ['entityid' => $new]));
        $this->assertSame(1, $DB->count_records('local_oksigeniaayvurenda_review', ['entityid' => $new]));

        // Not into another centre.
        $this->assertNull(recover::bring_files($entity, $path, 2));
        $other = $gen->create_library(['slug' => 'otra']);
        $this->assertNull(recover::bring_files((int) $other->entityid, $path, 2));

        $done = recover::bring_files($new, $path, (int) get_admin()->id);
        $this->assertSame(['files' => 5, 'digital' => 1], $done);
        $this->assertSame(['cover.jpg' => 'una portada'], $this->has('cover', $newrecord));
        $this->assertSame(['image.png' => 'una imagen'], $this->has('libraryimage', (int) $back->id));
        $this->assertSame(['herald-saluda.webp' => 'un personaje'], $this->has('portrait', $new));
        $newbook = $DB->get_record('local_oksigeniaayvurenda_digital', ['entityid' => $new, 'kind' => 'file'], '*', MUST_EXIST);
        $this->assertSame((int) $newrecord, (int) $newbook->recordid);
        $this->assertSame(['libro.pdf' => '%PDF-1.4 un libro inventado'], $this->has('digital', (int) $newbook->id));
        $newreview = (int) $DB->get_field('local_oksigeniaayvurenda_review', 'id', ['entityid' => $new]);
        $this->assertNotNull(reviews::drawing($newreview));
        $this->assertNull(reviews::voice($newreview));
        $this->assertSame(0, $DB->count_records('local_oksigeniaayvurenda_digital', ['entityid' => $new, 'kind' => 'voice']));

        $this->assertSame(['files' => 0, 'digital' => 0], recover::bring_files($new, $path, 2));
        $this->assertCount(1, $this->has('digital', (int) $newbook->id));
        $this->assertSame(2, $DB->count_records('local_oksigeniaayvurenda_digital', ['entityid' => $new]));

        removal::remove($new);
        $this->assertFalse(recover::recovered($new));
    }

    /**
     * A copy someone brings cannot put in what the site would serve as a page.
     */
    public function test_what_does_not_belong_stays_out(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library(['slug' => 'central']);
        $entity = (int) $library->entityid;
        $record = $gen->create_record(['entityid' => $entity, 'title' => 'Platero y yo']);
        $dir = make_request_directory();
        $daily = $dir . '/copia.zip';
        copies::make($entity)->copy_content_to($daily);
        $new = (int) recover::restore($daily, 2)->entityid;

        $digital = array_keys($DB->get_columns('local_oksigeniaayvurenda_digital'));
        $row = array_fill_keys($digital, '');
        $row = ['id' => 7, 'entityid' => $entity, 'recordid' => $record->id, 'kind' => 'file', 'format' => 'pdf',
            'status' => 'published', 'createdby' => 2, 'timecreated' => 1, 'timemodified' => 1] + $row;
        $evil = $dir . '/malo.zip';
        $zip = new \ZipArchive();
        $zip->open($evil, \ZipArchive::CREATE);
        $zip->addFromString('origen.json', json_encode(['entity' => $entity]));
        $values = array_map(fn($c) => $row[$c], $digital);
        $zip->addFromString('digital.csv', implode(',', $digital) . "\n" . implode(',', $values) . "\n");
        $zip->addFromString('ficheros.csv', "area,item,path,file,what,size\n"
            . "cover,{$record->id},cover/x/pagina.html,pagina.html,,1\n"
            . "digital,7,digital/7/pagina.html,pagina.html,,1\n"
            . "portrait,{$entity},portrait/x/x.svg,x.svg,,1\n"
            . "copies,{$entity},copies/x/a.zip,a.zip,,1\n"
            . "cover,{$record->id},../../fuera.jpg,../../fuera.jpg,,1\n");
        foreach (['cover/x/pagina.html', 'digital/7/pagina.html', 'portrait/x/x.svg', 'copies/x/a.zip'] as $name) {
            $zip->addFromString($name, '<html><script>alert(1)</script></html>');
        }
        $zip->close();
        $this->assertSame(0, recover::bring_files($new, $evil, 2)['files']);
    }

    /**
     * It leaves every week if the centre's copies leave the server, and only the last two stay there; if they do not, it
     * goes after a few days.
     */
    public function test_every_week_out_of_the_server(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $entity = (int) $gen->create_library(['slug' => 'central'])->entityid;
        $now = time();

        filecopy::night($entity, $now);
        $this->assertFalse(filecopy::waiting($entity));
        filecopy::make($entity, $now - (filecopy::KEEP + 1) * DAYSECS);
        filecopy::night($entity, $now);
        $this->assertNull(filecopy::latest($entity));

        offsite::$network = function (string $method, string $url, string $payload): array {
            $name = rawurldecode(basename($url));
            if ($method === 'PUT') {
                $this->folder[$name] = (string) file_get_contents($payload);
                return [201, ''];
            }
            if ($method === 'DELETE') {
                unset($this->folder[$name]);
                return [204, ''];
            }
            if ($method === 'PROPFIND') {
                $hrefs = array_map(fn($n) => '<d:href>/dav/Copias/' . rawurlencode($n) . '</d:href>', array_keys($this->folder));
                return [207, implode('', $hrefs)];
            }
            return [201, ''];
        };
        try {
            offsite::new_key($entity);
            offsite::add_webdav($entity, 'https://nube.ejemplo.org/dav/Copias/', 'biblioteca', 'clave');
            filecopy::night($entity, $now);
            $this->assertTrue(filecopy::waiting($entity));
            ob_start();
            $this->runAdhocTasks(\local_oksigeniaayvurenda\task\make_filecopy::class);
            ob_end_clean();
            $this->assertFalse(filecopy::waiting($entity));
            filecopy::night($entity, $now);
            $this->assertFalse(filecopy::waiting($entity));
            filecopy::night($entity, $now + filecopy::EVERY * DAYSECS);
            $this->assertTrue(filecopy::waiting($entity));

            $copy = filecopy::make($entity, $now);
            foreach ([0, 7, 14, 21] as $weeks) {
                $out = offsite::send_files($entity, $copy, $now + $weeks * DAYSECS);
                $this->assertSame(['sent' => 1, 'failed' => []], $out);
            }
            $names = array_values(preg_grep('/ficheros/', array_keys($this->folder)));
            sort($names);
            $this->assertSame([
                filecopy::name($entity, $now + 14 * DAYSECS) . '.age',
                filecopy::name($entity, $now + 21 * DAYSECS) . '.age',
            ], $names);
            $this->assertStringStartsWith('age-encryption.org/v1', $this->folder[$names[0]]);
        } finally {
            offsite::$network = null;
        }
    }
}
