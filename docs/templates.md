# Templates

## Template locations

The module provides default templates under:

```
vendor/atwx/silverstripe-frontdesk-kit/templates/Atwx/SilverstripeFrontdeskKit/
├── FrontdeskController.ss          # full HTML page (navbar + layout)
└── Layout/
    ├── FrontdeskController.ss      # list view layout
    ├── FrontdeskController_edit.ss # edit/add form layout
    └── FrontdeskController_view.ss # detail view layout
Includes/
    ├── ListTable.ss                # table body rows (HTMX target)
    ├── RowActions.ss               # dropdown per row
    └── Pagination.ss               # DaisyUI pagination
```

---

## Overriding templates

Silverstripe resolves templates by searching your app's `templates/` directory before the module's. To override any template, copy it to the matching path under `app/templates/`:

```
app/templates/Atwx/SilverstripeFrontdeskKit/Layout/FrontdeskController.ss
```

### Controller-specific templates

To override a template for one controller only, Silverstripe tries the controller class name first. For `ContactManageController`:

```
app/templates/App/Controllers/ContactManageController.ss        # index
app/templates/App/Controllers/ContactManageController_edit.ss   # edit/add
app/templates/App/Controllers/ContactManageController_view.ss   # view
```

If none of these exist, Silverstripe falls back to the module's `FrontdeskController` templates.

---

## HTMX partial rendering

When the filter bar submits a request (via `hx-get`), the `HX-Request` header is set. `FrontdeskController::index()` detects this and returns only the `ListTable.ss` fragment instead of the full page:

```php
// Simplified from FrontdeskController::index()
if ($this->isHtmxRequest()) {
    return $this->renderPartial(
        'Atwx\\SilverstripeFrontdeskKit\\Includes\\ListTable',
        ['Items' => $this->getItems(), 'Columns' => $this->defineColumns()]
    );
}
```

The filter form in `FrontdeskController.ss` targets the `tbody`:

```html
<form hx-get="$Link"
      hx-target="#fdk-list-body"
      hx-trigger="change, submit"
      hx-swap="innerHTML">
  ...
</form>

<tbody id="fdk-list-body">
    <% include Atwx\\SilverstripeFrontdeskKit\\Includes\\ListTable %>
</tbody>
```

**Result:** filter changes update only the table rows — no full page reload.

---

## Available template variables

### On list view (`FrontdeskController.ss`)

| Variable | Type | Description |
|---|---|---|
| `$Title` | string | Controller title from `$title` config |
| `$Actions` | ArrayList | Page-level actions (New, Export) |
| `$Columns` | ColumnCollection | Column definitions for `<thead>` |
| `$ItemRows` | ArrayList | Rows with `Cells`, `RowActions`, `ID` |
| `$Items` | PaginatedList | For pagination |
| `$FilterForm` | Form | The filter form |
| `$FilterIsActive` | bool | Whether any filter is currently set |
| `$Link` | string | Base URL of this controller |
| `$MainNavigation` | ArrayList | Navigation items (from BaseController) |
| `$Logo` | string | Logo URL |

### On edit/add view

| Variable | Type | Description |
|---|---|---|
| `$Form` | Form | The edit form |
| `$Title` | string | "Edit Contact" / "New Contact" |
| `$Item` | DataObject | The record being edited (`null` for add) |

### Per row (inside `ListTable.ss`)

Each `$ItemRows` entry exposes:

| Variable | Type | Description |
|---|---|---|
| `$Cells` | ArrayList | Cell data: `Value`, `Link`, `HasLink`, `Type` |
| `$RowActions` | ArrayList | `RowAction` objects |
| `$ID` | int | Record ID |
