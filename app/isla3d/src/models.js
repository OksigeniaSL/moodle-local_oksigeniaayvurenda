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

// What stands on the island, all made of simple shapes with vertex colours (Lambert: cheap and warm): the palm, the
// bushes and the rocks of the base, and the pieces a class wins (Gaudí's layers): the flowering tree with its round
// bench, the reading hut, the garden, the lighthouse of books, and the dock with the boat that brings the book the
// class asked for. Each piece is a group whose origin is its foot, so it can grow from the ground.
import * as THREE from 'three';
import { mergeGeometries } from 'three/examples/jsm/utils/BufferGeometryUtils.js';
import { mergeVertices } from 'three/examples/jsm/utils/BufferGeometryUtils.js';
import { rng, terrainH, SPOTS, SEA_Y } from './shape.js';

const UP = new THREE.Vector3(0, 1, 0);
const tmpM = new THREE.Matrix4();
const tmpQ = new THREE.Quaternion();
const tmpV = new THREE.Vector3();
const tmpC = new THREE.Color();

// Shared materials: vertex colours, both lit the same warm way.
export function materials() {
  // A soft warm disc for the halo of a lamp, added to what is behind it.
  const canvas = document.createElement('canvas');
  canvas.width = canvas.height = 64;
  const ctx = canvas.getContext('2d');
  const g = ctx.createRadialGradient(32, 32, 0, 32, 32, 32);
  g.addColorStop(0, 'rgba(255, 220, 140, 0.95)');
  g.addColorStop(0.35, 'rgba(255, 200, 110, 0.4)');
  g.addColorStop(1, 'rgba(255, 190, 100, 0)');
  ctx.fillStyle = g;
  ctx.fillRect(0, 0, 64, 64);
  const halo = new THREE.CanvasTexture(canvas);
  halo.colorSpace = THREE.SRGBColorSpace;
  return {
    // Flat facets, as the house's style D and the dioramas of campus (Gaudí).
    solid: new THREE.MeshLambertMaterial({ vertexColors: true, flatShading: true }),
    leaf: new THREE.MeshLambertMaterial({ vertexColors: true, side: THREE.DoubleSide, flatShading: true }),
    glow: new THREE.MeshBasicMaterial({ color: '#ffd77a' }),
    // The bulbs of a model of the art package keep their own colours.
    bulb: new THREE.MeshBasicMaterial({ vertexColors: true }),
    halo: new THREE.SpriteMaterial({ map: halo, blending: THREE.AdditiveBlending, depthWrite: false, transparent: true }),
  };
}

// The glow around a lamp.
export function halo(mat, x, y, z, size = 0.3) {
  const sprite = new THREE.Sprite(mat.halo);
  sprite.position.set(x, y, z);
  sprite.scale.set(size, size, size);
  return sprite;
}

// Grass like cut paper: flat patches with a wavy edge, a couple of centimetres above the sand, around (cx, cz).
export function makeGrass(patches, seed) {
  const group = new THREE.Group();
  if (!patches.length) {
    return group;
  }
  const [cx, cz] = patches[0];
  group.position.set(cx, 0, cz);
  const rand = rng(seed);
  const geos = [];
  const shades = ['#7fb83a', '#8fc441', '#6fa835'];
  patches.forEach(([x, z, r], k) => {
    // A polar mesh (rings of points out to a wavy edge), so the patch follows the dome of sand instead of cutting it.
    const n = 28, rings = 4;
    const edge = [];
    for (let i = 0; i < n; i++) {
      const a = (i / n) * Math.PI * 2;
      edge.push(r * (0.78 + 0.22 * Math.sin(a * 3 + k) + 0.12 * (rand() - 0.5)));
    }
    const pos = [];
    const vertex = (ring, i) => {
      const a = (i / n) * Math.PI * 2, f = ring / rings;
      const wx = x + Math.cos(a) * edge[i % n] * f, wz = z + Math.sin(a) * edge[i % n] * f * 0.82;
      return [wx - cx, terrainH(wx, wz) + 0.022 + k * 0.004, wz - cz];
    };
    for (let ring = 0; ring < rings; ring++) {
      for (let i = 0; i < n; i++) {
        const a = vertex(ring, i), b = vertex(ring + 1, i), c = vertex(ring + 1, i + 1), d = vertex(ring, i + 1);
        pos.push(...a, ...c, ...b);
        if (ring > 0) {
          pos.push(...a, ...d, ...c);
        }
      }
    }
    const geo = new THREE.BufferGeometry();
    geo.setAttribute('position', new THREE.Float32BufferAttribute(pos, 3));
    geo.computeVertexNormals();
    geos.push(paint(geo, shades[k % 3], null, 0.04, seed + k));
  });
  group.add(mesh(geos, new THREE.MeshLambertMaterial({ vertexColors: true, side: THREE.DoubleSide })));
  return group;
}

