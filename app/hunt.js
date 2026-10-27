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
 * Ayvurenda: find the hidden book. The memory gives the label of a book hidden on the room's shelf; a spine that is not
 * it says which way to go and which line of the label to look at, never that it is wrong; after three, the hidden one
 * glows a little. Found, it comes out and the memory cheers. Each spine is a link with all it says for a screen reader,
 * so the game is the same with the keyboard; nothing moves with «reduce motion». Nothing is sent anywhere.
 *
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';
    var dialog = document.getElementById('ayv-hunt');
    if (!dialog || !dialog.showModal) {
        return;
    }
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var target = dialog.getAttribute('data-target');
    var say = dialog.querySelector('.ayv-hunt-say');
    var messages = {};
    try {
        messages = JSON.parse(dialog.getAttribute('data-messages')) || {};
    } catch (e) {
        messages = {};
    }
    var spines = Array.prototype.slice.call(dialog.querySelectorAll('.ayv-spine'));
    var mine = spines.filter(function(s) {
        return s.getAttribute('data-id') === target;
    })[0];
    if (!mine) {
        return;
    }
    var lines = function(spine) {
        return (spine.getAttribute('data-lines') || '').split('|');
    };
    var misses = 0;
    var done = false;

    // A small sound when it is found, with the visit's sound on.
    var chime = function() {
        if (window.AYVVisit) {
            window.AYVVisit.chime();
        }
    };

    var tell = function(text) {
        // The same words twice are said again.
        say.textContent = '';
        window.setTimeout(function() {
            say.textContent = text;
        }, 30);
    };

    var miss = function(spine) {
        misses++;
        var slot = spine.closest('.ayv-spine-slot');
        var parts = [];
        if (slot && slot.classList.contains('ayv-spine-gap')) {
            parts.push(messages.gap);
        } else {
            parts.push(spines.indexOf(spine) < spines.indexOf(mine) ? messages.before : messages.after);
            var theirs = lines(spine), ours = lines(mine);
            for (var i = 0; i < Math.min(3, ours.length); i++) {
                if ((theirs[i] || '') !== ours[i]) {
                    parts.push((messages['line' + (i + 1)] || '').replace('{line}', ours[i]));
                    break;
                }
            }
        }
        if (misses >= 3) {
            mine.closest('.ayv-spine-slot').classList.add('ayv-hunt-glow');
            parts.push(messages.glow);
        }
        tell(parts.filter(Boolean).join(' '));
        if (!reduce && slot) {
            slot.classList.remove('ayv-hunt-no');
            // Restart the nudge.
            void slot.offsetWidth;
            slot.classList.add('ayv-hunt-no');
        }
    };

    var found = function() {
        done = true;
        say.textContent = '';
        mine.closest('.ayv-spine-slot').classList.remove('ayv-hunt-glow');
        mine.closest('.ayv-spine-slot').classList.add('ayv-hunt-yes');
        chime();
        window.setTimeout(function() {
            var ask = dialog.querySelector('.ayv-hunt-ask');
            if (ask) {
                ask.hidden = true;
            }
            var end = dialog.querySelector('.ayv-hunt-found');
            end.hidden = false;
            end.querySelector('.ayv-hunt-found-title').focus();
        }, reduce ? 0 : 650);
    };

    // The magnifier: the label of the spine under the pointer, the keyboard or the finger, big, to set beside the clue.
    var lens = dialog.querySelector('.ayv-hunt-lens');
    var look = function(spine) {
        var label = document.createElement('span');
        label.className = 'ayv-where-label';
        lines(spine).forEach(function(line) {
            var span = document.createElement('span');
            span.textContent = line;
            label.appendChild(span);
        });
        var title = document.createElement('span');
        title.className = 'ayv-hunt-lens-title';
        title.textContent = spine.getAttribute('data-title') || '';
        lens.textContent = '';
        lens.appendChild(label);
        lens.appendChild(title);
    };

    spines.forEach(function(spine) {
        spine.addEventListener('pointerenter', function() {
            look(spine);
        });
        spine.addEventListener('focus', function() {
            look(spine);
        });
        spine.addEventListener('click', function(e) {
            look(spine);
            e.preventDefault();
            if (done) {
                return;
            }
            if (spine === mine) {
                found();
            } else {
                miss(spine);
            }
        });
    });

    var open = function() {
        if (!dialog.open) {
            dialog.showModal();
        }
    };
    Array.prototype.forEach.call(document.querySelectorAll('[data-open="ayv-hunt"]'), function(button) {
        button.hidden = false;
        button.addEventListener('click', open);
    });
    dialog.addEventListener('close', function() {
        if (window.location.hash === '#busca' && window.history.replaceState) {
            window.history.replaceState(null, '', window.location.pathname + window.location.search);
        }
    });
    // «Another book» comes back here, straight into the game.
    if (window.location.hash === '#busca') {
        open();
    }
})();
