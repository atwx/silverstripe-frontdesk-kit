<?php

namespace Atwx\SilverstripeFrontdeskKit;

use SilverStripe\Model\List\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\View\ArrayData;

class ColumnCollection extends ArrayList
{
    /**
     * Build a ColumnCollection from a model's $summary_fields config.
     */
    public static function fromSummaryFields(string $modelClass): static
    {
        $collection = new static();
        $summaryFields = DataObject::singleton($modelClass)->summaryFields();
        foreach ($summaryFields as $name => $label) {
            $collection->add(Column::create($name, $label));
        }
        return $collection;
    }

    /**
     * Start building a new column fluently. Returns a ColumnBuilder.
     * Call ->end() on the builder to return to this collection.
     */
    public function make(string $name, string $label = ''): ColumnBuilder
    {
        $column = Column::create($name, $label);
        $this->add($column);
        return new ColumnBuilder($column, $this);
    }

    public function add(Column $col): static
    {
        parent::push($col);
        return $this;
    }

    /**
     * Render all column values for a given record as an ArrayList of ArrayData.
     */
    public function renderFor(DataObject $record): ArrayList
    {
        $rows = ArrayList::create();
        $isFirst = true;
        foreach ($this->items as $col) {
            $rows->push(ArrayData::create([
                'Column' => $col,
                'Value' => $col->renderValue($record),
                'Link' => $col->renderLink($record),
                'HasLink' => (bool) $col->getLinkPattern(),
                'IsFirst' => $isFirst,
                'Type' => $col->getType(),
            ]));
            $isFirst = false;
        }
        return $rows;
    }

    /**
     * Return only columns marked as visible in export.
     */
    public function forExport(): static
    {
        $collection = new static();
        foreach ($this->items as $col) {
            if ($col->isVisibleInExport()) {
                $collection->add($col);
            }
        }
        return $collection;
    }
}
