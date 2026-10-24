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
 * Ayvurenda: the desk. One field, always ready, and no modes: what is read decides. A scanner types the code and
 * Enter; reads that come while the last one travels wait their turn. Every line can be undone; only red cards ask
 * for a tap, and the scanner cannot give it. Shortcuts need Alt or Ctrl, so a scanner never fires them.
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
    var panel = document.getElementById('ayv-reader');
    var today = document.getElementById('ayv-today');
    if (!form || !input || !log || !panel || !window.fetch) {
        return;
    }
    var LETTERS = 'TRWAGMYFPDXBNJZSQVHLCKE';
    var readerid = 0;
    var queue = [];
    var busy = false;
    var lastline = null;
    var audio = null;

    // Sounds: short, made here, the same on every machine. The scanner's own beep should be off.
    var tone = function(kind) {
        try {
            audio = audio || new (window.AudioContext || window.webkitAudioContext)();
        } catch (e) {
            return;
        }
        var plan = {ok: [[1320, 0, 0.07]], look: [[660, 0, 0.09], [880, 0.11, 0.09]], no: [[220, 0, 0.22], [233, 0, 0.22]]};
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

    var button = function(label, cls, onclick) {
        var b = el('button', 'ayv-line-button ' + (cls || ''), label);
        b.type = 'button';
        b.addEventListener('click', function() {
            onclick(b);
            input.focus();
        });
        return b;
    };

    var post = function(params) {
        var body = new URLSearchParams();
        body.set('library', config.library);
        body.set('sesskey', config.sesskey);
        Object.keys(params).forEach(function(key) {
            body.set(key, params[key]);
        });
        return fetch(config.ajaxurl, {method: 'POST', credentials: 'same-origin', body: body})
            .then(function(response) {
                return response.json();
            })
            .then(function(answer) {
                if (answer && answer.error) {
                    throw new Error(answer.error);
                }
                return answer;
            });
    };

    var showReader = function(reader) {
        panel.textContent = '';
        if (!reader) {
            readerid = 0;
            panel.hidden = true;
            if (today) {
                today.hidden = false;
            }
            return;
        }
        readerid = reader.id;
        panel.hidden = false;
        if (today) {
            today.hidden = true;
        }
        var head = el('div', 'ayv-reader-head');
        var who = el('div', 'ayv-reader-who');
        who.appendChild(el('span', 'ayv-reader-name', reader.name));
        if (reader.group) {
            who.appendChild(el('span', 'ayv-reader-group', reader.group));
        }
        head.appendChild(who);
        head.appendChild(button(str.deskclose, 'ayv-line-quiet', function() {
            showReader(null);
        }));
        panel.appendChild(head);
        if (reader.latecount > 0) {
            panel.appendChild(el('p', 'ayv-reader-late', str.desklate));
        }
        if (!reader.loans.length) {
            panel.appendChild(el('p', 'ayv-reader-none', str.desknoloans));
            return;
        }
        panel.appendChild(el('p', 'ayv-reader-has', str.deskloans));
        var list = el('ul', 'ayv-reader-loans');
        reader.loans.forEach(function(loan) {
            var item = el('li', loan.late ? 'ayv-loan ayv-loan-late' : 'ayv-loan');
            item.appendChild(el('span', 'ayv-loan-title', loan.title));
            item.appendChild(el('span', 'ayv-loan-due', loan.late || loan.due));
            list.appendChild(item);
        });
        panel.appendChild(list);
    };

    var line = function(answer, code) {
        var li = el('li', 'ayv-line ayv-line-' + answer.kind + ' ayv-sound-' + (answer.sound || 'ok'));
        li.appendChild(el('p', 'ayv-line-text', answer.message));
        var actions = el('div', 'ayv-line-actions');
        var undo = function(what, extra) {
            actions.appendChild(button(str.deskundo, 'ayv-line-quiet', function(b) {
                b.disabled = true;
                var params = {action: 'undo', loanid: answer.loanid, what: what};
                Object.keys(extra || {}).forEach(function(k) {
                    params[k] = extra[k];
                });
                post(params).then(function(undone) {
                    li.classList.add('ayv-line-undone');
                    actions.textContent = '';
                    li.appendChild(el('p', 'ayv-line-note', undone.message));
                    if (readerid && undone.reader && undone.reader.id === readerid) {
                        showReader(undone.reader);
                    }
                    tone('look');
                    return undone;
                }).catch(function() {
                    tone('no');
                    b.disabled = false;
                });
            }));
        };
        if (answer.kind === 'lent' && answer.loanid) {
            undo('lent');
        } else if (answer.kind === 'renewed') {
            undo('renewed', {previousdue: answer.previousdue});
        } else if (answer.kind === 'returned' && answer.loanid) {
            var notes = el('div', 'ayv-notes');
            ['loved', 'unfinished', 'more'].forEach(function(note, index) {
                var chip = button(str['note' + note], 'ayv-note', function(b) {
                    var on = b.getAttribute('aria-pressed') !== 'true';
                    post({action: 'note', loanid: answer.loanid, note: on ? note : ''}).then(function() {
                        notes.querySelectorAll('.ayv-note').forEach(function(other) {
                            other.setAttribute('aria-pressed', 'false');
                        });
                        b.setAttribute('aria-pressed', on ? 'true' : 'false');
                        return on;
                    }).catch(function() {
                        tone('no');
                    });
                });
                chip.setAttribute('aria-pressed', 'false');
                chip.setAttribute('aria-keyshortcuts', 'Alt+' + (index + 1));
                notes.appendChild(chip);
            });
            actions.appendChild(notes);
            if (answer.canrenew) {
                var renew = button(str.deskrenew, 'ayv-line-renew', function(b) {
                    b.disabled = true;
                    post({action: 'renew', loanid: answer.loanid}).then(function(renewed) {
                        li.classList.add('ayv-line-undone');
                        actions.textContent = '';
                        add(renewed);
                        return renewed;
                    }).catch(function() {
                        tone('no');
                        b.disabled = false;
                    });
                });
                renew.setAttribute('aria-keyshortcuts', 'Alt+A');
                actions.appendChild(renew);
            }
            undo('returned');
        } else if (answer.kind === 'stop' && answer.forceable) {
            actions.appendChild(button(str.desklendanyway, 'ayv-line-danger', function(b) {
                b.disabled = true;
                li.classList.add('ayv-line-undone');
                actions.textContent = '';
                enqueue(answer.code, true);
            }));
            actions.appendChild(button(str.deskdismiss, 'ayv-line-quiet', function() {
                li.classList.add('ayv-line-undone');
                actions.textContent = '';
            }));
        } else if (answer.kind === 'choose' && answer.readers) {
            answer.readers.forEach(function(reader) {
                actions.appendChild(button(reader.name + (reader.group ? ' · ' + reader.group : ''), 'ayv-line-choice', function() {
                    post({action: 'reader', readerid: reader.id}).then(function(found) {
                        li.classList.add('ayv-line-undone');
                        actions.textContent = '';
                        showReader(found.reader);
                        tone('ok');
                        return found;
                    }).catch(function() {
                        tone('no');
                    });
                }));
            });
        }
        if (actions.childNodes.length) {
            li.appendChild(actions);
        }
        log.insertBefore(li, log.firstChild);
        lastline = li;
        return li;
    };

    var add = function(answer, code) {
        tone(answer.sound || 'ok');
        if (answer.kind === 'reader') {
            showReader(answer.reader);
            return;
        }
        if (answer.reader && (!readerid || answer.reader.id === readerid)) {
            showReader(answer.reader);
        }
        line(answer, code);
    };

    var next = function() {
        if (busy || !queue.length) {
            return;
        }
        busy = true;
        var job = queue.shift();
        post({action: 'scan', code: job.code, readerid: readerid, force: job.force ? 1 : 0})
            .then(function(answer) {
                add(answer, job.code);
                return answer;
            })
            .catch(function() {
                tone('no');
                line({kind: 'stop', sound: 'no', message: str.deskerror}, job.code);
            })
            .then(function() {
                busy = false;
                next();
                return null;
            });
    };

    var enqueue = function(code, force) {
        queue.push({code: code, force: !!force});
        next();
    };

    var read = function(code) {
        if (!control(code)) {
            tone('no');
            line({kind: 'badletter', sound: 'no', message: str.deskbadletter}, code);
            return;
        }
        enqueue(code, false);
    };

    form.addEventListener('submit', function(event) {
        event.preventDefault();
        var code = input.value.trim();
        input.value = '';
        if (code) {
            read(code);
        }
    });

    if (window.AYVCamera) {
        window.AYVCamera.wire(config, read, function() {
            input.focus();
        });
    }

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && readerid) {
            showReader(null);
            event.preventDefault();
            return;
        }
        if (!lastline || !(event.altKey || event.ctrlKey)) {
            return;
        }
        var key = event.key.toLowerCase();
        var target = null;
        if (event.altKey && ['1', '2', '3'].indexOf(key) >= 0) {
            target = lastline.querySelectorAll('.ayv-note')[Number(key) - 1];
        } else if (event.altKey && key === 'a') {
            target = lastline.querySelector('.ayv-line-renew');
        } else if (event.ctrlKey && key === 'z' && document.activeElement === input && input.value === '') {
            var undos = lastline.querySelectorAll('.ayv-line-quiet');
            target = undos.length ? undos[undos.length - 1] : null;
        }
        if (target && !target.disabled) {
            event.preventDefault();
            target.click();
        }
    });

    // The field keeps the focus: after a click anywhere that is not a button or a link, it comes back.
    document.addEventListener('click', function(event) {
        if (!event.target.closest('button, a, input, select, textarea')) {
            input.focus();
        }
    });
    input.focus();
})();
