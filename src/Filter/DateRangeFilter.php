<?php

namespace Atwx\SilverstripeFrontdeskKit\Filter;

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
            DateField::create($this->name . '_From', 'From'),
            DateField::create($this->name . '_To', 'To')
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
    /**
     * "01.03.2026 – 31.03.2026", "from 01.03.2026", "to 31.03.2026".
     *
     * @param array{from?: ?string, to?: ?string}|mixed $value
     */
    public function describe(mixed $value): string
    {
        $format = fn (?string $date) => $date ? date('d.m.Y', (int) strtotime($date)) : '';
        $from = $format(is_array($value) ? ($value['from'] ?? null) : null);
        $to = $format(is_array($value) ? ($value['to'] ?? null) : null);
        if ($from !== '' && $to !== '') {
            return $from . ' – ' . $to;
        }
        return $from !== ''
            ? _t(self::class . '.FROM_DATE', 'from {date}', ['date' => $from])
            : _t(self::class . '.TO_DATE', 'to {date}', ['date' => $to]);
    }

    public function applyRange(DataList $list, ?string $from, ?string $to): DataList
    {
        if ($this->applyFn) {
            return ($this->applyFn)($list, ['from' => $from, 'to' => $to]);
        }

        return $list;
    }
}
