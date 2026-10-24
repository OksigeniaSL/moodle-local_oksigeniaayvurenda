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
 * right, say what was read, send. The recording waits for approval; nobody else hears it until then.
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
    var preview = document.getElementById('ayv-preview');
    var time = document.getElementById('ayv-time');
    var said = document.getElementById('ayv-voice-said');
    var what = document.getElementById('ayv-what');
    if (!rec || !window.fetch) {
        return;
    }
    var MAX = 20 * 60;
    var recorder = null;
    var chunks = [];
    var blob = null;
    var started = 0;
    var ticker = null;
    var stream = null;

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
        var seconds = Math.floor((Date.now() - started) / 1000);
        time.textContent = (str.voicetime || '').replace('{$a}', Math.floor(seconds / 60) + ':' + String(seconds % 60).padStart(2, '0'));
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
                blob = new Blob(chunks, {type: recorder.mimeType || mime || 'audio/webm'});
                preview.src = URL.createObjectURL(blob);
                preview.hidden = false;
                send.disabled = false;
                again.hidden = false;
                stream.getTracks().forEach(function(track) {
                    track.stop();
                });
            });
            recorder.start(1000);
            started = Date.now();
            ticker = setInterval(clock, 500);
            rec.hidden = true;
            stop.hidden = false;
            send.disabled = true;
            said.textContent = '';
            return recorder;
        }).catch(function() {
            said.textContent = str.voicenomic || '';
        });
    });
    stop.addEventListener('click', function() {
        clearInterval(ticker);
        if (recorder && recorder.state !== 'inactive') {
            recorder.stop();
        }
        stop.hidden = true;
    });
    again.addEventListener('click', function() {
        blob = null;
        preview.hidden = true;
        again.hidden = true;
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
        body.append('library', config.library);
        body.append('record', config.record);
        body.append('title', what ? what.value : '');
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
