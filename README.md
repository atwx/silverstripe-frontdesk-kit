# silverstripe-frontdesk-kit

A modern CRUD frontend toolkit for Silverstripe 6, built on **Tailwind CSS + DaisyUI + HTMX + Alpine.js + Chart.js**.

Rather than rigid table columns from `$summary_fields` and FieldList blobs for filters, you define columns, filters, and row actions cleanly in the controller — inspired by Laravel Backpack and Django Admin.

## Requirements

- PHP ^8.4
- Silverstripe Framework ^6
- Composer

## Installation

```bash
composer require atwx/silverstripe-frontdesk-kit
```

Expose assets and flush Silverstripe:

```bash
composer install
vendor/bin/sake dev/build flush=1
```

## Quick Start

See **[docs/quickstart.md](docs/quickstart.md)** for a step-by-step guide to setting up a new project.

## Documentation

| Document | Contents |
|---|---|
| [docs/quickstart.md](docs/quickstart.md) | New project setup, first controller |
| [docs/columns.md](docs/columns.md) | Defining columns, links, formatting, export |
| [docs/filters.md](docs/filters.md) | TextFilter, SelectFilter, DateRangeFilter |
| [docs/row-actions.md](docs/row-actions.md) | Row actions, HTMX actions, conditional visibility |
| [docs/templates.md](docs/templates.md) | Overriding templates, HTMX partial rendering |
| [docs/charts.md](docs/charts.md) | Declarative Chart.js rendering via `Chart` include |
| [docs/frontend.md](docs/frontend.md) | Building CSS/JS, theming via CSS custom properties |

## Overview

```php
class ContactManageController extends FrontdeskController
{
    private static $managed_model = Contact::class;
    private static $url_segment = 'contacts';
    private static $title = 'Contacts';

    protected function defineColumns(): ColumnCollection
    {
        return ColumnCollection::fromSummaryFields(Contact::class)
            ->make('Title', 'Name')->link('view/{ID}')->end()
            ->make('Company', 'Company')->end();
    }

    protected function defineFilters(): FilterCollection
    {
        return FilterCollection::create()
            ->add(TextFilter::create('Query', 'Search')
                ->apply(fn($list, $v) => $list->filterAny([
                    'FirstName:PartialMatch' => $v,
                    'Surname:PartialMatch'   => $v,
                ])));
    }

    protected function formFields(FieldList $fields): FieldList
    {
        $fields->removeByName('InternalNote');
        return $fields;
    }
}
```

## Charts

Chart.js is bundled. Render any Chart.js config declaratively:

```ss
<% include Atwx\SilverstripeFrontdeskKit\Includes\Chart
    ChartTitle='Revenue (12 months)',
    ChartHeight='360px',
    ChartConfigJson=$RevenueChartJson %>
```

Expose a JSON method on the controller:

```php
public function RevenueChartJson(): string
{
    return json_encode([
        'type' => 'line',
        'data' => ['labels' => $labels, 'datasets' => [...]],
        'options' => ['responsive' => true, 'maintainAspectRatio' => false],
    ]);
}
```

The bundled JS picks up every `[data-fdk-chart]` canvas on page load and after HTMX swaps.

## Form Field Styling

DaisyUI utility classes (`input`, `select`, `textarea`, `checkbox`, `btn`) are applied automatically to any form field rendered inside a `FrontdeskController` or `Security` controller context — the CMS admin is left untouched.

## Frontend Build

The kit ships a **prebuilt** `client/dist/frontdesk.css` + `frontdesk.js`, so
consuming projects work without Node.js. There are two ways to consume the CSS —
see **[docs/frontend.md](docs/frontend.md)** for details.

**1. Prebuilt stylesheet** (no build step) — load it directly, as
`FrontdeskController.ss` does:

```html
<link rel="stylesheet" href="$resourceURL('atwx/silverstripe-frontdesk-kit:client/dist/frontdesk.css')">
```

**2. Single Tailwind build** (recommended when your app already builds its own
CSS) — *don't* also load `frontdesk.css`; that creates a second DaisyUI runtime
that can override the kit's component styles. Instead import the kit's **preset**
into your own Tailwind entry so the whole page comes from one build:

```css
/* app/client/src/css/tailwind.css */
@import "tailwindcss";
@import "@fdk/preset.css";            /* daisyUI config + kit @source globs + .fdk-* */

@source "../../../templates/**/*.ss"; /* your own templates */
@source "../../../src/**/*.php";
```

Map the `@fdk` alias to the kit's CSS dir. With `@tailwindcss/vite` the alias
replacement **must be absolute**:

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

Then drop the `frontdesk.css` `<link>` (keep the `frontdesk.js` `<script>`).

Rebuilding the prebuilt files:

```bash
cd vendor/atwx/silverstripe-frontdesk-kit
yarn install
yarn build
```

Theme without rebuilding by overriding DaisyUI v5 tokens **after** the kit styles:

```css
/* In your app CSS */
:root {
    --color-primary:   oklch(45% 0.24 277);  /* primary colour */
    --color-secondary: oklch(58% 0.23 277);  /* secondary colour */
}
```

## Licence

BSD-3-Clause
