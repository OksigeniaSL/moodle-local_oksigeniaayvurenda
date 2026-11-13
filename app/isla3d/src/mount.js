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

// The class island of Ayvurenda in 3D, for «My class»: the island and its sea, and a piece for each thing the class
// met this school year, as in Gaudí's layers (pix/isla/). When a piece arrives it grows from the ground with a bounce and
// a few sparks, and then everything stops: the loop only runs while something moves, so an old Chromebook rests.
// With «reduce motion» nothing moves. Without WebGL2, mount() gives null and the page keeps the 2D layers.
//
//   const island = AYVIsland3D.mount(element, {met: 2, everyone: false, boat: false, reduce: false});
//   island.update({met: 3, everyone: true, boat: false});  // the new pieces grow
//
// The ship of the term (P5): with ship «wait» it is moored off the beach (the last week of the term); with «sail» (its
// last day) it hoists its sail and sets off to the horizon on the left, among sparks if the class met the challenge
// (shipmet), quietly if not, and the beach stays as it was.
//   island.destroy();                                      // frees geometries, textures and the WebGL context
import * as THREE from 'three';
import { GLTFLoader } from 'three/examples/jsm/loaders/GLTFLoader.js';
import { makeSea } from './sea.js';
import { makeGround, grassOf } from './terrain.js';
import {
  materials, makePalm, makeBushes, makeRocks, ROCKS, makeTree, makeHut, makeGarden, makeLighthouse, makeDock,
  makeSparkles, makeGrass, halo, makeShip,
} from './models.js';
import { SPOTS, SEA_Y } from './shape.js';

// The pieces in the order a school year brings them, with what makes each appear.
const PIECES = [
  { name: 'tree', make: makeTree, on: (s) => s.met >= 1 },
  { name: 'hut', make: makeHut, on: (s) => s.met >= 2 },
  { name: 'garden', make: makeGarden, on: (s) => s.met >= 3 },
  { name: 'lighthouse', make: makeLighthouse, on: (s) => !!s.everyone },
  { name: 'dock', make: makeDock, on: (s) => !!s.boat },
  { name: 'ship', make: makeShip, on: (s) => s.ship === 'wait' || s.ship === 'sail' },
];
const GROW_MS = 1100;
// The ship's going: it waits a moment, hoists its sail and sails out of sight.
const SAIL_WAIT_MS = 1200;
const SAIL_MS = 6500;
const HOIST = 0.16;
// Where it goes: off to the left and away, out of the frame.
const OFFING = { dx: -3.6, dz: -1.7 };
const ease = (t) => t * t * (3 - 2 * t);
const stateOf = (s) => ({ met: s.met || 0, everyone: !!s.everyone, boat: !!s.boat, ship: s.ship || '', shipmet: !!s.shipmet });

const easeOutBack = (t) => {
  const c = 1.6;
  return 1 + (c + 1) * Math.pow(t - 1, 3) + c * Math.pow(t - 1, 2);
};

function webgl2() {
  try {
    return !!document.createElement('canvas').getContext('webgl2');
  } catch (e) {
    return false;
  }
}

