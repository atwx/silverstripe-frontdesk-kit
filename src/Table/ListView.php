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
 *
 * A view marked as report() is not shown in the view bar but in the menu
 * "Reports" (and on the reports page), grouped:
 *
 *   ListView::create('returners', 'Rückkehrer')
 *       ->report('Nach der Vermittlung')
 *       ->description('Austritt in den letzten 12 Wochen')
 *       ->filters(['OutDate_Rel' => '-12w..1w'])
 *       ->groupBy('CurrentForm');
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

    private bool $report = false;

    private ?bool $pinned = null;

    private string $reportGroup = '';

    private string $description = '';

    private ?string $groupBy = null;

    private string $search = '';

    private string $searchScope = '';

    private ?string $columnSort = null;

    private string $columnSortDir = 'asc';

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

    /**
     * A report: shown in the menu "Reports" (grouped by $group) and on the
     * reports page instead of the view bar. The bar still shows it while it
     * is the current view.
     */
    public function report(?string $group = null): static
    {
        $this->report = true;
        $this->reportGroup = (string) $group;
        return $this;
    }

    public function isReport(): bool
    {
        return $this->report;
    }

    /**
     * Shown in the view bar (default: views that are no reports).
     */
    public function pinned(bool $pinned = true): static
    {
        $this->pinned = $pinned;
        return $this;
    }

    public function isPinned(): bool
    {
        return $this->pinned ?? !$this->report;
    }

    public function getReportGroup(): string
    {
        return $this->reportGroup;
    }

    /**
     * Short text shown in the reports menu, on the reports page and above
     * the list.
     */
    public function description(string $text): static
    {
        $this->description = $text;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Groups the rows by a column (its name): sorted by it first, a heading
     * row with value and count per group. The export stays ungrouped.
     */
    public function groupBy(?string $column): static
    {
        $this->groupBy = $column !== '' ? $column : null;
        return $this;
    }

    public function getGroupBy(): ?string
    {
        return $this->groupBy;
    }

    /**
     * Search text (and search area) the view starts with; the search field
     * of the list can change it.
     */
    public function search(string $text, string $scope = ''): static
    {
        $this->search = $text;
        $this->searchScope = $scope;
        return $this;
    }

    public function getSearch(): string
    {
        return $this->search;
    }

    public function getSearchScope(): string
    {
        return $this->searchScope;
    }

    /**
     * Sort by a sortable column as in the list header (?sort=&dir=); a click
     * on a header changes it. sort() instead sorts in SQL.
     */
    public function columnSort(?string $column, string $dir = 'asc'): static
    {
        $this->columnSort = $column !== '' ? $column : null;
        $this->columnSortDir = strtolower($dir) === 'desc' ? 'desc' : 'asc';
        return $this;
    }

    public function getColumnSort(): ?string
    {
        return $this->columnSort;
    }

    public function getColumnSortDir(): string
    {
        return $this->columnSortDir;
    }

    /**
     * Request values the view starts with apart from the filters: search
     * and column sort (values in the request win).
     *
     * @return array<string, string>
     */
    public function presetParams(string $searchParam): array
    {
        $params = [];
        if ($this->search !== '') {
            $params[$searchParam] = $this->search;
            if ($this->searchScope !== '') {
                $params[$searchParam . '_Scope'] = $this->searchScope;
            }
        }
        if ($this->columnSort !== null) {
            $params['sort'] = $this->columnSort;
            $params['dir'] = $this->columnSortDir;
        }
        return $params;
    }
}
