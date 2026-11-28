<?php

namespace Atwx\SilverstripeFrontdeskKit\Controller;

use Atwx\SilverstripeFrontdeskKit\Filter\DateRangeFilter;
use Atwx\SilverstripeFrontdeskKit\Filter\MultiSelectFilter;
use Atwx\SilverstripeFrontdeskKit\Model\ListColumnChoice;
use Atwx\SilverstripeFrontdeskKit\Model\RecentView;
use Atwx\SilverstripeFrontdeskKit\Model\SavedListView;
use Atwx\SilverstripeFrontdeskKit\Table\ColumnCollection;
use Atwx\SilverstripeFrontdeskKit\Table\ListView;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\Model\ArrayData;
use SilverStripe\Security\Security;
use SilverStripe\Security\SecurityToken;

/**
 * List views ("Ansichten") of a FrontdeskController: views defined in code
 * and views saved by members. See docs/list-views.md.
 */
trait ListViewsTrait
{

    /**
     * Views of the list defined in code ("Ansichten"), shown above the filter bar.
     *
     * @return array<int, ListView>
     */
    protected function defineListViews(): array
    {
        return [];
    }

    /**
     * Key under which members' saved views of this list are stored.
     */
    protected function listKey(): string
    {
        return (string) static::config()->get('url_segment');
    }

    /**
     * Views defined in code, then saved views (shared and own).
     *
     * @return array<int, ListView>
     */
    protected function allListViews(): array
    {
        $views = $this->defineListViews();
        if (static::config()->get('recent_views')) {
            array_unshift($views, ListView::create(
                'recent',
                _t(FrontdeskController::class . '.RECENT_VIEWS', 'Recently viewed')
            )->query(fn ($list) => RecentView::applyTo($list)));
        }
        foreach (SavedListView::forList($this->listKey()) as $saved) {
            $views[] = ListView::create('saved-' . $saved->ID, (string) $saved->Title)
                ->filters($saved->filterValues())
                ->columns($saved->columnNames() ?? [])
                ->search((string) $saved->Search, (string) $saved->SearchScope)
                ->columnSort($saved->SortColumn, (string) $saved->SortDir)
                ->groupBy($saved->GroupBy)
                ->description((string) $saved->Description)
                ->report($saved->Shared
                    ? _t(FrontdeskController::class . '.REPORTS_SHARED', 'Shared reports')
                    : _t(FrontdeskController::class . '.REPORTS_OWN', 'My reports'))
                ->pinned((bool) $saved->Pinned)
                ->setSaved((int) $saved->ID, $saved->canManageIn($this->canEdit()));
        }
        return $views;
    }

    /**
     * Key of the view the list opens with (config default_list_view, e.g.
     * 'recent'); '' = all records. "All" is then reached with ?view=all.
     */
    protected function defaultListViewKey(): string
    {
        return (string) static::config()->get('default_list_view');
    }

    /**
     * Key of the current view: ?view=<key>, the default view without one,
     * '' for all records (?view=all).
     */
    protected function currentListViewKey(): string
    {
        $request = $this->getRequest();
        $key = $request ? (string) $request->getVar('view') : '';
        if ($key === 'all') {
            return '';
        }
        return $key !== '' ? $key : $this->defaultListViewKey();
    }

    /**
     * The view chosen with ?view=<key> (or the default view), if any.
     */
    protected function currentListView(): ?ListView
    {
        $key = $this->currentListViewKey();
        if ($key === '') {
            return null;
        }
        foreach ($this->allListViews() as $view) {
            if ($view->getKey() === $key) {
                return $view;
            }
        }
        return null;
    }

    /**
     * The request the list is filtered with: when a view was just chosen (no
     * filter values in the request), its filter values are used.
     */
    protected function listRequest(): HTTPRequest
    {
        $request = $this->getRequest();
        $vars = $request->getVars();
        $merged = $this->listVarsWithView($vars, $this->currentListView());
        if ($merged === $vars) {
            return $request;
        }
        $listRequest = new HTTPRequest($request->httpMethod(), $request->getURL(), $merged);
        $listRequest->setSession($request->getSession());
        return $listRequest;
    }

