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

// The ground of the island: a light mesh and a painted texture with the sand, the wet rim, the grass and the soft
// shadows of what stands on it, baked into the picture (no shadow maps: an old Chromebook must keep up). The texture is
// painted again when a piece arrives, with its grass and its shadow.
import * as THREE from 'three';
import { shoreSD, terrainH, vnoise, rng, smooth, SPOTS } from './shape.js';

export const BOUNDS = { x0: -3.2, x1: 3.2, z0: -2.15, z1: 2.15 };
const W = 1024;
const H = Math.round(W * (BOUNDS.z1 - BOUNDS.z0) / (BOUNDS.x1 - BOUNDS.x0));
const px = (x) => (x - BOUNDS.x0) / (BOUNDS.x1 - BOUNDS.x0) * W;
const pz = (z) => (z - BOUNDS.z0) / (BOUNDS.z1 - BOUNDS.z0) * H;
const scale = W / (BOUNDS.x1 - BOUNDS.x0);

// The sun comes from the back right, so shadows fall to the front left.
const SHADOW = { dx: -0.16, dz: 0.11 };

// What each piece leaves on the ground: grass around it, and its shadow.
const GROUND = {
  base: { grass: [[SPOTS.palm.x + 0.05, SPOTS.palm.z + 0.05, 0.42], [SPOTS.palm.x - 0.25, SPOTS.palm.z + 0.3, 0.22]],
    shadows: [[SPOTS.palm.x + 0.05, SPOTS.palm.z + 0.12, 0.34, 0.2]] },
  tree: { grass: [[SPOTS.tree.x, SPOTS.tree.z + 0.02, 0.62], [SPOTS.tree.x + 0.55, SPOTS.tree.z + 0.1, 0.25]],
    shadows: [[SPOTS.tree.x - 0.12, SPOTS.tree.z + 0.16, 0.62, 0.36]] },
  hut: { grass: [[SPOTS.hut.x + 0.32, SPOTS.hut.z - 0.1, 0.18]], shadows: [[SPOTS.hut.x - 0.1, SPOTS.hut.z + 0.18, 0.42, 0.26]] },
  garden: { grass: [], shadows: [[SPOTS.garden.x - 0.08, SPOTS.garden.z + 0.08, 0.6, 0.34]] },
  lighthouse: { grass: [], shadows: [[SPOTS.lighthouse.x - 0.1, SPOTS.lighthouse.z + 0.1, 0.22, 0.14]] },
  dock: { grass: [], shadows: [] },
};

function blob(ctx, x, z, r, colours, rand, n = 160) {
  for (let i = 0; i < n; i++) {
    const a = rand() * Math.PI * 2, d = Math.sqrt(rand()) * r;
    const bx = x + Math.cos(a) * d, bz = z + Math.sin(a) * d;
    const rr = (0.05 + rand() * 0.08) * scale * (1 - d / r * 0.6);
    ctx.fillStyle = colours[Math.floor(rand() * colours.length)];
    ctx.beginPath();
    ctx.ellipse(px(bx), pz(bz), rr, rr * 0.8, rand() * Math.PI, 0, Math.PI * 2);
    ctx.fill();
  }
}

function shadow(ctx, x, z, rx, rz, strength = 0.32) {
  const cx = px(x + SHADOW.dx), cz = pz(z + SHADOW.dz);
  const g = ctx.createRadialGradient(0, 0, 0, 0, 0, 1);
  g.addColorStop(0, `rgba(96, 62, 24, ${strength})`);
  g.addColorStop(0.65, `rgba(96, 62, 24, ${strength * 0.55})`);
  g.addColorStop(1, 'rgba(96, 62, 24, 0)');
  ctx.save();
  ctx.translate(cx, cz);
  ctx.scale(rx * scale, rz * scale);
  ctx.fillStyle = g;
  ctx.beginPath();
  ctx.arc(0, 0, 1, 0, Math.PI * 2);
  ctx.fill();
  ctx.restore();
}

// Sand from the wet rim to the dry middle, with a few pebbles; then the grass and shadows of what is there.
export function paintGround(canvas, pieces) {
  canvas.width = W;
  canvas.height = H;
  const ctx = canvas.getContext('2d');
  const img = ctx.createImageData(W, H);
  const wet = [222, 184, 118], dry = [252, 226, 160], deep = [182, 148, 96];
  for (let j = 0; j < H; j++) {
    for (let i = 0; i < W; i++) {
      const x = BOUNDS.x0 + (i + 0.5) / scale, z = BOUNDS.z0 + (j + 0.5) / scale;
      const sd = shoreSD(x, z);
      const k = smooth(-0.05, -0.55, sd);
      const n = vnoise(x * 6, z * 6) * 0.06 + vnoise(x * 23, z * 23) * 0.04 - 0.05;
      const c = sd > 0 ? deep : wet.map((w, m) => w + (dry[m] - w) * k);
      const o = (j * W + i) * 4;
      img.data[o] = Math.min(255, c[0] * (1 + n));
      img.data[o + 1] = Math.min(255, c[1] * (1 + n));
      img.data[o + 2] = Math.min(255, c[2] * (1 + n));
      img.data[o + 3] = 255;
    }
  }
  ctx.putImageData(img, 0, 0);
  const rand = rng(77);
  ctx.fillStyle = 'rgba(150, 118, 80, 0.55)';
  for (let i = 0; i < 70; i++) {
    const x = BOUNDS.x0 + rand() * (BOUNDS.x1 - BOUNDS.x0), z = BOUNDS.z0 + rand() * (BOUNDS.z1 - BOUNDS.z0);
    if (shoreSD(x, z) < -0.25) {
      ctx.beginPath();
      ctx.ellipse(px(x), pz(z), 2 + rand() * 3, 1.5 + rand() * 2, rand() * 3, 0, Math.PI * 2);
      ctx.fill();
    }
  }
  const greens = ['#5f9a2c', '#77b236', '#4c8526', '#8cc043'];
  for (const name of ['base', ...pieces]) {
    const ground = GROUND[name];
    if (!ground) {
      continue;
    }
    for (const [x, z, rx, rz] of ground.shadows) {
      shadow(ctx, x, z, rx, rz);
    }
    const r = rng(name.length * 31);
    for (const [x, z, rad] of ground.grass) {
      blob(ctx, x, z, rad, greens, r);
    }
  }
}

// The ground mesh: about 8,600 triangles, its heights from the shape of the island.
export function makeGround() {
  const { x0, x1, z0, z1 } = BOUNDS;
  const geo = new THREE.PlaneGeometry(x1 - x0, z1 - z0, 80, 54);
  geo.rotateX(-Math.PI / 2);
  const pos = geo.attributes.position, uv = geo.attributes.uv;
  for (let i = 0; i < pos.count; i++) {
    const x = pos.getX(i), z = pos.getZ(i);
    pos.setY(i, terrainH(x, z));
    uv.setXY(i, (x - x0) / (x1 - x0), 1 - (z - z0) / (z1 - z0));
  }
  geo.computeVertexNormals();
  const canvas = document.createElement('canvas');
  const texture = new THREE.CanvasTexture(canvas);
  texture.colorSpace = THREE.SRGBColorSpace;
  texture.anisotropy = 4;
  const mesh = new THREE.Mesh(geo, new THREE.MeshLambertMaterial({ map: texture }));
  return {
    mesh,
    paint(pieces) {
      paintGround(canvas, pieces);
      texture.needsUpdate = true;
    },
  };
}