// Paints a geometry one colour, or a gradient from its foot (c2) to its top (c1); a light jitter keeps it from looking
// flat.
function paint(geo, c1, c2 = null, jitter = 0, seed = 1) {
  const g = geo.index ? geo.toNonIndexed() : geo;
  const pos = g.attributes.position;
  const n = pos.count;
  const col = new Float32Array(n * 3);
  let min = Infinity, max = -Infinity;
  for (let i = 0; i < n; i++) {
    min = Math.min(min, pos.getY(i));
    max = Math.max(max, pos.getY(i));
  }
  const a = new THREE.Color(c1), b = c2 ? new THREE.Color(c2) : a;
  const rand = rng(seed);
  for (let i = 0; i < n; i += 3) {
    const j = 1 + (rand() - 0.5) * jitter;
    for (let k = 0; k < 3 && i + k < n; k++) {
      const t = (pos.getY(i + k) - min) / Math.max(1e-6, max - min);
      tmpC.copy(b).lerp(a, t).multiplyScalar(j);
      col.set([tmpC.r, tmpC.g, tmpC.b], (i + k) * 3);
    }
  }
  g.setAttribute('color', new THREE.BufferAttribute(col, 3));
  if (g.attributes.uv) {
    g.deleteAttribute('uv');
  }
  return g;
}

const at = (geo, x, y, z, ry = 0, rx = 0, rz = 0) => {
  geo.applyMatrix4(tmpM.makeRotationFromEuler(new THREE.Euler(rx, ry, rz)));
  geo.translate(x, y, z);
  return geo;
};
const box = (w, h, d, colour, x, y, z, ry = 0, seed = 1, rx = 0, rz = 0) =>
  at(paint(new THREE.BoxGeometry(w, h, d), colour, null, 0.08, seed), x, y, z, ry, rx, rz);

function mesh(geos, material) {
  const m = new THREE.Mesh(mergeGeometries(geos), material);
  return m;
}

function place(group, spot) {
  group.position.set(spot.x, terrainH(spot.x, spot.z) - 0.01, spot.z);
  return group;
}

