<?php

namespace Atwx\SilverstripeFrontdeskKit\Controller;

use Atwx\SilverstripeFrontdeskKit\Filter\FilterCollection;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Model\List\PaginatedList;
use SilverStripe\ORM\DataList;

abstract class FrontdeskSubController extends FrontdeskController
{
    /**
     * Consume the parent ID segment from the URL, then optionally route to
     * a sub-action. Without this, Silverstripe treats the numeric ID as an
     * action name and throws a 403.
     */
    private static $url_handlers = [
        '$ParentID//$Action' => 'handleAction',
    ];

    private ?FrontdeskController $parentController = null;
    private int $parentID = 0;

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
