<?php

namespace Atwx\SilverstripeFrontdeskKit\Controller;

use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\ORM\DataObject;

/**
 * The "⋯" menu of a record header for entries used rarely, and the page
 * "History" (config timeline_page): the record's timeline on a page of its
 * own (view/<ID>/history), reached through that menu.
 *
 * Entries of the menu: "History", view actions with 'Menu' => 'more' (or
 * a 'Name' listed in record_action_menu) and record actions with
 * ->menu('more') (or listed in record_action_menu).
 */
trait RecordMoreMenuTrait
{
    public function timelinePageEnabled(): bool
    {
        return (bool) static::config()->get('timeline_page');
    }

    public function timelinePageSegment(): string
    {
        return (string) (static::config()->get('timeline_page_segment') ?: 'history');
    }

    public function historyLink(DataObject $record): string
    {
        return $this->Link('view/' . $record->ID . '/' . $this->timelinePageSegment());
    }

    /**
     * Whether a view action (link) belongs in the "⋯" menu.
     */
    protected function isMenuViewAction(mixed $action): bool
    {
        $map = $action instanceof ArrayData ? $action->toMap() : [];
        if (!empty($map['Menu'])) {
            return true;
        }
        $name = (string) ($map['Name'] ?? '');
        return $name !== '' && isset(((array) static::config()->get('record_action_menu'))[$name]);
    }

    /**
     * View actions shown as buttons (those of the menu left out).
     */
    protected function buttonViewActions(ArrayList $actions): ArrayList
    {
        return $actions->filterByCallback(fn ($action) => !$this->isMenuViewAction($action));
    }

    /**
     * The "⋯" menu: links (History, view actions) and record actions; null
     * when it has no entries.
     */
    protected function recordMoreMenu(DataObject $record, ArrayList $viewActions, ArrayList $recordActions): ?ArrayData
    {
        $links = ArrayList::create();
        if ($this->timelinePageEnabled() && $this->timelineFor($record) !== null) {
            $links->push(ArrayData::create([
                'Title' => _t(FrontdeskController::class . '.HISTORY', 'History'),
                'Link' => $this->historyLink($record),
                'Target' => null,
            ]));
        }
        foreach ($viewActions as $action) {
            if ($this->isMenuViewAction($action)) {
                $map = $action->toMap();
                $links->push(ArrayData::create([
                    'Title' => $map['Title'] ?? '',
                    'Link' => $map['Link'] ?? '',
                    'Target' => $map['Target'] ?? null,
                ]));
            }
        }
        $actions = $recordActions->filter('InMenu', true);
        if (!$links->count() && !$actions->count()) {
            return null;
        }
        return ArrayData::create(['Links' => $links, 'Actions' => $actions]);
    }

    /**
     * Data of the page "History": header like the record view, the whole
     * timeline, a link back.
     *
     * @return array<string, mixed>
     */
    protected function historyView(DataObject $item, string $title): array
    {
        $timeline = $this->timelineFor($item);
        if ($timeline === null) {
            $this->httpError(404);
        }
        $pages = $this->defineRecordPages($item);
        $tabs = $pages?->isTabs() ?? false;
        return [
            'Item' => $item,
            'Title' => $title,
            'IsHistory' => true,
            'HistoryTitle' => _t(FrontdeskController::class . '.HISTORY', 'History'),
            'BackLink' => $this->Link('view/' . $item->ID),
            'HeaderFields' => $this->defineHeaderFields($item),
            'HeaderStatus' => $tabs ? $this->defineHeaderStatus($item) : null,
            'Timeline' => $timeline,
            // The page title says it already
            'TimelineTitle' => '',
            'RecordActions' => ArrayList::create(),
            'ViewActions' => ArrayList::create(),
            'SubControllerData' => ArrayList::create(),
        ];
    }
}
