<?php

namespace Atwx\SilverstripeFrontdeskKit\Controller;

use Atwx\SilverstripeFrontdeskKit\Forms\ExportColumnsField;
use Atwx\SilverstripeFrontdeskKit\Model\SavedExport;
use Atwx\SilverstripeFrontdeskKit\Table\ColumnCollection;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\Validation\RequiredFieldsValidator;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use SilverStripe\Security\SecurityToken;

/**
 * Exports of a list set up by members (SavedExport): the menu "Export ▾"
 * with the default export, the exports in code (defineExports()) and the
 * saved ones; a page to set them up (savedexports), with the permission
 * <prefix>_EXPORT.
 */
trait SavedExportsTrait
{
    /**
     * Columns members may choose for their exports: by default all columns
     * of the list (also optional and export-only ones). Add more, e.g.
     * fields of related records, as exportOnly() columns in defineColumns()
     * or by overriding this.
     */
    protected function exportableColumns(): ColumnCollection
    {
        return $this->defineColumns();
    }

    /**
     * May the member set up exports of this list (and share them)?
     */
    public function canManageExports(): bool
    {
        if (!$this->canView() || !static::config()->get('saved_exports')) {
            return false;
        }
        return Permission::check([$this->getPermissionCode('EXPORT'), 'ADMIN'], 'any');
    }

    /**
     * Saved exports offered in the current view.
     *
     * @return array<int, SavedExport>
     */
    protected function savedExportsHere(): array
    {
        if (!static::config()->get('saved_exports')) {
            return [];
        }
        $view = $this->currentListView()?->getKey() ?? '';
        return array_values(array_filter(
            SavedExport::forList($this->listKey())->toArray(),
            fn (SavedExport $export) => $export->isAvailableIn($view)
        ));
    }

    /**
     * Entries of the export button or menu: default export, exports in code
     * and saved ones (each a link keeping the current filters), and "Set up
     * export …".
     *
     * @return ArrayList<ArrayData>
     */
    protected function exportEntries(): ArrayList
    {
        $entries = ArrayList::create();
        $query = $this->CurrentQuery();
        $entries->push(ArrayData::create([
            'Title' => _t(FrontdeskController::class . '.ACTION_EXPORT', 'Export'),
            'Link' => $this->Link('export') . '?' . $query,
            'ExtraQuery' => '',
            'Group' => 'code',
        ]));
        $view = $this->currentListView()?->getKey();
        foreach ($this->defineExports() as $export) {
            if (!$export->isAvailableIn($view)) {
                continue;
            }
            $extra = http_build_query(['export' => $export->getKey()]);
            $entries->push(ArrayData::create([
                'Title' => $export->getTitle(),
                'Link' => $this->Link('export') . '?' . ($query !== '' ? $query . '&' : '') . $extra,
                'ExtraQuery' => $extra,
                'Group' => 'code',
            ]));
        }
        foreach ($this->savedExportsHere() as $export) {
            $extra = http_build_query(['saved' => $export->ID]);
            $entries->push(ArrayData::create([
                'Title' => $export->Title,
                'Link' => $this->Link('export') . '?' . ($query !== '' ? $query . '&' : '') . $extra,
                'ExtraQuery' => $extra,
                'Group' => 'saved',
            ]));
        }
        return $entries;
    }

    /**
     * The export entry of the page actions: one button, or (with more
     * exports or the right to set them up) the menu "Export ▾".
     */
    protected function exportAction(): ArrayData
    {
        $entries = $this->exportEntries();
        $manage = $this->canManageExports();
        if ($entries->count() === 1 && !$manage) {
            $entry = $entries->first();
            return ArrayData::create([
                'Title' => $entry->Title,
                'Primary' => false,
                'Link' => $entry->Link,
                'Target' => '_blank',
                'KeepQuery' => true,
                'ExtraQuery' => '',
            ]);
        }
        return ArrayData::create([
            'Title' => _t(FrontdeskController::class . '.ACTION_EXPORT', 'Export'),
            'Primary' => false,
            'MenuItems' => $entries->filter('Group', 'code'),
            'SavedItems' => $entries->filter('Group', 'saved'),
            'ManageLink' => $manage ? $this->Link('savedexports') : null,
            'CreateLink' => $manage ? $this->Link('savedexport/new') . '?from=' . rawurlencode($this->CurrentQuery()) : null,
        ]);
    }

