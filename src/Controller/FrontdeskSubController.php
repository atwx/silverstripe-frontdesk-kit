<?php

namespace Atwx\SilverstripeFrontdeskKit\Controller;

use Atwx\SilverstripeFrontdeskKit\Catalog\CatalogFields;
use Atwx\SilverstripeFrontdeskKit\Forms\FileUploadField;
use Atwx\SilverstripeFrontdeskKit\Forms\RecordFieldNames;
use Atwx\SilverstripeFrontdeskKit\Forms\SearchableDropdownField;
use Atwx\SilverstripeFrontdeskKit\Filter\FilterCollection;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\FormField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\SingleSelectField;
use SilverStripe\Model\List\PaginatedList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;

abstract class FrontdeskSubController extends FrontdeskController
{
    /**
     * FK column on the managed model pointing at the parent record.
     * Set to e.g. 'WebsiteID'. When set, save() will populate it automatically.
     */
    private static $parent_field = null;

    /**
     * Declared here so overrides in subclasses inherit the permission check.
     * SilverStripe's checkAccessAction uses definingClassForAction without
     * inheritance, so each class that defines save()/delete() needs its own entry.
     */
    private static $allowed_actions = [
        'save'   => '->canEdit',
        'delete' => '->canEdit',
        'bulkedit' => '->canBulkEdit',
        'BulkEditForm' => '->canBulkEdit',
    ];

    /**
     * Fields that can be set for several entries at once ("Edit several"): only
     * filled fields are applied, checkboxes have the choice unchanged/yes/no.
     * Empty: no bulk editing.
     *
     * @var array<int, string>
     */
    private static $bulk_edit_fields = [];
    /**
     * Two rules are needed:
     * 1. "$ParentID/$Action//$ID/$OtherID" – handles edit/delete/save with a record ID
     *    (e.g. domains/23/edit/19 → ParentID=23, Action=edit, ID=19)
     * 2. "$ParentID//$Action" – handles index/add where no record ID follows the action
     *    (e.g. domains/23 → ParentID=23, Action=index)
     * Without rule 1, $request->param('ID') is never set for edit/delete routes.
     */
    private static $url_handlers = [
        '$ParentID/$Action//$ID/$OtherID' => 'handleAction',
        '$ParentID//$Action'              => 'handleAction',
    ];

    protected ?FrontdeskController $parentController = null;
    protected int $parentID = 0;

    public function setParentContext(FrontdeskController $parent, int $parentId): static
    {
        $this->parentController = $parent;
        $this->parentID = $parentId;
        return $this;
    }

    public function getParentID(): int
    {
        return $this->parentID;
    }

    /**
     * Load the record currently being edited/viewed, scoped to this parent.
     * Returns null on add or when the record does not belong to this parent.
     */
    protected function currentRecord(): ?DataObject
    {
        $id = $this->currentRecordID();
        return $id ? $this->getBaseList()->byID($id) : null;
    }

    /**
     * Return the base (unfiltered) query scoped to the parent record.
     */
    abstract protected function getBaseQuery(): DataList;

    /**
     * The parent field is set on save; it is neither shown in the form (a dropdown
     * of all possible parents) nor in the view.
     *
     * @return array<int, string>
     */
    protected function hiddenFields(DataObject $record): array
    {
        $hidden = parent::hiddenFields($record);
        $parentField = (string) static::config()->get('parent_field');
        return $parentField === ''
            ? $hidden
            : array_values(array_unique(array_merge($hidden, RecordFieldNames::withRelationNames([$parentField]))));
    }

    /**
     * Records are always loaded from the parent-scoped query.
     */
    protected function getBaseList(): DataList
    {
        $list = $this->getBaseQuery();
        $this->extend('updateBaseList', $list);
        return $list;
    }

    /**
     * Override: apply filters from request to the scoped base query.
     */
    protected function getQuery(): DataList
    {
        return $this->applyColumnSort($this->defineFilters()->applyAll($this->getBaseList(), $this->getRequest()));
    }

    /**
     * Override: build URL relative to the parent controller.
     */
    public function Link($action = null): string
    {
        $parentSegment = $this->parentController->config()->get('url_segment');
        $subSegment = static::config()->get('url_segment');
        return Controller::join_links($parentSegment, $subSegment, $this->parentID, $action);
    }

    /**
     * The CSS selector for the container div this sublist renders into.
     * Used as hx-target in filter form and pagination.
     */
    public function HtmxTarget(): string
    {
        return '#fdk-sublist-' . static::config()->get('url_segment');
    }

