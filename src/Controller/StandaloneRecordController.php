<?php

namespace Atwx\SilverstripeFrontdeskKit\Controller;

use Atwx\SilverstripeFrontdeskKit\Catalog\CatalogFields;
use Atwx\SilverstripeFrontdeskKit\Catalog\Catalogs;
use Atwx\SilverstripeFrontdeskKit\Forms\FieldLayout;
use Atwx\SilverstripeFrontdeskKit\Forms\FileUploadField;
use Atwx\SilverstripeFrontdeskKit\Forms\RecordFieldNames;
use Atwx\SilverstripeFrontdeskKit\Forms\RecordFiles;
use Atwx\SilverstripeFrontdeskKit\Forms\SearchableDropdownField;
use Atwx\SilverstripeFrontdeskKit\Table\RecordAction;
use Atwx\SilverstripeFrontdeskKit\View\ViewRow;
use SilverStripe\Assets\File;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\i18n\i18n;
use SilverStripe\Model\ArrayData;
use SilverStripe\ORM\DataObject;
use SilverStripe\View\SSViewer;

/**
 * A page for a single record without login and without the frontdesk
 * navigation, e.g. for an edit link sent to someone without an account.
 *
 * The subclass decides which record a request may see (resolveRecord(), e.g.
 * from a token in the URL) and which fields and actions it gets. Field levels,
 * the form and saving work as in FrontdeskController (FieldLayout): the form
 * holds the visible fields, only writable fields are saved.
 *
 * Rendered with the main template "FrontdeskStandalonePage" (override it in
 * the project to load the project's styles) and the layout
 * "StandaloneRecordController". Responses are not cached and not indexed.
 */
abstract class StandaloneRecordController extends Controller
{
    use RecordActionsTrait;

    private static $allowed_actions = [
        'index',
        'RecordForm',
        'recordaction',
        'file',
    ];

    /**
     * The first URL segment after the route is the key ($request->param('Key')),
     * e.g. a token: route "edit" → edit/<key>, edit/<key>/recordaction.
     */
    private static $url_handlers = [
        '$Key!/$Action//$ID' => 'handleAction',
    ];

    private ?DataObject $record = null;

    private bool $resolved = false;

    // ─── Override in subclass ─────────────────────────────────────────────────

    /**
     * The record this request may access, or null (→ 404).
     */
    abstract protected function resolveRecord(HTTPRequest $request): ?DataObject;

    /**
     * Base URL of this page (including the token or key), see Link().
     */
    abstract protected function baseLink(): string;

    abstract protected function defineFieldLayout(DataObject $record): FieldLayout;

    /**
     * Modify the (scaffolded) form fields before the layout is applied, e.g. to
     * add virtual fields. Values of added fields are not loaded from the record.
     */
    protected function formFields(FieldList $fields, DataObject $record): FieldList
    {
        return $fields;
    }

    /**
     * @return RecordAction[]
     */
    protected function defineRecordActions(DataObject $record): array
    {
        return [];
    }

    protected function pageTitle(DataObject $record): string
    {
        return (string) $record->getTitle();
    }

    /**
     * Optional text above the record (plain text).
     */
    protected function introText(DataObject $record): string
    {
        return '';
    }

    /** Hook: the page was shown (GET). */
    protected function onView(DataObject $record): void
    {
    }

    /**
     * Hook: before the record is written. $changed lists the fields that changed.
     *
     * @param array<int, string> $changed
     */
    protected function beforeSave(DataObject $record, array $changed): void
    {
    }

    /**
     * @param array<int, string> $changed
     */
    protected function afterSave(DataObject $record, array $changed): void
    {
    }

    // ─── Actions ──────────────────────────────────────────────────────────────

    public function handleRequest(HTTPRequest $request): HTTPResponse
    {
        $response = parent::handleRequest($request);
        $response->addHeader('Cache-Control', 'no-store, private');
        $response->addHeader('X-Robots-Tag', 'noindex, nofollow');
        $response->addHeader('Referrer-Policy', 'no-referrer');
        return $response;
    }

    public function index(HTTPRequest $request)
    {
        $record = $this->currentRecord();
        $this->onView($record);
        $layout = $this->defineFieldLayout($record);
        $form = $this->RecordForm();

        return $this->customise([
            'Title' => $this->pageTitle($record),
            'IntroText' => $this->introText($record),
            'Item' => $record,
            'Form' => $form,
            'ViewGroups' => $form ? null : $layout->viewGroups($this->viewRows($record)),
            'RecordActions' => $this->recordActionsFor($record),
        ])->renderWith($this->standaloneTemplates());
    }

    public function recordaction(HTTPRequest $request)
    {
        return $this->runRecordAction($request, $this->currentRecord(), $this->Link());
    }