// ---------------------------------------------------------------- the palm (after the island test of the incubator)
function frond(L, W, lift, droop, nLeaf, rand) {
  const pos = [], col = [], idx = [];
  const c0 = new THREE.Color('#1d5a10'), c1 = new THREE.Color('#3f8c19'), c2 = new THREE.Color('#9fca35');
  const c = new THREE.Color();
  const rib = (s) => new THREE.Vector3(L * s, L * (lift * s - droop * s * s), 0);
  const push = (p, cc, m) => {
    pos.push(p.x, p.y, p.z);
    col.push(cc.r * m, cc.g * m, cc.b * m);
    return pos.length / 3 - 1;
  };
  for (let k = 0; k < nLeaf; k++) {
    const s0 = 0.08 + 0.84 * (k / nLeaf), s1 = Math.min(0.99, s0 + 0.84 * 1.25 / nLeaf);
    const sm = (s0 + s1) / 2;
    const len = W * Math.pow(Math.sin(Math.PI * Math.min(1, sm * 0.9 + 0.1)), 0.5) * (0.9 + rand() * 0.2);
    for (const side of [-1, 1]) {
      const a = rib(s0), b = rib(s1);
      const dir = rib(Math.min(1, sm + 0.05)).sub(rib(Math.max(0, sm - 0.05))).normalize();
      const out = (p, f, o, d) => p.clone().addScaledVector(dir, len * f).add(new THREE.Vector3(0, -len * d, side * len * o));
      const oa = out(a, 0.3, 0.9, 0.22), tip = out(b, 0.7, 0.72, 0.3);
      const mid = a.clone().lerp(b, 0.5).lerp(oa.clone().lerp(tip, 0.5), 0.5).add(new THREE.Vector3(0, len * 0.1, 0));
      c.copy(c1).lerp(c2, sm);
      const base = c.clone().lerp(c0, 0.5);
      const j = 0.9 + rand() * 0.2;
      const ia = push(a, base, j), ib = push(b, base, j), im = push(mid, c, j);
      const io = push(oa, c, j * 1.1), it = push(tip, c, j * 1.15);
      idx.push(ia, io, im, im, io, it, ia, im, ib, ib, im, it);
    }
  }
  const g = new THREE.BufferGeometry();
  g.setAttribute('position', new THREE.Float32BufferAttribute(pos, 3));
  g.setAttribute('color', new THREE.Float32BufferAttribute(col, 3));
  g.setIndex(idx);
  g.computeVertexNormals();
  return g.toNonIndexed();
}

export function makePalm(mat) {
  const rand = rng(303);
  const group = place(new THREE.Group(), SPOTS.palm);
  const top = new THREE.Vector3(0.38, 1.25, -0.08);
  const curve = new THREE.QuadraticBezierCurve3(new THREE.Vector3(), new THREE.Vector3(0.05, 0.62, 0), top);
  const trunk = [];
  const segs = 12;
  for (let i = 0; i < segs; i++) {
    const a = curve.getPoint(i / segs), b = curve.getPoint((i + 1) / segs);
    const r = 0.075 - 0.028 * (i / segs);
    const g = paint(new THREE.CylinderGeometry(r * 1.02, r * 0.95, a.distanceTo(b) * 1.06, 8, 1, true),
      i % 2 ? '#a8703a' : '#b98146', '#83501f', 0.1, i + 1);
    tmpQ.setFromUnitVectors(UP, tmpV.copy(b).sub(a).normalize());
    g.applyMatrix4(tmpM.compose(a.clone().add(b).multiplyScalar(0.5), tmpQ, new THREE.Vector3(1, 1, 1)));
    trunk.push(g);
  }
  group.add(mesh(trunk, mat.solid));
  const leaves = [];
  for (let k = 0; k < 9; k++) {
    const g = frond(0.62 + rand() * 0.12, 0.15 + rand() * 0.03, 0.55, 1.0 + rand() * 0.4, 6, rand);
    g.applyMatrix4(tmpM.makeRotationY((k / 9) * Math.PI * 2 + (rand() - 0.5) * 0.4)
      .multiply(new THREE.Matrix4().makeRotationZ(0.05 + rand() * 0.3)));
    g.translate(top.x, top.y, top.z);
    leaves.push(g);
  }
  for (let k = 0; k < 3; k++) {
    const a = k * 2.1;
    leaves.push(at(paint(new THREE.IcosahedronGeometry(0.045, 1), '#5a3a1a'), top.x + Math.cos(a) * 0.05, top.y - 0.05,
      top.z + Math.sin(a) * 0.05));
  }
  group.add(mesh(leaves, mat.leaf));
  return group;
}

