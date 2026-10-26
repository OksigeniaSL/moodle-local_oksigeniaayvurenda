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
 * Ayvurenda: the characters, alive. Where the art package brings Gaudí's puppets, a pose drawn still (an image with
 * data-puppet) becomes the puppet doing that gesture, then breathing a while; a touch plays the gesture again. It only
 * moves while it is in sight and for a little while, and never with «reduce motion»; without the puppet, the pose stays.
 *
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduce || !window.Puppet || !window.DOMMatrix || !window.fetch) {
        return;
    }
    // How long it stays alive after its gesture: then it holds still, so a page left open costs nothing.
    var ALIVE = 9;
    var json = function(url) {
        return fetch(url, {credentials: 'same-origin'}).then(function(r) {
            if (!r.ok) {
                throw new Error(r.status);
            }
            return r.json();
        });
    };
    var picture = function(url) {
        return new Promise(function(resolve, reject) {
            var image = new Image();
            image.onload = function() {
                resolve(image);
            };
            image.onerror = reject;
            image.src = url;
        });
    };
    function mount(img) {
        var spec;
        try {
            spec = JSON.parse(img.getAttribute('data-puppet'));
        } catch (e) {
            return;
        }
        Promise.all([json(spec.atlas), json(spec.rig), json(spec.anim), picture(spec.image)]).then(function(parts) {
            var puppet = new window.Puppet({atlas: parts[0], skeleton: parts[1], anims: parts[2], image: parts[3]});
            var gesture = parts[2].clips[spec.gesture] ? spec.gesture : 'idle';
            var canvasSize = parts[0].meta.rig.canvas || [512, 512];
            var canvas = document.createElement('canvas');
            canvas.className = img.className + ' ayv-puppet';
            canvas.setAttribute('aria-hidden', 'true');
            var w = img.clientWidth || 170, h = img.clientHeight || 170, ratio = Math.min(window.devicePixelRatio || 1, 2);
            canvas.width = Math.round(w * ratio);
            canvas.height = Math.round(h * ratio);
            canvas.style.width = w + 'px';
            canvas.style.height = h + 'px';
            var ctx = canvas.getContext('2d');
            // The figure's canvas fitted in the box, feet at the bottom, as the pose was.
            var s = Math.min(canvas.width / canvasSize[0], canvas.height / canvasSize[1]);
            var ox = (canvas.width - canvasSize[0] * s) / 2, oy = canvas.height - canvasSize[1] * s;
            var frame = 0, last = 0, until = 0;
            var draw = function() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                puppet.draw(ctx, ox + puppet.feet[0] * s, oy + puppet.feet[1] * s, s, false);
            };
            // Where a looping clip is in its turn (0..1).
            var phase = function() {
                return (puppet.time % puppet.clip.duration) / puppet.clip.duration;
            };
            var tick = function(now) {
                frame = 0;
                var before = puppet.clip && puppet.clip.loop ? phase() : 0;
                puppet.update(last ? Math.min(0.05, (now - last) / 1000) : 0);
                last = now;
                if (puppet.done()) {
                    puppet.play('idle');
                    before = 0;
                }
                if (now >= until && puppet.clip && puppet.clip.loop) {
                    // Time to rest: the loop goes on to its still pose (the one drawn on the page) and stops there, so a
                    // bird is never left frozen in the middle of a wingbeat.
                    var still = puppet.clip.still || 0, after = phase();
                    var passed = before <= after ? before < still && still <= after : still > before || still <= after;
                    if (passed || now > until + 6000) {
                        puppet.time = still * puppet.clip.duration;
                        draw();
                        last = 0;
                        return;
                    }
                }
                draw();
                frame = window.requestAnimationFrame(tick);
            };
            var start = function(name) {
                puppet.play('idle');
                puppet.play(name);
                until = window.performance.now() + ALIVE * 1000;
                if (!frame) {
                    frame = window.requestAnimationFrame(tick);
                }
            };
            img.parentNode.insertBefore(canvas, img.nextSibling);
            img.hidden = true;
            // The first frame is the gesture's own, so reading or arriving never shows the rest pose for an instant.
            puppet.play(gesture);
            draw();
            canvas.addEventListener('click', function() {
                start(gesture);
            });
            var seen = false;
            var watch = new window.IntersectionObserver(function(entries) {
                if (entries[0].isIntersecting && !seen) {
                    seen = true;
                    start(gesture);
                }
            });
            watch.observe(canvas);
        }).catch(function() {
            // Without the puppet, the pose stays.
        });
    }
    Array.prototype.forEach.call(document.querySelectorAll('img[data-puppet]'), mount);
})();
