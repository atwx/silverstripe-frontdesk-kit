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
        $hasAnyFilterParam = $this->hasAnyRequestParam($request);

        foreach ($this->filters as $filter) {
            if ($filter instanceof DateRangeFilter) {
                $range = $filter->rangeFrom(fn (string $name) => $request->getVar($name));
                if ($range) {
                    $list = $filter->applyRange($list, $range['from'], $range['to']);
                }
            } else {
                $value = $request->getVar($filter->getName());
                if ($filter instanceof MultiSelectFilter) {
                    $filter->setRequestMatch($request->getVar($filter->matchParamName()));
                }
                if ($value === null && !$hasAnyFilterParam && $filter->getDefault() !== null) {
                    $value = $filter->getDefault();
                }
                $list = $filter->applyToList($list, $value);
            }
        }
        return $list;
    }

    public function getDefaults(): array
    {
        $defaults = [];
        foreach ($this->filters as $filter) {
            if ($filter->getDefault() !== null) {
                $defaults[$filter->getName()] = $filter->getDefault();
            }
        }
        return $defaults;
    }

    public function hasAnyRequestParam(HTTPRequest $request): bool
    {
        foreach ($this->filters as $filter) {
            if ($filter instanceof DateRangeFilter) {
                foreach ($filter->paramNames() as $param) {
                    if ($request->getVar($param) !== null) {
                        return true;
                    }
                }
            } elseif ($request->getVar($filter->getName()) !== null) {
                return true;
            } elseif ($filter instanceof MultiSelectFilter && $request->getVar($filter->matchParamName()) !== null) {
                return true;
            }
        }
        return false;
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
     * The filters with a value in the request: name => [filter, value,
     * text, params (request vars of the filter)].
     *
     * @return array<string, array{filter: Filter, value: mixed, text: string, params: array<int, string>}>
     */
    public function activeValues(HTTPRequest $request): array
    {
        $active = [];
        foreach ($this->filters as $filter) {
            $name = $filter->getName();
            if ($filter instanceof DateRangeFilter) {
                $value = $filter->rangeFrom(fn (string $param) => $request->getVar($param));
                if (!$value) {
                    continue;
                }
                $params = $filter->paramNames();
            } else {
                $value = $request->getVar($name);
                if ($filter instanceof TreeFilter) {
                    if (TreeFilter::values($value) === []) {
                        continue;
                    }
                } elseif ($value === null || $value === '' || $value === []) {
                    continue;
                }
                $params = [$name];
                if ($filter instanceof MultiSelectFilter && $filter->hasChoosableMatch()) {
                    $params[] = $filter->matchParamName();
                }
            }
            $active[$name] = ['filter' => $filter, 'value' => $value, 'text' => $filter->describe($value), 'params' => $params];
        }
        return $active;
    }

    /**
     * Check if any filter value is set in the request.
     */
    public function isActive(HTTPRequest $request): bool
    {
        foreach ($this->filters as $filter) {
            if ($filter instanceof DateRangeFilter) {
                if ($filter->rangeFrom(fn (string $param) => $request->getVar($param))) {
                    return true;
                }
            } elseif ($request->getVar($filter->getName())) {
                return true;
            }
        }
        return false;
    }
}
