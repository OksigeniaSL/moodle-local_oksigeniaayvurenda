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
 * Ayvurenda: suggestions while filling a box. Words typed in the field (not a code) bring the free copies whose book
 * has them; a tap puts one in the box. A code read by the scanner goes on as before, with the Enter it brings.
 *
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';
    var config = window.AYV || {};
    var input = document.getElementById('ayv-code');
    var list = document.getElementById('ayv-suggest');
    var form = input ? input.form : null;
    if (!input || !list || !form || !window.fetch) {
        return;
    }
    var timer = null;
    var asked = '';

    var el = function(tag, cls, text) {
        var node = document.createElement(tag);
        if (cls) {
            node.className = cls;
        }
        if (text) {
            node.textContent = text;
        }
        return node;
    };

    var show = function(suggestions) {
        list.textContent = '';
        suggestions.forEach(function(s) {
            var li = el('li', 'ayv-holds-row');
            li.appendChild(el('span', 'ayv-holds-title', s.title));
            li.appendChild(el('span', 'ayv-holds-who', s.authors + (s.callnumber ? ' · ' + s.callnumber : '')));
            var add = el('button', 'ayv-button ayv-button-quiet', (config.strings || {}).lotadd);
            add.type = 'button';
            add.addEventListener('click', function() {
                input.value = s.barcode;
                form.submit();
            });
            li.appendChild(add);
            list.appendChild(li);
        });
    };

    var ask = function() {
        var text = input.value.trim();
        // A code (digits, perhaps with a control letter) is for the scanner, not for suggestions.
        if (text.length < 3 || !/\p{L}{2,}/u.test(text) || /^\d+[A-Za-z]?$/.test(text)) {
            show([]);
            return;
        }
        if (text === asked) {
            return;
        }
        asked = text;
        var params = new URLSearchParams({sesskey: config.sesskey, library: config.library, lot: config.lot, q: text});
        fetch(config.suggesturl + '?' + params.toString(), {credentials: 'same-origin'}).then(function(response) {
            return response.json();
        }).then(function(answer) {
            if (input.value.trim() === text) {
                show(answer.suggestions || []);
            }
            return answer;
        }).catch(function() {
            return null;
        });
    };

    input.addEventListener('input', function() {
        clearTimeout(timer);
        timer = setTimeout(ask, 250);
    });
})();
