<?php

namespace Atwx\SilverstripeFrontdeskKit\Catalog;

use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ManyManyList;
use SilverStripe\Model\List\SS_List;

/**
 * Registry of the catalogs and their use on records.
 *
 * Catalogs are declared in config:
 *
 *   Atwx\SilverstripeFrontdeskKit\Catalog\Catalogs:
 *     catalogs:
 *       sports: { title: 'Sportarten' }
 *       school_reaction: { title: 'Reaktion der Schule', class: App\Model\SchoolReaction }
 *
 * Records reference entries through has_one or many_many relations to CatalogEntry
 * (or the catalog's class), declared as catalog fields:
 *
 *   private static array $has_one = ['SchoolType' => CatalogEntry::class];
 *   private static array $many_many = ['Sports' => CatalogEntry::class];
 *   private static array $catalog_fields = ['SchoolType' => 'school_type', 'Sports' => 'sports'];
 *
 * Frontdesk forms then offer the active entries (plus the record's current ones),
 * views and list columns show titles, CatalogFilter filters by entry.
 */
class Catalogs
{
    use Configurable;
    use Extensible;
    use Injectable;

    /**
     * @var array<string, array{title?: string, class?: class-string<CatalogEntry>}>
     */
    private static array $catalogs = [];

    /**
     * @return array<string, string> catalog key => title
     */
    public function titles(): array
    {
        $titles = [];
        foreach ((array) static::config()->get('catalogs') as $key => $definition) {
            $titles[(string) $key] = (string) ($definition['title'] ?? $key);
        }
        return $titles;
    }

    public function exists(string $key): bool
    {
        return $key !== '' && array_key_exists($key, (array) static::config()->get('catalogs'));
    }

    public function title(string $key): string
    {
        return $this->titles()[$key] ?? $key;
    }

    /**
     * @return class-string<CatalogEntry>
     */
    public function entryClass(string $key): string
    {
        $class = (string) (static::config()->get('catalogs')[$key]['class'] ?? '');
        return $class !== '' && is_subclass_of($class, CatalogEntry::class) ? $class : CatalogEntry::class;
    }

    /**
     * Entries of a catalog that can be chosen (active ones) plus the given ones
     * (the record's current entries, which stay visible when inactive).
     *
     * @param array<int, int> $includeIDs
     * @return DataList<CatalogEntry>
     */
    public function choosable(string $key, array $includeIDs = []): DataList
    {
        $list = CatalogEntry::get()->filter('Catalog', $key);
        $includeIDs = array_values(array_filter(array_map('intval', $includeIDs)));
        return $includeIDs
            ? $list->filterAny(['Active' => true, 'ID' => $includeIDs])
            : $list->filter('Active', true);
    }

    /**
     * All entries of a catalog (for filters: inactive entries stay filterable).
     *
     * @return DataList<CatalogEntry>
     */
    public function all(string $key): DataList
    {
        return CatalogEntry::get()->filter('Catalog', $key);
    }

    /**
     * @param iterable<CatalogEntry> $entries
     * @return array<int, string> ID => label
     */
    public function options(iterable $entries): array
    {
        $options = [];
        foreach ($entries as $entry) {
            $options[(int) $entry->ID] = $entry->getLabel();
        }
        return $options;
    }

    public function defaultEntry(string $key): ?CatalogEntry
    {
        return CatalogEntry::get()->filter(['Catalog' => $key, 'IsDefault' => true, 'Active' => true])->first();
    }

    public function byCode(string $key, string $code): ?CatalogEntry
    {
        return CatalogEntry::get()->filter(['Catalog' => $key, 'Code' => $code])->first();
    }

    /**
     * Catalog fields of a class: relation name => catalog key.
     *
     * @param class-string<DataObject>|DataObject $class
     * @return array<string, string>
     */
    public function fieldsOf(string|DataObject $class): array
    {
        $class = is_string($class) ? $class : $class::class;
        $fields = [];
        foreach ((array) Config::inst()->get($class, 'catalog_fields') as $relation => $key) {
            if ($this->exists((string) $key)) {
                $fields[(string) $relation] = (string) $key;
            }
        }
        return $fields;
    }

    /**
     * 'has_one' or 'many_many' for a relation of the class, null otherwise.
     *
     * @param class-string<DataObject> $class
     */
    public function relationType(string $class, string $relation): ?string
    {
        $schema = DataObject::getSchema();
        if ($schema->hasOneComponent($class, $relation)) {
            return 'has_one';
        }
        if ($schema->manyManyComponent($class, $relation)) {
            return 'many_many';
        }
        return null;
    }

    /**
     * Titles of the entries a record has in a catalog field, comma-separated.
     */
    public function titlesOf(DataObject $record, string $relation): string
    {
        $value = $record->$relation();
        $entries = $value instanceof SS_List ? $value : [$value];
        $titles = [];
        foreach ($entries as $entry) {
            if ($entry instanceof CatalogEntry && $entry->exists()) {
                $titles[] = $entry->getLabel();
            }
        }
        return implode(', ', $titles);
    }

    /**
     * Whether a record of any class with catalog fields uses the entry. Extensions
     * add other uses (e.g. values stored by code) with updateIsInUse().
     */
    public function isInUse(CatalogEntry $entry): bool
    {
        $used = false;
        foreach (ClassInfo::subclassesFor(DataObject::class, false) as $class) {
            foreach ($this->fieldsOf($class) as $relation => $key) {
                if ($key !== $entry->Catalog || DataObject::getSchema()->classHasTable($class) === false) {
                    continue;
                }
                $type = $this->relationType($class, $relation);
                $filter = $type === 'has_one' ? [$relation . 'ID' => $entry->ID] : [$relation . '.ID' => $entry->ID];
                if ($type && $class::get()->filter($filter)->exists()) {
                    $used = true;
                    break 2;
                }
            }
        }
        $this->extend('updateIsInUse', $entry, $used);
        return $used;
    }

    /**
     * Creates or updates entries by code (e.g. for imports). Existing entries keep
     * fields that are not given. Returns the entries by code.
     *
     * @param array<string, string|array<string, mixed>> $entries code => title or field values
     * @return array<string, CatalogEntry>
     */
    public function sync(string $key, array $entries): array
    {
        $class = $this->entryClass($key);
        $result = [];
        $sort = 0;
        foreach ($entries as $code => $values) {
            $values = is_array($values) ? $values : ['Title' => $values];
            $entry = $this->byCode($key, (string) $code) ?? $class::create(['Catalog' => $key, 'Code' => (string) $code]);
            $entry->update(array_merge(['Sort' => ++$sort], $values));
            $entry->write();
            $result[(string) $code] = $entry;
        }
        return $result;
    }

    /**
     * IDs of a record's current entries in a catalog field.
     *
     * @return array<int, int>
     */
    public function currentIDs(DataObject $record, string $relation): array
    {
        $type = $this->relationType($record::class, $relation);
        if ($type === 'has_one') {
            return array_filter([(int) $record->getField($relation . 'ID')]);
        }
        if ($type === 'many_many' && $record->isInDB()) {
            $list = $record->$relation();
            return $list instanceof ManyManyList ? array_map('intval', $list->column('ID')) : [];
        }
        return [];
    }
}
