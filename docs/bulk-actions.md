# Bulk actions

Lists get a checkbox column and an action bar when the controller defines bulk
actions:

```php
use Atwx\SilverstripeFrontdeskKit\Table\BulkAction;
use SilverStripe\ORM\DataList;

protected function defineBulkActions(): array
{
    return [
        BulkAction::create('status', 'Set status to', function (DataList $records, ?string $status) {
            foreach ($records as $record) {
                $record->Status = $status;
                $record->write();
            }
            return sprintf('%d records changed.', $records->count());
        })->options(['open' => 'Open', 'closed' => 'Closed']),

        BulkAction::create('pdf', 'Download PDF', fn (DataList $records) => $this->pdfResponse($records)),
    ];
}
```

- The handler receives the selected records loaded from `getBaseList()`, so they
  are scoped like the list. With `check_record_permissions`, only records the user
  may edit (`canEdit()`) are passed.
- Return an `HTTPResponse` (e.g. a download), a message string (shown once after
  the redirect back to the list, filters kept) or `null`.
- `options()` turns the action into an option group in the select; the chosen
  value is passed as second argument and validated against the options.
- The `bulk` action requires POST with CSRF token and `canEdit()` on the
  controller. Without bulk actions nothing changes in the list.

## Confirmation

`->confirm('Wirklich löschen?')` asks before the action runs (browser
confirmation when the form is submitted), e.g. for deleting:

```php
BulkAction::create('delete', 'Löschen', fn (DataList $records) => …)->confirm('Die ausgewählten Einträge löschen?');
```

`{count}` in the question is replaced by the number of records the action runs
on (the checked rows, or all matches when "Select all N matches" is on):

```php
->confirm('{count} Spieler löschen?')
```


## Selection and list reloads

The chosen rows stay checked when the list is reloaded by the filter bar or the
pagination (rows that are no longer shown drop out). A bulk action submitted
without any chosen row does not run; the list shows "No records selected."
instead.

Members without edit permission on the controller see neither the bulk actions
nor the row checkboxes.

## Counter and all matches

The bulk bar shows how many rows are checked ("12 ausgewählt"). When the
whole page is checked and the filter has more matches, it offers "Alle 37
Treffer auswählen": the action then applies to all records of the filtered
list (all pages). The browser sends `all=1` with the list's query string
instead of `ids[]`; the controller resolves it with the same filters, view
and base list as the list (`bulkRecords()`), so the handler gets the same
kind of `DataList` either way. Paging keeps the mode, changing a filter ends it.

## Actions with fields

`fields()` asks for values in a dialog before the action runs. The dialog
says for how many entries, validates (required fields and the fields' own
validation) and shows errors in place. The handler gets the records and the
values of these fields; field values set in the list are the defaults:

```php
BulkAction::create('assign', 'Zuteilen …', function (DataList $records, array $data) {
    foreach ($records as $person) {
        $person->Area = $data['Area'];
        $person->Status = $data['Status'];
        $person->write();
    }
    return $records->count() . ' Personen zugeteilt.';
})
    ->fields(fn () => FieldList::create(
        DropdownField::create('Area', 'Bereich', $areas)->setHasEmptyDefault(true),
        DropdownField::create('Status', 'Status', $statuses)->setValue('invited')
    ), ['Area', 'Status'])
    ->submitLabel('Zuteilen');
```

- `fields(FieldList|callable $fields, array $required = [])`: the callable
  is called when the dialog opens (e.g. for options from the database).
- `submitLabel()`: text of the dialog's button (default: the action's label).
- `confirm()` is not needed (the dialog is the confirmation); `newWindow()`
  submits the dialog into a new tab (for a file shown in the browser).
- Actions with `options()` work as before; an action uses either
  `options()` or `fields()`.
