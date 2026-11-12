# Sub-controllers

A sub-controller manages records that belong to a parent record, e.g. the
domains of a website or the submissions of a form. It extends
`FrontdeskSubController`, scopes its list to the parent and is mounted as an
action of the parent controller.

```php
class DomainSubController extends FrontdeskSubController
{
    private static $managed_model = Domain::class;
    private static $url_segment = 'domains';
    private static $title = 'Domains';
    private static $parent_field = 'WebsiteID';

    protected function getBaseQuery(): DataList
    {
        return Domain::get()->filter('WebsiteID', $this->getParentID());
    }
}

class WebsiteController extends FrontdeskController
{
    private static $sub_controllers = [DomainSubController::class];
    private static $allowed_actions = ['domains'];

    public function domains(HTTPRequest $request): DomainSubController
    {
        return DomainSubController::create()->setParentContext($this, (int) $request->param('ID'));
    }
}
```

URLs are `<parent>/<segment>/<parentID>[/<action>/<id>]`, e.g.
`websites/domains/23`, `websites/domains/23/edit/19`. Permissions are taken
from the parent controller; records are always loaded from `getBaseQuery()`.

## Display: inline or tabs

By default every sub-controller is loaded via HTMX as a compact sublist below
the record view (`websites/view/23`).

With `sub_controller_tabs` the record gets tabs instead: the record view is the
first tab ("Overview"), each sub-controller is a tab with a full list page –
filters, bulk actions, export and pagination work as on a top-level list.

```php
class WebsiteController extends FrontdeskController
{
    private static $sub_controller_tabs = true;
}
```

In tab mode

- the page title is the parent record's title, the tabs mark the active list;
- saving or deleting a sub record returns to the tab list instead of the record view;
- the tab label of the record view can be changed by overriding `overviewTabTitle()`.

`Tabs()` returns the tabs shown above a page (`Includes/Tabs.ss`). Controllers
may override it to show their own tabs, e.g. sections of an area.

## Lists without "New"

Records that are created elsewhere (e.g. documents created by an action, or
one entry per configured checklist item) set

```php
private static $allow_add = false;
```

The "New" button is hidden (`canAdd()`), the `add` action and saving a new
record answer 403. Editing and deleting are not affected.

## Edit several at once

With `bulk_edit_fields`, the sub-list gets the button *Mehrere bearbeiten*.
It opens a form with:

- the entries, all of them chosen; the member can deselect some,
- the listed fields, all empty.

Only filled fields are applied to the chosen entries. Empty fields leave the
values unchanged. Checkboxes become a choice of *– unverändert –*, *Ja* or
*Nein*, so an unticked box does not overwrite anything. Drop-downs start with
*– unverändert –*.

```php
class SchoolApplicationSubController extends FrontdeskSubController
{
    private static array $bulk_edit_fields = ['Active', 'SentOn', 'ReactionID'];
}
```

- Fields locked by the field layout of an entry stay unchanged.
- An entry that fails validation is skipped and named in the message:
  *2 Einträge geändert. Übersprungen: …*
- With `check_record_permissions`, only the entries the member may edit are
  offered and changed.
- A field cannot be emptied this way.

