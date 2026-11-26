<?php

namespace Atwx\SilverstripeFrontdeskKit\Filter;

use SilverStripe\Forms\FormField;
use SilverStripe\ORM\DataList;

abstract class Filter
{
    protected string $name;
    protected string $label;
    protected $applyFn = null;
    protected mixed $default = null;

    public function __construct(string $name, string $label)
    {
        $this->name = $name;
        $this->label = $label;
    }

    public function defaultValue(mixed $value): static
    {
        $this->default = $value;
        return $this;
    }

    public function getDefault(): mixed
    {
        return $this->default;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * Set a custom apply callable: fn(DataList $list, mixed $value): DataList
     */
    public function apply(callable $fn): static
    {
        $this->applyFn = $fn;
        return $this;
    }

    /**
     * Apply this filter to a DataList given the request value.
     */
    public function applyToList(DataList $list, mixed $value): DataList
    {
        if (!$value && $value !== 0 && $value !== '0') {
            return $list;
        }

        if ($this->applyFn) {
            return ($this->applyFn)($list, $value);
        }

        return $list;
    }

    /**
     * The value as text for the chip of an active filter ("Status: open").
     */
    public function describe(mixed $value): string
    {
        return is_array($value) ? implode(', ', array_map('strval', $value)) : trim((string) $value);
    }

    /**
     * Return a Silverstripe FormField for this filter.
     */
    abstract public function renderField(): FormField;
}
