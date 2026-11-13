# Filters

Filters appear in a filter bar above the list. When a filter value is set, the URL updates (GET parameters) and HTMX refreshes only the table body — no full page reload.

## Basic usage

Override `defineFilters()` and return a `FilterCollection`:

```php
protected function defineFilters(): FilterCollection
{
    return FilterCollection::create()
        ->add(TextFilter::create('Query', 'Search')
            ->apply(fn($list, $v) => $list->filterAny([
                'FirstName:PartialMatch' => $v,
                'Surname:PartialMatch'   => $v,
            ])));
}
```

---

## TextFilter

Renders a plain text input. Suitable for fulltext/partial match searches.

```php
TextFilter::create('Query', 'Search')
    ->apply(fn($list, $v) => $list->filterAny([
        'FirstName:PartialMatch' => $v,
        'Surname:PartialMatch'   => $v,
        'Email:PartialMatch'     => $v,
    ]))
```

---

## SelectFilter

Renders a dropdown. Options can be a static array or a lazy callable (evaluated only when needed).

**Static options:**

```php
SelectFilter::create('Status', 'Status')
    ->options([
        'requested'   => 'Requested',
        'participant' => 'Participant',
        'cancelled'   => 'Cancelled',
    ])
    ->apply(fn($list, $v) => $list->filter('Status', $v))
```

**Dynamic options via callable:**

```php
SelectFilter::create('Year', 'Year')
    ->options(fn() => Event::get()->sort('EventDate', 'DESC')->map('ID', 'YearSummary'))
    ->apply(fn($list, $v) => $list->filter([
        'Attendances.EventID' => $v,
        'Attendances.Status'  => 'participant',
    ]))
```

The callable is deferred — it is only invoked when the filter bar is rendered, not on every request.

---

## MultiSelectFilter

Several fixed options (value => label) to choose, as a searchable checkbox
list. Records match any of the chosen values (OR) or, with `matchAll()`, all of
them (AND). `choosableMatch()` adds a field "Verknüpfung" where the member picks
OR/AND (request var `<Name>_Match` = `any`|`all`; kept in saved views).

```php
use Atwx\SilverstripeFrontdeskKit\Filter\MultiSelectFilter;

MultiSelectFilter::create('Sports', 'Sportarten')
    ->options(['tennis' => 'Tennis', 'golf' => 'Golf'])   // or a callable returning the array
    ->field('Sports.Code')                                // filter on a field or relation path
    ->choosableMatch();

// Own logic: the callable gets the chosen values and whether all must match.
MultiSelectFilter::create('Offers', 'Angebote')
    ->options($offers)
    ->apply(fn (DataList $list, array $values, bool $all) => ...);
```

With `field()`, AND means: the record matches each value (for relations, each
value may be on a different related record).

## DateRangeFilter

Renders two date inputs (`From` / `To`). The `apply` callable receives an array with `from` and `to` keys.

```php
DateRangeFilter::create('Created', 'Created between')
    ->apply(function($list, $range) {
        if ($range['from']) {
            $list = $list->filter('Created:GreaterThanOrEqual', $range['from']);
        }
        if ($range['to']) {
            $list = $list->filter('Created:LessThanOrEqual', $range['to'] . ' 23:59:59');
        }
        return $list;
    })
```

Request parameters are `Created_From` and `Created_To`.

---

## How filters are applied

`FilterCollection::applyAll()` iterates over every filter and passes the current request var to `applyToList()`. If the value is empty (empty string, `null`), the filter is skipped and the list is returned unchanged. This means no special "show all" handling is needed in the apply callable.

---

## Combining multiple filters

All active filters are applied in sequence. The result is the intersection (AND logic):

```php
protected function defineFilters(): FilterCollection
{
    return FilterCollection::create()
        ->add(TextFilter::create('Query', 'Search')
            ->apply(fn($list, $v) => $list->filterAny([
                'FirstName:PartialMatch' => $v,
                'Surname:PartialMatch'   => $v,
            ])))
        ->add(SelectFilter::create('Year', 'Year')
            ->options(fn() => Event::get()->map('ID', 'YearSummary'))
            ->apply(fn($list, $v) => $list->filter('Attendances.EventID', $v)))
        ->add(DateRangeFilter::create('Created', 'Registered')
            ->apply(fn($list, $range) => $list->filter('Created:GreaterThanOrEqual', $range['from'])));
}
```
