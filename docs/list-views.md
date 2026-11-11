# List views

A list can offer views ("Ansichten") above the filter bar: views defined in
code and views members save themselves. A view is chosen with `?view=<key>`.

## Views in code

```php
protected function defineListViews(): array
{
    return [
        ListView::create('active', 'Aktive')
            ->filters(['Status' => 'placed'])                 // values as in the filter bar
            ->query(fn (DataList $list) => $list->filter('InDate:LessThanOrEqual', date('Y-m-d')))
            ->sort('InDate DESC')
            ->columns(['Title', 'School.Name', 'InDate'])     // list and export
            ->exportColumns([...]),                           // export only (optional)
    ];
}
```

- The filter values are used when the view is chosen; values in the request win,
  so the filter bar can narrow a view down further (the view stays selected).
- `query()` runs after the filters, e.g. for dates relative to today or
  conditions on related records the filter bar does not offer.

## Saved views

Members who can see a list can save the current filters and a column selection
("Save view"); members who may edit can share a view with everyone. Saved views
are `SavedListView` records keyed by the controller's `url_segment`
(`listKey()`). Own views can be deleted, shared views by members who may edit.

## Columns

- `->optional()`: only shown when a view selects the column (e.g. "Austritt" for
  the returners view).
- `->exportOnly()`: in the export, not in the list (unless a view selects it).

Without a view the list shows all columns that are neither optional nor
export-only; the export shows all but the optional ones.
