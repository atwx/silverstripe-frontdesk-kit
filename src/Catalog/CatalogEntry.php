<?php

namespace Atwx\SilverstripeFrontdeskKit\Catalog;

use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use SilverStripe\View\Parsers\URLSegmentFilter;

/**
 * An entry of a catalog (a list of values maintained by admins, e.g. sports or
 * school types). Entries are referenced by has_one or many_many relations that
 * are declared as catalog fields on the owning class (see Catalogs).
 *
 * - Code: stable key (for imports and stored values), unique within the catalog,
 *   generated from the title when empty, never changed afterwards.
 * - Active: inactive entries can no longer be chosen, but stay visible and
 *   filterable where they are used. Entries in use cannot be deleted.
 * - IsDefault: preselected for new records (one per catalog).
 *
 * Catalogs with own columns per entry use a subclass that fixes its catalog key:
 *
 *   class SchoolReaction extends CatalogEntry
 *   {
 *       private static string $catalog = 'school_reaction';
 *       private static array $db = ['PortalText' => 'Varchar(255)', 'Outcome' => "Enum('positive,negative')"];
 *   }
 *
 * @property ?string $Catalog
 * @property ?string $Code
 * @property ?string $Title
 * @property int $Sort
 * @property bool $Active
 * @property bool $IsDefault
 */
class CatalogEntry extends DataObject
{
    private static string $table_name = 'FDK_CatalogEntry';

    /**
     * Catalog key of a subclass (entries of the subclass always belong to it).
     */
    private static ?string $catalog = null;

    private static array $db = [
        'Catalog' => 'Varchar(64)',
        'Code' => 'Varchar(64)',
        'Title' => 'Varchar(255)',
        'Sort' => 'Int',
        'Active' => 'Boolean',
        'IsDefault' => 'Boolean',
    ];

    private static array $defaults = [
        'Active' => true,
    ];

    private static array $indexes = [
        'CatalogCode' => ['type' => 'unique', 'columns' => ['Catalog', 'Code']],
    ];

    private static array $summary_fields = [
        'Title',
        'Code',
        'Active.Nice',
    ];

    private static string $default_sort = 'Sort ASC, Title ASC';

    public function fieldLabels($includerelations = true): array
    {
        $labels = parent::fieldLabels($includerelations);
        $labels['Catalog'] = _t(self::class . '.db_Catalog', 'Catalog');
        $labels['Code'] = _t(self::class . '.db_Code', 'Code');
        $labels['Title'] = _t(self::class . '.db_Title', 'Title');
        $labels['Sort'] = _t(self::class . '.db_Sort', 'Order');
        $labels['Active'] = $labels['Active.Nice'] = _t(self::class . '.db_Active', 'Active');
        $labels['IsDefault'] = _t(self::class . '.db_IsDefault', 'Default for new records');
        return $labels;
    }

    /**
     * Title with a marker for inactive entries (for selections and views).
     */
    public function getLabel(): string
    {
        return $this->Active
            ? (string) $this->Title
            : _t(self::class . '.INACTIVE_LABEL', '{title} (inactive)', ['title' => (string) $this->Title]);
    }

    public function getCatalogTitle(): string
    {
        return Catalogs::singleton()->title((string) $this->Catalog);
    }

    public function populateDefaults()
    {
        parent::populateDefaults();
        $key = static::config()->get('catalog');
        if ($key) {
            $this->Catalog = $key;
        }
        return $this;
    }

    public function validate(): ValidationResult
    {
        $result = parent::validate();
        $catalogs = Catalogs::singleton();
        $key = (string) $this->Catalog;
        if (!$catalogs->exists($key)) {
            $result->addFieldError('Catalog', _t(self::class . '.CATALOG_UNKNOWN', 'Please choose a catalog.'));
            return $result;
        }
        if (!is_a($this, $catalogs->entryClass($key))) {
            $result->addFieldError('Catalog', _t(self::class . '.CATALOG_CLASS', 'Entries of this catalog have another type.'));
        }
        if (trim((string) $this->Title) === '') {
            $result->addFieldError('Title', _t(self::class . '.TITLE_REQUIRED', 'Please enter a title.'));
        }
        if ($this->isInDB() && $this->isChanged('Code', DataObject::CHANGE_VALUE)) {
            $result->addFieldError('Code', _t(self::class . '.CODE_LOCKED', 'The code cannot be changed.'));
        } elseif ((string) $this->Code !== '' && !preg_match('/^[a-z0-9_-]+$/', (string) $this->Code)) {
            $result->addFieldError('Code', _t(
                self::class . '.CODE_FORMAT',
                'Only lower-case letters, digits, "-" and "_".'
            ));
        } elseif ((string) $this->Code !== '') {
            $duplicate = CatalogEntry::get()
                ->filter(['Catalog' => $key, 'Code' => (string) $this->Code])
                ->exclude('ID', (int) $this->ID ?: 0)
                ->exists();
            if ($duplicate) {
                $result->addFieldError('Code', _t(self::class . '.CODE_TAKEN', 'This code is already used in the catalog.'));
            }
        }
        return $result;
    }

    protected function onBeforeWrite(): void
    {
        parent::onBeforeWrite();
        if ((string) $this->Code === '') {
            $this->Code = $this->uniqueCode((string) $this->Title);
        }
        if (!$this->isInDB() && !$this->Sort) {
            $this->Sort = (int) CatalogEntry::get()->filter('Catalog', (string) $this->Catalog)->max('Sort') + 1;
        }
    }

    protected function onAfterWrite(): void
    {
        parent::onAfterWrite();
        if ($this->IsDefault) {
            foreach (CatalogEntry::get()->filter(['Catalog' => $this->Catalog, 'IsDefault' => true])->exclude('ID', $this->ID) as $other) {
                $other->IsDefault = false;
                $other->write();
            }
        }
    }

    /**
     * Entries in use cannot be deleted (deactivate them instead).
     */
    protected function onBeforeDelete(): void
    {
        if ($this->isInUse()) {
            throw ValidationException::create(_t(
                self::class . '.IN_USE',
                '"{title}" is in use and cannot be deleted; deactivate it instead.',
                ['title' => (string) $this->Title]
            ));
        }
        parent::onBeforeDelete();
    }

    public function isInUse(): bool
    {
        return $this->isInDB() && Catalogs::singleton()->isInUse($this);
    }

    public function canView($member = null): bool
    {
        return (bool) ($member ?? Security::getCurrentUser());
    }

    /**
     * Maintained by members with the edit permission of the catalog page
     * (FDK_CATALOGS_EDIT) and admins.
     */
    public function canEdit($member = null): bool
    {
        return Permission::checkMember($member ?? Security::getCurrentUser(), ['ADMIN', 'FDK_CATALOGS_EDIT']);
    }

    public function canCreate($member = null, $context = []): bool
    {
        return $this->canEdit($member);
    }

    public function canDelete($member = null): bool
    {
        return $this->canEdit($member) && !$this->isInUse();
    }

    private function uniqueCode(string $title): string
    {
        $base = URLSegmentFilter::create()->filter($title) ?: 'entry';
        $base = substr(str_replace('-', '_', $base), 0, 56);
        $code = $base;
        $i = 2;
        while (CatalogEntry::get()->filter(['Catalog' => (string) $this->Catalog, 'Code' => $code])->exists()) {
            $code = $base . '_' . $i++;
        }
        return $code;
    }
}
