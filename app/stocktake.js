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
 * Ayvurenda: a stocktake on the shelves. Each code read (by a scanner, the camera or the phone paired) joins a queue kept
 * in the browser, so that nothing read is lost where the shelves have no network; the queue goes to the server a few
 * codes at a time whenever it can. Each answer says whether the copy is where it should be.
 *
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';
    var config = window.AYV || {};
    var str = config.strings || {};
    var form = document.getElementById('ayv-scan');
    var input = document.getElementById('ayv-code');
    var log = document.getElementById('ayv-log');
    var here = document.getElementById('ayv-here');
    var progress = document.getElementById('ayv-progress');
    var pending = document.getElementById('ayv-pending');
    if (!form || !input || !log || !window.fetch) {
        return;
    }
    var LETTERS = 'TRWAGMYFPDXBNJZSQVHLCKE';
    var KEY = 'ayv-stocktake-' + config.stocktake;
    var SOUND = {seen: 'ok', again: '', misplaced: 'look', lent: 'look', found: 'look', withdrawn: 'look',
        otherlibrary: 'look', unknown: 'no', badletter: 'no'};
    var sending = false;
    var audio = null;

    var load = function() {
        try {
            return JSON.parse(window.localStorage.getItem(KEY) || '[]');
        } catch (e) {
            return [];
        }
    };
    var queue = load();
    var save = function() {
        try {
            window.localStorage.setItem(KEY, JSON.stringify(queue));
        } catch (e) {
            // Without storage the queue lives as long as the page.
        }
    };

    var tone = function(kind) {
        try {
            audio = audio || new (window.AudioContext || window.webkitAudioContext)();
        } catch (e) {
            return;
        }
        var plan = {ok: [[1320, 0, 0.05]], look: [[660, 0, 0.09], [880, 0.11, 0.09]], no: [[220, 0, 0.22], [233, 0, 0.22]]};
        (plan[kind] || []).forEach(function(note) {
            var osc = audio.createOscillator();
            var gain = audio.createGain();
            var start = audio.currentTime + note[1];
            osc.frequency.value = note[0];
            osc.type = kind === 'no' ? 'square' : 'sine';
            gain.gain.setValueAtTime(0.0001, start);
            gain.gain.exponentialRampToValueAtTime(kind === 'no' ? 0.08 : 0.15, start + 0.01);
            gain.gain.exponentialRampToValueAtTime(0.0001, start + note[2]);
            osc.connect(gain);
            gain.connect(audio.destination);
            osc.start(start);
            osc.stop(start + note[2] + 0.02);
        });
    };

    var control = function(code) {
        var match = /^(\d+)([A-Za-z])$/.exec(code);
        if (!config.controlletter || !match) {
            return true;
        }
        var rest = 0;
        for (var i = 0; i < match[1].length; i++) {
            rest = (rest * 10 + Number(match[1][i])) % 23;
        }
        return LETTERS[rest] === match[2].toUpperCase();
    };

    var el = function(tag, cls, text) {
        var node = document.createElement(tag);
        if (cls) {
            node.className = cls;
        }
        if (text !== undefined && text !== null) {
            node.textContent = text;
        }
        return node;
    };

    var fill = function(text, a) {
        text = String(text || '');
        if (a !== null && typeof a === 'object') {
            return text.replace(/\{\$a->(\w+)\}/g, function(all, key) {
                return a[key] !== undefined ? a[key] : '';
            });
        }
        return text.replace('{$a}', a);
    };

    var counts = function(c) {
        if (progress && c) {
            progress.textContent = fill(str.stocktakeprogress, c);
        }
    };

    var showPending = function(offline) {
        if (!pending) {
            return;
        }
        pending.hidden = !queue.length;
        pending.textContent = queue.length ? fill(offline ? str.stocktakeoffline : str.stocktakepending, queue.length) : '';
    };

    var post = function(params) {
        var body = new URLSearchParams();
        body.set('sesskey', config.sesskey);
        body.set('library', config.library);
        body.set('id', config.stocktake);
        Object.keys(params).forEach(function(key) {
            body.set(key, params[key]);
        });
        return fetch(config.ajaxurl, {method: 'POST', credentials: 'same-origin', body: body}).then(function(response) {
            if (!response.ok) {
                throw new Error(response.status);
            }
            return response.json();
        });
    };

    var line = function(answer) {
        var li = el('li', 'ayv-line ayv-stock-' + answer.kind + ' ayv-sound-' + (SOUND[answer.kind] || 'ok'));
        var text = answer.kind === 'badletter' ? str.deskbadletter
            : fill(str['stocktake' + answer.kind], {title: answer.title || answer.code, where: answer.where});
        li.appendChild(el('p', 'ayv-line-text', text));
        if (answer.kind === 'lent') {
            var actions = el('div', 'ayv-line-actions');
            var b = el('button', 'ayv-line-button ayv-line-quiet', str.stocktakereturn);
            b.type = 'button';
            b.addEventListener('click', function() {
                b.disabled = true;
                post({action: 'return', code: answer.code}).then(function(returned) {
                    actions.textContent = '';
                    li.appendChild(el('p', 'ayv-line-note', returned.message));
                    counts(returned.counts);
                    return returned;
                }).catch(function() {
                    b.disabled = false;
                    tone('no');
                });
                input.focus();
            });
            actions.appendChild(b);
            li.appendChild(actions);
        }
        log.insertBefore(li, log.firstChild);
        while (log.childNodes.length > 60) {
            log.removeChild(log.lastChild);
        }
        if (SOUND[answer.kind]) {
            tone(SOUND[answer.kind]);
        }
    };

    // Sends what is waiting, a few at a time; without network it waits and tries again.
    var flush = function() {
        if (sending || !queue.length) {
            showPending(false);
            return;
        }
        sending = true;
        var batch = queue.slice(0, 50);
        post({action: 'read', reads: JSON.stringify(batch)}).then(function(answer) {
            queue = queue.slice(batch.length);
            save();
            (answer.answers || []).forEach(line);
            counts(answer.counts);
            sending = false;
            showPending(false);
            if (queue.length) {
                flush();
            }
            return answer;
        }).catch(function() {
            sending = false;
            showPending(true);
            setTimeout(flush, 5000);
        });
    };

    var read = function(code) {
        code = String(code || '').trim();
        if (!code) {
            return;
        }
        if (!control(code)) {
            line({kind: 'badletter', code: code});
            return;
        }
        queue.push({code: code, here: here ? Number(here.value || 0) : 0, t: Date.now()});
        save();
        flush();
    };

    form.addEventListener('submit', function(event) {
        event.preventDefault();
        var code = input.value;
        input.value = '';
        read(code);
    });
    window.addEventListener('online', flush);
    if (window.AYVCamera) {
        window.AYVCamera.wire(config, read, function() {
            input.focus();
        });
    }
    showPending(false);
    flush();
    input.focus();
})();
