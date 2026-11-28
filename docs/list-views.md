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

## Reports

A view marked as `report()` is not shown in the view bar but in the menu
"Berichte ▾" next to it, grouped. While it is the current view, the bar shows
it too, so one sees where one is. Without `report()` everything stays as
before (the view is in the bar).

```php
ListView::create('returners', 'Rückkehrer')
    ->report('Nach der Vermittlung')                  // group in the menu
    ->description('Austritt in den letzten 12 Wochen') // menu, reports page, above the list
    ->filters(['Stay' => 'back', 'OutDate_Rel' => '-12w..1w'])
    ->search('Hamburg')                               // search text (optional: scope)
    ->columnSort('OutDate', 'desc')                   // like a click on the header
    ->groupBy('CurrentForm')                          // column name
    ->pinned();                                       // a report in the bar as well (optional)
```

- `search()` and `columnSort()` are request values the view starts with; the
  search field and the column headers can change them.
- `groupBy(<column>)`: rows grouped by the column's value with a heading row
  per group. If the column is a database field of the model, the list is
  sorted by it first and the heading shows the number of records of the whole
  list. The export stays ungrouped.

### Reports page

`reports` (`ReportsController`) lists the reports of all lists the member
may see: views with `report()` and saved views, by list and group, with
their description. Add it to the navigation:

```yaml
Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController:
  main_navigation:
    reports:
      title: 'Berichte'
      segment: 'reports'
      controller: 'Atwx\SilverstripeFrontdeskKit\Controller\ReportsController'
```

A list leaves the page with config `reports: false` on its controller.

## Saved views

Members who can see a list save its current state as a report ("Save view"):
filters, search, column sort, columns, grouping, name and description.
"Show in the view bar" pins it to the bar (default: only in the menu
"Berichte"); views saved before reports existed stay pinned. Members who may
edit can share a report with everyone. With a saved report open, the dialog
updates it ("Update …") instead of saving a new one.

Saved views are `SavedListView` records keyed by the controller's
`url_segment` (`listKey()`). Own reports can be updated and deleted, shared
ones by members who may edit (run `dev/build` for the new fields).

## Relative periods

`DateRangeFilter::relative()` adds a select "Zeitraum" to the from/to dates.
Its value (`<Name>_Rel`) stays relative in saved reports and views:

| Value | Meaning |
|---|---|
| `-12w..1w` | today − 12 weeks to today + 1 week (units `d`, `w`, `m`, `y`) |
| `-4w..0d`, `0d..13w` | last 4 weeks, next 13 weeks |
| `..13w`, `-1y..` | open on one side |
| `schoolyear`, `schoolyear-1` | this / last school year |
| `year`, `year-1` | this / last calendar year |

```php
DateRangeFilter::create('OutDate', 'Austritt')
    ->relative()                                    // default periods
    // ->relative(['-12w..1w' => 'Rückkehrer-Zeitraum', 'schoolyear'])
    ->apply(fn (DataList $list, array $range) => …); // $range['from'], $range['to'] as before
```

The school year starts in August; change it with

```yaml
Atwx\SilverstripeFrontdeskKit\Filter\DateRangeFilter:
  school_year_start_month: 9
```

A period set by a view (e.g. `-12w..1w`) is added to the select if it is not
among the offered ones. A valid period wins over dates entered; the chip
describes it ("letzte 12 Wochen", "heute − 12 Wochen bis heute + 1 Woche").

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
