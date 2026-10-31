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
 * Ayvurenda: the library one visits. The painted room fits the screen (on a phone it fills the height and one looks
 * around sideways), by day or by night as the page is; the places light up under the pointer and when their word below
 * is reached with the keyboard; a door one goes through draws one in and the next room opens around; the layers move a
 * little with the pointer, as things near and far do; the characters say something when touched; and the room has its
 * sound, off until one turns it on. With «reduce motion», nothing moves.
 *
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';
    var visit = document.querySelector('.ayv-visit');
    if (!visit) {
        return;
    }
    // A few bright notes for the games' good moments, only with the visit's sound on.
    // What a character says, one clip at a time, never over another; the room's sound goes down meanwhile.
    var voice = null;
    var duck = function(down) {
        if (ambience) {
            ambience.volume = (down ? 0.3 : 1) * level;
        }
    };
    window.AYVVisit = {
        say: function(url) {
            if (!on || !url || !window.Audio) {
                return;
            }
            if (voice) {
                voice.pause();
            }
            voice = new window.Audio(url);
            voice.volume = level;
            duck(true);
            voice.addEventListener('ended', function() {
                duck(false);
            });
            voice.play().catch(function() {
                duck(false);
            });
        },
        chime: function() {
            var Context = window.AudioContext || window.webkitAudioContext;
            if (!on || !Context) {
                return;
            }
            var audio = new Context();
            [[784, 0], [988, 0.1], [1319, 0.2]].forEach(function(note) {
                var osc = audio.createOscillator(), gain = audio.createGain(), at = audio.currentTime + note[1];
                osc.frequency.value = note[0];
                gain.gain.setValueAtTime(0.0001, at);
                gain.gain.exponentialRampToValueAtTime(0.12 * level, at + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, at + 0.35);
                osc.connect(gain);
                gain.connect(audio.destination);
                osc.start(at);
                osc.stop(at + 0.4);
            });
        },
    };
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var fine = window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches;
    var W = parseFloat(visit.getAttribute('data-width')) || 2400;
    var H = parseFloat(visit.getAttribute('data-height')) || 1350;
    var focus = parseFloat(visit.getAttribute('data-focus')) || W / 2;
    var frame = visit.querySelector('.ayv-visit-frame');
    var view = visit.querySelector('.ayv-visit-view');
    var room = visit.querySelector('.ayv-visit-room');
    var stage = visit.querySelector('.ayv-visit-stage');
    var tip = visit.querySelector('.ayv-visit-tip');
    var left = visit.querySelector('.ayv-visit-pan-left');
    var right = visit.querySelector('.ayv-visit-pan-right');
    var store = function(kind, key, value) {
        try {
            var s = window[kind];
            if (value === undefined) {
                return s.getItem(key);
            }
            if (value === null) {
                s.removeItem(key);
            } else {
                s.setItem(key, value);
            }
        } catch (e) {
            // Without storage, the visit goes on without remembering.
        }
        return null;
    };

    // By day, or by night when the page is dark: the library with its lamps lit. A site without Moodle's colour modes
    // says nothing, and then the painting follows the visitor's system.
    var theme = document.documentElement.getAttribute('data-bs-theme');
    var night = theme ? theme === 'dark'
        : !!(window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
    visit.classList.toggle('ayv-visit-night', night);
    Array.prototype.forEach.call(visit.querySelectorAll('.ayv-visit-layer'), function(img) {
        img.src = img.getAttribute(night ? 'data-night' : 'data-day');
    });
    frame.hidden = false;

    // The painting fits the width; on a narrow screen it fills a height and slides sideways.
    var k = 1;
    var narrow = false;
    var arrows = function() {
        var more = view.scrollWidth - view.clientWidth;
        left.hidden = !narrow || view.scrollLeft < 8;
        right.hidden = !narrow || view.scrollLeft > more - 8;
    };
    var fit = function() {
        var width = frame.clientWidth;
        narrow = width / W * H < 320;
        var height = narrow ? Math.max(320, Math.min(560, window.innerHeight * 0.62)) : width / W * H;
        k = height / H;
        room.style.width = Math.round(W * k) + 'px';
        room.style.height = Math.round(height) + 'px';
        view.style.height = Math.round(height) + 'px';
        stage.style.transform = 'scale(' + k + ')';
        arrows();
    };
    fit();
    if (narrow) {
        view.scrollLeft = Math.max(0, focus * k - view.clientWidth / 2);
    }
    if (window.ResizeObserver) {
        new window.ResizeObserver(fit).observe(frame);
    }
    view.addEventListener('scroll', arrows, {passive: true});

    // The words on the painted boards fit them: a busy day or a long name gets smaller letters, never out of the board.
    var settle = function() {
        Array.prototype.forEach.call(stage.querySelectorAll('.ayv-visit-chalk, .ayv-visit-board'), function(surface) {
            var size = parseFloat(surface.getAttribute('data-size') || surface.style.fontSize);
            surface.setAttribute('data-size', size);
            surface.style.fontSize = size + 'px';
            var least = size * 0.55;
            var board = surface.classList.contains('ayv-visit-board');
            // A row of the board has its height; the letters' strokes may go a little past their line.
            var over = function() {
                return surface.scrollHeight > surface.clientHeight + 1 || board && Array.prototype.some.call(
                    surface.querySelectorAll('li'),
                    function(li) {
                        return li.scrollHeight > li.clientHeight + size * 0.3 || li.scrollWidth > li.clientWidth + 1;
                    }
                );
            };
            while (over() && size > least) {
                size *= 0.93;
                surface.style.fontSize = size.toFixed(1) + 'px';
            }
        });
    };
    settle();
    if (document.fonts && document.fonts.ready) {
        // With the house's letters loaded, again from the start: they are not as wide as the ones before.
        document.fonts.ready.then(settle);
    }
    var pan = function(way) {
        view.scrollBy({left: way * view.clientWidth * 0.7, behavior: reduce ? 'auto' : 'smooth'});
    };
    left.addEventListener('click', function() {
        pan(-1);
    });
    right.addEventListener('click', function() {
        pan(1);
    });

    // Near things move more than far ones as the pointer goes over the room.
    var moving = Array.prototype.map.call(stage.querySelectorAll('[data-depth]'), function(el) {
        return {el: el, depth: parseFloat(el.getAttribute('data-depth')) || 0, base: el.style.transform || ''};
    }).filter(function(m) {
        return m.depth > 0;
    });
    var at = {x: 0, y: 0}, to = {x: 0, y: 0}, loop = 0;
    var place = function() {
        loop = 0;
        at.x += (to.x - at.x) * 0.12;
        at.y += (to.y - at.y) * 0.12;
        moving.forEach(function(m) {
            m.el.style.transform = 'translate(' + (-at.x * m.depth * 16).toFixed(2) + 'px, ' +
                (-at.y * m.depth * 7).toFixed(2) + 'px) ' + m.base;
        });
        if (Math.abs(to.x - at.x) > 0.002 || Math.abs(to.y - at.y) > 0.002) {
            loop = window.requestAnimationFrame(place);
        }
    };
    if (fine && !reduce && moving.length) {
        view.addEventListener('pointermove', function(e) {
            var box = view.getBoundingClientRect();
            to.x = (e.clientX - box.left) / box.width * 2 - 1;
            to.y = (e.clientY - box.top) / box.height * 2 - 1;
            if (!loop) {
                loop = window.requestAnimationFrame(place);
            }
        });
        view.addEventListener('pointerleave', function() {
            to.x = to.y = 0;
            if (!loop) {
                loop = window.requestAnimationFrame(place);
            }
        });
    }

    // A place lights up, with its name over it, under the pointer or when its word is reached; on a phone, the room
    // slides to show it.
    var zone = function(id) {
        return stage.querySelector('.ayv-visit-zones a[data-zone="' + id + '"]');
    };
    var lit = null;
    var light = function(link, slide) {
        if (lit) {
            lit.classList.remove('ayv-visit-lit');
            visit.querySelector('.ayv-visit-place[data-zone="' + lit.getAttribute('data-zone') + '"]')
                .classList.remove('ayv-visit-lit');
            lit = null;
        }
        tip.hidden = true;
        if (!link) {
            return;
        }
        var id = link.getAttribute('data-zone');
        var shape = zone(id), word = visit.querySelector('.ayv-visit-place[data-zone="' + id + '"]');
        if (!shape || !word) {
            return;
        }
        lit = shape;
        shape.classList.add('ayv-visit-lit');
        word.classList.add('ayv-visit-lit');
        var p = shape.getAttribute('data-tip').split(' ').map(parseFloat);
        if (slide && narrow) {
            view.scrollTo({left: Math.max(0, p[0] * k - view.clientWidth / 2), behavior: reduce ? 'auto' : 'smooth'});
        }
        tip.textContent = word.textContent;
        tip.hidden = false;
        var x = p[0] * k - (slide && narrow ? Math.max(0, p[0] * k - view.clientWidth / 2) : view.scrollLeft);
        tip.style.left = Math.max(tip.offsetWidth / 2 + 4, Math.min(view.clientWidth - tip.offsetWidth / 2 - 4, x)) + 'px';
        tip.style.top = Math.max(tip.offsetHeight + 4, p[1] * k - 6) + 'px';
    };
    // A soft touch of a book's cover when a place lights up under the pointer, with the sound on.
    var touch = null;
    var tap = function() {
        if (!on || !visit.getAttribute('data-touch') || !window.Audio) {
            return;
        }
        touch = touch || new window.Audio(visit.getAttribute('data-touch'));
        touch.volume = 0.5 * level;
        touch.currentTime = 0;
        touch.play().catch(function() {
            // Silence, then.
        });
    };
    Array.prototype.forEach.call(visit.querySelectorAll('.ayv-visit-zones a'), function(a) {
        a.addEventListener('pointerenter', function() {
            light(a, false);
            tap();
        });
        a.addEventListener('pointerleave', function() {
            light(null);
        });
    });
    Array.prototype.forEach.call(visit.querySelectorAll('.ayv-visit-place'), function(a) {
        a.addEventListener('focus', function() {
            light(a, true);
        });
        a.addEventListener('blur', function() {
            light(null);
        });
        a.addEventListener('pointerenter', function() {
            light(a, true);
        });
        a.addEventListener('pointerleave', function() {
            light(null);
        });
    });

    // The sound of the room, off until one turns it on; the choice is remembered for the next rooms.
    var soundbutton = visit.querySelector('.ayv-visit-sound');
    var ambience = null;
    // The sound is off until one turns it on; a reader of the flower hears it unless they turned it off.
    var chosen = store('localStorage', 'ayv-visit-sound');
    var on = chosen === 'on' || (chosen !== 'off' && visit.getAttribute('data-young') === '1');
    // How loud, one setting for the whole device, set once (on a class's board, say): low to start with, since twenty
    // devices may sound at once and a voice that comes out loud frightens the little ones.
    var level = parseFloat(store('localStorage', 'ayv-volume'));
    level = isNaN(level) ? 0.25 : Math.max(0, Math.min(1, level));
    var volume = visit.querySelector('.ayv-visit-volume');
    var play = function() {
        if (!ambience) {
            ambience = new window.Audio(visit.getAttribute('data-sound'));
            ambience.loop = true;
            ambience.volume = level;
        }
        return ambience.play();
    };
    if (soundbutton && window.Audio) {
        soundbutton.hidden = false;
        soundbutton.setAttribute('aria-pressed', on ? 'true' : 'false');
        soundbutton.addEventListener('click', function() {
            // On, but the browser kept it quiet until a touch: this one starts it rather than turning it off.
            if (on && (!ambience || ambience.paused)) {
                play().catch(function() {
                    // The browser would not; the button stays as chosen.
                });
                return;
            }
            on = !on;
            store('localStorage', 'ayv-visit-sound', on ? 'on' : 'off');
            soundbutton.setAttribute('aria-pressed', on ? 'true' : 'false');
            if (on) {
                play().catch(function() {
                    // The browser would not; the button stays as chosen.
                });
            } else if (ambience) {
                ambience.pause();
            }
        });
        if (volume) {
            volume.hidden = false;
            volume.querySelector('input').value = Math.round(level * 100);
            volume.querySelector('input').addEventListener('input', function(event) {
                level = Math.max(0, Math.min(1, event.target.value / 100));
                store('localStorage', 'ayv-volume', String(level));
                if (ambience) {
                    ambience.volume = level;
                }
                if (voice) {
                    voice.volume = level;
                }
            });
        }
        if (on) {
            // A browser may wait for a first touch before it lets a page sound.
            play().catch(function() {
                var first = function(event) {
                    document.removeEventListener('pointerdown', first, true);
                    document.removeEventListener('keydown', first, true);
                    // A touch of the sound button is left to its own click.
                    if (on && !soundbutton.contains(event.target)) {
                        play().catch(function() {
                            // Still not: silence.
                        });
                    }
                };
                document.addEventListener('pointerdown', first, true);
                document.addEventListener('keydown', first, true);
            });
        }
    }

    // What can be touched is not plain in a painting: the first time in a room this session, every place glows a moment.
    var key = 'ayv-visit-seen-' + (visit.getAttribute('data-room') || '');
    var zonesvg = stage.querySelector('.ayv-visit-zones');
    if (!reduce && zonesvg && store('sessionStorage', key) !== '1') {
        store('sessionStorage', key, '1');
        window.setTimeout(function() {
            zonesvg.classList.add('ayv-visit-hint');
            window.setTimeout(function() {
                zonesvg.classList.remove('ayv-visit-hint');
            }, 1400);
        }, 700);
    }

    // Through a door: the room draws one in towards it, and the next opens around.
    if (!reduce && store('sessionStorage', 'ayv-visit-in') === '1') {
        store('sessionStorage', 'ayv-visit-in', null);
        room.classList.add('ayv-visit-arriving');
        window.requestAnimationFrame(function() {
            window.requestAnimationFrame(function() {
                room.classList.remove('ayv-visit-arriving');
            });
        });
    }
    var enter = function(e) {
        var link = e.currentTarget;
        if (reduce || e.ctrlKey || e.metaKey || e.shiftKey || e.button > 0) {
            return;
        }
        e.preventDefault();
        var place = link.hasAttribute('data-zone') ? zone(link.getAttribute('data-zone')) : null;
        if (place) {
            var p = place.getAttribute('data-tip').split(' ').map(parseFloat);
            room.style.transformOrigin = (p[0] * k) + 'px ' + (p[2] * k) + 'px';
        } else {
            // A row of the board: towards the row itself.
            var r = room.getBoundingClientRect(), b = link.getBoundingClientRect();
            room.style.transformOrigin = (b.left + b.width / 2 - r.left) + 'px ' + (b.top + b.height / 2 - r.top) + 'px';
        }
        light(null);
        room.classList.add('ayv-visit-leaving');
        if (on && visit.getAttribute('data-door') && window.Audio) {
            var door = new window.Audio(visit.getAttribute('data-door'));
            door.volume = 0.6 * level;
            door.play().catch(function() {
                // Without the door's sound, through it all the same.
            });
        }
        store('sessionStorage', 'ayv-visit-in', '1');
        window.setTimeout(function() {
            window.location.href = link.getAttribute('href');
        }, 420);
    };
    Array.prototype.forEach.call(visit.querySelectorAll('[data-enters]'), function(a) {
        a.addEventListener('click', enter);
    });
    // Back with the browser's arrow, the room is as it was left, not drawn into the door.
    window.addEventListener('pageshow', function(e) {
        if (e.persisted) {
            room.classList.remove('ayv-visit-leaving');
        }
    });

    // The characters: touched, they say their words beside them (the memory asks what one is looking for).
    var open = null;
    var shut = function(back) {
        if (!open) {
            return;
        }
        open.say.hidden = true;
        open.button.setAttribute('aria-expanded', 'false');
        if (back) {
            open.button.focus();
        }
        open = null;
    };
    Array.prototype.forEach.call(visit.querySelectorAll('.ayv-visit-figure'), function(button) {
        var say = document.getElementById(button.getAttribute('aria-controls'));
        if (!say) {
            return;
        }
        button.addEventListener('click', function() {
            var again = open && open.say === say;
            shut(false);
            if (again) {
                return;
            }
            open = {say: say, button: button};
            say.hidden = false;
            window.AYVVisit.say(say.getAttribute('data-voice'));
            button.setAttribute('aria-expanded', 'true');
            var box = frame.getBoundingClientRect(), who = button.getBoundingClientRect();
            var x = who.left - box.left + who.width / 2 - say.offsetWidth / 2;
            say.style.left = Math.max(8, Math.min(box.width - say.offsetWidth - 8, x)) + 'px';
            var y = who.top - box.top + who.height * 0.12 - say.offsetHeight;
            say.style.top = Math.max(8, y) + 'px';
            var field = say.querySelector('input[type="search"]');
            (field || say.querySelector('.ayv-visit-close')).focus();
        });
        say.querySelector('.ayv-visit-close').addEventListener('click', function() {
            shut(true);
        });
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            shut(true);
        }
    });
    document.addEventListener('pointerdown', function(e) {
        if (open && !open.say.contains(e.target) && !open.button.contains(e.target)) {
            shut(false);
        }
    });
})();
