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
    // Nothing moves with «reduce motion»: not the card landing, not the stamps, not the hummingbird.
    var still = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    // The loan just made, to stamp its date on the reader's card.
    var stamped = 0;
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
    // Books at the desk before their reader: the field asks who takes them, and the reader who opens takes them.
    var hand = [];
    var handbox = null;
    var label = form.querySelector('.ayv-scan-label');
    var readername = '';
    var lastbad = '';
    var prompt = label ? label.textContent : '';

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
                // A gateway that does not reach the site (502 to 504) is as good as no network: the scan waits. Any other
                // page that is not the desk's answer (an error of the site, a maintenance page) is the server answering:
                // that scan is shown as failed and the desk goes on, never stuck behind it.
                if (response.status >= 502 && response.status <= 504) {
                    throw new TypeError('gateway ' + response.status);
                }
                var type = response.headers.get('Content-Type') || '';
                if (!response.ok || type.indexOf('json') === -1) {
                    throw new Error('server ' + response.status);
                }
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
            readername = '';
            panel.hidden = true;
            paintHand();
            return;
        }
        // A card that comes to the desk lands on it; the same one painted again stays where it is (Gaudí, dis-efectos 07).
        if (reader.id !== readerid && !still) {
            panel.classList.remove('ayv-card-land');
            panel.getBoundingClientRect();
            panel.classList.add('ayv-card-land');
        }
        readerid = reader.id;
        panel.hidden = false;
        // The field says who it lends to now; the figures of the day stay, under the card.
        readername = reader.name;
        paintHand();
        var head = el('div', 'ayv-reader-head');
        head.appendChild(face(reader, 'ayv-reader-face'));
        var who = el('div', 'ayv-reader-who');
        who.appendChild(el('span', 'ayv-reader-name', reader.name));
        if (reader.group) {
            who.appendChild(el('span', 'ayv-reader-group', reader.group));
        }
        if (reader.url) {
            // Their record, in this same tab: it knows its way back to the desk.
            var record = el('a', 'ayv-reader-record', str.profileview);
            record.href = reader.url;
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
        // What a late book left: what the desk stops for, and what the librarian can lift or close from here.
        if (reader.sanction) {
            var owed = el('div', 'ayv-sanction-band');
            owed.appendChild(el('p', 'ayv-sanction-text', reader.sanction.text));
            owed.appendChild(settle(reader.sanction.actions, reader.id));
            panel.appendChild(owed);
        }
        // At a textbook bank: the set of their grade, what is still to give, and the contract for the family.
        if (reader.bank) {
            var set = el('div', 'ayv-reader-bank');
            set.appendChild(el('p', 'ayv-reader-has', str.deskbankset.replace('{$a}', function() { return reader.bank.set; })));
            set.appendChild(el('p', reader.bank.missing ? 'ayv-reader-bank-missing' : 'ayv-reader-bank-done',
                reader.bank.missing ? str.deskbankmissing.replace('{$a}', function() { return reader.bank.pending.join(', '); }) : str.deskbankdone));
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
                item.appendChild(el('span', 'ayv-loan-due', hold.ready ? str.deskholdready.replace('{$a}', function() { return hold.until; })
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
            // The book just lent gets its date stamped: it falls big and blurred and sets sharp.
            if (stamped && loan.id === stamped && !loan.late) {
                item.appendChild(el('span', 'ayv-loan-stamp' + (still ? '' : ' ayv-stamp-new'), loan.due));
                stamped = 0;
            }
            // More time, without the book: the reader is here, or the family asks.
            if (loan.renewable) {
                item.appendChild(button(str.deskrenew, 'ayv-line-quiet ayv-loan-renew', function(b) {
                    b.disabled = true;
                    post({action: 'renew', loanid: loan.id}).then(function(renewed) {
                        add(renewed);
                        return renewed;
                    }).catch(function() {
                        tone('no');
                        b.disabled = false;
                    });
                }));
            }
            // A book without a label comes back with a touch.
            item.appendChild(button(str.deskgiveback, 'ayv-line-quiet ayv-loan-back', function(b) {
                b.disabled = true;
                choose({action: 'giveback', loanid: loan.id, readerid: reader.id}, loan.title);
            }));
            // Lost: a second touch says it is so (a slip of the finger must not cost a reader a book).
            item.appendChild(button(str.desklost, 'ayv-line-quiet ayv-loan-lost', function(b) {
                if (!b.classList.contains('ayv-loan-lost-sure')) {
                    b.classList.add('ayv-loan-lost-sure');
                    b.textContent = str.desklostsure;
                    return;
                }
                b.disabled = true;
                choose({action: 'lost', loanid: loan.id, readerid: reader.id}, loan.title);
            }));
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

    // Lift the days or close what is owed, from the panel or from the line of the book that just came back.
    var settle = function(choices, who, done, row) {
        var box = el('div', 'ayv-line-actions');
        (choices || []).forEach(function(choice) {
            box.appendChild(button(choice.label, 'ayv-line-quiet', function(b) {
                b.disabled = true;
                post({action: 'sanction', readerid: who, what: choice.do, row: row || 0}).then(function(answer) {
                    tone('ok');
                    if (done) {
                        done(answer);
                    }
                    if (answer.reader && readerid === who) {
                        showReader(answer.reader);
                    }
                    return answer;
                }).catch(function() {
                    tone('no');
                    b.disabled = false;
                });
            }));
        });
        return box;
    };

    var line = function(answer, code) {
        var li = el('li', 'ayv-line ayv-line-' + answer.kind + ' ayv-sound-' + (answer.sound || 'ok'));
        li.appendChild(el('p', 'ayv-line-text', answer.message));
        // Back home: the green stamp «Returned» on the line (Gaudí, dis-efectos 07).
        if (answer.kind === 'returned' && str.deskstampback) {
            li.appendChild(el('span', 'ayv-line-stamp' + (still ? '' : ' ayv-stamp-new'), str.deskstampback));
        }
        if (answer.sanction) {
            var left = el('p', 'ayv-line-note ayv-sanction-left', answer.sanction.text);
            li.appendChild(left);
            var settling = settle(answer.sanction.actions, answer.readerid, function(done) {
                left.textContent = done.message;
                settling.remove();
            }, answer.sanction.id);
            li.appendChild(settling);
        }
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
        if (answer.kind === 'lost' && answer.loanid) {
            undo('lost');
        }
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
                    // It stayed in the room: nobody takes it.
                    dropFromHand(code);
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
                if (answer.recordid) {
                    // A book chosen by its title goes the same way, now despite the reason.
                    choose({action: 'lendrecord', recordid: answer.recordid, readerid: answer.readerid || readerid, force: 1}, '');
                } else {
                    // To the reader of this line, not to whoever is open now (the next child in the queue).
                    enqueue(answer.code, true, answer.readerid || 0);
                }
            }));
        } else if (answer.kind === 'choose') {
            // Who it is first, when people were found: the books that also have those words, folded under them.
            var people = (answer.readers || []).length > 0;
            var hasbooks = (answer.books || []).length || (answer.out || []).length || (answer.shelf || []).length;
            (answer.readers || []).forEach(function(reader) {
                var choice = button(reader.name + (reader.group ? ' · ' + reader.group : ''), 'ayv-line-choice', function() {
                    post({action: 'reader', readerid: reader.id}).then(function(found) {
                        li.classList.add('ayv-line-undone');
                        actions.textContent = '';
                        showReader(found.reader);
                        tone('ok');
                        handOver(found.reader.id);
                        return found;
                    }).catch(function() {
                        tone('no');
                    });
                });
                // Two readers of the same name: their photos tell them apart.
                choice.insertBefore(face(reader, 'ayv-choice-face'), choice.firstChild);
                actions.appendChild(choice);
            });
            if (answer.morereaders) {
                actions.appendChild(el('p', 'ayv-line-note ayv-line-shelf-hint', str.deskmorereaders));
            }
            var bookbox = actions;
            if (people && hasbooks) {
                bookbox = el('details', 'ayv-line-morebooks');
                bookbox.appendChild(el('summary', '', str.deskbooksalso));
                actions.appendChild(bookbox);
            }
            // A book without a label, by its title: on the shelf to lend, or out to bring back.
            (answer.books || []).forEach(function(book) {
                var free = book.free === 1 ? str.deskbookfreeone : str.deskbookfree.replace('{$a}', function() {
                    return book.free;
                });
                bookbox.appendChild(bookChoice(book.title, [book.author, free, book.callnumber], function() {
                    li.classList.add('ayv-line-undone');
                    actions.textContent = '';
                    choose({action: 'lendrecord', recordid: book.recordid, readerid: answer.readerid || readerid}, book.title);
                }));
            });
            (answer.out || []).forEach(function(loan) {
                bookbox.appendChild(bookChoice(loan.title, [loan.who, loan.due], function() {
                    li.classList.add('ayv-line-undone');
                    actions.textContent = '';
                    choose({action: 'giveback', loanid: loan.loanid, readerid: readerid}, loan.title);
                }));
            });
            // What is on the shelf: a touch and the desk asks who takes it.
            if (answer.shelf && answer.shelf.length) {
                if ((answer.out || []).length || (answer.readers || []).length) {
                    bookbox.appendChild(el('p', 'ayv-line-note ayv-line-shelf-hint', str.deskshelfhint));
                }
                answer.shelf.forEach(function(book) {
                    var free = book.free === 1 ? str.deskbookfreeone : str.deskbookfree.replace('{$a}', function() {
                        return book.free;
                    });
                    bookbox.appendChild(bookChoice(book.title, [book.author, free, book.callnumber], function(b) {
                        b.disabled = false;
                        takeUp([{recordid: book.recordid, title: book.title, more: [book.author, book.callnumber]}], li);
                    }));
                });
            }
            // More than it shows: another word narrows it.
            if (answer.more > 0) {
                bookbox.appendChild(el('p', 'ayv-line-note ayv-line-shelf-hint', str.deskmorebooks.replace('{$a}', function() {
                    return answer.more;
                })));
            }
        }
        if (actions.childNodes.length) {
            li.appendChild(actions);
        }
        log.insertBefore(li, log.firstChild);
        lastline = li;
        return li;
    };

    // A book to choose: its title, and below what tells it apart.
    var bookChoice = function(title, more, onclick) {
        var choice = button('', 'ayv-line-choice ayv-line-book', function(b) {
            b.disabled = true;
            onclick(b);
        });
        choice.appendChild(el('span', 'ayv-line-book-title', title));
        var rest = more.filter(function(part) {
            return part;
        }).join(' · ');
        if (rest) {
            choice.appendChild(el('span', 'ayv-line-book-more', rest));
        }
        return choice;
    };

    // What the desk answers to a choice is shown as a scan's answer.
    var choose = function(params, what) {
        return post(params).then(function(answer) {
            add(answer, what);
            return answer;
        }).catch(function() {
            tone('no');
            line({kind: 'stop', sound: 'no', message: str.deskerror}, what);
        });
    };

    var add = function(answer, code) {
        tone(answer.sound || 'ok');
        if (answer.kind === 'reader') {
            showReader(answer.reader);
            handOver(answer.reader.id);
            return;
        }
        if (answer.reader && (!readerid || answer.reader.id === readerid)) {
            stamped = answer.kind === 'lent' ? answer.loanid : 0;
            showReader(answer.reader);
        }
        line(answer, code);
        // A book read with no reader open: whoever comes next takes it. Scans passed later keep their own order.
        if (answer.kind === 'needreader' && !replaying) {
            takeUp([{code: code, title: answer.title, more: []}], null);
        }
        if (answer.herald) {
            heraldSays(answer.herald);
            if (answer.herald.cross && answer.kind === 'returned' && answer.reader && answer.reader.loans &&
                    !answer.reader.loans.length) {
                cross(answer.herald.cross);
            }
        }
    };

    // The hummingbird crosses the card of a child who has just brought back their last book, once, and leaves a feather
    // by their name that goes by itself (Gaudí). Nothing with «reduce motion».
    var cross = function(what) {
        if (still || !what.figure || panel.hidden) {
            return;
        }
        var bird = el('img', 'ayv-desk-cross');
        bird.src = what.figure;
        bird.alt = '';
        panel.appendChild(bird);
        bird.getBoundingClientRect();
        bird.classList.add('ayv-desk-cross-go');
        window.setTimeout(function() {
            bird.remove();
        }, 1300);
        var name = panel.querySelector('.ayv-reader-name');
        if (what.feather && name) {
            var feather = el('img', 'ayv-desk-feather');
            feather.src = what.feather;
            feather.alt = '';
            name.appendChild(feather);
            window.setTimeout(function() {
                feather.remove();
            }, 4000);
        }
    };

    // What the desk has in its hand, above the field, and the field asking who takes it.
    var paintHand = function() {
        form.classList.toggle('ayv-scan-hand', hand.length > 0);
        if (label) {
            label.textContent = hand.length ? str.deskforwhom
                : (readername ? str.deskfor.replace('{$a}', function() { return readername; }) : prompt);
            form.classList.toggle('ayv-scan-reader', !hand.length && !!readername);
        }
        if (handbox) {
            handbox.remove();
            handbox = null;
        }
        if (!hand.length) {
            return;
        }
        handbox = el('div', 'ayv-hand');
        handbox.setAttribute('role', 'status');
        var books = el('ul', 'ayv-hand-books');
        hand.forEach(function(book) {
            var row = el('li', 'ayv-hand-book');
            row.appendChild(el('span', 'ayv-hand-title', book.title));
            var more = book.more.filter(function(part) {
                return part;
            }).join(' · ');
            if (more) {
                row.appendChild(el('span', 'ayv-hand-more', more));
            }
            books.appendChild(row);
        });
        handbox.appendChild(books);
        handbox.appendChild(button(str.deskdismiss, 'ayv-line-quiet', function() {
            dropHand();
        }));
        form.insertBefore(handbox, form.firstChild);
    };
    var takeUp = function(books, li) {
        books.forEach(function(book) {
            var same = hand.some(function(held) {
                return book.code ? held.code === book.code : held.recordid === book.recordid;
            });
            if (!same) {
                book.line = li;
                hand.push(book);
            }
        });
        // The list stays out of the way; left, it comes back.
        if (li) {
            li.hidden = true;
        }
        paintHand();
        input.focus();
        // On a phone the field may sit at the top with the book above it, out of sight.
        form.scrollIntoView({block: 'nearest'});
    };
    var dropHand = function() {
        hand.forEach(function(book) {
            if (book.line) {
                book.line.hidden = false;
            }
        });
        hand = [];
        paintHand();
    };
    var dropFromHand = function(code) {
        hand = hand.filter(function(book) {
            return book.code !== code;
        });
        paintHand();
    };
    // Their reader is here: each book goes as if it had been read now, and red cards ask as always.
    var handOver = function(who) {
        var books = hand;
        hand = [];
        paintHand();
        books.reduce(function(chain, book) {
            return chain.then(function() {
                return choose(book.code ? {action: 'scan', code: book.code, readerid: who}
                    : {action: 'lendrecord', recordid: book.recordid, readerid: who}, book.title);
            });
        }, Promise.resolve());
    };

    // The herald, in a corner, a word as a book comes home: it never waits for anything and goes by itself.
    var heraldBox = null;
    var heraldTimer = null;
    var heraldSays = function(said) {
        if (!heraldBox) {
            heraldBox = el('div', 'ayv-desk-herald');
            heraldBox.setAttribute('aria-hidden', 'true');
            document.body.appendChild(heraldBox);
        }
        heraldBox.textContent = '';
        var face = el('span', 'ayv-desk-herald-face');
        if (said.portrait) {
            var img = document.createElement('img');
            img.src = said.portrait;
            img.alt = '';
            face.appendChild(img);
        } else {
            face.textContent = said.initial || '';
        }
        heraldBox.appendChild(face);
        heraldBox.appendChild(el('span', 'ayv-desk-herald-text', said.text));
        heraldBox.classList.remove('ayv-desk-herald-on');
        // Read the layout so that the class put back starts the appearance again.
        heraldBox.getBoundingClientRect();
        heraldBox.classList.add('ayv-desk-herald-on');
        window.clearTimeout(heraldTimer);
        heraldTimer = window.setTimeout(function() {
            heraldBox.classList.remove('ayv-desk-herald-on');
        }, 4000);
    };

    // Without a network the desk goes on: each scan is kept in this browser, in order and with its moment, and passed
    // to the server as soon as the network is back; what it answers then is shown as if it had just happened.
    var pendingkey = 'ayv-desk-pending-' + config.library;
    var pending = [];
    // Another tab may have kept scans or passed them: what is stored is what counts.
    var loadPending = function() {
        try {
            pending = JSON.parse(window.localStorage.getItem(pendingkey) || 'null') || pending;
        } catch (e) {
            // Without storage they are the ones in this page.
        }
    };
    loadPending();
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
        bar.textContent = pending.length === 1 ? str.deskofflineone : (str.deskofflinemany || '').replace('{$a}', function() { return pending.length; });
    };
    var lostNetwork = function(error) {
        return !navigator.onLine || error instanceof TypeError;
    };
    var keep = function(job) {
        loadPending();
        pending.push({code: job.code, force: job.force, readerid: job.readerid || readerid, forwhom: job.forwhom,
            at: job.at || Math.floor(Date.now() / 1000)});
        keepPending();
        tone('look');
        line({kind: 'offline', sound: 'look', message: str.deskoffline}, job.code);
    };
    var replayed = function() {
        var reader = pending[0].readerid || 0;
        // The reader each scan was kept with: when it changes between two (the librarian closed one, opened another by
        // name), that one counts; else the reader the server opened with a card read meanwhile.
        var kept = reader;
        var passed = 0;
        var step = function() {
            loadPending();
            if (!pending.length) {
                offline = false;
                replaying = false;
                keepPending();
                if (!passed) {
                    return null;
                }
                line({kind: 'online', sound: 'ok', message: (str.deskbackonline || '').replace('{$a}', function() { return passed; })}, '');
                return null;
            }
            var job = pending[0];
            if ((job.readerid || 0) !== kept) {
                kept = job.readerid || 0;
                reader = kept;
            }
            return post({action: 'scan', code: job.code, readerid: reader, force: job.force ? 1 : 0, forwhom: job.forwhom ? 1 : 0,
                at: job.at})
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
        return step();
    };
    var replay = function() {
        loadPending();
        if (replaying || !pending.length) {
            return;
        }
        replaying = true;
        // One tab at a time: two passing the same scans would lend a book and bring it back.
        if (!navigator.locks) {
            replayed();
            return;
        }
        navigator.locks.request(pendingkey, {ifAvailable: true}, function(lock) {
            if (!lock) {
                replaying = false;
                return null;
            }
            return replayed();
        });
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
        job.forwhom = hand.length > 0;
        // While scans wait for the network, new ones wait behind them, in order.
        if (offline || pending.length) {
            keep(job);
            busy = false;
            next();
            return;
        }
        post({action: 'scan', code: job.code, readerid: job.readerid || readerid, force: job.force ? 1 : 0,
            forwhom: job.forwhom ? 1 : 0})
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

    var enqueue = function(code, force, forreader) {
        queue.push({code: code, force: !!force, readerid: forreader || 0});
        next();
    };

    var read = function(code) {
        if (!control(code)) {
            tone('no');
            // The same code twice is not a bad reading: it is a label of another library or program.
            line({kind: 'badletter', sound: 'no', message: code === lastbad ? str.deskbadletteragain : str.deskbadletter}, code);
            lastbad = code;
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
        if (event.key === 'Escape' && hand.length) {
            dropHand();
            event.preventDefault();
            return;
        }
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
    // Opened from a book's page: the desk starts with it in its hand.
    if (config.book) {
        takeUp([{recordid: config.book.recordid, title: config.book.title, more: [config.book.author, config.book.callnumber]}],
            null);
    }

    // The field keeps the focus: after a click anywhere that is not a button or a link, it comes back.
    document.addEventListener('click', function(event) {
        if (!event.target.closest('button, a, input, select, textarea')) {
            input.focus();
        }
    });
    input.focus();
})();
