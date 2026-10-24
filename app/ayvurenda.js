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
 * Ayvurenda: results while typing. Without this script the search still works: the form sends the page.
 *
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';
    var config = window.AYV || {};
    var input = document.getElementById('ayv-q');
    var main = document.getElementById('ayv-main');
    if (!input || !main || !window.fetch || !config.library) {
        return;
    }
    var timer = null;
    var last = input.value.trim();
    var controller = null;

    var show = function(query) {
        var url = config.searchurl + '?library=' + encodeURIComponent(config.library) + '&q=' + encodeURIComponent(query);
        if (/[?&]free=1(&|$)/.test(window.location.search)) {
            url += '&free=1';
        }
        if (controller) {
            controller.abort();
        }
        controller = window.AbortController ? new AbortController() : null;
        fetch(url + '&fragment=1', {credentials: 'same-origin', signal: controller ? controller.signal : undefined})
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.text();
            })
            .then(function(html) {
                main.innerHTML = html;
                if (window.history && window.history.replaceState) {
                    window.history.replaceState(null, '', url);
                }
                return html;
            })
            .catch(function() {
                // A failed or overtaken search leaves the screen as it was; Enter still sends the form.
            });
    };

    input.addEventListener('input', function() {
        window.clearTimeout(timer);
        timer = window.setTimeout(function() {
            var query = input.value.trim();
            if (query === last || query.length < 2) {
                return;
            }
            last = query;
            show(query);
        }, 300);
    });
})();
