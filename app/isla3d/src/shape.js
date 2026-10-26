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

// The class island of Ayvurenda: its shoreline and its ground, shared by the terrain, the sea shader and the
// placement of the pieces. World frame: x to the right of the screen, -z away from the camera, y up.
// The shape follows Gaudí's island (pix/isla/base.webp): a wide oval with a soft wobble.

export function rng(seed) {
  let a = seed >>> 0;
  return () => {
    a = (a + 0x6d2b79f5) >>> 0;
    let t = a;
    t = Math.imul(t ^ (t >>> 15), t | 1);
    t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

function hash2(ix, iy) {
  let h = Math.imul(ix | 0, 374761393) + Math.imul(iy | 0, 668265263);
  h = Math.imul(h ^ (h >>> 13), 1274126177);
  h ^= h >>> 16;
  return (h >>> 0) / 4294967295;
}

export function vnoise(x, y) {
  const ix = Math.floor(x), iy = Math.floor(y);
  const fx = x - ix, fy = y - iy;
  const ux = fx * fx * (3 - 2 * fx), uy = fy * fy * (3 - 2 * fy);
  const a = hash2(ix, iy), b = hash2(ix + 1, iy), c = hash2(ix, iy + 1), d = hash2(ix + 1, iy + 1);
  return a + (b - a) * ux + (c - a) * uy + (a - b - c + d) * ux * uy;
}

export const smooth = (e0, e1, x) => {
  const t = Math.min(1, Math.max(0, (x - e0) / (e1 - e0)));
  return t * t * (3 - 2 * t);
};

// The oval: half widths along x and z, and a wobble so it never looks drawn with a compass.
const RX = 2.55, RZ = 1.62;
export function shoreR(ang) {
  const c = Math.cos(ang), s = Math.sin(ang);
  const r = (RX * RZ) / Math.sqrt((RZ * c) * (RZ * c) + (RX * s) * (RX * s));
  return r * (1 + 0.035 * Math.sin(3 * ang + 0.7) + 0.025 * Math.cos(5 * ang + 1.9) + 0.012 * Math.sin(9 * ang + 0.3));
}
// Signed distance to the shore, negative on land (a radial approximation, good enough for colour and placement).
export function shoreSD(x, z) {
  return Math.hypot(x, z) - shoreR(Math.atan2(-z, x));
}

export const SHORE_GLSL = /* glsl */`
float shoreR(float a) {
  float c = cos(a), s = sin(a);
  float r = ${(RX * RZ).toFixed(4)} / sqrt(${(RZ * RZ).toFixed(4)} * c * c + ${(RX * RX).toFixed(4)} * s * s);
  return r * (1.0 + 0.035 * sin(3.0 * a + 0.7) + 0.025 * cos(5.0 * a + 1.9) + 0.012 * sin(9.0 * a + 0.3));
}
float shoreSD(vec2 p) { return length(p) - shoreR(atan(-p.y, p.x)); }
`;

// The sea's surface; the sand rises gently from it and drops under it at the shore.
export const SEA_Y = 0.02;
export function terrainH(x, z) {
  const sd = shoreSD(x, z);
  if (sd < 0) {
    // A soft dome (a quarter of a unit in the middle) and a low mound under the tree (Gaudí).
    const mound = 0.07 * Math.exp(-((x - MOUND.x) ** 2 + (z - MOUND.z) ** 2) / 0.22);
    return SEA_Y + 0.25 * smooth(0, 1.5, -sd) + mound * smooth(0, 0.4, -sd)
      + 0.012 * (vnoise(x * 2.3, z * 2.3) - 0.5) * smooth(0, 0.5, -sd);
  }
  return SEA_Y - 0.16 * smooth(0, 0.55, sd);
}
const MOUND = { x: -0.05, z: -0.88 };

// Where everything stands, after Gaudí's layers: the palm and the rocks of the base, and the pieces the class wins.
export const SPOTS = {
  palm: { x: -1.45, z: -0.62 },
  tree: { x: -0.05, z: -0.88 },
  hut: { x: 1.38, z: -0.12 },
  garden: { x: -0.55, z: 0.72 },
  lighthouse: { x: -2.42, z: 0.32 },
  dock: { x: 1.9, z: 0.55, yaw: -0.3, length: 0.85 },
  boat: { x: 2.72, z: 1.02, yaw: -0.3 },
};
