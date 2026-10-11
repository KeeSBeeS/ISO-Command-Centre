// bg_render.cjs - background art for the FINAL "C - Bold brand banner" signature band.
//
// Story (left -> right, the direction the oil flows):
//   faint oil-stream lines run through a dim, flat hexagonal filter mesh (text-safe zone),
//   then hit the CAPTURE FRONT at the far right, where amber contaminant particles are
//   trapped; past the front the mesh glows clean green and a quiet condition-monitoring
//   gauge arc reads "healthy".
//
// Text-safe mask: everything left of x = SAFE_X (1x px) is flattened: mesh at ~7 % opacity,
// no particles, no lit cells, no bright seams. The bright art lives only in x >= ~584.
// Edge fades: the LEFT and BOTTOM edges fade to EXACTLY the band bgcolor (#0D4B4A), and the
// last rows/columns are pure flat bgcolor, so a taller band (wrapped title, long-email row,
// VML growth) continues seamlessly in solid colour.
//
// Output: bg.svg (1x viewBox 640 x BAND_H, rendered at 2x) and a 1280 x 2*BAND_H PNG.
// render.sh then converts the PNG to public/images/signature/iiso-signature-bg.jpg (see README.md).
// Usage: node bg_render.cjs [--from-svg] [out.png]
//   (default)   regenerate bg.svg from this script, then render it
//   --from-svg  keep bg.svg as it is (e.g. after editing it by hand) and only render it
//   out.png     where the PNG is written (default: bg.png next to this script)
// Needs Node with the playwright package and its Chromium (NODE_PATH=/opt/node22/lib/node_modules here).
'use strict';
const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const OUT = __dirname;
const FROM_SVG = process.argv.includes('--from-svg');
const PNG_OUT = path.resolve(process.argv.slice(2).find((a) => !a.startsWith('--')) || path.join(OUT, 'bg.png'));
const W = 640, H = 132;             // display size of the band (1x) - keep in sync with the signature template (background-size 640px 132px)
const SCALE = 2;                    // bg.jpg is 2x
const BAND = '#0D4B4A';             // band bgcolor - the edges fade to exactly this
const SAFE_X = 574;                 // text column ends at x = 576; art is quiet left of here
const BRIGHT_X = 592;               // full-strength art from here to the right edge

let seed = 20261011;
function rnd() { seed = (seed * 1664525 + 1013904223) >>> 0; return seed / 4294967296; }
const f = (n) => Math.round(n * 100) / 100;
const clamp = (v, a, b) => Math.max(a, Math.min(b, v));
const smooth = (a, b, x) => { const t = clamp((x - a) / (b - a), 0, 1); return t * t * (3 - 2 * t); };

// ---- capture front = condition-monitoring gauge arc ------------------------------------
// One arc does both jobs: contaminants pile up on its outer (upstream) face, the inside is clean
// and glowing, and its ticks + coloured zones read as a gauge. Its centre sits on y = NEEDLE_Y, the
// y of the HTML accent "flow line" (signature template), so in the image version that live 1px line reads as
// the gauge needle pointing into the healthy zone. In the fallback it is simply a flow line.
const NEEDLE_Y = 70;
const GC = [680, NEEDLE_Y], GR = 84;            // leftmost point of the arc: x = 596
const dist = (x, y) => Math.hypot(x - GC[0], y - GC[1]);
const inside = (x, y) => GR - dist(x, y);       // >0 inside (clean), <0 outside (upstream)
const pol = (deg, r) => [GC[0] + r * Math.cos(deg * Math.PI / 180), GC[1] + r * Math.sin(deg * Math.PI / 180)];
const arc = (a0, a1, r) => { const p0 = pol(a0, r), p1 = pol(a1, r); return `M${f(p0[0])} ${f(p0[1])} A${r} ${r} 0 0 1 ${f(p1[0])} ${f(p1[1])}`; };

// ---- hex mesh (flat-top) ---------------------------------------------------------
const R = 9.5;
const HX = 1.5 * R, HY = Math.sqrt(3) * R;
function hexPts(cx, cy, r) {
  const p = [];
  for (let i = 0; i < 6; i++) { const a = Math.PI / 3 * i; p.push([cx + r * Math.cos(a), cy + r * Math.sin(a)]); }
  return p;
}
const hexD = (cx, cy, r) => 'M' + hexPts(cx, cy, r).map(q => f(q[0]) + ' ' + f(q[1])).join('L') + 'Z';
const cells = [];
for (let c = -1; c * HX < W + R * 2; c++) {
  for (let r = -1; r * HY < H + R * 2; r++) cells.push([c * HX, r * HY + (c % 2 ? HY / 2 : 0)]);
}

// Mesh opacity: flat and dim in the text-safe zone, ramps up only past SAFE_X.
function meshAlpha(x, y) {
  const ramp = smooth(SAFE_X, BRIGHT_X + 6, x);
  const quiet = 0.07;
  const d = inside(x, y);
  const bright = d < 0 ? 0.26 + 0.26 * Math.exp(-((d / 10) ** 2)) : 0.50 - 0.12 * clamp(d / 30, 0, 1);
  return quiet + (bright - quiet) * ramp;
}
let mesh = '';
for (const [cx, cy] of cells) mesh += `<path d="${hexD(cx, cy, R)}" stroke-opacity="${f(meshAlpha(cx, cy))}"/>`;

