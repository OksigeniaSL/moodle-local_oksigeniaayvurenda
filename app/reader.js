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
 * Ayvurenda: reading in the browser. An EPUB opens with epub.js, a page at a time, with the letter size and bookmarks;
 * a recording or a video goes on from where it was left. Where the reader is goes to the server now and then, so that
 * another device finds it there too.
 *
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';
    var config = window.AYV || {};
    var str = config.strings || {};
    var marks = Array.isArray(config.bookmarks) ? config.bookmarks : [];
    var box = document.getElementById('ayv-marks');
    var timer = null;

    var stored = 'ayv-read-' + config.item;
    if (config.local) {
        try {
            var saved = JSON.parse(window.localStorage.getItem(stored) || '{}');
            config.position = saved.position || null;
            marks = Array.isArray(saved.bookmarks) ? saved.bookmarks : [];
        } catch (e) {
            marks = [];
        }
    }
    var send = function(params) {
        if (config.local) {
            try {
                var place = JSON.parse(window.localStorage.getItem(stored) || '{}');
                Object.keys(params).forEach(function(key) {
                    place[key] = key === 'bookmarks' ? JSON.parse(params[key]) : params[key];
                });
                window.localStorage.setItem(stored, JSON.stringify(place));
            } catch (e) {
                return Promise.resolve(null);
            }
            return Promise.resolve(null);
        }
        var body = new URLSearchParams();
        body.set('sesskey', config.sesskey);
        body.set('item', config.item);
        Object.keys(params).forEach(function(key) {
            body.set(key, params[key]);
        });
        return fetch(config.ajaxurl, {method: 'POST', credentials: 'same-origin', body: body}).catch(function() {
            return null;
        });
    };
    var keep = function(position) {
        clearTimeout(timer);
        timer = setTimeout(function() {
            send({position: position});
        }, 1500);
    };

    var showMarks = function(go) {
        if (!box) {
            return;
        }
        var list = box.querySelector('ul');
        list.textContent = '';
        marks.forEach(function(mark, index) {
            var li = document.createElement('li');
            li.className = 'ayv-holds-row';
            var open = document.createElement('button');
            open.type = 'button';
            open.className = 'ayv-button ayv-button-quiet';
            open.textContent = mark.label;
            open.addEventListener('click', function() {
                go(mark.place);
            });
            var drop = document.createElement('button');
            drop.type = 'button';
            drop.className = 'ayv-line-button ayv-line-quiet';
            drop.textContent = '×';
            drop.setAttribute('aria-label', mark.label);
            drop.addEventListener('click', function() {
                marks.splice(index, 1);
                send({bookmarks: JSON.stringify(marks)});
                showMarks(go);
            });
            li.appendChild(open);
            li.appendChild(drop);
            list.appendChild(li);
        });
        box.hidden = !marks.length;
    };

    // A recording or a video: from where it was left.
    var media = document.getElementById('ayv-media');
    if (media) {
        media.addEventListener('loadedmetadata', function() {
            var at = parseFloat(config.position || '0');
            if (at > 0 && at < media.duration - 5) {
                media.currentTime = at;
            }
        });
        media.addEventListener('timeupdate', function() {
            keep(String(Math.floor(media.currentTime)));
        });
        media.addEventListener('pause', function() {
            send({position: String(Math.floor(media.currentTime))});
        });
        return;
    }

    // An EPUB, with epub.js, as a page of paper in the library's letters and colours.
    var area = document.getElementById('ayv-epub');
    if (!area || !window.ePub) {
        return;
    }
    var size = 100;
    var where = document.getElementById('ayv-where');
    var progress = document.getElementById('ayv-progress');
    var on = function(id, handler) {
        var element = document.getElementById(id);
        if (element) {
            element.addEventListener('click', handler);
        }
    };
    var colours = window.getComputedStyle(area);
    fetch(area.getAttribute('data-url'), {credentials: 'same-origin'}).then(function(response) {
        return response.arrayBuffer();
    }).then(function(data) {
        area.textContent = '';
        var book = window.ePub(data);
        var rendition = book.renderTo(area, {width: '100%', height: '100%',
            spread: area.getAttribute('data-spread') === 'auto' ? 'auto' : 'none', minSpreadWidth: 760});
        var go = function(place) {
            rendition.display(place);
        };
        rendition.themes.default({
            'body': {
                'font-family': '"Ayvurenda Andika", Andika, Georgia, serif !important',
                'line-height': '1.65 !important',
                'color': (colours.color || '#1d2a27') + ' !important',
                'background': 'transparent !important'
            },
            'p': {'margin': '0 0 .9em !important'},
            'h1, h2, h3': {'line-height': '1.25 !important'},
            'a': {'color': 'inherit !important'}
        });
        var keys = function(event) {
            if (event.key === 'ArrowRight') {
                rendition.next();
            } else if (event.key === 'ArrowLeft') {
                rendition.prev();
            }
        };
        rendition.hooks.content.register(function(contents) {
            if (config.pagecss) {
                contents.addStylesheet(config.pagecss);
            }
            // A finger turns the page, and so do the arrows while the page has the focus.
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
                    if (moved < 0) {
                        rendition.next();
                    } else {
                        rendition.prev();
                    }
                }
            }, {passive: true});
            contents.document.addEventListener('keydown', keys);
        });
        rendition.display(config.position || undefined);
        // The pages epub.js draws go in frames, made again when the window changes: each with the book's title, for
        // screen readers, from the moment it appears.
        var titled = function() {
            area.querySelectorAll('iframe:not([title])').forEach(function(frame) {
                frame.title = area.getAttribute('data-title') || '';
            });
        };
        if (window.MutationObserver) {
            new MutationObserver(titled).observe(area, {childList: true, subtree: true});
        }
        rendition.on('rendered', titled);
        var showWhere = function(location) {
            if (where && location && location.start && location.start.displayed) {
                where.textContent = (str.readpage || '').replace('{$a->page}', location.start.displayed.page)
                    .replace('{$a->total}', location.start.displayed.total);
            }
            if (progress && location && location.start && book.locations.length()) {
                progress.value = Math.round((location.start.percentage || 0) * 100);
                progress.hidden = false;
            }
        };
        rendition.on('relocated', function(location) {
            keep(location.start.cfi);
            showWhere(location);
        });
        // How far through the whole book: worked out once the book is open, without holding up the first page.
        book.ready.then(function() {
            return book.locations.generate(1200);
        }).then(function() {
            showWhere(rendition.currentLocation());
            return true;
        }).catch(function() {
            return false;
        });
        on('ayv-prev', function() {
            rendition.prev();
        });
        on('ayv-next', function() {
            rendition.next();
        });
        on('ayv-smaller', function() {
            size = Math.max(70, size - 10);
            rendition.themes.fontSize(size + '%');
        });
        on('ayv-bigger', function() {
            size = Math.min(200, size + 10);
            rendition.themes.fontSize(size + '%');
        });
        on('ayv-mark', function() {
            var location = rendition.currentLocation();
            if (!location || !location.start) {
                return;
            }
            marks.push({place: location.start.cfi, label: (str.readmarklabel || '').replace('{$a}', marks.length + 1)});
            send({bookmarks: JSON.stringify(marks)});
            showMarks(go);
        });
        // Full screen: the book alone, or with the recorder beside it. Where the browser cannot (an iPhone), the same
        // part of the page covers everything else.
        var full = document.getElementById('ayv-full');
        var stage = area.closest('.ayv-voice-grid') || area.closest('.ayv-read-stage');
        if (full && stage) {
            var ask = stage.requestFullscreen || stage.webkitRequestFullscreen;
            var leave = document.exitFullscreen || document.webkitExitFullscreen;
            var current = function() {
                return document.fullscreenElement || document.webkitFullscreenElement || null;
            };
            var show = function(on) {
                stage.classList.toggle('is-reading', on);
                document.documentElement.classList.toggle('ayv-reading-on', on);
                full.setAttribute('aria-pressed', on ? 'true' : 'false');
                window.setTimeout(function() {
                    rendition.resize();
                }, 200);
            };
            full.addEventListener('click', function() {
                var on = !stage.classList.contains('is-reading');
                show(on);
                if (on && ask) {
                    var asked = ask.call(stage);
                    if (asked && asked.catch) {
                        asked.catch(function() {
                            return null;
                        });
                    }
                } else if (!on && current() && leave) {
                    leave.call(document);
                }
            });
            var changed = function() {
                if (!current() && stage.classList.contains('is-reading')) {
                    show(false);
                }
            };
            document.addEventListener('fullscreenchange', changed);
            document.addEventListener('webkitfullscreenchange', changed);
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape' && stage.classList.contains('is-reading') && !current()) {
                    show(false);
                }
            });
        }
        document.addEventListener('keydown', function(event) {
            // Not while writing in a field of the page, such as what was read.
            if (!event.target.closest || !event.target.closest('input, textarea, select')) {
                keys(event);
            }
        });
        showMarks(go);
        return book;
    }).catch(function() {
        area.textContent = '';
    });
})();
