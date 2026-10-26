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

// Gaudí's puppet player, from the art kit of Oksigenia (agents/arte/kit/marionetas/marioneta.js), as it is.

/**
 * Reference player for the puppets of cortar.py: plain JS, no dependencies, drawing on a 2D canvas.
 *
 *   var p = new Puppet({atlas: atlasJson, image: img, skeleton: skeletonJson, anims: animJson, tone: 't2'});
 *   p.play('walk');            // or 'idle', 'surprise', 'point', 'celebrate'…
 *   p.update(dtSeconds);       // every frame
 *   p.draw(ctx, x, y, scale, flip);   // x, y: where the feet go
 *
 * The atlas brings what belongs to one figure (each piece, its pivot and where its joint sits from its parent's); the
 * skeleton, what every body of its kind shares (who hangs from whom, drawing order); the animations are data too, so
 * the same file moves every hero. Keyframes: [t (0..1 of the clip), values, ease into it], values absolute from the
 * pose the figure was cut in (r in degrees, x and y in px of the 768 x 896 figure, sx and sy). Parts a clip does not
 * move keep the `base` pose. Eases are named as in Phaser. With reduced motion, `still` gives the pose to hold. A figure
 * may hang a piece from another parent or draw it in another order (meta.rig.override), bring pieces of its own
 * (meta.rig.extra, a fairy's wings: the gestures move them if they have tracks for them), and one with its tool in the
 * left hand (meta.rig.toolHand 'l') plays every gesture mirrored. Arms, forearms and tool are asked for as absolute
 * directions (`a`: 0 to the right, 90 down), so that every figure, whatever the pose it was cut in, takes the same
 * posture; the atlas gives each limb's direction in the cut pose (meta.rig.dirs).
 */
