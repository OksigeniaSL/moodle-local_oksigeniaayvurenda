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
 * Ayvurenda: the engine of the guided help. The real screen runs in a frame, on the practice library; a pointer moves
 * over it, types like a scanner and taps; each step has its caption (and its voice, when there is one); «Now you»
 * rings a button and waits for the person's tap. With ?auto=1 it taps by itself, for the tests.
 *
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';
    var config = window.AYV || {};
    var frame = document.getElementById('ayv-help-frame');
    var stage = document.getElementById('ayv-help-stage');
    var pointer = document.getElementById('ayv-help-pointer');
    var ring = document.getElementById('ayv-help-ring');
    var caption = document.getElementById('ayv-help-caption');
    var start = document.getElementById('ayv-help-start');
    var done = document.getElementById('ayv-help-done');
    var piece = (window.AYVHelpPieces || {})[config.piece];
    if (!frame || !piece) {
        return;
    }
    var skipping = false;
    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    var sleep = function(ms) {
        return new Promise(function(resolve) {
            setTimeout(resolve, skipping ? 0 : ms);
        });
    };
    var doc = function() {
        return frame.contentDocument;
    };
    var find = function(selector) {
        return typeof selector === 'function' ? selector(doc()) : doc().querySelector(selector);
    };
    var wait = function(selector, timeout) {
        var until = Date.now() + (timeout || 15000);
        return new Promise(function(resolve, reject) {
            var look = function() {
                var element = doc() && find(selector);
                if (element) {
                    resolve(element);
                } else if (Date.now() > until) {
                    reject(new Error('not found: ' + selector));
                } else {
                    setTimeout(look, 100);
                }
            };
            look();
        });
    };
    // Where an element of the frame is, in the stage.
    var place = function(element) {
        var f = frame.getBoundingClientRect();
        var s = stage.getBoundingClientRect();
        var r = element.getBoundingClientRect();
        return {x: f.left - s.left + r.left + r.width / 2, y: f.top - s.top + r.top + r.height / 2,
            left: f.left - s.left + r.left, top: f.top - s.top + r.top, width: r.width, height: r.height};
    };

    var h = {
        say: function(index) {
            var text = config.steps[index] || '';
            caption.textContent = text;
            // Time to read it: about a word every quarter of a second, never less than two seconds.
            return sleep(Math.max(2200, text.split(/\s+/).length * 260));
        },
        point: function(selector) {
            return wait(selector).then(function(element) {
                element.scrollIntoView({block: 'nearest'});
                var at = place(element);
                pointer.style.transform = 'translate(' + Math.round(at.x) + 'px, ' + Math.round(at.y) + 'px)';
                pointer.classList.add('ayv-help-pointer-on');
                return sleep(reduced ? 100 : 700).then(function() {
                    return element;
                });
            });
        },
        tap: function(selector) {
            return h.point(selector).then(function(element) {
                pointer.classList.add('ayv-help-pointer-tap');
                return sleep(250).then(function() {
                    pointer.classList.remove('ayv-help-pointer-tap');
                    element.click();
                    return sleep(500);
                });
            });
        },
        // Types into the reading field as a scanner does: the characters, and Enter.
        scan: function(code) {
            return h.point('#ayv-code').then(function(input) {
                input.focus();
                input.value = '';
                var i = 0;
                return new Promise(function(resolve) {
                    var next = function() {
                        if (i < code.length) {
                            input.value += code[i++];
                            setTimeout(next, skipping ? 0 : 45);
                        } else {
                            input.form.requestSubmit();
                            resolve();
                        }
                    };
                    next();
                }).then(function() {
                    return sleep(900);
                });
            });
        },
        wait: wait,
        // Writes into any field, a character at a time.
        type: function(selector, text) {
            return h.point(selector).then(function(field) {
                field.focus();
                field.value = '';
                var i = 0;
                return new Promise(function(resolve) {
                    var next = function() {
                        if (i < text.length) {
                            field.value += text[i++];
                            setTimeout(next, skipping ? 0 : 45);
                        } else {
                            resolve(sleep(400));
                        }
                    };
                    next();
                });
            });
        },
        // Chooses an option of a list.
        choose: function(selector, value) {
            return h.point(selector).then(function(list) {
                list.value = value;
                list.dispatchEvent(new Event('change', {bubbles: true}));
                return sleep(500);
            });
        },
        // Taps something that loads another page in the frame, and waits for it.
        go: function(selector) {
            return h.point(selector).then(function(element) {
                pointer.classList.add('ayv-help-pointer-tap');
                var loaded = new Promise(function(resolve) {
                    frame.addEventListener('load', resolve, {once: true});
                });
                return sleep(250).then(function() {
                    pointer.classList.remove('ayv-help-pointer-tap');
                    element.click();
                    return loaded;
                }).then(function() {
                    // The new page has nothing under the old spot: the pointer waits out of sight.
                    pointer.classList.remove('ayv-help-pointer-on');
                    return sleep(400);
                });
            });
        },
        // «Now you»: a ring round the button, and nothing moves until it is tapped.
        you: function(index, selector) {
            caption.textContent = config.now + ' ' + (config.steps[index] || '');
            return wait(selector).then(function(element) {
                element.scrollIntoView({block: 'nearest'});
                var at = place(element);
                ring.style.left = (at.left - 8) + 'px';
                ring.style.top = (at.top - 8) + 'px';
                ring.style.width = (at.width + 16) + 'px';
                ring.style.height = (at.height + 16) + 'px';
                ring.hidden = false;
                pointer.classList.remove('ayv-help-pointer-on');
                return new Promise(function(resolve) {
                    element.addEventListener('click', function() {
                        ring.hidden = true;
                        resolve(sleep(700));
                    }, {once: true});
                    if (config.auto || skipping) {
                        setTimeout(function() {
                            element.click();
                        }, skipping ? 0 : 1200);
                    }
                });
            });
        },
        codes: config.codes || {}
    };

    var load = function() {
        return new Promise(function(resolve) {
            frame.addEventListener('load', function() {
                resolve();
            }, {once: true});
            frame.src = config.frame;
        });
    };

    var run = function() {
        start.hidden = true;
        done.hidden = true;
        skipping = false;
        return load().then(function() {
            return piece(h);
        }).then(function() {
            caption.textContent = '';
            pointer.classList.remove('ayv-help-pointer-on');
            done.hidden = false;
            document.getElementById('ayv-help-skip').hidden = true;
            done.querySelector('button').focus();
            document.body.setAttribute('data-help', 'done');
            return null;
        }).catch(function(error) {
            caption.textContent = error.message;
            document.body.setAttribute('data-help', 'failed');
        });
    };

    document.getElementById('ayv-help-go').addEventListener('click', run);
    document.getElementById('ayv-help-again').addEventListener('click', function() {
        window.location.reload();
    });
    document.getElementById('ayv-help-skip').addEventListener('click', function() {
        skipping = true;
    });
    if (config.auto) {
        run();
    }
})();