    /**
     * A saved export the member may run in the current view, if any.
     */
    protected function currentSavedExport(int $id): ?SavedExport
    {
        foreach ($this->savedExportsHere() as $export) {
            if ((int) $export->ID === $id) {
                return $export;
            }
        }
        return null;
    }

    /**
     * Records, columns and file name of a saved export. Its filters apply
     * when the list is neither filtered nor searched.
     *
     * @return array{items: DataList<\SilverStripe\ORM\DataObject>, columns: ColumnCollection, fileName: string}|null
     */
    protected function savedExportData(int $id): ?array
    {
        $export = $this->currentSavedExport($id);
        if (!$export) {
            return null;
        }
        $request = $this->getRequest();
        $stored = $export->filterValues();
        $filtered = $this->defineFilters()->hasAnyRequestParam($request) || $this->searchText() !== '';
        if ($stored && !$filtered) {
            $listRequest = new HTTPRequest('GET', $request->getURL(), array_merge($request->getVars(), $stored));
            $listRequest->setSession($request->getSession());
            $this->setRequest($listRequest);
            $items = $this->getQuery();
            $this->setRequest($request);
        } else {
            $items = $this->getQuery();
        }
        $byName = [];
        foreach ($this->exportableColumns() as $column) {
            $byName[$column->getName()] = $column;
        }
        $columns = ColumnCollection::create();
        foreach ($export->columnList() as $entry) {
            $column = $byName[$entry['name']] ?? null;
            if (!$column) {
                continue;
            }
            if ($entry['width']) {
                $column->width($entry['width']);
            }
            $columns->addColumn($column);
        }
        if (!$columns->count()) {
            return null;
        }
        return ['items' => $items, 'columns' => $columns, 'fileName' => $export->exportFileName()];
    }

    // ─── Page "Exports" ──────────────────────────────────────────────────────

    /**
     * The member's and the shared exports of this list.
     *
     * @return mixed
     */
    public function savedexports(HTTPRequest $request)
    {
        if (!$this->canManageExports()) {
            return $this->httpError(403);
        }
        $exports = ArrayList::create();
        foreach (SavedExport::forList($this->listKey()) as $export) {
            $exports->push(ArrayData::create([
                'Export' => $export,
                'Columns' => implode(', ', array_column($export->columnList(), 'name')),
                'Owner' => $export->Owner()->exists() ? $export->Owner()->getName() : '',
                'CanManage' => $export->canManage(),
                'EditLink' => $this->Link('savedexport/' . $export->ID),
                'DeleteLink' => $this->Link('savedexportdelete/' . $export->ID),
                'CopyLink' => $this->Link('savedexportcopy/' . $export->ID),
            ]));
        }
        return $this->customise([
            'Title' => _t(FrontdeskController::class . '.EXPORTS_TITLE', 'Exports: {list}', ['list' => $this->Title()]),
            'Exports' => $exports,
            'NewLink' => $this->Link('savedexport/new'),
            'BackLink' => $this->Link(),
            'Token' => SecurityToken::inst()->getValue(),
        ])->renderWith([FrontdeskController::class . '_savedexports', 'Page']);
    }