// lit (clean, glowing) cells inside the arc
let lit = '';
for (const [cx, cy] of cells) {
  if (cx < BRIGHT_X || inside(cx, cy) < 3) continue;
  if (rnd() < 0.45) lit += `<path d="${hexD(cx, cy, R - 1.3)}" fill="#8BDC65" fill-opacity="${f(0.07 + 0.14 * rnd())}"/>`;
}

// ---- trapped contaminants on the outer (upstream) face of the arc -----------------------
let trapped = '', halos = '';
const placed = [];
for (const [cx, cy] of cells) {
  for (const v of hexPts(cx, cy, R)) {
    const d = -inside(v[0], v[1]);               // distance upstream of the arc
    if (v[0] < SAFE_X + 9 || d < -1.5 || d > 15) continue;
    if (v[1] < 2 || v[1] > H - 22) continue;       // keep clear of the faded bottom
    if (Math.abs(v[1] - NEEDLE_Y) < 3) continue;   // keep the needle line clean
    if (placed.some(([px, py]) => Math.abs(px - v[0]) < 1 && Math.abs(py - v[1]) < 1)) continue;
    const near = Math.exp(-Math.max(d, 0) / 6);
    if (rnd() > 0.22 + 0.62 * near) continue;
    placed.push(v);
    const rr = f(0.7 + rnd() * 1.0 * near + 0.35);
    const amber = rnd() < 0.8;
    const col = amber ? '#F2B444' : '#D7F9B8';
    halos += `<circle cx="${f(v[0])}" cy="${f(v[1])}" r="${f(rr * 3.2)}" fill="${col}" fill-opacity="${f(0.10 + 0.10 * near)}"/>`;
    trapped += `<circle cx="${f(v[0])}" cy="${f(v[1])}" r="${rr}" fill="${col}" fill-opacity="${f(0.6 + 0.35 * near)}"/>`;
  }
}

// ---- oil-stream flow lines (faint across the band, brighter only past SAFE_X) ----------
const flows = [
  { d: `M-10 ${H * 0.80} C 160 ${H * 0.96}, 340 ${H * 0.36}, 520 ${H * 0.46} S 610 ${H * 0.36}, 660 ${H * 0.30}`, w: 1.2 },
  { d: `M-10 ${H * 0.52} C 150 ${H * 0.66}, 330 ${H * 0.10}, 520 ${H * 0.20} S 620 ${H * 0.10}, 660 ${H * 0.06}`, w: 0.8 },
  { d: `M-10 ${H * 1.02} C 190 ${H * 1.08}, 360 ${H * 0.64}, 540 ${H * 0.70} S 620 ${H * 0.62}, 660 ${H * 0.60}`, w: 0.7 },
  { d: `M-10 ${H * 0.30} C 130 ${H * 0.40}, 320 ${H * -0.06}, 500 ${H * 0.04} S 620 ${H * -0.04}, 660 ${H * -0.08}`, w: 0.6 },
];
const flowSvg = flows.map(fl => `<path d="${fl.d}" fill="none" stroke="url(#flowFade)" stroke-width="${fl.w}" stroke-linecap="round"/>`).join('');

// ---- gauge furniture on the arc: ticks, healthy (lime) and warning (amber) zones ---------
let ticks = '';
for (let i = 0; i <= 8; i++) {
  const a = 146 + i * 8.5, major = i % 2 === 0;
  const p0 = pol(a, GR - (major ? 7 : 4)), p1 = pol(a, GR - 1.2);
  ticks += `<path d="M${f(p0[0])} ${f(p0[1])}L${f(p1[0])} ${f(p1[1])}" stroke-opacity="${major ? 0.62 : 0.38}"/>`;
}
const gauge = `<g fill="none" stroke-linecap="round">
  <path d="${arc(128, 232, GR)}" stroke="#C3F4DE" stroke-opacity="0.42" stroke-width="0.9"/>
  <path d="${arc(169, 191, GR - 10)}" stroke="#8BDC65" stroke-opacity="0.85" stroke-width="2"/>
  <path d="${arc(203, 214, GR - 10)}" stroke="#F2B444" stroke-opacity="0.7" stroke-width="2"/>
  <g stroke="#E3FBEF" stroke-width="0.8">${ticks}</g>
</g>`;