// Bushes: clusters of leafy balls, some with a flower.
export function makeBushes(mat) {
  const spots = [[-1.75, -0.38, 1.0], [-1.18, -0.42, 0.8], [-1.62, -0.92, 0.75], [1.75, -0.3, 0.7], [0.55, -0.92, 0.6],
    [-0.62, -1.02, 0.55]];
  const geos = [];
  const rand = rng(17);
  for (const [x, z, s] of spots) {
    const y = terrainH(x, z);
    for (let i = 0; i < 4; i++) {
      const r = (0.07 + rand() * 0.05) * s;
      const greens = ['#3f8a22', '#58a52b', '#2f7420'];
      geos.push(at(paint(new THREE.IcosahedronGeometry(r, 1), greens[i % 3], '#24561a', 0.12, i * 7 + 3),
        x + (rand() - 0.5) * 0.16 * s, y + r * 0.7, z + (rand() - 0.5) * 0.1 * s));
    }
  }
  return mesh(geos, mat.solid);
}

// Rocks: on the beach, at the shore and out in the water, and the bed of the lighthouse.
export const ROCKS = [
  [-1.62, 0.62, 0.2, 1], [-1.38, 0.78, 0.08, 2], [-2.35, 0.95, 0.09, 3], [-2.62, -0.25, 0.1, 4], [0.35, 1.72, 0.14, 5],
  [-0.9, 1.75, 0.1, 6], [1.62, 1.18, 0.11, 7], [2.32, -0.72, 0.13, 8], [-0.4, 1.32, 0.05, 9], [0.9, 1.05, 0.04, 10],
];
export function makeRocks(mat) {
  const geos = [];
  for (const [x, z, r, seed] of ROCKS) {
    const rand = rng(seed * 13);
    const g = new THREE.DodecahedronGeometry(r, 0);
    const p = g.attributes.position;
    for (let i = 0; i < p.count; i++) {
      p.setXYZ(i, p.getX(i) * (0.9 + rand() * 0.25), p.getY(i) * (0.62 + rand() * 0.15), p.getZ(i) * (0.9 + rand() * 0.25));
    }
    g.computeVertexNormals();
    geos.push(at(paint(g, '#a7a39a', '#6f6d68', 0.1, seed), x, Math.max(terrainH(x, z), SEA_Y - 0.05) + r * 0.25, z,
      rand() * 3));
  }
  return mesh(geos, mat.solid);
}