    /**
     * Request values with those of the view: its filter values when none are
     * given (and the filters were not just chosen), its search and column
     * sort unless given.
     *
     * @param array<string, mixed> $vars
     * @return array<string, mixed>
     */
    protected function listVarsWithView(array $vars, ?ListView $view): array
    {
        if (!$view) {
            return $vars;
        }
        $preset = [];
        if ($view->getFilters() && empty($vars['filtered'])
            && !$this->defineFilters()->hasAnyRequestParam(new HTTPRequest('GET', '', $vars))) {
            $preset = $view->getFilters();
        }
        foreach ($view->presetParams($this->searchParam()) as $name => $value) {
            if (!array_key_exists($name, $vars)) {
                $preset[$name] = $value;
            }
        }
        return $preset ? array_merge($vars, $preset) : $vars;
    }

    /**
     * The view of the key ('' = the default view, 'all' = none).
     */
    protected function listViewByKey(string $key): ?ListView
    {
        $key = $key === '' ? $this->defaultListViewKey() : ($key === 'all' ? '' : $key);
        foreach ($key !== '' ? $this->allListViews() : [] as $view) {
            if ($view->getKey() === $key) {
                return $view;
            }
        }
        return null;
    }

    /**
     * Columns of a collection by name, in the given order (null: all).
     *
     * @param array<int, string>|null $names
     */
    protected function selectColumns(ColumnCollection $columns, ?array $names): ColumnCollection
    {
        if (!$names) {
            return $columns;
        }
        $byName = [];
        foreach ($columns as $column) {
            $byName[$column->getName()] = $column;
        }
        $selected = ColumnCollection::create();
        $grouping = $columns->getGrouping();
        if ($grouping) {
            $selected->groupBy($grouping['field'], $grouping['empty'], $grouping['value'], $grouping['sort']);
        }
        foreach ($names as $name) {
            if (isset($byName[$name])) {
                $selected->addColumn($byName[$name]);
            }
        }
        return $selected->count() ? $selected : $columns->forList();
    }

    /**
     * Groups the columns by the column the current view groups by (its
     * rendered value as title; sorted by it if it is a database field).
     */
    protected function applyListViewGrouping(ColumnCollection $columns): ColumnCollection
    {
        $name = $this->currentListView()?->getGroupBy();
        if (!$name) {
            return $columns;
        }
        foreach ($this->defineColumns() as $column) {
            if ($column->getName() === $name) {
                $isField = (bool) \SilverStripe\ORM\DataObject::getSchema()
                    ->fieldSpec($this->getManagedModel(), $name);
                return $columns->groupBy(
                    $name,
                    '–',
                    fn ($record) => trim(html_entity_decode(strip_tags((string) $column->renderValue($record)))),
                    $isField
                );
            }
        }
        return $columns;
    }

    /**
     * Views for the view bar: "All" first, then the pinned views of the list,
     * and the current view if it is a report (so the bar shows where one is).
     */
    public function ListViews(): ArrayList
    {
        $current = $this->currentListViewKey();
        $views = $this->allListViews();
        $result = ArrayList::create();
        if (!$views && !$this->canSaveListViews()) {
            return $result;
        }
        $result->push(ArrayData::create([
            'Title' => _t(self::class . '.VIEW_ALL', 'All'),
            'Link' => $this->defaultListViewKey() !== '' ? $this->Link() . '?view=all' : $this->Link(),
            'Active' => $current === '',
            'Saved' => false,
            'Report' => false,
            'DeleteLink' => null,
        ]));
        foreach ($views as $view) {
            $active = $current === $view->getKey();
            if (!$view->isPinned() && !$active) {
                continue;
            }
            $result->push($this->listViewEntry($view, $active));
        }
        return $result;
    }

