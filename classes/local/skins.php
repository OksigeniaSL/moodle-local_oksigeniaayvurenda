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
 * The skin of a centre's library: a school, a secondary school, a university or academy, a public library or a
 * foundation's, a company's, or a library that travels.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * Each centre chooses the skin of its library, and with it how much play and fantasy there is on top: not childish at a
 * university nor academic at a school. Under every skin the reader's own step by age goes on (the flower, the sky), and
 * play is chosen and can be turned off. What each skin is, in the project's DESIGN.md.
 */
final class skins {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var string[] The skins, the school's first: it is the one a centre has until it chooses. */
    public const SKINS = ['school', 'secondary', 'university', 'public', 'company', 'mobile'];

    /**
     * @var array What is on at each skin until the centre changes it: the visit, its games, the characters standing whole
     * with their gestures, their voices in the games, and the visit's sound on when the young readers come in.
     */
    public const PLAY = [
        'school' => ['visit' => true, 'games' => true, 'figures' => true, 'voices' => true, 'sound' => true],
        'secondary' => ['visit' => true, 'games' => true, 'figures' => true, 'voices' => false, 'sound' => false],
        'university' => ['visit' => true, 'games' => true, 'figures' => false, 'voices' => false, 'sound' => false],
        // A public library (a town's, a foundation's, an association's): readers of every age, a children's room.
        'public' => ['visit' => true, 'games' => true, 'figures' => true, 'voices' => false, 'sound' => false],
        // A company's library or documentation centre: adults only, plain, with no play until the centre wants it.
        'company' => ['visit' => true, 'games' => false, 'figures' => false, 'voices' => false, 'sound' => false],
        // A library that travels (a bookmobile): readers of every age at each stop.
        'mobile' => ['visit' => true, 'games' => true, 'figures' => true, 'voices' => false, 'sound' => false],
    ];

    /** @var string[] The skins of readers of every age, where the characters stand whole only for the young. */
    public const FIGURES_YOUNG = ['public', 'mobile'];

    /**
     * @var array The skins whose scenes a skin also visits, its own first: a public library has the school's as its
     * children's room; a company, the university's hall until the art brings its own; a bookmobile, a public library's.
     */
    public const SCENES = ['public' => ['public', 'school'], 'company' => ['company', 'university'],
        'mobile' => ['mobile', 'public', 'school']];

    /** @var array Whose words a skin speaks with, where it has none of its own: warm for every age, or plain for adults. */
    public const TONES = [
        'public' => ['public', 'lay', 'secondary'],
        'company' => ['company', 'lay', 'university'],
        'mobile' => ['mobile', 'public', 'lay', 'secondary'],
        // A university has its own words for many things; for the rest, the plain ones of grown-ups, not the school's.
        'university' => ['university', 'lay'],
    ];

    /** @var string[] The skins with no classes nor tutors: each reader is told about their own books. */
    public const NO_CLASSES = ['university', 'public', 'company', 'mobile'];

    /** @var string[] The skins with no courses either: no reading lists, no programs of a school, no lessons on a platform. */
    public const NO_COURSES = ['public', 'company', 'mobile'];

    /**
     * The skin of a centre.
     *
     * @param int $entityid
     * @return string One of SKINS.
     */
    public static function of(int $entityid): string {
        global $DB;
        if (!$entityid) {
            return self::SKINS[0];
        }
        $cache = \cache::make('local_oksigeniaayvurenda', 'access');
        $skin = $cache->get('skin_' . $entityid);
        if ($skin === false) {
            $settings = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
            $skin = in_array($settings['skin'] ?? '', self::SKINS, true) ? $settings['skin'] : self::SKINS[0];
            $cache->set('skin_' . $entityid, $skin);
        }
        return $skin;
    }

    /**
     * Whether the characters of a centre stand whole and move, and speak with their voices: at a school and a secondary
     * school; at a university or academy they are a portrait and a signature, with no voice of their own.
     *
     * @param int $entityid
     * @return bool
     */
    public static function lively(int $entityid): bool {
        return self::on($entityid, 'figures');
    }

    /**
     * What is on at a centre: its skin's, with what the centre changed.
     *
     * @param int $entityid
     * @return bool[] By PLAY's keys.
     */
    public static function play(int $entityid): array {
        global $DB;
        $play = self::PLAY[self::of($entityid)];
        if (!$entityid) {
            return $play;
        }
        $cache = \cache::make('local_oksigeniaayvurenda', 'access');
        $changed = $cache->get('play_' . $entityid);
        if ($changed === false) {
            $settings = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
            $changed = array_map('boolval', array_intersect_key((array) ($settings['play'] ?? []), $play));
            $cache->set('play_' . $entityid, $changed);
        }
        return $changed + $play;
    }

