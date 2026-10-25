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
 * Ayvurenda: the recorder of a review, for whoever would rather say it: up to half a minute, heard before sending, and
 * sent with the form as a file.
 *
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';
    var LIMIT = 30;
    document.querySelectorAll('[data-ayv-speak]').forEach(function(box) {
        var form = box.closest('form');
        var open = form.querySelector('[data-ayv-speak-open]');
        var record = box.querySelector('[data-speak-record]');
        var label = record.querySelector('span');
        var again = box.querySelector('[data-speak-again]');
        var time = box.querySelector('.ayv-speak-time');
        var player = box.querySelector('audio');
        var input = box.querySelector('input[name="voice"]');
        var words = form.querySelector('textarea[name="text"]');
        var recorder = null;
        var timer = null;
        var chunks = [];
        var stop = function() {
            if (recorder && recorder.state === 'recording') {
                recorder.stop();
            }
        };
        var keep = function(blob, type) {
            var ending = type.indexOf('mp4') >= 0 ? 'm4a' : (type.indexOf('ogg') >= 0 ? 'ogg' : 'webm');
            var files = new DataTransfer();
            files.items.add(new File([blob], 'voz.' + ending, {type: type}));
            input.files = files.files;
            player.src = URL.createObjectURL(blob);
            player.hidden = false;
            again.hidden = false;
            // With a voice, the words are no longer needed.
            words.required = false;
        };
        open.addEventListener('click', function() {
            box.hidden = false;
            open.hidden = true;
        });
        record.addEventListener('click', function() {
            if (recorder && recorder.state === 'recording') {
                stop();
                return;
            }
            if (!navigator.mediaDevices || !window.MediaRecorder || !window.DataTransfer) {
                time.textContent = box.getAttribute('data-nomic');
                return;
            }
            navigator.mediaDevices.getUserMedia({audio: true}).then(function(stream) {
                var started = Date.now();
                chunks = [];
                recorder = new MediaRecorder(stream);
                recorder.addEventListener('dataavailable', function(event) {
                    if (event.data.size) {
                        chunks.push(event.data);
                    }
                });
                recorder.addEventListener('stop', function() {
                    clearInterval(timer);
                    stream.getTracks().forEach(function(track) {
                        track.stop();
                    });
                    record.classList.remove('ayv-speak-on');
                    label.textContent = box.getAttribute('data-record');
                    time.textContent = '';
                    if (chunks.length) {
                        var type = recorder.mimeType || 'audio/webm';
                        keep(new Blob(chunks, {type: type}), type);
                    }
                });
                recorder.start();
                record.classList.add('ayv-speak-on');
                label.textContent = box.getAttribute('data-stop');
                timer = setInterval(function() {
                    var seconds = Math.floor((Date.now() - started) / 1000);
                    time.textContent = seconds + ' / ' + LIMIT + ' s';
                    if (seconds >= LIMIT) {
                        stop();
                    }
                }, 250);
            }).catch(function() {
                time.textContent = box.getAttribute('data-nomic');
            });
        });
        again.addEventListener('click', function() {
            input.value = '';
            player.hidden = true;
            player.removeAttribute('src');
            again.hidden = true;
        });
    });
})();