    private function listViewEntry(ListView $view, bool $active): ArrayData
    {
        return ArrayData::create([
            'Key' => $view->getKey(),
            'Title' => $view->getTitle(),
            'Description' => $view->getDescription(),
            'Link' => $this->Link() . '?view=' . rawurlencode($view->getKey()),
            'Active' => $active,
            'Saved' => $view->getSavedID() !== null,
            'Report' => $view->isReport(),
            'DeleteLink' => $view->isDeletable() ? $this->Link('deleteview/' . $view->getSavedID()) : null,
        ]);
    }

    /**
     * Reports of the list for the menu "Reports": groups (Title, Reports)
     * of views marked report() and saved views; empty without reports.
     *
     * @return ArrayList<ArrayData>
     */
    public function ListReports(): ArrayList
    {
        $current = $this->currentListViewKey();
        $groups = [];
        foreach ($this->allListViews() as $view) {
            if ($view->isReport()) {
                $groups[$view->getReportGroup()][] = $this->listViewEntry($view, $current === $view->getKey());
            }
        }
        $result = ArrayList::create();
        foreach ($groups as $title => $entries) {
            $result->push(ArrayData::create(['Title' => (string) $title, 'Reports' => ArrayList::create($entries)]));
        }
        return $result;
    }

    /**
     * Description of the current view, shown above the list.
     */
    public function CurrentListViewDescription(): string
    {
        return $this->currentListView()?->getDescription() ?? '';
    }

    /**
     * The current view if it is a saved one the member may update.
     */
    public function UpdatableListView(): ?SavedListView
    {
        $id = $this->currentListView()?->getSavedID();
        $saved = $id ? SavedListView::get()->filter(['ID' => $id, 'ListKey' => $this->listKey()])->first() : null;
        return $saved && $saved->canManageIn($this->canEdit()) ? $saved : null;
    }

    /**
     * Columns to group by when saving a view (selected: the current grouping).
     *
     * @return ArrayList<ArrayData>
     */
    public function ListViewGroupColumns(): ArrayList
    {
        $current = $this->currentListView()?->getGroupBy();
        $result = ArrayList::create();
        foreach ($this->defineColumns() as $column) {
            if (!$column->isExportOnly()) {
                $result->push(ArrayData::create([
                    'Name' => $column->getName(),
                    'Title' => $column->getLabel(),
                    'Selected' => $current === $column->getName(),
                ]));
            }
        }
        return $result;
    }

    public function canSaveListViews(): bool
    {
        return $this->canView() && Security::getCurrentUser() !== null;
    }

    public function CanShareListViews(): bool
    {
        return $this->canEdit();
    }

    /**
     * Columns to choose from when saving a view (checked: shown now).
     */
    public function ListViewColumns(): ArrayList
    {
        $shown = [];
        foreach ($this->Columns() as $column) {
            $shown[$column->getName()] = true;
        }
        $result = ArrayList::create();
        foreach ($this->defineColumns() as $column) {
            $result->push(ArrayData::create([
                'Name' => $column->getName(),
                'Title' => $column->getLabel(),
                'Checked' => isset($shown[$column->getName()]),
            ]));
        }
        return $result;
    }

    // ─── Column choice ("Spalten" menu) ───────────────────────────────────────

    /**
     * Key the column choice is stored under (default: the list). Override to
     * store it per context, e.g. 'campaign-' . $this->getParentID().
     */
    protected function columnChoiceKey(): string
    {
        return 'list:' . $this->listKey();
    }

    /**
     * true: one choice for everyone using the key (only editors may change it);
     * false (default): each member chooses for themselves.
     */
    protected function columnChoiceShared(): bool
    {
        return false;
    }

    /**
     * Columns offered in the menu: all columns of defineColumns() that are not
     * export-only, including optional() ones (which are hidden by default).
     * Columns built from data (e.g. form questions) belong in defineColumns().
     */
    protected function choosableColumns(): ColumnCollection
    {
        $choosable = ColumnCollection::create();
        foreach ($this->defineColumns() as $column) {
            if (!$column->isExportOnly()) {
                $choosable->addColumn($column);
            }
        }
        return $choosable;
    }

