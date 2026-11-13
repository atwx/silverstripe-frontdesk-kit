# Recently viewed

Members find the records they worked on last: every view of a record page is
noted per member, a list view "Zuletzt angesehen" shows them latest first, and
a block lists them across types (e.g. on a start page).

## Enable per controller

```php
class SchoolController extends FrontdeskController
{
    private static $recent_views = 20;     // or true for the default (RecentView.max_per_class = 20)
}
```

- **Noting:** opening `view/<id>` notes the record for the current member. A
  new view moves it to the top. Per member and record type (base class) only
  the latest entries are kept. Embedded views and htmx requests are not noted.
- **List view:** the list gets the view "Zuletzt angesehen" (`?view=recent`),
  latest first. Filters and sortable columns still work on it, and it only
  shows records of the controller's base list.
- **Related records:** to note another record together with the viewed one
  (e.g. the family when a student is opened), return it from
  `recentViewRecords()`, or use the extension hook
  `updateRecentViewRecords(array &$records, DataObject $record)`:

  ```php
  protected function recentViewRecords(DataObject $record): array
  {
      return $record->FamilyID ? [$record->Family()] : [];
  }
  ```

  Related records are listed under the controller that manages their class
  (one with `recent_views` on first). They are only noted if the member may
  view them.

## Block on a start page

```ss
<% include Atwx\SilverstripeFrontdeskKit\Includes\RecentViews %>
```

The template global `$FrontdeskRecentViews` (optionally `$FrontdeskRecentViews(5)`,
default `RecentView.block_limit = 10`) gives the current member's records
across all types, latest first, each with `Title`, `Type`, `Link`, `ViewedAt`
and `Record`. Records the member may no longer view are skipped.

In PHP: `RecentView::recent($limit)`, `RecentView::idsFor(School::class)`,
`RecentView::applyTo($list)`.

## Cleanup

- Old entries beyond the limit are removed whenever a view is noted.
- Deleting a record through the frontdesk removes its entries for all members.
  Entries of records deleted elsewhere are removed when they are next read; call
  `RecentView::forget($record)` from your own delete code to remove them at once.
