# Frontend

## Stack

| Tool | Purpose |
|---|---|
| **Tailwind CSS v3** | Utility-first CSS |
| **DaisyUI v4** | Component layer on top of Tailwind |
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

`FrontdeskController.ss` loads the assets via Silverstripe's `resourceURL()`:

```html
<link rel="stylesheet" href="$resourceURL('atwx/silverstripe-frontdesk-kit:client/dist/frontdesk.css')">
<script src="$resourceURL('atwx/silverstripe-frontdesk-kit:client/dist/frontdesk.js')" defer></script>
```

Silverstripe's vendor plugin exposes `client/dist` via `_resources/` (configured in `extra.expose` in the module's `composer.json`).

---

## Theming

DaisyUI exposes its colour tokens as CSS custom properties (`--p`, `--s`, etc.). The module maps them to its own properties:

```css
/* client/src/css/main.css */
:root {
    --fdk-primary:   oklch(var(--p));
    --fdk-secondary: oklch(var(--s));
}
```

To theme the kit without rebuilding, override the DaisyUI variables anywhere in your app's CSS **after** the module stylesheet is loaded:

```css
/* app/client/src/scss/main.scss */
:root {
    --p: 220 80% 30%;   /* primary: dark blue */
    --s: 160 60% 40%;   /* secondary: teal */
    --a: 30 90% 55%;    /* accent: amber */
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