// ---------------------------------------------------------------- the pieces
// The flowering tree with its round bench and an open book (the first challenge met).
export function makeTree(mat) {
  const group = place(new THREE.Group(), SPOTS.tree);
  const rand = rng(41);
  const wood = [];
  wood.push(paint(new THREE.CylinderGeometry(0.1, 0.2, 0.62, 9), '#9a6a3a', '#6e4522', 0.12, 2).translate(0, 0.31, 0));
  for (let i = 0; i < 3; i++) {
    const a = i * 2.1 + 0.5;
    const root = paint(new THREE.ConeGeometry(0.07, 0.3, 4), '#8a5c30', '#6e4522', 0.1, i + 40);
    root.rotateZ(-1.05);
    root.rotateY(-a);
    wood.push(root.translate(Math.cos(a) * 0.17, 0.05, Math.sin(a) * 0.17));
  }
  for (let i = 0; i < 4; i++) {
    const a = i * 1.6 + 0.4;
    const g = paint(new THREE.CylinderGeometry(0.03, 0.06, 0.38, 6), '#9a6a3a', '#7b5029', 0.1, i + 5);
    g.translate(0, 0.19, 0);
    at(g, 0, 0.5, 0, a, 0, 0.75);
    wood.push(g);
  }
  // The round bench around the trunk, on short legs.
  const ring = new THREE.CylinderGeometry(0.4, 0.4, 0.05, 28, 1, false);
  wood.push(paint(ring, '#c9864a', null, 0.06, 9).translate(0, 0.15, 0));
  for (let i = 0; i < 6; i++) {
    const a = (i / 6) * Math.PI * 2;
    wood.push(box(0.04, 0.13, 0.04, '#8c5a30', Math.cos(a) * 0.34, 0.065, Math.sin(a) * 0.34, 0, i));
  }
  group.add(mesh(wood, mat.solid));
  // The crown: leafy balls in a dome, sprinkled with white and red flowers.
  const crown = [];
  const greens = ['#9cd14a', '#b6dc52', '#7fbe3e', '#c8e35c'];
  // Eleven lumps, deep into each other, so it reads as one mass with bumps (Gaudí): each one welded and jittered by a
  // tenth of its radius, so it shows no cracks.
  for (let i = 0; i < 11; i++) {
    const ring = i === 0 ? 0 : i < 6 ? 1 : 2;
    const a = ((ring === 1 ? i - 1 : i - 6) / 5) * Math.PI * 2 + ring * 0.6;
    const d = ring === 0 ? 0 : ring === 1 ? 0.16 : 0.3;
    const r = ring === 0 ? 0.34 : ring === 1 ? 0.27 + rand() * 0.04 : 0.21 + rand() * 0.04;
    const y = ring === 0 ? 1.02 : ring === 1 ? 1.0 + rand() * 0.08 : 0.84 + rand() * 0.08;
    let lump = new THREE.IcosahedronGeometry(r, 1);
    lump.deleteAttribute('normal');
    lump.deleteAttribute('uv');
    lump = mergeVertices(lump);
    const p = lump.attributes.position;
    for (let v = 0; v < p.count; v++) {
      const k = 1 + (rand() - 0.5) * 0.2;
      p.setXYZ(v, p.getX(v) * k, p.getY(v) * k, p.getZ(v) * k);
    }
    lump.computeVertexNormals();
    crown.push(at(paint(lump, greens[i % 4], '#5c9a2e', 0.16, i + 20), Math.cos(a) * d, y, Math.sin(a) * d));
  }
  for (let i = 0; i < 40; i++) {
    const a = rand() * Math.PI * 2, e = 0.15 + rand() * 1.15;
    const r = 0.5 + rand() * 0.04;
    crown.push(at(paint(new THREE.IcosahedronGeometry(0.045, 0), i % 3 ? '#fff9ef' : '#e0473a'),
      Math.cos(a) * Math.cos(e) * r, 0.88 + Math.sin(e) * r * 0.6, Math.sin(a) * Math.cos(e) * r));
  }
  group.add(mesh(crown, mat.solid));
  // A cushion and an open book on the bench, at the front.
  const book = [];
  book.push(box(0.12, 0.05, 0.1, '#79b7c7', -0.18, 0.2, 0.3, 0.3, 31));
  book.push(box(0.1, 0.012, 0.13, '#fbf6ec', 0.05, 0.19, 0.36, 0.15, 32, 0, 0.12));
  book.push(box(0.1, 0.012, 0.13, '#fbf6ec', 0.15, 0.19, 0.34, 0.15, 33, 0, -0.12));
  group.add(mesh(book, mat.solid));
  return group;
}

