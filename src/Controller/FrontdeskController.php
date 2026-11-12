<?php

namespace Atwx\SilverstripeFrontdeskKit\Controller;

use Atwx\SilverstripeFrontdeskKit\Filter\FilterCollection;
use Atwx\SilverstripeFrontdeskKit\Forms\FieldLayout;
use Atwx\SilverstripeFrontdeskKit\Forms\SearchableDropdownField;
use Atwx\SilverstripeFrontdeskKit\Table\BulkAction;
use Atwx\SilverstripeFrontdeskKit\Table\ColumnCollection;
use Atwx\SilverstripeFrontdeskKit\Table\RecordAction;
use Atwx\SilverstripeFrontdeskKit\Table\RowAction;
use Atwx\SilverstripeFrontdeskKit\View\RecordPage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\i18n\i18n;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\PaginatedList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\Security\Security;
use SilverStripe\Security\SecurityToken;

class FrontdeskController extends Controller implements PermissionProvider
{
    use RecordActionsTrait;
    use RecordPagesTrait;
    use ListViewsTrait;

    private static $managed_model = null;
    private static $url_segment = null;
    private static $title = '';
    private static $logo = null; // Configured via YAML; read by FrontdeskTemplateProvider
    private static $page_length = 30;

    /**
     * Main navigation entries, keyed by arbitrary identifier. Each entry:
     *   - title: label shown in the nav
     *   - segment: URL path (used for both link and active-match)
     *   - controller: FQCN of the target controller; used for canView() filtering
     * Configure via YAML on FrontdeskController. Entries are rendered in the
     * order they appear in the merged config.
     */
    private static $main_navigation = [];

    /**
     * When true, records are checked with their own canView/canEdit/canDelete/
     * canCreate before they are shown, edited, saved or deleted. Off by default
     * for backwards compatibility; recommended for new projects.
     */
    private static $check_record_permissions = false;

    /**
     * When true, sub-controllers are shown as tabs of the record (each one a
     * full list page with filters, bulk actions and export) instead of inline
     * sublists below the record view. The record view becomes the first tab.
     */
    private static $sub_controller_tabs = false;

    /**
     * Whether the delete action accepts GET requests. The built-in UI deletes via
     * HTMX DELETE; disable this to protect against CSRF via links.
     */
    private static $allow_get_delete = true;

    /**
     * Set to false for lists whose records are created elsewhere (no "New",
     * the add action answers 403).
     */
    private static $allow_add = true;

    private static $allowed_actions = [
        'index',
        'EditForm',
        'view',
        'edit'   => '->canEdit',
        'add'    => '->canEdit',
        'save'         => '->canEdit',
        'savecontinue' => '->canEdit',
        'delete'       => '->canEdit',
        'export',
        'bulk'         => '->canEdit',
        'recordaction',
        'saveview',
        'deleteview',
    ];

    public function init()
    {
        parent::init();

        $this->templates['index'] = [
            static::class,
            FrontdeskController::class,
            'Page',
        ];
        $this->templates['view'] = [
            static::class . '_view',
            FrontdeskController::class . '_view',
            FrontdeskController::class,
            'Page',
        ];
        $this->templates['edit'] = [
            static::class . '_edit',
            FrontdeskController::class . '_edit',
            FrontdeskController::class,
            'Page',
        ];
        $this->templates['add'] = [
            static::class . '_add',
            static::class . '_edit',
            FrontdeskController::class . '_add',
            FrontdeskController::class . '_edit',
            FrontdeskController::class,
            'Page',
        ];
    }

    public function handleRequest(HTTPRequest $request): HTTPResponse
    {
        $response = parent::handleRequest($request);

        // A nested sub-controller may already have redirected to the login form.
        if (!$this->canView() && $response->isRedirect()) {
            return $response;
        }

        if (!$this->canView()) {
            $this->pushCurrent();
            try {
                return Security::permissionFailure($this, _t(self::class . '.PERMISSION_FAILURE_LOGIN', 'Please log in to access this area.'));
            } finally {
                $this->popCurrent();
            }
        }

        if ($response->getStatusCode() == 403) {
            $this->pushCurrent();
            try {
                $response = Security::permissionFailure($this, _t(self::class . '.PERMISSION_FAILURE_ACCESS', 'You do not have permission to perform this action.'));
                $response->addHeader('Content-Type', 'text/html');
            } finally {
                $this->popCurrent();
            }
            return $response;
        }

        return $response;
    }

