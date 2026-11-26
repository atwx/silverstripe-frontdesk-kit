<?php

namespace Atwx\SilverstripeFrontdeskKit\View;

use SilverStripe\Core\Injector\Injectable;

/**
 * One page of a record view ("sub view"), listed in the record's sidebar (or
 * shown as a tab, see RecordPages::layout()) at <segment>/view/<ID>/<page>.
 * A page is made of blocks, shown in the order they were added:
 *
 *   RecordPage::create('placement', 'Vermittlung')
 *       ->groups(['placement', 'feedback'])          // field groups of the record, editable on this page
 *       ->sublist(ApplicationSubController::class)   // a sub-controller list
 *       ->related(fn ($r) => $r->Child(), ChildController::class)  // view of a related record
 *       ->timeline()                                 // the record's timeline
 *       ->content(fn ($r) => '<p>…</p>');            // own HTML
 *
 * The "Edit" button of a page edits only the fields of its groups. The same
 * blocks can be placed on a card (RecordCard).
 */
class RecordPage
{
    use Injectable;
    use RecordBlocks;

    public const string GROUPS = 'groups';
    public const string SUBLIST = 'sublist';
    public const string RELATED = 'related';
    public const string TIMELINE = 'timeline';
    public const string CONTENT = 'content';

    public function __construct(private string $segment, private string $title)
    {
    }

    public function getSegment(): string
    {
        return $this->segment;
    }

    public function getTitle(): string
    {
        return $this->title;
    }
}
