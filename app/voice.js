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
 * Ayvurenda: recording a reading with the browser's microphone. Record, stop, listen, record again if it did not sound
 * right, say what was read, send. The recording waits for approval; nobody else hears it until then. A long book goes in
 * parts: its chapter chosen first, a pause between pages, and a soft sound under the whole reading, heard for a moment.
 *
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';
    var config = window.AYV || {};
    var str = config.strings || {};
    var rec = document.getElementById('ayv-rec');
    var stop = document.getElementById('ayv-stop');
    var again = document.getElementById('ayv-again');
    var send = document.getElementById('ayv-send');
    // «¿Para quién es?»: for the little ones, and then, only then, the family too; the sentence says what happens.
    var forwho = document.querySelector('.ayv-lp-para');
    var little = document.getElementById('ayv-lp-little');
    var family = document.getElementById('ayv-lp-family');
    var pressed = function(button) {
        return !!button && button.getAttribute('aria-pressed') === 'true';
    };
    var sayForWho = function() {
        var note = forwho ? forwho.querySelector('.ayv-lp-nota') : null;
        if (!note) {
            return;
        }
        note.textContent = !pressed(little) ? forwho.getAttribute('data-off')
            : forwho.getAttribute('data-on') + (pressed(family) ? ' ' + forwho.getAttribute('data-family') : '');
    };
    if (little) {
        little.addEventListener('click', function() {
            var on = !pressed(little);
            little.setAttribute('aria-pressed', on ? 'true' : 'false');
            if (family) {
                family.setAttribute('aria-disabled', on ? 'false' : 'true');
                if (!on) {
                    family.setAttribute('aria-pressed', 'false');
                }
            }
            sayForWho();
        });
    }
    if (family) {
        family.addEventListener('click', function() {
            if (family.getAttribute('aria-disabled') === 'true') {
                return;
            }
            family.setAttribute('aria-pressed', pressed(family) ? 'false' : 'true');
            sayForWho();
        });
    }
    var preview = document.getElementById('ayv-preview');
    var time = document.getElementById('ayv-time');
    var said = document.getElementById('ayv-voice-said');
    var what = document.getElementById('ayv-what');
    var consent = document.getElementById('ayv-consent');
    // Copying a family's link.
    document.querySelectorAll('[data-copy]').forEach(function(button) {
        button.addEventListener('click', function() {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(button.getAttribute('data-copy')).then(function() {
                    button.textContent = str.copied || button.textContent;
                    return true;
                }).catch(function() {
                    return false;
                });
            }
        });
    });
    // The part about to be read: a chapter of the chips, or «Otra cosa», which asks what it is.
    var whatField = document.getElementById('ayv-what-field');
    var chosenPart = function() {
        var checked = document.querySelector('input[name="ayv-part"]:checked');
        return checked ? checked : null;
    };
    // A part may have a name of its own («El faro que no se apaga»); «Otra cosa» says what was read.
    var barPart = document.getElementById('ayv-vp-barra-parte');
    var showPart = function() {
        var checked = chosenPart();
        if (!checked || !whatField) {
            return;
        }
        var other = Number(checked.value) < 0;
        whatField.querySelector('.ayv-what-word').textContent = whatField.getAttribute(other ? 'data-other' : 'data-part');
        whatField.querySelector('.ayv-what-hint').textContent = whatField.getAttribute(other ? 'data-otherhint' : 'data-parthint');
        if (what) {
            what.value = '';
        }
        if (barPart) {
            barPart.textContent = other ? whatField.getAttribute('data-other') : checked.getAttribute('data-title');
        }
        // Each part has its sound: the one it has, or the one of the part before it.
        var which = document.getElementById('ayv-vp-bt-word');
        if (!other && which) {
            which.textContent = (str.voicebackdropforpart || '{$a}').replace('{$a}', function() {
                return checked.getAttribute('data-title');
            });
            var sound = document.querySelector('input[name="ayv-backdrop"][value="' + (checked.getAttribute('data-sound') || '') + '"]');
            if (sound) {
                sound.checked = true;
            }
        }
    };
    document.querySelectorAll('input[name="ayv-part"]').forEach(function(radio) {
        radio.addEventListener('change', showPart);
    });
    showPart();
    // «Más»: five chapters more, after the last one shown.
    var more = document.getElementById('ayv-vp-more');
    if (more) {
        more.addEventListener('click', function() {
            var numbers = document.querySelectorAll('.ayv-vp-number');
            var last = numbers[numbers.length - 1];
            if (!last) {
                return;
            }
            var from = Number(last.querySelector('input').value);
            for (var n = from + 1; n <= from + 5 && n <= 300; n++) {
                var chip = last.cloneNode(true);
                chip.classList.remove('is-done', 'is-waiting');
                var input = chip.querySelector('input');
                var title = (str.voicepartchapter || '{$a}').replace('{$a}', String(n));
                input.value = String(n);
                input.checked = false;
                input.setAttribute('data-title', title);
                input.setAttribute('aria-label', title);
                input.addEventListener('change', showPart);
                chip.querySelector('.ayv-vp-word').textContent = String(n);
                more.parentNode.insertBefore(chip, more);
                last = chip;
            }
        });
    }
    // The soft sound under the part about to be read: a touch chooses it and lets it be heard for a moment.
    var sample = null;
    var hush = function() {
        if (sample) {
            sample.pause();
            sample = null;
        }
    };
    // iPhone and iPad leave an audio element's volume to the device's buttons (it always reads 1): there the sample goes
    // through Web Audio, whose gain does follow; with neither, it is not heard rather than heard loud.
    var sampleContext = null;
    var soft = function(audio, value) {
        try {
            audio.volume = value;
            if (Math.abs(audio.volume - value) < 0.01) {
                return true;
            }
        } catch (e) {
            // Read-only: as on iOS.
        }
        var Context = window.AudioContext || window.webkitAudioContext;
        if (!Context) {
            return false;
        }
        try {
            sampleContext = sampleContext || new Context();
            if (sampleContext.state === 'suspended' && sampleContext.resume) {
                sampleContext.resume();
            }
            var gain = sampleContext.createGain();
            gain.gain.value = value;
            sampleContext.createMediaElementSource(audio).connect(gain);
            gain.connect(sampleContext.destination);
            return true;
        } catch (e) {
            return false;
        }
    };
    var backdrop = function() {
        var checked = document.querySelector('input[name="ayv-backdrop"]:checked');
        return checked ? checked.value : null;
    };
    document.querySelectorAll('input[name="ayv-backdrop"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            hush();
            var ogg = radio.getAttribute('data-ogg');
            var m4a = radio.getAttribute('data-m4a');
            if (ogg || m4a) {
                sample = new Audio();
                sample.src = sample.canPlayType('audio/ogg; codecs=opus') ? ogg : m4a;
                // Alone, a little above how it sounds under a voice, to know which it is.
                if (!soft(sample, 0.15)) {
                    sample = null;
                    return;
                }
                sample.play().catch(function() {
                    return null;
                });
                var playing = sample;
                window.setTimeout(function() {
                    if (sample === playing) {
                        hush();
                    }
                }, 6000);
            }
        });
    });
    // A part already recorded changes its sound with a touch, without recording again: it plays apart from the voice.
    document.querySelectorAll('.ayv-vp-son [data-item] input').forEach(function(radio) {
        radio.addEventListener('change', function() {
            var box = radio.closest('.ayv-vp-son');
            box.querySelector('summary').textContent = radio.getAttribute('data-says');
            if (window.fetch && config.sendurl) {
                var body = new FormData();
                body.append('sesskey', config.sesskey);
                body.append('library', config.library);
                body.append('item', radio.closest('[data-item]').getAttribute('data-item'));
                body.append('do', 'backdrop');
                body.append('backdrop', radio.value);
                fetch(config.sendurl, {method: 'POST', credentials: 'same-origin', body: body}).catch(function() {
                    return null;
                });
            }
        });
    });
    if (!rec || !window.fetch) {
        return;
    }
    // A family records once they have given their permission.
    if (consent) {
        consent.addEventListener('change', function() {
            rec.disabled = !consent.checked;
        });
    }
    var MAX = 20 * 60;
    var recorder = null;
    var chunks = [];
    var blob = null;
    var started = 0;
    var ticker = null;
    var stream = null;
    var after = document.getElementById('ayv-after');
    var pause = document.getElementById('ayv-pause');
    var pausedAt = 0;
    // On a phone, the part and the button stay at the foot of the screen, to read the book and record at once.
    var bar = document.getElementById('ayv-vp-barra');
    var sayBar = function(state) {
        if (!bar) {
            return;
        }
        bar.hidden = state === 'done';
        bar.classList.toggle('is-grabando', state === 'recording');
        bar.querySelector('.ayv-vp-barra-word').textContent = bar.getAttribute(state === 'recording' ? 'data-stop' : 'data-record');
    };
    if (bar) {
        bar.querySelector('.ayv-vp-barra-boton').addEventListener('click', function() {
            if (!stop.hidden) {
                stop.click();
            } else if (!rec.hidden && !rec.disabled) {
                rec.click();
            }
        });
    }
    // On a phone (the review of 21 Nov 2026, with the mock-up): which part to read goes above the text, the soft sound
    // opens as a sheet from the bar, the bar counts the time while recording, and after recording the plate comes up from
    // the foot. Widened again, everything goes back where it was.
    var narrow = window.matchMedia ? window.matchMedia('(max-width: 47.99rem)') : null;
    var plate = document.getElementById('ayv-recorder');
    var parts = document.getElementById('ayv-vp');
    var reading = document.querySelector('.ayv-voice-grid') || document.querySelector('.ayv-voice-text');
    var sounds = document.getElementById('ayv-vp-backdrops');
    var sheet = document.getElementById('ayv-vp-hoja');
    var veil = document.getElementById('ayv-vp-velo');
    var sheetDone = document.getElementById('ayv-vp-listo');
    var soundChip = document.getElementById('ayv-vp-barra-fondo');
    var barClock = document.getElementById('ayv-vp-barra-tiempo');
    var afterTitle = document.getElementById('ayv-rec-titulo');
    var home = function(node) {
        if (!node || !node.parentNode) {
            return null;
        }
        var mark = document.createComment('');
        node.parentNode.insertBefore(mark, node);
        return mark;
    };
    var partsHome = home(parts);
    var soundsHome = home(sounds);
    // The veil and the sheet hang from the page, not from the plate: risen after recording, the plate makes a layer of its
    // own, and a veil inside it would cover it.
    var section = document.querySelector('.ayv-voice');
    if (section && veil && sheet) {
        section.appendChild(veil);
        section.appendChild(sheet);
    }
    var small = function() {
        return narrow ? narrow.matches : false;
    };
    var closeSheet = function() {
        if (!sheet || sheet.hidden) {
            return;
        }
        sheet.hidden = true;
        veil.hidden = !(plate && plate.classList.contains('is-despues') && small());
        if (soundChip) {
            soundChip.focus();
        }
    };
    var sayChip = function() {
        if (!soundChip) {
            return;
        }
        var picked = document.querySelector('input[name="ayv-backdrop"]:checked');
        var word = picked && picked.parentNode.querySelector('.ayv-vp-word') ? picked.parentNode.querySelector('.ayv-vp-word')
            .textContent.trim() : '';
        // «Sin fondo» says itself; a sound, «Fondo: mar».
        soundChip.querySelector('.ayv-vp-barra-sonido').textContent = !picked || picked.value === '' ? word
            : (soundChip.getAttribute('data-says') || '{$a}').replace('{$a}', function() {
                return word.charAt(0).toLocaleLowerCase() + word.slice(1);
            });
    };
    var place = function() {
        if (parts && reading && partsHome) {
            if (small()) {
                reading.parentNode.insertBefore(parts, reading);
            } else {
                partsHome.parentNode.insertBefore(parts, partsHome.nextSibling);
            }
        }
        if (sounds && sheet && soundsHome) {
            if (small()) {
                sheet.insertBefore(sounds, sheetDone);
            } else {
                closeSheet();
                soundsHome.parentNode.insertBefore(sounds, soundsHome.nextSibling);
            }
        }
        if (plate && !small()) {
            plate.classList.remove('is-despues');
            if (veil) {
                veil.hidden = true;
            }
        }
    };
    if (soundChip && sheet) {
        soundChip.addEventListener('click', function() {
            sheet.hidden = false;
            veil.hidden = false;
            var picked = sheet.querySelector('input[name="ayv-backdrop"]:checked') || sheet.querySelector('input');
            if (picked) {
                picked.focus();
            }
        });
        sheetDone.addEventListener('click', closeSheet);
        veil.addEventListener('click', closeSheet);
        sheet.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeSheet();
            }
        });
    }
    document.querySelectorAll('input[name="ayv-backdrop"], input[name="ayv-part"]').forEach(function(radio) {
        radio.addEventListener('change', sayChip);
    });
    sayChip();
    place();
    if (narrow) {
        if (narrow.addEventListener) {
            narrow.addEventListener('change', place);
        } else if (narrow.addListener) {
            narrow.addListener(place);
        }
    }
    // What was just recorded comes with its name; on a phone, as a sheet from the foot.
    var showAfter = function() {
        if (afterTitle) {
            afterTitle.textContent = (afterTitle.getAttribute('data-says') || '{$a}').replace('{$a}', function() {
                return barPart ? barPart.textContent : '';
            });
            afterTitle.hidden = !barPart;
        }
        if (plate && small()) {
            plate.classList.add('is-despues');
            plate.scrollTop = 0;
            if (veil) {
                veil.hidden = false;
            }
        }
    };
    var hideAfter = function() {
        if (plate) {
            plate.classList.remove('is-despues');
        }
        if (veil && (!sheet || sheet.hidden)) {
            veil.hidden = true;
        }
    };
    var level = document.getElementById('ayv-level');
    var listening = null;

    // A meter that moves with the voice: the loudness of the microphone, many times a second, while recording.
    var meter = function(source) {
        var Context = window.AudioContext || window.webkitAudioContext;
        if (!Context || !level) {
            return;
        }
        var context = new Context();
        var analyser = context.createAnalyser();
        analyser.fftSize = 512;
        context.createMediaStreamSource(source).connect(analyser);
        var samples = new Uint8Array(analyser.fftSize);
        var frame = function() {
            if (!listening) {
                return;
            }
            analyser.getByteTimeDomainData(samples);
            var sum = 0;
            for (var i = 0; i < samples.length; i++) {
                var value = (samples[i] - 128) / 128;
                sum += value * value;
            }
            level.style.transform = 'scaleX(' + Math.min(1, Math.sqrt(sum / samples.length) * 4).toFixed(3) + ')';
            window.requestAnimationFrame(frame);
        };
        listening = context;
        window.requestAnimationFrame(frame);
    };
    var quiet = function() {
        if (listening) {
            listening.close();
            listening = null;
        }
        if (level) {
            level.style.transform = 'scaleX(0)';
        }
    };

    // A browser's WebM does not say how long it lasts: the player then cannot tell it nor go forward. The length the
    // recorder measured goes into its header (Segment › Info › Duration) before it is sent.
    var withDuration = function(recording, ms) {
        if (recording.type.indexOf('webm') < 0 || !recording.arrayBuffer || !window.DataView) {
            return Promise.resolve(recording);
        }
        return recording.arrayBuffer().then(function(buffer) {
            var bytes = new Uint8Array(buffer);
            var id = function(at) {
                var first = bytes[at];
                var length = first & 0x80 ? 1 : (first & 0x40 ? 2 : (first & 0x20 ? 3 : (first & 0x10 ? 4 : 0)));
                var value = 0;
                for (var i = 0; i < length; i++) {
                    value = value * 256 + bytes[at + i];
                }
                return length ? {length: length, value: value} : null;
            };
            var size = function(at) {
                var first = bytes[at];
                var length = 1;
                var mask = 0x80;
                while (length <= 8 && !(first & mask)) {
                    length++;
                    mask >>= 1;
                }
                if (length > 8) {
                    return null;
                }
                var value = first & (mask - 1);
                var unknown = value === mask - 1;
                for (var i = 1; i < length; i++) {
                    value = value * 256 + bytes[at + i];
                    unknown = unknown && bytes[at + i] === 0xff;
                }
                return {length: length, value: value, unknown: unknown};
            };
            var header = id(0);
            if (!header || header.value !== 0x1A45DFA3) {
                return recording;
            }
            var at = header.length + size(header.length).length + size(header.length).value;
            var segment = id(at);
            if (!segment || segment.value !== 0x18538067 || !size(at + segment.length).unknown) {
                return recording;
            }
            at += segment.length + size(at + segment.length).length;
            while (at < bytes.length) {
                var child = id(at);
                var length = child ? size(at + child.length) : null;
                if (!child || !length || child.value === 0x1F43B675) {
                    return recording;
                }
                if (child.value === 0x1549A966) {
                    break;
                }
                at += child.length + length.length + length.value;
            }
            var info = id(at);
            var infoSize = info ? size(at + info.length) : null;
            if (!info || !infoSize || info.value !== 0x1549A966) {
                return recording;
            }
            var start = at + info.length + infoSize.length;
            var end = start + infoSize.value;
            var scale = 1000000;
            for (var here = start; here < end;) {
                var element = id(here);
                var elementSize = element ? size(here + element.length) : null;
                if (!element || !elementSize) {
                    return recording;
                }
                var data = here + element.length + elementSize.length;
                if (element.value === 0x4489) {
                    return recording;
                }
                if (element.value === 0x2AD7B1) {
                    scale = 0;
                    for (var k = 0; k < elementSize.value; k++) {
                        scale = scale * 256 + bytes[data + k];
                    }
                }
                here = data + elementSize.value;
            }
            var duration = new Uint8Array(11);
            duration.set([0x44, 0x89, 0x88]);
            new DataView(duration.buffer).setFloat64(3, ms * 1000000 / (scale || 1000000));
            var grown = new Uint8Array(8);
            var value = infoSize.value + 11;
            grown[0] = 0x01;
            for (var j = 7; j >= 1; j--) {
                grown[j] = value % 256;
                value = Math.floor(value / 256);
            }
            return new Blob([bytes.subarray(0, at + info.length), grown, bytes.subarray(start, end), duration,
                bytes.subarray(end)], {type: recording.type});
        }).catch(function() {
            return recording;
        });
    };
    var measured = 0;
    // Why it stopped by itself, over what was recorded.
    var because = function(text) {
        document.getElementById('ayv-rec-nota').textContent = text || '';
    };
    // Nothing recorded is lost by accident (24 Nov 2026): the screen stays on while the microphone is open, since whoever
    // reads a paper book does not touch the phone, and leaving with something not sent asks first.
    var awake = null;
    var recording = function() {
        return !!recorder && recorder.state !== 'inactive';
    };
    var keepAwake = function() {
        if (!navigator.wakeLock || !recording()) {
            return;
        }
        navigator.wakeLock.request('screen').then(function(held) {
            if (recording()) {
                awake = held;
            } else {
                held.release();
            }
            return held;
        }).catch(function() {
            return null;
        });
    };
    var letSleep = function() {
        if (awake) {
            awake.release().catch(function() {
                return null;
            });
            awake = null;
        }
    };
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'visible') {
            keepAwake();
        }
    });
    window.addEventListener('beforeunload', function(event) {
        if (recording() || blob || (pieces && pieces.length)) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
    var type = function() {
        var options = ['audio/webm;codecs=opus', 'audio/ogg;codecs=opus', 'audio/mp4'];
        for (var i = 0; i < options.length; i++) {
            if (window.MediaRecorder && window.MediaRecorder.isTypeSupported && window.MediaRecorder.isTypeSupported(options[i])) {
                return options[i];
            }
        }
        return '';
    };
    var extension = function(mime) {
        return mime.indexOf('ogg') >= 0 ? 'ogg' : (mime.indexOf('mp4') >= 0 ? 'm4a' : 'webm');
    };
    var clock = function() {
        if (pausedAt || !recording()) {
            return;
        }
        var live = Date.now() - pieceStarted;
        var seconds = Math.floor((byPieces ? total() + live : Date.now() - started) / 1000);
        if (byPieces && view) {
            view.pieces(lengths, live);
        }
        time.textContent = (str.voicetime || '').replace('{$a}', function() { return Math.floor(seconds / 60) + ':' + String(seconds % 60).padStart(2, '0'); });
        if (barClock) {
            barClock.textContent = Math.floor(seconds / 60) + ':' + String(seconds % 60).padStart(2, '0');
        }
        if (seconds >= MAX) {
            stop.click();
            because(str.voicelimit);
        }
    };

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || !window.MediaRecorder) {
        rec.disabled = true;
        said.textContent = str.voicenomic || '';
        return;
    }
    // A reading is recorded in pieces where the browser records WebM (Chrome, Edge, Firefox): each pause closes one, a
    // piece read badly can be thrown away, and at the end they are joined, their ends cut and the voice levelled, without
    // encoding anything again (voiceedit.js); app/voiceview.js draws each moment. Elsewhere (Safari records MP4), one
    // recording that pauses, as before. Five seconds are counted before the microphone records, where there is a view.
    var edit = window.ayvVoiceEdit || null;
    var view = window.ayvVoiceView || null;
    var mime = '';
    var byPieces = false;
    // The pieces closed (Blobs) and how long each lasted (ms), side by side.
    var pieces = [];
    var lengths = [];
    var pieceStarted = 0;
    var closing = '';
    var stopCount = null;
    // The reading joined, after: its parsed pieces, where the voice starts and ends, and its gain.
    var made = null;
    var draftKey = 'v1:' + (config.library || '') + ':' + (config.record || config.token || '');
    var total = function() {
        return lengths.reduce(function(sum, ms) {
            return sum + ms;
        }, 0);
    };
    var partTitle = function() {
        var part = chosenPart();
        if (!part) {
            return what ? what.value : '';
        }
        return part.getAttribute('data-title') || (part.parentNode ? part.parentNode.textContent.trim() : '');
    };
    var keepDraft = function(current, currentMs) {
        if (!edit || !byPieces) {
            return;
        }
        var part = chosenPart();
        var all = pieces.concat(current ? [current] : []);
        if (!all.length) {
            edit.draft.drop(draftKey);
            return;
        }
        edit.draft.save(draftKey, {pieces: all, lengths: lengths.concat(current ? [currentMs || 0] : []),
            part: part ? part.value : '', parttitle: partTitle(), title: what ? what.value : '', backdrop: backdrop(), mime: mime,
            time: Date.now()});
    };
    var startPiece = function() {
        recorder = mime ? new window.MediaRecorder(stream, {mimeType: mime}) : new window.MediaRecorder(stream);
        chunks = [];
        closing = '';
        recorder.addEventListener('dataavailable', function(event) {
            if (event.data && event.data.size) {
                chunks.push(event.data);
                // What is being read is kept every ten seconds too, not only when a piece closes.
                if (byPieces && chunks.length % 10 === 0) {
                    keepDraft(new Blob(chunks, {type: mime}), Date.now() - pieceStarted);
                }
            }
        });
        recorder.addEventListener('stop', pieceStopped);
        recorder.start(1000);
        pieceStarted = Date.now();
    };
    var decode = function(sound) {
        var Context = window.AudioContext || window.webkitAudioContext;
        return sound.arrayBuffer().then(function(buffer) {
            var context = new Context();
            return context.decodeAudioData(buffer).then(function(audio) {
                context.close();
                return audio;
            }, function(error) {
                context.close();
                throw error;
            });
        });
    };
    // The recording ready to hear and send: its address in the player, its length, and the button to send it.
    var ready = function(sound, ms) {
        blob = sound;
        measured = ms;
        var seconds = Math.floor(ms / 1000);
        var says = (str.voicelength || '').replace('{$a}', function() {
            return Math.floor(seconds / 60) + ':' + String(seconds % 60).padStart(2, '0');
        });
        // With the wave, its length goes under it (and follows the handles); above, it would be said twice.
        var under = view ? document.getElementById('ayv-dura-t') : null;
        time.textContent = under ? '' : says;
        if (under && !under.textContent) {
            under.textContent = says;
        }
        if (preview.src) {
            URL.revokeObjectURL(preview.src);
        }
        preview.src = URL.createObjectURL(blob);
        send.disabled = false;
    };
    var openAfter = function() {
        after.hidden = false;
        showAfter();
        if (view) {
            view.state('despues');
        }
        var why = document.getElementById('ayv-rec-nota');
        if (why.textContent) {
            why.scrollIntoView({block: 'center'});
        }
    };
    // The reading as it will be sent, from where its ends are now; a moment after the last touch of a handle.
    var recut = null;
    var peekAt = null;
    // A handle let go: the cut is heard, two seconds from the new start or the last three up to the new end.
    var peek = function(which, length) {
        var audio = new Audio(preview.src);
        var from = which === 'ayv-acaba' ? Math.max(0, length - 3000) : 0;
        var to = which === 'ayv-acaba' ? length : Math.min(length, 2000);
        stopListening();
        hearing = {audio: audio, button: null, url: '', under: null};
        audio.addEventListener('loadedmetadata', function() {
            audio.currentTime = from / 1000;
            audio.play().catch(function() {
                return null;
            });
        }, {once: true});
        audio.addEventListener('timeupdate', function() {
            if (audio.currentTime * 1000 >= to) {
                audio.pause();
            }
        });
    };
    var trimmed = function() {
        if (!made) {
            return;
        }
        var ends = view ? view.trim() : {start: made.start, end: made.end};
        var done = edit.cut(made.parsed, ends.start, ends.end, made.gain);
        ready(done.blob, done.duration);
        if (peekAt) {
            peek(peekAt, done.duration);
            peekAt = null;
        }
    };
    // The pieces, as one reading: joined, its silent ends cut and its voice at the level of the others.
    var assemble = function() {
        said.textContent = str.voicepreparing || '';
        made = null;
        Promise.all(pieces.map(function(piece) {
            return piece.arrayBuffer();
        })).then(function(buffers) {
            var parsed = buffers.map(edit.parse).filter(function(piece) {
                return piece && piece.packets.length;
            });
            if (!parsed.length) {
                throw new Error('not a WebM');
            }
            var joins = [];
            parsed.slice(0, -1).reduce(function(sum, piece) {
                joins.push(sum + edit.length(piece));
                return sum + edit.length(piece);
            }, 0);
            var whole = edit.cut(parsed, 0, Infinity, 0);
            return decode(whole.blob).then(function(audio) {
                var found = edit.measure(audio);
                made = {parsed: parsed, start: found.start, end: found.end, gain: edit.gainFor(found)};
                said.textContent = '';
                if (view) {
                    view.wave(audio, joins, found.start, found.end);
                    view.level(made.gain);
                }
                trimmed();
                openAfter();
                return made;
            }, function() {
                // Not decoded here: joined as it was recorded, with nothing cut.
                said.textContent = '';
                made = {parsed: parsed, start: 0, end: whole.duration, gain: 0};
                if (view) {
                    view.whole(true);
                }
                ready(whole.blob, whole.duration);
                openAfter();
                return whole;
            });
        }).catch(function() {
            said.textContent = str.voicefailed || '';
        });
    };
    var stopStream = function() {
        quiet();
        if (stream) {
            stream.getTracks().forEach(function(track) {
                track.stop();
            });
            stream = null;
        }
    };
    var pieceStopped = function() {
        var sound = new Blob(chunks, {type: (recorder && recorder.mimeType) || mime || 'audio/webm'});
        var why = closing;
        closing = '';
        // Stopped by itself: the microphone was cut (a call, headphones that went off). What there is stays.
        if (!why) {
            if (byPieces) {
                lengths.push(Date.now() - pieceStarted);
            }
            finish(true);
            because(str.voicecut);
            why = 'finish';
        }
        if (byPieces) {
            if (sound.size) {
                pieces.push(sound);
            } else {
                lengths.pop();
            }
            keepDraft(null);
            if (view) {
                view.pieces(lengths, null);
            }
            if (why === 'finish') {
                stopStream();
                assemble();
            }
            return;
        }
        stopStream();
        withDuration(sound, measured).then(function(fixed) {
            ready(fixed, measured);
            openAfter();
            if (view && window.AudioContext) {
                // Safari's one file: drawn whole, without handles.
                decode(fixed).then(function(audio) {
                    view.wave(audio, [], 0, audio.duration * 1000);
                    return audio;
                }).catch(function() {
                    return null;
                });
            }
            return fixed;
        }).catch(function() {
            return null;
        });
    };
    // The microphone, asked for before the count: its question comes when the button is touched, not five seconds later.
    var openMic = function() {
        if (stream) {
            return Promise.resolve(stream);
        }
        return navigator.mediaDevices.getUserMedia({audio: true}).then(function(s) {
            stream = s;
            meter(stream);
            return s;
        });
    };
    var begin = function() {
        stopCount = null;
        if (!stream) {
            return;
        }
        if (pausedAt) {
            started += Date.now() - pausedAt;
            pausedAt = 0;
        }
        startPiece();
        keepAwake();
        ticker = ticker || setInterval(clock, 500);
        rec.hidden = true;
        stop.hidden = false;
        sayBar('recording');
        if (pause && (byPieces || recorder.pause)) {
            pause.hidden = false;
            pause.setAttribute('aria-pressed', 'false');
        }
        if (view) {
            view.state('grabando');
            if (byPieces) {
                view.pieces(lengths, 1);
            }
        }
    };
    // The count, then the recording. first() runs as the count ends, before the microphone records; back() puts things
    // as they were if the count is called off.
    var counted = function(first, back) {
        var go = function() {
            if (first) {
                first();
            }
            begin();
        };
        if (view) {
            stopCount = view.countdown(go, 0, function() {
                stopCount = null;
                if (back) {
                    back();
                }
            });
        } else {
            go();
        }
    };
    var fresh = function(keep) {
        // The take before this one goes only now that there is another, and is not heard under it.
        preview.pause();
        stopListening();
        blob = null;
        made = null;
        after.hidden = true;
        because('');
        hideAfter();
        if (!keep) {
            pieces = [];
            lengths = [];
        }
        send.disabled = true;
        said.textContent = '';
        hush();
    };
    rec.addEventListener('click', function() {
        openMic().then(function() {
            // The take before and its draft go only when the count is over: called off, they are there as they were.
            var had = !!blob;
            stopListening();
            preview.pause();
            hush();
            after.hidden = true;
            hideAfter();
            rec.hidden = true;
            counted(function() {
                mime = type();
                byPieces = !!edit && mime.indexOf('webm') >= 0;
                if (view) {
                    view.whole(!byPieces);
                }
                fresh(false);
                if (edit) {
                    edit.draft.drop(draftKey);
                }
                started = Date.now();
                pausedAt = 0;
            }, function() {
                stopStream();
                if (had) {
                    openAfter();
                } else {
                    rec.hidden = false;
                    view.state('listo');
                }
            });
            return stream;
        }).catch(function() {
            said.textContent = str.voicenomic || '';
        });
    });
    // A pause between pages, for water or a cough: the recording goes on afterwards. By pieces, the pause closes one and
    // the plate offers to hear the last of it, to read it again, to go on or to finish.
    var word = function(text) {
        pause.querySelector('.ayv-rec-word').textContent = text;
    };
    var paused = function() {
        return !!pausedAt;
    };
    var toPause = function() {
        if (!recorder || paused() || !recording()) {
            return;
        }
        pausedAt = Date.now();
        if (byPieces) {
            closing = 'pause';
            lengths.push(Date.now() - pieceStarted);
            recorder.stop();
            letSleep();
            if (view) {
                view.pieces(lengths, null);
                view.state('pausa');
            }
        } else {
            recorder.pause();
            if (view) {
                view.state('pausa');
            }
        }
        pause.setAttribute('aria-pressed', 'true');
        word(pause.getAttribute('data-resume'));
    };
    var fromPause = function() {
        stopListening();
        if (byPieces) {
            counted(null, function() {
                view.state('pausa');
                pause.setAttribute('aria-pressed', 'true');
                word(pause.getAttribute('data-resume'));
            });
        } else if (recorder && recorder.state === 'paused') {
            recorder.resume();
            started += Date.now() - pausedAt;
            pausedAt = 0;
            if (view) {
                view.state('grabando');
            }
        }
        pause.setAttribute('aria-pressed', 'false');
        word(pause.getAttribute('data-pause'));
    };
    if (pause) {
        pause.addEventListener('click', function() {
            if (paused()) {
                fromPause();
            } else {
                toPause();
            }
        });
    }
    var barPause = document.getElementById('ayv-vp-barra-pausa');
    if (barPause) {
        barPause.addEventListener('click', toPause);
    }
    var byId = function(id) {
        return document.getElementById(id);
    };
    if (byId('ayv-pausa-seguir')) {
        byId('ayv-pausa-seguir').addEventListener('click', fromPause);
    }
    if (byId('ayv-pausa-terminar')) {
        byId('ayv-pausa-terminar').addEventListener('click', function() {
            stopListening();
            finish();
        });
    }
    // The last piece read badly: thrown away, and read again after the count (data-ayv-twice arms it first).
    if (byId('ayv-pausa-repetir')) {
        byId('ayv-pausa-repetir').addEventListener('click', function(event) {
            var button = event.currentTarget;
            if (event.isTrusted && button.getAttribute('data-ayv-armed') !== '1') {
                return;
            }
            stopListening();
            if (byPieces && pieces.length) {
                pieces.pop();
                lengths.pop();
                keepDraft(null);
                if (view) {
                    view.pieces(lengths, null);
                }
            }
            fromPause();
        });
    }
    var finish = function(cut) {
        clearInterval(ticker);
        ticker = null;
        letSleep();
        if (stopCount) {
            stopCount();
            stopCount = null;
        }
        var wasPaused = paused();
        if (pausedAt) {
            started += Date.now() - pausedAt;
            pausedAt = 0;
        }
        if (pause) {
            pause.hidden = true;
            pause.setAttribute('aria-pressed', 'false');
            word(pause.getAttribute('data-pause'));
        }
        stop.hidden = true;
        sayBar('done');
        measured = byPieces ? total() : Date.now() - started;
        if (cut) {
            return;
        }
        if (recording()) {
            closing = 'finish';
            if (byPieces) {
                lengths.push(Date.now() - pieceStarted);
                measured = total();
            }
            recorder.stop();
        } else if (byPieces && (wasPaused || pieces.length)) {
            // The last piece closed with the pause.
            stopStream();
            assemble();
        }
    };
    stop.addEventListener('click', function() {
        finish();
    });

    // Listening while recording or after: one sound at a time, and the button that plays it says how far it went.
    var hearing = null;
    var stopListening = function() {
        if (!hearing) {
            return;
        }
        var was = hearing;
        hearing = null;
        was.audio.pause();
        if (was.under) {
            was.under.pause();
        }
        if (was.url) {
            URL.revokeObjectURL(was.url);
        }
        if (view && was.button) {
            view.playing(was.button, null);
        }
    };
    var hear = function(button, sound, from, of, offset) {
        if (hearing && hearing.button === button) {
            stopListening();
            return;
        }
        stopListening();
        var audio = new Audio();
        var url = sound ? URL.createObjectURL(sound) : '';
        audio.src = sound ? url : preview.src;
        hearing = {audio: audio, button: button, url: url, under: null};
        var current = hearing;
        audio.addEventListener('timeupdate', function() {
            if (view && hearing === current) {
                view.playing(button, (offset || 0) + audio.currentTime * 1000, of);
            }
        });
        audio.addEventListener('ended', function() {
            if (hearing === current) {
                stopListening();
            }
        });
        var go = function() {
            if (from) {
                audio.currentTime = from / 1000;
            }
            audio.play().catch(function() {
                stopListening();
            });
        };
        if (audio.readyState >= 1) {
            go();
        } else {
            audio.addEventListener('loadedmetadata', go, {once: true});
        }
        if (view) {
            view.playing(button, offset || 0, of);
        }
        return current;
    };
    // At the end, with the soft sound chosen under it, as low as whoever listens will hear it (lectura.js, 0.05).
    var withSound = function(current) {
        var picked = document.querySelector('input[name="ayv-backdrop"]:checked');
        var ogg = picked ? picked.getAttribute('data-ogg') : '';
        var m4a = picked ? picked.getAttribute('data-m4a') : '';
        if (!current || !(ogg || m4a)) {
            return;
        }
        var under = new Audio();
        under.src = under.canPlayType('audio/ogg; codecs=opus') ? ogg : m4a;
        under.loop = true;
        if (!soft(under, 0.05)) {
            return;
        }
        current.under = under;
        under.play().catch(function() {
            return null;
        });
    };
    if (byId('ayv-oir')) {
        byId('ayv-oir').addEventListener('click', function() {
            if (!blob) {
                return;
            }
            var ends = view && made ? view.trim() : {start: 0};
            withSound(hear(byId('ayv-oir'), null, 0, 0, ends.start));
        });
    }
    if (view) {
        // A touch on the whole reading plays it from there.
        view.onSeek(function(ms) {
            if (!blob) {
                return;
            }
            var ends = made ? view.trim() : {start: 0};
            stopListening();
            var current = hear(byId('ayv-oir'), null, ms - ends.start, 0, ends.start);
            withSound(current);
        });
        // The ends moved: what is sent follows, a moment after the handle stops.
        view.onTrim(function(start, end, released) {
            stopListening();
            if (released) {
                peekAt = released;
            }
            clearTimeout(recut);
            recut = setTimeout(trimmed, 250);
        });
    }
    // The last fifteen seconds read, in a pause.
    if (byId('ayv-pausa-oir')) {
        byId('ayv-pausa-oir').addEventListener('click', function() {
            var button = byId('ayv-pausa-oir');
            if (hearing && hearing.button === button) {
                stopListening();
                return;
            }
            if (!byPieces || !pieces.length) {
                return;
            }
            Promise.all(pieces.map(function(piece) {
                return piece.arrayBuffer();
            })).then(function(buffers) {
                var parsed = buffers.map(edit.parse).filter(function(piece) {
                    return piece && piece.packets.length;
                });
                var all = parsed.reduce(function(sum, piece) {
                    return sum + edit.length(piece);
                }, 0);
                var from = Math.max(0, all - 15000);
                var last = edit.cut(parsed, from, Infinity, 0);
                hear(button, last.blob, 0, last.duration, 0);
                return last;
            }).catch(function() {
                return null;
            });
        });
    }

    // A reading left half-done in this device: it says so, and goes on, is heard or is thrown away.
    var draftFound = null;
    var restore = function(found) {
        draftFound = found;
        var part = found.part !== '' ? document.querySelector('input[name="ayv-part"][value="' + found.part + '"]') : null;
        if (part && !part.checked) {
            part.checked = true;
            part.dispatchEvent(new Event('change', {bubbles: true}));
        }
        var sound = found.backdrop !== null && found.backdrop !== undefined
            ? document.querySelector('input[name="ayv-backdrop"][value="' + found.backdrop + '"]') : null;
        if (sound && !sound.checked) {
            sound.checked = true;
            hush();
        }
        if (what && found.title && Number(found.part) < 0) {
            what.value = found.title;
        }
        view.draft({part: found.parttitle || '', time: found.time, pieces: found.lengths});
    };
    if (edit && view && byId('ayv-borrador') && type().indexOf('webm') >= 0) {
        edit.draft.load(draftKey).then(function(found) {
            if (found && found.pieces && found.pieces.length && !recording() && !blob) {
                restore(found);
            }
            return found;
        }).catch(function() {
            return null;
        });
        byId('ayv-borrador-seguir').addEventListener('click', function() {
            if (!draftFound) {
                return;
            }
            openMic().then(function() {
                var found = draftFound;
                mime = draftFound.mime || type();
                byPieces = true;
                view.whole(false);
                fresh(true);
                pieces = draftFound.pieces.slice();
                lengths = (draftFound.lengths || []).slice();
                started = Date.now() - total();
                pausedAt = 0;
                draftFound = null;
                rec.hidden = true;
                counted(null, function() {
                    stopStream();
                    pieces = [];
                    lengths = [];
                    draftFound = found;
                    rec.hidden = false;
                    view.state('borrador');
                });
                return stream;
            }).catch(function() {
                said.textContent = str.voicenomic || '';
            });
        });
        byId('ayv-borrador-oir').addEventListener('click', function() {
            var button = byId('ayv-borrador-oir');
            if (hearing && hearing.button === button) {
                stopListening();
                return;
            }
            if (!draftFound) {
                return;
            }
            Promise.all(draftFound.pieces.map(function(piece) {
                return piece.arrayBuffer();
            })).then(function(buffers) {
                var parsed = buffers.map(edit.parse).filter(function(piece) {
                    return piece && piece.packets.length;
                });
                var whole = edit.cut(parsed, 0, Infinity, 0);
                hear(button, whole.blob, 0, whole.duration, 0);
                return whole;
            }).catch(function() {
                return null;
            });
        });
        byId('ayv-borrador-tirar').addEventListener('click', function(event) {
            if (event.isTrusted && event.currentTarget.getAttribute('data-ayv-armed') !== '1') {
                return;
            }
            stopListening();
            edit.draft.drop(draftKey);
            draftFound = null;
            view.state('listo');
        });
    }
    // Recording again throws away what there is: it asks once more in its place (data-ayv-twice arms it after this).
    again.addEventListener('click', function(event) {
        if (event.isTrusted && again.getAttribute('data-ayv-armed') !== '1') {
            return;
        }
        time.textContent = '';
        // The take and its draft go only when the new one starts, after the count: not if the microphone says no, nor if
        // the count is called off.
        rec.click();
    });
    send.addEventListener('click', function() {
        if (!blob) {
            return;
        }
        send.disabled = true;
        said.textContent = str.voicesending || '';
        var body = new FormData();
        body.append('sesskey', config.sesskey);
        if (config.token) {
            body.append('t', config.token);
            body.append('consent', consent && consent.checked ? '1' : '0');
            document.querySelectorAll('input[name="audience[]"]:checked').forEach(function(box) {
                body.append('audience[]', box.value);
            });
        } else {
            body.append('library', config.library);
            body.append('record', config.record);
        }
        body.append('title', what ? what.value : '');
        var part = chosenPart();
        if (part && Number(part.value) >= 0) {
            body.append('part', part.value);
        }
        if (backdrop() !== null) {
            body.append('backdrop', backdrop());
        }
        // Whole seconds, as players count them.
        body.append('seconds', String(Math.floor(measured / 1000)));
        body.append('forlittle', pressed(little) ? '1' : '0');
        body.append('tellfamily', pressed(little) && pressed(family) ? '1' : '0');
        var signature = document.getElementById('ayv-signature');
        body.append('signature', signature ? signature.value : '');
        var email = document.getElementById('ayv-email');
        if (email && email.value) {
            body.append('email', email.value);
        }
        body.append('ext', extension(blob.type));
        body.append('voice', blob, 'voz.' + extension(blob.type));
        fetch(config.sendurl, {method: 'POST', credentials: 'same-origin', body: body}).then(function(response) {
            if (!response.ok) {
                throw new Error(response.status);
            }
            return response.json();
        }).then(function(answer) {
            if (!answer || !answer.id) {
                throw new Error('no id');
            }
            blob = null;
            pieces = [];
            if (edit) {
                edit.draft.drop(draftKey);
            }
            said.textContent = str.voicesent || '';
            window.setTimeout(function() {
                window.location.reload();
            }, 1200);
            return answer;
        }).catch(function() {
            said.textContent = str.voicefailed || '';
            send.disabled = false;
        });
    });
})();