    /**
     * Returns the ID of the record being edited/viewed/deleted, or null when
     * there is none (e.g. on add/index). Sub-controllers override this to
     * guard against the parent's URL match leaking its own ID into params.
     */
    protected function currentRecordID(): ?int
    {
        // A submitted edit form posts to <segment>/EditForm without an ID in the URL.
        // The form must be built for the submitted record, so that field levels that
        // depend on the record (e.g. its status) match the form that was shown;
        // findRecord() still checks the record against the base list.
        $request = $this->getRequest();
        if ($this->getAction() === 'EditForm' && $request->isPOST()) {
            $id = (int) $request->postVar('ID');
            return $id ?: null;
        }
        if (!in_array($this->getAction(), ['edit', 'view', 'delete'], true)) {
            return null;
        }
        $id = $this->getRequest()->param('ID');
        return $id ? (int) $id : null;
    }

    // ─── Override in subclass ─────────────────────────────────────────────────

    protected function defineColumns(): ColumnCollection
    {
        $collection = ColumnCollection::fromSummaryFields($this->getManagedModel());
        // Auto-link the first column to the view action
        if ($collection->count() > 0) {
            $collection->first()->link('view/{ID}');
        }
        return $collection;
    }

    protected function defineFilters(): FilterCollection
    {
        return FilterCollection::create();
    }

    /**
     * Actions for records selected via the checkbox column. Without bulk
     * actions the list has no checkbox column.
     *
     * @return BulkAction[]
     */
    protected function defineBulkActions(): array
    {
        return [];
    }

    /**
     * @param DataObject $record
     * @return RowAction[]
     */
    protected function defineRowActions(DataObject $record): array
    {
        $actions = [];
        $actions[] = RowAction::link(_t(self::class . '.ACTION_VIEW', 'View'), $this->Link('view/' . $record->ID));
        if ($this->canEdit()) {
            $actions[] = RowAction::edit($this->Link('edit/' . $record->ID));
            $actions[] = RowAction::delete($this->Link('delete/' . $record->ID));
        }
        return $actions;
    }

    /**
     * Return an ArrayList of field name/value pairs for the detail view.
     * Override to customise which fields are shown and in what order.
     */
    protected function defineViewFields(DataObject $record): ArrayList
    {
        return ArrayList::create(array_values($this->viewFieldRows($record)));
    }

    /**
     * View rows keyed by form field name (has_one relations as "<Name>ID"),
     * see defineViewFields(). Used to arrange rows by a FieldLayout.
     *
     * @return array<string, ArrayData>
     */
    protected function viewFieldRows(DataObject $record): array
    {
        $rows = [];

        // DB fields (respects field_labels)
        $labels = $record->fieldLabels(false);
        foreach ($record->config()->get('db') ?? [] as $name => $type) {
            $label = $labels[$name] ?? $name;
            $dbField = $record->dbObject($name);
            $value = $dbField
                ? (method_exists($dbField, 'Nice') ? $dbField->Nice() : (string) $dbField)
                : (string) $record->$name;
            $rows[$name] = ArrayData::create([
                'Label' => $label,
                'Value' => $value,
                'Type'  => 'text',
            ]);
        }

        // has_one relations
        foreach ($record->config()->get('has_one') ?? [] as $name => $class) {
            $label = $labels[$name] ?? $name;
            $related = $record->$name();
            if (!$related || !$related->exists()) {
                continue;
            }
            // Image / File: render thumbnail or filename
            if (is_a($related, 'SilverStripe\Assets\Image', true)) {
                $rows[$name . 'ID'] = ArrayData::create([
                    'Label' => $label,
                    'Value' => $related->ScaleWidth(200)->forTemplate(),
                    'Type'  => 'html',
                ]);
            } elseif (is_a($related, 'SilverStripe\Assets\File', true)) {
                $rows[$name . 'ID'] = ArrayData::create([
                    'Label' => $label,
                    'Value' => $related->Name,
                    'Type'  => 'text',
                ]);
            } else {
                $rows[$name . 'ID'] = ArrayData::create([
                    'Label' => $label,
                    'Value' => (string) $related->getTitle(),
                    'Type'  => 'text',
                ]);
            }
        }

        // has_many relations: show the count, unless a sub-controller lists them
        $listed = [];
        foreach (static::config()->get('sub_controllers') ?? [] as $subClass) {
            $listed[] = singleton($subClass)->config()->get('managed_model');
        }
        foreach (array_keys($record->config()->get('has_many') ?? []) as $name) {
            if (in_array(DataObject::getSchema()->hasManyComponent($record::class, $name), $listed, true)) {
                continue;
            }
            $rows[$name] = ArrayData::create([
                'Label' => $labels[$name] ?? $name,
                'Value' => (string) $record->$name()->count(),
                'Type'  => 'text',
            ]);
        }

        return $rows;
    }

