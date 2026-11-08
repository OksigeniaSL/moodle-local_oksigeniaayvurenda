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
 * Ayvurenda: the self-loan post. One field, always ready for the scanner: a card opens, each book goes out, and it closes by
 * itself if nobody reads anything for a while, so the next person never finds someone else's name on the screen.
 *
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';
    var config = window.AYV || {};
    var str = config.strings || {};
    var root = document.getElementById('ayv-self');
    var form = document.getElementById('ayv-self-form');
    var SAVED = 'ayv-self-key';
    // The address opens the post with its key after a «#», which never reaches the server: the page takes it from there
    // (or from what this machine kept), wipes it from the address bar and sends it by POST; the server keeps it in a
    // cookie. A key the server refused is forgotten here too.
    var open = function(key) {
        var send = document.createElement('form');
        send.method = 'post';
        send.action = window.location.pathname;
        send.hidden = true;
        var field = document.createElement('input');
        field.type = 'hidden';
        field.name = 'key';
        field.value = key;
        send.appendChild(field);
        document.body.appendChild(send);
        send.submit();
    };
    var hashed = '';
    try {
        hashed = new URLSearchParams(window.location.hash.replace(/^#/, '')).get('key') || '';
        if (window.location.hash) {
            window.history.replaceState(null, '', window.location.pathname);
        }
        if (config.selfrefused) {
            window.localStorage.removeItem(SAVED);
        }
    } catch (e) {
        hashed = '';
    }
    // A new address typed on a machine already at its post changes only what follows the «#»: the page reloads with it.
    window.addEventListener('hashchange', function() {
        window.location.reload();
    });
    // A new address beats what this machine had, even an open post or a key just refused.
    if (hashed && hashed !== config.selfkey && /^[A-Za-z0-9]+$/.test(hashed)) {
        open(hashed);
        return;
    }
    if (!config.selfkey) {
        var gone = document.getElementById('ayv-self-gone');
        var kept = '';
        try {
            kept = config.selfrefused ? '' : window.localStorage.getItem(SAVED) || '';
        } catch (e) {
            kept = '';
        }
        if (kept && /^[A-Za-z0-9]+$/.test(kept)) {
            open(kept);
        } else if (gone) {
            gone.hidden = false;
        }
        return;
    }
    if (!root || !form) {
        return;
    }
    try {
        window.localStorage.setItem(SAVED, config.selfkey);
        window.history.replaceState(null, '', window.location.pathname);
    } catch (e) {
        // With nowhere to keep it, the address stays as it is: reloading has to go on working.
    }
    var title = document.getElementById('ayv-self-title');
    var hint = document.getElementById('ayv-self-hint');
    var said = document.getElementById('ayv-self-said');
    var list = document.getElementById('ayv-self-list');
    var field = document.getElementById('ayv-self-code');
    var done = document.getElementById('ayv-self-done');
    var camera = document.getElementById('ayv-self-camera');
    var type = document.getElementById('ayv-self-type');
    // A visit ends alone after a minute and a half without reading anything, so the next one does not see a first name;
    // fifteen seconds before, the screen asks, and any touch or key gives more time.
    var LONELY = 90000;
    var WARN = 15000;
    var GOODBYE = 6000;
    var warned = null;
    var still = document.createElement('p');
    still.className = 'ayv-self-still';
    still.setAttribute('role', 'status');
    still.hidden = true;
    still.textContent = str.selfstill || '';
    root.appendChild(still);
    var ticket = '';
    var name = '';
    var lonely = null;
    var goodbye = null;
    var busy = false;
    var visit = 0;
    var queue = [];
    var audio = null;

    var tone = function(kind) {
        try {
            audio = audio || new (window.AudioContext || window.webkitAudioContext)();
        } catch (e) {
            return;
        }
        var plan = {ok: [[1320, 0, 0.07]], look: [[660, 0, 0.09], [880, 0.11, 0.09]], no: [[220, 0, 0.2]]};
        (plan[kind] || []).forEach(function(note) {
            var osc = audio.createOscillator();
            var gain = audio.createGain();
            var start = audio.currentTime + note[1];
            osc.frequency.value = note[0];
            gain.gain.setValueAtTime(0.0001, start);
            gain.gain.exponentialRampToValueAtTime(0.06, start + 0.01);
            gain.gain.exponentialRampToValueAtTime(0.0001, start + note[2]);
            osc.connect(gain);
            gain.connect(audio.destination);
            osc.start(start);
            osc.stop(start + note[2] + 0.02);
        });
    };

    var show = function(text, sound) {
        said.textContent = text || '';
        said.hidden = !text;
        said.className = 'ayv-self-said ayv-self-said-' + (sound || 'ok');
    };

    var ready = function() {
        if (document.activeElement !== field) {
            field.focus({preventScroll: true});
        }
    };

    var idle = function() {
        clearTimeout(lonely);
        clearTimeout(goodbye);
        ticket = '';
        name = '';
        root.setAttribute('data-state', 'idle');
        title.textContent = str.selfidle;
        hint.textContent = str.selfidlereturn || '';
        hint.hidden = !str.selfidlereturn;
        list.textContent = '';
        list.hidden = true;
        done.hidden = true;
        show('');
        ready();
    };

    // The end of a visit: what was taken stays a moment, and the screen is clean for whoever comes next.
    var end = function() {
        clearTimeout(lonely);
        visit++;
        var who = name;
        ticket = '';
        name = '';
        root.setAttribute('data-state', 'bye');
        title.textContent = who ? String(str.selfbye).replace('{$a}', function() {
            return who;
        }) : str.selfbyenoname;
        hint.hidden = true;
        done.hidden = true;
        show('');
        goodbye = setTimeout(idle, GOODBYE);
        ready();
    };

    var wait = function() {
        clearTimeout(lonely);
        clearTimeout(warned);
        still.hidden = true;
        if (ticket) {
            warned = setTimeout(function() {
                still.hidden = false;
            }, LONELY - WARN);
            lonely = setTimeout(end, LONELY);
        }
    };
    ['pointerdown', 'keydown'].forEach(function(name) {
        document.addEventListener(name, function() {
            if (ticket) {
                wait();
            }
        });
    });

    var line = function(answer) {
        var li = document.createElement('li');
        li.className = 'ayv-self-book';
        var book = document.createElement('span');
        book.className = 'ayv-self-book-title';
        book.textContent = answer.title;
        var due = document.createElement('span');
        due.className = 'ayv-self-book-due';
        due.textContent = String(str.selfdue).replace('{$a}', function() {
            return answer.due;
        });
        li.appendChild(book);
        li.appendChild(due);
        list.insertBefore(li, list.firstChild);
        list.hidden = false;
    };

    var answered = function(answer, sent) {
        if (answer.gone) {
            document.body.textContent = str.selfgonenow;
            return;
        }
        // Sent before «All done»: it says what happened, but it does not open that visit again for the next person.
        if (sent !== visit) {
            answer = {sound: answer.sound, text: answer.title ? answer.text : '', state: 'idle'};
        }
        tone(answer.sound);
        clearTimeout(goodbye);
        if (answer.state === 'reader' && answer.ticket) {
            var opened = !ticket;
            ticket = answer.ticket;
            root.setAttribute('data-state', 'reader');
            if (answer.text && answer.name !== undefined) {
                name = answer.name;
                list.textContent = '';
                list.hidden = true;
                title.textContent = answer.text;
                hint.textContent = str.selfnext;
                hint.hidden = false;
                show('');
            } else if (answer.title) {
                line(answer);
                show('');
            } else {
                show(answer.text, answer.sound);
            }
            done.hidden = false;
            if (opened) {
                hint.hidden = false;
            }
        } else {
            ticket = '';
            if (root.getAttribute('data-state') !== 'idle') {
                idle();
            }
            show(answer.text, answer.sound);
            // A word for whoever gave a book back goes away by itself: the next person starts clean.
            goodbye = setTimeout(idle, GOODBYE);
        }
        wait();
        ready();
    };

    var next = function() {
        if (busy || !queue.length) {
            return;
        }
        busy = true;
        var body = new URLSearchParams();
        body.set('key', config.selfkey);
        body.set('code', queue.shift());
        body.set('ticket', ticket);
        var sent = visit;
        fetch(config.selfurl, {method: 'POST', credentials: 'omit', body: body}).then(function(response) {
            return response.json();
        }).then(function(answer) {
            answered(answer, sent);
        }).catch(function() {
            tone('no');
            show(str.selfoffline, 'no');
        }).then(function() {
            busy = false;
            next();
        });
    };

    var read = function(code) {
        code = String(code || '').trim();
        if (code !== '') {
            queue.push(code);
            next();
        }
    };

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        read(field.value);
        field.value = '';
    });
    done.addEventListener('click', end);
    // On a tablet the scanner is a keyboard: the one on the screen only comes out when asked for.
    type.addEventListener('click', function() {
        field.setAttribute('inputmode', 'text');
        field.blur();
        field.focus();
    });
    if (window.AYVCamera && window.AYVCamera.available()) {
        camera.hidden = false;
        camera.addEventListener('click', function() {
            window.AYVCamera.open({onCode: read, strings: str, zxing: config.zxing, keep: true});
        });
    }
    // Whatever is touched, the scanner still goes to the field.
    document.addEventListener('click', function(e) {
        if (!e.target.closest('dialog')) {
            ready();
        }
    });
    idle();
})();
