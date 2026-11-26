# Search, list toolbar and global search

## Weighted list search

A controller describes its free-text search; the list then gets a search
field (in the list toolbar, see below) and the result is ranked:

```php
use Atwx\SilverstripeFrontdeskKit\Search\SearchDefinition;

protected function defineSearch(): ?SearchDefinition
{
    return SearchDefinition::create()
        ->field('Child.Surname', 10)                 // path from the model, weight
        ->field('Child.FirstName', 8)
        ->field('Child.Family.City', 2)              // over has_one, has_many, many_many
        ->field('PlacementSchool.Name', 3)
        ->scope('name', 'Name', ['Child.FirstName', 'Child.Surname'])   // search areas to choose
        ->scope('city', 'Ort', ['Child.Family.City'])
        ->rule(function (string $word) {                                // words of a special form
            if (!ctype_digit($word)) {
                return null;                          // normal fields
            }
            return (int) $word >= 1900 && (int) $word <= 3000
                ? ['InDate:StartsWith' => 5]          // a year
                : ['Child.Family.Number' => 10, 'Child.Family.Zip:StartsWith' => 4];
        });
}
```

- **Words:** every word must match one of the fields (AND over words, OR over
  fields), as before.
- **Rank per word:** the best field counts: exact match ×3, start of a word ×2
  (start of the value or after a space, "-" or "("), contained ×1, times the
  field's weight. The scores of the words are added.
- **Order:** best first while a search is active and no column sort is chosen
  (`?sort=` wins); equal scores in the model's `default_sort`. The order is
  done in SQL, so paging and export work as usual.
- **Rules:** a rule returns the fields to search instead (with weights), or
  `null`. To search for a changed word, it returns
  `['word' => str_replace('-', '', $word), 'fields' => ['Number:ExactMatch' => 10]]`
  ("12-345" finds 12345). Fields with the weight `null` are left out. A key with a modifier (`InDate:StartsWith`) matches only that way
  and counts as exact. Rules apply when searching all fields;
  `rule($callback, ['city'])` also in search areas.
- **Parameters:** `?Query=<text>` and `?Query_Scope=<area>` (config
  `search_param`), so links such as `students?Query=pippas2026` keep working.
  Remove an own `TextFilter` named `Query` once `defineSearch()` exists.
- **How it is computed:** the matching IDs and their tiers come from ORM
  queries per field (relations become joins); no search index, no second
  system. Fine for lists of thousands of records.

## List toolbar with filter panel

```php
private static bool $filter_panel = true;
private static array $suggested_filters = ['FlowStatus', 'Assignee_lead'];   // filter names
```

Instead of the view chips and the bar with all filters, the list gets:

- **Toolbar:** search field (always visible, also on phones) with the search
  area, the button **Filter (n)** with the number of active filters, the view
  select (views in code and saved views; "Save view" and the column choice
  stay; a saved view can be deleted next to the select).
- **Panel:** below the toolbar on large screens (open while filters are
  active), a drawer from the right on smaller ones. It shows the suggested
  filters and those with a value; **Filter hinzufügen** adds any other one.
- **Chips:** above the table, one per active filter ("Status: open ×"). The ×
  removes the filter and keeps the others, the search and the view.
- **Views with presets:** `ListView::filters([...])` presets filter values; in
  the panel they are visible and can be changed or removed (the marker
  `filtered=1` keeps a removed preset removed). Views with own logic keep
  `ListView::query()`.

Without `filter_panel` the list looks as before, and a list with
`defineSearch()` gets the search field (and the search area, if there are
scopes) at the start of the classic filter bar – unless it has an own filter
of that name. All request parameters of filters and views are unchanged.

## Global search

```yaml
Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController:
  search_overlay: true          # the overlay in the header
  main_navigation:
    search:
      title: 'Suche'
      icon: 'search'
      open_search: true         # opens the overlay instead of a link

App\Controllers\StudentController:
  global_search_sort: 10        # groups with lower numbers first (default 100)
App\Controllers\ArchiveController:
  global_search: false          # never in the global search
```

Every frontdesk controller with `defineSearch()` takes part, if the member
may view it (`canView()`, records from `getBaseList()`, with
`check_record_permissions` also `canView()` per hit). Of several controllers
for the same model only the most general one takes part. The key `/` opens
the overlay, Escape or a click beside it closes it.

The parts are separate, so a project can replace any of them:

| Part | What it is | Replace by |
|---|---|---|
| `GlobalSearch::search($text)` | the data: groups with `key`, `type`, `count`, `link` (list with the search), `hits` | – (PHP service) |
| `fdk-search?q=…` | endpoint: HTML for the overlay | – |
| `fdk-search?q=…&format=json` | the same as JSON (`{query, groups: [{key, type, count, link, hits: [{title, text, link, image, icon}]}]}`), also with `Accept: application/json` | an own front end (e.g. React) |
| `defineSearchHit($record)` | one hit: `Title`, `Text` (line below), `Link`, `Image` (URL), `Icon` (Lucide name) | override in the controller |
| `Includes/SearchHit` | template of a hit | per list: config `search_hit_template`; all: `SearchController.hit_template` or an override in the theme |
| `Includes/SearchGroup` | template of a group (type, count, hits, "More results (N)") | per list: `search_group_template`; all: `SearchController.group_template` |
| `Includes/SearchResults` | the groups together | `SearchController.results_template` |
| `Includes/SearchOverlay` | the shell: dialog, field, debounce (htmx, 250 ms) | override the include, or `search_overlay: false` and an own shell |

An own shell only needs an element with `data-fdk-search-open` to open a
`<dialog id="fdk-search">` (or calls `window.fdkOpenSearch()`), or it talks
to the JSON endpoint directly.

```php
// A student as a hit: photo, form and year below the name
protected function defineSearchHit(DataObject $record): array
{
    return array_merge(parent::defineSearchHit($record), [
        'Text' => $record->Form . ' · ' . $record->InYear,
        'Image' => $record->PhotoLink(),
    ]);
}
```

```yaml
App\Controllers\StudentController:
  search_hit_template: 'App\Includes\StudentSearchHit'
```
