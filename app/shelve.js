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
 * Ayvurenda: the herald's shelf. The herald brings the new books one after another and each goes to its corner, the
 * corners being doors. A wrong door says what numbers it keeps and what number the book has (and what it is about), never
 * the answer; after two, the right door glows. When all are in their place, the herald cheers and the books are there
 * to take home. Doors are buttons, so it is the same with the keyboard and a screen reader; nothing moves with «reduce
 * motion». Nothing is sent anywhere.
 *
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';
    var dialog = document.getElementById('ayv-sort');
    if (!dialog || !dialog.showModal) {
        return;
    }
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var say = dialog.querySelector('.ayv-hunt-say');
    var messages = {};
    try {
        messages = JSON.parse(dialog.getAttribute('data-messages')) || {};
    } catch (e) {
        messages = {};
    }
    var books = Array.prototype.slice.call(dialog.querySelectorAll('.ayv-sort-book'));
    var doors = Array.prototype.slice.call(dialog.querySelectorAll('.ayv-sort-door'));
    var at = 0;
    var misses = 0;
    var busy = false;
    var voices = {};
    try {
        voices = JSON.parse(dialog.getAttribute('data-voices')) || {};
    } catch (e) {
        voices = {};
    }
    // The herald says it too, with the visit's sound on.
    var speak = function(key) {
        if (window.AYVVisit) {
            window.AYVVisit.say(voices[key]);
        }
    };

    var tell = function(text) {
        say.textContent = '';
        window.setTimeout(function() {
            say.textContent = text;
        }, 30);
    };
    var fill = function(text, values) {
        return (text || '').replace(/\{(\w+)\}/g, function(all, key) {
            return values[key] !== undefined ? values[key] : all;
        });
    };
    var clear = function() {
        doors.forEach(function(door) {
            door.classList.remove('ayv-sort-glow', 'ayv-hunt-no-door');
        });
    };

    var next = function() {
        books[at].hidden = true;
        at++;
        misses = 0;
        clear();
        busy = false;
        if (at < books.length) {
            books[at].hidden = false;
            return;
        }
        // All in their place.
        say.textContent = '';
        dialog.querySelector('.ayv-hunt-ask').hidden = true;
        dialog.querySelector('.ayv-sort-play').hidden = true;
        var end = dialog.querySelector('.ayv-sort-done');
        end.hidden = false;
        if (window.AYVVisit) {
            window.AYVVisit.chime();
        }
        speak('done');
        end.querySelector('.ayv-hunt-found-title').focus();
    };

    doors.forEach(function(door) {
        door.addEventListener('click', function() {
            if (busy || at >= books.length) {
                return;
            }
            var book = books[at];
            var label = door.querySelector('.ayv-sort-door-label').textContent;
            if (door.getAttribute('data-corner') === book.getAttribute('data-corner')) {
                busy = true;
                tell(fill(messages.yes, {corner: label}));
                speak('yes');
                door.classList.add('ayv-sort-yes');
                window.setTimeout(function() {
                    door.classList.remove('ayv-sort-yes');
                    next();
                }, reduce ? 900 : 1200);
                return;
            }
            misses++;
            var parts = [fill(messages.no, {corner: label, digit: door.getAttribute('data-corner'),
                mine: book.getAttribute('data-corner')})];
            if (book.getAttribute('data-topic')) {
                parts.push(fill(messages.topic, {topic: book.getAttribute('data-topic')}));
            }
            if (misses >= 2) {
                doors.forEach(function(other) {
                    if (other.getAttribute('data-corner') === book.getAttribute('data-corner')) {
                        other.classList.add('ayv-sort-glow');
                    }
                });
                parts.push(messages.glow);
            }
            tell(parts.join(' '));
            speak(misses >= 2 ? 'glow' : 'no');
            if (!reduce) {
                door.classList.remove('ayv-hunt-no-door');
                void door.offsetWidth;
                door.classList.add('ayv-hunt-no-door');
            }
        });
    });

    var open = function() {
        if (!dialog.open) {
            dialog.showModal();
            speak('intro');
        }
    };
    Array.prototype.forEach.call(document.querySelectorAll('[data-open="ayv-sort"]'), function(button) {
        button.hidden = false;
        button.addEventListener('click', open);
    });
    dialog.addEventListener('close', function() {
        if (window.location.hash === '#coloca' && window.history.replaceState) {
            window.history.replaceState(null, '', window.location.pathname + window.location.search);
        }
    });
    // «Other new books» comes back here, straight into the game.
    if (window.location.hash === '#coloca') {
        open();
    }
})();
