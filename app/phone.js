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
 * Ayvurenda: the phone as a reader. The camera stays open; each code it reads goes to the desk or the adding screen
 * the same person has open on a computer, and is listed here.
 *
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';
    var config = window.AYV || {};
    var str = config.strings || {};
    var open = document.getElementById('ayv-phone-open');
    var log = document.getElementById('ayv-phone-log');
    if (!open || !log || !window.AYVCamera || !window.fetch) {
        return;
    }
    if (!window.AYVCamera.available()) {
        open.disabled = true;
        log.appendChild(document.createElement('li')).textContent = str.cameranotsecure;
        return;
    }
    var send = function(code) {
        var li = document.createElement('li');
        li.className = 'ayv-line';
        li.textContent = code;
        log.insertBefore(li, log.firstChild);
        var body = new URLSearchParams();
        body.set('sesskey', config.sesskey);
        body.set('action', 'push');
        body.set('code', code);
        fetch(config.relayurl, {method: 'POST', credentials: 'same-origin', body: body}).then(function(response) {
            return response.json();
        }).then(function(answer) {
            if (!answer || answer.error) {
                throw new Error('relay');
            }
            li.textContent = String(str.phonesent).replace('{$a}', code);
            return answer;
        }).catch(function() {
            li.textContent = String(str.phonefailed).replace('{$a}', code);
            li.className = 'ayv-line ayv-sound-no';
        });
    };
    open.addEventListener('click', function() {
        window.AYVCamera.open({onCode: send, strings: str, zxing: config.zxing, keep: true});
    });
})();
