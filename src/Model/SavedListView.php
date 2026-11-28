<?php

namespace Atwx\SilverstripeFrontdeskKit\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;

/**
 * A list view saved by a member ("report"): filter values, search, column
 * sort, grouping and columns of one list, personal or shared with everyone
 * who may see the list. Pinned ones are shown in the view bar; all are in
 * the menu "Reports" and on the reports page.
 *
 * @property ?string $ListKey
 * @property ?string $Title
 * @property ?string $FilterQuery
 * @property ?string $Columns
 * @property bool $Shared
 * @property bool $Pinned
 * @property ?string $Description
 * @property ?string $Search
 * @property ?string $SearchScope
 * @property ?string $SortColumn
 * @property ?string $SortDir
 * @property ?string $GroupBy
 * @property int $OwnerID
 * @method Member Owner()
 */
class SavedListView extends DataObject
{
    private static string $table_name = 'FDK_SavedListView';

    private static array $db = [
        'ListKey' => 'Varchar(100)',
        'Title' => 'Varchar(255)',
        'FilterQuery' => 'Text',
        'Columns' => 'Text',
        'Shared' => 'Boolean',
        // Views saved before reports existed stay in the view bar
        'Pinned' => 'Boolean(1)',
        'Description' => 'Text',
        'Search' => 'Varchar(255)',
        'SearchScope' => 'Varchar(100)',
        'SortColumn' => 'Varchar(100)',
        'SortDir' => 'Varchar(4)',
        'GroupBy' => 'Varchar(100)',
    ];

    private static array $has_one = [
        'Owner' => Member::class,
    ];

    private static array $indexes = [
        'ListKey' => true,
    ];

    private static string $default_sort = 'Title ASC';

    /**
     * Views of a list the current member may use: own and shared ones.
     *
     * @return \SilverStripe\ORM\DataList<SavedListView>
     */
    public static function forList(string $listKey): \SilverStripe\ORM\DataList
    {
        $memberID = (int) Security::getCurrentUser()?->ID;
        return self::get()->filter('ListKey', $listKey)->filterAny([
            'OwnerID' => $memberID ?: -1,
            'Shared' => true,
        ]);
    }

    /**
     * Changed (updated, deleted) by its owner, shared ones also by members
     * who may edit the list.
     */
    public function canManageIn(bool $canEditList): bool
    {
        $memberID = (int) Security::getCurrentUser()?->ID;
        return $memberID > 0 && ((int) $this->OwnerID === $memberID || ($this->Shared && $canEditList));
    }

    /**
     * @return array<string, mixed>
     */
    public function filterValues(): array
    {
        parse_str((string) $this->FilterQuery, $values);
        return $values;
    }

    /**
     * @return array<int, string>|null
     */
    public function columnNames(): ?array
    {
        $names = json_decode((string) $this->Columns, true);
        return is_array($names) && $names ? array_values(array_map('strval', $names)) : null;
    }
}
