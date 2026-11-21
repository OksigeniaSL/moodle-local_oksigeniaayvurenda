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
    // «¿Para quién es?» (Jujol): for the little ones, and then, only then, the family too; the sentence says what happens.
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
                sample.volume = 0.15;
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
        bar.querySelector('button').addEventListener('click', function() {
            if (!stop.hidden) {
                stop.click();
            } else if (!rec.hidden && !rec.disabled) {
                rec.click();
            }
        });
    }
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
        if (pausedAt) {
            return;
        }
        var seconds = Math.floor((Date.now() - started) / 1000);
        time.textContent = (str.voicetime || '').replace('{$a}', function() { return Math.floor(seconds / 60) + ':' + String(seconds % 60).padStart(2, '0'); });
        if (seconds >= MAX) {
            stop.click();
        }
    };

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || !window.MediaRecorder) {
        rec.disabled = true;
        said.textContent = str.voicenomic || '';
        return;
    }
    rec.addEventListener('click', function() {
        navigator.mediaDevices.getUserMedia({audio: true}).then(function(s) {
            stream = s;
            var mime = type();
            recorder = mime ? new window.MediaRecorder(stream, {mimeType: mime}) : new window.MediaRecorder(stream);
            chunks = [];
            recorder.addEventListener('dataavailable', function(event) {
                if (event.data && event.data.size) {
                    chunks.push(event.data);
                }
            });
            recorder.addEventListener('stop', function() {
                var made = new Blob(chunks, {type: recorder.mimeType || mime || 'audio/webm'});
                withDuration(made, measured).then(function(fixed) {
                    blob = fixed;
                    preview.src = URL.createObjectURL(blob);
                    // What was read and the signature come once there is something to send.
                    after.hidden = false;
                    send.disabled = false;
                    return fixed;
                }).catch(function() {
                    return null;
                });
                quiet();
                stream.getTracks().forEach(function(track) {
                    track.stop();
                });
            });
            recorder.start(1000);
            meter(stream);
            started = Date.now();
            ticker = setInterval(clock, 500);
            rec.hidden = true;
            stop.hidden = false;
            sayBar('recording');
            hush();
            if (pause && recorder.pause) {
                pause.hidden = false;
            }
            send.disabled = true;
            said.textContent = '';
            return recorder;
        }).catch(function() {
            said.textContent = str.voicenomic || '';
        });
    });
    // A pause between pages, for water or a cough: the recording goes on afterwards, in the same file.
    var word = function(text) {
        pause.querySelector('.ayv-rec-word').textContent = text;
    };
    if (pause) {
        pause.addEventListener('click', function() {
            if (!recorder) {
                return;
            }
            if (recorder.state === 'recording') {
                recorder.pause();
                pausedAt = Date.now();
                pause.setAttribute('aria-pressed', 'true');
                word(pause.getAttribute('data-resume'));
            } else if (recorder.state === 'paused') {
                recorder.resume();
                started += Date.now() - pausedAt;
                pausedAt = 0;
                pause.setAttribute('aria-pressed', 'false');
                word(pause.getAttribute('data-pause'));
            }
        });
    }
    stop.addEventListener('click', function() {
        clearInterval(ticker);
        if (pausedAt) {
            started += Date.now() - pausedAt;
            pausedAt = 0;
        }
        if (pause) {
            pause.hidden = true;
            pause.setAttribute('aria-pressed', 'false');
            word(pause.getAttribute('data-pause'));
        }
        if (recorder && recorder.state !== 'inactive') {
            recorder.stop();
        }
        stop.hidden = true;
        // What was recorded, and how long it lasts.
        sayBar('done');
        measured = Date.now() - started;
        var seconds = Math.floor(measured / 1000);
        time.textContent = (str.voicelength || '').replace('{$a}', function() { return Math.floor(seconds / 60) + ':' +
            String(seconds % 60).padStart(2, '0'); });
    });
    again.addEventListener('click', function() {
        sayBar('ready');
        blob = null;
        after.hidden = true;
        send.disabled = true;
        time.textContent = '';
        rec.hidden = false;
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
