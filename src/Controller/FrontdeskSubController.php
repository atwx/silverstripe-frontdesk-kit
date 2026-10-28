<?php

namespace Atwx\SilverstripeFrontdeskKit\Controller;

use Atwx\SilverstripeFrontdeskKit\Filter\FilterCollection;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
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
    ];
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
        return $this->defineFilters()->applyAll($this->getBaseList(), $this->getRequest());
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