(function(root) {
  'use strict';

  var EASE = {
    'linear': function(t) { return t; },
    'Sine.easeIn': function(t) { return 1 - Math.cos(t * Math.PI / 2); },
    'Sine.easeOut': function(t) { return Math.sin(t * Math.PI / 2); },
    'Sine.easeInOut': function(t) { return -(Math.cos(Math.PI * t) - 1) / 2; },
    'Quad.easeIn': function(t) { return t * t; },
    'Quad.easeOut': function(t) { return t * (2 - t); },
    'Quad.easeInOut': function(t) { return t < 0.5 ? 2 * t * t : -1 + (4 - 2 * t) * t; },
    'Cubic.easeOut': function(t) { return 1 - Math.pow(1 - t, 3); },
    'Back.easeOut': function(t) { var s = 1.70158; t -= 1; return t * t * ((s + 1) * t + s) + 1; },
    'Back.easeIn': function(t) { var s = 1.70158; return t * t * ((s + 1) * t - s); }
  };
  var ZERO = {r: 0, x: 0, y: 0, sx: 1, sy: 1};

  function Puppet(o) {
    var self = this;
    this.image = o.image;
    this.anims = o.anims;
    this.reduced = !!o.reduced;
    var meta = o.atlas.meta.rig;
    this.feet = meta.feet;
    // A figure that holds its tool in the left hand plays every gesture mirrored (left and right swapped, turns and
    // sideways moves the other way), so it points and waves with its free hand.
    this.mirror = meta.toolHand === 'l';
    this.dirs = meta.dirs || {};
    // What the gestures change for a kind of tool (byTool.<tool>): a shield hangs from the forearm but stays upright
    // (an absolute direction, 270), and has its own rest pose and moves where a staff would look wrong. A tool listed in
    // `rigid` turns with its forearm instead.
    this.byTool = (o.anims.byTool || {})[meta.tool] || {};
    // Skin tone: the figure is painted in meta.rig.skin.own; for another tone, each piece with skin is drawn again on
    // top with its frame <piece>.skin.<tone> (the same piece, its skin already moved to that tone).
    this.skin = meta.skin || null;
    this.tone = o.tone || null;
    this.frames = o.atlas.frames;
    var override = meta.override || {};
    // Pieces the skeleton has but this figure does not (the Embrollo's tail, once it has unravelled) are left out.
    this.parts = o.skeleton.parts.concat(meta.extra || []).filter(function(s) {
      return !!o.atlas.frames[s.name];
    }).map(function(s) {
      var f = o.atlas.frames[s.name], ov = override[s.name] || {};
      return {name: s.name, parent: 'parent' in ov ? ov.parent : s.parent, z: 'z' in ov ? ov.z : s.z, frame: f.frame,
        pivot: [f.pivot.x * f.frame.w, f.pivot.y * f.frame.h], at: meta.joints[s.name]};
    });
    this.byName = {};
    this.parts.forEach(function(p) { self.byName[p.name] = p; });
    this.clip = null;
    this.time = 0;
    this.play('idle');
  }

  /** Starts a clip from its beginning; a clip that is already playing goes on. */
  Puppet.prototype.play = function(name) {
    if (this.clipName === name) {
      return;
    }
    this.clipName = name;
    var clip = this.anims.clips[name] || null, extra = (this.byTool.clips || {})[name];
    this.clip = clip && extra ? Object.assign({}, clip, {tracks: Object.assign({}, clip.tracks, extra)}) : clip;
    this.time = 0;
  };

  /** True when a clip that does not loop has reached its end (and holds its last pose). */
  Puppet.prototype.done = function() {
    return !!this.clip && !this.clip.loop && this.time >= this.clip.duration;
  };

  Puppet.prototype.update = function(dt) {
    this.time += dt;
  };

  /** The values of a track at time t (0..1). */
  function sample(track, t) {
    if (t <= track[0][0]) {
      return track[0][1];
    }
    for (var i = 1; i < track.length; i++) {
      if (t <= track[i][0]) {
        var a = track[i - 1], b = track[i];
        var k = (EASE[b[2] || 'linear'] || EASE.linear)((t - a[0]) / (b[0] - a[0] || 1));
        var v = {};
        Object.keys(ZERO).concat('a' in a[1] || 'a' in b[1] ? ['a'] : []).forEach(function(key) {
          var va = key in a[1] ? a[1][key] : (key in b[1] ? b[1][key] : ZERO[key]);
          var vb = key in b[1] ? b[1][key] : va;
          v[key] = va + (vb - va) * k;
        });
        return v;
      }
    }
    return track[track.length - 1][1];
  }

  /** The pose now: for each part (and 'root'), {r, x, y, sx, sy}. */
  Puppet.prototype.pose = function() {
    var clip = this.clip, base = Object.assign({}, this.anims.base || {}, this.byTool.base || {}), out = {}, t = 0;
    var rigid = this.byTool.rigid || [];
    if (clip) {
      if (this.reduced) {
        t = 'still' in clip ? clip.still : 1;
      } else if (clip.loop) {
        t = (this.time % clip.duration) / clip.duration;
      } else {
        t = Math.min(1, this.time / clip.duration);
      }
    }
    var mirror = this.mirror;
    ['root'].concat(this.parts.map(function(p) { return p.name; })).forEach(function(name) {
      var src = mirror ? name.replace(/_(l|r)$/, function(m, side) { return side === 'l' ? '_r' : '_l'; }) : name;
      var v = Object.assign({}, ZERO, base[src] || {});
      if (clip && clip.tracks[src]) {
        Object.assign(v, sample(clip.tracks[src], t));
      }
      if (rigid.indexOf(name) >= 0) {
        delete v.a;
        v.r = 0;
      }
      if (mirror) {
        v.r = -v.r;
        v.x = -v.x;
        if ('a' in v) {
          v.a = 180 - v.a;
        }
      }
      out[name] = v;
    });
    return out;
  };

  /** Draws the puppet with its feet at (x, y), at `scale` of the 768 x 896 figure, mirrored if `flip`. */
  Puppet.prototype.draw = function(ctx, x, y, scale, flip) {
    var pose = this.pose(), self = this, world = {}, turned = {};
    var rootv = pose.root;
    // How much each part has turned from the cut pose, in the world: a value `a` asks for an absolute direction (the
    // part's own direction in the cut pose, `dirs`, is taken away); `r` turns it from where its parent left it.
    function turn(p) {
      if (!(p.name in turned)) {
        var v = pose[p.name], up = p.parent ? turn(self.byName[p.parent]) : 0;
        turned[p.name] = 'a' in v ? v.a - (self.dirs[p.name] || 0) : up + v.r;
      }
      return turned[p.name];
    }
    // World matrix of each part: parent's, then to its joint, then its own turn and stretch.
    function matrixOf(p) {
      if (world[p.name]) {
        return world[p.name];
      }
      var m = p.parent ? matrixOf(self.byName[p.parent]) : new DOMMatrix();
      var v = pose[p.name];
      var local = turn(p) - (p.parent ? turn(self.byName[p.parent]) : 0);
      m = m.translate(p.at[0] + v.x, p.at[1] + v.y).rotate(local).scale(v.sx, v.sy);
      world[p.name] = m;
      return m;
    }
    ctx.save();
    ctx.translate(x, y);
    ctx.scale(flip ? -scale : scale, scale);
    // The root moves the whole figure around its feet.
    ctx.translate(rootv.x, rootv.y);
    ctx.rotate(rootv.r * Math.PI / 180);
    ctx.scale(rootv.sx, rootv.sy);
    ctx.translate(-this.feet[0], -this.feet[1]);
    var base = ctx.getTransform();
    this.parts.slice().sort(function(a, b) { return a.z - b.z; }).forEach(function(p) {
      ctx.setTransform(base.multiply(matrixOf(p)));
      var f = p.frame;
      ctx.drawImage(self.image, f.x, f.y, f.w, f.h, -p.pivot[0], -p.pivot[1], f.w, f.h);
      var over = self.skin && self.tone && self.tone !== self.skin.own && self.frames[p.name + '.skin.' + self.tone];
      if (over) {
        var g = over.frame;
        ctx.drawImage(self.image, g.x, g.y, g.w, g.h, -p.pivot[0], -p.pivot[1], g.w, g.h);
      }
    });
    ctx.restore();
  };

  root.Puppet = Puppet;
})(typeof window !== 'undefined' ? window : this);
