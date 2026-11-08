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
 * A story to read at home, as Gaudí drew its reader: the cover, the e-book's pages one at a time (epub.js) and the end;
 * the two big buttons, the arrows, Page Up and Down, the space bar and a finger to turn them, Escape to go back; four
 * sizes of letter and night, both kept in this browser; and the screen that does not go off while reading.
 *
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';
    var config = window.AYV || {};
    var str = config.strings || {};
    var reader = document.getElementById('rd');
    if (!reader || !window.ePub) {
        return;
    }
    var root = document.documentElement;
    var cover = reader.querySelector('[data-page="cover"]');
    var pages = reader.querySelector('[data-page="book"]');
    var end = reader.querySelector('[data-page="end"]');
    var area = reader.querySelector('.rd-area');
    var prev = reader.querySelector('.rd-prev');
    var next = reader.querySelector('.rd-next');
    var nextLabel = reader.querySelector('.rd-next-label');
    var count = reader.querySelector('.rd-count');
    var dots = reader.querySelector('.rd-dots');
    var night = reader.querySelector('.rd-night');

    // The real height of the window, for phones whose 100vh includes the address bar.
    var height = function() {
        root.style.setProperty('--vh', (window.innerHeight / 100) + 'px');
    };
    height();
    // Turning the phone changes the room, and the size of the letters with it.
    window.addEventListener('resize', function() {
        height();
        if (rendition) {
            dress();
        }
    });

    var keep = function(key, value) {
        try {
            if (value === undefined) {
                return window.localStorage.getItem(key);
            }
            window.localStorage.setItem(key, value);
        } catch (e) {
            return null;
        }
        return null;
    };

    // The letters: four sizes, 28 and 34 px to begin with on a phone and a big screen (Gaudí).
    // On a short screen (a phone lying down) a little less, so a page still holds some lines (Gaudí).
    var sizes = function() {
        var query = function(media) {
            return window.matchMedia && window.matchMedia(media).matches;
        };
        if (query('(max-height: 520px)')) {
            return [18, 22, 26, 30];
        }
        if (query('(max-height: 700px)')) {
            return [21, 24, 28, 33];
        }
        return query('(min-width: 760px) and (min-aspect-ratio: 5/4)') ? [28, 34, 40, 46] : [24, 28, 34, 38];
    };
    var size = Math.min(3, Math.max(0, parseInt(keep('ayv-tale-size') || '1', 10) || 1));
    // Night: on if the system is dark, and as it was chosen after that.
    var dark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    var isNight = keep('ayv-tale-night') === null ? dark : keep('ayv-tale-night') === '1';

    var rendition = null;
    // epub.js measures where it draws: the book is first drawn when its section is shown, never while hidden.
    var started = false;
    var book = null;
    var at = 'cover';
    var spine = 0;
    var index = 0;
    var atStart = true;
    var atEnd = false;
    // A picture book laid out page by page, or text that flows: they say where one is differently (Gaudí).
    var fixed = false;
    var spot = null;
    var share = null;
    var progress = reader.querySelector('.rd-progress');
    var bar = progress ? progress.querySelector('i') : null;
    var sheet = reader.querySelector('.rd-sheet');

    var colours = function() {
        return isNight ? {ink: '#f1e2c4', page: '#17130e'} : {ink: '#1e2b2a', page: '#fbf6ec'};
    };
    var dress = function() {
        reader.classList.toggle('is-night', isNight);
        night.setAttribute('aria-pressed', isNight ? 'true' : 'false');
        reader.className = reader.className.replace(/\bsize-\d\b/, '') + ' size-' + size;
        reader.querySelectorAll('[data-size]').forEach(function(button) {
            var step = parseInt(button.getAttribute('data-size'), 10);
            button.disabled = (step < 0 && size === 0) || (step > 0 && size === 3);
        });
        if (rendition) {
            var c = colours();
            rendition.themes.override('color', c.ink, true);
            rendition.themes.override('background', 'transparent', true);
            rendition.themes.fontSize(sizes()[size] + 'px');
        }
    };

    var shown = function(which) {
        at = which;
        cover.hidden = which !== 'cover';
        pages.hidden = which !== 'book';
        end.hidden = which !== 'end';
        prev.disabled = which === 'cover';
        next.disabled = which === 'end';
        nextLabel.textContent = which === 'cover' ? (str.start || '') : '';
        next.setAttribute('aria-label', which === 'cover' ? (str.start || '') : (str.next || ''));
        where();
    };

    // Where one is: the dots (one for the cover, one for each part of the book, one for the end) and «Page 2 of 4».
    var where = function() {
        var total = spine + 2;
        var here = at === 'cover' ? 0 : (at === 'end' ? total - 1 : index + 1);
        if (dots.children.length !== total && total <= 24) {
            dots.textContent = '';
            for (var i = 0; i < total; i++) {
                dots.appendChild(document.createElement('i'));
            }
        }
        Array.prototype.forEach.call(dots.children, function(dot, i) {
            dot.classList.toggle('on', i === here);
        });
        // Text that flows has no pages of its own: a thin line of how far through the whole book, not dots.
        dots.hidden = total > 24 || (!fixed && at === 'book');
        if (progress) {
            progress.hidden = fixed || at !== 'book' || share === null;
            if (bar && share !== null) {
                bar.style.width = Math.round(share * 100) + '%';
            }
        }
        if (at === 'cover') {
            count.textContent = str.cover || '';
        } else if (at === 'end') {
            count.textContent = str.end || '';
        } else if (fixed || !spot) {
            count.textContent = (str.page || '').replace('{at}', String(index + 1)).replace('{of}', String(spine || 1));
        } else {
            // «One · 2 of 4»: the chapter, and the screen inside it.
            count.textContent = (spot.chapter ? str.spot || '' : str.screen || '').replace('{chapter}', spot.chapter)
                .replace('{page}', String(spot.page)).replace('{total}', String(spot.total));
        }
    };
    // The title of the chapter one is in, from the book's table of contents.
    var chapter = function(href) {
        var label = '';
        var file = String(href || '').split('#')[0];
        var look = function(items) {
            (items || []).forEach(function(item) {
                if (!label && String(item.href || '').split('#')[0].split('/').pop() === file.split('/').pop()) {
                    label = String(item.label || '').trim();
                }
                if (!label && item.subitems) {
                    look(item.subitems);
                }
            });
        };
        look(book && book.navigation ? book.navigation.toc : []);
        return label;
    };

    var fail = function() {
        reader.querySelector('.rd-loading').hidden = true;
        reader.querySelector('.rd-failed').hidden = false;
    };

    // The book is fetched while the cover shows, from this site only; it is laid out when its page is first shown,
    // because epub.js measures where it draws.
    var draw = function() {
        if (!book || at !== 'book') {
            return;
        }
        if (rendition) {
            if (!started) {
                started = true;
                rendition.display();
            } else {
                rendition.resize();
            }
            return;
        }
        started = true;
        rendition = book.renderTo(area, {width: '100%', height: '100%', spread: 'auto', minSpreadWidth: 900});
        rendition.themes.default({
            'body': {'font-family': '"Ayvurenda Andika", Andika, system-ui, sans-serif !important',
                'line-height': '1.42 !important'},
            // The text flows from one screen to the next: a paragraph may split, with two lines at least on each side (Gaudí).
            'p': {'margin': '0 0 .6em !important', 'break-inside': 'auto !important', 'orphans': '2 !important',
                'widows': '2 !important'},
            'h1, h2, h3': {'margin-top': '0 !important', 'line-height': '1.2 !important'},
            'img': {'max-width': '100% !important', 'height': 'auto !important'},
            'a': {'color': 'inherit !important'}
        });
        rendition.hooks.content.register(function(contents) {
            if (config.pagecss) {
                contents.addStylesheet(config.pagecss);
            }
            if (window.matchMedia && window.matchMedia('(max-height: 520px)').matches) {
                // epub.js sets 20 px above and below each page, not important, again at each layout: a rule of
                // the sheet wins, and 6 px leave the lines their room.
                // The theme's rules come later and are as important: these say html first, so they win.
                contents.addStylesheetRules({'html body h1, html body h2': {'font-size': '1.5em !important',
                    'margin': '0 0 .4em !important'},
                    'html body': {'padding-top': '6px !important', 'padding-bottom': '6px !important',
                        'line-height': '1.32 !important'},
                    'html body p': {'margin': '0 0 .4em !important'}});
            }
            contents.document.addEventListener('keydown', keys);
            var startx = null;
            contents.document.addEventListener('touchstart', function(event) {
                startx = event.changedTouches[0].clientX;
            }, {passive: true});
            contents.document.addEventListener('touchend', function(event) {
                if (startx === null) {
                    return;
                }
                var moved = event.changedTouches[0].clientX - startx;
                startx = null;
                if (Math.abs(moved) > 50) {
                    turn(moved < 0 ? 1 : -1);
                }
            }, {passive: true});
        });
        rendition.on('relocated', function(location) {
            index = location && location.start ? location.start.index : 0;
            atStart = !!location.atStart;
            atEnd = !!location.atEnd;
            var screen = location && location.start ? location.start.displayed : null;
            spot = screen ? {chapter: chapter(location.start.href), page: screen.page, total: screen.total} : null;
            if (!fixed && book.locations.length() && location.start) {
                share = book.locations.percentageFromCfi(location.start.cfi) || 0;
            }
            where();
        });
        // Two pages side by side: the spine's shadow between them, as on the cover lying down.
        rendition.on('layout', function(layout) {
            if (sheet) {
                // Two columns drawn, not two allowed: on a narrow screen epub.js keeps one.
                sheet.classList.toggle('is-spread', !!(layout && layout.divisor > 1));
            }
        });
        rendition.on('rendered', function() {
            reader.querySelector('.rd-loading').hidden = true;
            area.querySelectorAll('iframe:not([title])').forEach(function(frame) {
                frame.title = reader.querySelector('.rd-title').textContent;
            });
        });
        dress();
        rendition.display();
    };
    var open = function() {
        fetch(config.file, {credentials: 'same-origin'}).then(function(response) {
            if (!response.ok) {
                throw new Error(String(response.status));
            }
            return response.arrayBuffer();
        }).then(function(data) {
            book = window.ePub(data);
            book.opened.catch(fail);
            book.loaded.spine.then(function(items) {
                spine = (items && items.length) || book.spine.length || 0;
                where();
                return true;
            }).catch(function() {
                return false;
            });
            book.loaded.metadata.then(function(metadata) {
                fixed = !!(metadata && metadata.layout === 'pre-paginated');
                where();
                if (fixed) {
                    return false;
                }
                var key = 'ayv-tale-places-' + config.file;
                var kept = keep(key);
                if (kept) {
                    book.locations.load(kept);
                    return true;
                }
                return book.ready.then(function() {
                    return book.locations.generate(1200);
                }).then(function() {
                    keep(key, book.locations.save());
                    return true;
                });
            }).catch(function() {
                return false;
            });
            draw();
            return true;
        }).catch(fail);
    };

    // One step forward or back: the cover leads to the book, the book's end to «The end».
    var turn = function(step) {
        if (at === 'cover') {
            if (step > 0) {
                shown('book');
                draw();
            }
            return;
        }
        if (at === 'end') {
            if (step < 0) {
                shown('book');
                draw();
            }
            return;
        }
        if (!rendition) {
            if (step < 0) {
                shown('cover');
            }
            return;
        }
        if (step > 0) {
            if (atEnd) {
                shown('end');
            } else {
                rendition.next();
            }
        } else if (atStart) {
            shown('cover');
        } else {
            rendition.prev();
        }
    };
    var keys = function(event) {
        if (event.key === 'ArrowRight' || event.key === 'PageDown' || (event.key === ' ' && at !== 'end')) {
            event.preventDefault();
            turn(1);
        } else if (event.key === 'ArrowLeft' || event.key === 'PageUp') {
            event.preventDefault();
            turn(-1);
        } else if (event.key === 'Escape' && config.back) {
            window.location.href = config.back;
        }
    };
    document.addEventListener('keydown', function(event) {
        if (event.target && /^(INPUT|TEXTAREA|SELECT)$/.test(event.target.tagName)) {
            return;
        }
        keys(event);
    });
    prev.addEventListener('click', function() {
        turn(-1);
    });
    next.addEventListener('click', function() {
        turn(1);
    });
    // A finger on the cover and on the end turns too.
    var sx = null;
    reader.querySelector('.rd-book').addEventListener('pointerdown', function(event) {
        sx = event.clientX;
    });
    reader.querySelector('.rd-book').addEventListener('pointerup', function(event) {
        if (sx !== null && Math.abs(event.clientX - sx) > 50 && at !== 'book') {
            turn(event.clientX < sx ? 1 : -1);
        }
        sx = null;
    });
    reader.querySelectorAll('[data-size]').forEach(function(button) {
        button.addEventListener('click', function() {
            size = Math.min(3, Math.max(0, size + parseInt(button.getAttribute('data-size'), 10)));
            keep('ayv-tale-size', String(size));
            dress();
        });
    });
    night.addEventListener('click', function() {
        isNight = !isNight;
        keep('ayv-tale-night', isNight ? '1' : '0');
        dress();
    });
    // From the start again: the book opens on its first page when the cover is turned.
    reader.querySelector('.rd-restart').addEventListener('click', function() {
        started = false;
        shown('cover');
    });

    // The screen does not go off while reading, where the browser can keep it on.
    var lock = null;
    var awake = function() {
        if (!('wakeLock' in navigator) || document.visibilityState !== 'visible' || lock) {
            return;
        }
        navigator.wakeLock.request('screen').then(function(held) {
            lock = held;
            held.addEventListener('release', function() {
                lock = null;
            });
            return true;
        }).catch(function() {
            return false;
        });
    };
    awake();
    document.addEventListener('visibilitychange', awake);

    dress();
    shown('cover');
    open();
})();