// The reading hut: open at the front, shelves of books, a warm lamp and a cushion (the second challenge).
export function makeHut(mat) {
  const group = place(new THREE.Group(), SPOTS.hut);
  group.rotation.y = -0.35;
  const rand = rng(57);
  const wood = [];
  wood.push(box(0.66, 0.06, 0.5, '#b5773d', 0, 0.03, 0, 0, 1));
  wood.push(box(0.66, 0.5, 0.04, '#6e4220', 0, 0.31, -0.23, 0, 2));
  wood.push(box(0.04, 0.5, 0.5, '#a0652f', -0.31, 0.31, 0, 0, 3));
  wood.push(box(0.04, 0.5, 0.5, '#a0652f', 0.31, 0.31, 0, 0, 4));
  for (const y of [0.18, 0.32, 0.46]) {
    wood.push(box(0.58, 0.018, 0.1, '#7a4a22', 0, y, -0.17, 0, Math.round(y * 100)));
  }
  // The roof: two pitched halves of shingles, the ridge running from the open front to the back, the gable closed
  // at the back.
  // Three rows of shingles on each side, stepped, steeper, with an eave of 0.08 at the front (Gaudí).
  for (const side of [-1, 1]) {
    for (let row = 0; row < 3; row++) {
      const along = 0.07 + row * 0.11;
      const x = side * along * Math.cos(0.75), y = 0.84 - along * Math.sin(0.75) - row * 0.012;
      const tone = side < 0 ? ['#d39352', '#c98b48', '#bd7f40'][row] : ['#b97a3c', '#ad7036', '#a0652f'][row];
      wood.push(box(0.15, 0.03, 0.76, tone, x, y, 0.06, 0, 5 + row + (side > 0 ? 3 : 0), 0, -side * 0.75));
    }
  }
  wood.push(box(0.045, 0.045, 0.78, '#7a4a22', 0, 0.86, 0.06, 0, 12));
  const gable = new THREE.Shape([new THREE.Vector2(-0.33, 0), new THREE.Vector2(0.33, 0), new THREE.Vector2(0, 0.3)]);
  wood.push(paint(new THREE.ExtrudeGeometry(gable, { depth: 0.035, bevelEnabled: false }), '#a0652f', null, 0.06, 8)
    .translate(0, 0.56, -0.25));
  group.add(mesh(wood, mat.solid));
  const books = [];
  const colours = ['#c8443a', '#2f6fc0', '#3f9b4f', '#f0b43c', '#7b4fa8', '#e2654c', '#17574c'];
  for (const y of [0.18, 0.32, 0.46]) {
    let x = -0.27;
    while (x < 0.25) {
      const w = 0.025 + rand() * 0.02, h = 0.08 + rand() * 0.04;
      books.push(box(w, h, 0.07, colours[Math.floor(rand() * colours.length)], x + w / 2, y + 0.009 + h / 2, -0.17, 0,
        Math.floor(rand() * 999)));
      x += w + 0.004;
    }
  }
  books.push(box(0.2, 0.06, 0.16, '#e2654c', 0.08, 0.09, 0.08, 0.2, 61));
  books.push(paint(new THREE.CylinderGeometry(0.012, 0.03, 0.12, 8), '#6a4a2a').translate(-0.16, 0.12, 0.02));
  group.add(mesh(books, mat.solid));
  const lamp = new THREE.Mesh(new THREE.IcosahedronGeometry(0.035, 1), mat.glow);
  lamp.position.set(-0.16, 0.2, 0.02);
  group.add(lamp, halo(mat, -0.16, 0.2, 0.02, 0.32));
  return group;
}

