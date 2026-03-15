<?php

namespace Atwx\SilverstripeFrontdeskKit\Filter;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataList;

class FilterCollection
{
    /** @var Filter[] */
    protected array $filters = [];

    public static function create(): static
    {
        return new static();
    }

    public function add(Filter $filter): static
    {
        $this->filters[] = $filter;
        return $this;
    }

    /**
     * @return Filter[]
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * Apply all filters to the given list using request vars.
     */
    public function applyAll(DataList $list, HTTPRequest $request): DataList
    {
        foreach ($this->filters as $filter) {
            if ($filter instanceof DateRangeFilter) {
                $from = $request->getVar($filter->getName() . '_From');
                $to = $request->getVar($filter->getName() . '_To');
                if ($from || $to) {
                    $list = $filter->applyRange($list, $from, $to);
                }
            } else {
                $value = $request->getVar($filter->getName());
                $list = $filter->applyToList($list, $value);
            }
        }
        return $list;
    }

    /**
     * Build a Silverstripe FieldList from all filter fields.
     */
    public function toFieldList(): FieldList
    {
        $fields = FieldList::create();
        foreach ($this->filters as $filter) {
            $fields->push($filter->renderField());
        }
        return $fields;
    }

    /**
     * Check if any filter value is set in the request.
     */
    public function isActive(HTTPRequest $request): bool
    {
        foreach ($this->filters as $filter) {
            if ($filter instanceof DateRangeFilter) {
                if ($request->getVar($filter->getName() . '_From') || $request->getVar($filter->getName() . '_To')) {
                    return true;
                }
            } elseif ($request->getVar($filter->getName())) {
                return true;
            }
        }
        return false;
    }
}
