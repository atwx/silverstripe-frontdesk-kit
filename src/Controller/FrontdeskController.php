<?php

namespace Atwx\SilverstripeFrontdeskKit\Controller;

use Atwx\SilverstripeFrontdeskKit\Filter\DateRangeFilter;
use Atwx\SilverstripeFrontdeskKit\Filter\FilterCollection;
use Atwx\SilverstripeFrontdeskKit\Forms\FieldLayout;
use Atwx\SilverstripeFrontdeskKit\Forms\SearchableDropdownField;
use Atwx\SilverstripeFrontdeskKit\Model\SavedListView;
use Atwx\SilverstripeFrontdeskKit\Table\ActionException;
use Atwx\SilverstripeFrontdeskKit\Table\BulkAction;
use Atwx\SilverstripeFrontdeskKit\Table\ColumnCollection;
use Atwx\SilverstripeFrontdeskKit\Table\ListView;
use Atwx\SilverstripeFrontdeskKit\Table\RecordAction;
use Atwx\SilverstripeFrontdeskKit\Table\RowAction;
use Atwx\SilverstripeFrontdeskKit\View\RecordPage;
use Atwx\SilverstripeFrontdeskKit\View\RecordPages;
use Atwx\SilverstripeFrontdeskKit\View\RecordSwitcher;
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

    /**
     * Pages ("sub views") of the record view, shown in a sidebar; null = one page
     * with all fields (default). See RecordPage.
     */
    protected function defineRecordPages(DataObject $record): ?RecordPages
    {
        return null;
    }

    /**
     * Label/value rows shown in the header of every page of a record view with
     * record pages (e.g. status and phase). Null = none.
     *
     * @return ArrayList<ArrayData>|null
     */
    protected function defineHeaderFields(DataObject $record): ?ArrayList
    {
        return null;
    }

    /**
     * URL of an image shown at the top of the record sidebar (e.g. a photo).
     */
    protected function defineRecordImage(DataObject $record): ?string
    {
        return null;
    }

    /**
     * Field layout of the edit form and of saving: the record's layout, limited to
     * the groups of the requested record page (?page=<segment> on the form,
     * RecordPage in the submitted data), so a page edits only its own fields.
     */
    protected function formLayout(?DataObject $record): ?FieldLayout
    {
        $layout = $this->defineFieldLayout($record);
        $page = $record ? $this->requestedRecordPage($record) : null;
        if ($layout && $page) {
            return $layout->onlyGroups($page->groupKeys());
        }
        return $layout;
    }

    protected function requestedRecordPage(DataObject $record): ?RecordPage
    {
        $request = $this->getRequest();
        $segment = (string) ($request->isPOST() ? $request->postVar('RecordPage') : $request->getVar('page'));
        if ($segment === '') {
            return null;
        }
        $page = $this->defineRecordPages($record)?->find($segment);
        return $page && $page->groupKeys() ? $page : null;
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

    /**
     * Data of a record view with record pages: sidebar, header and the blocks of
     * the current page (404 for an unknown or hidden page).
     *
     * @return array<string, mixed>
     */
    protected function recordPageView(DataObject $item, RecordPages $pages, string $segment, string $title): array
    {
        $visible = array_values(array_filter(
            $pages->getPages(),
            fn (RecordPage $page) => $this->recordPageIsVisible($item, $page)
        ));
        $current = $visible[0] ?? null;
        if ($segment !== '') {
            $current = null;
            foreach ($visible as $page) {
                if ($page->getSegment() === $segment) {
                    $current = $page;
                }
            }
        }
        if (!$current) {
            $this->httpError(404);
        }

        $sidebar = ArrayList::create();
        foreach ($pages->getItems() as $entry) {
            if ($entry instanceof RecordPage) {
                if (!in_array($entry, $visible, true)) {
                    continue;
                }
                $sidebar->push(ArrayData::create([
                    'Type' => 'page',
                    'Title' => $entry->getTitle(),
                    'Link' => $this->recordPageLink($item, $entry),
                    'Active' => $entry === $current,
                ]));
            } elseif ($entry instanceof RecordSwitcher) {
                $sidebar->push(ArrayData::create(['Type' => 'switcher', 'Switcher' => $entry]));
            } else {
                $sidebar->push(ArrayData::create(['Type' => 'separator']));
            }
        }

        return [
            'Item'             => $item,
            'Title'            => $title,
            'RecordPages'      => $sidebar,
            'CurrentPage'      => ArrayData::create([
                'Title' => $current->getTitle(),
                'Segment' => $current->getSegment(),
                'EditLink' => $this->recordPageEditLink($item, $current),
            ]),
            'PageContent'      => $this->renderRecordPage($item, $current),
            'HeaderFields'     => $this->defineHeaderFields($item),
            'RecordImage'      => $this->defineRecordImage($item),
            'ViewActions'      => $this->defineViewActions($item)->exclude('Primary', true),
            'RecordActions'    => $this->recordActionsFor($item),
            'SubControllerData' => ArrayList::create(),
        ];
    }

    /**
     * Whether a sub-controller list is shown for the record on a record page
     * (subclasses hide lists from some members, e.g. lists only editors may see).
     *
     * @param class-string $class
     */
    protected function canShowSubController(string $class, DataObject $record): bool
    {
        return true;
    }

        protected function recordPageLink(DataObject $record, RecordPage $page): string
    {
        return $this->Link('view/' . $record->ID . '/' . $page->getSegment());
    }

    /**
     * Link to edit the fields of a page, null when it has none the member may write.
     */
    protected function recordPageEditLink(DataObject $record, RecordPage $page): ?string
    {
        $keys = $page->groupKeys();
        if (!$keys || !$this->canEdit()) {
            return null;
        }
        $layout = $this->defineFieldLayout($record);
        if ($layout && !$layout->hasFieldsIn($keys, true)) {
            return null;
        }
        return $this->Link('edit/' . $record->ID) . '?page=' . rawurlencode($page->getSegment())
            . '&BackURL=' . rawurlencode($this->recordPageLink($record, $page));
    }

    protected function recordPageIsVisible(DataObject $record, RecordPage $page): bool
    {
        foreach ($page->getBlocks() as $block) {
            switch ($block['type']) {
                case RecordPage::GROUPS:
                    $layout = $this->defineFieldLayout($record);
                    if (!$layout || $layout->hasFieldsIn($block['keys'])) {
                        return true;
                    }
                    break;
                case RecordPage::RELATED:
                    $related = ($block['resolver'])($record);
                    if ($related instanceof DataObject && $related->exists()) {
                        $controller = $this->embeddedController($block['class']);
                        if ($controller->canView()) {
                            return true;
                        }
                    }
                    break;
                case RecordPage::TIMELINE:
                    if ($this->timelineFor($record) !== null) {
                        return true;
                    }
                    break;
                case RecordPage::SUBLIST:
                    if ($this->canShowSubController($block['class'], $record)) {
                        return true;
                    }
                    break;
                default:
                    return true;
            }
        }
        return false;
    }

    protected function renderRecordPage(DataObject $record, RecordPage $page): DBHTMLText
    {
        $html = '';
        $layout = $this->defineFieldLayout($record);
        foreach ($page->getBlocks() as $block) {
            switch ($block['type']) {
                case RecordPage::GROUPS:
                    if ($layout) {
                        $html .= $this->renderWith(
                            'Atwx\\SilverstripeFrontdeskKit\\Includes\\ViewGroups',
                            ['ViewGroups' => $layout->onlyGroups($block['keys'])->viewGroups($this->viewFieldRows($record))]
                        )->forTemplate();
                    } else {
                        $html .= $this->renderWith(
                            'Atwx\\SilverstripeFrontdeskKit\\Includes\\ViewDetail',
                            ['ViewFields' => $this->defineViewFields($record)]
                        )->forTemplate();
                    }
                    break;
                case RecordPage::SUBLIST:
                    if (!$this->canShowSubController($block['class'], $record)) {
                        break;
                    }
                    $sub = singleton($block['class']);
                    $segment = (string) $sub->config()->get('url_segment');
                    $html .= $this->renderWith('Atwx\\SilverstripeFrontdeskKit\\Includes\\SubListBlock', [
                        'Title' => $block['title'] ?? $sub->config()->get('title'),
                        'Segment' => $segment,
                        'Url' => $this->Link($segment . '/' . $record->ID),
                    ])->forTemplate();
                    break;
                case RecordPage::RELATED:
                    $related = ($block['resolver'])($record);
                    if ($related instanceof DataObject && $related->exists()) {
                        $html .= (string) $this->embeddedController($block['class'])->embeddedView(
                            (int) $related->ID,
                            $this->recordPageLink($record, $page),
                            (bool) $block['sublists']
                        );
                    }
                    break;
                case RecordPage::TIMELINE:
                    $timeline = $this->timelineFor($record);
                    if ($timeline !== null) {
                        $html .= $this->renderWith('Atwx\\SilverstripeFrontdeskKit\\Includes\\Timeline', [
                            'Timeline' => $timeline,
                            'TimelineTitle' => $this->timelineTitle(),
                        ])->forTemplate();
                    }
                    break;
                case RecordPage::CONTENT:
                    $html .= (string) ($block['renderer'])($record);
                    break;
            }
        }
        return DBHTMLText::create()->setValue($html);
    }

    /**
     * Another frontdesk controller, set up to render a related record on this page.
     *
     * @param class-string $class
     */
    protected function embeddedController(string $class): FrontdeskController
    {
        /** @var FrontdeskController $controller */
        $controller = $class::create();
        $controller->setRequest($this->getRequest());
        return $controller;
    }

    /**
     * View of one of this controller's records embedded in another record's page:
     * its fields, an edit button (back to $backUrl after saving) and its sub-lists.
     * Null when the record is not in the base list or the member may not view it.
     */
    public function embeddedView(int $id, string $backUrl, bool $withSublists = true): ?DBHTMLText
    {
        if (!$this->canView()) {
            return null;
        }
        $record = $this->findRecord($id, 'canView');
        if (!$record) {
            return null;
        }
        $editLink = $this->canEdit()
            ? $this->Link('edit/' . $record->ID) . '?BackURL=' . rawurlencode($backUrl)
            : null;
        return $this->renderWith('Atwx\\SilverstripeFrontdeskKit\\Includes\\EmbeddedRecord', [
            'Record' => $record,
            'EditLink' => $editLink,
            'Fields' => $this->defineViewContent($record),
            'SubControllerData' => $withSublists ? $this->getSubControllerData($record) : ArrayList::create(),
        ]);
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

    // ─── List views ───────────────────────────────────────────────────────────

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
     * The view chosen with ?view=<key>, if any.
     */
    protected function currentListView(): ?ListView
    {
        $request = $this->getRequest();
        $key = $request ? (string) $request->getVar('view') : '';
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
        $current = (string) $this->getRequest()->getVar('view');
        $views = $this->allListViews();
        $result = ArrayList::create();
        if (!$views && !$this->canSaveListViews()) {
            return $result;
        }
        $result->push(ArrayData::create([
            'Title' => _t(self::class . '.VIEW_ALL', 'All'),
            'Link' => $this->Link(),
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

    protected function assertCanCreate(): void
    {
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
