<?php

namespace Atwx\SilverstripeFrontdeskKit\Table;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DataList;

/**
 * A named view of a list ("Ansicht"): filter values, an optional query of its
 * own, a sort order and a column selection. Views defined in code
 * (FrontdeskController::defineListViews()) can do everything; views saved by
 * members (SavedListView) store filter values and columns only.
 *
 *   ListView::create('active', 'Aktive')
 *       ->filters(['Status' => 'placed'])
 *       ->query(fn (DataList $list) => $list->filter('InDate:LessThanOrEqual', date('Y-m-d')))
 *       ->sort('InDate DESC')
 *       ->columns(['Title', 'InDate', 'PlacementSchool.Name']);
 */
class ListView
{
    use Injectable;

    /**
     * @var array<string, mixed>
     */
    private array $filters = [];

    /**
     * @var callable|null
     */
    private $query = null;

    private ?string $sort = null;

    /**
     * @var array<int, string>|null
     */
    private ?array $columns = null;

    /**
     * @var array<int, string>|null
     */
    private ?array $exportColumns = null;

    private ?int $savedID = null;

    private bool $deletable = false;

    public function __construct(private string $key, private string $title)
    {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Filter values as in the filter bar (filter name => value; date ranges as
     * "<Name>_From" / "<Name>_To").
     *
     * @param array<string, mixed> $filters
     */
    public function filters(array $filters): static
    {
        $this->filters = $filters;
        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * Query of its own, applied after the filters (e.g. dates relative to today).
     *
     * @param callable(DataList): DataList $query
     */
    public function query(callable $query): static
    {
        $this->query = $query;
        return $this;
    }

    public function applyTo(DataList $list): DataList
    {
        if ($this->query) {
            $list = ($this->query)($list);
        }
        if ($this->sort) {
            $list = $list->orderBy($this->sort);
        }
        return $list;
    }

    public function sort(string $sort): static
    {
        $this->sort = $sort;
        return $this;
    }

    /**
     * Columns of the list and the export, by column name and in this order.
     *
     * @param array<int, string> $names
     */
    public function columns(array $names): static
    {
        $this->columns = array_values($names);
        return $this;
    }

    /**
     * @return array<int, string>|null
     */
    public function getColumns(): ?array
    {
        return $this->columns;
    }

    /**
     * Columns of the export only (default: the columns of the view).
     *
     * @param array<int, string> $names
     */
    public function exportColumns(array $names): static
    {
        $this->exportColumns = array_values($names);
        return $this;
    }

    /**
     * @return array<int, string>|null
     */
    public function getExportColumns(): ?array
    {
        return $this->exportColumns ?? $this->columns;
    }

    /**
     * Marks a view saved by a member (see SavedListView).
     */
    public function setSaved(int $id, bool $deletable): static
    {
        $this->savedID = $id;
        $this->deletable = $deletable;
        return $this;
    }

    public function getSavedID(): ?int
    {
        return $this->savedID;
    }

    public function isDeletable(): bool
    {
        return $this->deletable;
    }
}
