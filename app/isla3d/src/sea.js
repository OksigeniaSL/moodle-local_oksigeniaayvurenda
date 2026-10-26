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

// Animated sea (from the island test of the incubator): one big plane with a custom shader. Colour by distance to the shore (light shallows
// over the sand, turquoise, deep teal), moving caustic cells, a foam ring at the shoreline,
// lacy foam and small rings around rocks and posts that stand in the water.
import * as THREE from 'three';
import { SHORE_GLSL } from './shape.js';

const MAX_RINGS = 20;

const vertexShader = /* glsl */`
varying vec3 vW;
void main() {
  vec4 w = modelMatrix * vec4(position, 1.0);
  vW = w.xyz;
  gl_Position = projectionMatrix * viewMatrix * w;
}`;

const fragmentShader = /* glsl */`
uniform float uTime;
uniform vec3 uRings[${MAX_RINGS}];
varying vec3 vW;
${SHORE_GLSL}
vec2 hash22(vec2 p) {
  p = vec2(dot(p, vec2(127.1, 311.7)), dot(p, vec2(269.5, 183.3)));
  return fract(sin(p) * 43758.5453);
}
float hash12(vec2 p) { return fract(sin(dot(p, vec2(12.9898, 78.233))) * 43758.5453); }
float vnoise(vec2 p) {
  vec2 i = floor(p), f = fract(p);
  vec2 u = f * f * (3.0 - 2.0 * f);
  float a = hash12(i), b = hash12(i + vec2(1.0, 0.0)), c = hash12(i + vec2(0.0, 1.0)), d = hash12(i + vec2(1.0, 1.0));
  return mix(mix(a, b, u.x), mix(c, d, u.x), u.y);
}
// x: distance to the nearest cell centre, y: distance to the cell border (F2 - F1), z: random per cell
vec3 voro(vec2 p, float t) {
  vec2 n = floor(p), f = fract(p);
  float f1 = 8.0, f2 = 8.0;
  vec2 id = vec2(0.0);
  for (int j = -1; j <= 1; j++) for (int i = -1; i <= 1; i++) {
    vec2 g = vec2(float(i), float(j));
    vec2 o = hash22(n + g);
    o = 0.5 + 0.42 * sin(t + 6.2831 * o);
    vec2 r = g + o - f;
    float d = dot(r, r);
    if (d < f1) { f2 = f1; f1 = d; id = n + g; } else if (d < f2) { f2 = d; }
  }
  f1 = sqrt(f1); f2 = sqrt(f2);
  return vec3(f1, f2 - f1, hash12(id));
}
float shallowK(float sd) { return 1.0 - smoothstep(0.0, 0.9, sd); }
void main() {
  vec2 p = vW.xz;
  float t = uTime;
  float sd = shoreSD(p);
  float ang = atan(p.y, p.x);

  vec2 warp = vec2(vnoise(p * 0.9 + t * 0.04), vnoise(p * 0.9 + 5.2 - t * 0.04)) * 1.2
             + vec2(vnoise(p * 4.0 + 1.7 + t * 0.1), vnoise(p * 4.0 + 9.1 - t * 0.1)) * 0.45;
  vec2 q = p * 5.8 + warp * 1.2;
  vec3 v1 = voro(q, t * 0.35);
  vec3 v2 = voro(q * 0.35 + 3.1, t * 0.22 + 1.0);
  // dappled water: darker rounded blobs separated by soft light gaps
  float gap = 1.0 - smoothstep(0.0, mix(0.34, 0.24, shallowK(sd)), v1.y);
  gap *= gap * (3.0 - 2.0 * gap);
  float thin = 1.0 - smoothstep(0.0, 0.05, v1.y);
  float gap2 = 1.0 - smoothstep(0.0, 0.22, v2.y);
  float shallow = shallowK(sd);

  vec3 cShallow = vec3(0.44, 0.86, 0.75);
  vec3 cMid = vec3(0.09, 0.75, 0.72);
  vec3 cDeep = vec3(0.02, 0.65, 0.70);
  vec3 base = mix(cShallow, cMid, smoothstep(0.05, 0.95, sd));
  base = mix(base, cDeep, smoothstep(0.7, 2.6, sd));
  base *= 0.95 + 0.08 * vnoise(p * 0.45 + t * 0.03);
  base *= mix(0.93, 1.07, smoothstep(-3.5, 3.0, p.y + 0.35 * p.x));
  vec3 blob = base * (0.8 + 0.14 * v1.z + 0.05 * smoothstep(0.0, 0.7, v1.x)) * (0.94 + 0.1 * v2.z);
  vec3 light = mix(base * 1.12 + vec3(0.04, 0.06, 0.03), vec3(0.62, 0.93, 0.80), 0.55 * shallow);
  vec3 col = mix(blob, light, gap * mix(0.2, 0.85, shallow));
  col = mix(col, col * 1.06 + 0.02, gap2 * 0.4);
  col += vec3(0.12, 0.13, 0.09) * thin * mix(0.03, 0.65, shallow);

  float a = mix(0.6, 1.0, smoothstep(0.0, 0.7, sd));
  a = max(a, (gap * 0.45 + thin * 0.3) * shallow);

  // foam ring at the shoreline, breathing in and out
  float wob = 0.025 * sin(ang * 13.0 + t * 1.1) + 0.02 * sin(ang * 29.0 - t * 0.7) + 0.05 * (vnoise(p * 6.0 + t * 0.2) - 0.5);
  float surge = 0.03 * sin(t * 0.9 + ang * 2.0);
  float fs = sd + wob - surge;
  float foam = (1.0 - smoothstep(0.03, 0.075, fs)) * smoothstep(-0.1, -0.065, fs) * (0.82 + 0.18 * thin);
  float lace = (1.0 - smoothstep(0.08, 0.3, fs)) * smoothstep(0.02, 0.06, fs) * max(thin * 1.25, gap * 0.8);
  float ph = fract(t * 0.11);
  float ring = (1.0 - smoothstep(0.0, 0.025, abs(fs - (0.12 + ph * 0.35)))) * (1.0 - ph) * 0.5
             * smoothstep(0.35, 0.65, vnoise(p * 4.0 + ph * 3.0));
  float rf = 0.0;
  for (int i = 0; i < ${MAX_RINGS}; i++) {
    vec3 r = uRings[i];
    if (r.z <= 0.0) continue;
    vec2 dp = p - r.xy;
    float d = length(dp) - r.z;
    float w = 0.018 + 0.012 * sin(atan(dp.y, dp.x) * 7.0 + t * 2.0);
    rf = max(rf, (1.0 - smoothstep(w, w + 0.03, d)) * smoothstep(-0.03, 0.0, d));
  }
  float f = clamp(max(max(foam, lace), max(ring, rf * 0.85)), 0.0, 1.0);
  col = mix(col, vec3(1.0, 0.99, 0.95), f);
  a = max(a, f * 0.95);

  float g = vnoise(p * 17.0 + vec2(t * 0.6, -t * 0.4)) * vnoise(p * 23.0 - vec2(t * 0.5, t * 0.3));
  col += vec3(1.0, 0.95, 0.8) * smoothstep(0.6, 0.8, g) * 0.06 * smoothstep(0.5, 1.5, sd);

  gl_FragColor = vec4(col, a);
}`;

export function makeSea(rings) {
  const arr = [];
  for (let i = 0; i < MAX_RINGS; i++) {
    const r = rings[i];
    arr.push(r ? new THREE.Vector3(r.x, r.z, r.r) : new THREE.Vector3(0, 0, -1));
  }
  const uniforms = { uTime: { value: 0 }, uRings: { value: arr } };
  const material = new THREE.ShaderMaterial({ uniforms, vertexShader, fragmentShader, transparent: true });
  const geo = new THREE.PlaneGeometry(120, 120, 1, 1);
  geo.rotateX(-Math.PI / 2);
  const mesh = new THREE.Mesh(geo, material);
  mesh.position.y = 0.02;
  mesh.renderOrder = 1;
  return { mesh, uniforms };
}
