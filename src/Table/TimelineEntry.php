<?php

namespace Atwx\SilverstripeFrontdeskKit\Table;

use SilverStripe\Model\ArrayData;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBField;

/**
 * One entry of a record's timeline (see FrontdeskController::defineTimeline()).
 *
 * Entries from different sources (e.g. notes, status changes, accesses) are
 * merged by the controller; the timeline shows them newest first.
 */
class TimelineEntry
{
    /**
     * @param string $kind Free-form type, used as CSS modifier (fdk-timeline-<kind>)
     */
    public static function create(
        string $date,
        string $title,
        string $text = '',
        string $author = '',
        string $kind = ''
    ): ArrayData {
        /** @var DBDatetime $dbDate */
        $dbDate = DBField::create_field('Datetime', $date);
        return ArrayData::create([
            'Date' => $dbDate,
            'Timestamp' => (int) strtotime($date),
            'Title' => $title,
            'Text' => $text,
            'Author' => $author,
            'Kind' => preg_replace('/[^a-z0-9-]/', '', strtolower($kind)),
        ]);
    }
}