    /**
     * Override: render SubListTable partial for HTMX requests so that the
     * filter bar and pagination links also carry HTMX attributes.
     */
    public function index(HTTPRequest $request)
    {
        if ($this->isTab()) {
            return parent::index($request);
        }
        if ($this->isHtmxRequest()) {
            return $this->renderPartial(
                'Atwx\\SilverstripeFrontdeskKit\\Includes\\SubListTable',
                [
                    'Items'   => $this->getItems(),
                    'Columns' => $this->defineColumns(),
                ]
            );
        }

        return parent::index($request);
    }

    /**
     * Override: pass a request built from Link() so PaginatedList generates
     * correct pagination URLs (the shifted sub-request URL would be wrong).
     */
    protected function getItems(): PaginatedList
    {
        $pageLength = (int) (static::config()->get('page_length') ?: 30);
        $paginationRequest = new HTTPRequest('GET', $this->Link(), $this->getRequest()->getVars());
        return PaginatedList::create($this->getQuery(), $paginationRequest)
            ->setPageLength($pageLength);
    }

    /**
     * After saving, return to the parent controller's view page.
     * Subclasses can hook into beforeWrite()/afterWrite() rather than overriding.
     */
    public function save($data, \SilverStripe\Forms\Form $form)
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
        if ($parentField = static::config()->get('parent_field')) {
            $item->$parentField = $this->parentID;
        }
        $this->beforeWrite($item, $data);
        $item->write();
        $this->afterWrite($item, $data);