export function mount(element, options = {}) {
  if (!element || !webgl2()) {
    return null;
  }
  const reduce = !!options.reduce || (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: false, powerPreference: 'low-power' });
  // One pixel of the canvas for each pixel of the screen, at most: sharp enough, and light.
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1));
  renderer.outputColorSpace = THREE.SRGBColorSpace;
  const canvas = renderer.domElement;
  canvas.setAttribute('aria-hidden', 'true');
  canvas.style.display = 'block';
  canvas.style.width = '100%';
  canvas.style.height = '100%';
  element.appendChild(canvas);

  const scene = new THREE.Scene();
  scene.background = new THREE.Color('#0f6f86');
  scene.add(new THREE.HemisphereLight('#cdeefa', '#e2c38a', 1.2));
  // The sun from the top left and in front, as in Gaudí's 2D island.
  const sun = new THREE.DirectionalLight('#ffe2b0', 2.3);
  sun.position.set(-3, 5, 3.5);
  scene.add(sun);
  const fill = new THREE.DirectionalLight('#ffe9cc', 0.35);
  fill.position.set(3, 2.5, -2);
  scene.add(fill);

  const camera = new THREE.PerspectiveCamera(26, 16 / 10, 0.1, 60);
  const look = new THREE.Vector3(0.22, 0.2, 0.12);

  const mat = materials();
  const time = { value: 0 };
  const ground = makeGround();
  scene.add(ground.mesh);
  scene.add(makeGrass(grassOf('base'), 5), makePalm(mat), makeBushes(mat), makeRocks(mat));
  const rings = ROCKS.filter(([x, z]) => Math.hypot(x / 2.55, z / 1.62) > 0.92).map(([x, z, r]) => ({ x, z, r: r * 0.9 }));
  rings.push({ x: SPOTS.lighthouse.x, z: SPOTS.lighthouse.z, r: 0.2 });
  // Four rings of foam for the ship (Gaudí): one round its hull, so it floats, and three that follow it as its wake.
  const SHIP_RING = rings.length;
  for (let i = 0; i < 4; i++) {
    rings.push({ x: 0, z: 0, r: -1 });
  }
  const sea = makeSea(rings);
  sea.mesh.material.uniforms.uTime = time;
  sea.mesh.position.y = SEA_Y;
  scene.add(sea.mesh);
  const sparkles = makeSparkles();
  scene.add(sparkles);

  // Pieces Gaudí modelled (GLB in the art package) replace the drawn ones once loaded; the drawn ones stay without them.
  const assets = options.assets || {};
  let gone = false;
  function dress(name, group) {
    if (!assets[name]) {
      return;
    }
    new GLTFLoader().load(assets[name], (gltf) => {
      if (gone) {
        return;
      }
      for (const child of [...group.children]) {
        group.remove(child);
        child.traverse((o) => {
          if (o.geometry) {
            o.geometry.dispose();
          }
        });
      }
      gltf.scene.traverse((o) => {
        if (o.isMesh) {
          o.material = o.name === 'pantalla' ? mat.bulb : mat.solid;
        }
      });
      // A sail and a pennant are a single sheet: seen from either side, wherever the ship points.
      for (const sheet of ['vela', 'vela_recogida', 'banderin']) {
        const part = gltf.scene.getObjectByName(sheet);
        if (part) {
          part.traverse((o) => {
            if (o.isMesh) {
              o.material = mat.leaf;
            }
          });
        }
      }
      if (group.userData.rehook) {
        group.userData.rehook(gltf.scene);
        rig(group, group.userData.hoist || 0);
      }
      const light = gltf.scene.getObjectByName('luz');
      if (light) {
        light.add(halo(mat, 0, 0, 0, 0.32));
      }
      group.add(gltf.scene);
      render();
    }, undefined, () => {
      // Without the model, the drawn piece stays.
    });
  }

  // How far the ship's sail is up (0 furled, 1 hoisted): the sail grows from its foot and the furled one shrinks.
  function rig(ship, hoist) {
    const ud = ship.userData;
    ud.hoist = hoist;
    const up = ease(Math.max(0, Math.min(1, hoist)));
    ud.sail.scale.set(1, 0.12 + 0.88 * up, 1);
    if (ud.furl) {
      ud.furl.scale.set(1, Math.max(0.001, 1 - up), 1);
      ud.furl.visible = up < 0.999;
    }
  }

  // The ship's foam: round its hull while it is there, and its wake while it sails.
  const wake = [];
  function foam(ship) {
    const slots = sea.mesh.material.uniforms.uRings.value;
    const on = ship && ship.visible;
    slots[SHIP_RING].set(on ? ship.position.x : 0, on ? ship.position.z : 0, on ? 0.34 : -1);
    for (let i = 0; i < 3; i++) {
      const w = on && sailing ? wake[(i + 1) * 7] : null;
      slots[SHIP_RING + 1 + i].set(w ? w.x : 0, w ? w.z : 0, w ? 0.24 - i * 0.06 : -1);
    }
  }

  const shown = new Map();
  let growing = [];
  let sailing = null;
  let sailed = false;
  let until = 0;
  let frame = 0;
  let last = 0;

  // Where the island stands in its box: in the middle, or (frame) moved right and down and a little further away, as
  // on the whiteboard, clear of the sign at the top left and the herald below (Jujol).
  const framing = options.frame || null;
  // Where the ship waits and where it goes: off the front beach and away to the left, or (berth) elsewhere, as on the
  // whiteboard, where the herald's bubble covers the front beach and the ship waits behind the island instead.
  const berth = options.berth || null;
  const offing = berth && berth.offing ? berth.offing : OFFING;
  function frameSize() {
    const w = Math.max(1, element.clientWidth), h = Math.max(1, element.clientHeight);
    renderer.setSize(w, h, false);
    camera.aspect = w / h;
    // A box narrower than 16:10 sees the island from further away, so it always fits whole.
    const far = 8.7 * Math.max(1, 1.6 / camera.aspect) * (framing ? framing.zoom || 1 : 1);
    camera.position.set(0.55, far * 0.68, far * 0.78);
    camera.lookAt(look);
    if (framing) {
      camera.setViewOffset(w, h, -(framing.x || 0) * w, -(framing.y || 0) * h, w, h);
    }
    camera.updateProjectionMatrix();
  }

  function render() {
    renderer.render(scene, camera);
  }

  function tick(now) {
    frame = 0;
    const dt = last ? Math.min(0.05, (now - last) / 1000) : 0;
    last = now;
    time.value += dt;
    let moving = false;
    for (const g of growing) {
      const t = Math.min(1, (now - g.start) / GROW_MS);
      const s = Math.max(0.001, easeOutBack(t)) * g.group.userData.size;
      g.group.scale.set(s, s, s);
      if (t < 1) {
        moving = true;
      }
    }
    growing = growing.filter((g) => now - g.start < GROW_MS);
    if (sparkles.visible) {
      const p = sparkles.geometry.attributes.position;
      const age = (now - sparkles.userData.start) / 1600;
      for (let i = 0; i < p.count; i++) {
        const v = sparkles.userData.v[i];
        p.setXYZ(i, sparkles.userData.x + v[0] * age, sparkles.userData.y + v[1] * age - 0.25 * age * age,
          sparkles.userData.z + v[2] * age);
      }
      p.needsUpdate = true;
      sparkles.material.opacity = Math.max(0, 1 - age);
      sparkles.visible = age < 1;
      moving = moving || sparkles.visible;
    }
    // The boat bobs while anything moves.
    const dock = shown.get('dock');
    if (dock) {
      const boat = dock.userData.boat;
      boat.position.y = SEA_Y - 0.01 + Math.sin(time.value * 1.6) * 0.008;
      boat.rotation.z = Math.sin(time.value * 1.2) * 0.03;
    }
    // The ship bobs too, and on its day it hoists its sail and goes.
    const ship = shown.get('ship');
    if (ship && ship.visible) {
      ship.position.y = SEA_Y - 0.01 + Math.sin(time.value * 1.4 + 1) * 0.01;
      ship.rotation.z = Math.sin(time.value * 1.1 + 1) * 0.035;
      ship.userData.pennant.rotation.y = Math.sin(time.value * 5) * 0.35;
      if (sailing) {
        const t = Math.max(0, (now - sailing.start) / SAIL_MS);
        rig(ship, Math.min(1, t / HOIST));
        const go = ease(Math.max(0, Math.min(1, (t - HOIST) / (1 - HOIST))));
        // Where it has been, for its wake.
        if (go > 0) {
          wake.unshift({ x: ship.position.x, z: ship.position.z });
          wake.length = Math.min(wake.length, 24);
        }
        ship.position.x = sailing.x + offing.dx * go;
        ship.position.z = sailing.z + offing.dz * go;
        // Its bow turns to where it goes, and it heels with the wind once under way.
        ship.rotation.y = sailing.yaw + (Math.atan2(-offing.dz, offing.dx) - sailing.yaw) * Math.min(1, go * 3);
        ship.rotation.x = -0.06 * Math.min(1, go * 4);
        if (t >= 1) {
          ship.visible = false;
          sailing = null;
          sailed = true;
          wake.length = 0;
        } else {
          moving = true;
        }
      }
      foam(ship);
    } else if (ship) {
      foam(null);
    }
    render();
    if (moving || now < until) {
      frame = requestAnimationFrame(tick);
    } else {
      last = 0;
    }
  }

  function run(ms) {
    if (reduce) {
      render();
      return;
    }
    until = Math.max(until, performance.now() + ms);
    if (!frame) {
      frame = requestAnimationFrame(tick);
    }
  }

  function spark(at) {
    const count = sparkles.geometry.attributes.position.count;
    sparkles.userData = { start: performance.now(), x: at.x, y: at.y, z: at.z, v: [] };
    for (let i = 0; i < count; i++) {
      const a = (i / count) * Math.PI * 2;
      sparkles.userData.v.push([Math.cos(a) * (0.2 + Math.random() * 0.3), 0.6 + Math.random() * 0.6,
        Math.sin(a) * (0.2 + Math.random() * 0.3)]);
    }
    sparkles.material.opacity = 1;
    sparkles.visible = true;
  }

  // Shows what the state asks for: pieces that were there appear at once; new ones grow, one after another.
  function show(state, animate) {
    // With «reduce motion», on its last day the ship has simply gone.
    if (state.ship === 'sail' && reduce) {
      state = Object.assign({}, state, { ship: '' });
    }
    const names = [];
    let delay = 0;
    for (const piece of PIECES) {
      if (!piece.on(state)) {
        continue;
      }
      names.push(piece.name);
      if (shown.has(piece.name)) {
        continue;
      }
      const group = piece.make(mat);
      if (piece.name === 'ship' && berth) {
        group.position.x = berth.x;
        group.position.z = berth.z;
        group.rotation.y = berth.yaw;
      }
      group.userData.size = group.userData.size || 1;
      group.scale.setScalar(group.userData.size);
      shown.set(piece.name, group);
      scene.add(group);
      dress(piece.name, group);
      // Its grass, cut paper on the sand, grows with it.
      const grass = makeGrass(grassOf(piece.name), piece.name.length * 7);
      grass.userData.size = 1;
      scene.add(grass);
      if (animate && !reduce) {
        // Where the sparks rise: the middle of the piece at its full size, a little above its foot.
        const box = new THREE.Box3().setFromObject(group);
        const at = box.getCenter(new THREE.Vector3());
        at.y = box.min.y + 0.15;
        group.scale.set(0.001, 0.001, 0.001);
        grass.scale.set(0.001, 0.001, 0.001);
        const start = performance.now() + delay;
        growing.push({ group, start }, { group: grass, start });
        setTimeout(() => spark(at), delay);
        delay += 700;
      }
    }
    ground.paint(names);
    const ship = shown.get('ship');
    if (ship && state.ship !== 'wait' && state.ship !== 'sail') {
      ship.visible = false;
    }
    // Waiting, its sail is furled: hoisting it is what says it is going.
    if (ship && state.ship === 'wait' && !sailing) {
      rig(ship, 0);
    }
    foam(ship || null);
    // Its last day: once it is in sight, it sails (once for each time the island is opened).
    let ms = animate ? delay + GROW_MS + 1800 : 2500;
    if (ship && state.ship === 'sail' && !sailing && !sailed) {
      rig(ship, 0);
      sailing = { start: performance.now() + SAIL_WAIT_MS, x: ship.position.x, z: ship.position.z, yaw: ship.rotation.y };
      if (state.shipmet) {
        // The class met its challenge: it leaves among sparks.
        setTimeout(() => {
          spark(new THREE.Vector3(ship.position.x, SEA_Y + 0.5, ship.position.z));
        }, SAIL_WAIT_MS + 300);
      }
      ms = Math.max(ms, SAIL_WAIT_MS + SAIL_MS + 600);
    }
    run(ms);
  }

  frameSize();
  show(stateOf(options), false);
  render();
  const resize = new ResizeObserver(() => {
    frameSize();
    render();
  });
  resize.observe(element);

  return {
    // What the last frame drew: for measuring, not for the page.
    stats() {
      return { triangles: renderer.info.render.triangles, calls: renderer.info.render.calls };
    },
    update(state) {
      show(stateOf(state), true);
    },
    destroy() {
      gone = true;
      cancelAnimationFrame(frame);
      resize.disconnect();
      scene.traverse((object) => {
        if (object.geometry) {
          object.geometry.dispose();
        }
        if (object.material) {
          for (const m of [].concat(object.material)) {
            if (m.map) {
              m.map.dispose();
            }
            m.dispose();
          }
        }
      });
      renderer.dispose();
      renderer.forceContextLoss();
      canvas.remove();
    },
  };
}

window.AYVIsland3D = { mount };
