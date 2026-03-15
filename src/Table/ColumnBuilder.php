<?php

namespace Atwx\SilverstripeFrontdeskKit\Table;

/**
 * Fluent builder for Column. Created by ColumnCollection::make().
 * Call ->end() to return control to the collection.
 */
class ColumnBuilder
{
    protected Column $column;
    protected ColumnCollection $collection;

    public function __construct(Column $column, ColumnCollection $collection)
    {
        $this->column = $column;
        $this->collection = $collection;
    }

    public function link(string $pattern): static
    {
        $this->column->link($pattern);
        return $this;
    }

    public function label(string $label): static
    {
        $this->column->label($label);
        return $this;
    }

    public function sortable(bool $v = true): static
    {
        $this->column->sortable($v);
        return $this;
    }

    public function type(string $type): static
    {
        $this->column->type($type);
        return $this;
    }

    public function format(callable $fn): static
    {
        $this->column->format($fn);
        return $this;
    }

    public function visibleInExport(bool $v): static
    {
        $this->column->visibleInExport($v);
        return $this;
    }

    public function end(): ColumnCollection
    {
        return $this->collection;
    }
}
