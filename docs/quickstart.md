# Quickstart: New Project with silverstripe-frontdesk-kit

This guide walks through creating a new Silverstripe 6 project that uses `silverstripe-frontdesk-kit` as its CRUD frontend.

---

## 1. Create a Silverstripe project

```bash
composer create-project silverstripe/recipe-core my-project
cd my-project
```

## 2. Install the module

```bash
composer require atwx/silverstripe-frontdesk-kit
composer install
```

## 3. Configure routing

`app/_config/routes.yml`:

```yaml
---
Name: app-routes
After: '#rootroutes'
---
SilverStripe\Control\Director:
  rules:
    'contacts//$Action/$ID/$OtherID': 'App\Controllers\ContactManageController'
```

Add one rule per managed entity. The routing prefix must match the `$url_segment` defined in the controller.

## 4. Create a DataObject

`app/src/Models/Contact.php`:

```php
<?php

namespace App\Models;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;

class Contact extends DataObject
{
    private static $db = [
        'FirstName' => 'Varchar(100)',
        'Surname'   => 'Varchar(100)',
        'Email'     => 'Varchar(255)',
        'Company'   => 'Varchar(255)',
    ];

    private static $summary_fields = [
        'FirstName' => 'First Name',
        'Surname'   => 'Surname',
        'Email'     => 'Email',
        'Company'   => 'Company',
    ];

    private static $table_name = 'Contact';
    private static $singular_name = 'Contact';
    private static $plural_name = 'Contacts';

    public function Title(): string
    {
        return $this->FirstName . ' ' . $this->Surname;
    }

    public function canView($member = null): bool   { return true; }
    public function canEdit($member = null): bool   { return Permission::check('ADMIN'); }
    public function canDelete($member = null): bool { return Permission::check('ADMIN'); }
    public function canCreate($member = null, $context = []): bool { return Permission::check('ADMIN'); }
}
```

## 5. Create a BaseController

A shared base controller for navigation and branding:

`app/src/Controllers/BaseController.php`:

```php
<?php

namespace App\Controllers;

use Atwx\SilverstripeFrontdeskKit\FrontdeskController;
use SilverStripe\Control\Controller;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;

abstract class BaseController extends FrontdeskController
{
    // Path to logo image, relative to project root
    private static $logo = 'app/client/images/logo.svg';

    public function MainNavigation(): ArrayList
    {
        $nav = ArrayList::create();
        $nav->push(ArrayData::create([
            'Title'  => 'Contacts',
            'Link'   => 'contacts',
            'Active' => str_starts_with(Controller::curr()->getRequest()->getURL(), 'contacts'),
        ]));
        return $nav;
    }
}
```

## 6. Create a managed controller

`app/src/Controllers/ContactManageController.php`:

```php
<?php

namespace App\Controllers;

use App\Models\Contact;
use Atwx\SilverstripeFrontdeskKit\ColumnCollection;
use Atwx\SilverstripeFrontdeskKit\FilterCollection;
use Atwx\SilverstripeFrontdeskKit\TextFilter;
use SilverStripe\Forms\FieldList;

class ContactManageController extends BaseController
{
    private static $managed_model = Contact::class;
    private static $url_segment = 'contacts';
    private static $title = 'Contacts';

    protected function defineColumns(): ColumnCollection
    {
        // Start from summary_fields, then customise individual columns
        return ColumnCollection::fromSummaryFields(Contact::class)
            ->make('FirstName', 'First Name')->link('view/{ID}')->end()
            ->make('Surname', 'Surname')->end()
            ->make('Email', 'Email')->end()
            ->make('Company', 'Company')->end();
    }

    protected function defineFilters(): FilterCollection
    {
        return FilterCollection::create()
            ->add(
                TextFilter::create('Query', 'Search')
                    ->apply(fn($list, $v) => $list->filterAny([
                        'FirstName:PartialMatch' => $v,
                        'Surname:PartialMatch'   => $v,
                        'Email:PartialMatch'     => $v,
                    ]))
            );
    }

    protected function formFields(FieldList $fields): FieldList
    {
        // Modify scaffolded fields, or return unchanged for defaults
        return $fields;
    }
}
```

## 7. Build the database

```bash
vendor/bin/sake dev/build flush=1
```

## 8. Open in the browser

```
https://my-project.ddev.site/contacts        # list view
https://my-project.ddev.site/contacts/add    # create form
https://my-project.ddev.site/contacts/edit/1 # edit form
```

---

## Next steps

- More filter types → [filters.md](filters.md)
- Column formatting and export → [columns.md](columns.md)
- Custom row actions → [row-actions.md](row-actions.md)
- Overriding templates → [templates.md](templates.md)
- CSS theming → [frontend.md](frontend.md)
