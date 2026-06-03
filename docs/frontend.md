# Frontend

## Stack

| Tool | Purpose |
|---|---|
| **Tailwind CSS v4** | Utility-first CSS |
| **DaisyUI v5** | Component layer on top of Tailwind |
| **HTMX v1.9** | Partial page updates without writing JS |
| **Alpine.js v3** | Client-side state (dropdowns, checkboxes) |
| **Vite v5** | JS bundler |
| **Tailwind CLI** | Standalone CSS build |

---

## Building assets

```bash
cd vendor/atwx/silverstripe-frontdesk-kit
yarn install
yarn build
```

This produces:

```
client/dist/frontdesk.css   # Tailwind + DaisyUI styles
client/dist/frontdesk.js    # Alpine.js + HTMX bundle (IIFE)
```

The built files are committed to the repository so consuming projects do not need Node.js installed.

---

## How assets are loaded

There are two ways to consume the kit's CSS. Pick **one** — loading both at the
same time gives you two DaisyUI runtimes (duplicated theme/base layers), and the
one that loads last wins for equal-specificity rules, which can silently override
the kit's component styling (e.g. a `btn-primary` losing its foreground colour).

The JS bundle (`frontdesk.js`) is always loaded the same way, via `resourceURL()`.

### Mode 1 — Prebuilt stylesheet (no Node.js build in the host app)

Load the shipped `frontdesk.css` directly. This is what `FrontdeskController.ss`
does by default:

```html
<link rel="stylesheet" href="$resourceURL('atwx/silverstripe-frontdesk-kit:client/dist/frontdesk.css')">
<script src="$resourceURL('atwx/silverstripe-frontdesk-kit:client/dist/frontdesk.js')" defer></script>
```

Silverstripe's vendor plugin exposes `client/dist` via `_resources/` (configured in
`extra.expose` in the module's `composer.json`).

### Mode 2 — Single Tailwind build (host app builds its own CSS)

When the host app already runs Tailwind (e.g. via Vite), build the whole page from
**one** Tailwind run by importing the kit's `preset.css` into your own entry. The
preset carries the DaisyUI config, the kit's `@source` globs and the `.fdk-*`
component styles — but deliberately does **not** `@import "tailwindcss"` (your entry
does that exactly once):

```css
/* app/client/src/css/tailwind.css */
@import "tailwindcss";
@import "@fdk/preset.css";

@source "../../../templates/**/*.ss"; /* your own templates */
@source "../../../src/**/*.php";
```

Map the `@fdk` alias to the kit's CSS directory. With `@tailwindcss/vite` the
alias replacement **must be an absolute path** (a relative one is not resolved for
CSS `@import`):

```js
// vite.config.js
import { fileURLToPath, URL } from 'node:url'
// …
resolve: {
  alias: [{
    find: '@fdk',
    replacement: fileURLToPath(new URL(
      './vendor/atwx/silverstripe-frontdesk-kit/client/src/css', import.meta.url)),
  }],
},
```

Then **remove the `frontdesk.css` `<link>`** from your `Page.ss` (keep the
`frontdesk.js` `<script>`). The preset's `@source` paths resolve relative to the
preset file, so the kit's classes (`btn-primary`, `.fdk-*`, …) are emitted by your
build even though the kit lives in the gitignored `vendor/` directory.

> Don't declare `@plugin "daisyui"` again in your own entry — the preset already
> does. Override theme tokens (see [Theming](#theming)) instead.

In this mode the host's installed Tailwind/DaisyUI versions are used; the kit's own
`package.json` versions only matter for rebuilding the prebuilt `frontdesk.css`.

---

## Theming

DaisyUI v5 exposes its colour tokens as CSS custom properties (`--color-primary`,
`--color-secondary`, etc.). The module maps them to its own properties:

```css
/* client/src/css/preset.css */
:root {
    --fdk-primary:   var(--color-primary);
    --fdk-secondary: var(--color-secondary);
}
```

To theme the kit without rebuilding, override the DaisyUI tokens anywhere in your
app's CSS **after** the kit styles are loaded:

```css
/* app/client/src/scss/main.scss */
:root {
    --color-primary:   oklch(45% 0.24 277);  /* primary */
    --color-secondary: oklch(58% 0.23 277);  /* secondary */
    --color-accent:    oklch(70% 0.18 50);   /* accent */
}
```

For a full list of DaisyUI variables, see [daisyui.com/docs/themes](https://daisyui.com/docs/themes/).

---

## Customising component styles

Because Tailwind classes are in the built CSS, you can extend component styles by adding your own utility classes to template overrides:

```html
{{-- app/templates/Atwx/SilverstripeFrontdeskKit/Layout/FrontdeskController.ss --}}
<div class="fdk-manager my-custom-wrapper">
    ...
</div>
```

Or add extra selectors in your app CSS:

```css
.fdk-table-wrapper {
    border-radius: 0; /* square corners in this project */
}
```

---

## Development mode

To rebuild CSS automatically while editing templates:

```bash
cd vendor/atwx/silverstripe-frontdesk-kit
yarn dev
```

This starts a Vite dev server. Note that in dev mode only JS is hot-reloaded; for CSS you can watch with Tailwind CLI:

```bash
npx tailwindcss -i client/src/css/main.css -o client/dist/frontdesk.css --watch
```
