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
 * Ayvurenda: a reading in parts, heard as one book (Jujol's page, 21 Nov 2026). One big «play», the bar of the part and
 * the whole book as a strip of its parts, each as long as it lasts; between two parts, a count of three and «Not now»;
 * the listener comes back to the part and the second where they were (kept on the reading's first part, «part:second»);
 * and the soft sound its reader chose plays low under the voice, from part to part, until the listener takes it away.
 * Without JavaScript the browser's own player stays.
 *
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';
    var config = window.AYV || {};
    var str = config.strings || {};
    var page = document.getElementById('ayv-oye');
    var voice = document.getElementById('ayv-lectura-voz');
    if (!page || !voice) {
        return;
    }
    var buttons = Array.prototype.slice.call(document.querySelectorAll('.ayv-oye-parte'));
    var strip = Array.prototype.slice.call(document.querySelectorAll('.ayv-oye-todo span'));
    var player = document.getElementById('ayv-oye-rep');
    var play = document.getElementById('ayv-oye-play');
    var bar = document.getElementById('ayv-oye-barra');
    var done = document.getElementById('ayv-oye-ya');
    var whole = document.getElementById('ayv-oye-total');
    var left = document.getElementById('ayv-oye-quedan');
    var nowText = document.getElementById('ayv-oye-ahora-texto');
    var nowName = document.getElementById('ayv-oye-ahora-nombre');
    var next = document.getElementById('ayv-oye-sigue');
    var ids = buttons.map(function(b) {
        return Number(b.getAttribute('data-id'));
    });
    var current = Number(config.start) || ids[0];
    var say = function(text, a) {
        return (text || '{$a}').replace('{$a}', function() {
            return a;
        });
    };
    var mmss = function(s) {
        s = Math.max(0, Math.floor(s || 0));
        return Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2);
    };
    var button = function(id) {
        return buttons[ids.indexOf(id)];
    };
    var seconds = function(id) {
        return Number(button(id).getAttribute('data-seconds')) || 0;
    };
    // How long the part lasts: what the file says, or what the recorder measured if the file does not say.
    var length = function() {
        return isFinite(voice.duration) && voice.duration > 0 ? voice.duration : seconds(current);
    };
    page.classList.add('is-js');
    player.hidden = false;

    // Where the listener is, kept for the next time: soon while it plays, at once when it stops.
    var timer = null;
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

    // What is drawn: the bar of the part, its times, the strip of the book, what is left, the index.
    var paint = function() {
        var total = length();
        var at = Math.min(voice.currentTime || 0, total || 0);
        var share = total ? at / total : 0;
        bar.querySelector('i').style.width = (share * 100) + '%';
        bar.querySelector('b').style.left = (share * 100) + '%';
        bar.setAttribute('aria-valuemax', String(Math.floor(total)));
        bar.setAttribute('aria-valuenow', String(Math.floor(at)));
        bar.setAttribute('aria-valuetext', mmss(at) + ' / ' + mmss(total));
        done.textContent = mmss(at);
        whole.textContent = total ? mmss(total) : '';
        var index = ids.indexOf(current);
        var rest = total - at;
        strip.forEach(function(span, i) {
            span.classList.toggle('is-hecha', i < index);
            span.classList.toggle('is-ahora', i === index);
            span.style.setProperty('--p', i === index ? Math.round(share * 100) + '%' : '0%');
            if (i > index) {
                rest += seconds(ids[i]);
            }
        });
        if (left) {
            left.textContent = say(str.readingleft, String(Math.max(1, Math.round(rest / 60))));
        }
    };
    var mark = function() {
        var index = ids.indexOf(current);
        var sounding = !voice.paused;
        page.classList.toggle('is-sonando', sounding);
        play.setAttribute('aria-pressed', sounding ? 'true' : 'false');
        play.setAttribute('aria-label', sounding ? (str.readingpause || '') : (str.readingplay || ''));
        buttons.forEach(function(b, i) {
            var num = b.querySelector('.ayv-oye-num');
            b.classList.toggle('is-oida', i < index);
            if (i === index) {
                b.setAttribute('aria-current', 'true');
            } else {
                b.removeAttribute('aria-current');
            }
            // The part that sounds or comes next keeps its bars: still at rest, moving while it sounds.
            if (i === index) {
                num.innerHTML = '<span class="ayv-oye-barras" aria-hidden="true"><i></i><i></i><i></i></span>';
            } else {
                num.textContent = i < index ? '✓' : b.getAttribute('data-num');
            }
        });
        paint();
    };
    var tell = function() {
        var b = button(current);
        nowText.textContent = say(str.readingnow, b.getAttribute('data-title'));
        var name = b.getAttribute('data-name');
        nowName.textContent = name ? '«' + name + '»' : '';
    };

    // Each part's soft sound (Enrique, 21 Nov 2026): the one its reader chose, or the one whoever listens took for that
    // part, kept in their browser. Low, faded in and out, from part to part; the switch says which one and takes them away.
    var sound = document.getElementById('ayv-lectura-fondo');
    var toggle = document.getElementById('ayv-oye-fondo');
    var other = document.getElementById('ayv-oye-otro');
    var panel = document.getElementById('ayv-oye-sonidos');
    var sounds = {};
    (config.sounds || []).forEach(function(s) {
        sounds[s.key] = s;
    });
    // Thirty below the voice (Enrique, 21 Nov 2026, after hearing it at 8 to 34): the sounds come at −24 LUFS and the voice
    // of a browser near −20, so −26 dB.
    var LOW = 0.05;
    var get = function(key) {
        try {
            return window.localStorage.getItem(key);
        } catch (e) {
            return null;
        }
    };
    var put = function(key, value) {
        try {
            if (value === null) {
                window.localStorage.removeItem(key);
            } else {
                window.localStorage.setItem(key, value);
            }
        } catch (e) {
            return;
        }
    };
    var mine = function(id) {
        return 'ayv-lectura-fondo-' + id;
    };
    var wanted = get('ayv-lectura-sin-fondo') !== '1';
    var theirs = function(id) {
        var b = button(id);
        return b ? b.getAttribute('data-sound') || '' : '';
    };
    var soundOf = function(id) {
        var chosen = get(mine(id));
        var key = chosen === null ? theirs(id) : (chosen === 'none' ? '' : chosen);
        return sounds[key] ? key : '';
    };
    var fill = function(text, values) {
        return (text || '').replace(/\{\$a->(\w+)\}|\{\$a\}/g, function(found, key) {
            return key ? values[key] : values.a;
        });
    };
    var playing = '';
    var fade = null;
    var fadeTo = function(target, then) {
        clearInterval(fade);
        fade = setInterval(function() {
            var step = target > sound.volume ? 0.01 : -0.01;
            var value = sound.volume + step;
            if ((step > 0 && value >= target) || (step < 0 && value <= target)) {
                sound.volume = Math.max(0, Math.min(1, target));
                clearInterval(fade);
                if (then) {
                    then();
                }
                return;
            }
            sound.volume = Math.max(0, Math.min(1, value));
        }, 60);
    };
    var begin = function(key) {
        playing = key;
        sound.src = sound.canPlayType('audio/ogg; codecs=opus') ? sounds[key].ogg : sounds[key].m4a;
        sound.volume = 0;
        sound.play().catch(function() {
            return null;
        });
        fadeTo(LOW);
    };
    var soundOff = function() {
        if (sound && !sound.paused) {
            fadeTo(0, function() {
                sound.pause();
            });
        }
    };
    // From one part to the next: the same sound goes on; another one comes in after a short fade.
    var soundOn = function() {
        if (!sound) {
            return;
        }
        var key = wanted ? soundOf(current) : '';
        if (!key) {
            soundOff();
        } else if (key === playing && !sound.paused) {
            fadeTo(LOW);
        } else if (!sound.paused) {
            fadeTo(0, function() {
                begin(key);
            });
        } else {
            begin(key);
        }
    };
    var sayToggle = function() {
        if (!toggle) {
            return;
        }
        var key = soundOf(current);
        toggle.setAttribute('aria-pressed', wanted && key ? 'true' : 'false');
        toggle.querySelector('.ayv-oye-fondo-texto').textContent = wanted && key
            ? say(str.readingbackdropunder, sounds[key].the) : (str.voicebackdropnone || '');
    };
    // «Otro sonido»: what its reader chose for this part, and the rest; what is touched changes only for whoever listens.
    var drawPanel = function() {
        if (!panel || panel.hidden) {
            return;
        }
        var key = sounds[theirs(current)] ? theirs(current) : '';
        var who = config.who || str.readingsomeone || '';
        document.getElementById('ayv-oye-sonidos-dice').textContent = key
            ? fill(str.readingchose, {who: who, sound: sounds[key].the}) : fill(str.readingchosenone, {a: who});
        var box = document.getElementById('ayv-oye-sonidos-botones');
        box.textContent = '';
        var now = wanted ? soundOf(current) : '';
        var keys = Object.keys(sounds).filter(function(k) {
            return k !== key;
        });
        (key ? [key] : []).concat(keys, ['']).forEach(function(k) {
            var b = document.createElement('button');
            b.type = 'button';
            b.textContent = k ? sounds[k].name : (str.voicebackdropnone || '');
            if (k && k === key) {
                var small = document.createElement('small');
                small.textContent = ' ' + say(str.readingtheirs, who);
                b.appendChild(small);
            }
            b.setAttribute('aria-pressed', k === now ? 'true' : 'false');
            b.addEventListener('click', function() {
                put(mine(current), k === key ? null : (k || 'none'));
                wanted = true;
                put('ayv-lectura-sin-fondo', null);
                sayToggle();
                drawPanel();
                if (!voice.paused) {
                    soundOn();
                }
            });
            box.appendChild(b);
        });
    };
    if (toggle) {
        sayToggle();
        toggle.addEventListener('click', function() {
            wanted = !wanted;
            put('ayv-lectura-sin-fondo', wanted ? null : '1');
            sayToggle();
            drawPanel();
            if (wanted && !voice.paused) {
                soundOn();
            } else {
                soundOff();
            }
        });
    }
    if (other && panel) {
        other.addEventListener('click', function() {
            panel.hidden = !panel.hidden;
            other.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
            drawPanel();
        });
    }

    // Between two parts: «Sigue el capítulo 3», three, two, one; «Ahora no» stays where it is.
    var counting = null;
    var stopCount = function() {
        clearInterval(counting);
        counting = null;
        next.hidden = true;
    };
    var load = function(id, at, go) {
        stopCount();
        current = id;
        voice.src = button(id).getAttribute('data-url');
        if (at > 0) {
            voice.addEventListener('loadedmetadata', function seek() {
                voice.removeEventListener('loadedmetadata', seek);
                voice.currentTime = at;
            });
        }
        tell();
        mark();
        sayToggle();
        drawPanel();
        if (go) {
            voice.play().catch(function() {
                return null;
            });
        }
        send();
    };
    var countTo = function(id) {
        var b = button(id);
        var name = b.getAttribute('data-name');
        var key = wanted ? soundOf(id) : '';
        document.getElementById('ayv-oye-sigue-texto').textContent = say(str.readingnext,
            b.getAttribute('data-title').toLowerCase() + (name ? ', «' + name + '»' : ''))
            + (key ? ' · ' + say(str.readingwith, sounds[key].the) : '');
        var count = document.getElementById('ayv-oye-cuenta');
        var remaining = 3;
        count.textContent = String(remaining);
        next.hidden = false;
        counting = setInterval(function() {
            remaining--;
            count.textContent = String(remaining);
            if (remaining <= 0) {
                load(id, 0, true);
            }
        }, 1000);
    };
    document.getElementById('ayv-oye-ahora-no').addEventListener('click', function() {
        stopCount();
        soundOff();
    });

    play.addEventListener('click', function() {
        if (voice.paused) {
            stopCount();
            voice.play().catch(function() {
                return null;
            });
        } else {
            voice.pause();
        }
    });
    buttons.forEach(function(b) {
        b.addEventListener('click', function() {
            load(Number(b.getAttribute('data-id')), 0, true);
        });
    });
    // The bar of the part: a touch or a drag goes there; the arrows, five seconds.
    var goTo = function(event) {
        var box = bar.getBoundingClientRect();
        var share = Math.max(0, Math.min(1, (event.clientX - box.left) / box.width));
        voice.currentTime = share * length();
        paint();
    };
    var dragging = false;
    bar.addEventListener('pointerdown', function(event) {
        dragging = true;
        if (bar.setPointerCapture) {
            bar.setPointerCapture(event.pointerId);
        }
        goTo(event);
    });
    bar.addEventListener('pointermove', function(event) {
        if (dragging) {
            goTo(event);
        }
    });
    bar.addEventListener('pointerup', function() {
        dragging = false;
        keep();
    });
    bar.addEventListener('keydown', function(event) {
        var steps = {ArrowRight: 5, ArrowUp: 5, ArrowLeft: -5, ArrowDown: -5, PageUp: 30, PageDown: -30};
        if (event.key in steps) {
            voice.currentTime = Math.max(0, Math.min(length(), (voice.currentTime || 0) + steps[event.key]));
        } else if (event.key === 'Home') {
            voice.currentTime = 0;
        } else if (event.key === 'End') {
            voice.currentTime = Math.max(0, length() - 1);
        } else {
            return;
        }
        event.preventDefault();
        paint();
        keep();
    });

    // Back where the listener was: the part is already loaded; the second, once it knows how long it is.
    var at = Number(config.at) || 0;
    if (at > 0) {
        voice.addEventListener('loadedmetadata', function seek() {
            voice.removeEventListener('loadedmetadata', seek);
            voice.currentTime = at;
        });
    }
    voice.addEventListener('play', function() {
        soundOn();
        tell();
        mark();
    });
    voice.addEventListener('pause', function() {
        // Between one part and the next the sound goes on; it stops when the listener stops.
        if (!voice.ended) {
            soundOff();
        }
        mark();
        send();
    });
    voice.addEventListener('timeupdate', function() {
        paint();
        keep();
    });
    // Once the file says how long it lasts, the index says the same as the player.
    voice.addEventListener('loadedmetadata', function() {
        if (isFinite(voice.duration) && voice.duration > 0) {
            var row = button(current);
            row.setAttribute('data-seconds', String(Math.floor(voice.duration)));
            row.querySelector('.ayv-oye-dura').textContent = mmss(voice.duration);
        }
        paint();
    });
    voice.addEventListener('durationchange', paint);
    voice.addEventListener('ended', function() {
        var index = ids.indexOf(current);
        mark();
        if (index >= 0 && index < ids.length - 1) {
            countTo(ids[index + 1]);
        } else {
            soundOff();
            send();
        }
    });
    mark();
})();
