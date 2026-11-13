<?php

namespace Atwx\SilverstripeFrontdeskKit\Model;

use Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController;
use Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskSubController;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;

/**
 * "Recently viewed": one entry per member and record, noted when the member
 * opens the record's page in a controller with `recent_views` enabled. A new
 * view moves the entry to the top; per member and record type (base class)
 * only the latest `max_per_class` entries are kept (a controller can set its
 * own limit with `recent_views: <number>`).
 *
 * @property ?string $ViewedAt
 * @property ?string $BaseClass
 * @property ?string $ControllerClass
 * @property int $MemberID
 * @property int $RecordID
 * @property ?string $RecordClass
 * @method Member Member()
 */
class RecentView extends DataObject
{
    private static string $table_name = 'FDK_RecentView';

    private static array $db = [
        'ViewedAt' => 'Datetime',
        'BaseClass' => 'Varchar(255)',
        'ControllerClass' => 'Varchar(255)',
    ];

    private static array $has_one = [
        'Member' => Member::class,
        'Record' => DataObject::class,
    ];

    private static array $indexes = [
        'MemberRecord' => ['type' => 'index', 'columns' => ['MemberID', 'RecordID']],
        'MemberBase' => ['type' => 'index', 'columns' => ['MemberID', 'BaseClass', 'ViewedAt']],
    ];

    private static string $default_sort = '"ViewedAt" DESC, "ID" DESC';

    /**
     * Entries kept per member and record type, unless the controller sets a
     * number with `recent_views`.
     */
    private static int $max_per_class = 20;

    /**
     * Entries in the block of the start page ($FrontdeskRecentViews).
     */
    private static int $block_limit = 10;

    /**
     * Controller class by record class, for records noted without a controller.
     *
     * @var array<string, string>
     */
    protected static array $controllerCache = [];

    /**
     * Notes that the member (default: the current one) viewed the record.
     * $controllerClass is the controller whose page shows it (default: the
     * first controller managing the record's class); its `recent_views`
     * setting gives the limit.
     */
    public static function note(DataObject $record, string $controllerClass = '', ?Member $member = null): void
    {
        $member ??= Security::getCurrentUser();
        if (!$member || !$record->isInDB()) {
            return;
        }
        $controllerClass = $controllerClass ?: (string) static::controllerFor($record::class);
        $baseClass = $record->baseClass();
        // A new entry per view: the latest has the highest ID, also within a second.
        static::get()->filter([
            'MemberID' => $member->ID,
            'RecordClass' => $record->ClassName,
            'RecordID' => $record->ID,
        ])->removeAll();
        static::create([
            'MemberID' => $member->ID,
            'RecordClass' => $record->ClassName,
            'RecordID' => $record->ID,
            'ViewedAt' => DBDatetime::now()->getValue(),
            'BaseClass' => $baseClass,
            'ControllerClass' => $controllerClass,
        ])->write();

        $ids = static::get()->filter(['MemberID' => $member->ID, 'BaseClass' => $baseClass])->column('ID');
        $old = array_slice($ids, static::limitFor($controllerClass));
        if ($old) {
            static::get()->filter('ID', $old)->removeAll();
        }
    }

    /**
     * Removes the entries of a record (all members), e.g. after deleting it.
     */
    public static function forget(DataObject $record): void
    {
        static::get()->filter([
            'RecordClass' => $record->ClassName,
            // delete() sets the ID to 0 and keeps it as OldID
            'RecordID' => (int) ($record->ID ?: $record->OldID),
        ])->removeAll();
    }

    /**
     * IDs of the member's recently viewed records of a class (and its
     * subclasses), latest first.
     *
     * @param class-string<DataObject> $class
     * @return array<int, int>
     */
    public static function idsFor(string $class, ?Member $member = null): array
    {
        $member ??= Security::getCurrentUser();
        if (!$member) {
            return [];
        }
        return array_map('intval', static::get()->filter([
            'MemberID' => $member->ID,
            'RecordClass' => ClassInfo::subclassesFor($class, true),
        ])->column('RecordID'));
    }

