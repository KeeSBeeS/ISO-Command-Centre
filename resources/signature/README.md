# Email signature background art

Source of `public/images/signature/iiso-signature-bg.jpg`, the band behind the name and contact details in the
Signature Builder (`SignatureLogoStore::DEFAULT_BACKGROUND`).

| File | Purpose |
|---|---|
| `bg.svg` | The art: 640 x 132 viewBox (the 1x band), rendered at 2x. Edit it in any SVG editor. |
| `bg_render.cjs` | Generates `bg.svg` (seeded, so it is reproducible) and renders it to PNG with Playwright Chromium. |
| `render.sh` | Renders and writes the JPG: 1280 x 264, progressive, quality 82, 4:2:0 (about 30 KB). |

Re-render: `sh resources/signature/render.sh --from-svg` after editing `bg.svg` by hand, or
`sh resources/signature/render.sh` to regenerate `bg.svg` from `bg_render.cjs` first. Both need Node with the
`playwright` package and ImageMagick `convert`; upload the new JPG afterwards (the server needs no build step).

Rules the template relies on:

- Keep the size 1280 x 264. The template shows it at 640 x 132 (`background-size:640px 132px`, VML 480pt x 99pt).
- The left edge (first 8px at 1x) and the bottom edge (last 8px) must be exactly `#0D4B4A`, the band `bgcolor`.
  The band grows downward for long names, titles and emails, and the extra height is plain `#0D4B4A`.
- Keep everything left of x = 574 (1x) dark and flat: the text column ends at x = 576. Bright art belongs at
  x >= 583 only. The flow line in the HTML sits at y = 70, the centre of the gauge arc.
- No text and no company logo in the image. The official logo is uploaded separately under Manage Logos.
