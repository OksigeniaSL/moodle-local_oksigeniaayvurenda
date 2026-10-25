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
    // The credits, from the footer, in a small window; it closes with its button, Escape or outside it.
    var credits = document.getElementById('ayv-credits');
    if (credits && credits.showModal) {
        document.querySelectorAll('[data-ayv-credits]').forEach(function(button) {
            button.addEventListener('click', function() {
                credits.showModal();
            });
        });
        credits.addEventListener('click', function(event) {
            if (event.target === credits) {
                credits.close();
            }
        });
    }
    // Choosing a file: our button and words, in the library's language, over the browser's control, which still does
    // the work underneath (it takes the click and the keyboard, and says what it is to a screen reader).
    if (config.filechoose) {
        document.querySelectorAll('input[type="file"]').forEach(function(input) {
            var box = document.createElement('span');
            var button = document.createElement('span');
            var name = document.createElement('span');
            box.className = 'ayv-file';
            button.className = 'ayv-file-button';
            name.className = 'ayv-file-name';
            button.setAttribute('aria-hidden', 'true');
            name.setAttribute('aria-hidden', 'true');
            button.textContent = config.filechoose;
            var show = function() {
                var names = Array.prototype.map.call(input.files || [], function(file) {
                    return file.name;
                });
                name.textContent = names.length ? names.join(', ') : config.filenone;
            };
            input.parentNode.insertBefore(box, input);
            box.appendChild(input);
            box.appendChild(button);
            box.appendChild(name);
            input.addEventListener('change', show);
            show();
        });
    }
    // Dates as three dropdowns (day, month by its name, year) in the library's language: the browser's date field writes
    // the date its own way (mm/dd/yyyy in an English browser). The field stays, hidden, and carries the value.
    var dateParts = function(lang) {
        try {
            var name = new Intl.DateTimeFormat(lang, {month: 'long', timeZone: 'UTC'});
            var months = [];
            for (var m = 0; m < 12; m++) {
                months.push(name.format(new Date(Date.UTC(2026, m, 15))));
            }
            var order = new Intl.DateTimeFormat(lang, {day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC'})
                .formatToParts(new Date(Date.UTC(2026, 8, 15))).map(function(part) {
                    return part.type;
                }).filter(function(type) {
                    return type === 'day' || type === 'month' || type === 'year';
                });
            return order.length === 3 ? {months: months, order: order} : null;
        } catch (e) {
            return null;
        }
    };
    var parts = config.dateday ? dateParts(document.documentElement.lang || 'en') : null;
    if (parts) {
        var pad = function(n) {
            return (n < 10 ? '0' : '') + n;
        };
        document.querySelectorAll('input[type="date"]').forEach(function(input) {
            var now = new Date().getFullYear();
            var value = /^(\d{4})-(\d{2})-(\d{2})$/.exec(input.value);
            var low = parseInt((input.min || '').slice(0, 4), 10) || now - 10;
            var high = parseInt((input.max || '').slice(0, 4), 10) || now + 2;
            if (value) {
                low = Math.min(low, +value[1]);
                high = Math.max(high, +value[1]);
            }
            var label = input.closest('label');
            var said = label ? label.textContent.trim() : '';
            var box = document.createElement('span');
            box.className = 'ayv-date';
            var lists = {};
            var options = {
                day: function(add) {
                    for (var d = 1; d <= 31; d++) {
                        add(String(d), String(d));
                    }
                },
                month: function(add) {
                    parts.months.forEach(function(month, i) {
                        add(String(i + 1), month);
                    });
                },
                year: function(add) {
                    for (var y = low; y <= high; y++) {
                        add(String(y), String(y));
                    }
                }
            };
            var sync = function() {
                var y = lists.year.value, m = lists.month.value, d = lists.day.value;
                if (y && m && d) {
                    var last = new Date(Date.UTC(+y, +m, 0)).getUTCDate();
                    if (+d > last) {
                        d = String(last);
                        lists.day.value = d;
                    }
                    input.value = y + '-' + pad(+m) + '-' + pad(+d);
                } else {
                    input.value = '';
                }
                input.dispatchEvent(new Event('change', {bubbles: true}));
            };
            parts.order.forEach(function(part) {
                var list = document.createElement('select');
                list.setAttribute('aria-label', (said ? said + ' · ' : '') + config['date' + part]);
                list.required = input.required;
                var add = function(v, text) {
                    var option = document.createElement('option');
                    option.value = v;
                    option.textContent = text;
                    list.appendChild(option);
                };
                if (!input.required) {
                    add('', '—');
                }
                options[part](add);
                if (value) {
                    list.value = String(+value[{year: 1, month: 2, day: 3}[part]]);
                }
                list.addEventListener('change', sync);
                lists[part] = list;
                box.appendChild(list);
            });
            input.parentNode.insertBefore(box, input);
            input.type = 'hidden';
            box.appendChild(input);
        });
    }
    // Sheets: a form over the page, opened by its button. They close with their cross, Escape or a click outside the
    // sheet (not in its margin, which is still the dialog: what was typed is not lost by a near miss).
    document.querySelectorAll('[data-ayv-open]').forEach(function(button) {
        var sheet = document.getElementById(button.getAttribute('data-ayv-open'));
        if (!sheet || !sheet.showModal) {
            return;
        }
        button.addEventListener('click', function() {
            sheet.showModal();
        });
    });
    document.querySelectorAll('dialog.ayv-modal').forEach(function(sheet) {
        sheet.addEventListener('click', function(event) {
            var box = sheet.getBoundingClientRect();
            if (event.target === sheet && (event.clientX < box.left || event.clientX > box.right ||
                    event.clientY < box.top || event.clientY > box.bottom)) {
                sheet.close();
            }
        });
    });
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
