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

    // A reader's photo, or the initial of their name in a round.
    var face = function(reader, cls) {
        var box = el('span', cls);
        box.setAttribute('aria-hidden', 'true');
        if (reader.photo) {
            var img = document.createElement('img');
            img.src = reader.photo;
            img.alt = '';
            box.appendChild(img);
        } else {
            box.textContent = reader.initial || '';
        }
        return box;
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
        head.appendChild(face(reader, 'ayv-reader-face'));
        var who = el('div', 'ayv-reader-who');
        who.appendChild(el('span', 'ayv-reader-name', reader.name));
        if (reader.group) {
            who.appendChild(el('span', 'ayv-reader-group', reader.group));
        }
        if (reader.url) {
            // Their record opens apart, so the desk stays as it is.
            var record = el('a', 'ayv-reader-record', str.profileview);
            record.href = reader.url;
            record.target = '_blank';
            record.rel = 'noopener';
            who.appendChild(record);
        }
        head.appendChild(who);
        head.appendChild(button(str.deskclose, 'ayv-line-quiet', function() {
            showReader(null);
        }));
        panel.appendChild(head);
        if (reader.latecount > 0) {
            panel.appendChild(el('p', 'ayv-reader-late', str.desklate));
        }
        // At a textbook bank: the set of their grade, what is still to give, and the contract for the family.
        if (reader.bank) {
            var set = el('div', 'ayv-reader-bank');
            set.appendChild(el('p', 'ayv-reader-has', str.deskbankset.replace('{$a}', reader.bank.set)));
            set.appendChild(el('p', reader.bank.missing ? 'ayv-reader-bank-missing' : 'ayv-reader-bank-done',
                reader.bank.missing ? str.deskbankmissing.replace('{$a}', reader.bank.pending.join(', ')) : str.deskbankdone));
            var contract = el('a', 'ayv-button ayv-button-quiet', str.deskbankcontract);
            contract.href = reader.bank.contract;
            contract.target = '_blank';
            contract.rel = 'noopener';
            set.appendChild(contract);
            panel.appendChild(set);
        }
        // What they reserved: first what is set aside for them, to hand it over now.
        if (reader.holds && reader.holds.length) {
            panel.appendChild(el('p', 'ayv-reader-has', str.deskholds));
            var holds = el('ul', 'ayv-reader-loans ayv-reader-holds');
            reader.holds.forEach(function(hold) {
                var item = el('li', hold.ready ? 'ayv-loan ayv-hold-ready' : 'ayv-loan');
                item.appendChild(el('span', 'ayv-loan-title', hold.title));
                item.appendChild(el('span', 'ayv-loan-due', hold.ready ? str.deskholdready.replace('{$a}', hold.until)
                    : str.deskholdwaiting));
                holds.appendChild(item);
            });
            panel.appendChild(holds);
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

    // Material: its state when it goes or comes back, already noted as it was; a tap changes it.
    var states = function(answer) {
        var box = el('div', 'ayv-notes ayv-states');
        box.setAttribute('role', 'group');
        box.setAttribute('aria-label', answer.material.when === 'out' ? str.deskconditionout : str.deskconditionin);
        box.appendChild(el('span', 'ayv-states-label', answer.material.when === 'out' ? str.deskconditionout
            : str.deskconditionin));
        answer.material.conditions.forEach(function(condition, index) {
            var chip = button(condition.label, 'ayv-note', function(b) {
                post({action: 'condition', loanid: answer.loanid, when: answer.material.when, condition: condition.value})
                    .then(function() {
                        box.querySelectorAll('.ayv-note').forEach(function(other) {
                            other.setAttribute('aria-pressed', 'false');
                        });
                        b.setAttribute('aria-pressed', 'true');
                        return condition.value;
                    }).catch(function() {
                        tone('no');
                    });
            });
            chip.setAttribute('aria-pressed', condition.value === answer.material.current ? 'true' : 'false');
            if (index < 3) {
                chip.setAttribute('aria-keyshortcuts', 'Alt+' + (index + 1));
            }
            box.appendChild(chip);
        });
        return box;
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
            if (answer.material) {
                actions.appendChild(states(answer));
            }
            undo('lent');
        } else if (answer.kind === 'renewed') {
            undo('renewed', {previousdue: answer.previousdue});
        } else if (answer.kind === 'returned' && answer.loanid && answer.material) {
            actions.appendChild(states(answer));
            undo('returned');
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
        } else if (answer.kind === 'needreader' && answer.room) {
            // A book from the tables: read here, without going home. One touch counts it, and it can be undone.
            var room = button(str.deskroom, 'ayv-line-room', function(b) {
                b.disabled = true;
                post({action: 'room', itemid: answer.itemid}).then(function(read) {
                    actions.textContent = '';
                    li.querySelector('.ayv-line-text').textContent = read.message;
                    tone('ok');
                    actions.appendChild(button(str.deskundo, 'ayv-line-quiet', function(u) {
                        u.disabled = true;
                        post({action: 'roomundo', roomid: read.roomid}).then(function(undone) {
                            li.classList.add('ayv-line-undone');
                            actions.textContent = '';
                            li.appendChild(el('p', 'ayv-line-note', undone.message));
                            return undone;
                        }).catch(function() {
                            tone('no');
                            u.disabled = false;
                        });
                    }));
                    return read;
                }).catch(function() {
                    tone('no');
                    b.disabled = false;
                });
            });
            room.setAttribute('aria-keyshortcuts', 'Alt+S');
            actions.appendChild(room);
        } else if (answer.kind === 'stop' && answer.forceable) {
            // The safe answer first: not lending.
            var held = answer.reason === 'held';
            actions.appendChild(button(held ? str.deskkeepheld : str.deskdismiss, 'ayv-line-quiet', function() {
                li.classList.add('ayv-line-undone');
                actions.textContent = '';
            }));
            actions.appendChild(button(held ? str.desklendheld : str.desklendanyway, 'ayv-line-danger', function(b) {
                b.disabled = true;
                li.classList.add('ayv-line-undone');
                actions.textContent = '';
                enqueue(answer.code, true);
            }));
        } else if (answer.kind === 'choose' && answer.readers) {
            answer.readers.forEach(function(reader) {
                var choice = button(reader.name + (reader.group ? ' · ' + reader.group : ''), 'ayv-line-choice', function() {
                    post({action: 'reader', readerid: reader.id}).then(function(found) {
                        li.classList.add('ayv-line-undone');
                        actions.textContent = '';
                        showReader(found.reader);
                        tone('ok');
                        return found;
                    }).catch(function() {
                        tone('no');
                    });
                });
                // Two readers of the same name: their photos tell them apart.
                choice.insertBefore(face(reader, 'ayv-choice-face'), choice.firstChild);
                actions.appendChild(choice);
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

    // Without a network the desk goes on: each scan is kept in this browser, in order and with its moment, and passed
    // to the server as soon as the network is back; what it answers then is shown as if it had just happened.
    var pendingkey = 'ayv-desk-pending-' + config.library;
    var pending = [];
    try {
        pending = JSON.parse(window.localStorage.getItem(pendingkey) || '[]') || [];
    } catch (e) {
        pending = [];
    }
    var offline = false;
    var replaying = false;
    var bar = el('p', 'ayv-desk-offline');
    bar.setAttribute('role', 'status');
    bar.hidden = true;
    form.parentNode.insertBefore(bar, form.nextSibling);
    var keepPending = function() {
        try {
            window.localStorage.setItem(pendingkey, JSON.stringify(pending));
        } catch (e) {
            // Without storage they are kept while the page is open.
        }
        bar.hidden = !pending.length;
        bar.textContent = pending.length === 1 ? str.deskofflineone : (str.deskofflinemany || '').replace('{$a}', pending.length);
    };
    var lostNetwork = function(error) {
        return !navigator.onLine || error instanceof TypeError || error instanceof SyntaxError;
    };
    var keep = function(job) {
        pending.push({code: job.code, force: job.force, readerid: readerid, at: job.at || Math.floor(Date.now() / 1000)});
        keepPending();
        tone('look');
        line({kind: 'offline', sound: 'look', message: str.deskoffline}, job.code);
    };
    var replay = function() {
        if (replaying || !pending.length) {
            return;
        }
        replaying = true;
        var reader = pending[0].readerid || 0;
        var passed = 0;
        var step = function() {
            if (!pending.length) {
                offline = false;
                replaying = false;
                keepPending();
                line({kind: 'online', sound: 'ok', message: (str.deskbackonline || '').replace('{$a}', passed)}, '');
                return null;
            }
            var job = pending[0];
            return post({action: 'scan', code: job.code, readerid: reader, force: job.force ? 1 : 0, at: job.at})
                .then(function(answer) {
                    pending.shift();
                    passed++;
                    keepPending();
                    if (answer.reader) {
                        reader = answer.reader.id;
                    }
                    add(answer, job.code);
                    return step();
                })
                .catch(function(error) {
                    replaying = false;
                    if (!lostNetwork(error)) {
                        // The server answered with an error: that scan is dropped and shown; the rest go on.
                        pending.shift();
                        keepPending();
                        line({kind: 'stop', sound: 'no', message: str.deskerror}, job.code);
                        replaying = true;
                        return step();
                    }
                    return null;
                });
        };
        step();
    };
    window.addEventListener('online', replay);
    setInterval(function() {
        if (pending.length && !busy) {
            replay();
        }
    }, 15000);
    keepPending();
    // Scans kept from before (the page was closed without a network): they go first.
    if (pending.length) {
        replay();
    }

    var next = function() {
        if (busy || !queue.length) {
            return;
        }
        busy = true;
        var job = queue.shift();
        // While scans wait for the network, new ones wait behind them, in order.
        if (offline || pending.length) {
            keep(job);
            busy = false;
            next();
            return;
        }
        post({action: 'scan', code: job.code, readerid: readerid, force: job.force ? 1 : 0})
            .then(function(answer) {
                add(answer, job.code);
                return answer;
            })
            .catch(function(error) {
                if (lostNetwork(error)) {
                    offline = true;
                    keep(job);
                    return null;
                }
                tone('no');
                line({kind: 'stop', sound: 'no', message: str.deskerror}, job.code);
                return null;
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
        } else if (event.altKey && key === 's') {
            target = lastline.querySelector('.ayv-line-room');
        } else if (event.ctrlKey && key === 'z' && document.activeElement === input && input.value === '') {
            var undos = lastline.querySelectorAll('.ayv-line-quiet');
            target = undos.length ? undos[undos.length - 1] : null;
        }
        if (target && !target.disabled) {
            event.preventDefault();
            target.click();
        }
    });

    // Opened from a reader's record: the desk starts with them.
    if (config.preload) {
        showReader(config.preload);
    }

    // The field keeps the focus: after a click anywhere that is not a button or a link, it comes back.
    document.addEventListener('click', function(event) {
        if (!event.target.closest('button, a, input, select, textarea')) {
            input.focus();
        }
    });
    input.focus();
})();
