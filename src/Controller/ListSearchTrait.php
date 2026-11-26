<?php

namespace Atwx\SilverstripeFrontdeskKit\Controller;

use Atwx\SilverstripeFrontdeskKit\Filter\Filter;
use Atwx\SilverstripeFrontdeskKit\Search\SearchDefinition;
use SilverStripe\Forms\Form;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;

/**
 * Weighted free-text search of a list (defineSearch()) and the compact list
 * toolbar with a filter panel (config filter_panel): search field with
 * search area, view select, button "Filter (n)", panel with the active and
 * suggested filters, "Add filter", chips of the active filters.
 */
trait ListSearchTrait
{
    /**
     * Scores of the current search (ID => score), null without a search.
     *
     * @var array<int, int>|null
     */
    protected ?array $searchScores = null;

    /**
     * The list's free-text search (fields with weights, search areas, rules);
     * null = none. See SearchDefinition.
     */
    protected function defineSearch(): ?SearchDefinition
    {
        return null;
    }

    /**
     * Request var of the search text (config search_param, default "Query");
     * the search area is <param>_Scope.
     */
    public function searchParam(): string
    {
        return (string) (static::config()->get('search_param') ?: 'Query');
    }

    public function searchText(): string
    {
        return trim((string) $this->getRequest()->getVar($this->searchParam()));
    }

    public function searchScope(): string
    {
        $search = $this->defineSearch();
        $scope = (string) $this->getRequest()->getVar($this->searchParam() . '_Scope');
        return $search && isset($search->getScopes()[$scope]) ? $scope : '';
    }

    /**
     * The list limited to the records matching the search; their scores are
     * kept for the order (see applyColumnSort()).
     *
     * @template T of DataObject
     * @param DataList<T> $list
     * @return DataList<T>
     */
    protected function applySearch(DataList $list): DataList
    {
        $this->searchScores = null;
        $search = $this->defineSearch();
        $text = $this->searchText();
        if (!$search || !SearchDefinition::words($text)) {
            return $list;
        }
        $this->searchScores = $search->scores($list, $text, $this->searchScope());
        return $list->filter('ID', array_keys($this->searchScores) ?: [0]);
    }

    /**
     * Ordered by search score (best first) while a search is active and no
     * column sort is chosen.
     *
     * @template T of DataObject
     * @param DataList<T> $list
     * @return DataList<T>|null null: no search order
     */
    protected function applySearchOrder(DataList $list): ?DataList
    {
        if (!$this->searchScores || (string) $this->getRequest()->getVar('sort') !== '') {
            return null;
        }
        return SearchDefinition::orderByScore($list, $this->searchScores);
    }

    public function hasSearch(): bool
    {
        return $this->defineSearch() !== null;
    }

    /**
     * Search field (and search area) at the start of the classic filter bar,
     * for a list with defineSearch() and without filter_panel; none when a
     * filter of that name exists.
     *
     * @return array<int, \SilverStripe\Forms\FormField>
     */
    protected function classicSearchFields(): array
    {
        $box = $this->SearchBox();
        if (!$box) {
            return [];
        }
        foreach ($this->defineFilters()->getFilters() as $filter) {
            if ($filter->getName() === $box->Name) {
                return [];
            }
        }
        $label = _t(FrontdeskController::class . '.SEARCH_PLACEHOLDER', 'Search');
        $fields = [
            \SilverStripe\Forms\TextField::create($box->Name, $label)
                ->setInputType('search')
                ->setAttribute('autocomplete', 'off'),
        ];
        if ($box->Scopes->count()) {
            $options = [];
            foreach ($box->Scopes as $scope) {
                $options[$scope->Key] = $scope->Title;
            }
            $fields[] = \SilverStripe\Forms\DropdownField::create(
                $box->ScopeName,
                _t(FrontdeskController::class . '.SEARCH_SCOPE', 'Search in'),
                $options
            );
        }
        return $fields;
    }

    // ─── Global search ───────────────────────────────────────────────────────

    /**
     * A record as a hit of the global search: Title, Text (a short line
     * below), Link, Image (URL) and Icon (Lucide name), the last three
     * optional. Override for a better second line or a picture.
     *
     * @return array<string, string|null>
     */
    protected function defineSearchHit(DataObject $record): array
    {
        return [
            'Title' => (string) $record->getTitle(),
            'Text' => '',
            'Link' => $this->Link('view/' . $record->ID),
            'Image' => null,
            'Icon' => null,
        ];
    }

    /**
     * Group of this list in the global search: key, type (list title),
     * count of all matches, link to the list with the search, best hits.
     *
     * @return array{key: string, type: string, count: int, link: string, hits: array<int, array<string, string|null>>, hit_template: string, group_template: string}
     */
    public function globalSearchResult(string $text, int $limit): array
    {
        $search = $this->defineSearch();
        $list = $this->getBaseList();
        $scores = $search ? $search->scores($list, $text) : [];
        $hits = [];
        if ($scores) {
            $ids = array_slice(array_keys($scores), 0, max($limit * 3, $limit));
            $records = $list->filter('ID', $ids)->toArray();
            $byID = [];
            foreach ($records as $record) {
                $byID[(int) $record->ID] = $record;
            }
            foreach ($ids as $id) {
                $record = $byID[$id] ?? null;
                if (!$record || (static::config()->get('check_record_permissions') && !$record->canView())) {
                    continue;
                }
                $hits[] = array_merge(['Image' => null, 'Icon' => null, 'Text' => ''], $this->defineSearchHit($record));
                if (count($hits) >= $limit) {
                    break;
                }
            }
        }
        return [
            'key' => (string) static::config()->get('url_segment'),
            'type' => (string) static::config()->get('title'),
            'count' => count($scores),
            'link' => $this->Link() . '?' . http_build_query([$this->searchParam() => $text, 'view' => 'all']),
            'hits' => $hits,
            'hit_template' => (string) static::config()->get('search_hit_template'),
            'group_template' => (string) static::config()->get('search_group_template'),
        ];
    }