    /**
     * The list restricted to the member's recently viewed records, latest
     * first (used by the list view "Zuletzt angesehen").
     *
     * @template T of DataObject
     * @param DataList<T> $list
     * @return DataList<T>
     */
    public static function applyTo(DataList $list, ?Member $member = null): DataList
    {
        $ids = static::idsFor($list->dataClass(), $member);
        if (!$ids) {
            return $list->filter('ID', 0);
        }
        $table = DataObject::getSchema()->baseDataTable($list->dataClass());
        $cases = '';
        foreach ($ids as $position => $id) {
            $cases .= ' WHEN ' . (int) $id . ' THEN ' . (int) $position;
        }
        return $list->filter('ID', $ids)->orderBy("CASE \"{$table}\".\"ID\"{$cases} END ASC");
    }

    /**
     * The member's recently viewed records across all types, latest first:
     * ArrayData with Record, Title, Type, Link and ViewedAt. Records that are
     * gone are dropped (and their entries removed), records the member may no
     * longer open (FrontdeskController::canViewRecord()) are skipped.
     *
     * @return ArrayList<ArrayData>
     */
    public static function recent(int $limit = 0, ?Member $member = null): ArrayList
    {
        $member ??= Security::getCurrentUser();
        $limit = $limit ?: (int) static::config()->get('block_limit');
        $result = ArrayList::create();
        if (!$member) {
            return $result;
        }
        $entries = static::get()->filter('MemberID', $member->ID);
        $offset = 0;
        $batch = max($limit * 2, 20);
        while ($result->count() < $limit) {
            $page = $entries->limit($batch, $offset)->toArray();
            if (!$page) {
                break;
            }
            foreach ($page as $entry) {
                $item = $entry->item($member);
                if ($item) {
                    $result->push($item);
                    if ($result->count() >= $limit) {
                        break;
                    }
                }
            }
            $offset += $batch;
        }
        return $result;
    }

    /**
     * This entry for display, or null when the record is gone (the entry is
     * then removed) or the member may not see it.
     */
    public function item(?Member $member = null): ?ArrayData
    {
        $record = $this->RecordID && $this->RecordClass && class_exists((string) $this->RecordClass)
            ? DataObject::get_by_id((string) $this->RecordClass, (int) $this->RecordID)
            : null;
        if (!$record) {
            $this->delete();
            return null;
        }
        $controller = $this->controller();
        if (!($controller ? $controller->canViewRecord($record, $member) : $record->canView($member))) {
            return null;
        }
        return ArrayData::create([
            'Record' => $record,
            'Title' => $record->getTitle() ?: $record->i18n_singular_name() . ' #' . $record->ID,
            'Type' => $record->i18n_singular_name(),
            'Link' => $controller?->Link('view/' . $record->ID),
            'ViewedAt' => $this->dbObject('ViewedAt'),
        ]);
    }

    private function controller(): ?FrontdeskController
    {
        $class = (string) $this->ControllerClass;
        if ($class === '' || !is_subclass_of($class, FrontdeskController::class)) {
            $class = (string) static::controllerFor((string) $this->RecordClass);
        }
        return $class !== '' ? singleton($class) : null;
    }

    /**
     * The frontdesk controller showing records of the class: one with
     * `recent_views` enabled first, then any other; the closest managed
     * model wins.
     */
    public static function controllerFor(string $recordClass): ?string
    {
        if (array_key_exists($recordClass, static::$controllerCache)) {
            return static::$controllerCache[$recordClass] ?: null;
        }
        $best = '';
        $bestScore = PHP_INT_MAX;
        $ancestry = array_values(array_reverse(ClassInfo::ancestry($recordClass)));
        foreach (ClassInfo::subclassesFor(FrontdeskController::class, false) as $class) {
            if (
                is_subclass_of($class, FrontdeskSubController::class)
                || (new \ReflectionClass($class))->isAbstract()
            ) {
                continue;
            }
            $model = (string) $class::config()->get('managed_model');
            $distance = array_search($model, $ancestry, true);
            if ($model === '' || $distance === false) {
                continue;
            }
            $score = (int) $distance * 2 + ($class::config()->get('recent_views') ? 0 : 1);
            if ($score < $bestScore) {
                $best = $class;
                $bestScore = $score;
            }
        }
        static::$controllerCache[$recordClass] = $best;
        return $best ?: null;
    }

    public static function limitFor(string $controllerClass): int
    {
        $setting = $controllerClass !== '' && class_exists($controllerClass)
            ? $controllerClass::config()->get('recent_views')
            : null;
        return is_int($setting) && $setting > 0 ? $setting : (int) static::config()->get('max_per_class');
    }
}