    /**
     * Form with the visible fields; null when nothing is writable.
     */
    public function RecordForm(): ?Form
    {
        $record = $this->currentRecord();
        $layout = $this->defineFieldLayout($record);
        $fields = $layout->applyTo(
            $this->formFields(
                CatalogFields::apply(FileUploadField::replaceCmsFields(
                    SearchableDropdownField::replaceCmsFields($record->scaffoldFormFields([
                        'ignoreFields' => RecordFieldNames::withRelationNames((array) $record->config()->get('fdk_hidden_fields')),
                    ])),
                    $record
                ), $record),
                $record
            )
        );
        if ($fields->saveableFields() === []) {
            return null;
        }
        $form = Form::create(
            $this,
            'RecordForm',
            $fields,
            FieldList::create(FormAction::create('save', _t(FrontdeskController::class . '.ACTION_SAVE', 'Save')))
        );
        $form->loadDataFrom($record);
        foreach ($form->Fields()->dataFields() as $field) {
            if ($field instanceof FileUploadField) {
                $field->setDownloadLink($this->Link('file/' . $field->getName()));
            }
        }
        return $form;
    }

    /**
     * Download of a file of the record: <key>/file/<FieldName>, for fields visible
     * in the record's field layout.
     */
    public function file(HTTPRequest $request): HTTPResponse
    {
        $record = $this->currentRecord();
        $file = RecordFiles::visibleFile($record, (string) $request->param('ID'), $this->defineFieldLayout($record));
        if (!$file) {
            return $this->httpError(404);
        }
        return RecordFiles::response($file, $request->getVar('download') !== null);
    }

    public function save(array $data, Form $form): HTTPResponse
    {
        $record = $this->currentRecord();
        $form->saveInto($record);
        $this->defineFieldLayout($record)->resetLockedChanges($record);
        $changed = array_keys($record->getChangedFields(false, DataObject::CHANGE_VALUE));
        $this->beforeSave($record, $changed);
        $record->write();
        $this->afterSave($record, $changed);
        $this->flash(_t(self::class . '.SAVED', 'Your changes have been saved.'));
        return $this->redirect($this->Link());
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function ContentLocale(): string
    {
        return i18n::convert_rfc1766(i18n::get_locale());
    }

    public function Link($action = null): string
    {
        return Controller::join_links($this->baseLink(), $action);
    }

    protected function recordActionUrl(DataObject $record): string
    {
        return $this->Link('recordaction');
    }

    /**
     * The record of this request; 404 if there is none.
     */
    protected function currentRecord(): DataObject
    {
        if (!$this->resolved) {
            $this->record = $this->resolveRecord($this->getRequest());
            $this->resolved = true;
        }
        if (!$this->record) {
            $this->httpError(404);
        }
        return $this->record;
    }

    /**
     * View rows keyed by field name (db fields and has_one relations).
     *
     * @return array<string, ArrayData>
     */
    protected function viewRows(DataObject $record): array
    {
        $rows = [];
        $labels = $record->fieldLabels(false);
        $hidden = RecordFieldNames::withRelationNames((array) $record->config()->get('fdk_hidden_fields'));
        foreach ($record->config()->get('db') ?? [] as $name => $type) {
            if (in_array($name, $hidden, true)) {
                continue;
            }
            $rows[$name] = ViewRow::forField($labels[$name] ?? $name, $record, $name);
        }
        foreach ($record->config()->get('has_one') ?? [] as $name => $class) {
            if (in_array($name, $hidden, true)) {
                continue;
            }
            if (is_string($class) && is_a($class, File::class, true)) {
                $file = RecordFiles::fileOf($record, $name);
                if ($file) {
                    $rows[$name . 'ID'] = RecordFiles::viewRow($labels[$name] ?? $name, $file, $this->Link('file/' . $name . 'ID'));
                }
                continue;
            }
            $related = $record->$name();
            if ($related && $related->exists()) {
                $rows[$name . 'ID'] = ArrayData::create([
                    'Label' => $labels[$name] ?? $name,
                    'Value' => (string) $related->getTitle(),
                    'Type' => 'text',
                ]);
            }
        }
        $catalogs = Catalogs::singleton();
        foreach ($catalogs->fieldsOf($record) as $relation => $key) {
            if ($catalogs->relationType($record::class, $relation) === 'many_many') {
                $rows[$relation] = ArrayData::create([
                    'Label' => $labels[$relation] ?? $catalogs->title($key),
                    'Value' => $catalogs->titlesOf($record, $relation),
                    'Type' => 'text',
                ]);
            }
        }
        return $rows;
    }

    /**
     * @return array<int, string>
     */
    protected function standaloneTemplates(): array
    {
        return [
            static::class,
            self::class,
            'FrontdeskStandalonePage',
        ];
    }

    public function getViewer($action)
    {
        return SSViewer::create($this->standaloneTemplates());
    }
}
