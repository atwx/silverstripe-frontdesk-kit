# silverstripe-frontdesk-kit

A modern CRUD frontend toolkit for Silverstripe 6, built on **Tailwind CSS + DaisyUI + HTMX + Alpine.js**.

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

## Frontend Build

```bash
cd vendor/atwx/silverstripe-frontdesk-kit
yarn install
yarn build
```

Theme without rebuilding via CSS custom properties:

```css
/* In your app CSS */
:root {
    --p: 220 80% 30%;   /* DaisyUI primary colour */
    --s: 160 60% 40%;   /* DaisyUI secondary colour */
}
```

## Licence

BSD-3-Clause
