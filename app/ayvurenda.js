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
    // The shelves slide without a scrollbar: two arrows, shown when there is more that way.
    document.querySelectorAll('.ayv-wood').forEach(function(wood) {
        var list = wood.querySelector('.ayv-cards');
        var prev = wood.querySelector('.ayv-shelf-prev');
        var next = wood.querySelector('.ayv-shelf-next');
        if (!list || !prev || !next) {
            return;
        }
        var still = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var update = function() {
            prev.hidden = list.scrollLeft <= 4;
            next.hidden = list.scrollLeft + list.clientWidth >= list.scrollWidth - 4;
        };
        var go = function(way) {
            list.scrollBy({left: way * list.clientWidth * 0.8, behavior: still ? 'auto' : 'smooth'});
        };
        prev.addEventListener('click', function() {
            go(-1);
        });
        next.addEventListener('click', function() {
            go(1);
        });
        list.addEventListener('scroll', update, {passive: true});
        window.addEventListener('resize', update);
        update();
    });
    var input = document.getElementById('ayv-q');
    // On the home the search is in the page: the results come under it, and the box stays where it is.
    var main = document.getElementById('ayv-live') || document.getElementById('ayv-main');
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

    var clear = document.querySelector('.ayv-search-clear');
    input.addEventListener('input', function() {
        window.clearTimeout(timer);
        if (clear) {
            clear.hidden = input.value.trim() === '';
        }
        timer = window.setTimeout(function() {
            var query = input.value.trim();
            // An empty box means no search: back to the library's page.
            if (query === '' && last !== '') {
                window.location.assign(clear ? clear.href : config.homeurl);
                return;
            }
            if (query === last || query.length < 2) {
                return;
            }
            last = query;
            show(query);
        }, 300);
    });
})();
