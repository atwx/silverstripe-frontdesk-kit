# Field layout, record actions and timeline

## Field layout

`defineFieldLayout()` arranges the fields of a record in groups and gives each
field an access level. The same layout drives the edit form, the record view
and saving.

```php
protected function defineFieldLayout(?DataObject $record): ?FieldLayout
{
    return FieldLayout::create()
        ->group('Basics', ['Title', 'Date', 'VenueID'])
        ->group('Internal', ['Note'], false)          // collapsed
        ->setLevels(['Date' => FieldLayout::READ, 'Note' => FieldLayout::HIDDEN])
        ->setDefaultLevel(FieldLayout::WRITE);        // fields without a level
}
```

| Level    | Form                    | View  | Save              |
|----------|-------------------------|-------|-------------------|
| `write`  | editable                | shown | saved             |
| `read`   | read-only               | shown | change is reset   |
| `hidden` | removed                 | –     | change is reset   |

`$record` is `null` when a record is added. Field names are form field names
(`VenueID` for a has_one). After `saveInto()` the controller calls
`resetLockedChanges()`, so a manipulated POST cannot change a field that is
not writable. Groups are rendered as `CollapsibleFieldGroup`s in the form and
as cards in the view (`Includes/ViewGroups.ss`).

## Record actions

An action on a single record, shown as a button on the record view. It opens a
dialog with a choice of options and an optional comment and posts to
`<segment>/recordaction/<ID>` (CSRF protected).

```php
protected function defineRecordActions(DataObject $record): array
{
    return [
        RecordAction::create('status', 'Change status')
            ->options(['review' => 'Send to review', 'done' => 'Close'])
            ->withComment('Comment')
            ->handle(function (DataObject $record, ?string $option, string $comment) {
                if (!$allowed) {
                    throw new ActionException('Not allowed.');    // shown as error
                }
                // ...
                return 'Status changed.';                       // shown as success
            }),
    ];
}
```

Only the actions returned for the record are executed, and only with one of
their options. The endpoint is open to everyone who can view the list, so
return only the actions the current user may run. Messages are shown once on
the next page (`FlashMessage()`).

## Timeline

`defineTimeline()` returns entries shown below the record view, newest first.
Entries can come from any source:

```php
protected function defineTimeline(DataObject $record): ?ArrayList
{
    $entries = ArrayList::create();
    foreach ($record->Notes() as $note) {
        $entries->push(TimelineEntry::create($note->Created, 'Note', $note->Text, $note->Author()->Name, 'note'));
    }
    return $entries;
}
```

Override `timelineTitle()` to change the heading. `Includes/Timeline.ss` renders it.

## Column visibility per record

`Column::visibleWhen(fn (DataObject $record) => …)` leaves the cell empty (list
and export) for records where the callable returns false, e.g. for field-level
permissions.
