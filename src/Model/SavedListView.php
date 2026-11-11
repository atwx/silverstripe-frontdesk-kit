<?php

namespace Atwx\SilverstripeFrontdeskKit\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;

/**
 * A list view saved by a member: filter values and columns of one list,
 * personal or shared with everyone who may see the list.
 *
 * @property ?string $ListKey
 * @property ?string $Title
 * @property ?string $FilterQuery
 * @property ?string $Columns
 * @property bool $Shared
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