// The garden: a raised bed of planks with cabbages and a border of flowers (the third challenge).
export function makeGarden(mat) {
  const group = place(new THREE.Group(), SPOTS.garden);
  group.rotation.y = 0.18;
  const rand = rng(73);
  const geos = [];
  const W = 0.86, D = 0.56;
  geos.push(box(W, 0.07, 0.04, '#b5773d', 0, 0.04, -D / 2, 0, 1));
  geos.push(box(W, 0.07, 0.04, '#a8692f', 0, 0.04, D / 2, 0, 2));
  geos.push(box(0.04, 0.07, D, '#a8692f', -W / 2, 0.04, 0, 0, 3));
  geos.push(box(0.04, 0.07, D, '#b5773d', W / 2, 0.04, 0, 0, 4));
  geos.push(box(0.04, 0.07, D, '#b5773d', 0, 0.04, 0, 0, 5));
  geos.push(box(W - 0.04, 0.05, D - 0.04, '#5b3a22', 0, 0.035, 0, 0, 6));
  for (let i = 0; i < 4; i++) {
    for (let j = 0; j < 3; j++) {
      const x = -W / 2 + 0.12 + i * 0.205, z = -D / 2 + 0.12 + j * 0.16;
      geos.push(at(paint(new THREE.IcosahedronGeometry(0.055, 1), '#86c24a', '#3f7d27', 0.12, i * 3 + j),
        x + (rand() - 0.5) * 0.03, 0.09, z));
    }
  }
  const petals = ['#f46b8a', '#ffd23f', '#ffffff', '#e0473a', '#ff9ec0'];
  for (let i = 0; i < 44; i++) {
    const t = i / 44, side = Math.floor(t * 4), f = (t * 4) % 1;
    const x = side % 2 === 0 ? -W / 2 + f * W : (side === 1 ? W / 2 : -W / 2);
    const z = side % 2 === 0 ? (side === 0 ? -D / 2 : D / 2) : -D / 2 + f * D;
    geos.push(at(paint(new THREE.IcosahedronGeometry(0.035, 0), '#4c8a2a'), x + (rand() - 0.5) * 0.05, 0.08, z +
      (rand() - 0.5) * 0.05));
    geos.push(at(paint(new THREE.IcosahedronGeometry(0.022, 0), petals[i % petals.length]), x + (rand() - 0.5) * 0.04,
      0.12, z + (rand() - 0.5) * 0.04));
  }
  group.add(mesh(geos, mat.solid));
  return group;
}

// The lighthouse of books on its rocks, with its lamp (a challenge of everyone in the class).
export function makeLighthouse(mat) {
  const group = place(new THREE.Group(), SPOTS.lighthouse);
  group.position.y = SEA_Y + 0.02;
  group.userData.size = 1.35;
  const geos = [];
  const rand = rng(91);
  for (let i = 0; i < 5; i++) {
    const a = (i / 5) * Math.PI * 2;
    const g = new THREE.DodecahedronGeometry(0.08 + rand() * 0.04, 0);
    geos.push(at(paint(g, '#9b978e', '#66635e', 0.1, i + 1), Math.cos(a) * 0.13, 0.02, Math.sin(a) * 0.1, rand() * 3));
  }
  const colours = ['#c8443a', '#2f6fc0', '#3f9b4f', '#f0b43c', '#e2654c', '#17574c', '#7b4fa8'];
  let y = 0.06;
  for (let i = 0; i < 7; i++) {
    const w = 0.22 - i * 0.015, h = 0.05 + rand() * 0.012;
    geos.push(box(w, h, w * 0.8, colours[i], (rand() - 0.5) * 0.02, y + h / 2, 0, (rand() - 0.5) * 0.4, i + 11));
    geos.push(box(w * 0.96, h * 0.7, w * 0.8 + 0.006, '#fbf6ec', (rand() - 0.5) * 0.01, y + h / 2, 0, 0, i + 21));
    y += h;
  }
  geos.push(paint(new THREE.CylinderGeometry(0.07, 0.08, 0.025, 12), '#6a4a2a').translate(0, y + 0.012, 0));
  geos.push(paint(new THREE.ConeGeometry(0.085, 0.1, 12), '#c8443a').translate(0, y + 0.16, 0));
  geos.push(box(0.004, 0.12, 0.004, '#6a4a2a', 0, y + 0.26, 0, 0, 40));
  geos.push(box(0.05, 0.03, 0.004, '#c8443a', 0.026, y + 0.3, 0, 0, 41));
  group.add(mesh(geos, mat.solid));
  const lamp = new THREE.Mesh(new THREE.CylinderGeometry(0.055, 0.055, 0.075, 12), mat.glow);
  lamp.position.y = y + 0.07;
  group.add(lamp, halo(mat, 0, y + 0.07, 0, 0.42));
  return group;
}