    // ─── Toolbar and filter panel (config filter_panel) ──────────────────────

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps -- template method
    public function FilterPanelEnabled(): bool
    {
        return (bool) static::config()->get('filter_panel');
    }

    /**
     * Search field of the toolbar: name, value and the search areas.
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps -- template method
    public function SearchBox(): ?ArrayData
    {
        $search = $this->defineSearch();
        if (!$search) {
            return null;
        }
        $scope = $this->searchScope();
        $scopes = ArrayList::create();
        if ($search->getScopes()) {
            $scopes->push(ArrayData::create([
                'Key' => '',
                'Title' => _t(FrontdeskController::class . '.SEARCH_SCOPE_ALL', 'All fields'),
                'Selected' => $scope === '',
            ]));
            foreach ($search->getScopes() as $key => $title) {
                $scopes->push(ArrayData::create(['Key' => $key, 'Title' => $title, 'Selected' => $scope === $key]));
            }
        }
        return ArrayData::create([
            'Name' => $this->searchParam(),
            'ScopeName' => $this->searchParam() . '_Scope',
            'Value' => $this->searchText(),
            'Scopes' => $scopes,
        ]);
    }

    /**
     * Names of the filters shown in the panel without a value (config
     * suggested_filters).
     *
     * @return array<int, string>
     */
    protected function suggestedFilters(): array
    {
        return array_values((array) static::config()->get('suggested_filters'));
    }

    /**
     * Filters with a value in the list request (view presets included).
     *
     * @return array<string, array{filter: Filter, value: mixed, text: string, params: array<int, string>}>
     */
    protected function activeFilterValues(): array
    {
        $active = $this->defineFilters()->activeValues($this->listRequest());
        unset($active[$this->searchParam()]);
        return $active;
    }

    /**
     * The filters of the panel: label, form field, whether it is shown
     * (active or suggested) and active.
     *
     * @return ArrayList<ArrayData>
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps -- template method
    public function PanelFilters(): ArrayList
    {
        $form = $this->FilterForm();
        $active = $this->activeFilterValues();
        $suggested = $this->suggestedFilters();
        $result = ArrayList::create();
        foreach ($this->defineFilters()->getFilters() as $filter) {
            $name = $filter->getName();
            if ($name === $this->searchParam()) {
                continue;
            }
            $field = $form ? $this->filterFormField($form, $name) : null;
            if (!$field) {
                continue;
            }
            $isActive = isset($active[$name]);
            $result->push(ArrayData::create([
                'Name' => $name,
                'Label' => $filter->getLabel(),
                'FormField' => $field,
                'Active' => $isActive,
                'Shown' => $isActive || in_array($name, $suggested, true),
            ]));
        }
        return $result;
    }

    /**
     * The top-level field of the filter form that belongs to a filter: the
     * field of that name, or a composite field holding it (e.g. a multiple
     * choice with its field "match all").
     */
    protected function filterFormField(Form $form, string $name): ?\SilverStripe\Forms\FormField
    {
        foreach ($form->Fields() as $field) {
            if ($field->getName() === $name) {
                return $field;
            }
            if ($field instanceof \SilverStripe\Forms\CompositeField && $field->fieldByName($name)) {
                return $field;
            }
        }
        return null;
    }

    /**
     * Chips of the active filters with a link that removes the filter.
     *
     * @return ArrayList<ArrayData>
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps -- template method
    public function ActiveFilterChips(): ArrayList
    {
        $active = $this->activeFilterValues();
        $request = $this->listRequest();
        $keep = [];
        foreach ($active as $entry) {
            foreach ($entry['params'] as $param) {
                $keep[$param] = $request->getVar($param);
            }
        }
        foreach (['view', $this->searchParam(), $this->searchParam() . '_Scope', 'sort', 'dir'] as $param) {
            $value = $this->getRequest()->getVar($param);
            if ($value !== null && $value !== '') {
                $keep[$param] = $value;
            }
        }
        $chips = ArrayList::create();
        foreach ($active as $entry) {
            $vars = array_diff_key($keep, array_flip($entry['params']));
            // The filters were chosen: a view's presets are not applied again.
            $vars['filtered'] = '1';
            $chips->push(ArrayData::create([
                'Label' => $entry['filter']->getLabel(),
                'Value' => $entry['text'],
                'RemoveLink' => $this->Link() . '?' . http_build_query($vars),
            ]));
        }
        return $chips;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps -- template method
    public function ActiveFilterCount(): int
    {
        return count($this->activeFilterValues());
    }

    /**
     * Hidden fields of the filter form (view, marker "filtered").
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps -- template method
    public function FilterHiddenFields(): ArrayList
    {
        $form = $this->FilterForm();
        return $form instanceof Form ? ArrayList::create($form->Fields()->HiddenFields()->toArray()) : ArrayList::create();
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps -- template method
    public function ActiveListViewEntry(): ?ArrayData
    {
        foreach ($this->ListViews() as $view) {
            if ($view->Active) {
                return $view;
            }
        }
        return null;
    }
}