const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${W * SCALE}" height="${H * SCALE}" viewBox="0 0 ${W} ${H}">
  <defs>
    <linearGradient id="tone" x1="0" y1="0" x2="${W}" y2="0" gradientUnits="userSpaceOnUse">
      <stop offset="0" stop-color="#0B4142"/>
      <stop offset="0.55" stop-color="#0B4344"/>
      <stop offset="${f(548 / W)}" stop-color="#0C4746"/>
      <stop offset="${f(600 / W)}" stop-color="#0F5C54"/>
      <stop offset="1" stop-color="#127060"/>
    </linearGradient>
    <radialGradient id="glowR" cx="652" cy="${NEEDLE_Y - 18}" r="84" gradientUnits="userSpaceOnUse">
      <stop offset="0" stop-color="#2BC08C" stop-opacity="0.60"/>
      <stop offset="0.55" stop-color="#17976F" stop-opacity="0.18"/>
      <stop offset="1" stop-color="#138B70" stop-opacity="0"/>
    </radialGradient>
    <radialGradient id="glowL" cx="40" cy="-30" r="190" gradientUnits="userSpaceOnUse">
      <stop offset="0" stop-color="#139072" stop-opacity="0.32"/>
      <stop offset="1" stop-color="#138B70" stop-opacity="0"/>
    </radialGradient>
    <linearGradient id="flowFade" x1="0" y1="0" x2="${W}" y2="0" gradientUnits="userSpaceOnUse">
      <stop offset="0" stop-color="#A4EDCB" stop-opacity="0.05"/>
      <stop offset="${f(SAFE_X / W)}" stop-color="#A4EDCB" stop-opacity="0.08"/>
      <stop offset="${f(BRIGHT_X / W)}" stop-color="#A4EDCB" stop-opacity="0.32"/>
      <stop offset="1" stop-color="#A4EDCB" stop-opacity="0.22"/>
    </linearGradient>
    <!-- edge fades: alpha 0 at the very left / bottom so the art meets the bgcolor exactly -->
    <linearGradient id="fadeL" x1="0" y1="0" x2="${W}" y2="0" gradientUnits="userSpaceOnUse">
      <stop offset="0" stop-color="#fff" stop-opacity="0"/>
      <stop offset="${f(8 / W)}" stop-color="#fff" stop-opacity="0"/>
      <stop offset="${f(44 / W)}" stop-color="#fff" stop-opacity="1"/>
      <stop offset="1" stop-color="#fff" stop-opacity="1"/>
    </linearGradient>
    <linearGradient id="fadeB" x1="0" y1="0" x2="0" y2="${H}" gradientUnits="userSpaceOnUse">
      <stop offset="0" stop-color="#fff" stop-opacity="1"/>
      <stop offset="${f(98 / H)}" stop-color="#fff" stop-opacity="1"/>
      <stop offset="${f(124 / H)}" stop-color="#fff" stop-opacity="0"/>
      <stop offset="1" stop-color="#fff" stop-opacity="0"/>
    </linearGradient>
    <mask id="mL" maskUnits="userSpaceOnUse" x="0" y="0" width="${W}" height="${H}"><rect width="${W}" height="${H}" fill="url(#fadeL)"/></mask>
    <mask id="mB" maskUnits="userSpaceOnUse" x="0" y="0" width="${W}" height="${H}"><rect width="${W}" height="${H}" fill="url(#fadeB)"/></mask>
    <clipPath id="brightZone"><rect x="${SAFE_X + 4}" y="0" width="${W - SAFE_X}" height="${H}"/></clipPath>
    <filter id="soft" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur stdDeviation="0.8"/></filter>
    <filter id="seam" x="-50%" y="-20%" width="200%" height="140%"><feGaussianBlur stdDeviation="4"/></filter>
  </defs>
  <rect width="${W}" height="${H}" fill="${BAND}"/>
  <g mask="url(#mB)"><g mask="url(#mL)">
    <rect width="${W}" height="${H}" fill="url(#tone)"/>
    <rect width="${W}" height="${H}" fill="url(#glowL)"/>
    <rect width="${W}" height="${H}" fill="url(#glowR)"/>
    <g>${flowSvg}</g>
    <g fill="none" stroke="#C3F4DE" stroke-width="0.62">${mesh}</g>
    <g clip-path="url(#brightZone)">
      <path d="${arc(120, 240, GR + 2)}" fill="none" stroke="#5FD39A" stroke-opacity="0.32" stroke-width="10" filter="url(#seam)"/>
      <g>${lit}</g>
      ${gauge}
      <g filter="url(#soft)">${halos}</g>
      <g>${trapped}</g>
    </g>
  </g></g>
</svg>`;

const SVG_FILE = path.join(OUT, 'bg.svg');
if (!FROM_SVG) fs.writeFileSync(SVG_FILE, svg);
const art = FROM_SVG ? fs.readFileSync(SVG_FILE, 'utf8') : svg;

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: W * SCALE, height: H * SCALE }, deviceScaleFactor: 1 });
  await page.setContent(`<!doctype html><html><body style="margin:0;background:${BAND}">${art}</body></html>`);
  await page.screenshot({ path: PNG_OUT, clip: { x: 0, y: 0, width: W * SCALE, height: H * SCALE } });
  await browser.close();
  console.log((FROM_SVG ? 'rendered existing bg.svg -> ' : 'bg.svg regenerated and rendered -> ') + PNG_OUT, W * SCALE + 'x' + H * SCALE);
})().catch((e) => { console.error(e); process.exit(1); });
