<?php

namespace Atwx\SilverstripeFrontdeskKit;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Manifest\ModuleResourceLoader;
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
    private static $logo = null;
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
            'Page',
        ];
        $this->templates['edit'] = [
            static::class . '_edit',
            FrontdeskController::class . '_edit',
            'Page',
        ];
        $this->templates['add'] = [
            static::class . '_add',
            static::class . '_edit',
            FrontdeskController::class . '_add',
            FrontdeskController::class . '_edit',
            'Page',
        ];
    }

    public function handleRequest(HTTPRequest $request): HTTPResponse
    {
        $response = parent::handleRequest($request);
        if (!$this->canView()) {
            $this->pushCurrent();
            return Security::permissionFailure($this, 'Bitte loggen Sie sich ein.');
        }
        if ($response->getStatusCode() == 403) {
            $this->pushCurrent();
            $response = Security::permissionFailure($this, 'Diese Aktion ist nicht erlaubt.');
            $response->addHeader('Content-Type', 'text/html');
            return $response;
        }
        return $response;
    }

    // ─── Override in subclass ─────────────────────────────────────────────────

    protected function columns(): ColumnCollection
    {
        return ColumnCollection::fromSummaryFields($this->getManagedModel());
    }

    protected function filters(): FilterCollection
    {
        return FilterCollection::create();
    }

    /**
     * @param DataObject $record
     * @return RowAction[]
     */
    protected function rowActions(DataObject $record): array
    {
        $actions = [];
        if ($this->canEdit()) {
            $actions[] = RowAction::link('Bearbeiten', $this->Link('edit/' . $record->ID));
            $actions[] = RowAction::delete($this->Link('delete/' . $record->ID));
        }
        return $actions;
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
                ['Items' => $this->getItems(), 'Columns' => $this->columns()]
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
        return [
            'Item' => $item,
            'Title' => $item->Title(),
        ];
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
        $form->loadDataFrom(['BackURL' => $request->getVar('BackURL')]);
        return [
            'Form' => $form,
            'Item' => $item,
            'Title' => singleton($class)->singular_name() . ' bearbeiten',
            'Actions' => false,
        ];
    }

    public function add(HTTPRequest $request)
    {
        $form = $this->EditForm();
        $form->loadDataFrom($request->getVars());
        $form->loadDataFrom(['BackURL' => $request->getVar('BackURL')]);
        $class = $this->getManagedModel();
        $title = 'Neu: ' . singleton($class)->singular_name();
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
        $columns = $this->columns()->forExport();

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
            FormAction::create('save', 'Speichern'),
            LiteralField::create('Cancel', '<a href="javascript:history.back();" class="btn">Abbrechen</a>')
        ));
    }

    // ─── Template data ────────────────────────────────────────────────────────

    public function Items(): PaginatedList
    {
        return $this->getItems();
    }

    public function Columns(): ColumnCollection
    {
        return $this->columns();
    }

    public function FilterBar(): \SilverStripe\ORM\FieldType\DBHTMLText
    {
        $filterCollection = $this->filters();
        $fields = $filterCollection->toFieldList();
        $actions = FieldList::create(
            FormAction::create('search', 'Filtern')->addExtraClass('btn btn-sm')
        );
        $form = Form::create($this, 'FilterForm', $fields, $actions)
            ->setFormAction($this->Link())
            ->setFormMethod('GET');
        $form->loadDataFrom($this->getRequest()->getVars());
        return $form->forTemplate();
    }

    public function FilterForm(): Form
    {
        $filterCollection = $this->filters();
        $fields = $filterCollection->toFieldList();
        $actions = FieldList::create(
            FormAction::create('search', 'Filtern')->addExtraClass('btn btn-sm')
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
                'Title' => 'Neu',
                'Primary' => true,
                'Link' => $this->Link('add') . '?BackURL=' . urlencode($this->Link()),
            ]));
        }
        $actions->push(ArrayData::create([
            'Title' => 'Export',
            'Primary' => false,
            'Link' => $this->Link('export') . '?' . $this->CurrentQuery(),
            'Target' => '_blank',
        ]));
        return $actions;
    }

    public function RowActionsFor(DataObject $record): ArrayList
    {
        $list = ArrayList::create();
        foreach ($this->rowActions($record) as $action) {
            $list->push($action);
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
        $columns = $this->columns();
        foreach ($this->getItems() as $record) {
            $rows->push(ArrayData::create([
                'Record' => $record,
                'Cells' => $columns->renderFor($record),
                'RowActions' => $this->RowActionsFor($record),
                'ID' => $record->ID,
            ]));
        }
        return $rows;
    }

    public function FilterIsActive(): bool
    {
        return $this->filters()->isActive($this->getRequest());
    }

    public function CurrentQuery(): string
    {
        $vars = $this->getRequest()->getVars();
        unset($vars['SecurityID'], $vars['action_search']);
        return http_build_query($vars);
    }

    public function getLogo(): ?string
    {
        $logo = self::config()->get('logo');
        if ($logo) {
            return ModuleResourceLoader::resourceURL($logo);
        }
        return null;
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
                'name' => "Zugriff auf '{$simpleClass}'",
                'category' => 'Frontdesk-Zugriff',
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
        $list = $this->filters()->applyAll($list, $this->getRequest());
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
