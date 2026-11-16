# List views

A list can offer views ("Ansichten") above the filter bar: views defined in
code and views members save themselves. A view is chosen with `?view=<key>`.

A list can open with a view instead of all records:

```php
private static string $default_list_view = 'recent';   // e.g. "Zuletzt angesehen"
```

"All" then links to `?view=all`, and the filter bar keeps the current view
(`view=all` when all records are shown).

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

## Column choice ("Spalten")

Every list has a menu "Spalten" in the view bar: members tick the columns
they want to see, "Standard" goes back to the default. Offered are all
columns of `defineColumns()` that are not `exportOnly()`, including
`optional()` ones (hidden by default). A view with its own columns wins over
the choice (the menu is hidden then). The export is not affected.

The choice is stored in `ListColumnChoice` (run `dev/build`), by default per
member and list. Hooks on the controller:

```php
// One choice per campaign, for the whole team (only editors may change it)
protected function columnChoiceKey(): string
{
    return 'campaign-' . $this->getParentID();
}

protected function columnChoiceShared(): bool
{
    return true;
}

// Columns from data, e.g. the campaign's form questions: add them in
// defineColumns() as optional(); override choosableColumns() for another set.
protected function defineColumns(): ColumnCollection
{
    $columns = parent::defineColumns();
    foreach ($this->campaign()->Questions() as $question) {
        $columns->make('Answer_' . $question->ID, $question->Title)
            ->format(fn ($value, $record) => $record->answerTo($question))
            ->optional()->end();
    }
    return $columns;
}
```

Chosen columns that no longer exist are left out; if none is left, the
default columns show. Switch the menu off with config
`column_choice: false` on the controller.
