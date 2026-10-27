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