    protected function columnChoiceMemberID(): int
    {
        return $this->columnChoiceShared() ? 0 : (int) Security::getCurrentUser()?->ID;
    }

    /**
     * Names of the chosen columns, null when nothing was chosen.
     *
     * @return array<int, string>|null
     */
    protected function chosenColumnNames(): ?array
    {
        if (!static::config()->get('column_choice')) {
            return null;
        }
        $memberID = $this->columnChoiceMemberID();
        if (!$memberID && !$this->columnChoiceShared()) {
            return null;
        }
        return ListColumnChoice::find($this->columnChoiceKey(), $memberID)?->columnNames();
    }

    /**
     * Whether the "Spalten" menu is shown: enabled (config column_choice), more
     * than one column to choose, no view with its own columns, and allowed
     * (shared choices only for editors).
     */
    public function CanChooseColumns(): bool
    {
        if (!static::config()->get('column_choice') || !Security::getCurrentUser() || !$this->canView()) {
            return false;
        }
        if ($this->currentListView()?->getColumns()) {
            return false;
        }
        if ($this->columnChoiceShared() && !$this->canEdit()) {
            return false;
        }
        return $this->choosableColumns()->count() > 1;
    }

    public function ColumnChoiceIsShared(): bool
    {
        return $this->columnChoiceShared();
    }

    /**
     * Entries of the "Spalten" menu (checked: shown now).
     */
    public function ColumnChoice(): ArrayList
    {
        $shown = [];
        foreach ($this->Columns() as $column) {
            $shown[$column->getName()] = true;
        }
        $result = ArrayList::create();
        foreach ($this->choosableColumns() as $column) {
            $result->push(ArrayData::create([
                'Name' => $column->getName(),
                'Title' => $column->getLabel(),
                'Checked' => isset($shown[$column->getName()]),
            ]));
        }
        return $result;
    }

    /**
     * Stores the columns chosen in the menu (POST: Columns[]; reset=1 goes
     * back to the default columns) and returns to the list.
     */
    public function choosecolumns(HTTPRequest $request)
    {
        if (!$request->isPOST() || !SecurityToken::inst()->checkRequest($request) || !$this->CanChooseColumns()) {
            return $this->httpError(400);
        }
        $key = $this->columnChoiceKey();
        $memberID = $this->columnChoiceMemberID();
        $choice = ListColumnChoice::find($key, $memberID);
        $chosen = array_flip(array_map('strval', (array) $request->postVar('Columns')));
        $names = [];
        foreach ($this->choosableColumns() as $column) {
            if (isset($chosen[$column->getName()])) {
                $names[] = $column->getName();
            }
        }
        if ($request->postVar('reset') || !$names) {
            $choice?->delete();
        } else {
            $choice ??= ListColumnChoice::create(['ChoiceKey' => $key, 'MemberID' => $memberID]);
            $choice->Columns = json_encode($names);
            $choice->write();
        }
        $back = (string) $request->postVar('BackURL');
        return $this->redirect(Director::is_site_url($back) ? $back : $this->Link());
    }

    /**
     * Names of the request vars that belong to the filters.
     *
     * @return array<int, string>
     */
    protected function filterParamNames(): array
    {
        $names = [];
        foreach ($this->defineFilters()->getFilters() as $filter) {
            if ($filter instanceof DateRangeFilter) {
                array_push($names, ...$filter->paramNames());
            } else {
                $names[] = $filter->getName();
            }
            if ($filter instanceof MultiSelectFilter && $filter->hasChoosableMatch()) {
                $names[] = $filter->matchParamName();
            }
        }
        return $names;
    }

