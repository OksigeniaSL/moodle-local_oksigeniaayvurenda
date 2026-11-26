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
 * Ayvurenda: what the recorder shows while recording with calm, drawn and nothing else (voice.js and voiceedit.js do the
 * sound). Three seconds counted before the microphone opens; the pieces of the reading as a strip, the one being read
 * growing; the pause with its choices; at the end, the whole reading as a wave with two handles at its cleaned ends, the
 * line that runs over it while it sounds, and how the voice was levelled; and a reading left half-done in the device.
 *
 * The moment is data-estado on the plate: listo, cuenta, grabando, pausa, despues or borrador. On a phone, the pause and
 * the reading half-done rise from the foot as the plate does after recording, over the veil.
 *
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';
    var plate = document.getElementById('ayv-recorder');
    if (!plate) {
        return;
    }
    var lang = document.documentElement.lang || undefined;
    var narrow = window.matchMedia ? window.matchMedia('(max-width: 47.99rem)') : null;
    var small = function() {
        return narrow ? narrow.matches : false;
    };
    // The veil hangs from the page, not from the plate, which becomes a layer of its own when it rises.
    var veil = document.getElementById('ayv-vp-velo');
    var section = document.querySelector('.ayv-voice');
    if (veil && section && plate.contains(veil)) {
        section.appendChild(veil);
    }
    // «{$a}», or «{$a->name}» for each name given.
    var fill = function(text, value) {
        text = text || '';
        if (value !== null && typeof value === 'object') {
            Object.keys(value).forEach(function(name) {
                text = text.split('{$a->' + name + '}').join(value[name]);
            });
            return text;
        }
        return text.split('{$a}').join(value);
    };
    // 7:05, as players count.
    var clock = function(ms) {
        var seconds = Math.max(0, Math.floor(ms / 1000));
        return Math.floor(seconds / 60) + ':' + String(seconds % 60).padStart(2, '0');
    };
    // 1,2 s, with the decimal mark of the page's language.
    var tenths = new Intl.NumberFormat(lang, {minimumFractionDigits: 1, maximumFractionDigits: 1});
    var seconds = function(ms) {
        var box = document.getElementById('ayv-recorte');
        return fill(box ? box.getAttribute('data-seconds') : '{$a}', tenths.format(ms / 1000));
    };

    // What is being recorded, in one line, while the chips are put away; after recording, «Cambiar» by the title brings
    // them back.
    var summary = plate.querySelector('.ayv-rec-resumen');
    var sum = function() {
        if (!summary) {
            return;
        }
        var part = document.querySelector('input[name="ayv-part"]:checked');
        var title = part ? (part.getAttribute('data-title') || part.parentNode.textContent.trim()) : '';
        summary.querySelector('.ayv-rec-resumen-parte').textContent = title;
        var sound = document.querySelector('input[name="ayv-backdrop"]:checked');
        var word = sound && sound.value ? sound.parentNode.querySelector('.ayv-vp-word').textContent.trim() : '';
        summary.querySelector('.ayv-rec-resumen-fondo').textContent = word ? fill(summary.getAttribute('data-sound'),
            word.charAt(0).toLocaleLowerCase() + word.slice(1)) : '';
    };
    if (summary && plate.querySelector('.ayv-rec-cambiar')) {
        var change = plate.querySelector('.ayv-rec-cambiar');
        change.addEventListener('click', function() {
            // On a phone the parts are above the text: the sound opens its sheet, as from the bar.
            var chip = document.getElementById('ayv-vp-barra-fondo');
            if (small() && chip) {
                chip.click();
                return;
            }
            var open = !plate.classList.contains('is-eligiendo');
            plate.classList.toggle('is-eligiendo', open);
            change.setAttribute('aria-expanded', open ? 'true' : 'false');
            var chips = document.getElementById('ayv-vp') || document.getElementById('ayv-vp-backdrops');
            if (open && chips) {
                chips.scrollIntoView({block: 'nearest', behavior: 'smooth'});
            }
        });
    }
    document.querySelectorAll('input[name="ayv-part"], input[name="ayv-backdrop"]').forEach(function(radio) {
        radio.addEventListener('change', sum);
    });

    /**
     * Which moment of the recording it is.
     *
     * @param {string} name listo, cuenta, grabando, pausa, despues or borrador.
     */
    var state = function(name) {
        plate.setAttribute('data-estado', name);
        plate.classList.remove('is-eligiendo');
        sum();
        var rises = small() && (name === 'pausa' || name === 'borrador' || name === 'cuenta');
        if (veil && (rises || name === 'listo' || name === 'grabando')) {
            veil.hidden = !rises;
        }
        if (rises && name !== 'cuenta') {
            plate.scrollTop = 0;
        }
        var first = {cuenta: 'ayv-cuenta-ya', pausa: 'ayv-pausa-seguir', borrador: 'ayv-borrador-seguir'}[name];
        if (first && document.getElementById(first)) {
            document.getElementById(first).focus({preventScroll: !rises});
        }
    };

    /**
     * Three, two, one: then done(). «Empezar ya» does not wait.
     *
     * @param {Function} done
     * @param {number} from 3 if not said.
     * @return {Function} To stop it without starting.
     */
    var countdown = function(done, from) {
        var box = document.getElementById('ayv-cuenta');
        var number = box.querySelector('.ayv-rec-cuenta-n');
        var ring = box.querySelector('.ayv-rec-cuenta-pasa');
        var now = box.querySelector('#ayv-cuenta-ya');
        var said = box.querySelector('[role="status"]');
        var left = from || 3;
        var timer = null;
        var over = false;
        var tick = function() {
            number.textContent = String(left);
            // The ring empties in each second, from its top.
            ring.style.animation = 'none';
            ring.getBoundingClientRect();
            ring.style.animation = '';
        };
        var end = function(go) {
            if (over) {
                return;
            }
            over = true;
            window.clearInterval(timer);
            now.removeEventListener('click', skip);
            if (go) {
                done();
            }
        };
        var skip = function() {
            end(true);
        };
        state('cuenta');
        if (said) {
            said.textContent = said.getAttribute('data-says') || '';
        }
        tick();
        now.addEventListener('click', skip);
        timer = window.setInterval(function() {
            left--;
            if (left > 0) {
                tick();
            } else {
                end(true);
            }
        }, 1000);
        return function() {
            end(false);
        };
    };

    /**
     * The pieces read so far, as a strip against the longest a part may last, and their count.
     *
     * @param {number[]} list ms of each piece closed.
     * @param {number|null} live ms of the one being read, if any.
     * @param {Element} where One strip and its count; every strip of the plate but the draft's if not said.
     */
    var pieces = function(list, live, where) {
        var max = 20 * 60 * 1000;
        var total = list.reduce(function(sum, ms) {
            return sum + ms;
        }, 0) + (live || 0);
        var strips = where ? [where] : [].slice.call(plate.querySelectorAll('.ayv-rec-trozos .ayv-rec-tira'));
        strips.forEach(function(strip) {
            strip.textContent = '';
            var add = function(ms, kind) {
                var piece = document.createElement('i');
                piece.style.width = (100 * ms / max).toFixed(3) + '%';
                if (kind) {
                    piece.className = kind;
                }
                strip.appendChild(piece);
            };
            list.forEach(function(ms, k) {
                add(ms, k === list.length - 1 && !live ? 'is-ultimo' : '');
            });
            if (live) {
                add(live, 'is-vivo');
            }
            var rest = document.createElement('i');
            rest.className = 'is-resto';
            strip.appendChild(rest);
        });
        if (where) {
            return;
        }
        var n = list.length + (live ? 1 : 0);
        plate.querySelectorAll('.ayv-rec-trozos').forEach(function(count) {
            count.querySelector('.ayv-rec-trozos-n').textContent = n === 1 ? count.getAttribute('data-one')
                : fill(count.getAttribute('data-many'), n);
            count.querySelector('.ayv-rec-trozos-t').textContent = '· ' + clock(total);
            var most = count.querySelector('.ayv-rec-trozos-max');
            most.textContent = fill(most.textContent, max / 60000);
        });
    };

    // How loud the sound is in each of so many steps, from ms to ms of the decoded sound (the whole of it if not said).
    var levels = function(audio, steps, start, end) {
        var data = audio.getChannelData(0);
        var rate = audio.sampleRate;
        var first = Math.floor((start || 0) * rate / 1000);
        var last = end === undefined ? data.length : Math.min(data.length, Math.floor(end * rate / 1000));
        var span = Math.max(1, Math.floor((last - first) / steps));
        var out = [];
        for (var k = 0; k < steps; k++) {
            var sum = 0;
            var from = first + k * span;
            for (var at = from; at < Math.min(last, from + span); at++) {
                sum += data[at] * data[at];
            }
            out.push(Math.sqrt(sum / span));
        }
        return out;
    };

    var NS = 'http://www.w3.org/2000/svg';
    // One bar per level against the loudest of the reading (a square root, so that a soft voice is seen), and the
    // pauses as thin marks (fractions of the width).
    var bars = function(svg, list, loudest, marks, wide) {
        var step = 3;
        if (!wide) {
            svg.setAttribute('viewBox', '0 0 ' + (list.length * step) + ' 100');
            svg.textContent = '';
        }
        list.forEach(function(level, k) {
            var tall = Math.max(2, Math.min(1, Math.sqrt(level / (loudest || 1))) * 96);
            var bar = document.createElementNS(NS, 'rect');
            bar.setAttribute('x', String(k * step));
            bar.setAttribute('y', (50 - tall / 2).toFixed(2));
            bar.setAttribute('width', '2');
            bar.setAttribute('height', tall.toFixed(2));
            bar.setAttribute('rx', '1');
            svg.appendChild(bar);
        });
        (marks || []).forEach(function(at) {
            ['0', '86'].forEach(function(y) {
                var mark = document.createElementNS(NS, 'rect');
                mark.setAttribute('class', 'is-junta');
                mark.setAttribute('x', ((wide || list.length * step) * at - 1).toFixed(2));
                mark.setAttribute('y', y);
                mark.setAttribute('width', '2.5');
                mark.setAttribute('height', '14');
                svg.appendChild(mark);
            });
        });
    };
    // The whole reading as one silhouette, the same above and below, and the pauses as ticks at the top and the foot.
    var shape = function(svg, list, loudest, marks) {
        var wide = list.length * 3;
        svg.setAttribute('viewBox', '0 0 ' + wide + ' 100');
        svg.textContent = '';
        var tall = list.map(function(level) {
            return Math.max(3, Math.min(1, Math.pow(level / (loudest || 1), 0.8)) * 44);
        });
        var top = tall.map(function(h, k) {
            return (k * 3 + 1.5).toFixed(1) + ' ' + (50 - h).toFixed(1);
        });
        var foot = tall.map(function(h, k) {
            return (k * 3 + 1.5).toFixed(1) + ' ' + (50 + h).toFixed(1);
        }).reverse();
        var path = document.createElementNS(NS, 'path');
        path.setAttribute('d', 'M0 50 L' + top.join(' L') + ' L' + wide + ' 50 L' + foot.join(' L') + ' Z');
        svg.appendChild(path);
        bars(svg, [], loudest, marks, wide);
    };
    // 0:01,2: the ends are moved by tenths of a second.
    var mark = tenths.format(1.5).charAt(1);
    var fine = function(ms) {
        return clock(ms) + mark + Math.floor(Math.max(0, ms) % 1000 / 100);
    };
    // «Empieza en {$a}»: the words, and the time in bold.
    var label = function(id, time) {
        var line = document.getElementById(id);
        if (!line) {
            return;
        }
        var parts = line.getAttribute('data-says').split('{$a}');
        line.textContent = parts[0];
        var bold = document.createElement('b');
        bold.textContent = time;
        line.appendChild(bold);
        line.appendChild(document.createTextNode(parts[1] || ''));
    };

    var wave = {ms: 0, span: 0, start: 0, end: 0, trims: [], seeks: []};
    var from = document.getElementById('ayv-empieza');
    var to = document.getElementById('ayv-acaba');
    var whole = plate.querySelector('.ayv-rec-entera');
    var at = function(fraction) {
        return (100 * Math.max(0, Math.min(1, fraction))).toFixed(3) + '%';
    };
    // The ends, as they are now: the shade outside each handle, the handles, the times.
    var show = function() {
        from.parentNode.style.setProperty('--x', at(wave.start / wave.span));
        to.parentNode.style.setProperty('--x', at((wave.end - (wave.ms - wave.span)) / wave.span));
        from.setAttribute('aria-valuetext', fine(wave.start));
        to.setAttribute('aria-valuetext', fine(wave.end));
        label('ayv-empieza-t', fine(wave.start));
        label('ayv-acaba-t', fine(wave.end));
        label('ayv-dura-t', clock(wave.end - wave.start));
    };

    /**
     * The whole reading as a wave with its pauses marked, and its two ends close up, each with its handle where it was
     * cleaned: on a reading of minutes, a second and a half at the start is too thin to see in the whole of it.
     *
     * @param {AudioBuffer} audio The reading, decoded.
     * @param {number[]} joins ms where a piece ends and the next one starts.
     * @param {number} start ms where the voice starts, from measure().
     * @param {number} end ms where it ends.
     */
    var draw = function(audio, joins, start, end) {
        var ms = audio.duration * 1000;
        // Ten seconds of each end, or half of a reading shorter than twenty.
        var span = Math.min(10000, ms / 2);
        wave.ms = ms;
        wave.span = span;
        wave.start = start;
        wave.end = end;
        var all = levels(audio, 160);
        var loudest = Math.max.apply(null, all);
        shape(document.getElementById('ayv-onda'), all, loudest, joins.map(function(join) {
            return join / ms;
        }));
        if (from && to) {
            bars(from.parentNode.querySelector('svg'), levels(audio, 80, 0, span), loudest);
            bars(to.parentNode.querySelector('svg'), levels(audio, 80, ms - span, ms), loudest);
            from.min = '0';
            from.max = String(Math.round(span));
            from.value = String(Math.round(start));
            to.min = String(Math.round(ms - span));
            to.max = String(Math.round(ms));
            to.value = String(Math.round(end));
            show();
            var note = document.getElementById('ayv-recorte-nota');
            // Nothing to cut (a reading that starts and ends with the voice): it says so, not «0,0 s».
            // Only one end cut (the microphone cut at the end, a reading that starts at once): it says which.
            var head = start >= 100;
            var tail = ms - end >= 100;
            var says = note.getAttribute('data-says');
            if (!head && !tail && note.getAttribute('data-none')) {
                says = note.getAttribute('data-none');
            } else if (head !== tail && note.getAttribute(head ? 'data-start' : 'data-end')) {
                says = fill(note.getAttribute(head ? 'data-start' : 'data-end'), seconds(head ? start : ms - end));
            } else {
                says = fill(says, {start: seconds(start), end: seconds(ms - end)});
            }
            note.textContent = says;
        } else {
            label('ayv-dura-t', clock(ms));
        }
    };
    if (from && to) {
        // A second of reading at least stays between the handles, on a reading shorter than twenty.
        var moved = function(event) {
            if (Number(to.value) - Number(from.value) < 1000) {
                if (event.target === from) {
                    from.value = String(Number(to.value) - 1000);
                } else {
                    to.value = String(Number(from.value) + 1000);
                }
            }
            wave.start = Number(from.value);
            wave.end = Number(to.value);
            show();
            wave.trims.forEach(function(call) {
                call(wave.start, wave.end, event.type === 'change' ? event.target.id : null);
            });
        };
        [from, to].forEach(function(range) {
            range.addEventListener('input', moved);
            range.addEventListener('change', moved);
        });
    }
    if (whole) {
        // A touch on the whole reading plays from there (inside its ends).
        whole.addEventListener('click', function(event) {
            if (!wave.ms) {
                return;
            }
            var rect = whole.getBoundingClientRect();
            var ms = Math.round(wave.ms * (event.clientX - rect.left) / rect.width);
            wave.seeks.forEach(function(call) {
                call(Math.min(wave.end, Math.max(wave.start, ms)));
            });
        });
    }

    /**
     * What is sounding now: the line over the whole reading, or the part heard filling the button that plays it.
     *
     * @param {Element} button The one that plays.
     * @param {number|null} ms Where it goes, or null when it stops.
     * @param {number} of How long what it plays lasts, if it is not the whole reading.
     */
    var playing = function(button, ms, of) {
        var on = ms !== null && ms !== undefined;
        button.setAttribute('aria-pressed', on ? 'true' : 'false');
        if (button.id === 'ayv-oir' && whole) {
            whole.classList.toggle('is-sonando', on);
            whole.style.setProperty('--f', on && wave.ms ? Math.max(0, Math.min(1, ms / wave.ms)).toFixed(4) : '0');
        } else {
            button.style.setProperty('--avance', on && of ? at(ms / of) : '0%');
        }
    };

    /**
     * How the voice was levelled: a line in words and the little drawing of where it was and where it is now.
     *
     * @param {number|null} gain dB, from gainFor(); null hides it.
     */
    var level = function(gain) {
        var line = document.getElementById('ayv-nivel');
        if (!line) {
            return;
        }
        line.hidden = gain === null || gain === undefined;
        if (line.hidden) {
            return;
        }
        var which = Math.abs(gain) <= 0.5 ? 'same' : (gain > 0 ? (gain > 6 ? 'upmore' : 'up') : (gain < -6 ? 'downmore' : 'down'));
        line.querySelector('.ayv-rec-nivel-dice').textContent = line.getAttribute('data-' + which);
        // Where the voice was, against the mark of the others (the middle): 18 dB to a side is the end of the line.
        var x = 32 - 26 * Math.max(-18, Math.min(18, gain)) / 18;
        line.querySelector('.ayv-rec-nivel-antes').setAttribute('cx', x.toFixed(1));
        var step = line.querySelector('.ayv-rec-nivel-paso');
        step.setAttribute('x1', x.toFixed(1));
        line.classList.toggle('is-igual', which === 'same');
    };

    /**
     * Listening at the end: with the soft sound chosen, named; or alone.
     */
    var listenSays = function() {
        var row = plate.querySelector('.ayv-rec-oir');
        if (!row) {
            return;
        }
        var picked = document.querySelector('input[name="ayv-backdrop"]:checked');
        var the = picked ? picked.getAttribute('data-the') : '';
        document.getElementById('ayv-oir-t').textContent = row.getAttribute(the ? 'data-with' : 'data-alone');
        row.querySelector('.ayv-rec-oir-dice').textContent = the ? fill(row.getAttribute('data-withhint'), the)
            : row.getAttribute('data-alonehint');
    };
    document.querySelectorAll('input[name="ayv-backdrop"]').forEach(function(radio) {
        radio.addEventListener('change', listenSays);
    });
    listenSays();

    /**
     * A reading left half-done in this device: its part, when, how long and its pieces.
     *
     * @param {Object} found part (its title), time (ms since 1970), pieces (ms of each).
     */
    var draft = function(found) {
        var card = document.getElementById('ayv-borrador');
        var when = new Date(found.time);
        var hour = new Intl.DateTimeFormat(lang, {hour: 'numeric', minute: '2-digit'}).format(when);
        var today = new Date();
        var yesterday = new Date(today.getFullYear(), today.getMonth(), today.getDate() - 1);
        var said;
        if (when.toDateString() === today.toDateString()) {
            said = fill(card.getAttribute('data-today'), hour);
        } else if (when.toDateString() === yesterday.toDateString()) {
            said = fill(card.getAttribute('data-yesterday'), hour);
        } else {
            said = fill(card.getAttribute('data-day'), {day: new Intl.DateTimeFormat(lang, {day: 'numeric', month: 'long'})
                .format(when), time: hour});
        }
        var count = plate.querySelector('.ayv-rec-trozos');
        var total = found.pieces.reduce(function(sum, ms) {
            return sum + ms;
        }, 0);
        card.querySelector('.ayv-rec-borrador-parte').textContent = found.part;
        card.querySelector('.ayv-rec-borrador-dice').textContent = fill(card.getAttribute('data-line'), {when: said,
            length: clock(total), pieces: found.pieces.length === 1 ? count.getAttribute('data-one')
                : fill(count.getAttribute('data-many'), found.pieces.length)});
        pieces(found.pieces, null, card.querySelector('.ayv-rec-tira'));
        state('borrador');
    };

    window.ayvVoiceView = {
        state: state,
        countdown: countdown,
        pieces: pieces,
        wave: draw,
        playing: playing,
        level: level,
        draft: draft,
        listenSays: listenSays,
        // Safari records one file: its pieces cannot be thrown away nor its ends cut, so those choices are not shown.
        whole: function(yes) {
            plate.classList.toggle('is-entera', !!yes);
        },
        onTrim: function(call) {
            wave.trims.push(call);
        },
        onSeek: function(call) {
            wave.seeks.push(call);
        },
        trim: function() {
            return {start: wave.start, end: wave.end};
        },
    };
})();