    protected function defineViewActions(DataObject $record): ArrayList
    {
        $actions = ArrayList::create();
        $currentAction = $this->getAction();
        if ($this->canEdit()) {
            $actions->push(ArrayData::create([
                'Title'   => _t(self::class . '.ACTION_EDIT', 'Edit'),
                'Link'    => $this->Link('edit/' . $record->ID),
                'Primary' => true,
                'Active'  => $currentAction === 'edit',
            ]));
        }
        $actions->push(ArrayData::create([
            'Title'   => _t(self::class . '.ACTION_BACK', '← Back'),
            'Link'    => $this->Link(),
            'Primary' => false,
            'Active'  => false,
        ]));
        return $actions;
    }

    /**
     * Actions on a single record, shown on the record view (see RecordAction).
     * Return only the actions the current user may run on this record: the
     * controller executes nothing else. Runs for users who can view the list.
     *
     * @return RecordAction[]
     */
    protected function defineRecordActions(DataObject $record): array
    {
        return [];
    }

    /**
     * Timeline of the record (TimelineEntry rows), shown below the record view.
     * Null = no timeline.
     */
    protected function defineTimeline(DataObject $record): ?ArrayList
    {
        return null;
    }

    protected function timelineTitle(): string
    {
        return _t(self::class . '.TIMELINE', 'Timeline');
    }

    protected function defineViewContent(DataObject $record): DBHTMLText
    {
        $layout = $this->defineFieldLayout($record);
        if ($layout) {
            return $this->renderWith(
                'Atwx\\SilverstripeFrontdeskKit\\Includes\\ViewGroups',
                ['ViewGroups' => $layout->viewGroups($this->viewFieldRows($record))]
            );
        }
        return $this->renderWith(
            'Atwx\\SilverstripeFrontdeskKit\\Includes\\ViewDetail',
            ['ViewFields' => $this->defineViewFields($record)]
        );
    }

    protected function formFields(FieldList $fields): FieldList
    {
        return $fields;
    }

    /**
     * Groups and access levels of the record's fields, used by the edit form,
     * the record view and saving (changes to fields that are not writable are
     * reset). $record is null when a new record is added. Null = no layout.
     */
    protected function defineFieldLayout(?DataObject $record): ?FieldLayout
    {
        return null;
    }

    // ─── CRUD Actions ─────────────────────────────────────────────────────────

    public function index(HTTPRequest $request)
    {
        if ($this->isHtmxRequest()) {
            return $this->renderPartial(
                'Atwx\\SilverstripeFrontdeskKit\\Includes\\ListTable',
                ['Items' => $this->getItems(), 'Columns' => $this->Columns()]
            );
        }

        return [];
    }

    public function view(HTTPRequest $request)
    {
        $id = $request->param('ID');
        $class = $this->getManagedModel();
        if (!$id) {
            return $this->httpError(404);
        }
        $item = $this->findRecord((int) $id, 'canView');
        if (!$item) {
            return $this->httpError(404);
        }
        $title = $item->hasMethod('Title') ? $item->Title() : ($item->getTitle() ?: $item->singular_name() . ' #' . $item->ID);
        $pages = $this->defineRecordPages($item);
        if ($pages) {
            return $this->recordPageView($item, $pages, (string) $request->param('OtherID'), $title);
        }
        return [
            'Item'              => $item,
            'Title'             => $title,
            'ViewFields'        => $this->defineViewFields($item),
            'ViewActions'       => $this->defineViewActions($item),
            'ViewContent'       => $this->defineViewContent($item),
            'RecordActions'     => $this->recordActionsFor($item),
            'Timeline'          => $this->timelineFor($item),
            'TimelineTitle'     => $this->timelineTitle(),
            'SubControllerData' => $this->getSubControllerData($item),
        ];
    }

    protected function getSubControllerData(DataObject $record): ArrayList
    {
        $result = ArrayList::create();
        if (static::config()->get('sub_controller_tabs')) {
            return $result;
        }
        foreach (static::config()->get('sub_controllers') ?? [] as $class) {
            $segment = singleton($class)->config()->get('url_segment');
            $title   = singleton($class)->config()->get('title');
            $result->push(ArrayData::create([
                'Title'   => $title,
                'Segment' => $segment,
                'Url'     => $this->Link($segment . '/' . $record->ID),
            ]));
        }
        return $result;
    }

    /**
     * Tabs of a record (record view plus one tab per sub-controller) when
     * sub_controller_tabs is enabled, null otherwise.
     *
     * @param string|null $active url_segment of the active sub-controller, null for the record view
     */
    public function RecordTabs(int $recordID, ?string $active = null): ?ArrayList
    {
        if (!static::config()->get('sub_controller_tabs') || !$recordID) {
            return null;
        }
        $tabs = ArrayList::create([ArrayData::create([
            'Title'  => $this->overviewTabTitle(),
            'Link'   => $this->Link('view/' . $recordID),
            'Active' => $active === null,
        ])]);
        foreach (static::config()->get('sub_controllers') ?? [] as $class) {
            $segment = singleton($class)->config()->get('url_segment');
            $tabs->push(ArrayData::create([
                'Title'  => singleton($class)->config()->get('title'),
                'Link'   => $this->Link($segment . '/' . $recordID),
                'Active' => $active === $segment,
            ]));
        }
        return $tabs;
    }

