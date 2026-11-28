# Columns

Columns control which fields appear in the list view, whether they are linked, and how values are rendered.

## Basic usage

Override `defineColumns()` in your controller and return a `ColumnCollection`:

```php
protected function defineColumns(): ColumnCollection
{
    return ColumnCollection::fromSummaryFields(Contact::class);
}
```

`fromSummaryFields()` reads the model's `$summary_fields` config and creates a base collection from it.

---

## Fluent builder

Use `->make()` to configure individual columns. `->end()` returns the collection so further `->make()` calls can be chained. `make()` on a name that already exists (e.g. from `fromSummaryFields()`) changes that column instead of adding a second one; a label given replaces its label:

```php
protected function defineColumns(): ColumnCollection
{
    return ColumnCollection::fromSummaryFields(Contact::class)
        ->make('Title', 'Name')->link('view/{ID}')->end()
        ->make('Company', 'Company')->sortable()->end()
        ->make('Email', 'Email')->type('email')->end();
}
```

---

## Available methods

### `->link(string $pattern)`

Makes the cell content a hyperlink. `{FieldName}` is replaced with the value of that field on the record.

```php
->make('Title')->link('view/{ID}')->end()
// → <a href="view/42">Jane Smith</a>

->make('Company')->link('companies/view/{CompanyID}')->end()
```

### `->label(string $label)`

Overrides the column header text.

```php
->make('FormatLastEdited')->label('Last updated')->end()
```

### `->sortable(bool|string|callable $v = true)`

Makes the header a link that sorts the list by this column. A second click
reverses the direction. The choice travels as `?sort=<column>&dir=asc|desc`
and keeps the filters. It works in lists and sub-lists; in sub-lists the link
reloads only the sub-list.

```php
->make('Surname')->sortable()->end()                       // by the field Surname
->make('Member.Name')->sortable('Member.Surname')->end()    // by another field or relation path
->make('StartDate.Nice')->sortable('StartDate')->end()     // formatted column, sorted by the raw field
->make('Length')
    ->sortable(fn (DataList $list, string $dir) => $list->orderBy('LENGTH("Title") ' . $dir))
    ->end()                                                // by a SQL expression
```

The database does the sorting. A value computed in PHP (for example, a
progress rank derived from several dates) can only be sorted if it is stored:
write it into a field in `onBeforeWrite()` and sort by that field. Filters work
on that field as well.

Scaffolded columns are changed by name:
`$columns->byName('Title')?->sortable();`.

### `->type(string $type)`

Hints to the template what kind of data this column contains. Recognised values: `text` (default), `date`, `badge`, `html`.

```php
->make('Status')->type('badge')->end()
```

### `->format(callable $fn)`

Custom value renderer. The callable receives `($value, $record)` and must return a string.

```php
->make('EventDate', 'Date')
    ->format(fn($value, $record) => $record->dbObject('EventDate')->Format('d MMM yyyy'))
    ->end()

->make('Status', 'Status')
    ->format(fn($value, $record) => match($value) {
        'participant' => 'Participant',
        'cancelled'   => 'Cancelled',
        'dropout'     => 'Dropout',
        default       => $value,
    })
    ->end()
```

### Boolean fields

Columns on a `Boolean` database field (also on a has_one path) without
`format()` show a quiet check mark for yes and nothing for no; the export
writes yes/no ("ja"/"nein"). `format()` replaces this.

### `->visibleInExport(bool $v)`

Controls whether the column appears in the XLSX export. Defaults to `true`.

```php
->make('InternalNote')->visibleInExport(false)->end()
```

---

## Grouped rows

A list can show a heading row per value of one field, e.g. schools by state:

```php
return ColumnCollection::create()
    ->make('Name', 'Schule')->sortable()->end()
    ->groupBy('State', 'Ohne Bundesland');          // field or has_one path ("Region.Title")
```

Records without a value are listed under the empty label. On a database field
of the model the list is sorted by it first (empty values last), then by the
chosen column sort; for a has_one path or a computed title
(`groupBy('Region', '–', fn ($record) => …)`) sort the list yourself, e.g. with
`default_sort`. Each page starts with the heading of its first group.

## Building a collection manually

You can build a collection without `fromSummaryFields`:

```php
use Atwx\SilverstripeFrontdeskKit\Column;
use Atwx\SilverstripeFrontdeskKit\ColumnCollection;

protected function defineColumns(): ColumnCollection
{
    $collection = new ColumnCollection();
    $collection->addColumn(Column::create('Title', 'Name')->link('view/{ID}'));
    $collection->addColumn(Column::create('Company', 'Company'));
    return $collection;
}
```

---

## Export

The XLSX export (available at `/contacts/export`) uses the same `ColumnCollection`, filtered to `visibleInExport() === true`. The export link appears automatically in the page actions bar.
It exports the list as filtered on screen (also after filtering with htmx).

The sheet has a bold, frozen header row with an autofilter. Columns fit their
content unless they set a width (in characters, as in Excel):

```php
$columns->make('Name', 'Schule')->width(40)->end();
```

HTML values are exported as plain text. To change the sheet further, use the
extension hook `updateSpreadsheet(Spreadsheet $spreadsheet, ColumnCollection $columns)`.

### Additional exports

A list can offer more export buttons, each with its own title, file name and
columns, optionally only in some list views:

```php
use Atwx\SilverstripeFrontdeskKit\Table\Export;

protected function defineExports(): array
{
    $year = (int) date('Y') + 1;
    return [
        Export::create('fees', 'Fees ' . $year)
            ->fileName('Fees ' . $year)                    // ".xlsx" is added
            ->columns(['Title', 'Fee', 'City']),           // names of the list's columns
        Export::create('schools', 'Schulexport')
            ->columns(fn () => $this->schoolExportColumns())   // ColumnCollection, any number of columns
            ->query(fn (DataList $list) => $list->filter('Active', 1)),
        Export::create('y11', 'Details Y11 Entry')
            ->columns([...])
            ->views(['y11']),                              // only in the view "y11" ('' = no view)
    ];
}
```

Each export runs on the filtered list (filters, view, column sort), then its own
`query()`. Without `columns()` it uses the list's columns.

### Exports set up by members

With more than one export (exports in code, saved ones) or the right to set
them up, the page actions show one menu **Export ▾** instead of several
buttons:

- above: the default export and the exports in code (`defineExports()`, for
  special formats such as title rows),
- below: the exports members set up (own ones and those shared with everyone,
  only in their views),
- at the end, with the permission `<prefix>_EXPORT` ("Set up and share
  exports of …", e.g. `FDK_STUDENTS_EXPORT`): **Export anlegen …** and
  **Exporte verwalten**.

Every export runs on the list as it is shown (search, filters, view, sort).

**Setting one up** (`<segment>/savedexport/new`, from the menu): title, file
name (the date and `.xlsx` are added), the columns in their order with an
optional width (add from a select, move up/down or drag, remove), the views it
is offered in (none: all), "Filter und Suche merken" and "Für alle".

- **Columns to choose:** all columns of `defineColumns()`, also `optional()`
  and `exportOnly()` ones. Offer more, e.g. fields of related records, as
  `exportOnly()` columns, or override `exportableColumns()`:

  ```php
  ->make('Child.Family.Email', 'E-Mail Eltern')->exportOnly()->end()
  ```

- **Kept filters:** when set up from a filtered list, the export may keep its
  filters and search. They apply when the export runs on a list that is
  neither filtered nor searched; a filtered list is exported as shown. (The
  export does not change the list on screen.)
- **Managing** (`<segment>/savedexports`): own and shared exports; own ones
  are edited and deleted by their owner (and admins), every export can be
  duplicated into an own one.

Exports are `SavedExport` records per list (`url_segment`); run `dev/build`.
Switch them off with config `saved_exports: false` on the controller.

## Columns that depend on the filters

`filterValue('<filter name>')` returns a filter's value as the list is filtered
with it (also when it comes from the chosen view), as an array for
`TreeFilter` and `MultiSelectFilter`. Use it in `defineColumns()`, e.g. a
rating column per chosen sport:

```php
protected function defineColumns(): ColumnCollection
{
    $columns = ColumnCollection::create()->make('Title', 'Schule')->end();
    foreach ($this->filterValue('Sports') as $code) {
        $columns->make('Rating_' . $code, 'Rating ' . $code)
            ->format(fn ($value, School $school) => $school->ratingFor($code))
            ->end();
    }
    return $columns;
}
```