    /**
     * Form of a saved export: savedexport/new (?from=<query of the list>) or
     * savedexport/<ID>.
     *
     * @return mixed
     */
    public function savedexport(HTTPRequest $request)
    {
        if (!$this->canManageExports()) {
            return $this->httpError(403);
        }
        $param = (string) $request->param('ID');
        $export = null;
        if ($param !== 'new') {
            $export = SavedExport::forList($this->listKey())->byID((int) $param);
            if (!$export || !$export->canManage()) {
                return $this->httpError(404);
            }
        }
        $form = $this->SavedExportForm();
        if ($export) {
            $form->loadDataFrom([
                'ID' => $export->ID,
                'Title' => $export->Title,
                'FileName' => $export->FileName,
                'Columns' => $export->Columns,
                'Views' => $export->viewKeys(),
                'Shared' => $export->Shared,
                'KeepFilters' => $export->FilterQuery !== null && $export->FilterQuery !== '',
                'FilterQuery' => $export->FilterQuery,
            ]);
        } else {
            $form->loadDataFrom([
                'FilterQuery' => $this->exportFilterQuery((string) $request->getVar('from')),
                'Columns' => json_encode(array_map(
                    fn ($column) => ['name' => $column->getName(), 'width' => null],
                    $this->defineColumns()->forList()->forExport()->toArray()
                )),
            ]);
        }
        return $this->customise([
            'Title' => $export
                ? _t(FrontdeskController::class . '.EXPORT_EDIT', 'Edit export')
                : _t(FrontdeskController::class . '.EXPORT_NEW', 'Set up export'),
            'Form' => $form,
            'Item' => null,
        ])->renderWith([FrontdeskController::class . '_edit', 'Page']);
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps -- form name
    public function SavedExportForm(): Form
    {
        $choices = [];
        foreach ($this->exportableColumns() as $column) {
            $choices[$column->getName()] = (string) $column->getLabel();
        }
        $views = ['' => _t(FrontdeskController::class . '.EXPORT_NO_VIEW', 'Without a view')];
        foreach ($this->allListViews() as $view) {
            $views[$view->getKey()] = $view->getTitle();
        }
        $label = fn (string $key, string $default) => _t(FrontdeskController::class . '.' . $key, $default);
        $fields = FieldList::create(
            HiddenField::create('ID'),
            TextField::create('Title', $label('EXPORT_FIELD_TITLE', 'Title')),
            TextField::create('FileName', $label('EXPORT_FIELD_FILE', 'File name'))
                ->setDescription($label('EXPORT_FIELD_FILE_HINT', 'Without extension; the date and ".xlsx" are added.')),
            ExportColumnsField::create('Columns', $label('EXPORT_FIELD_COLUMNS', 'Columns'), $choices),
            CheckboxSetField::create('Views', $label('EXPORT_FIELD_VIEWS', 'Only in these views'), $views)
                ->setDescription($label('EXPORT_FIELD_VIEWS_HINT', 'None ticked: in every view.')),
            CheckboxField::create('KeepFilters', $label('EXPORT_FIELD_FILTERS', 'Keep the filters and the search')),
            HiddenField::create('FilterQuery'),
            CheckboxField::create('Shared', $label('EXPORT_FIELD_SHARED', 'For everyone'))
        );
        $fields->dataFieldByName('KeepFilters')?->setDescription($label(
            'EXPORT_FIELD_FILTERS_HINT',
            'Applied when the list is neither filtered nor searched; a filtered list is exported as shown.'
        ));
        $form = Form::create($this, 'SavedExportForm', $fields, FieldList::create(
            FormAction::create('doSaveExport', $label('ACTION_SAVE', 'Save'))->addExtraClass('btn-primary'),
            LiteralField::create('Cancel', '<a href="' . htmlspecialchars($this->Link('savedexports')) . '" class="btn">'
                . htmlspecialchars($label('ACTION_CANCEL', 'Cancel')) . '</a>')
        ), RequiredFieldsValidator::create(['Title']));
        $form->setFormAction($this->Link('SavedExportForm'));
        return $form;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function doSaveExport(array $data, Form $form): HTTPResponse
    {
        if (!$this->canManageExports()) {
            return $this->httpError(403);
        }
        $export = null;
        if (!empty($data['ID'])) {
            $export = SavedExport::forList($this->listKey())->byID((int) $data['ID']);
            if (!$export || !$export->canManage()) {
                return $this->httpError(404);
            }
        }
        $export ??= SavedExport::create([
            'ListKey' => $this->listKey(),
            'OwnerID' => (int) Security::getCurrentUser()?->ID,
        ]);
        $known = array_map(fn ($column) => $column->getName(), $this->exportableColumns()->toArray());
        $submitted = (string) ($data['Columns'] ?? '');
        if (trim($submitted) === '' || trim($submitted) === '[]') {
            // Without JavaScript: names one per line
            $submitted = (string) ($data['Columns_names'] ?? '');
        }
        $columns = array_values(array_filter(
            ExportColumnsField::decode($submitted),
            fn (array $entry) => in_array($entry['name'], $known, true)
        ));
        if (!$columns) {
            $form->sessionMessage(_t(FrontdeskController::class . '.EXPORT_NO_COLUMNS', 'Choose at least one column.'), 'bad');
            return $this->redirectBack();
        }
        $viewKeys = array_merge([''], array_map(fn ($view) => $view->getKey(), $this->allListViews()));
        $export->Title = trim((string) $data['Title']);
        $export->FileName = trim((string) ($data['FileName'] ?? ''));
        $export->setColumnList($columns);
        $export->Views = (string) json_encode(array_values(array_intersect(array_map('strval', (array) ($data['Views'] ?? [])), $viewKeys)));
        $export->FilterQuery = !empty($data['KeepFilters']) ? $this->exportFilterQuery((string) ($data['FilterQuery'] ?? '')) : null;
        $export->Shared = !empty($data['Shared']);
        $export->write();
        $this->flash(_t(FrontdeskController::class . '.EXPORT_SAVED', 'Export "{title}" saved.', ['title' => $export->Title]));
        return $this->redirect($this->Link('savedexports'));
    }

    public function savedexportdelete(HTTPRequest $request): HTTPResponse
    {
        $export = $this->managedExportFromPost($request);
        if ($export instanceof HTTPResponse) {
            return $export;
        }
        $export->delete();
        $this->flash(_t(FrontdeskController::class . '.EXPORT_DELETED', 'Export deleted.'));
        return $this->redirect($this->Link('savedexports'));
    }

    /**
     * A copy, the member's own and not shared.
     */
    public function savedexportcopy(HTTPRequest $request): HTTPResponse
    {
        if (!$request->isPOST() || !SecurityToken::inst()->checkRequest($request) || !$this->canManageExports()) {
            return $this->httpError(400);
        }
        $export = SavedExport::forList($this->listKey())->byID((int) $request->param('ID'));
        if (!$export) {
            return $this->httpError(404);
        }
        $copy = $export->duplicate(false);
        $copy->Title = _t(FrontdeskController::class . '.EXPORT_COPY', 'Copy of {title}', ['title' => $export->Title]);
        $copy->OwnerID = (int) Security::getCurrentUser()?->ID;
        $copy->Shared = false;
        $copy->write();
        return $this->redirect($this->Link('savedexport/' . $copy->ID));
    }

    private function managedExportFromPost(HTTPRequest $request): SavedExport|HTTPResponse
    {
        if (!$request->isPOST() || !SecurityToken::inst()->checkRequest($request) || !$this->canManageExports()) {
            return $this->httpError(400);
        }
        $export = SavedExport::forList($this->listKey())->byID((int) $request->param('ID'));
        if (!$export || !$export->canManage()) {
            return $this->httpError(404);
        }
        return $export;
    }

    /**
     * The filter, search and view parameters of a list query string.
     */
    protected function exportFilterQuery(string $query): string
    {
        parse_str($query, $vars);
        $keep = array_merge($this->filterParamNames(), [$this->searchParam(), $this->searchParam() . '_Scope']);
        $vars = array_intersect_key($vars, array_flip($keep));
        $vars = array_filter($vars, fn ($value) => $value !== '' && $value !== null && $value !== [] && $value !== '[]');
        return http_build_query($vars);
    }
}