    /**
     * Whether one thing is on at a centre.
     *
     * @param int $entityid
     * @param string $what A key of PLAY.
     * @return bool
     */
    public static function on(int $entityid, string $what): bool {
        return !empty(self::play($entityid)[$what]);
    }

    /**
     * What a centre changed from its skin, to keep: only what differs, so a new skin brings its own.
     *
     * @param string $skin
     * @param string[] $on The keys left on.
     * @return bool[]
     */
    public static function changes(string $skin, array $on): array {
        $changed = [];
        foreach (self::PLAY[$skin] ?? self::PLAY[self::SKINS[0]] as $what => $default) {
            if (in_array($what, $on, true) !== $default) {
                $changed[$what] = !$default;
            }
        }
        return $changed;
    }

    /**
     * The words of a centre's skin for something said to a reader: the same thing, in its tone (a school's greets and
     * speaks warmly; a university's gives the facts). A skin without its own words says the school's.
     *
     * @param int $entityid
     * @param string $key The school's string.
     * @return string The string to use.
     */
    public static function words(int $entityid, string $key): string {
        $skin = self::of($entityid);
        // A young reader of a library of every age (public, mobile) reads the school's words, which are the ones its
        // voices say; the grown-ups, the library's.
        if (in_array($skin, self::FIGURES_YOUNG, true) && characters::young($entityid)) {
            return $key;
        }
        // In a school with secondary, a reader past the flower reads the secondary school's words where there are some:
        // a fourteen-year-old is not spoken to as an infant.
        if (
            $skin === self::SKINS[0] && characters::step($entityid) === 'cielo'
                && get_string_manager()->string_exists($key . 'secondary', 'local_oksigeniaayvurenda')
        ) {
            return $key . 'secondary';
        }
        foreach (self::TONES[$skin] ?? [$skin] as $tone) {
            if ($tone !== self::SKINS[0] && get_string_manager()->string_exists($key . $tone, 'local_oksigeniaayvurenda')) {
                return $key . $tone;
            }
        }
        return $key;
    }

    /**
     * A string, in the words of a centre's skin.
     *
     * @param int $entityid
     * @param string $key
     * @param mixed $a
     * @return string
     */
    public static function say(int $entityid, string $key, $a = null): string {
        return get_string(self::words($entityid, $key), 'local_oksigeniaayvurenda', $a);
    }

    /**
     * What a page asks its strings with, in the words of its library's skin.
     *
     * @param \stdClass|null $library
     * @return \Closure Of the key and its parameter.
     */
    public static function speaker(?\stdClass $library): \Closure {
        return fn(string $key, $a = null) => $library ? self::say((int) $library->entityid, $key, $a)
            : get_string($key, 'local_oksigeniaayvurenda', $a);
    }

    /**
     * Whether a centre has no courses: its people are readers, not students of anything.
     *
     * @param int $entityid
     * @return bool
     */
    public static function no_courses(int $entityid): bool {
        return in_array(self::of($entityid), self::NO_COURSES, true);
    }

    /**
     * Whether a centre has no classes nor tutors, so each reader is told about their own books.
     *
     * @param int $entityid
     * @return bool
     */
    public static function no_classes(int $entityid): bool {
        return in_array(self::of($entityid), self::NO_CLASSES, true);
    }

    /**
     * Whose scenes a centre of this skin visits: its own, and a public library also the school's (its children's room).
     *
     * @param string $skin
     * @return string[]
     */
    public static function scenes(string $skin): array {
        return self::SCENES[$skin] ?? [$skin];
    }

    /**
     * Forgets the skin kept for this request, after it changes.
     *
     * @param int $entityid
     */
    public static function forget(int $entityid): void {
        \cache::make('local_oksigeniaayvurenda', 'access')->delete_many(['skin_' . $entityid, 'play_' . $entityid]);
    }

    /**
     * The card of a reader whose group does not say their age: the lively sky in a school or a secondary school, the
     * sober one where readers are grown-ups or of every age (a little one in the children's room is recognised by it).
     *
     * @param int $entityid
     * @return string
     */
    public static function unknown_look(int $entityid): string {
        return in_array(self::of($entityid), ['school', 'secondary'], true) ? 'sky' : 'skysober';
    }
}
