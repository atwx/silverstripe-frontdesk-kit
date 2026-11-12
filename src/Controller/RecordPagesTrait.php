<?php

namespace Atwx\SilverstripeFrontdeskKit\Controller;

use Atwx\SilverstripeFrontdeskKit\Forms\FieldLayout;
use Atwx\SilverstripeFrontdeskKit\View\RecordPage;
use Atwx\SilverstripeFrontdeskKit\View\RecordPages;
use Atwx\SilverstripeFrontdeskKit\View\RecordSwitcher;
use SilverStripe\Control\Controller;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\Model\ArrayData;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBHTMLText;

/**
 * Record pages ("sub views") of a FrontdeskController: sidebar, header,
 * page-wise editing and embedded views of related records. See RecordPage.
 */
trait RecordPagesTrait
{
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
}