    /**
     * Label of the first tab (the record view).
     */
    protected function overviewTabTitle(): string
    {
        return _t(self::class . '.TAB_OVERVIEW', 'Overview');
    }

    /**
     * Tabs shown above the current page, see RecordTabs().
     */
    public function Tabs(): ?ArrayList
    {
        if ($this->getAction() !== 'view') {
            return null;
        }
        return $this->RecordTabs((int) $this->getRequest()->param('ID'));
    }

    public function edit(HTTPRequest $request)
    {
        $form = $this->EditForm();
        $id = $request->param('ID');
        $class = $this->getManagedModel();
        $item = null;
        if ($id) {
            $item = $this->findRecord((int) $id, 'canEdit');
            if (!$item) {
                return $this->httpError(404);
            }
            // After a failed save the submitted values were restored from the session; keep them.
            if ($form->getSessionData() === null) {
                $form->loadDataFrom($item);
            }
        }
        if ($form->getSessionData() === null) {
            $form->loadDataFrom(['BackURL' => $request->getVar('BackURL') ?: $this->resolveBackURL($request)]);
        }
        $title = _t(self::class . '.TITLE_EDIT', 'Edit {name}', ['name' => singleton($class)->i18n_singular_name()]);

        if ($this->isHtmxRequest()) {
            return $this->renderPartial(
                'Atwx\\SilverstripeFrontdeskKit\\Includes\\EditFormModal',
                ['Form' => $form, 'Title' => $title, 'Item' => $item]
            );
        }

        return [
            'Form' => $form,
            'Item' => $item,
            'Title' => $title,
            'Actions' => false,
        ];
    }

    public function add(HTTPRequest $request)
    {
        $this->assertCanCreate();
        $form = $this->EditForm();
        if ($form->getSessionData() === null) {
            $form->loadDataFrom($request->getVars());
            $form->loadDataFrom(['BackURL' => $request->getVar('BackURL') ?: $this->resolveBackURL($request)]);
        }
        $class = $this->getManagedModel();
        $title = _t(self::class . '.TITLE_NEW', 'New {name}', ['name' => singleton($class)->i18n_singular_name()]);

        if ($this->isHtmxRequest()) {
            return $this->renderPartial(
                'Atwx\\SilverstripeFrontdeskKit\\Includes\\EditFormModal',
                ['Form' => $form, 'Title' => $title, 'Item' => null]
            );
        }

        return [
            'Title' => $title,
            'Form' => $form,
            'Actions' => false,
        ];
    }

    public function save($data, Form $form)
    {
        $class = $this->getManagedModel();

        $isNew = empty($data['ID']);
        if (!$isNew) {
            $item = $this->findRecord((int) $data['ID'], 'canEdit');
            if (!$item) {
                return $this->httpError(404);
            }
        } else {
            $this->assertCanCreate();
            $item = $class::create();
        }

        $form->saveInto($item);
        $this->formLayout($isNew ? null : $item)?->resetLockedChanges($item);
        $this->beforeWrite($item, $data);
        $item->write();
        $this->afterWrite($item, $data);

        if ($isNew) {
            return $this->redirect($this->Link('view/' . $item->ID));
        }

        $backURL = $data['BackURL'] ?? $this->Link();
        return $this->redirect($backURL ?: $this->Link());
    }

    public function savecontinue($data, Form $form)
    {
        $class = $this->getManagedModel();

        if (!empty($data['ID'])) {
            $item = $this->findRecord((int) $data['ID'], 'canEdit');
            if (!$item) {
                return $this->httpError(404);
            }
        } else {
            $this->assertCanCreate();
            $item = $class::create();
        }

        $form->saveInto($item);
        $this->formLayout($item->isInDB() ? $item : null)?->resetLockedChanges($item);
        $this->beforeWrite($item, $data);
        $item->write();
        $this->afterWrite($item, $data);

        return $this->redirect($this->Link('edit/' . $item->ID));
    }

    public function delete(HTTPRequest $request)
    {
        $this->assertDeleteMethod($request);
        $id = $request->param('ID');
        if ($id) {
            $item = $this->findRecord((int) $id, 'canDelete');
            if ($item) {
                $this->beforeDelete($item);
                $item->delete();
                $this->afterDelete($item);
            }
        }

        if ($this->isHtmxRequest()) {
            // Return empty 200 — HTMX will remove the target element
            return HTTPResponse::create('', 200);
        }

        return $this->redirectBack();
    }