        return $this->redirect($this->afterSaveLink());
    }

    /**
     * Whether this sub-controller is shown as a tab of the parent record.
     */
    public function isTab(): bool
    {
        return (bool) $this->parentController?->config()->get('sub_controller_tabs');
    }

    /**
     * Where to go after saving or deleting: the tab list or the parent record view.
     */
    protected function afterSaveLink(): string
    {
        return $this->isTab() ? $this->Link() : $this->parentController->Link('view/' . $this->parentID);
    }

    // ─── Bulk editing ─────────────────────────────────────────────────────────

    /**
     * Whether several entries can be edited at once (bulk_edit_fields set, edit permission).
     */
    public function canBulkEdit(): bool
    {
        return (array) static::config()->get('bulk_edit_fields') !== [] && $this->canEdit();
    }

    /**
     * Form "Edit several" (in the modal): the entries, all preselected, and the
     * bulk edit fields, empty.
     */
    public function bulkedit(HTTPRequest $request)
    {
        $form = $this->BulkEditForm();
        $title = _t(self::class . '.BULK_EDIT_TITLE', 'Edit several');
        if ($this->isHtmxRequest()) {
            return $this->renderPartial(
                'Atwx\\SilverstripeFrontdeskKit\\Includes\\EditFormModal',
                ['Form' => $form, 'Title' => $title, 'Item' => null]
            );
        }
        return ['Title' => $title, 'Form' => $form, 'Actions' => false];
    }

    public function BulkEditForm(): Form
    {
        $entries = [];
        foreach ($this->getBaseList() as $record) {
            if (!static::config()->get('check_record_permissions') || $record->canEdit()) {
                $entries[(int) $record->ID] = (string) $record->getTitle();
            }
        }
        $fields = FieldList::create(
            CheckboxSetField::create('BulkEntries', _t(self::class . '.BULK_ENTRIES', 'Entries'), $entries)
                ->setValue(array_keys($entries))
        );
        $unchanged = _t(self::class . '.BULK_UNCHANGED', '– unchanged –');
        foreach ($this->bulkEditFieldList() as $field) {
            if ($field instanceof CheckboxField) {
                $field = DropdownField::create($field->getName(), $field->Title(), [
                    '1' => _t(self::class . '.BULK_YES', 'Yes'),
                    '0' => _t(self::class . '.BULK_NO', 'No'),
                ])->setEmptyString($unchanged);
            } elseif ($field instanceof SingleSelectField) {
                $field->setHasEmptyDefault(true)->setEmptyString($unchanged);
            }
            $field->setValue(null);
            $fields->push($field);
        }
        $fields->push(LiteralField::create('BulkHint', '<p class="text-sm opacity-70">' . htmlspecialchars(_t(
            self::class . '.BULK_HINT',
            'Only filled fields are applied to the chosen entries; empty ones leave the values unchanged.'
        )) . '</p>'));
        $form = Form::create($this, 'BulkEditForm', $fields, FieldList::create(
            FormAction::create('doBulkEdit', _t(FrontdeskController::class . '.ACTION_SAVE', 'Save')),
            LiteralField::create('Cancel', '<a href="javascript:void(0);" onclick="var d=this.closest(\'dialog\'); '
                . 'if(d){d.close();return false;} history.back();" class="btn">'
                . _t(FrontdeskController::class . '.ACTION_CANCEL', 'Cancel') . '</a>')
        ));
        $form->setFormAction($this->Link('BulkEditForm'));
        return $form;
    }

    /**
     * Applies the filled fields to the chosen entries the member may edit; fields
     * locked by the field layout stay unchanged, entries that fail validation are
     * skipped and reported.
     *
     * @param array<string, mixed> $data
     */
    public function doBulkEdit(array $data, Form $form): HTTPResponse
    {
        $ids = array_filter(array_map('intval', (array) ($data['BulkEntries'] ?? [])));
        $apply = [];
        foreach ($this->bulkEditFieldList() as $field) {
            $name = $field->getName();
            $value = $data[$name] ?? null;
            $empty = $value === null || $value === '' || $value === [] || (is_array($value) && !array_filter($value));
            if (!$empty) {
                $apply[$name] = $field instanceof CheckboxField ? (bool) (int) $value : $value;
            }
        }
        $changed = 0;
        $skipped = [];
        foreach ($ids && $apply ? $this->getBaseList()->filter('ID', $ids) : [] as $record) {
            if (static::config()->get('check_record_permissions') && !$record->canEdit()) {
                continue;
            }
            foreach ($apply as $name => $value) {
                $field = $form->Fields()->dataFieldByName($name);
                if ($field && !is_bool($value)) {
                    $field->setSubmittedValue($value);
                    $field->saveInto($record);
                } else {
                    $record->$name = $value;
                }
            }
            $this->formLayout($record)?->resetLockedChanges($record);
            try {
                $record->write();
                $changed++;
            } catch (ValidationException $e) {
                $skipped[] = $record->getTitle() . ' (' . $e->getMessage() . ')';
            }
        }
        $message = _t(self::class . '.BULK_DONE', '{count} entries changed.', ['count' => $changed]);
        if ($skipped) {
            $message .= ' ' . _t(self::class . '.BULK_SKIPPED', 'Skipped: {list}', ['list' => implode('; ', $skipped)]);
        }
        $this->flash($message, $skipped ? 'error' : 'success');
        return $this->redirect($this->afterSaveLink());
    }

    /**
     * The edit form fields named in bulk_edit_fields (scaffolded like the edit form).
     *
     * @return array<int, FormField>
     */
    protected function bulkEditFieldList(): array
    {
        $names = (array) static::config()->get('bulk_edit_fields');
        if (!$names) {
            return [];
        }
        $item = DataObject::singleton($this->getManagedModel());
        $scaffolded = $this->formFields(CatalogFields::apply(FileUploadField::replaceCmsFields(
            SearchableDropdownField::replaceCmsFields($item->scaffoldFormFields()),
            $item
        ), $item));
        $fields = [];
        foreach ($names as $name) {
            $field = $scaffolded->dataFieldByName($name);
            if ($field) {
                $fields[] = $field;
            }
        }
        return $fields;
    }

    /**
     * The parent record, scoped by the parent controller's permissions.
     */
    public function ParentRecord(): ?DataObject
    {
        $class = $this->parentController?->getManagedModel();
        $record = $class ? DataObject::get($class)->byID($this->parentID) : null;
        return $record && $record->canView() ? $record : null;
    }

    /**
     * In tab mode the page shows the parent record's title above the tabs.
     */
    public function Title(): string
    {
        if ($this->isTab() && ($record = $this->ParentRecord())) {
            return (string) $record->getTitle();
        }
        return parent::Title();
    }

    public function Tabs(): ?\SilverStripe\Model\List\ArrayList
    {
        if (!$this->isTab()) {
            return null;
        }
        return $this->parentController->RecordTabs($this->parentID, static::config()->get('url_segment'));
    }

    /**
     * Delete a record scoped to this sub-controller's parent.
     * Subclasses can hook into beforeDelete()/afterDelete() rather than overriding.
     */
    public function delete(HTTPRequest $request)
    {
        $this->assertDeleteMethod($request);
        $id = $request->param('ID');
        $item = $id ? $this->findRecord((int) $id, 'canDelete') : null;
        if ($item) {
            $this->beforeDelete($item);
            $item->delete();
            $this->afterDelete($item);
        }

        if ($this->isHtmxRequest()) {
            return HTTPResponse::create('', 200);
        }
        return $this->redirect($this->afterSaveLink());
    }

    /**
     * Inherit view permission from parent controller.
     */
    public function canView($member = null): bool
    {
        return $this->parentController->canView($member);
    }

    /**
     * Inherit edit permission from parent controller.
     */
    public function canEdit($member = null): bool
    {
        return $this->parentController->canEdit($member);
    }
}
