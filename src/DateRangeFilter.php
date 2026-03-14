<?php

namespace Atwx\SilverstripeFrontdeskKit;

use SilverStripe\Forms\DateField;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\Forms\FormField;
use SilverStripe\ORM\DataList;

class DateRangeFilter extends Filter
{
    public static function create(string $name, string $label): static
    {
        return new static($name, $label);
    }

    public function renderField(): FormField
    {
        return FieldGroup::create(
            $this->label,
            DateField::create($this->name . '_From', 'Von'),
            DateField::create($this->name . '_To', 'Bis')
        )->setName($this->name);
    }

    public function applyToList(DataList $list, mixed $value): DataList
    {
        // For date range, we look at _From and _To in the request vars.
        // This is handled specially in FilterCollection.
        return $list;
    }

    /**
     * Apply from/to date range to the list.
     */
    public function applyRange(DataList $list, ?string $from, ?string $to): DataList
    {
        if ($this->applyFn) {
            return ($this->applyFn)($list, ['from' => $from, 'to' => $to]);
        }

        return $list;
    }
}
