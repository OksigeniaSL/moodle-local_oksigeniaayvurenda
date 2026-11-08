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
 * How many readers there must be behind what the readers see of each other («What your class reads», the book of the
 * week, «Also taken»), so that nobody can work out who: in a small group, two is a name (priv-menores 5).
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class anonymity {
    /** @var int Readers there must be behind a figure the readers see. */
    public const FEW = 5;

    /** @var int Below this, how many is said as «several», never as a number. */
    public const SAY = 10;

    /** @var int Readers who took the same two books there must be before one is offered beside the other. */
    public const PAIR = 3;
}
