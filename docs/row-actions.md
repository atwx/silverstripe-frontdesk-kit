# Row Actions

Row actions appear in a dropdown menu at the end of each table row. The default actions (Edit and Delete) are provided automatically when `canEdit()` returns true.

## Default behaviour

If you do not override `defineRowActions()`, each row gets:

- **Edit** — links to `/contacts/edit/{ID}`
- **Delete** — links to `/contacts/delete/{ID}`, triggers an HTMX `hx-delete` request with a confirmation dialog, and removes the row inline

---

## Overriding row actions

Override `defineRowActions(DataObject $record)` in your controller:

```php
protected function defineRowActions(DataObject $record): array
{
    return [
        RowAction::link('View', $this->Link('view/' . $record->ID)),
        RowAction::link('Edit', $this->Link('edit/' . $record->ID)),
        RowAction::delete($this->Link('delete/' . $record->ID)),
    ];
}
```

---

## Action types

### `RowAction::link(string $label, string $url)`

A standard anchor link.

```php
RowAction::link('Preview', $this->Link('preview/' . $record->ID))
```

### `RowAction::delete(string $url)`

Preconfigured delete action: uses `hx-delete` to remove the row without a page reload, and shows a browser confirmation dialog.

```php
RowAction::delete($this->Link('delete/' . $record->ID))
```

### `RowAction::htmx(string $label, string $url, string $method = 'get')`

An HTMX-powered action. The response replaces the closest `<tr>` element.

```php
RowAction::htmx('Archive', $this->Link('archive/' . $record->ID), 'post')
    ->withConfirm('Archive this record?')
```

---

## Modifiers

### `->withIcon(string $icon)`

Attaches an icon name to the action (used by the template).

```php
RowAction::link('Download', $this->Link('export/' . $record->ID))
    ->withIcon('download')
```

### `->withConfirm(string $message)`

Shows a browser `confirm()` dialog before the action fires.

```php
RowAction::link('Reset', $this->Link('reset/' . $record->ID))
    ->withConfirm('This will reset all data. Continue?')
```

### `->enabled(callable|bool $condition)`

Conditionally shows or hides the action. The callable receives no arguments.

```php
RowAction::link('Activate', $this->Link('activate/' . $record->ID))
    ->enabled(fn() => $record->Status === 'inactive')
```

---

## Adding custom controller actions

Custom actions must be declared in `$allowed_actions` on your controller. They can live alongside the standard CRUD actions:

```php
class EventManageController extends BaseController
{
    private static $allowed_actions = [
        'archive',
        'duplicate',
    ];

    public function archive(HTTPRequest $request): HTTPResponse
    {
        $id = $request->param('ID');
        $record = Event::get()->byID($id);
        $record->Archived = true;
        $record->write();
        // Return empty response — HTMX removes the row
        return HTTPResponse::create('', 200);
    }

    protected function defineRowActions(DataObject $record): array
    {
        return [
            RowAction::link('Edit', $this->Link('edit/' . $record->ID)),
            RowAction::htmx('Archive', $this->Link('archive/' . $record->ID), 'post')
                ->withConfirm('Archive this event?'),
            RowAction::delete($this->Link('delete/' . $record->ID)),
        ];
    }
}
```
