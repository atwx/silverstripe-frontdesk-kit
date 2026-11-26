<?php

namespace Atwx\SilverstripeFrontdeskKit\Model;

use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;

/**
 * An export of a list set up by a member: title, file name, the chosen
 * columns in their order (with optional widths), for the member or for
 * everyone, optionally only in some list views and with filters of its own.
 *
 * Filters: when the export is set up, the member may keep the filters and
 * the search of the list at that moment. They are applied when the export
 * runs on a list that is not filtered or searched; a filtered list is
 * exported as it is shown.
 *
 * @property ?string $ListKey
 * @property ?string $Title
 * @property ?string $FileName
 * @property ?string $Columns JSON [{name, width}]
 * @property ?string $Views JSON [view keys]; empty: all
 * @property ?string $FilterQuery
 * @property bool $Shared
 * @property int $OwnerID
 * @method Member Owner()
 */
class SavedExport extends DataObject
{
    private static string $table_name = 'FDK_SavedExport';

    private static array $db = [
        'ListKey' => 'Varchar(100)',
        'Title' => 'Varchar(255)',
        'FileName' => 'Varchar(255)',
        'Columns' => 'Text',
        'Views' => 'Text',
        'FilterQuery' => 'Text',
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
     * Exports of a list the current member may run: own and shared ones.
     *
     * @return DataList<SavedExport>
     */
    public static function forList(string $listKey): DataList
    {
        $memberID = (int) Security::getCurrentUser()?->ID;
        return self::get()->filter('ListKey', $listKey)->filterAny([
            'OwnerID' => $memberID ?: -1,
            'Shared' => true,
        ]);
    }

    /**
     * Chosen columns: [['name' => …, 'width' => int|null], …].
     *
     * @return array<int, array{name: string, width: ?int}>
     */
    public function columnList(): array
    {
        $columns = [];
        foreach ((array) json_decode((string) $this->Columns, true) as $entry) {
            $name = is_array($entry) ? (string) ($entry['name'] ?? '') : (string) $entry;
            if ($name === '') {
                continue;
            }
            $width = is_array($entry) && isset($entry['width']) && (int) $entry['width'] > 0 ? (int) $entry['width'] : null;
            $columns[] = ['name' => $name, 'width' => $width];
        }
        return $columns;
    }

    /**
     * @param array<int, array{name: string, width: ?int}> $columns
     */
    public function setColumnList(array $columns): void
    {
        $this->Columns = (string) json_encode(array_values($columns));
    }

    /**
     * @return array<int, string>
     */
    public function viewKeys(): array
    {
        return array_values(array_map('strval', (array) json_decode((string) $this->Views, true)));
    }

    /**
     * Offered in the view ('' = no view chosen)?
     */
    public function isAvailableIn(?string $viewKey): bool
    {
        $views = $this->viewKeys();
        return !$views || in_array((string) $viewKey, $views, true);
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
     * File name without extension; default: the title.
     */
    public function exportFileName(): string
    {
        $name = trim((string) ($this->FileName ?: $this->Title));
        $name = preg_replace('/[^\pL\pN _.-]+/u', '', $name) ?: 'export';
        return $name . ' ' . date('Y-m-d') . '.xlsx';
    }

    /**
     * Changed and deleted by its owner, and by admins.
     */
    public function canManage(?Member $member = null): bool
    {
        $member ??= Security::getCurrentUser();
        if (!$member) {
            return false;
        }
        return (int) $this->OwnerID === (int) $member->ID || Permission::checkMember($member, 'ADMIN');
    }
}
