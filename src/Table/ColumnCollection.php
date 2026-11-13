<?php

namespace Atwx\SilverstripeFrontdeskKit\Table;

use Atwx\SilverstripeFrontdeskKit\Model\LastView;
use SilverStripe\Control\Controller;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;

class ColumnCollection extends ArrayList
{
    /**
     * @var array{field: string, empty: string, value: callable|null}|null
     */
    private ?array $grouping = null;

    /**
     * Rows of the list grouped by a field: a heading row per value, e.g.
     * schools by state. $field is a field or has_one path ("State",
     * "Region.Title"); records without a value go under $emptyLabel. A database
     * field of the model itself also sorts the list by it first; for other
     * paths sort the list yourself (default_sort). $value(record) may compute
     * the group title instead.
     */
    public function groupBy(string $field, string $emptyLabel = '–', ?callable $value = null): static
    {
        $this->grouping = ['field' => $field, 'empty' => $emptyLabel, 'value' => $value];
        return $this;
    }

    /**
     * @return array{field: string, empty: string, value: callable|null}|null
     */
    public function getGrouping(): ?array
    {
        return $this->grouping;
    }

    /**
     * Title of the record's group ('' when the list is not grouped).
     */
    public function groupTitle(DataObject $record): string
    {
        if (!$this->grouping) {
            return '';
        }
        $value = $this->grouping['value']
            ? ($this->grouping['value'])($record)
            : $record->relField($this->grouping['field']);
        if ($value instanceof DataObject) {
            $value = $value->exists() ? $value->getTitle() : '';
        }
        $title = is_scalar($value) ? trim((string) $value) : '';
        return $title !== '' ? $title : $this->grouping['empty'];
    }

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

    /**
     * Column "last viewed by anyone": date, time and member of the latest
     * view of the record page (LastView, noted in controllers with
     * recent_views), sortable.
     */
    public function lastViewed(string $label = '', string $name = 'LastViewed'): ColumnBuilder
    {
        return $this->make($name, $label ?: _t(LastView::class . '.COLUMN', 'Last viewed'))
            ->format(fn ($value, DataObject $record) => LastView::forRecord($record)?->summary() ?? '')
            ->sortable(fn (DataList $list, string $dir) => LastView::sortList($list, $dir));
    }

    /**
     * The column with this name (e.g. to make a scaffolded column sortable).
     */
    public function byName(string $name): ?Column
    {
        foreach ($this as $column) {
            if ($column instanceof Column && $column->getName() === $name) {
                return $column;
            }
        }
        return null;
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
            $value = $col->renderValue($record);
            $rows->push(ArrayData::create([
                'Column'  => $col,
                'Label'   => $col->getLabel(),
                'Value'   => $value,
                // Hidden in the stacked phone layout
                'IsEmpty' => trim(strip_tags((string) $value)) === '',
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
     * Columns of the list without a view: neither optional nor export-only.
     */
    public function forList(): static
    {
        $collection = new static();
        $collection->grouping = $this->grouping;
        foreach ($this->items as $col) {
            if (!$col->isOptional() && !$col->isExportOnly()) {
                $collection->addColumn($col);
            }
        }
        return $collection;
    }

    /**
     * Columns of the export without a view: all but optional ones.
     */
    public function withoutOptional(): static
    {
        $collection = new static();
        $collection->grouping = $this->grouping;
        foreach ($this->items as $col) {
            if (!$col->isOptional()) {
                $collection->addColumn($col);
            }
        }
        return $collection;
    }

    /**
     * Return only columns marked as visible in export.
     */
    public function forExport(): static
    {
        $collection = new static();
        $collection->grouping = $this->grouping;
        foreach ($this->items as $col) {
            if ($col->isVisibleInExport()) {
                $collection->addColumn($col);
            }
        }
        return $collection;
    }
}
