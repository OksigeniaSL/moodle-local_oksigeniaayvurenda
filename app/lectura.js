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
 * Ayvurenda: a reading in parts, heard as one book. A part goes on into the next; the listener comes back to the part and
 * the second where they were (kept on the reading's first part, «part:second»); and the soft sound its reader chose plays
 * low under the voice, from part to part, until the listener takes it away.
 *
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';
    var config = window.AYV || {};
    var str = config.strings || {};
    var voice = document.getElementById('ayv-lectura-voz');
    if (!voice) {
        return;
    }
    var buttons = Array.prototype.slice.call(document.querySelectorAll('.ayv-lectura-parte'));
    var now = document.getElementById('ayv-lectura-ahora');
    var current = Number(config.start) || (buttons[0] ? Number(buttons[0].getAttribute('data-id')) : 0);
    var timer = null;

    // Where the listener is, kept for the next time: soon while it plays, at once when it stops.
    var send = function() {
        if (!window.fetch || !config.ajaxurl) {
            return;
        }
        var body = new URLSearchParams();
        body.set('sesskey', config.sesskey);
        body.set('item', config.item);
        body.set('position', current + ':' + Math.floor(voice.currentTime || 0));
        fetch(config.ajaxurl, {method: 'POST', credentials: 'same-origin', body: body}).catch(function() {
            return null;
        });
    };
    var keep = function() {
        clearTimeout(timer);
        timer = setTimeout(send, 2000);
    };

    // The soft sound: low, from part to part, faded in and out; whoever listens may take it away, and it stays away.
    var sound = document.getElementById('ayv-lectura-fondo');
    var toggle = document.getElementById('ayv-lectura-quitar');
    // The voice of a browser is near −20 LUFS and the sounds come at −24: this leaves them about twelve below.
    var LOW = 0.4;
    var stored = 'ayv-lectura-sin-fondo';
    var wanted = true;
    try {
        wanted = window.localStorage.getItem(stored) !== '1';
    } catch (e) {
        wanted = true;
    }
    var fade = null;
    var fadeTo = function(target, then) {
        clearInterval(fade);
        fade = setInterval(function() {
            var step = target > sound.volume ? 0.05 : -0.05;
            var next = sound.volume + step;
            if ((step > 0 && next >= target) || (step < 0 && next <= target)) {
                sound.volume = Math.max(0, Math.min(1, target));
                clearInterval(fade);
                if (then) {
                    then();
                }
                return;
            }
            sound.volume = Math.max(0, Math.min(1, next));
        }, 80);
    };
    var soundOn = function() {
        if (!sound || !wanted) {
            return;
        }
        if (sound.paused) {
            sound.volume = 0;
            sound.play().catch(function() {
                return null;
            });
        }
        fadeTo(LOW);
    };
    var soundOff = function() {
        if (sound && !sound.paused) {
            fadeTo(0, function() {
                sound.pause();
            });
        }
    };
    var sayToggle = function() {
        if (!toggle) {
            return;
        }
        toggle.setAttribute('aria-pressed', wanted ? 'true' : 'false');
        toggle.querySelector('span').textContent = toggle.getAttribute(wanted ? 'data-off' : 'data-on');
    };
    if (toggle) {
        sayToggle();
        toggle.addEventListener('click', function() {
            wanted = !wanted;
            try {
                window.localStorage.setItem(stored, wanted ? '0' : '1');
            } catch (e) {
                wanted = !!wanted;
            }
            sayToggle();
            if (wanted && !voice.paused) {
                soundOn();
            } else {
                soundOff();
            }
        });
    }

    var titleOf = function(id) {
        var found = buttons.filter(function(button) {
            return Number(button.getAttribute('data-id')) === id;
        })[0];
        return found ? found.textContent : '';
    };
    var mark = function() {
        buttons.forEach(function(button) {
            if (Number(button.getAttribute('data-id')) === current) {
                button.setAttribute('aria-current', 'true');
            } else {
                button.removeAttribute('aria-current');
            }
        });
    };
    var play = function(id, at) {
        var button = buttons.filter(function(b) {
            return Number(b.getAttribute('data-id')) === id;
        })[0];
        if (!button) {
            return;
        }
        current = id;
        mark();
        voice.src = button.getAttribute('data-url');
        if (at > 0) {
            voice.addEventListener('loadedmetadata', function seek() {
                voice.removeEventListener('loadedmetadata', seek);
                voice.currentTime = at;
            });
        }
        voice.play().catch(function() {
            return null;
        });
        if (now) {
            now.textContent = (str.readingnow || '{$a}').replace('{$a}', function() {
                return titleOf(id);
            });
        }
        send();
    };

    buttons.forEach(function(button) {
        button.addEventListener('click', function() {
            play(Number(button.getAttribute('data-id')), 0);
        });
    });
    // Back where the listener was: the part is already loaded; the second, once it knows how long it is.
    var at = Number(config.at) || 0;
    if (at > 0) {
        voice.addEventListener('loadedmetadata', function seek() {
            voice.removeEventListener('loadedmetadata', seek);
            voice.currentTime = at;
        });
    }
    voice.addEventListener('play', soundOn);
    voice.addEventListener('pause', function() {
        // Between one part and the next the sound goes on; it stops when the listener stops.
        if (!voice.ended) {
            soundOff();
        }
        send();
    });
    voice.addEventListener('timeupdate', keep);
    voice.addEventListener('ended', function() {
        var index = buttons.map(function(b) {
            return Number(b.getAttribute('data-id'));
        }).indexOf(current);
        if (index >= 0 && index < buttons.length - 1) {
            play(Number(buttons[index + 1].getAttribute('data-id')), 0);
        } else {
            soundOff();
            send();
        }
    });
})();
