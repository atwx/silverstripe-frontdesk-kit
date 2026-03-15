<?php

namespace Atwx\SilverstripeFrontdeskKit;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\i18n\i18n;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\PaginatedList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\Security\Security;

abstract class FrontdeskController extends Controller implements PermissionProvider
{
    private static $managed_model = null;
    private static $url_segment = null;
    private static $title = '';
    private static $logo = null; // Configured via YAML; read by FrontdeskTemplateProvider
    private static $page_length = 30;

    private static $allowed_actions = [
        'index',
        'EditForm',
        'view',
        'edit'   => '->canEdit',
        'add'    => '->canEdit',
        'save'   => '->canEdit',
        'delete' => '->canEdit',
        'export',
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
        if (!$this->canView()) {
            $this->pushCurrent();
            return Security::permissionFailure($this, _t(self::class . '.PERMISSION_FAILURE_LOGIN', 'Please log in to access this area.'));
        }
        if ($response->getStatusCode() == 403) {
            $this->pushCurrent();
            $response = Security::permissionFailure($this, _t(self::class . '.PERMISSION_FAILURE_ACCESS', 'You do not have permission to perform this action.'));
            $response->addHeader('Content-Type', 'text/html');
            return $response;
        }
        return $response;
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
     * @param DataObject $record
     * @return RowAction[]
     */
    protected function defineRowActions(DataObject $record): array
    {
        $actions = [];
        $actions[] = RowAction::link(_t(self::class . '.ACTION_VIEW', 'View'), $this->Link('view/' . $record->ID));
        if ($this->canEdit()) {
            $actions[] = RowAction::htmx(_t(self::class . '.ACTION_EDIT', 'Edit'), $this->Link('edit/' . $record->ID), 'get');
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
        $rows = ArrayList::create();

        // DB fields (respects field_labels)
        $labels = $record->fieldLabels(false);
        foreach ($record->config()->get('db') ?? [] as $name => $type) {
            $label = $labels[$name] ?? $name;
            $dbField = $record->dbObject($name);
            $value = $dbField
                ? (method_exists($dbField, 'Nice') ? $dbField->Nice() : (string) $dbField)
                : (string) $record->$name;
            $rows->push(ArrayData::create([
                'Label' => $label,
                'Value' => $value,
                'Type'  => 'text',
            ]));
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
                $rows->push(ArrayData::create([
                    'Label' => $label,
                    'Value' => $related->ScaleWidth(200)->forTemplate(),
                    'Type'  => 'html',
                ]));
            } elseif (is_a($related, 'SilverStripe\Assets\File', true)) {
                $rows->push(ArrayData::create([
                    'Label' => $label,
                    'Value' => $related->Name,
                    'Type'  => 'text',
                ]));
            } else {
                $rows->push(ArrayData::create([
                    'Label' => $label,
                    'Value' => method_exists($related, 'Title') ? $related->Title() : (string) $related->ID,
                    'Type'  => 'text',
                ]));
            }
        }

        // has_many relations: show count + class name
        foreach ($record->config()->get('has_many') ?? [] as $name => $class) {
            $label = $labels[$name] ?? $name;
            $count = $record->$name()->count();
            $rows->push(ArrayData::create([
                'Label' => $label,
                'Value' => $count . ' ' . $name,
                'Type'  => 'text',
            ]));
        }

        return $rows;
    }

    protected function formFields(FieldList $fields): FieldList
    {
        return $fields;
    }

    // ─── CRUD Actions ─────────────────────────────────────────────────────────

    public function index(HTTPRequest $request)
    {
        if ($this->isHtmxRequest()) {
            return $this->renderPartial(
                'Atwx\\SilverstripeFrontdeskKit\\Includes\\ListTable',
                ['Items' => $this->getItems(), 'Columns' => $this->defineColumns()]
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
        $item = $class::get()->byID($id);
        if (!$item) {
            return $this->httpError(404);
        }
        $title = $item->hasMethod('Title') ? $item->Title() : ($item->getTitle() ?: $item->singular_name() . ' #' . $item->ID);
        return [
            'Item'              => $item,
            'Title'             => $title,
            'ViewFields'        => $this->defineViewFields($item),
            'SubControllerData' => $this->getSubControllerData($item),
        ];
    }

    protected function getSubControllerData(DataObject $record): ArrayList
    {
        $result = ArrayList::create();
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

    public function edit(HTTPRequest $request)
    {
        $form = $this->EditForm();
        $id = $request->param('ID');
        $class = $this->getManagedModel();
        $item = null;
        if ($id) {
            $item = $class::get()->byID($id);
            if (!$item) {
                return $this->httpError(404);
            }
            $form->loadDataFrom($item);
        }
        $form->loadDataFrom(['BackURL' => $request->getVar('BackURL') ?: $this->Link()]);
        $title = _t(self::class . '.TITLE_EDIT', 'Edit {name}', ['name' => singleton($class)->singular_name()]);

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
        $form = $this->EditForm();
        $form->loadDataFrom($request->getVars());
        $form->loadDataFrom(['BackURL' => $request->getVar('BackURL')]);
        $class = $this->getManagedModel();
        $title = _t(self::class . '.TITLE_NEW', 'New {name}', ['name' => singleton($class)->singular_name()]);
        return [
            'Title' => $title,
            'Form' => $form,
            'Actions' => false,
        ];
    }

    public function save($data, Form $form)
    {
        $class = $this->getManagedModel();

        if (!empty($data['ID'])) {
            $item = $class::get()->byID($data['ID']);
            if (!$item) {
                return $this->httpError(404);
            }
        } else {
            $item = $class::create();
        }

        $form->saveInto($item);
        $item->write();

        $backURL = $data['BackURL'] ?? $this->Link();
        return $this->redirect($backURL ?: $this->Link());
    }

    public function delete(HTTPRequest $request)
    {
        if (!$request->isGET() && !$request->isPOST() && !$request->isDelete()) {
            // Accept any HTTP method for simplicity
        }
        $id = $request->param('ID');
        $class = $this->getManagedModel();
        if ($id) {
            $item = $class::get()->byID($id);
            if ($item) {
                $item->delete();
            }
        }

        if ($this->isHtmxRequest()) {
            // Return empty 200 — HTMX will remove the target element
            return HTTPResponse::create('', 200);
        }

        return $this->redirectBack();
    }

    public function export(HTTPRequest $request)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $row = 2;

        $items = $this->getQuery();
        $columns = $this->defineColumns()->forExport();

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
        $className = strtolower(DataObject::singleton($class)->plural_name());
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
        $id = $this->getRequest()->param('ID');

        if ($id) {
            $item = $class::get()->byID($id);
        } else {
            $item = DataObject::singleton($class);
        }

        $scaffolded = $item->scaffoldFormFields();
        $fields = $this->formFields($scaffolded);
        $fields->push(HiddenField::create('ID', 'ID'));
        $fields->push(HiddenField::create('BackURL', 'BackURL'));

        return Form::create($this, 'EditForm', $fields, FieldList::create(
            FormAction::create('save', _t(self::class . '.ACTION_SAVE', 'Save')),
            LiteralField::create('Cancel', '<a href="javascript:history.back();" class="btn">' . _t(self::class . '.ACTION_CANCEL', 'Cancel') . '</a>')
        ));
    }

    // ─── Template data ────────────────────────────────────────────────────────

    public function Items(): PaginatedList
    {
        return $this->getItems();
    }

    public function Columns(): ColumnCollection
    {
        return $this->defineColumns();
    }

    public function FilterForm(): ?Form
    {
        $filterCollection = $this->defineFilters();
        $fields = $filterCollection->toFieldList();
        if($fields->count() === 0) {
            return null;
        }
        $actions = FieldList::create(
            FormAction::create('search', _t(self::class . '.ACTION_FILTER', 'Filter'))->removeExtraClass('btn-primary')->addExtraClass('btn-ghost btn-sm')
        );
        $form = Form::create($this, 'FilterForm', $fields, $actions)
            ->setFormAction($this->Link())
            ->setFormMethod('GET');
        $form->loadDataFrom($this->getRequest()->getVars());
        return $form;
    }

    public function Title(): string
    {
        return (string) self::config()->get('title');
    }

    public function Actions(): ArrayList
    {
        $actions = ArrayList::create();
        if ($this->canEdit()) {
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

    public function RowActionsFor(DataObject $record): ArrayList
    {
        $list = ArrayList::create();
        foreach ($this->defineRowActions($record) as $action) {
            // Wrap in ArrayData so Silverstripe templates can access properties
            $list->push(ArrayData::create([
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
            ]));
        }
        return $list;
    }

    /**
     * Render a list of items with their columns and row actions as an ArrayList
     * suitable for the ListTable template.
     */
    public function ItemRows(): ArrayList
    {
        $rows = ArrayList::create();
        $columns = $this->defineColumns();
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

    // ─── Permission ───────────────────────────────────────────────────────────

    public function canView($member = null): bool
    {
        return (bool) Security::getCurrentUser();
    }

    public function canEdit($member = null): bool
    {
        return Permission::check('ADMIN');
    }

    public function providePermissions(): array
    {
        $perms = [];
        foreach (ClassInfo::subclassesFor(static::class) as $class) {
            if ($class === static::class) {
                continue;
            }
            $parts = explode('\\', (string) $class);
            $simpleClass = array_pop($parts);
            $code = 'FRONTDESK_ACCESS_' . strtoupper($simpleClass);
            $perms[$code] = [
                'name' => _t(self::class . '.PERMISSION_ACCESS', "Access to '{name}'", ['name' => $simpleClass]),
                'category' => _t(self::class . '.PERMISSION_CATEGORY', 'Frontdesk Access'),
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
        $class = $this->getManagedModel();
        $list = DataObject::get($class);
        $list = $this->defineFilters()->applyAll($list, $this->getRequest());
        return $list;
    }

    protected function getItems(): PaginatedList
    {
        $pageLength = (int) (self::config()->get('page_length') ?: 30);
        return PaginatedList::create($this->getQuery(), $this->getRequest())
            ->setPageLength($pageLength);
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
