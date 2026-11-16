<?php

namespace Atwx\SilverstripeFrontdeskKit\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

/**
 * Columns chosen for a list ("Spalten" menu): per member, or shared (no
 * member) when the controller's columnChoiceShared() says so. The key comes
 * from the controller's columnChoiceKey() (default: the list).
 *
 * @property ?string $ChoiceKey
 * @property ?string $Columns
 * @property int $MemberID
 * @method Member Member()
 */
class ListColumnChoice extends DataObject
{
    private static string $table_name = 'FDK_ListColumnChoice';

    private static array $db = [
        'ChoiceKey' => 'Varchar(150)',
        'Columns' => 'Text',
    ];

    private static array $has_one = [
        'Member' => Member::class,
    ];

    private static array $indexes = [
        'ChoiceKey' => true,
    ];

    public static function find(string $key, int $memberID): ?self
    {
        return self::get()->filter(['ChoiceKey' => $key, 'MemberID' => $memberID])->first();
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