    /**
     * Applies a bulk action to the selected records (POST with CSRF token).
     * Records are loaded from the base list; with check_record_permissions only
     * records the user may edit are passed to the handler.
     */
    public function bulk(HTTPRequest $request)
    {
        if (!$request->isPOST() || !SecurityToken::inst()->checkRequest($request)) {
            return $this->httpError(400);
        }
        [$name, $option] = array_pad(explode(':', (string) $request->postVar('BulkAction'), 2), 2, null);
        $action = null;
        foreach ($this->defineBulkActions() as $candidate) {
            if ($candidate->getName() === $name) {
                $action = $candidate;
            }
        }
        if (!$action || ($action->getOptions() && !array_key_exists((string) $option, $action->getOptions()))) {
            return $this->httpError(400);
        }
        $ids = array_filter(array_map('intval', (array) $request->postVar('ids')));
        $records = $this->getBaseList()->filter('ID', $ids ?: [0]);
        if (static::config()->get('check_record_permissions')) {
            $allowed = [];
            foreach ($records as $record) {
                if ($record->canEdit()) {
                    $allowed[] = $record->ID;
                }
            }
            $records = $records->filter('ID', $allowed ?: [0]);
        }

        $result = $action->handle($records, $option);
        if ($result instanceof HTTPResponse) {
            return $result;
        }
        if (is_string($result) && $result !== '') {
            $request->getSession()->set('FrontdeskBulkMessage', $result);
        }
        $back = (string) $request->postVar('BackURL');
        return $this->redirect(Director::is_site_url($back) ? $back : $this->Link());
    }

    /**
     * Runs a record action (POST with CSRF token) and returns to the record view.
     */
    public function recordaction(HTTPRequest $request)
    {
        $record = $this->findRecord((int) $request->param('ID'), 'canView');
        if (!$record) {
            return $this->httpError(404);
        }
        return $this->runRecordAction($request, $record, $this->Link('view/' . $record->ID));
    }

    protected function recordActionUrl(DataObject $record): string
    {
        return $this->Link('recordaction/' . $record->ID);
    }

    protected function timelineFor(DataObject $record): ?ArrayList
    {
        $timeline = $this->defineTimeline($record);
        if ($timeline === null) {
            return null;
        }
        $entries = $timeline->toArray();
        usort($entries, fn ($a, $b) => (int) $b->Timestamp <=> (int) $a->Timestamp);
        return ArrayList::create($entries);
    }

    public function BulkActions(): ArrayList
    {
        return ArrayList::create($this->defineBulkActions());
    }

    /**
     * Message of the last bulk action (shown once).
     */
    public function BulkMessage(): ?string
    {
        if ($this->bulkMessage === false) {
            $session = $this->getRequest()->getSession();
            $this->bulkMessage = $session->get('FrontdeskBulkMessage');
            $session->clear('FrontdeskBulkMessage');
        }
        return $this->bulkMessage;
    }

    /** Message of the last bulk action, read once per request (false = not read yet). */
    private string|null|false $bulkMessage = false;

    public function export(HTTPRequest $request)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $row = 2;

        $items = $this->getQuery();
        $names = $this->currentListView()?->getExportColumns();
        $columns = ($names ? $this->selectColumns($this->defineColumns(), $names) : $this->defineColumns()->withoutOptional())
            ->forExport();

        $col = 'A';
        foreach ($columns as $column) {
            $sheet->setCellValue($col . '1', $column->getLabel());
            $col++;
        }

        foreach ($items as $item) {
            $col = 'A';
            foreach ($columns as $column) {
                $sheet->setCellValue($col . $row, $column->renderValue($item));
                $col++;
            }
            $row++;
        }

        $class = $this->getManagedModel();
        $className = strtolower(DataObject::singleton($class)->i18n_plural_name());
        $fileName = 'export-' . $className . '-' . date('Y-m-d') . '.xlsx';

