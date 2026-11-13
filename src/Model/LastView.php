<?php

namespace Atwx\SilverstripeFrontdeskKit\Model;

use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Security\Member;

/**
 * When a record was last viewed by anyone, and by whom: one entry per record
 * (base class and ID), updated with every noted view (RecentView::note()).
 * Unlike the recently viewed entries it does not drop out of a per-member
 * limit. Shown as a list column with ColumnCollection::lastViewed().
 *
 * @property ?string $BaseClass
 * @property int $RecordID
 * @property ?string $ViewedAt
 * @property int $MemberID
 * @method Member Member()
 */
class LastView extends DataObject
{
    private static string $table_name = 'FDK_LastView';

    private static array $db = [
        'BaseClass' => 'Varchar(255)',
        'RecordID' => 'Int',
        'ViewedAt' => 'Datetime',
    ];

    private static array $has_one = [
        'Member' => Member::class,
    ];

    private static array $indexes = [
        'Record' => ['type' => 'unique', 'columns' => ['BaseClass', 'RecordID']],
    ];

    public static function note(DataObject $record, Member $member): void
    {
        if (!$record->isInDB()) {
            return;
        }
        $entry = static::forRecord($record) ?? static::create([
            'BaseClass' => $record->baseClass(),
            'RecordID' => $record->ID,
        ]);
        $entry->ViewedAt = DBDatetime::now()->getValue();
        $entry->MemberID = $member->ID;
        $entry->write();
    }

    public static function forRecord(DataObject $record): ?static
    {
        $entry = static::get()->filter([
            'BaseClass' => $record->baseClass(),
            'RecordID' => (int) ($record->ID ?: $record->OldID),
        ])->first();
        return $entry instanceof static ? $entry : null;
    }

    public static function forget(DataObject $record): void
    {
        static::forRecord($record)?->delete();
    }

    /**
     * "13.11.2026 14:05, Olga Organisation", or '' when never viewed.
     */
    public function summary(): string
    {
        $time = strtotime((string) $this->ViewedAt);
        $member = $this->Member();
        $name = $member->exists() ? trim($member->getName()) : '';
        return trim(($time ? date('d.m.Y H:i', $time) : '') . ($name !== '' ? ', ' . $name : ''), ', ');
    }

    /**
     * Sorts a list by the last view (records never viewed last when ascending).
     *
     * @template T of DataObject
     * @param DataList<T> $list
     * @return DataList<T>
     */
    public static function sortList(DataList $list, string $dir): DataList
    {
        $schema = DataObject::getSchema();
        $baseClass = $schema->baseDataClass($list->dataClass());
        $table = $schema->baseDataTable($list->dataClass());
        $dir = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';
        $class = str_replace(['\\', "'"], ['\\\\', "''"], $baseClass);
        $sub = '(SELECT "FDK_LastView"."ViewedAt" FROM "FDK_LastView" WHERE "FDK_LastView"."BaseClass" = \''
            . $class . '\' AND "FDK_LastView"."RecordID" = "' . $table . '"."ID")';
        return $list->orderBy($sub . ' ' . $dir . ', "' . $table . '"."ID" ' . $dir);
    }
}
