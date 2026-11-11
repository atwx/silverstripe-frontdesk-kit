# Record pages (sub views)

A record view can be split into pages ("sub views") listed in a sidebar, with a
fixed header above them (title, header fields, record actions). Each page is
reachable at `<segment>/view/<ID>/<page>`; without a page the first visible page
is shown.

```php
protected function defineRecordPages(DataObject $record): ?RecordPages
{
    return RecordPages::create()
        ->page(RecordPage::create('status', 'Status')->timeline()->sublist(NoteSubController::class))
        ->page(RecordPage::create('family', 'Familie')
            ->related(fn ($r) => $r->Family(), FamilyController::class))
        ->separator()
        ->switcher($this->caseSwitcher($record))
        ->page(RecordPage::create('placement', 'Vermittlung')->groups(['placement', 'internal']))
        ->page(RecordPage::create('letters', 'Korrespondenz')->content(fn ($r) => '<p>…</p>'));
}

protected function defineHeaderFields(DataObject $record): ?ArrayList   // label/value rows
protected function defineRecordImage(DataObject $record): ?string       // image URL in the sidebar
```

## Blocks

| Block | Shows |
|---|---|
| `groups(['key', …])` | field groups of the record's `FieldLayout` (by key, see below) |
| `sublist(SubController::class)` | a list of one of the controller's sub-controllers |
| `related(fn ($r) => $r->Other(), OtherController::class)` | a related record through its own controller: fields, edit button, its sub-lists |
| `timeline()` | the record's timeline |
| `content(fn ($r) => '…')` | own HTML |

Group keys are set with `FieldLayout::group($title, $fields, $open, $key)` and
default to the title.

## Editing a page

A page with `groups()` gets an "Edit" button (`edit/<ID>?page=<page>`) when the
member may write a field of its groups. The form holds only the fields of those
groups (`FieldLayout::onlyGroups()`), submits the page as `RecordPage`, and on save
changes to all other fields are reset, so a page cannot change fields it does not
show. A related record is edited through its own controller and returns to the page.

## Visibility

A page is left out of the sidebar (and its URL answers 404) when none of its
blocks is visible: no visible field in its groups, no related record or no view
permission on the related controller, no timeline, or a sub-list the controller
hides (`canShowSubController()`).

## Switcher

`RecordSwitcher` lists related records (e.g. the cases of the same child) between
the pages, with an optional link to add one (`withAddLink()`).
