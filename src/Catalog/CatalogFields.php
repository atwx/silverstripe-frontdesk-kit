<?php

namespace Atwx\SilverstripeFrontdeskKit\Catalog;

use Atwx\SilverstripeFrontdeskKit\Forms\SearchableDropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataObject;

/**
 * Form fields of a record's catalog fields (see Catalogs): a searchable dropdown
 * for has_one ("SchoolTypeID"), a searchable multiple choice for many_many
 * ("Sports"). Offered are the active entries plus the record's current ones;
 * new records get the catalog's default entry.
 */
class CatalogFields
{
    public static function apply(FieldList $fields, DataObject $record): FieldList
    {
        $catalogs = Catalogs::singleton();
        $labels = $record->fieldLabels(false);
        foreach ($catalogs->fieldsOf($record) as $relation => $key) {
            $type = $catalogs->relationType($record::class, $relation);
            $current = $catalogs->currentIDs($record, $relation);
            $entries = $catalogs->choosable($key, $current);
            $title = $labels[$relation] ?? $catalogs->title($key);

            if ($type === 'has_one') {
                $name = $relation . 'ID';
                $value = $current[0] ?? (!$record->isInDB() ? $catalogs->defaultEntry($key)?->ID : null);
                $field = SearchableDropdownField::create($name, $title, $catalogs->options($entries), $value)
                    ->setHasEmptyDefault(true)
                    ->setEmptyString(_t(SearchableDropdownField::class . '.EMPTY', '– please choose –'));
            } elseif ($type === 'many_many') {
                $name = $relation;
                $field = CatalogMultiField::create($name, $title, $entries, $current);
            } else {
                continue;
            }

            if ($fields->dataFieldByName($name)) {
                $fields->replaceField($name, $field);
            } else {
                $fields->push($field);
            }
        }
        return $fields;
    }
}