        $writer = new Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . urlencode($fileName) . '"');
        $writer->save('php://output');
    }

    // ─── Form ─────────────────────────────────────────────────────────────────

    public function EditForm(): Form
    {
        $class = $this->getManagedModel();
        $id = $this->currentRecordID();

        if ($id) {
            $item = $this->findRecord($id, 'canEdit');
        } else {
            $item = DataObject::singleton($class);
        }

        if (!$item) {
            $item = DataObject::singleton($class);
        }

        $scaffolded = SearchableDropdownField::replaceCmsFields($item->scaffoldFormFields());
        $fields = $this->formFields($scaffolded);
        $layout = $this->formLayout($item->isInDB() ? $item : null);
        if ($layout) {
            $fields = $layout->applyTo($fields);
        }
        $page = $item->isInDB() ? $this->requestedRecordPage($item) : null;
        if ($page) {
            $fields->push(HiddenField::create('RecordPage', 'RecordPage', $page->getSegment()));
        }
        $fields->push(HiddenField::create('ID', 'ID'));
        $fields->push(HiddenField::create('BackURL', 'BackURL'));

        $form = Form::create($this, 'EditForm', $fields, FieldList::create(
            FormAction::create('save', _t(self::class . '.ACTION_SAVE', 'Save')),
            FormAction::create('savecontinue', _t(self::class . '.ACTION_SAVE_CONTINUE', 'Save & Continue'))->addExtraClass('btn-ghost'),
            LiteralField::create('Cancel', '<a href="javascript:void(0);" onclick="var d=this.closest(\'dialog\'); if(d){d.close();return false;} history.back();" class="btn">' . _t(self::class . '.ACTION_CANCEL', 'Cancel') . '</a>')
        ));
        $form->setValidationResponseCallback(fn (ValidationResult $result) => $this->validationErrorResponse($form, $result));
        $this->extend('updateEditForm', $form, $item);
        return $form;
    }

    /**
     * Sends the user back to the edit page after a failed save, with messages and submitted values.
     * The default redirectBack() would end on the list when the form was opened in a modal.
     */
    protected function validationErrorResponse(Form $form, ValidationResult $result): HTTPResponse
    {
        $data = $form->getData();
        $form->setSessionValidationResult($result);
        $form->setSessionData($data);
        $id = (int) ($data['ID'] ?? 0);
        $link = $this->Link($id ? 'edit/' . $id : 'add');
        if (!empty($data['BackURL']) && Director::is_site_url($data['BackURL'])) {
            $link .= '?BackURL=' . urlencode($data['BackURL']);
        }
        return $this->redirect($link);
    }

    // ─── Template data ────────────────────────────────────────────────────────

    public function Items(): PaginatedList
    {
        return $this->getItems();
    }

    public function Columns(): ColumnCollection
    {
        $names = $this->currentListView()?->getColumns();
        return $names ? $this->selectColumns($this->defineColumns(), $names) : $this->defineColumns()->forList();
    }

    public function FilterForm(): ?Form
    {
        $filterCollection = $this->defineFilters();
        $fields = $filterCollection->toFieldList();
        if($fields->count() === 0) {
            return null;
        }
        $actions = FieldList::create(
            FormAction::create('search', _t(self::class . '.ACTION_FILTER', 'Filter'))->addExtraClass('btn-ghost btn-sm')
        );
        $form = Form::create($this, 'FilterForm', $fields, $actions)
            ->setFormAction($this->Link())
            ->setFormMethod('GET');
        $request = $this->listRequest();
        $requestVars = $request->getVars();
        $view = $this->currentListView();
        if ($view) {
            $fields->push(HiddenField::create('view', 'view', (string) $this->getRequest()->getVar('view')));
        }
        if ($filterCollection->hasAnyRequestParam($request)) {
            $form->loadDataFrom($requestVars);
        } else {
            $defaults = $filterCollection->getDefaults();
            $form->loadDataFrom($defaults);
        }
        return $form;
    }

    public function Title(): string
    {
        return (string) self::config()->get('title');
    }

    public function Actions(): ArrayList
    {
        $actions = ArrayList::create();
        if ($this->canAdd()) {
            $actions->push(ArrayData::create([
                'Title' => _t(self::class . '.ACTION_NEW', 'New'),
                'Primary' => true,
                'Link' => $this->Link('add') . '?BackURL=' . urlencode($this->Link()),
            ]));
        }
        $actions->push(ArrayData::create([
            'Title' => _t(self::class . '.ACTION_EXPORT', 'Export'),
            'Primary' => false,
            'Link' => $this->Link('export') . '?' . $this->CurrentQuery(),
            'Target' => '_blank',
        ]));
        return $actions;
    }

    public function RowActionsFor(DataObject $record): ArrayData
    {
        $direct   = ArrayList::create();
        $dropdown = ArrayList::create();
        foreach ($this->defineRowActions($record) as $action) {
            $data = ArrayData::create([
                'Label'          => $action->getLabel(),
                'Url'            => $action->getUrl(),
                'IsDelete'       => $action->isDeleteAction(),
                'IsHtmx'         => $action->isHtmxAction(),
                'Method'         => $action->getMethod(),
                'HasConfirm'     => $action->getConfirmMessage() !== null,
                'ConfirmMessage' => (string) $action->getConfirmMessage(),
                'HasIcon'        => $action->getIcon() !== '',
                'Icon'           => $action->getIcon(),
                'Target'         => $action->getTarget(),
                'HasTarget'      => $action->getTarget() !== '',
                'RowId'          => $record->ID,
            ]);
            if ($action->isDirectAction()) {
                $direct->push($data);
            } else {
                $dropdown->push($data);
            }
        }
        return ArrayData::create([
            'DirectActions'      => $direct,
            'DropdownActions'    => $dropdown,
            'HasDropdownActions' => $dropdown->count() > 0,
        ]);
    }

    /**
     * Render a list of items with their columns and row actions as an ArrayList
     * suitable for the ListTable template.
     */
    public function ItemRows(): ArrayList
    {
        $rows = ArrayList::create();
        $columns = $this->Columns();
        $baseUrl = $this->Link();
        foreach ($this->getItems() as $record) {
            $rows->push(ArrayData::create([
                'Record'     => $record,
                'Cells'      => $columns->renderFor($record, $baseUrl),
                'RowActions' => $this->RowActionsFor($record),
                'ID'         => $record->ID,
            ]));
        }
        return $rows;
    }

    public function FilterIsActive(): bool
    {
        return $this->defineFilters()->isActive($this->getRequest());
    }

    /**
     * Override in subclasses to show aggregate stats above the list.
     * Each entry should expose Label, Value and optionally SubLabel.
     */
    public function SummaryStats(): ?ArrayList
    {
        return null;
    }

    public function CurrentQuery(): string
    {
        $vars = $this->getRequest()->getVars();
        unset($vars['SecurityID'], $vars['action_search']);
        return http_build_query($vars);
    }

    public function ContentLocale(): string
    {
        return i18n::convert_rfc1766(i18n::get_locale());
    }

    public function CurrentUser()
    {
        return Security::getCurrentUser();
    }

    public function MainNavigation(): ArrayList
    {
        $navigation = ArrayList::create();
        $request = $this->getRequest();
        $url = $request ? (string) $request->getURL() : '';
        $entries = (array) self::config()->get('main_navigation');

        foreach ($entries as $entry) {
            $class = $entry['controller'] ?? null;
            $segment = (string) ($entry['segment'] ?? '');
            $title = (string) ($entry['title'] ?? $segment);
            if (!$class || !class_exists($class)) {
                continue;
            }
            $controller = singleton($class);
            if (!$controller->canView()) {
                continue;
            }
            $active = $segment !== ''
                && ($url === $segment || str_starts_with($url, $segment . '/'));
            if (!$active && !empty($entry['default']) && $url === '') {
                $active = true;
            }
            $navigation->push(ArrayData::create([
                'Title' => $title,
                'Link' => $segment,
                'Active' => $active,
            ]));
        }

        return $navigation;
    }

    public function CurrentUserInitials(): string
    {
        $m = Security::getCurrentUser();
        if (!$m) {
            return '';
        }
        $init = strtoupper(mb_substr((string) $m->FirstName, 0, 1) . mb_substr((string) $m->Surname, 0, 1));
        return $init ?: strtoupper(mb_substr((string) $m->Email, 0, 1));
    }

    public function CurrentUserDisplayName(): string
    {
        $m = Security::getCurrentUser();
        if (!$m) {
            return '';
        }
        $name = trim(((string) $m->FirstName) . ' ' . ((string) $m->Surname));
        return $name !== '' ? $name : (string) $m->Email;
    }

    public function HasPermission(string $code): bool
    {
        $member = Security::getCurrentUser();
        if (!$member) {
            return false;
        }
        return Permission::checkMember($member, 'ADMIN') || Permission::checkMember($member, $code);
    }

    // ─── Permission ───────────────────────────────────────────────────────────

    public function canView($member = null): bool
    {
        $member = $member ?: Security::getCurrentUser();
        if (!$member) {
            return false;
        }
        if (Permission::checkMember($member, 'ADMIN')) {
            return true;
        }
        return Permission::checkMember($member, $this->getPermissionCode('VIEW'))
            || Permission::checkMember($member, $this->getPermissionCode('EDIT'));
    }

    public function canEdit($member = null): bool
    {
        $member = $member ?: Security::getCurrentUser();
        if (!$member) {
            return false;
        }
        if (Permission::checkMember($member, 'ADMIN')) {
            return true;
        }
        return Permission::checkMember($member, $this->getPermissionCode('EDIT'));
    }

    /**
     * Returns the permission code for this controller's action (VIEW or EDIT),
     * derived from $url_segment. Subclasses can override $permission_code to
     * pin a specific prefix.
     */
    public function getPermissionCode(string $action): string
    {
        $prefix = (string) $this->config()->get('permission_code');
        if (!$prefix) {
            $segment = (string) $this->config()->get('url_segment');
            $prefix = 'FDK_' . strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', $segment));
        }
        return $prefix . '_' . strtoupper($action);
    }

    public function providePermissions(): array
    {
        $perms = [];
        foreach (ClassInfo::subclassesFor(FrontdeskController::class) as $class) {
            if ($class === FrontdeskController::class || $class === FrontdeskSubController::class) {
                continue;
            }
            // Skip SubControllers — they inherit from their parent.
            if (is_subclass_of($class, FrontdeskSubController::class)) {
                continue;
            }
            $ref = new \ReflectionClass($class);
            if ($ref->isAbstract()) {
                continue;
            }
            $segment = (string) singleton($class)->config()->get('url_segment');
            if (!$segment) {
                continue;
            }
            $title = (string) singleton($class)->config()->get('title') ?: $segment;
            $prefix = (string) singleton($class)->config()->get('permission_code')
                ?: 'FDK_' . strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', $segment));
            $category = _t(self::class . '.PERMISSION_CATEGORY', 'Frontdesk Access');
            $perms[$prefix . '_VIEW'] = [
                'name' => _t(self::class . '.PERMISSION_VIEW', "View '{name}'", ['name' => $title]),
                'category' => $category,
            ];
            $perms[$prefix . '_EDIT'] = [
                'name' => _t(self::class . '.PERMISSION_EDIT', "Edit '{name}' (includes add/delete)", ['name' => $title]),
                'category' => $category,
            ];
        }
        return $perms;
    }

    // ─── Routing ──────────────────────────────────────────────────────────────

    public function Link($action = null): string
    {
        $url = self::config()->get('url_segment');
        return Controller::join_links($url, $action);
    }

    public function CurrentUrl(): string
    {
        return $this->Link() . '?' . $this->CurrentQuery();
    }

    // ─── Internal helpers ─────────────────────────────────────────────────────

    public function getManagedModel(): string
    {
        return (string) self::config()->get('managed_model');
    }

    protected function getQuery()
    {
        $list = $this->defineFilters()->applyAll($this->getBaseList(), $this->listRequest());
        return $this->currentListView()?->applyTo($list) ?? $list;
    }

    /**
     * Unfiltered list of records this controller works on. All records are
     * loaded from it (list, view, edit, save, delete), so restricting it here
     * restricts access everywhere.
     */
    protected function getBaseList(): DataList
    {
        $class = $this->getManagedModel();
        $list = $class::get();
        $this->extend('updateBaseList', $list);
        return $list;
    }

    /**
     * Loads a record from the base list. With check_record_permissions, a
     * record the current user may not access results in a 403.
     *
     * @param string $permission canView, canEdit or canDelete
     */
    protected function findRecord(int $id, string $permission = 'canView'): ?DataObject
    {
        $item = $id ? $this->getBaseList()->byID($id) : null;
        if ($item && static::config()->get('check_record_permissions') && !$item->$permission()) {
            $this->httpError(403);
        }
        return $item;
    }

    /**
     * Whether new records can be added here (button "New" and the add action).
     */
    public function canAdd(): bool
    {
        return static::config()->get('allow_add') !== false && $this->canEdit();
    }

    protected function assertCanCreate(): void
    {
        if (!$this->canAdd()) {
            $this->httpError(403);
        }
        if (static::config()->get('check_record_permissions')
            && !DataObject::singleton($this->getManagedModel())->canCreate()
        ) {
            $this->httpError(403);
        }
    }

    protected function assertDeleteMethod(HTTPRequest $request): void
    {
        if (!static::config()->get('allow_get_delete') && !$request->isDELETE() && !$request->isPOST()) {
            $this->httpError(405);
        }
    }

    /** Hook: mutate $item before it is written. */
    protected function beforeWrite(DataObject $item, array $data): void
    {
        $this->extend('onBeforeWriteRecord', $item, $data);
    }

    /** Hook: react after $item is written. */
    protected function afterWrite(DataObject $item, array $data): void
    {
        $this->extend('onAfterWriteRecord', $item, $data);
    }

    /** Hook: e.g. cascade-delete related records before $item is deleted. */
    protected function beforeDelete(DataObject $item): void
    {
        $this->extend('onBeforeDeleteRecord', $item);
    }

    /** Hook: react after $item is deleted. */
    protected function afterDelete(DataObject $item): void
    {
        $this->extend('onAfterDeleteRecord', $item);
    }

    protected function getItems(): PaginatedList
    {
        $pageLength = (int) (self::config()->get('page_length') ?: 30);
        return PaginatedList::create($this->getQuery(), $this->getRequest())
            ->setPageLength($pageLength);
    }

    protected function resolveBackURL(HTTPRequest $request): string
    {
        $candidate = $request->getHeader('HX-Current-URL') ?: $request->getHeader('Referer');
        if (!$candidate) {
            return $this->Link();
        }
        $parts = parse_url($candidate);
        $path = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
        $own = '/' . trim($this->Link(), '/');
        if (str_starts_with($path, $own . '/edit') || str_starts_with($path, $own . '/add')) {
            return $this->Link();
        }
        return $path;
    }

    protected function isHtmxRequest(): bool
    {
        return (bool) $this->getRequest()->getHeader('HX-Request');
    }

    protected function renderPartial(string $template, array $data): HTTPResponse
    {
        $html = $this->renderWith($template, $data);
        return HTTPResponse::create((string) $html, 200)
            ->addHeader('Content-Type', 'text/html');
    }
}
