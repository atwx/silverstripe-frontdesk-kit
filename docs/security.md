# Record access and hooks

## Base list

All records of a controller are loaded from `getBaseList()` — the list view,
`view`, `edit`, `save`, `savecontinue`, `delete` and the edit form. Restrict it to
restrict access everywhere:

```php
protected function getBaseList(): DataList
{
    return parent::getBaseList()->filter('OwnerID', Security::getCurrentUser()?->ID);
}
```

Sub-controllers use their parent-scoped `getBaseQuery()` as base list, so a record
of another parent cannot be edited or deleted by changing the ID in the URL or form.

Extensions can hook in via `updateBaseList(DataList &$list)`.

## Record permissions

```yaml
Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController:
  check_record_permissions: true
  allow_get_delete: false
```

- `check_record_permissions` (default `false`): each record is checked with its own
  `canView()` (view), `canEdit()` (edit, save, edit form), `canDelete()` (delete) and
  `canCreate()` (add, save of a new record). A failed check returns 403. Note that
  `DataObject`'s defaults only allow `ADMIN`, so models need their own `can*()`
  methods before enabling this.
- `allow_get_delete` (default `true`): set to `false` to accept deletes only via
  `DELETE` (used by the built-in UI) or `POST`; GET returns 405.

Both default to the previous behaviour for backwards compatibility; new projects
should enable them.

## Hooks

Override in the controller:

| Method | When |
|---|---|
| `beforeWrite(DataObject $item, array $data)` | after `saveInto()`, before `write()` |
| `afterWrite(DataObject $item, array $data)` | after `write()` |
| `beforeDelete(DataObject $item)` | before `delete()` |
| `afterDelete(DataObject $item)` | after `delete()` |

The default implementations call the extension hooks `onBeforeWriteRecord`,
`onAfterWriteRecord`, `onBeforeDeleteRecord` and `onAfterDeleteRecord`.
`updateEditForm(Form $form, DataObject $item)` lets extensions change the edit form.