// The dock and the boat bringing the book the class asked for, wrapped as a present.
export function makeDock(mat) {
  const group = new THREE.Group();
  const D = SPOTS.dock, B = SPOTS.boat;
  const dock = new THREE.Group();
  dock.position.set(D.x, 0, D.z);
  dock.rotation.y = D.yaw;
  const geos = [];
  const deck = SEA_Y + 0.11;
  for (let i = 0; i < 9; i++) {
    geos.push(box(0.1, 0.025, 0.36, i % 2 ? '#b98146' : '#a8703a', 0.05 + i * 0.105, deck, 0, 0, i + 1));
  }
  for (const [x, z] of [[0.05, -0.17], [0.05, 0.17], [D.length - 0.05, -0.17], [D.length - 0.05, 0.17]]) {
    geos.push(paint(new THREE.CylinderGeometry(0.03, 0.03, 0.28, 8), '#8c5a30', '#5e3a1c', 0.1, 3).translate(x, deck - 0.08, z));
  }
  geos.push(paint(new THREE.TorusGeometry(0.045, 0.012, 6, 14), '#d9c08a').rotateX(Math.PI / 2).translate(0.62, deck + 0.02, 0.1));
  dock.add(mesh(geos, mat.solid));
  const lantern = new THREE.Mesh(new THREE.IcosahedronGeometry(0.03, 1), mat.glow);
  lantern.position.set(D.length - 0.05, deck + 0.1, -0.17);
  dock.add(lantern, halo(mat, D.length - 0.05, deck + 0.1, -0.17, 0.26));
  dock.add(mesh([paint(new THREE.CylinderGeometry(0.018, 0.024, 0.06, 8), '#3a2a1a').translate(D.length - 0.05, deck + 0.05, -0.17)],
    mat.solid));
  group.add(dock);
  // The boat: a tapered hull, blue below and white above, and the present.
  const boat = new THREE.Group();
  boat.position.set(B.x, SEA_Y - 0.01, B.z);
  boat.rotation.y = B.yaw;
  const hull = new THREE.BoxGeometry(0.62, 0.15, 0.26, 10, 2, 2);
  const p = hull.attributes.position;
  for (let i = 0; i < p.count; i++) {
    const x = p.getX(i), yv = p.getY(i);
    const taper = 1 - Math.pow(Math.abs(x) / 0.31, 3) * 0.85;
    const keel = yv < 0 ? 0.55 : 1;
    p.setZ(i, p.getZ(i) * taper * keel);
    p.setY(i, yv + Math.pow(Math.abs(x) / 0.31, 2) * 0.035);
  }
  hull.computeVertexNormals();
  boat.add(mesh([paint(hull, '#fbf6ec', '#2f5fa8', 0.04, 7).translate(0, 0.06, 0),
    box(0.5, 0.02, 0.17, '#a8703a', 0, 0.115, 0, 0, 8),
    box(0.15, 0.11, 0.13, '#d63a2f', 0.05, 0.18, 0, 0.2, 9),
    box(0.03, 0.115, 0.135, '#ffd23f', 0.05, 0.18, 0, 0.2, 10),
    box(0.155, 0.115, 0.03, '#ffd23f', 0.05, 0.18, 0, 0.2, 11)], mat.solid));
  group.add(boat);
  group.userData.boat = boat;
  return group;
}

// Gold and coral motes that rise and fade where a piece grows.
export function makeSparkles(count = 64) {
  const geo = new THREE.BufferGeometry();
  geo.setAttribute('position', new THREE.BufferAttribute(new Float32Array(count * 3), 3));
  const colours = new Float32Array(count * 3);
  const palette = [new THREE.Color('#ffd23f'), new THREE.Color('#e2654c'), new THREE.Color('#fff7e8')];
  for (let i = 0; i < count; i++) {
    colours.set(palette[i % 3].toArray(), i * 3);
  }
  geo.setAttribute('color', new THREE.BufferAttribute(colours, 3));
  const material = new THREE.PointsMaterial({ size: 0.11, vertexColors: true, transparent: true, depthWrite: false });
  const points = new THREE.Points(geo, material);
  points.visible = false;
  return points;
}
