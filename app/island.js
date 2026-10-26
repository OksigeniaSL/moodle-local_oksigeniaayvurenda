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
 * Ayvurenda: the class island in 3D over its 2D layers, in «My class». What the class met since this browser last saw
 * the island grows in front of the tutor; the first time, everything grows. With «reduce motion» or without WebGL2 the
 * 2D layers stay, as they are.
 *
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduce || !window.AYVIsland3D) {
        return;
    }
    Array.prototype.forEach.call(document.querySelectorAll('.ayv-island[data-island]'), function(figure) {
        var state = JSON.parse(figure.getAttribute('data-island'));
        var key = 'ayv-island-' + figure.getAttribute('data-island-key');
        var before = null;
        try {
            before = JSON.parse(window.localStorage.getItem(key) || 'null');
        } catch (e) {
            before = null;
        }
        // What this browser saw last, if it is not more than there is now (a new school year starts again).
        var seen = before && before.met <= state.met && (!before.everyone || state.everyone) && (!before.boat || state.boat)
            ? before : {met: 0, everyone: false, boat: false};
        var stage = document.createElement('div');
        stage.className = 'ayv-island-3d';
        figure.appendChild(stage);
        var island = window.AYVIsland3D.mount(stage, seen);
        if (!island) {
            stage.parentNode.removeChild(stage);
            return;
        }
        figure.classList.add('ayv-island-on');
        if (seen.met < state.met || seen.everyone !== state.everyone || seen.boat !== state.boat) {
            setTimeout(function() {
                island.update(state);
            }, 700);
        }
        try {
            window.localStorage.setItem(key, JSON.stringify(state));
        } catch (e) {
            // Without storage, it grows again next time: no harm.
        }
    });
})();
