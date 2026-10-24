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

    var send = function(params) {
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

    // An EPUB, with epub.js.
    var area = document.getElementById('ayv-epub');
    if (!area || !window.ePub) {
        return;
    }
    var size = 100;
    var where = document.getElementById('ayv-where');
    fetch(area.getAttribute('data-url'), {credentials: 'same-origin'}).then(function(response) {
        return response.arrayBuffer();
    }).then(function(data) {
        area.textContent = '';
        var book = window.ePub(data);
        var rendition = book.renderTo(area, {width: '100%', height: '100%', spread: 'none'});
        var go = function(place) {
            rendition.display(place);
        };
        rendition.display(config.position || undefined);
        // The pages epub.js draws go in frames: each with the book's title, for screen readers.
        rendition.on('rendered', function() {
            area.querySelectorAll('iframe').forEach(function(frame) {
                frame.title = area.getAttribute('data-title') || '';
            });
        });
        rendition.on('relocated', function(location) {
            keep(location.start.cfi);
            if (where && location.start.displayed) {
                where.textContent = (str.readpage || '').replace('{$a->page}', location.start.displayed.page)
                    .replace('{$a->total}', location.start.displayed.total);
            }
        });
        document.getElementById('ayv-prev').addEventListener('click', function() {
            rendition.prev();
        });
        document.getElementById('ayv-next').addEventListener('click', function() {
            rendition.next();
        });
        document.getElementById('ayv-smaller').addEventListener('click', function() {
            size = Math.max(70, size - 10);
            rendition.themes.fontSize(size + '%');
        });
        document.getElementById('ayv-bigger').addEventListener('click', function() {
            size = Math.min(200, size + 10);
            rendition.themes.fontSize(size + '%');
        });
        document.getElementById('ayv-mark').addEventListener('click', function() {
            var location = rendition.currentLocation();
            if (!location || !location.start) {
                return;
            }
            marks.push({place: location.start.cfi, label: (str.readmarklabel || '').replace('{$a}', marks.length + 1)});
            send({bookmarks: JSON.stringify(marks)});
            showMarks(go);
        });
        document.addEventListener('keydown', function(event) {
            if (event.key === 'ArrowRight') {
                rendition.next();
            } else if (event.key === 'ArrowLeft') {
                rendition.prev();
            }
        });
        showMarks(go);
        return book;
    }).catch(function() {
        area.textContent = '';
    });
})();
