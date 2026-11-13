<?php

namespace Atwx\SilverstripeFrontdeskKit\Controller;

use Atwx\SilverstripeFrontdeskKit\Filter\DateRangeFilter;
use Atwx\SilverstripeFrontdeskKit\Filter\MultiSelectFilter;
use Atwx\SilverstripeFrontdeskKit\Model\RecentView;
use Atwx\SilverstripeFrontdeskKit\Model\SavedListView;
use Atwx\SilverstripeFrontdeskKit\Table\ColumnCollection;
use Atwx\SilverstripeFrontdeskKit\Table\ListView;
use SilverStripe\Control\Controller;
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
        $memberID = (int) Security::getCurrentUser()?->ID;
        foreach (SavedListView::forList($this->listKey()) as $saved) {
            $views[] = ListView::create('saved-' . $saved->ID, (string) $saved->Title)
                ->filters($saved->filterValues())
                ->columns($saved->columnNames() ?? [])
                ->setSaved(
                    (int) $saved->ID,
                    (int) $saved->OwnerID === $memberID || ($saved->Shared && $this->canEdit())
                );
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
        $view = $this->currentListView();
        if (!$view || !$view->getFilters() || $this->defineFilters()->hasAnyRequestParam($request)) {
            return $request;
        }
        $listRequest = new HTTPRequest(
            $request->httpMethod(),
            $request->getURL(),
            array_merge($request->getVars(), $view->getFilters())
        );
        $listRequest->setSession($request->getSession());
        return $listRequest;
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
        foreach ($names as $name) {
            if (isset($byName[$name])) {
                $selected->addColumn($byName[$name]);
            }
        }
        return $selected->count() ? $selected : $columns->forList();
    }

    /**
     * Views for the view bar: "All" first, then the views of the list.
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
            'DeleteLink' => null,
        ]));
        foreach ($views as $view) {
            $result->push(ArrayData::create([
                'Title' => $view->getTitle(),
                'Link' => $this->Link() . '?view=' . rawurlencode($view->getKey()),
                'Active' => $current === $view->getKey(),
                'Saved' => $view->getSavedID() !== null,
                'DeleteLink' => $view->isDeletable() ? $this->Link('deleteview/' . $view->getSavedID()) : null,
            ]));
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
                $names[] = $filter->getName() . '_From';
                $names[] = $filter->getName() . '_To';
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
     * Saves the current filter values and the chosen columns as a view (POST).
     */
    public function saveview(HTTPRequest $request)
    {
        if (!$request->isPOST() || !SecurityToken::inst()->checkRequest($request) || !$this->canSaveListViews()) {
            return $this->httpError(400);
        }
        $title = trim((string) $request->postVar('Title'));
        if ($title === '') {
            $this->flash(_t(self::class . '.VIEW_TITLE_REQUIRED', 'Please enter a name for the view.'), 'error');
            return $this->redirect($this->Link());
        }
        parse_str((string) $request->postVar('Query'), $vars);
        $filters = array_intersect_key($vars, array_flip($this->filterParamNames()));
        $filters = array_filter($filters, fn ($value) => $value !== '' && $value !== null && $value !== []);
        $known = array_map(fn ($column) => $column->getName(), $this->defineColumns()->toArray());
        $columns = array_values(array_intersect((array) $request->postVar('Columns'), $known));
        $view = SavedListView::create([
            'ListKey' => $this->listKey(),
            'Title' => $title,
            'FilterQuery' => http_build_query($filters),
            'Columns' => $columns && count($columns) < count($known) ? json_encode($columns) : null,
            'Shared' => $this->CanShareListViews() && $request->postVar('Shared'),
            'OwnerID' => (int) Security::getCurrentUser()?->ID,
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
        $memberID = (int) Security::getCurrentUser()?->ID;
        if (!$view || !((int) $view->OwnerID === $memberID || ($view->Shared && $this->canEdit()))) {
            return $this->httpError(404);
        }
        $view->delete();
        return $this->redirect($this->Link());
    }
}
