<?php

namespace Atwx\SilverstripeFrontdeskKit\Catalog;

use Atwx\SilverstripeFrontdeskKit\Filter\SelectFilter;
use SilverStripe\ORM\DataList;

/**
 * Filter by a catalog field: records with the chosen entry (has_one) or having
 * it among their entries (many_many). All entries are offered, inactive ones
 * too, so old values stay filterable.
 *
 *   CatalogFilter::create('Sports', 'Sportart', 'sports')
 */
class CatalogFilter extends SelectFilter
{
    public function __construct(string $name, string $label, private string $catalog = '')
    {
        parent::__construct($name, $label);
        $this->searchable = true;
        $this->options(fn () => Catalogs::singleton()->options(Catalogs::singleton()->all($this->catalog)));
    }

    public static function create(string $name, string $label, string $catalog = ''): static
    {
        return new static($name, $label, $catalog);
    }

    public function applyToList(DataList $list, mixed $value): DataList
    {
        if (!$value || !is_numeric($value)) {
            return $list;
        }
        if ($this->applyFn) {
            return ($this->applyFn)($list, $value);
        }
        $type = Catalogs::singleton()->relationType($list->dataClass(), $this->name);
        return match ($type) {
            'has_one' => $list->filter($this->name . 'ID', (int) $value),
            'many_many' => $list->filter($this->name . '.ID', (int) $value),
            default => $list,
        };
    }
}
