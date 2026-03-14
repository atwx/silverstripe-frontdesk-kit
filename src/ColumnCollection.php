<?php

namespace Atwx\SilverstripeFrontdeskKit;

use SilverStripe\Control\Controller;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\ORM\DataObject;

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
            $collection->addColumn(Column::create($name, $label));
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
        $this->addColumn($column);
        return new ColumnBuilder($column, $this);
    }

    public function addColumn(Column $col): static
    {
        parent::push($col);
        return $this;
    }

    /**
     * Render all column values for a given record as an ArrayList of ArrayData.
     *
     * @param string $baseUrl Controller base URL, prepended to relative link patterns.
     */
    public function renderFor(DataObject $record, string $baseUrl = ''): ArrayList
    {
        $rows = ArrayList::create();
        $isFirst = true;
        foreach ($this->items as $col) {
            $rawLink = $col->renderLink($record);
            // Prepend the controller base URL to relative link patterns
            if ($rawLink && $baseUrl && !str_starts_with($rawLink, '/') && !str_starts_with($rawLink, 'http')) {
                $link = Controller::join_links($baseUrl, $rawLink);
            } else {
                $link = $rawLink;
            }
            $rows->push(ArrayData::create([
                'Column'  => $col,
                'Value'   => $col->renderValue($record),
                'Link'    => $link,
                'HasLink' => (bool) $rawLink,
                'IsFirst' => $isFirst,
                'Type'    => $col->getType(),
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
                $collection->addColumn($col);
            }
        }
        return $collection;
    }
}
