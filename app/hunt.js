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
    var voices = {};
    try {
        voices = JSON.parse(dialog.getAttribute('data-voices')) || {};
    } catch (e) {
        voices = {};
    }
    // The memory says it too, with the visit's sound on.
    var speak = function(key, then) {
        if (window.AYVVisit) {
            window.AYVVisit.say(voices[key], then ? voices[then] : null);
        }
    };

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

    var nudge = function(slot) {
        if (!reduce && slot) {
            slot.classList.remove('ayv-hunt-no');
            // Restart the nudge.
            void slot.offsetWidth;
            slot.classList.add('ayv-hunt-no');
        }
    };
    // For a grown reader the spines have no titles, as seen from the aisle: a book touched comes out a little and shows
    // its title, as someone takes it out to check, and goes back (Gaudí, JV-D11).
    var grown = dialog.hasAttribute('data-grown');
    var pull = function(spine, back) {
        var title = spine.querySelector('.ayv-spine-title');
        if (!grown || !title) {
            return;
        }
        title.textContent = spine.getAttribute('data-title') || '';
        spine.classList.add('ayv-spine-pulled');
        if (back) {
            window.setTimeout(function() {
                spine.classList.remove('ayv-spine-pulled');
                title.textContent = '';
            }, 1800);
        }
    };
    var miss = function(spine) {
        var slot = spine.closest('.ayv-spine-slot');
        // The gap of a book that is out says so, and is not a try: a small child touching the bird three times has not
        // looked at any label yet.
        if (slot && slot.classList.contains('ayv-spine-gap')) {
            tell(messages.gap);
            speak('gap');
            nudge(slot);
            return;
        }
        misses++;
        spine.setAttribute('data-tried', '1');
        pull(spine, true);
        var parts = [];
        var heard = spines.indexOf(spine) < spines.indexOf(mine) ? 'before' : 'after';
        parts.push(spines.indexOf(spine) < spines.indexOf(mine) ? messages.before : messages.after);
        var theirs = lines(spine), ours = lines(mine), told = false, look = null;
        for (var i = 0; i < Math.min(3, ours.length); i++) {
            if ((theirs[i] || '') !== ours[i]) {
                parts.push((messages['line' + (i + 1)] || '').replace('{line}', ours[i]));
                told = true;
                look = 'look' + (i + 1);
                // The line to look at lights up on the clue's label: the hint that works without reading (D8).
                dialog.querySelectorAll('.ayv-hunt-card .ayv-where-label > span').forEach(function(span, at) {
                    span.classList.toggle('ayv-line-lit', at === i);
                });
                break;
            }
        }
        if (!told && messages.same) {
            // The very same label: on a real shelf they would stand together; then it is the other one beside it.
            parts.push(messages.same);
        }
        if (misses >= 3) {
            mine.closest('.ayv-spine-slot').classList.add('ayv-hunt-glow');
            parts.push(messages.glow);
            heard = 'glow';
        }
        tell(parts.filter(Boolean).join(' '));
        speak(heard, misses >= 3 ? null : look);
        nudge(slot);
    };

    var found = function() {
        pull(mine, false);
        done = true;
        say.textContent = '';
        mine.closest('.ayv-spine-slot').classList.remove('ayv-hunt-glow');
        mine.closest('.ayv-spine-slot').classList.add('ayv-hunt-yes');
        chime();
        speak('found');
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
        // Only the label: the title would let one match titles with the cover instead of reading the label. A spine already
        // tried gives its title, as a consolation.
        var title = document.createElement('span');
        title.className = 'ayv-hunt-lens-title';
        title.textContent = spine.getAttribute('data-tried') ? spine.getAttribute('data-title') || '' : '';
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

    var show = function() {
        if (!dialog.open) {
            dialog.showModal();
            speak('clue');
        }
    };
    // The game comes out of the room's shelf, as a door is walked through: the room leans towards it, and the shelf
    // of the game opens (JV-D13). With «reduce motion», or without a shelf in the room, it simply opens.
    var open = function() {
        var room = document.querySelector('.ayv-visit-room');
        var visit = document.querySelector('.ayv-visit');
        var place = document.querySelector('.ayv-visit-place[href*="stacks.php"][data-tip]');
        if (reduce || dialog.open || !room || !visit || !place) {
            show();
            return;
        }
        var p = place.getAttribute('data-tip').split(' ').map(parseFloat);
        var k = room.getBoundingClientRect().width / (parseFloat(visit.getAttribute('data-width')) || 2400);
        room.style.transformOrigin = (p[0] * k) + 'px ' + (p[2] * k) + 'px';
        room.classList.add('ayv-visit-peek');
        window.setTimeout(function() {
            show();
            room.classList.remove('ayv-visit-peek');
        }, 320);
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
        show();
    }
})();
