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
        return $id ? $this->getBaseQuery()->byID($id) : null;
    }

    /**
     * Return the base (unfiltered) query scoped to the parent record.
     */
    abstract protected function getBaseQuery(): DataList;

    /**
     * Override: apply filters from request to the scoped base query.
     */
    protected function getQuery(): DataList
    {
        return $this->defineFilters()->applyAll($this->getBaseQuery(), $this->getRequest());
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
            $item = $class::get()->byID($data['ID']);
            if (!$item) {
                return $this->httpError(404);
            }
        } else {
            $item = $class::create();
        }

        $form->saveInto($item);
        if ($parentField = static::config()->get('parent_field')) {
            $item->$parentField = $this->parentID;
        }
        $this->beforeWrite($item, $data);
        $item->write();
        $this->afterWrite($item, $data);

        return $this->redirect($this->parentController->Link('view/' . $this->parentID));
    }

    /**
     * Delete a record scoped to this sub-controller's parent.
     * Subclasses can hook into beforeDelete()/afterDelete() rather than overriding.
     */
    public function delete(HTTPRequest $request)
    {
        $id = $request->param('ID');
        $item = $id ? $this->getBaseQuery()->byID($id) : null;
        if ($item) {
            $this->beforeDelete($item);
            $item->delete();
            $this->afterDelete($item);
        }

        if ($this->isHtmxRequest()) {
            return HTTPResponse::create('', 200);
        }
        return $this->redirect($this->parentController->Link('view/' . $this->parentID));
    }

    /** Hook: mutate $item before it is written. */
    protected function beforeWrite(DataObject $item, array $data): void
    {
    }

    /** Hook: react after $item is written. */
    protected function afterWrite(DataObject $item, array $data): void
    {
    }

    /** Hook: cascade-delete related records before $item is deleted. */
    protected function beforeDelete(DataObject $item): void
    {
    }

    /** Hook: react after $item is deleted. */
    protected function afterDelete(DataObject $item): void
    {
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