    /**
     * Saves the current state of the list as a view (POST): filter values,
     * search, column sort (from Query), columns, grouping, name, description,
     * shared, pinned. With UpdateID the saved view is overwritten instead
     * (own views, shared ones by members who may edit).
     */
    public function saveview(HTTPRequest $request)
    {
        if (!$request->isPOST() || !SecurityToken::inst()->checkRequest($request) || !$this->canSaveListViews()) {
            return $this->httpError(400);
        }
        $view = null;
        if ($request->postVar('UpdateID')) {
            $view = SavedListView::get()->filter([
                'ID' => (int) $request->postVar('UpdateID'),
                'ListKey' => $this->listKey(),
            ])->first();
            if (!$view || !$view->canManageIn($this->canEdit())) {
                return $this->httpError(404);
            }
        }
        $title = trim((string) $request->postVar('Title'));
        if ($title === '') {
            $this->flash(_t(self::class . '.VIEW_TITLE_REQUIRED', 'Please enter a name for the view.'), 'error');
            return $this->redirect($this->Link());
        }
        parse_str((string) $request->postVar('Query'), $vars);
        // Saved from a view: with the values the view applies
        $vars = $this->listVarsWithView($vars, $this->listViewByKey(is_scalar($vars['view'] ?? null) ? (string) $vars['view'] : ''));
        $filters = array_intersect_key($vars, array_flip($this->filterParamNames()));
        $filters = array_filter($filters, fn ($value) => $value !== '' && $value !== null && $value !== []);
        $columns = $this->defineColumns();
        $known = array_map(fn ($column) => $column->getName(), $columns->toArray());
        $chosen = array_values(array_intersect((array) $request->postVar('Columns'), $known));
        $sortColumn = is_scalar($vars['sort'] ?? null) ? (string) $vars['sort'] : '';
        $sortable = false;
        foreach ($columns as $column) {
            $sortable = $sortable || ($column->getName() === $sortColumn && $column->isSortable());
        }
        $groupBy = (string) $request->postVar('GroupBy');
        $search = is_scalar($vars[$this->searchParam()] ?? null) ? trim((string) $vars[$this->searchParam()]) : '';
        $scope = is_scalar($vars[$this->searchParam() . '_Scope'] ?? null)
            ? (string) $vars[$this->searchParam() . '_Scope'] : '';
        $view ??= SavedListView::create([
            'ListKey' => $this->listKey(),
            'OwnerID' => (int) Security::getCurrentUser()?->ID,
        ]);
        $view->update([
            'Title' => $title,
            'Description' => trim((string) $request->postVar('Description')),
            'FilterQuery' => http_build_query($filters),
            'Columns' => $chosen && count($chosen) < count($known) ? json_encode($chosen) : null,
            'Search' => $this->hasSearch() ? $search : '',
            'SearchScope' => $this->hasSearch() && $search !== '' ? $scope : '',
            'SortColumn' => $sortable ? $sortColumn : '',
            'SortDir' => $sortable && strtolower((string) ($vars['dir'] ?? '')) === 'desc' ? 'desc' : 'asc',
            'GroupBy' => in_array($groupBy, $known, true) ? $groupBy : '',
            'Shared' => $this->CanShareListViews() && $request->postVar('Shared'),
            'Pinned' => (bool) $request->postVar('Pinned'),
        ]);
        $view->write();
        return $this->redirect($this->Link() . '?view=saved-' . $view->ID);
    }

    /**
     * Deletes a saved view (POST): own views, shared views by members who may edit.
     */
    public function deleteview(HTTPRequest $request)
    {
        if (!$request->isPOST() || !SecurityToken::inst()->checkRequest($request)) {
            return $this->httpError(400);
        }
        $view = SavedListView::get()->filter([
            'ID' => (int) $request->param('ID'),
            'ListKey' => $this->listKey(),
        ])->first();
        if (!$view || !$view->canManageIn($this->canEdit())) {
            return $this->httpError(404);
        }
        $view->delete();
        return $this->redirect($this->Link());
    }
}
