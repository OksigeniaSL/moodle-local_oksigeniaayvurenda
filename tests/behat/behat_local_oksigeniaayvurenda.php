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
 * Steps of Ayvurenda: two centres kept apart on one site, with made-up people and books, and pages opened by address.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.
require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Mink\Exception\ExpectationException;
use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\libraries;
use local_oksigeniaayvurenda\local\roles;

/**
 * Steps of Ayvurenda.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_oksigeniaayvurenda extends behat_base {
    /** @var array[] What each made-up centre has, to look for it where it must not be. */
    private const CENTRES = [
        'alfa' => ['centre' => 'Colegio Alfa', 'library' => 'Biblioteca Alfa', 'book' => 'El faro de Alfa',
            'reader' => ['Iker', 'Alfa'], 'nouser' => ['Gael', 'Alfonsín']],
        'beta' => ['centre' => 'Colegio Beta', 'library' => 'Biblioteca Beta', 'book' => 'La isla de Beta',
            'reader' => ['Teo', 'Beta'], 'nouser' => ['Bruno', 'Betanzos']],
    ];

    /**
     * Two centres on one site kept apart, each with its own category and library (slugs alfa and beta), a librarian
     * given the role in its category (bibalfa, bibbeta), someone who runs it (jefealfa, jefebeta), a reader with an
     * account (lectoralfa, lectorbeta) and one without, and a book lent. All made up.
     *
     * @Given /^two Ayvurenda centres are kept apart$/
     */
    public function two_ayvurenda_centres_are_kept_apart(): void {
        set_config('entitycategories', 1, 'local_oksigeniaayvurenda');
        $generator = testing_util::get_data_generator();
        $plugin = $generator->get_plugin_generator('local_oksigeniaayvurenda');
        $librarian = roles::ensure_librarian();
        $manager = create_role('Runs a centre', 'ayvcentremanager', 'Made up for the tests');
        set_role_contextlevels($manager, [CONTEXT_COURSECAT]);
        foreach (['view', 'desk', 'catalogue', 'manage'] as $capability) {
            assign_capability('local/oksigeniaayvurenda:' . $capability, CAP_ALLOW, $manager, context_system::instance()->id);
        }
        foreach (self::CENTRES as $slug => $centre) {
            $library = libraries::create_entity(['name' => $centre['centre'], 'library' => $centre['library']]);
            $GLOBALS['DB']->set_field('local_oksigeniaayvurenda_library', 'slug', $slug, ['id' => $library->id]);
            $context = access::context($library);
            foreach (['bib' . $slug => $librarian, 'jefe' . $slug => $manager] as $username => $role) {
                $user = $generator->create_user(['username' => $username, 'password' => $username]);
                role_assign($role, $user->id, $context->id);
            }
            $user = $generator->create_user(['username' => 'lector' . $slug, 'password' => 'lector' . $slug,
                'firstname' => $centre['reader'][0], 'lastname' => $centre['reader'][1]]);
            $reader = $plugin->create_reader(['entityid' => $library->entityid, 'userid' => $user->id]);
            $plugin->create_reader(['entityid' => $library->entityid, 'firstname' => $centre['nouser'][0],
                'lastname' => $centre['nouser'][1]]);
            $record = $plugin->create_record(['entityid' => $library->entityid, 'title' => $centre['book']]);
            $item = $plugin->create_item(['libraryid' => $library->id, 'recordid' => $record->id]);
            $plugin->create_loan(['libraryid' => $library->id, 'itemid' => $item->id, 'readerid' => $reader->id]);
        }
        // The pages run in requests of their own: they read the roles anew once the site's are marked as changed.
        context_system::instance()->mark_dirty();
    }

    /**
     * Opens a page of the plugin for a library.
     *
     * @When /^I open the Ayvurenda page "(?P<page>[a-z_]+\.php)" of library "(?P<slug>[a-z0-9-]+)"$/
     * @param string $page
     * @param string $slug
     */
    public function i_open_the_ayvurenda_page(string $page, string $slug): void {
        $this->visit('/local/oksigeniaayvurenda/' . $page, ['library' => $slug]);
    }

    /**
     * Opens, in a library, the card of the reader with an account, as the staff preview it.
     *
     * @When /^I open the card of reader "(?P<username>[a-z0-9]+)" in library "(?P<slug>[a-z0-9-]+)"$/
     * @param string $username
     * @param string $slug
     */
    public function i_open_the_card_of_reader(string $username, string $slug): void {
        global $DB;
        $userid = $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        $readerid = $DB->get_field('local_oksigeniaayvurenda_reader', 'id', ['userid' => $userid], MUST_EXIST);
        $this->visit('/local/oksigeniaayvurenda/card.php', ['library' => $slug, 'reader' => $readerid]);
    }

    /**
     * Opens, in a library, the record of a book by its title, whichever centre it is of.
     *
     * @When /^I open the record "(?P<title>[^"]+)" in library "(?P<slug>[a-z0-9-]+)"$/
     * @param string $title
     * @param string $slug
     */
    public function i_open_the_record(string $title, string $slug): void {
        global $DB;
        $recordid = $DB->get_field('local_oksigeniaayvurenda_record', 'id', ['title' => $title], MUST_EXIST);
        $this->visit('/local/oksigeniaayvurenda/record.php', ['library' => $slug, 'id' => $recordid]);
    }

    /**
     * Checks that nothing of a made-up centre is in the page, not even hidden in its markup: its centre, library,
     * readers and book.
     *
     * @Then /^the page has nothing of centre "(?P<slug>alfa|beta)"$/
     * @param string $slug
     * @throws ExpectationException
     */
    public function the_page_has_nothing_of_centre(string $slug): void {
        $centre = self::CENTRES[$slug];
        $html = $this->getSession()->getPage()->getContent();
        foreach (
            [$centre['centre'], $centre['library'], $centre['book'], implode(' ', $centre['reader']),
                implode(' ', $centre['nouser']), $centre['nouser'][1]] as $text
        ) {
            if (str_contains($html, $text)) {
                throw new ExpectationException('The page shows «' . $text . '» of centre ' . $slug, $this->getSession());
            }
        }
    }

    /**
     * Goes to an address of the site.
     *
     * @param string $path
     * @param array $params
     */
    private function visit(string $path, array $params): void {
        $this->getSession()->visit($this->locate_path((new moodle_url($path, $params))->out_as_local_url(false)));
    }
}
