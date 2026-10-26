/* Ayvurenda: walking along the shelves. Each spine is a link to its record; with this script it comes out of the shelf
   onto a lectern under its row, turning to show its cover. The arrow keys go along the shelf (one stop for the Tab key:
   a roving tabindex), Enter takes a book out and Escape puts it back. A bookend closes each row. Works from Chrome 93. */
(function () {
    'use strict';

    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function slots(shelf) {
        return Array.prototype.slice.call(shelf.querySelectorAll('.ayv-spine-slot'));
    }

    function spines(shelf) {
        return Array.prototype.slice.call(shelf.querySelectorAll('a.ayv-spine'));
    }

    // Marks the last book of each row, for its bookend, and tells the rows apart.
    function markRows(shelf) {
        var list = slots(shelf);
        list.forEach(function (slot, i) {
            var next = list[i + 1];
            slot.classList.toggle('ayv-row-end', !next || next.offsetTop !== slot.offsetTop);
        });
    }

    function rowEnd(slot) {
        var list = slots(slot.parentNode);
        var i = list.indexOf(slot);
        while (i + 1 < list.length && list[i + 1].offsetTop === slot.offsetTop) {
            i++;
        }
        return list[i];
    }

    function roving(shelf, current) {
        spines(shelf).forEach(function (spine) {
            spine.tabIndex = spine === current ? 0 : -1;
        });
    }

    function close(shelf, back) {
        var open = shelf.querySelector('.ayv-lectern');
        if (!open) {
            return;
        }
        var slot = shelf.querySelector('.ayv-spine-open');
        open.parentNode.removeChild(open);
        if (slot) {
            slot.classList.remove('ayv-spine-open');
            if (back) {
                slot.querySelector('a.ayv-spine').focus();
            }
        }
    }

    function text(node, value) {
        node.textContent = value || '';
        node.hidden = !value;
    }

    function open(shelf, spine) {
        var template = document.getElementById('ayv-lectern');
        if (!template) {
            return;
        }
        var slot = spine.parentNode;
        var was = slot.classList.contains('ayv-spine-open');
        close(shelf, false);
        if (was) {
            spine.focus();
            return;
        }
        var lectern = template.content.firstElementChild.cloneNode(true);
        var style = window.getComputedStyle(spine);
        var gap = slot.classList.contains('ayv-spine-gap');
        var colour = gap ? '' : style.backgroundColor;
        var book = lectern.querySelector('.ayv-lectern-turn');
        book.style.setProperty('--bg', colour || 'var(--green2)');
        book.style.setProperty('--w', slot.style.getPropertyValue('--w') || '30px');
        book.style.color = gap ? '#fbf6ec' : style.color;
        var cover = lectern.querySelector('.ayv-lectern-cover');
        if (spine.dataset.cover) {
            cover.style.backgroundImage = 'url("' + spine.dataset.cover.replace(/"/g, '%22') + '")';
            cover.parentNode.classList.add('ayv-lectern-photo');
        }
        lectern.querySelector('.ayv-lectern-cover-title').textContent = spine.dataset.title;
        lectern.querySelector('.ayv-lectern-cover-author').textContent = spine.dataset.authors || '';
        lectern.querySelector('.ayv-lectern-title').textContent = spine.dataset.title;
        text(lectern.querySelector('.ayv-lectern-authors'), spine.dataset.authors);
        var state = lectern.querySelector('.ayv-lectern-state');
        state.textContent = spine.dataset.statetext || '';
        state.className = 'ayv-lectern-state ayv-state ayv-state-' + (spine.dataset.state || 'free');
        var place = lectern.querySelector('.ayv-lectern-place');
        text(place, spine.dataset.place);
        place.parentNode.hidden = !spine.dataset.place;
        var label = lectern.querySelector('.ayv-lectern-label');
        (spine.dataset.lines || '').split('|').forEach(function (line) {
            if (line) {
                var span = document.createElement('span');
                span.textContent = line;
                label.appendChild(span);
            }
        });
        var tint = spine.style.getPropertyValue('--place');
        if (tint) {
            label.style.setProperty('--place', tint);
        }
        label.parentNode.parentNode.hidden = !label.childNodes.length;
        lectern.querySelector('.ayv-lectern-record').href = spine.href;
        lectern.querySelector('.ayv-lectern-close').addEventListener('click', function () {
            close(shelf, true);
        });
        if (reduce) {
            book.style.animation = 'none';
        }
        var end = rowEnd(slot);
        end.parentNode.insertBefore(lectern, end.nextSibling);
        slot.classList.add('ayv-spine-open');
        fit(lectern, slot);
        roving(shelf, spine);
        lectern.querySelector('.ayv-lectern-title').focus({preventScroll: true});
        var box = lectern.getBoundingClientRect();
        if (box.bottom > window.innerHeight || box.top < 0) {
            lectern.scrollIntoView({block: 'nearest', behavior: reduce ? 'auto' : 'smooth'});
        }
    }

    // The lectern takes whole rows, so the planks after it still meet their books.
    function fit(lectern, slot) {
        if (window.getComputedStyle(lectern).position === 'fixed') {
            // A sheet over the shelf, on a phone: it takes no row.
            return;
        }
        var row = slot.offsetHeight || 280;
        var inner = lectern.querySelector('.ayv-lectern-in');
        inner.style.height = 'auto';
        var need = inner.offsetHeight + 38;
        inner.style.height = '';
        lectern.style.setProperty('--rows', String(Math.max(1, Math.ceil(need / row))));
    }

    // The spine nearest to x in the row above or below.
    function vertical(shelf, spine, down) {
        var list = spines(shelf);
        var top = spine.parentNode.offsetTop;
        var x = spine.parentNode.offsetLeft;
        var rows = list.filter(function (s) {
            var t = s.parentNode.offsetTop;
            return down ? t > top : t < top;
        });
        if (!rows.length) {
            return null;
        }
        var target = down ? Math.min.apply(null, rows.map(function (s) {
            return s.parentNode.offsetTop;
        })) : Math.max.apply(null, rows.map(function (s) {
            return s.parentNode.offsetTop;
        }));
        var best = null;
        rows.forEach(function (s) {
            if (s.parentNode.offsetTop === target && (!best ||
                Math.abs(s.parentNode.offsetLeft - x) < Math.abs(best.parentNode.offsetLeft - x))) {
                best = s;
            }
        });
        return best;
    }

    function init(shelf) {
        var list = spines(shelf);
        if (!list.length) {
            return;
        }
        var here = shelf.querySelector('.ayv-spine-here a.ayv-spine');
        roving(shelf, here || list[0]);
        markRows(shelf);
        if (here) {
            here.scrollIntoView({block: 'center'});
            here.focus({preventScroll: true});
        }
        shelf.addEventListener('click', function (event) {
            var spine = event.target.closest('a.ayv-spine');
            if (!spine || event.ctrlKey || event.metaKey || event.shiftKey || event.button !== 0) {
                return;
            }
            event.preventDefault();
            open(shelf, spine);
        });
        shelf.addEventListener('keydown', function (event) {
            var spine = event.target.closest('a.ayv-spine');
            if (event.key === 'Escape') {
                close(shelf, true);
                return;
            }
            if (!spine) {
                return;
            }
            var all = spines(shelf);
            var i = all.indexOf(spine);
            var next = null;
            if (event.key === 'ArrowRight') {
                next = all[i + 1];
            } else if (event.key === 'ArrowLeft') {
                next = all[i - 1];
            } else if (event.key === 'ArrowDown') {
                next = vertical(shelf, spine, true);
            } else if (event.key === 'ArrowUp') {
                next = vertical(shelf, spine, false);
            } else if (event.key === 'Home') {
                next = all[0];
            } else if (event.key === 'End') {
                next = all[all.length - 1];
            } else if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                open(shelf, spine);
                return;
            } else {
                return;
            }
            event.preventDefault();
            if (next) {
                roving(shelf, next);
                next.focus();
            }
        });
        var timer = null;
        window.addEventListener('resize', function () {
            clearTimeout(timer);
            timer = setTimeout(function () {
                var lectern = shelf.querySelector('.ayv-lectern');
                var slot = shelf.querySelector('.ayv-spine-open');
                if (lectern) {
                    lectern.parentNode.removeChild(lectern);
                }
                markRows(shelf);
                if (lectern && slot) {
                    var end = rowEnd(slot);
                    end.parentNode.insertBefore(lectern, end.nextSibling);
                    fit(lectern, slot);
                }
            }, 150);
        });
    }

    function start() {
        Array.prototype.forEach.call(document.querySelectorAll('.ayv-bookcase'), init);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
    // Fonts change the width of nothing here, but images of bookends can arrive late: mark rows again once loaded.
    window.addEventListener('load', function () {
        Array.prototype.forEach.call(document.querySelectorAll('.ayv-bookcase'), markRows);
    });
})();
