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
    // How do I…?, down on the right: it stays open or folded as it was left (in this browser only), the field takes the
    // cursor when it opens, and Escape folds it.
    var howto = document.querySelector('[data-ayv-howto]');
    if (howto) {
        // Opened by the page as it was left, the cursor stays where it is; opened by someone, it goes to the field.
        var restoring = false;
        try {
            restoring = window.localStorage.getItem('ayv-howto-open') === '1';
            howto.open = restoring;
        } catch (e) {
            // Without storage it starts folded.
        }
        howto.addEventListener('toggle', function() {
            if (restoring) {
                restoring = false;
                return;
            }
            try {
                window.localStorage.setItem('ayv-howto-open', howto.open ? '1' : '0');
            } catch (e) {
                // Nothing kept: it still opens and folds.
            }
            var field = howto.querySelector('input[type="search"]');
            if (howto.open && field) {
                field.focus();
            }
        });
        howto.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && howto.open) {
                howto.open = false;
                howto.querySelector('summary').focus();
            }
        });
    }
    // Choosing a file: our button and words, in the library's language, over the browser's control, which still does
    // the work underneath (it takes the click and the keyboard, and says what it is to a screen reader).
    if (config.filechoose) {
        document.querySelectorAll('input[type="file"]').forEach(function(input) {
            // A field a script fills by itself (the recorder's voice) stays out of sight.
            if (input.hidden) {
                return;
            }
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
    // A zone to drop a file on: the file field covers all of it, so the browser takes the drop by itself; choosing a
    // file is already the next step, and its button only shows that it is at work.
    document.querySelectorAll('[data-ayv-drop]').forEach(function(zone) {
        var input = zone.querySelector('input[type="file"]');
        var form = zone.closest('form');
        if (!input || !form) {
            return;
        }
        form.classList.add('ayv-imp-auto');
        ['dragenter', 'dragover'].forEach(function(name) {
            zone.addEventListener(name, function() {
                zone.classList.add('ayv-imp-over');
            });
        });
        ['dragleave', 'drop'].forEach(function(name) {
            zone.addEventListener(name, function() {
                zone.classList.remove('ayv-imp-over');
            });
        });
        input.addEventListener('change', function() {
            if (input.files && input.files.length && form.requestSubmit) {
                form.requestSubmit();
            }
        });
    });
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
    // What only matters with one answer of a form: «mode=days,fine» shows while the radio «mode» says one of those.
    document.querySelectorAll('[data-ayv-whens]').forEach(function(form) {
        var refresh = function() {
            form.querySelectorAll('[data-ayv-when]').forEach(function(box) {
                var parts = box.getAttribute('data-ayv-when').split('=');
                var chosen = form.querySelector('input[name="' + parts[0] + '"]:checked');
                box.hidden = !chosen || parts[1].split(',').indexOf(chosen.value) < 0;
            });
        };
        form.addEventListener('change', refresh);
        refresh();
    });
    // Sheets: a form over the page, opened by its button. They close with their cross, Escape or a click outside the
    // sheet (not in its margin, which is still the dialog: what was typed is not lost by a near miss).
    // The search's filters, folded on a phone unless one is on: the books come first.
    if (window.matchMedia && window.matchMedia('(max-width: 40rem)').matches) {
        document.querySelectorAll('details[data-ayv-fold=""]').forEach(function(fold) {
            fold.open = false;
        });
    }
    // On a phone, the search waits behind a magnifier and opens over the header's bar; the cross or Esc closes it.
    var top = document.querySelector('.ayv-top');
    var lens = document.querySelector('.ayv-search-toggle');
    if (top && lens) {
        var field = document.getElementById('ayv-q');
        var shut = function() {
            top.classList.remove('ayv-searching');
            lens.setAttribute('aria-expanded', 'false');
            lens.focus();
        };
        lens.addEventListener('click', function() {
            // Without a search in the header (the home has its own), the magnifier takes the focus to the page's field.
            if (!top.querySelector('.ayv-search')) {
                if (field) {
                    field.scrollIntoView({block: 'center'});
                    field.focus();
                }
                return;
            }
            top.classList.add('ayv-searching');
            lens.setAttribute('aria-expanded', 'true');
            if (field) {
                field.focus();
            }
        });
        var cross = document.querySelector('.ayv-search-close');
        if (cross) {
            cross.addEventListener('click', shut);
        }
        if (field) {
            field.addEventListener('keydown', function(event) {
                if (event.key === 'Escape' && window.getComputedStyle(lens).display !== 'none') {
                    shut();
                }
            });
        }
    }
    // The phone's «More» sheet closes with a tap outside it, on the dimmed page.
    var phonemore = document.getElementById('ayv-phonemore');
    if (phonemore) {
        phonemore.addEventListener('click', function(event) {
            if (event.target === phonemore && phonemore.close) {
                phonemore.close();
            }
        });
    }
    // A field that leaves only the lines with its words, and opens the groups that hold them (late books by a name).
    var plain = function(text) {
        return String(text).toLowerCase().normalize ? String(text).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            : String(text).toLowerCase();
    };
    document.querySelectorAll('input[data-ayv-filter]').forEach(function(field) {
        var rows = document.querySelectorAll(field.getAttribute('data-ayv-filter'));
        var groups = document.querySelectorAll(field.getAttribute('data-ayv-filter-groups') || '.ayv-none');
        var opened = Array.prototype.map.call(groups, function(group) {
            return group.open;
        });
        field.addEventListener('input', function() {
            var words = plain(field.value).split(/\s+/).filter(Boolean);
            Array.prototype.forEach.call(rows, function(row) {
                var text = plain(row.textContent);
                row.hidden = words.length > 0 && !words.every(function(word) {
                    return text.indexOf(word) >= 0;
                });
            });
            Array.prototype.forEach.call(groups, function(group, i) {
                var shown = group.querySelectorAll(field.getAttribute('data-ayv-filter') + ':not([hidden])').length;
                group.hidden = words.length > 0 && !shown;
                group.open = words.length > 0 ? shown > 0 : opened[i];
            });
        });
    });
    // A form that keeps itself when the field is left: the page is sent in the background and says «Saved» a moment.
    document.querySelectorAll('form[data-ayv-autosave]').forEach(function(form) {
        var field = form.querySelector('textarea, input[type="text"]');
        var said = form.querySelector('.ayv-autosaved');
        var button = form.querySelector('.ayv-autosave-button');
        if (!field || !window.fetch || !window.FormData) {
            return;
        }
        if (button) {
            button.hidden = true;
        }
        var kept = field.value;
        field.addEventListener('blur', function() {
            if (field.value === kept) {
                return;
            }
            var sent = field.value;
            window.fetch(form.action, {method: 'POST', body: new window.FormData(form), credentials: 'same-origin'})
                .then(function(response) {
                    if (!response.ok) {
                        throw new Error(String(response.status));
                    }
                    kept = sent;
                    if (said) {
                        said.textContent = form.getAttribute('data-ayv-autosave');
                        window.setTimeout(function() {
                            said.textContent = '';
                        }, 3000);
                    }
                    return null;
                }).catch(function() {
                    // Not kept: the button comes back, so nothing is lost.
                    if (button) {
                        button.hidden = false;
                    }
                });
        });
    });
    document.querySelectorAll('[data-ayv-open]').forEach(function(button) {
        var sheet = document.getElementById(button.getAttribute('data-ayv-open'));
        if (!sheet) {
            return;
        }
        button.addEventListener('click', function() {
            // A browser without modal windows (an old board, an old iPad) shows it in the page, with the focus inside.
            if (sheet.showModal) {
                sheet.showModal();
            } else {
                sheet.setAttribute('open', '');
                var first = sheet.querySelector('input, select, textarea, button');
                if (first) {
                    first.focus();
                }
            }
        });
    });
    // A sheet named in the address (from «More»: the centre's settings) opens with the page.
    if (window.location.hash.length > 1) {
        var named = document.getElementById(decodeURIComponent(window.location.hash.slice(1)));
        if (named && named.tagName === 'DIALOG' && named.showModal && !named.open) {
            named.showModal();
        }
    }
    // A button that closes its sheet, where the sheet sits inside a form and cannot have a form of its own.
    document.querySelectorAll('[data-ayv-close]').forEach(function(button) {
        button.addEventListener('click', function() {
            var sheet = button.closest('dialog');
            if (sheet) {
                sheet.close();
            }
        });
    });
    // Lists to mark (weeding): how many are marked, said where it is asked for; the action waits for one at least, and
    // «mark those of this page» marks only what the page shows.
    document.querySelectorAll('form[data-ayv-marks]').forEach(function(form) {
        form.classList.add('ayv-marks-live');
        var boxes = form.querySelectorAll('input[data-ayv-mark]');
        var all = form.querySelector('input[data-ayv-markall]');
        var count = function() {
            var n = 0;
            boxes.forEach(function(box) {
                n += box.checked ? 1 : 0;
            });
            form.querySelectorAll('[data-ayv-marked]').forEach(function(say) {
                say.textContent = n === 0 ? say.getAttribute('data-none') : (n === 1 ? say.getAttribute('data-one')
                    : say.getAttribute('data-many').replace('{n}', function() {
                        return String(n);
                    }));
            });
            form.querySelectorAll('[data-ayv-needs-mark]').forEach(function(button) {
                button.disabled = n === 0;
            });
            form.classList.toggle('ayv-has-marks', n > 0);
            if (all) {
                all.checked = n > 0 && n === boxes.length;
                all.indeterminate = n > 0 && n < boxes.length;
            }
        };
        boxes.forEach(function(box) {
            box.addEventListener('change', count);
        });
        if (all) {
            all.addEventListener('change', function() {
                boxes.forEach(function(box) {
                    box.checked = all.checked;
                });
                count();
            });
        }
        count();
    });
    // A sheet that has something to say once, as the page opens (a key just made, a link just made).
    document.querySelectorAll('dialog.ayv-modal[data-ayv-show]').forEach(function(sheet) {
        if (sheet.showModal) {
            sheet.showModal();
        }
    });
    // «Copy»: the text of a field to the clipboard, and the button says so for a moment.
    document.querySelectorAll('[data-ayv-copy]').forEach(function(button) {
        var field = document.getElementById(button.getAttribute('data-ayv-copy'));
        if (!field) {
            return;
        }
        var label = button.textContent;
        button.addEventListener('click', function() {
            var done = function() {
                button.textContent = button.getAttribute('data-done') || label;
                window.setTimeout(function() {
                    button.textContent = label;
                }, 2000);
            };
            var text = field.value !== undefined ? field.value : field.textContent;
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(done).catch(function() {
                    field.select();
                });
            } else {
                field.select();
                try {
                    document.execCommand('copy');
                    done();
                } catch (e) {
                    // The text stays selected, to copy by hand.
                }
            }
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
    // «Listen»: the voice of a line for a young reader, its clips in turn. A second touch stops it, and only one plays.
    var listening = null;
    var hushListening = function() {
        if (listening) {
            listening.audio.pause();
            listening.button.setAttribute('aria-pressed', 'false');
            listening = null;
        }
    };
    document.addEventListener('click', function(event) {
        var button = event.target.closest ? event.target.closest('.ayv-listen') : null;
        if (!button) {
            return;
        }
        var again = listening && listening.button === button;
        hushListening();
        if (again) {
            return;
        }
        var urls = (button.getAttribute('data-ayv-say') || '').split(' ').filter(Boolean);
        var play = function(i) {
            if (i >= urls.length) {
                hushListening();
                return;
            }
            var audio = new Audio(urls[i]);
            // The device's own volume, as the visit and its games set it (low until someone sets it).
            try {
                var level = parseFloat(window.localStorage.getItem('ayv-volume'));
                audio.volume = isNaN(level) ? 0.25 : Math.max(0, Math.min(1, level));
            } catch (e) {
                audio.volume = 0.25;
            }
            listening = {button: button, audio: audio};
            button.setAttribute('aria-pressed', 'true');
            audio.addEventListener('ended', function() {
                if (listening && listening.audio === audio) {
                    play(i + 1);
                }
            });
            audio.play().catch(hushListening);
        };
        play(0);
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
    // A button that prints the page (a sheet shown once), without a script in the page.
    document.addEventListener('click', function(event) {
        var printer = event.target.closest ? event.target.closest('[data-ayv-print]') : null;
        if (printer) {
            event.preventDefault();
            window.print();
        }
    });
    // A form that takes a while (a work brought from far away, a search in another catalogue) shows that it is working:
    // its button waits with the words of what it does, turning, and a line under it says how long it may take. Sent once;
    // back to it from the next page, it is as it was.
    var busy = function(form, on, pressed) {
        var button = pressed || form.querySelector('button[type="submit"], button:not([type])');
        var said = form.querySelector('.ayv-busy-said');
        form.setAttribute('aria-busy', on ? 'true' : 'false');
        if (button) {
            if (on && !button.hasAttribute('data-words')) {
                button.setAttribute('data-words', button.textContent);
            }
            button.textContent = on ? form.getAttribute('data-busy') : (button.getAttribute('data-words') || button.textContent);
            button.classList.toggle('ayv-button-busy', on);
            button.setAttribute('aria-disabled', on ? 'true' : 'false');
        }
        if (form.getAttribute('data-busyhint')) {
            if (!said) {
                said = document.createElement('p');
                said.className = 'ayv-field-hint ayv-busy-said';
                said.setAttribute('role', 'status');
                form.appendChild(said);
            }
            said.textContent = on ? form.getAttribute('data-busyhint') : '';
        }
    };
    // A page waiting for something done elsewhere looks again by itself.
    var reloads = document.querySelector('[data-ayv-reload]');
    if (reloads) {
        window.setTimeout(function() {
            window.location.reload();
        }, Number(reloads.getAttribute('data-ayv-reload')) * 1000);
    }

    // What saves itself when it is left, with nothing else to press.
    Array.prototype.forEach.call(document.querySelectorAll('[data-ayv-submit]'), function(input) {
        input.addEventListener('change', function() {
            if (input.value !== '' && input.form.requestSubmit) {
                input.form.requestSubmit();
            }
        });
    });
    Array.prototype.forEach.call(document.querySelectorAll('form[data-busy]'), function(form) {
        form.addEventListener('submit', function(event) {
            if (form.getAttribute('aria-busy') === 'true') {
                event.preventDefault();
                return;
            }
            // Leaving a form that imports, or looking at it again, is not what takes a while.
            if (!event.defaultPrevented && !(event.submitter && event.submitter.hasAttribute('data-nobusy'))) {
                busy(form, true, event.submitter);
            }
        });
    });
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            Array.prototype.forEach.call(document.querySelectorAll('form[data-busy][aria-busy="true"]'), function(form) {
                busy(form, false);
            });
        }
    });

    // On the home the search is in the page: the results come under it, and the box stays where it is.
    // The class while it is typed: a word («biología») offers the numbers that fit, from the centre's own classes and from
    // what the library gave to books on that subject; a number offers those that start with it, with their names. Arrows
    // and Enter choose, Escape closes; what is typed stays if nothing is chosen.
    Array.prototype.forEach.call(document.querySelectorAll('input[data-ayv-classes]'), function(field) {
        if (!window.fetch) {
            return;
        }
        var holder = field.parentNode;
        holder.classList.add('ayv-classes-holder');
        var list = document.createElement('ul');
        var said = document.createElement('span');
        var id = 'ayv-classes-' + Math.random().toString(36).slice(2, 8);
        list.className = 'ayv-classlist';
        list.id = id;
        list.setAttribute('role', 'listbox');
        list.hidden = true;
        said.className = 'ayv-class-said';
        said.setAttribute('aria-live', 'polite');
        holder.appendChild(list);
        holder.appendChild(said);
        field.setAttribute('role', 'combobox');
        field.setAttribute('aria-autocomplete', 'list');
        field.setAttribute('aria-expanded', 'false');
        field.setAttribute('aria-controls', id);
        var items = [];
        var active = -1;
        var timer = null;
        var asked = 0;
        var names = {};
        var close = function() {
            list.hidden = true;
            field.setAttribute('aria-expanded', 'false');
            field.removeAttribute('aria-activedescendant');
            active = -1;
        };
        var name = function() {
            var code = field.value.trim();
            said.textContent = names[code] ? names[code] : '';
        };
        var mark = function(index) {
            active = index;
            Array.prototype.forEach.call(list.children, function(li, i) {
                li.setAttribute('aria-selected', i === index ? 'true' : 'false');
            });
            if (index >= 0) {
                field.setAttribute('aria-activedescendant', id + '-' + index);
                list.children[index].scrollIntoView({block: 'nearest'});
            }
        };
        var choose = function(index) {
            var item = items[index];
            if (!item) {
                return;
            }
            field.value = item.code;
            names[item.code] = item.label;
            close();
            name();
            field.dispatchEvent(new Event('change', {bubbles: true}));
            field.focus();
        };
        var show = function(found) {
            items = found;
            list.textContent = '';
            found.forEach(function(item, i) {
                names[item.code] = item.label;
                var li = document.createElement('li');
                li.id = id + '-' + i;
                li.setAttribute('role', 'option');
                li.setAttribute('aria-selected', 'false');
                var code = document.createElement('b');
                code.className = 'ayv-classlist-code';
                code.textContent = item.code;
                var label = document.createElement('span');
                label.className = 'ayv-classlist-label';
                label.textContent = item.label;
                li.appendChild(code);
                li.appendChild(label);
                if (item.note) {
                    var note = document.createElement('span');
                    note.className = 'ayv-classlist-note';
                    note.textContent = item.note;
                    li.appendChild(note);
                }
                // A press, not a click: the field would lose the focus first and close the list.
                li.addEventListener('mousedown', function(event) {
                    event.preventDefault();
                    choose(i);
                });
                list.appendChild(li);
            });
            list.style.left = field.offsetLeft + 'px';
            list.style.top = (field.offsetTop + field.offsetHeight + 4) + 'px';
            list.hidden = !found.length;
            field.setAttribute('aria-expanded', found.length ? 'true' : 'false');
            mark(-1);
            name();
        };
        // Open: the list for what is typed. Closed: only the name of the number that is there.
        var ask = function(open) {
            var typed = field.value.trim();
            if (typed.length < (/^\d/.test(typed) ? 1 : 3) || (!open && !/^\d/.test(typed))) {
                close();
                name();
                return;
            }
            var mine = ++asked;
            var body = new URLSearchParams({action: 'classes', q: typed});
            fetch(field.getAttribute('data-ayv-classes'), {method: 'POST', body: body, credentials: 'same-origin'})
                .then(function(response) {
                    return response.json();
                })
                .then(function(answer) {
                    if (mine !== asked) {
                        return answer;
                    }
                    if (open && document.activeElement === field) {
                        show(answer.classes || []);
                    } else {
                        (answer.classes || []).forEach(function(item) {
                            names[item.code] = item.label;
                        });
                        name();
                    }
                    return answer;
                })
                .catch(function() {
                    close();
                });
        };
        field.addEventListener('input', function() {
            window.clearTimeout(timer);
            timer = window.setTimeout(function() {
                ask(true);
            }, 220);
        });
        field.addEventListener('keydown', function(event) {
            if (list.hidden) {
                return;
            }
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                var step = event.key === 'ArrowDown' ? 1 : -1;
                mark((active + step + items.length) % items.length);
            } else if (event.key === 'Enter' && active >= 0) {
                event.preventDefault();
                choose(active);
            } else if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                close();
            }
        });
        field.addEventListener('blur', function() {
            window.setTimeout(close, 120);
            name();
        });
        // Filled by the page (a proposal, the book before): its name too, when it is known.
        field.addEventListener('ayv:filled', function() {
            close();
            ask(false);
        });
        if (field.value.trim() !== '') {
            ask(false);
        }
    });

    // The subjects of a record as chips: what the centre already uses comes up while typing (the most used first, and
    // with a letter wrong too), Enter or a comma adds what is typed, the cross or Backspace takes one away. The field
    // underneath keeps one a line, as it is sent.
    Array.prototype.forEach.call(document.querySelectorAll('textarea[data-ayv-subjects]'), function(area) {
        if (!window.fetch) {
            return;
        }
        var fold = function(text) {
            return text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
        };
        var box = document.createElement('div');
        var chips = document.createElement('ul');
        var holder = document.createElement('span');
        var field = document.createElement('input');
        var list = document.createElement('ul');
        var id = 'ayv-subjects-' + Math.random().toString(36).slice(2, 8);
        box.className = 'ayv-chipbox';
        chips.className = 'ayv-chips';
        holder.className = 'ayv-classes-holder ayv-chipfield';
        field.type = 'text';
        field.className = 'ayv-chipinput';
        field.setAttribute('aria-label', area.getAttribute('data-add'));
        field.placeholder = area.getAttribute('data-add');
        field.setAttribute('role', 'combobox');
        field.setAttribute('aria-autocomplete', 'list');
        field.setAttribute('aria-expanded', 'false');
        field.setAttribute('aria-controls', id);
        list.className = 'ayv-classlist';
        list.id = id;
        list.setAttribute('role', 'listbox');
        list.hidden = true;
        holder.appendChild(field);
        holder.appendChild(list);
        box.appendChild(chips);
        box.appendChild(holder);
        area.hidden = true;
        area.parentNode.insertBefore(box, area.nextSibling);
        var values = area.value.split(/\r?\n/).map(function(line) {
            return line.trim();
        }).filter(Boolean);
        var items = [];
        var active = -1;
        var timer = null;
        var asked = 0;
        var sync = function() {
            area.value = values.join('\n');
        };
        var paint = function() {
            chips.textContent = '';
            values.forEach(function(value, i) {
                var li = document.createElement('li');
                li.className = 'ayv-chipitem';
                li.appendChild(document.createTextNode(value));
                var cross = document.createElement('button');
                cross.type = 'button';
                cross.className = 'ayv-chipremove';
                cross.setAttribute('aria-label', area.getAttribute('data-remove').replace('{$a}', function() {
                    return value;
                }));
                cross.textContent = '×';
                cross.addEventListener('click', function() {
                    values.splice(i, 1);
                    sync();
                    paint();
                    field.focus();
                });
                li.appendChild(cross);
                chips.appendChild(li);
            });
        };
        var close = function() {
            list.hidden = true;
            field.setAttribute('aria-expanded', 'false');
            field.removeAttribute('aria-activedescendant');
            active = -1;
        };
        var add = function(text) {
            text = text.trim();
            if (text === '') {
                return;
            }
            // The form the centre already has wins over what was typed («misterio» is «Misterio»).
            items.forEach(function(item) {
                if (fold(item.label) === fold(text)) {
                    text = item.label;
                }
            });
            if (!values.some(function(value) {
                return fold(value) === fold(text);
            })) {
                values.push(text);
            }
            field.value = '';
            sync();
            paint();
            close();
        };
        var mark = function(index) {
            active = index;
            Array.prototype.forEach.call(list.children, function(li, i) {
                li.setAttribute('aria-selected', i === index ? 'true' : 'false');
            });
            if (index >= 0) {
                field.setAttribute('aria-activedescendant', id + '-' + index);
            }
        };
        var show = function(found) {
            items = found.filter(function(item) {
                return !values.some(function(value) {
                    return fold(value) === fold(item.label);
                });
            });
            list.textContent = '';
            items.forEach(function(item, i) {
                var li = document.createElement('li');
                li.id = id + '-' + i;
                li.setAttribute('role', 'option');
                li.setAttribute('aria-selected', 'false');
                var label = document.createElement('span');
                label.className = 'ayv-classlist-label';
                label.textContent = item.label;
                var note = document.createElement('span');
                note.className = 'ayv-classlist-note';
                note.textContent = item.note || '';
                li.appendChild(label);
                li.appendChild(note);
                li.addEventListener('mousedown', function(event) {
                    event.preventDefault();
                    add(item.label);
                });
                list.appendChild(li);
            });
            list.style.left = '0px';
            list.style.top = (field.offsetTop + field.offsetHeight + 4) + 'px';
            list.hidden = !items.length;
            field.setAttribute('aria-expanded', items.length ? 'true' : 'false');
            mark(-1);
        };
        field.addEventListener('input', function() {
            window.clearTimeout(timer);
            if (field.value.indexOf(',') >= 0) {
                field.value.split(',').slice(0, -1).forEach(add);
                return;
            }
            timer = window.setTimeout(function() {
                var typed = field.value.trim();
                if (typed.length < 2) {
                    close();
                    return;
                }
                var mine = ++asked;
                fetch(area.getAttribute('data-ayv-subjects'), {method: 'POST', credentials: 'same-origin',
                    body: new URLSearchParams({action: 'subjects', q: typed})})
                    .then(function(response) {
                        return response.json();
                    })
                    .then(function(answer) {
                        if (mine === asked && document.activeElement === field) {
                            show(answer.subjects || []);
                        }
                        return answer;
                    })
                    .catch(close);
            }, 200);
        });
        field.addEventListener('keydown', function(event) {
            if ((event.key === 'ArrowDown' || event.key === 'ArrowUp') && !list.hidden) {
                event.preventDefault();
                mark((active + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length);
            } else if (event.key === 'Enter') {
                // Enter adds; it never sends the form from here.
                event.preventDefault();
                add(active >= 0 && items[active] ? items[active].label : field.value);
            } else if (event.key === 'Escape' && !list.hidden) {
                event.preventDefault();
                event.stopPropagation();
                close();
            } else if (event.key === 'Backspace' && field.value === '' && values.length) {
                values.pop();
                sync();
                paint();
            }
        });
        field.addEventListener('blur', function() {
            window.setTimeout(close, 120);
        });
        // What was left typed goes too when the form is sent.
        if (area.form) {
            area.form.addEventListener('submit', function() {
                add(field.value);
            });
        }
        paint();
    });

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
                // What was found is said from a region that stays (a live region put in new is seldom announced).
                var status = document.getElementById('ayv-search-status');
                if (!status) {
                    status = document.createElement('p');
                    status.id = 'ayv-search-status';
                    status.className = 'ayv-sr';
                    status.setAttribute('role', 'status');
                    main.parentNode.insertBefore(status, main);
                }
                var heading = main.querySelector('.ayv-results-title');
                status.textContent = heading ? heading.textContent.trim() : '';
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

    // A button that undoes something asks once more in its own place: the first touch says what it will do, a second does it.
    document.addEventListener('click', function(event) {
        var button = event.target.closest ? event.target.closest('[data-ayv-twice]') : null;
        // A guided help presses for the person, who is watching: it does not ask twice.
        if (!button || button.getAttribute('data-ayv-armed') === '1' || !event.isTrusted) {
            return;
        }
        event.preventDefault();
        var before = button.textContent;
        button.setAttribute('data-ayv-armed', '1');
        button.classList.add('ayv-armed');
        button.textContent = button.getAttribute('data-ayv-twice');
        window.setTimeout(function() {
            button.removeAttribute('data-ayv-armed');
            button.classList.remove('ayv-armed');
            button.textContent = before;
        }, 5000);
    });
})();
