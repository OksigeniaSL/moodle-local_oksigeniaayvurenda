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
 * Ayvurenda: the drawing pad of a review, for whoever would rather draw than write. A finger, a pen or the mouse draw;
 * the drawing goes with the form as a PNG, and with a drawing the words are no longer needed.
 *
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';
    document.querySelectorAll('[data-ayv-draw]').forEach(function(pad) {
        var form = pad.closest('form');
        var open = form.querySelector('[data-ayv-draw-open]');
        var canvas = pad.querySelector('canvas');
        var input = pad.querySelector('input[name="drawing"]');
        var words = form.querySelector('textarea[name="text"]');
        var context = canvas.getContext('2d');
        var colour = '#1e2b2a';
        var size = 6;
        var erasing = false;
        var drawn = false;
        var history = [];
        var last = null;
        var paper = function() {
            context.fillStyle = '#ffffff';
            context.fillRect(0, 0, canvas.width, canvas.height);
        };
        var mark = function(has) {
            drawn = has;
            // With a drawing, the words are no longer needed.
            words.required = !has;
        };
        var at = function(event) {
            var box = canvas.getBoundingClientRect();
            return {x: (event.clientX - box.left) * canvas.width / box.width, y: (event.clientY - box.top) * canvas.height / box.height};
        };
        var pencil = function() {
            context.strokeStyle = erasing ? '#ffffff' : colour;
            context.fillStyle = context.strokeStyle;
            context.lineWidth = erasing ? size * 2 : size;
            context.lineCap = 'round';
            context.lineJoin = 'round';
        };
        var press = function(group, chosen) {
            group.forEach(function(button) {
                button.setAttribute('aria-pressed', button === chosen ? 'true' : 'false');
            });
        };
        paper();
        open.addEventListener('click', function() {
            pad.hidden = false;
            open.hidden = true;
        });
        canvas.addEventListener('pointerdown', function(event) {
            event.preventDefault();
            canvas.setPointerCapture(event.pointerId);
            history.push(context.getImageData(0, 0, canvas.width, canvas.height));
            if (history.length > 30) {
                history.shift();
            }
            last = at(event);
            pencil();
            context.beginPath();
            context.arc(last.x, last.y, context.lineWidth / 2, 0, Math.PI * 2);
            context.fill();
        });
        canvas.addEventListener('pointermove', function(event) {
            if (!last) {
                return;
            }
            var point = at(event);
            pencil();
            context.beginPath();
            context.moveTo(last.x, last.y);
            context.lineTo(point.x, point.y);
            context.stroke();
            last = point;
        });
        ['pointerup', 'pointercancel'].forEach(function(type) {
            canvas.addEventListener(type, function() {
                if (last) {
                    last = null;
                    mark(true);
                }
            });
        });
        var colours = Array.prototype.slice.call(pad.querySelectorAll('[data-colour]'));
        var sizes = Array.prototype.slice.call(pad.querySelectorAll('[data-size]'));
        var eraser = pad.querySelector('[data-erase]');
        colours.forEach(function(button) {
            button.addEventListener('click', function() {
                colour = button.getAttribute('data-colour');
                erasing = false;
                eraser.setAttribute('aria-pressed', 'false');
                press(colours, button);
            });
        });
        sizes.forEach(function(button) {
            button.addEventListener('click', function() {
                size = parseInt(button.getAttribute('data-size'), 10);
                press(sizes, button);
            });
        });
        eraser.addEventListener('click', function() {
            erasing = !erasing;
            eraser.setAttribute('aria-pressed', erasing ? 'true' : 'false');
        });
        pad.querySelector('[data-undo]').addEventListener('click', function() {
            if (history.length) {
                context.putImageData(history.pop(), 0, 0);
                mark(history.length > 0);
            }
        });
        pad.querySelector('[data-clear]').addEventListener('click', function() {
            history.push(context.getImageData(0, 0, canvas.width, canvas.height));
            paper();
            mark(false);
        });
        form.addEventListener('submit', function() {
            input.value = drawn ? canvas.toDataURL('image/png') : '';
        });
    });
})();
