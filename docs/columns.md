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

Use `->make()` to configure individual columns. `->end()` returns the collection so further `->make()` calls can be chained:

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

### `->sortable(bool $v = true)`

Marks the column as sortable (reserved for future sort implementation).

```php
->make('Surname')->sortable()->end()
```

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

### `->visibleInExport(bool $v)`

Controls whether the column appears in the XLSX export. Defaults to `true`.

```php
->make('InternalNote')->visibleInExport(false)->end()
```

---

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
