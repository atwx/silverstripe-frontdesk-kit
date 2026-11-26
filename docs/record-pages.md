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
protected function defineHeaderStatus(DataObject $record): ?ArrayList   // status rows (layout 'tabs')
protected function defineRecordImage(DataObject $record): ?string       // image URL in the sidebar
protected function defineCompactFields(DataObject $record): ArrayList   // rows of a related record on a card
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

## Record actions per page

Record actions (`defineRecordActions()`) are shown on every page unless they
are bound to pages; then they appear only there:

```php
RecordAction::create('letter', 'Brief schreiben')->pages('letters');
```

or by name in the config (for actions of a parent class, e.g. those of a
flow case controller):

```yaml
App\Controllers\StudentController:
  record_action_pages:
    flowstatus: 'status'
    flownote: 'status'
    flowdocument: ['letters', 'status']
```

Running an action does not depend on the page it was shown on.

View actions (links from `defineViewActions()`, e.g. a PDF) are bound the
same way: a key `Pages` (segment or list) on the entry, or a `Name` listed in
`record_action_pages`:

```php
$actions->push(ArrayData::create(['Title' => 'PDF', 'Link' => $pdfLink, 'Pages' => ['letters']]));
$actions->push(ArrayData::create(['Title' => 'Vorschau Elternportal', 'Link' => $portal, 'Name' => 'portal']));
```

## Layout "cards and tabs"

`layout('tabs')` shows the pages as tabs (the first visible one is the start
page, URLs stay `view/<ID>/<page>`) and, if there are cards, a column of
collapsible cards on the left. Without `layout()` everything stays as above.

```php
return RecordPages::create()
    ->layout('tabs')
    ->switcher($this->caseSwitcher($record))               // select field above the cards
    ->page(RecordPage::create('comments', 'Kommentare')->sublist(CommentSubController::class, ''))
    ->page(RecordPage::create('schools', 'Schulauswahl')->sublist(SchoolChoiceSubController::class, ''))
    ->card(RecordCard::create('family', 'Familie')
        ->related(fn ($r) => $r->Family(), FamilyController::class))
    ->groupCards(null, ['internal'])                       // one card per field group
    ->card(RecordCard::create('checklist', 'Unterlagen')->sublist(ChecklistSubController::class)->open(false));
```

- **Blocks:** cards take the same blocks as pages (`groups()`, `sublist()`,
  `related()`, `timeline()`, `content()`), so any block can be a card or a tab.
  An empty title (`sublist(X::class, '')`) leaves out the heading in a tab.
- **`groupCards(?array $keys = null, array $exclude = [])`:** one card per group
  of the `FieldLayout` (key and title of the group, open as the group), at this
  position; groups that are on an explicit card are skipped.
- **On a card** groups are a short label/value list without empty values; a
  card without visible content is left out, unless the member may edit its
  groups (it then says "Noch keine Angaben"). `related()` is compact: title with
  a link, the rows of the related controller's `defineCompactFields()` (default:
  its first four rows with a value) and an edit icon.
- **Editing:** a card with groups has an "Bearbeiten" link that edits only its
  groups (`edit/<ID>?page=<card key>`, back to the current tab). Keys of cards
  and pages share one namespace.
- **Open or closed:** `open(false)` or `open(fn ($record) => …)` (e.g. by
  status) sets the initial state; the browser then remembers the member's
  choice per card (localStorage `fdk-card:<segment>:<key>`). While closed, a
  card shows a summary line: `summary(fn ($record) => '…')`, by default the
  first three values of its groups.
- **Header:** title on the left; on the right the rows of
  `defineHeaderStatus()` (ArrayData with `Title`, `Value`, optional `Color` as
  a DaisyUI badge color; several rows for records with phases).
  `defineHeaderFields()` becomes a small line under the title.
- **Switchers** are select fields above the cards (`display('select')` is the
  default in this layout; `display('menu')` keeps the dropdown menu). The add
  link is a button next to the select field.
- **Image:** `defineRecordImage()` is shown at the top of the card column.

## Switcher

`RecordSwitcher` lists related records (e.g. the cases of the same child) between
the pages, with an optional link to add one (`withAddLink()`).

From 15 options on (config `RecordSwitcher.searchable_from`) the list gets a
search box: typing filters the options, Enter opens the first match. Switch it
on or off per switcher with `->searchable()` / `->searchable(false)`:

```php
// Switch to another school on the same page
$segment = (string) $this->getRequest()->param('OtherID');
$switcher = RecordSwitcher::create()->searchable();
foreach (School::get()->sort('Name') as $school) {
    $switcher->option($school->Name, $this->Link('view/' . $school->ID . '/' . $segment), $school->ID === $record->ID);
}
```

`display('select')` shows the switcher as a select field (default in the
layout 'tabs').

The switcher's options are links inside `.fdk-record-sidebar` too. To address
only the page links (e.g. to count them in a test), use
`.fdk-record-sidebar > ul > li > a`.

## Select all in checkbox sets

`CheckboxSetSelectAll::apply($field, 3)` adds "Alle auswählen" and "Keine"
above a `CheckboxSetField` with at least 3 options (disabled options stay as
they are):

```php
use Atwx\SilverstripeFrontdeskKit\Forms\CheckboxSetSelectAll;

$fields->push(CheckboxSetSelectAll::apply(CheckboxSetField::create('Entries', 'Einträge', $options)));
```
