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
 * Ayvurenda: adding books by their ISBN, one after another. The field keeps the focus: reading a book's code shows
 * its card; reading the labels already stuck on it adds them as its codes; reading the next book saves this one with
 * what is on screen, and Enter on the empty field saves it too. Each book saved becomes a line that can be undone.
 * The library, place, kind and first line of the spine stay from one book to the next, as in a box of new books.
 *
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';
    var config = window.AYV || {};
    var str = config.strings || {};
    var scan = document.getElementById('ayv-scan');
    var input = document.getElementById('ayv-code');
    var status = document.getElementById('ayv-add-status');
    var book = document.getElementById('ayv-book');
    var log = document.getElementById('ayv-log');
    var results = document.getElementById('ayv-found');
    if (!scan || !input || !book || !log || !window.fetch) {
        return;
    }
    var LETTERS = 'TRWAGMYFPDXBNJZSQVHLCKE';
    var $ = function(id) {
        return document.getElementById(id);
    };
    var current = null;
    var busy = false;
    var queue = [];
    var audio = null;

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

    var fill = function(text, values) {
        return String(text || '').replace(/\{\$a(?:->(\w+))?\}/g, function(all, key) {
            return key ? (values[key] !== undefined ? values[key] : '') : values;
        });
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

    // What was read: a book's ISBN (EAN-13 from 978 or 979, or ISBN-10), a copy's label, words of a title or its
    // authors (with a space, or letters in small type and no digits: a reader of codes writes neither), or nothing.
    var kind = function(code) {
        if (/\s/.test(code.trim()) || (!/\d/.test(code) && /[a-z\u00e0-\u00ff]/.test(code))) {
            return 'words';
        }
        var digits = code.replace(/[\s-]/g, '').toUpperCase();
        if (/^97[89]\d{10}$/.test(digits)) {
            var sum = 0;
            for (var i = 0; i < 12; i++) {
                sum += Number(digits[i]) * (i % 2 ? 3 : 1);
            }
            return (10 - sum % 10) % 10 === Number(digits[12]) ? 'isbn' : 'bad';
        }
        if (/^\d{9}[\dX]$/.test(digits)) {
            var total = 0;
            for (var j = 0; j < 10; j++) {
                total += (digits[j] === 'X' ? 10 : Number(digits[j])) * (10 - j);
            }
            return total % 11 === 0 ? 'isbn' : 'label';
        }
        if (/^\d{13}$/.test(digits)) {
            return 'bad';
        }
        return /^[0-9A-Z][0-9A-Z.\-]*$/.test(digits) ? 'label' : 'bad';
    };

    var letterok = function(code) {
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

    var say = function(text, sound) {
        status.textContent = text || '';
        status.classList.remove('ayv-add-asking');
        status.style.removeProperty('--ayv-asked');
        if (sound) {
            tone(sound);
        }
    };

    // While the catalogues answer: a ring that turns and a line that fills, so the page is seen working.
    var saying = function(text, part) {
        status.textContent = text;
        status.classList.add('ayv-add-asking');
        status.style.setProperty('--ayv-asked', Math.round(part * 100) + '%');
    };

    var field = function(name) {
        return book.elements[name];
    };

    var spine = function(cover, title) {
        cover.textContent = '';
        cover.appendChild(el('span', 'ayv-book-spine', title));
    };

    var show = function(answer) {
        current = {isbn: answer.isbn, found: answer.found, recordid: answer.existing.length ? answer.existing[0].id : 0,
            newrecord: !answer.existing.length, pick: answer.pick || ''};
        results.hidden = true;
        results.textContent = '';
        book.hidden = false;
        var cover = $('ayv-book-cover');
        cover.textContent = '';
        if (answer.cover) {
            var img = el('img');
            img.src = answer.cover;
            img.alt = '';
            img.onerror = function() {
                spine(cover, answer.title);
            };
            cover.appendChild(img);
        } else {
            spine(cover, answer.title || answer.isbn);
        }
        $('ayv-book-title').textContent = answer.title ? answer.title + (answer.subtitle ? ': ' + answer.subtitle : '') : '';
        $('ayv-book-by').textContent = (answer.authors || []).join(' · ');
        $('ayv-book-facts').textContent = [answer.publisher, answer.year, answer.extent, answer.isbn]
            .filter(Boolean).join(' · ');
        $('ayv-book-memory').textContent = answer.remark || '';
        $('ayv-book-memory').hidden = !answer.remark;
        $('ayv-book-source').textContent = answer.found ? fill(str.addfound, answer.source)
            : (answer.tried ? fill(str.addnotfound, answer.tried) : str.addbyhand);
        var manual = $('ayv-book-manual');
        manual.hidden = answer.found || answer.existing.length > 0;
        ['title', 'subtitle', 'author', 'publisher', 'year', 'pages'].forEach(function(name) {
            field(name).value = '';
        });
        var existing = $('ayv-book-existing');
        existing.textContent = '';
        existing.hidden = !answer.existing.length;
        if (answer.existing.length) {
            var copies = answer.existing[0].copies.map(function(c) {
                return fill(str.addexistingcopies, {count: c.count, library: c.library});
            }).join(', ');
            existing.appendChild(el('p', '', fill(str.addexisting, copies)));
            var add = el('button', 'ayv-line-button', str.addaddcopy);
            var other = el('button', 'ayv-line-button', str.addotheredition);
            add.type = other.type = 'button';
            add.setAttribute('aria-pressed', 'true');
            other.setAttribute('aria-pressed', 'false');
            add.addEventListener('click', function() {
                current.recordid = answer.existing[0].id;
                current.newrecord = false;
                add.setAttribute('aria-pressed', 'true');
                other.setAttribute('aria-pressed', 'false');
                manual.hidden = true;
                input.focus();
            });
            other.addEventListener('click', function() {
                current.recordid = 0;
                current.newrecord = true;
                other.setAttribute('aria-pressed', 'true');
                add.setAttribute('aria-pressed', 'false');
                manual.hidden = answer.found;
                input.focus();
            });
            existing.appendChild(add);
            existing.appendChild(other);
        }
        // The spine: what the library wrote before for this book, or the proposal; the class stays from the last book
        // when the proposal has none (a box of novels).
        var keep = field('callnumber1').value;
        field('callnumber1').value = answer.spine[0] || (answer.spinebefore ? '' : keep);
        field('callnumber2').value = answer.spine[1] || '';
        field('callnumber3').value = answer.spine[2] || '';
        $('ayv-spine-note').textContent = answer.spinebefore ? str.addspinebefore : str.addspineproposed;
        field('count').value = 1;
        field('barcodes').value = '';
        say('', answer.found || answer.existing.length ? 'ok' : 'look');
        if (!manual.hidden) {
            field('title').focus();
        } else {
            input.focus();
        }
    };

    var close = function() {
        current = null;
        book.hidden = true;
        input.focus();
    };

    var line = function(answer) {
        var li = el('li', 'ayv-line ayv-line-saved ayv-sound-ok');
        var head = el('div', 'ayv-add-line');
        if (answer.cover) {
            var img = el('img', 'ayv-add-thumb');
            img.src = answer.cover;
            img.alt = '';
            head.appendChild(img);
        }
        var text = el('p', 'ayv-line-text');
        var link = el('a', '', answer.message);
        link.href = answer.url;
        text.appendChild(link);
        head.appendChild(text);
        li.appendChild(head);
        var actions = el('div', 'ayv-line-actions');
        var undo = el('button', 'ayv-line-button ayv-line-quiet', str.addundo);
        undo.type = 'button';
        undo.addEventListener('click', function() {
            undo.disabled = true;
            post({action: 'undo', recordid: answer.recordid, itemids: answer.itemids.join(','),
                newrecord: answer.newrecord ? 1 : 0}).then(function(undone) {
                actions.textContent = '';
                li.appendChild(el('p', 'ayv-line-note', undone.message));
                if (undone.kind === 'undone') {
                    li.classList.add('ayv-line-undone');
                }
                tone('look');
                return undone;
            }).catch(function() {
                undo.disabled = false;
                tone('no');
            });
            input.focus();
        });
        actions.appendChild(undo);
        li.appendChild(actions);
        log.insertBefore(li, log.firstChild);
    };

    var save = function() {
        if (!current) {
            return Promise.resolve();
        }
        var manual = !$('ayv-book-manual').hidden;
        if (manual && !field('title').value.trim()) {
            say(str.adderror, 'no');
            field('title').focus();
            return Promise.reject(new Error('title'));
        }
        var params = {action: 'save', isbn: current.isbn, recordid: current.recordid, pick: current.pick};
        ['callnumber1', 'callnumber2', 'callnumber3', 'locationid', 'itemtype', 'count', 'barcodes',
            'title', 'subtitle', 'author', 'publisher', 'year', 'pages'].forEach(function(name) {
            params[name] = field(name).value;
        });
        if (field('target')) {
            params.target = field('target').value;
        }
        if (field('codes').value !== 'scan') {
            params.barcodes = '';
        }
        var saving = current;
        return post(params).then(function(answer) {
            if (current === saving) {
                close();
            }
            line(answer);
            tone('ok');
            return answer;
        }).catch(function(error) {
            say(error.message || str.adderror, 'no');
            throw error;
        });
    };

    var lookup = function(code) {
        say(fill(str.addlooking, code));
        return post({action: 'lookup', isbn: code}).then(function(answer) {
            if (answer.kind !== 'book') {
                say(str.addnotisbn, 'no');
                return answer;
            }
            show(answer);
            return answer;
        });
    };

    // Choosing one of the books found by words, or none to write it by hand.
    var choose = function(pick, words) {
        return post({action: 'pick', pick: pick}).then(function(answer) {
            show(answer);
            if (!pick) {
                field('title').value = words;
                field('title').focus();
            }
            return answer;
        });
    };

    // Each search has its number: answers to an older one are forgotten.
    var searching = 0;
    var joined = function(names) {
        return names.length < 2 ? names.join('') : names.slice(0, -1).join(', ') + str.addand + names[names.length - 1];
    };

    // A book found, in its place: those with more of the words first, and the list does not jump under the finger.
    var foundItem = function(item, words, token) {
        var li = el('li');
        li.setAttribute('data-score', item.score);
        var button = el('button', 'ayv-found-item');
        button.type = 'button';
        button.appendChild(el('strong', 'ayv-found-title', item.title));
        if (item.authors) {
            button.appendChild(el('span', 'ayv-found-by', item.authors));
        }
        button.appendChild(el('span', 'ayv-found-facts', [item.facts, item.source].filter(Boolean).join(' · ')));
        button.addEventListener('click', function() {
            // Chosen: the rest of the catalogues are no longer waited for.
            if (token === searching) {
                searching++;
            }
            choose(item.pick, words).catch(function(error) {
                say(error.message || str.adderror, 'no');
            });
        });
        li.appendChild(button);
        var after = Array.prototype.filter.call(results.children, function(other) {
            return other.hasAttribute('data-score') && Number(other.getAttribute('data-score')) < item.score;
        })[0];
        results.insertBefore(li, after || results.querySelector('.ayv-found-hand'));
    };

    var search = function(words, wrong) {
        var token = ++searching;
        saying(fill(str.addsearching, words), 0);
        results.hidden = true;
        results.textContent = '';
        var hand = el('li', 'ayv-found-hand');
        var byhand = el('button', 'ayv-button ayv-button-quiet', str.addsearchbyhand);
        byhand.type = 'button';
        byhand.addEventListener('click', function() {
            searching++;
            choose('', wrong || words).catch(function(error) {
                say(error.message || str.adderror, 'no');
            });
        });
        hand.appendChild(byhand);
        results.appendChild(hand);
        // Writing it by hand is always there: nobody has to wait for all the catalogues.
        results.hidden = false;
        // What it may have meant, at the same time: a misspelt name may also be someone else's, and then there are results.
        var spell = wrong ? Promise.resolve({near: null}) : post({action: 'searchspell', q: words}).catch(function() {
            return {near: null};
        });
        spell.then(function(spelled) {
            if (!spelled.near || token !== searching) {
                return null;
            }
            var meant = el('li', 'ayv-found-meant');
            var again = el('button', 'ayv-button', fill(str.addsearchmeant, spelled.near));
            again.type = 'button';
            again.addEventListener('click', function() {
                search(spelled.near, words).catch(function(error) {
                    say(error.message || str.adderror, 'no');
                });
            });
            meant.appendChild(again);
            results.insertBefore(meant, results.firstChild);
            return null;
        });
        return post({action: 'searchwhere'}).then(function(where) {
            var left = where.sources.slice();
            var total = left.length;
            var done = 0;
            var seen = {};
            var from = [];
            var count = 0;
            var ask = function() {
                if (token !== searching || !left.length || count >= where.enough) {
                    return null;
                }
                var source = left.shift();
                return post({action: 'searchone', q: words, source: source.key}).catch(function() {
                    return {items: []};
                }).then(function(answer) {
                    done++;
                    if (token !== searching) {
                        return null;
                    }
                    answer.items.forEach(function(item) {
                        if (!seen[item.same] && count < where.most) {
                            seen[item.same] = true;
                            count++;
                            foundItem(item, words, token);
                            if (from.indexOf(item.source) < 0) {
                                from.push(item.source);
                            }
                        }
                    });
                    saying(fill(str.addsearchprogress, {done: done, total: total}), total ? done / total : 1);
                    return ask();
                });
            };
            return Promise.all([ask(), ask(), ask()]).then(function() {
                if (token !== searching) {
                    return null;
                }
                if (count) {
                    say(fill(str.addsearchfound, joined(from)), 'look');
                    return null;
                }
                // Nothing: it is searched as it may have been meant, once.
                return spell.then(function(spelled) {
                    if (token !== searching) {
                        return null;
                    }
                    if (spelled.near) {
                        return search(spelled.near, words);
                    }
                    say(fill(str.addsearchnone, where.tried), 'no');
                    return null;
                });
            });
        }).then(function() {
            // Corrected, it says so, because it is not what was typed.
            if (wrong && token === searching && results.querySelector('.ayv-found-item')) {
                status.textContent = fill(str.addsearchspelled, {wrong: wrong, near: words}) + ' ' + status.textContent;
            }
            var first = token === searching ? results.querySelector('.ayv-found-item') || results.querySelector('button') : null;
            if (first) {
                first.focus();
            }
            return null;
        });
    };

    var handle = function(code) {
        var what = kind(code);
        if (what === 'words') {
            var waiting = current && !(!$('ayv-book-manual').hidden && !field('title').value.trim()) ? save()
                : Promise.resolve();
            return waiting.then(function() {
                return search(code);
            });
        }
        if (what === 'label') {
            if (!current) {
                say(str.additemcode, 'no');
                return Promise.resolve();
            }
            if (!letterok(code)) {
                say(str.addbadletter, 'no');
                return Promise.resolve();
            }
            // A label already stuck on the book: one more of its codes.
            book.querySelector('input[name="codes"][value="scan"]').checked = true;
            $('ayv-barcodes').hidden = false;
            var list = field('barcodes').value.split(/\s+/).filter(Boolean);
            if (list.indexOf(code) < 0) {
                list.push(code.toUpperCase());
            }
            field('barcodes').value = list.join('\n');
            field('count').value = list.length;
            tone('ok');
            return Promise.resolve();
        }
        if (what === 'bad') {
            say(str.addnotisbn, 'no');
            return Promise.resolve();
        }
        // The next book: the one on screen is saved first, unless it still lacks its title.
        var before = current && !(!$('ayv-book-manual').hidden && !field('title').value.trim()) ? save() : Promise.resolve();
        return before.then(function() {
            return lookup(code);
        });
    };

    var next = function() {
        if (busy || !queue.length) {
            return;
        }
        busy = true;
        var code = queue.shift();
        handle(code).catch(function() {
            return null;
        }).then(function() {
            busy = false;
            next();
            return null;
        });
    };

    scan.addEventListener('submit', function(e) {
        e.preventDefault();
        var code = input.value.trim();
        input.value = '';
        if (code === '') {
            if (current && !busy) {
                busy = true;
                save().catch(function() {
                    return null;
                }).then(function() {
                    busy = false;
                    next();
                    return null;
                });
            }
            return;
        }
        queue.push(code);
        next();
    });

    book.addEventListener('submit', function(e) {
        e.preventDefault();
        save().catch(function() {
            return null;
        });
    });

    book.addEventListener('change', function(e) {
        if (e.target.name === 'codes') {
            $('ayv-barcodes').hidden = e.target.value !== 'scan';
        }
    });

    $('ayv-book-cancel').addEventListener('click', close);
    if (window.AYVCamera) {
        window.AYVCamera.wire(config, function(code) {
            queue.push(code);
            next();
        }, function() {
            input.focus();
        });
    }
    input.focus();
})();
