# Catalogs

Catalogs are lists of values that admins maintain, such as sports, languages or
school types. Records reference catalog entries through has_one relations
(single choice) or many_many relations (multiple choice).

## Declaring catalogs

```yaml
Atwx\SilverstripeFrontdeskKit\Catalog\Catalogs:
  catalogs:
    school_type: { title: 'Schultyp' }
    sports: { title: 'Sportarten' }
    school_reaction: { title: 'Reaktion der Schule', class: App\Model\SchoolReaction }
```

```php
class Student extends DataObject
{
    private static array $has_one = ['SchoolType' => CatalogEntry::class];
    private static array $many_many = ['Sports' => CatalogEntry::class];

    // relation name => catalog key
    private static array $catalog_fields = ['SchoolType' => 'school_type', 'Sports' => 'sports'];
}
```

What follows from these declarations:

- **Forms:** Frontdesk forms, both edit forms and standalone pages, show a searchable dropdown for has_one fields (`SchoolTypeID`). For many_many fields (`Sports`) they show a searchable multiple choice. Use these names in field layouts.
- **Views:** The record view shows the titles of the chosen entries.
- **List columns:** `->make('SchoolType.Title', 'Schultyp')` shows a has_one entry. `->make('Sports', 'Sportarten')` shows a many_many field; a column whose value is a list shows the titles of its items.
- **Filters:** `CatalogFilter::create('Sports', 'Sportart', 'sports')`, the same for has_one.

## Entries

| Field | |
|---|---|
| `Code` | Stable key, unique in its catalog, for imports and stored values. It is generated from the title when empty and cannot be changed later. |
| `Title` | Name shown everywhere. |
| `Sort` | Order. With equal sort values, entries are sorted by title (alphabetical by default). |
| `Active` | Inactive entries cannot be chosen any more, but stay on the records that use them (shown as "… (inaktiv)"). They also stay in filters. |
| `IsDefault` | Preselected for new records, one per catalog. |

An entry that is in use cannot be deleted; deactivate it instead.
`Catalogs::isInUse()` checks all catalog fields of all classes. Extensions can
add other uses, such as values stored by code, through `updateIsInUse(CatalogEntry $entry, bool &$used)`.

### Catalogs with own columns

```php
class SchoolReaction extends CatalogEntry
{
    private static string $table_name = 'App_SchoolReaction';
    private static ?string $catalog = 'school_reaction';
    private static array $db = [
        'PortalText' => 'Varchar(255)',
        'Outcome' => "Enum('positive,negative', 'positive')",
    ];
}
```

With `class:` set in the catalog's config, new entries are created as this class
and its fields appear in the form.

### Values per assignment

For values per assignment, such as a rating of a sport at a school, use a join
model with a has_one catalog field. Manage it in a sub-controller.

```php
class SchoolSport extends DataObject
{
    private static array $db = ['Rating' => 'Int', 'Comment' => 'Text'];
    private static array $has_one = ['School' => School::class, 'Sport' => CatalogEntry::class];
    private static array $catalog_fields = ['Sport' => 'sports'];
}
```

## Maintenance

The page `catalogs/` (`CatalogController`) lists the entries of one catalog at
a time and lets you add, rename, reorder, deactivate and delete them. Delete is
only offered for entries that are not in use. Changes need the permission
`FDK_CATALOGS_EDIT` ("Edit 'Kataloge'") or ADMIN; viewing needs
`FDK_CATALOGS_VIEW`. Add the page to `main_navigation` where admins should
find it.

## Import

```php
Catalogs::singleton()->sync('sports', [
    'hockey' => 'Hockey',                                   // code => title
    'polo' => ['Title' => 'Polo', 'Active' => false],       // or field values
]);
```

`sync()` creates or updates entries by code and sets `Sort` in the given
order. It returns the entries by code, for example to map legacy IDs.
